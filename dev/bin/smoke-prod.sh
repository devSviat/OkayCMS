#!/usr/bin/env bash
# Smoke-перевірки production-образу і зібраного prod-конфігу. Живого стеку не
# потребує: збирає одноразовий образ стадії `prod`, піднімає його через
# `docker run` і додатково звіряє вивід `docker compose ... config`.
#
# Образ один: вебсервер і PHP — той самий процес (FrankenPHP).
#
# Запуск: dev/bin/smoke-prod.sh
set -uo pipefail
cd "$(dirname "$0")/.."   # тепер у dev/

# shellcheck disable=SC1091
# Потрібно лише для MYSQL_*/APP_NAME/VIRTUAL_HOST, щоб `docker compose ...
# config` нижче міг підставити обов'язкові (":?err") змінні базового файлу.
# Самі значення для цих перевірок не важливі.
set -a; . ./.env; set +a

fails=0
image_tag="okaycms-smoke-prod:tmp"
app_stub_name="okaycms-smoke-app-$$"
cleanup() {
    docker rm -f "$app_stub_name" >/dev/null 2>&1 || true
    docker rmi -f "$image_tag" >/dev/null 2>&1 || true
}
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
# обходить реальний entrypoint образу, щоб виконати один рядок.
run_in_image() {
    docker run --rm --entrypoint sh "$image_tag" -c "$1" 2>&1
}

# Показує, що команда видала насправді, а не лише чого ми очікували.
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
# Найважливіша перевірка тут: білд читає файлову систему, а не git, тож без
# рядка в .dockerignore пароль дев-бази потрапив би в кожен образ.
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
echo "prod image over HTTP (regression test: the web root must not be empty)"
# Регресія, яку це ловить: образ, зібраний без дерева застосунку, віддає 404 на
# кожен запит. Вебсервер вбудований у той самий прод-образ, тож перевіряється
# рівно те, що поїде в продакшн.
# Caddyfile запечений в образ (COPY у стадії base), тож монтувати нічого не треба.
if ! docker run -d --name "$app_stub_name" -p "127.0.0.1::8080" "$image_tag" >/dev/null; then
    echo "FAIL  the prod container could not be started"
    fails=$((fails + 1))
fi

# Чекаємо, поки сервер справді почне відповідати, а не фіксований sleep.
app_port=""
app_ready=0
# Гейт мусить бути і суворим, і повним: `curl -sS` без -f зараховує будь-яку
# відповідь, включно з 500 на ще не піднятому застосунку, а robots.txt іде через
# file_server і не доводить, що PHP виконується. Звідси другий крок — запит у
# фронт-контролер, від якого без бази очікується 500.
for _ in $(seq 1 60); do
    app_port=$(docker port "$app_stub_name" 8080/tcp 2>/dev/null | head -1 | cut -d: -f2)
    [ -n "$app_port" ] || { sleep 1; continue; }

    curl -fsS -o /dev/null "http://127.0.0.1:${app_port}/robots.txt" 2>/dev/null || { sleep 1; continue; }

    front_code=$(curl -sS -o /dev/null -w '%{http_code}' \
        "http://127.0.0.1:${app_port}/" 2>/dev/null || echo "000")
    if [ "$front_code" = "500" ] || [ "$front_code" = "200" ]; then
        app_ready=1
        break
    fi
    sleep 1
done
if [ "$app_ready" -ne 1 ]; then
    echo "FAIL  the prod container did not start serving in time"
    fails=$((fails + 1))
    docker logs "$app_stub_name" 2>&1 | tail -20
