#!/usr/bin/env bash
# Smoke-перевірка кореневого .htaccess на справжньому Apache з mod_php.
#
# Навіщо окремий скрипт: форк ставлять і на звичайний хостинг, де немає ані
# Docker, ані доступу до конфігу віртуального хоста — там .htaccess єдиний
# важіль. smoke.sh і smoke-prod.sh перевіряють лише nginx-стек, тож без цього
# третя реалізація того самого білого списку лишалась би без перевірки.
#
# Запуск: dev/bin/smoke-apache.sh
#
# Дерево монтується як є, БД немає — застосунок віддає 500, і це нормально:
# перевіряється не робота магазину, а що саме Apache віддає з диска. Тому
# перевірка йде по ВМІСТУ відповіді, а не по коду: 500 і 404 однаково означають
# «не віддано», а от рядок із файлу в тілі означає витік.
set -uo pipefail
cd "$(dirname "$0")/../.."   # корінь репозиторію
repo=$(pwd)

image="php:8.4-apache"
name="okaycms-smoke-apache-$$"

cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; }
trap cleanup EXIT

echo "Starting $image with the application tree mounted read-only..."
if ! docker run -d --name "$name" -p 127.0.0.1::80 -v "$repo":/var/www/html:ro "$image" >/dev/null; then
    echo "FAIL  could not start the Apache container — nothing else in this script can run"
    exit 1
fi

# У штатному образі AllowOverride = None, тобто .htaccess не читається зовсім.
# Без цього кроку весь прогін нічого не доводив би.
docker exec "$name" sh -c \
    "sed -i 's#AllowOverride None#AllowOverride All#g' /etc/apache2/apache2.conf \
     && a2enmod rewrite headers >/dev/null && apache2ctl -k graceful" >/dev/null 2>&1

port=""
for _ in $(seq 1 30); do
    port=$(docker port "$name" 80/tcp 2>/dev/null | head -1 | cut -d: -f2)
    [ -n "$port" ] && curl -sS -o /dev/null "http://127.0.0.1:${port}/robots.txt" 2>/dev/null && break
    sleep 1
done
if [ -z "$port" ]; then
    echo "FAIL  Apache container did not become ready in time"
    docker logs "$name" 2>&1 | tail -20
    exit 1
fi
base="http://127.0.0.1:${port}"

fails=0

# Контроль: .htaccess мусить справді застосовуватись. /admin -> backend —
# правило саме з цього файлу, тож без нього шлях дав би 404 від Apache.
control=$(curl -sS -o /dev/null -w '%{http_code}' "$base/admin" 2>/dev/null || echo 000)
if [ "$control" = "404" ] || [ "$control" = "000" ]; then
    echo "FAIL  .htaccess is not applied (GET /admin -> $control) — every check below would prove nothing"
    docker logs "$name" 2>&1 | tail -10
    exit 1
fi
printf 'control: .htaccess is applied (GET /admin -> %s)\n\n' "$control"

# expect_closed <path> <marker>: вміст файлу не повинен доїхати до клієнта.
expect_closed() {
    local path=$1 marker=$2 code body
    code=$(curl -sS -o /dev/null -w '%{http_code}' "$base$path" 2>/dev/null || echo 000)
    body=$(curl -sS "$base$path" 2>/dev/null | head -c 4000)
    if [ "$code" = "200" ] && [[ "$body" == *"$marker"* ]]; then
        printf '  FAIL  %-46s leaks (HTTP %s, "%s" in body)\n' "$path" "$code" "$marker"
        fails=$((fails + 1))
    else
        printf '  ok    %-46s closed (HTTP %s)\n' "$path" "$code"
    fi
}

# expect_served <path>: дозволений шлях мусить віддаватись.
expect_served() {
    local path=$1 code
    code=$(curl -sS -o /dev/null -w '%{http_code}' "$base$path" 2>/dev/null || echo 000)
    if [ "$code" = "200" ]; then
        printf '  ok    %-46s served\n' "$path"
    else
        printf '  FAIL  %-46s must be served, got HTTP %s\n' "$path" "$code"
        fails=$((fails + 1))
    fi
}

