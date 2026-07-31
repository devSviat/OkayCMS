#!/usr/bin/env bash
# Smoke-перевірки *production*-образу OkayCMS і зібраного prod-конфігу.
# На відміну від smoke.sh, тут немає запущеного стеку — скрипт збирає
# одноразовий образ stage `prod` і перевіряє його напряму, а також звіряє
# вивід `docker compose ... config` на єдине, що образ сам довести не може:
# чи публікує зібраний стек якісь порти.
#
# Запуск у будь-який момент, без `docker compose up -d`: dev/bin/smoke-prod.sh
set -uo pipefail
cd "$(dirname "$0")/.."   # тепер у dev/

# shellcheck disable=SC1091
# Потрібно лише для MYSQL_*/APP_NAME/NETWORK_NAME/VIRTUAL_HOST, щоб `docker
# compose ... config` нижче міг підставити обов'язкові (":?err") змінні
# базового файлу. Самі значення для цих перевірок не важливі.
set -a; . ./.env; set +a

fails=0
image_tag="okaycms-smoke-prod:tmp"
cleanup() { docker rmi -f "$image_tag" >/dev/null 2>&1 || true; }
trap cleanup EXIT

echo "Building the prod image (target=prod)..."
if ! docker build -f docker/Dockerfile --target prod -t "$image_tag" \
        --build-arg APP_UID="${APP_UID:-1000}" --build-arg APP_GID="${APP_GID:-1000}" \
        .. ; then
    echo "FAIL  could not build the prod image at all — nothing else in this script can run"
    exit 1
fi
echo

# run_in_image <shell-command>: запускає одноразовий контейнер щойно
# зібраного образу (сам видаляється на виході) і забирає вивід. --entrypoint sh
# обходить реальний php-fpm entrypoint образу, щоб виконати один рядок.
run_in_image() {
    docker run --rm --entrypoint sh "$image_tag" -c "$1" 2>&1
}

# dump_actual_output <out>: показує, що насправді видала команда, а не лише
# те, чого ми очікували.
dump_actual_output() {
    local out=$1 len
    len=${#out}
    printf '        actual output (%d bytes), first 300 chars:\n' "$len"
    printf -- '        --- begin actual output ---\n'
    printf '%s' "$out" | head -c 300
    printf '\n'
    printf -- '        --- end actual output ---\n'
}

# expect_contains <description> <needle> <command...>
expect_contains() {
    local desc=$1 needle=$2
    shift 2
    local out
    out=$("$@" 2>&1) || true
    if printf '%s' "$out" | grep -qF -- "$needle"; then
        printf '  ok    %s\n' "$desc"
    else
        printf '  FAIL  %s\n' "$desc"
        printf '        expected output to contain: %s\n' "$needle"
        dump_actual_output "$out"
        fails=$((fails + 1))
    fi
}

# expect_missing <description> <needle> <command...>
expect_missing() {
    local desc=$1 needle=$2
    shift 2
    local out
    out=$("$@" 2>&1) || true
    if printf '%s' "$out" | grep -qF -- "$needle"; then
        printf '  FAIL  %s\n' "$desc"
        printf '        expected output NOT to contain: %s\n' "$needle"
        dump_actual_output "$out"
        fails=$((fails + 1))
    else
        printf '  ok    %s\n' "$desc"
    fi
}

echo "Filesystem: dev-only and secret-bearing files must not ship"
# Найважливіша перевірка тут. config/config.local.php у .gitignore, але
# Docker-білд читає файлову систему, а не git — без відповідного рядка в
# кореневому .dockerignore реальний пароль бази розробника потрапив би в
# кожен зібраний з цього checkout образ.
expect_contains "config/config.local.php did not make it into the image" \
    "absent" \
    run_in_image 'test -f /var/www/html/config/config.local.php && echo present || echo absent'
expect_contains "dev/.env did not make it into the image" \
    "absent" \
    run_in_image 'test -f /var/www/html/dev/.env && echo present || echo absent'

echo
echo "PHP configuration: prod-only, nothing dev-only"
expect_contains "dev.ini is absent from the prod stage" \
    "absent" \
    run_in_image 'test -f /usr/local/etc/php/custom.d/dev.ini && echo present || echo absent'
expect_contains "xdebug.ini is absent from the prod stage" \
    "absent" \
    run_in_image 'test -f /usr/local/etc/php/custom.d/xdebug.ini && echo present || echo absent'
expect_contains "prod.ini is present" \
    "present" \
    run_in_image 'test -f /usr/local/etc/php/custom.d/prod.ini && echo present || echo absent'
expect_missing "xdebug is not among the loaded extensions" \
    "xdebug" \
    run_in_image 'php -m'

echo
echo "Build artifacts"
expect_contains "vendor/autoload.php is present (composer install ran)" \
    "present" \
    run_in_image 'test -f /var/www/html/vendor/autoload.php && echo present || echo absent'

echo
echo "Composed prod config"
# Найважливіша властивість docker-compose.prod.yml: Dokploy підключає Traefik
# прямо до контейнера, тож зібраний стек не повинен публікувати нічого.
# Перевірка конфігу — не те саме, що перевірка запущеного стеку, але це
# єдине, що можна перевірити без реального Dokploy/Traefik, і вона ловить
# випадковий `ports:`, що прокрався в один із файлів.
#
# Це мають бути дві окремі перевірки: якщо docker-compose.prod.yml зникне,
# `docker compose ... config` впаде з "no such file", і цей текст так само не
# містить "published:" — самотній expect_missing пройшов би, але з хибної
# причини (команда впала, а не "портів не знайдено"). Тому спершу перевіряємо,
# що конфіг взагалі згенерувався.
expect_contains "docker compose config (base + prod overlay) renders successfully" \
    "services:" \
    docker compose -f docker-compose.yml -f docker-compose.prod.yml config
expect_missing "docker compose config (base + prod overlay) publishes no ports" \
    "published:" \
    docker compose -f docker-compose.yml -f docker-compose.prod.yml config

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