else
    # robots.txt, а не index.php: без config.local.php застосунок віддає
    # порожню 500 (коректна прод-поведінка), тож "GET / -> 200" тут не
    # годиться як ознака присутнього дерева.
    expect_contains "prod image: robots.txt is served (application tree is present, not an empty web root)" \
        "200" \
        sh -c "curl -sS -o /dev/null -w '%{http_code}' -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${app_port}/robots.txt"
    expect_contains "prod image: a design/ CSS asset is served" \
        "200" \
        sh -c "curl -sS -o /dev/null -w '%{http_code}' -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${app_port}/design/okay_shop/css/grid.css"
    # Не 404, а не 200: 500 тут очікувана (див. вище), а 404 означав би
    # фізично відсутній index.php — симптом старого багу.
    expect_missing "prod image: / does not 404 (index.php is present and executes, unlike an empty web root)" \
        "404" \
        sh -c "curl -sS -o /dev/null -w '%{http_code}' -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${app_port}/"
    # Доводить, що діє саме наш білий список, а не дефолтний file_server:
    # дозволений шлях віддається, а сусідній у тій самій теці — ні.
    expect_contains "prod image: the whitelist is in effect (an allowed asset is served)" \
        "200" \
        sh -c "curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:${app_port}/design/okay_shop/preview.png"
    expect_missing "prod image: the whitelist is in effect (a sibling .tpl is not served)" \
        "200" \
        sh -c "curl -sS -o /dev/null -w '%{http_code}' http://127.0.0.1:${app_port}/design/okay_shop/html/index.tpl"
    # Білий список кореня, перевірений саме на прод-образі. Тут це важить
    # найбільше: образ копіює дерево застосунку як є, разом із vendor/,
    # тестами й дампом бази.
    #
    # Перевіряється вміст, а не код відповіді. Після інверсії все, що не
    # дозволене явно, іде у фронт-контролер, а він у цьому стенді без
    # config.local.php віддає 500 — тобто «не 404, але й не файл». Значуще
    # тут одне: вміст файлу назовні не поїхав. Маркер для кожного шляху —
    # рядок, який у самому файлі точно є.
    while IFS='|' read -r p marker; do
        [ -z "$p" ] && continue
        body=$(curl -sS -H "Host: ${VIRTUAL_HOST:-okaycms.loc}" \
            "http://127.0.0.1:${app_port}$p" 2>&1)
        code=$(curl -sS -o /dev/null -w '%{http_code}' -H "Host: ${VIRTUAL_HOST:-okaycms.loc}" \
            "http://127.0.0.1:${app_port}$p" 2>/dev/null)
        # 000 — curl не достукався. Без цієї гілки недоступний контейнер
        # давав би "ok" на кожному шляху: тіло з тексту помилки curl із
        # маркером не збігається ніколи.
        if [ "$code" = "000" ]; then
            printf '  FAIL  prod image: %s unreachable (curl failed)\n' "$p"
            dump_actual_output "$body"
            fails=$((fails + 1))
        elif [ "$code" = "200" ] || [[ "$body" == *"$marker"* ]]; then
            printf '  FAIL  prod image: %s leaks (HTTP %s)\n' "$p" "$code"
            dump_actual_output "$body"
            fails=$((fails + 1))
        else
            printf '  ok    prod image: %s does not leak (HTTP %s, no "%s" in body)\n' "$p" "$code" "$marker"
        fi
    done <<'PATHS'
/1DB_changes/okay_clean.sql|CREATE TABLE
/vendor/composer/installed.json|"packages"
/vendor/autoload.php|ComposerAutoloader
/vendor/bin/phpunit|PHPUnit
/ok|ServiceLocator
/composer.json|"require"
/composer.lock|"content-hash"
/phpunit.xml|testsuite
/tests/bootstrap.php|require_once
/docs/README.md|OkayCMS
/config/config.php|db_server
/dev/docker-compose.yml|services:
/Okay/Core/Response.php|class Response
/backend/lang/ru.php|<?php
/design/okay_shop/js.php|<?php
/worker.php|frankenphp_handle_request
PATHS
    # Контроль самого вимірювача: маркер публічного файлу мусить
    # знаходитись, інакше «маркера немає» нічого не доводить.
    expect_contains "prod image: the leak check itself works (robots.txt content is found)" \
        "User-agent" \
        sh -c "curl -sS -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${app_port}/robots.txt"
    # Точна версія PHP у заголовку — джерело вимкнено в okay.ini, який
    # потрапляє і в прод-образ; Caddyfile ховає його другим рубежем
    # (`header -X-Powered-By`).
    # X-Powered-CMS: OkayCMS лишається свідомо — версії в ньому немає.
    expect_missing "prod image: X-Powered-By is not sent" \
        "X-Powered-By" \
        sh -c "curl -sSI -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${app_port}/robots.txt"
fi

echo
echo "Composed prod config"
# Дві окремі перевірки навмисно: якщо конфіг не згенерується, вивід так само
# не міститиме "published:", і самотній expect_missing пройшов би з хибної
# причини — команда впала, а не "портів не знайдено".
expect_contains "docker compose config (base + prod overlay) renders successfully" \
    "services:" \
    docker compose -f docker-compose.yml -f docker-compose.prod.yml config
expect_missing "docker compose config (base + prod overlay) publishes no ports" \
    "published:" \
    docker compose -f docker-compose.yml -f docker-compose.prod.yml config
# docker-compose.override.yml is tracked and auto-loads on a bare
# `docker compose up`, but an explicit -f list (as above and in the
# documented prod invocation) never includes it — mailpit and db-init are
# defined only there. Confirms that in practice, not just by reading the file.
expect_missing "docker compose config (base + prod overlay) has no mailpit service" \
    "mailpit" \
    docker compose -f docker-compose.yml -f docker-compose.prod.yml config
expect_missing "docker compose config (base + prod overlay) has no db-init service" \
    "db-init" \
    docker compose -f docker-compose.yml -f docker-compose.prod.yml config

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