echo "Closed"
expect_closed /1DB_changes/okay_clean.sql            "CREATE TABLE"
expect_closed /composer.json                         '"require"'
expect_closed /composer.lock                         "content-hash"
expect_closed /phpunit.xml                           "testsuite"
expect_closed /phpstan.neon                          "parameters"
expect_closed /phpcs.xml.dist                        "ruleset"
expect_closed /ok                                    "ServiceLocator"
expect_closed /README.md                             "OkayCMS"
expect_closed /CLAUDE.md                             "CLAUDE"
expect_closed /dev/.env                              "MYSQL"
expect_closed /config/config.php                     "db_server"
expect_closed /Okay/Core/Response.php                "class Response"
expect_closed /tests/bootstrap.php                   "require_once"
expect_closed /docs/README.md                        "OkayCMS"
expect_closed /vendor/composer/installed.json        '"packages"'
expect_closed /vendor/autoload.php                   "ComposerAutoloader"
expect_closed /backend/design/html/index.tpl         "{"
expect_closed /backend/Controllers/AuthAdmin.php     "class AuthAdmin"
expect_closed /backend/lang/ru.php                   "<?php"
expect_closed /backend/design/js/filemanager/config/config.php "<?php"
expect_closed /.htaccess                             "RewriteEngine"
expect_closed /.gitignore                            "vendor"
tpl=$(find compiled -name '*.php' 2>/dev/null | head -1)
[ -n "$tpl" ] && expect_closed "/$tpl" "Smarty"

echo
echo "Served"
expect_served /robots.txt
for probe in \
    "$(ls design/*/css/*.css 2>/dev/null | head -1)" \
    "$(ls design/*/preview.png 2>/dev/null | head -1)" \
    "$(find js_libraries -name '*.js' 2>/dev/null | head -1)" \
    "$(find backend/design/css -name '*.css' 2>/dev/null | head -1)" \
    "$(find Okay/Modules -path '*design/images/*.png' 2>/dev/null | head -1)" \
    "$(ls Okay/Modules/*/*/preview.png 2>/dev/null | head -1)" \
    "$(find files/resized -name '*.jpg' 2>/dev/null | head -1)"; do
    [ -n "$probe" ] && expect_served "/$probe"
done

# Контроль вимірювача: вміст публічного файлу мусить знаходитись, інакше
# «маркера немає» вище нічого не доводить.
if curl -sS "$base/robots.txt" 2>/dev/null | grep -q "User-agent"; then
    printf '  ok    %-46s the leak check itself works\n' "(control)"
else
    printf '  FAIL  %-46s robots.txt content not found — the leak check proves nothing\n' "(control)"
    fails=$((fails + 1))
fi

echo
echo "Routing"
# Маршрути вітрини мусять доходити до index.php. Без БД це 500 — важливо, що
# не 404 від Apache і не цикл переписування.
for pg in / /cart /catalog/tehnika-dlya-doma /some/deep/unknown/url; do
    code=$(curl -sS -o /dev/null -w '%{http_code}' "$base$pg" 2>/dev/null || echo 000)
    if [ "$code" = "404" ] || [ "$code" = "000" ]; then
        printf '  FAIL  %-46s did not reach the front controller (HTTP %s)\n' "$pg" "$code"
        fails=$((fails + 1))
    else
        printf '  ok    %-46s reaches the front controller (HTTP %s)\n' "$pg" "$code"
    fi
done
if docker logs "$name" 2>&1 | grep -qi "exceeded the limit of .* internal redirects"; then
    printf '  FAIL  %-46s rewrite loop in .htaccess\n' "(apache log)"
    fails=$((fails + 1))
else
    printf '  ok    %-46s no rewrite loops in the Apache log\n' "(apache log)"
fi

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
