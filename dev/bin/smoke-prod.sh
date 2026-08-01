#!/usr/bin/env bash
# Smoke-перевірки production-образів і зібраного prod-конфігу. Живого стеку не
# потребує: збирає одноразові образи стадій `prod` і `nginx-prod`, піднімає їх
# через `docker run` і додатково звіряє вивід `docker compose ... config`.
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
nginx_image_tag="okaycms-smoke-nginx-prod:tmp"
net_name="okaycms-smoke-net-$$"
php_stub_name="okaycms-smoke-php-$$"
nginx_stub_name="okaycms-smoke-nginx-$$"
cleanup() {
    docker rm -f "$nginx_stub_name" "$php_stub_name" >/dev/null 2>&1 || true
    docker network rm "$net_name" >/dev/null 2>&1 || true
    docker rmi -f "$image_tag" "$nginx_image_tag" >/dev/null 2>&1 || true
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
# обходить реальний php-fpm entrypoint образу, щоб виконати один рядок.
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
echo "nginx-prod image (regression test: prod nginx must not serve an empty web root)"
# Регресія, яку це ловить: nginx, зібраний не зі стадії nginx-prod, віддавав
# 404 на кожен запит, і жодна перевірка цього не бачила.
if ! docker build -f docker/Dockerfile --target nginx-prod -t "$nginx_image_tag" \
        --build-arg APP_UID="${APP_UID:-1000}" --build-arg APP_GID="${APP_GID:-1000}" \
        .. ; then
    echo "FAIL  could not build the nginx-prod image at all — nothing else in this section can run"
    fails=$((fails + 1))
else
    docker network create "$net_name" >/dev/null
    docker run -d --name "$php_stub_name" --network "$net_name" "$image_tag" >/dev/null

    # vhost-шаблон в образ не копіюється — його підключає прод-оверлей
    # bind-mount'ом. Відтворюємо це руками, щоб перевірка була чесною.
    docker run -d --name "$nginx_stub_name" --network "$net_name" \
        -p "127.0.0.1::80" \
        -e VIRTUAL_HOST="${VIRTUAL_HOST:-okaycms.loc}" \
        -e FASTCGI="$php_stub_name" \
        -v "$(pwd)/config/nginx/templates:/etc/nginx/templates:ro" \
        "$nginx_image_tag" >/dev/null

    # Чекаємо, поки nginx усередині контейнера справді підніметься (шаблон
    # розгортається entrypoint'ом образу при старті), а не фіксований sleep.
    nginx_ready=0
    for _ in $(seq 1 30); do
        if docker exec "$nginx_stub_name" nginx -t >/dev/null 2>&1; then
            nginx_ready=1
            break
        fi
        sleep 1
    done
    if [ "$nginx_ready" -ne 1 ]; then
        echo "FAIL  nginx-prod container did not become ready in time"
        fails=$((fails + 1))
        docker logs "$nginx_stub_name" 2>&1 | tail -20
    else
        nginx_port=$(docker port "$nginx_stub_name" 80/tcp | head -1 | cut -d: -f2)
        # robots.txt, а не index.php: без config.local.php застосунок віддає
        # порожню 500 (коректна прод-поведінка), тож "GET / -> 200" тут не
        # годиться як ознака присутнього дерева.
        expect_contains "nginx-prod: robots.txt is served (application tree is present, not an empty web root)" \
            "200" \
            sh -c "curl -sS -o /dev/null -w '%{http_code}' -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${nginx_port}/robots.txt"
        expect_contains "nginx-prod: a design/ CSS asset is served" \
            "200" \
            sh -c "curl -sS -o /dev/null -w '%{http_code}' -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${nginx_port}/design/okay_shop/css/grid.css"
        # Не 404, а не 200: 500 тут очікувана (див. вище), а 404 означав би
        # фізично відсутній index.php — симптом старого багу.
        expect_missing "nginx-prod: / does not 404 (index.php is present and executes, unlike an empty web root)" \
            "404" \
            sh -c "curl -sS -o /dev/null -w '%{http_code}' -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${nginx_port}/"
        expect_missing "nginx-prod: / is not the stock nginx placeholder" \
            "Welcome to nginx" \
            curl -sS -H "Host: ${VIRTUAL_HOST:-okaycms.loc}" "http://127.0.0.1:${nginx_port}/"
        expect_contains "nginx-prod: /1DB_changes/okay_clean.sql is 404 (present in the image, denied by the vhost)" \
            "404" \
            sh -c "curl -sS -o /dev/null -w '%{http_code}' -H 'Host: ${VIRTUAL_HOST:-okaycms.loc}' http://127.0.0.1:${nginx_port}/1DB_changes/okay_clean.sql"
    fi
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
