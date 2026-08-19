#!/usr/bin/env bash
# Smoke-перевірки dev-оточення OkayCMS.
# Запуск у будь-який момент після `docker compose up -d`: dev/bin/smoke.sh
# Сам чекає, поки healthchecked-сервіси стануть healthy — `sleep` перед ним
# не потрібен.
set -uo pipefail
cd "$(dirname "$0")/.."

# shellcheck disable=SC1091
set -a; . ./.env; set +a

# Чекає, поки кожен сервіс із healthcheck стане "healthy". db-init тут не
# чекається — він одноразовий і "healthy" не покаже ніколи.
wait_for_healthy() {
    local timeout=120 waited=0 services svc cid status all_healthy

    if command -v jq >/dev/null 2>&1; then
        services=$(docker compose config --format json 2>/dev/null \
            | jq -r '.services | to_entries[] | select(.value.healthcheck != null) | .key')
    else
        # Без jq — жорстко заданий список. Додаючи healthcheck новому сервісу,
        # онови і цей список.
        services="mariadb php85 scheduler"
    fi

    printf 'Waiting for services to become healthy: %s\n' "$(echo "$services" | tr '\n' ' ')"

    while [ "$waited" -lt "$timeout" ]; do
        all_healthy=1
        for svc in $services; do
            cid=$(docker compose ps -q "$svc" 2>/dev/null)
            status=$(docker inspect -f '{{.State.Health.Status}}' "$cid" 2>/dev/null || echo "unknown")
            [ "$status" = "healthy" ] || all_healthy=0
        done
        if [ "$all_healthy" -eq 1 ]; then
            printf 'all services healthy after %ss\n\n' "$waited"
            return 0
        fi
        printf '.'
        sleep 2
        waited=$((waited + 2))
    done

    echo
    printf 'timed out after %ss waiting for services to become healthy:\n' "$timeout"
    for svc in $services; do
        cid=$(docker compose ps -q "$svc" 2>/dev/null)
        status=$(docker inspect -f '{{.State.Health.Status}}' "$cid" 2>/dev/null || echo "unknown")
        printf '  %-8s %s\n' "$svc" "$status"
    done
    exit 1
}

wait_for_healthy

# db-init не має healthcheck, а перевірка "admin manager exists" нижче
# залежить від його завершення.
wait_for_db_init() {
    local timeout=120 waited=0 cid status exit_code

    printf 'Waiting for db-init to finish: '
    while [ "$waited" -lt "$timeout" ]; do
        cid=$(docker compose ps -a -q db-init 2>/dev/null)
        status=$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null || echo "unknown")
        if [ "$status" = "exited" ]; then
            exit_code=$(docker inspect -f '{{.State.ExitCode}}' "$cid" 2>/dev/null || echo "unknown")
            echo
            if [ "$exit_code" = "0" ]; then
                printf 'db-init finished successfully after %ss\n\n' "$waited"
                return 0
            fi
            printf 'db-init exited with code %s (expected 0)\n' "$exit_code"
            printf 'db-init logs:\n'
            docker compose logs db-init
            exit 1
        fi
        printf '.'
        sleep 2
        waited=$((waited + 2))
    done

    echo
    printf 'timed out after %ss waiting for db-init to finish, current state:\n' "$timeout"
    if [ -n "${cid:-}" ]; then
        docker inspect -f '  status={{.State.Status}} exitCode={{.State.ExitCode}}' "$cid" 2>/dev/null
    else
        echo "  db-init container not found"
    fi
    exit 1
}

wait_for_db_init

fails=0

# dump_actual_output <out>: при невдачі друкує реальний вивід команди, щоб
# було видно, чи вона впала, повернула порожній рядок чи щось інше.
dump_actual_output() {
    local out=$1 len
    len=${#out}
    printf '        actual output (%d bytes), first 300 chars:\n' "$len"
    printf -- '        --- begin actual output ---\n'
    printf '%s' "$out" | head -c 300
    printf '\n'
    printf -- '        --- end actual output ---\n'
}

# expect_status <expected code> <path>: HTTP-код на шлях вітрини. Окремо від
# expect_contains, бо тут перевіряється саме код, а не тіло — тіло сторінки 404
# застосунку виглядає як звичайна сторінка й будь-яку перевірку тексту пройшло б.
expect_status() {
    local expected=$1 path=$2 actual
    actual=$(curl -sS -o /dev/null -w '%{http_code}' \
        -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}${path}" 2>/dev/null || echo "000")
    if [ "$actual" = "$expected" ]; then
        printf '  ok    %-3s %s\n' "$actual" "$path"
    else
        printf '  FAIL  %s\n' "$path"
        printf '        expected HTTP %s, actual: %s\n' "$expected" "$actual"
        fails=$((fails + 1))
    fi
}

# expect_contains <description> <needle> <command...>
expect_contains() {
    local desc=$1 needle=$2
    shift 2
    local out
    out=$("$@" 2>&1) || true
    # Шаблоном bash, а не `printf | grep -q`: під pipefail grep -q виходить на
    # першому збігу, printf ловить SIGPIPE — і конвеєр падає саме тоді, коли
    # голку знайдено.
    if [[ "$out" == *"$needle"* ]]; then
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
    # Той самий шаблон bash замість `printf | grep -q` — див. коментар вище.
    if [[ "$out" == *"$needle"* ]]; then
        printf '  FAIL  %s\n' "$desc"
        printf '        expected output NOT to contain: %s\n' "$needle"
        dump_actual_output "$out"
        fails=$((fails + 1))
    else
        printf '  ok    %s\n' "$desc"
    fi
}

echo "PHP configuration"
expect_contains "stock extension ini files are not shadowed" \
    "docker-php-ext-pdo_mysql.ini" \
    docker compose exec -T php85 ls /usr/local/etc/php/conf.d
expect_contains "custom.d is on the scan path" \
    "custom.d" \
    docker compose exec -T php85 php --ini
expect_contains "memory_limit comes from okay.ini" \
    "1024M" \
    docker compose exec -T php85 php -r 'echo ini_get("memory_limit");'
expect_contains "timezone is Europe/Kyiv" \
    "Europe/Kyiv" \
    docker compose exec -T php85 php -r 'echo ini_get("date.timezone");'

for ext in pdo_mysql mysqli gd zip xsl xmlwriter SimpleXML dom xmlreader curl mbstring json; do
    expect_contains "extension loaded: $ext" "$ext" \
        docker compose exec -T php85 php -m
done

echo
echo "Database"
mariadb_cid=$(docker compose ps -q mariadb)
# Саме монтування в /var/lib/mysql, а не "чи є хоч один том": mariadb має ще
# том сідового дампа, тож грубіша перевірка пройшла б і з базою в bind mount.
# {{.Type}} тут може бути лише volume/bind/tmpfs, тож "volume" виключає bind.
expect_contains "the database is on a named volume, not a bind mount" \
    "volume" \
    docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Type}}{{end}}{{end}}' "$mariadb_cid"
# Що пароль підходить, а не що в базі конкретний хеш: OkayCMS перехешовує
# застарілі формати після входу, і звірка з рядком дампа ламалась.
admin_hash=$(docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT password FROM ok_managers WHERE login = \"admin\";"' \
    2>/dev/null | tr -d '\r\n')
expect_contains "admin still authenticates with the default password" \
    "OK" \
    docker compose exec -T php85 php -r \
    'require "/var/www/html/vendor/autoload.php"; $h = new Okay\Core\Security\PasswordHasher(); echo $h->verify("1234", $argv[1]) ? "OK" : "FAIL";' \
    -- "$admin_hash"
# Реальний запит з хоста, а не TCP-хендшейк: саме це потрібно DBeaver. Зсередини
# контейнера втрату dev-приєднання до frontend не видно.
expect_contains "mariadb is reachable from the host, not just from inside a container" \
    "ok_managers" \
    docker run --rm --network host mariadb:10.11 mariadb \
    -h 127.0.0.1 -P "${MYSQL_PORT}" -uroot -p"${MYSQL_ROOT_PASSWORD}" \
    -e "SHOW TABLES LIKE 'ok_managers';" "${MYSQL_DATABASE}"

echo
echo "Network segmentation"
# Ім'я мережі рахується з APP_NAME, а не вичитується з самого контейнера —
# інакше перевірка звіряла б список сам із собою і не могла б упасти.
backend_net="$(printf '%s' "${APP_NAME:?err}" | tr '[:upper:]' '[:lower:]')_backend"
expect_contains "the backend network is internal (no route off the host)" \
    "true" \
    docker network inspect "$backend_net" --format '{{.Internal}}'
expect_contains "mariadb is attached to the backend network" \
    "$backend_net" \
    docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$mariadb_cid"
# У dev mariadb додатково у frontend, інакше опублікований порт був би тихим
# no-op'ом: для internal-мережі Docker не створює NAT. У прод — лише backend.
# Вебсервер і PHP — один контейнер (FrankenPHP), тож перевіряти нічого
# резолвити. Лишається структурний факт: php85 мусить бути в обох мережах —
# у frontend, щоб публікація порту працювала, і в backend, щоб бачити базу.
frontend_net="$(printf '%s' "${APP_NAME:?err}" | tr '[:upper:]' '[:lower:]')_frontend"
php_cid=$(docker compose ps -q php85)
expect_contains "php85 is attached to the frontend network (it is the web tier now)" \
    "$frontend_net" \
    docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$php_cid"
expect_contains "php85 is attached to the backend network (it reaches the database)" \
    "$backend_net" \
    docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$php_cid"

echo
echo "Scheduler"
scheduler_cid=$(docker compose ps -q scheduler)
expect_contains "the scheduler container is running" \
    "true" \
    docker inspect -f '{{.State.Running}}' "$scheduler_cid"
# Доводить лише, що процес supercronic під tini живий — не що якесь
# заплановане завдання виконалось успішно (див. healthcheck у docker-compose.yml).
expect_contains "supercronic is running inside the scheduler container" \
    "supercronic" \
    docker compose exec -T scheduler pgrep -fa supercronic

echo
echo "Logging"
# Caddy пише access-лог у JSON, тож маркер — поле методу, а не рядок "GET /".
expect_contains "access logs reach docker compose logs" \
    '"method":"GET"' \
    sh -c "curl -sS -o /dev/null -H 'Host: ${VIRTUAL_HOST}' http://127.0.0.1:${HTTP_PORT}/ ; sleep 1 ; docker compose logs --tail=20 php85"
# Результат, а не конфіг: запит з унікальним маркером, потім пошук маркера в
# усьому дереві — не залежить від імені й шляху лог-файлу.
log_marker="smoke-nolog-$$"
curl -sS -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
    "http://127.0.0.1:${HTTP_PORT}/${log_marker}" 2>/dev/null || true
sleep 1
expect_missing "the web server writes no log files into the working tree" \
    "$log_marker" \
    docker compose exec -T php85 sh -c \
    "grep -r '$log_marker' /var/www/html 2>/dev/null"
# Саме grep -r, а не -rl: -l друкує ІМЕНА файлів, а не їх вміст, тож маркера
# у виводі не було б ніколи і expect_missing проходив би завжди незалежно від
# результату. Асершен має звірятись з тим, що команда справді друкує.

echo
echo "Mail"
expect_contains "mail() is routed to Mailpit via msmtp" \
    "msmtp" \
    docker compose exec -T php85 php -r 'echo ini_get("sendmail_path");'
expect_contains "the SMTP settings point at Mailpit" \
    "mailpit" \
    docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT value FROM ok_settings WHERE param = \"smtp_server\";"'
# Mailpit накопичує листи між прогонами, тож звіряється приріст лічильника, а
# не абсолютне число.
mailpit_total() {
    curl -sS "http://127.0.0.1:${MAILPIT_PORT:-8025}/api/v1/messages?limit=1" \
        | grep -o '"total":[0-9]*' | head -1 | grep -o '[0-9]*'
}
before_total=$(mailpit_total)
before_total=${before_total:-0}
docker compose exec -T php85 php -r 'mail("smoke@example.com", "smoke test", "body");' >/dev/null 2>&1
sleep 2
after_total=$(mailpit_total)
after_total=${after_total:-0}
if [ -n "$after_total" ] && [ "$after_total" -gt "$before_total" ] 2>/dev/null; then
    printf '  ok    %s (total %s -> %s)\n' "a message sent from PHP arrives in Mailpit" "$before_total" "$after_total"
else
    printf '  FAIL  %s\n' "a message sent from PHP arrives in Mailpit"
    printf '        expected total to increase from %s, actual: %s\n' "$before_total" "$after_total"
    fails=$((fails + 1))
fi

echo
echo "Web"
# Саме з Host: localhost. Раніше тут ловився регрес "штатний default.conf
# образу nginx виграв у нашого віртуального хоста". У FrankenPHP конкурентного
# server-блоку немає, натомість є інший регрес того ж класу: якщо наш Caddyfile
# не підхопився, працює штатний конфіг образу з коренем /app/public, якого в
# нашому образі немає — і вітрина не віддається взагалі. Перевірка нижче ловить
# саме це.
expect_contains "http://localhost/ serves the storefront" \
    "OkayCMS" \
    curl -sS -H "Host: localhost" "http://127.0.0.1:${HTTP_PORT}/"
expect_contains "the virtual host still serves the storefront" \
    "OkayCMS" \
    curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/"

# У dev error_reporting = E_ALL, тож Notice друкується в сторінку до
# заголовків і ламає редіректи, а не лише псує вигляд.
#
# Покривають лише GET-рендеринг: логіку відправки форм — ні.
for pg in "/" "/cart" "/blog" "/brands"; do
    expect_missing "no PHP diagnostics leak into the page: ${pg}" \
        "Deprecated:" \
        curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}${pg}"
done

echo
echo "Public surface"
# Конфіг nginx — білий список: файл з диска віддається лише там, де є явний
# location. Перевіряються обидві сторони — і що дозволене працює, і що решта
# закрита. tests/Security/PublicSurfaceTest.php стереже це ж статично, у CI;
# тут — реальні коди відповідей.

# Позитивний контроль іде першим: без нього весь блок нижче проходив би і на
# повністю зламаному сайті, який віддає 404 на все.
expect_status 200 /
expect_status 200 /robots.txt
expect_status 302 /backend/
# Канонізація index.php мусить спрацьовувати лише без рядка запиту. У nginx це
# виходило само: умова зіставлялась із $request_uri, який містить query, тож
# /backend/index.php?controller=… під `…index\.php$` не підпадав. У Caddy
# path_regexp бачить лише шлях — і без окремої умови цей редірект з'їдав
# параметри, ламаючи вхід в адмінку: саме туди ядро шле неавторизованого
# менеджера.
expect_status 301 "/backend/index.php"
expect_status 200 "/backend/index.php?controller=AuthAdmin"
# У nginx умова стоїть під `~*`, тож канонізація не зважає на регістр.
expect_status 301 "/INDEX.PHP"
# Оригінали завантажень назовні не віддаються, але це не серверна 404: і nginx,
# і .htaccess шлють їх у фронт-контролер, щоб магазин намалював свою сторінку.
# Перевіряється саме тіло — статус 404 однаковий і в порожньої відповіді.
expect_status 404 "/files/originals/logo.png"
expect_contains "files/originals/ is answered by the storefront, not by a bare server 404" \
    "OkayCMS" \
    curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/files/originals/logo.png"
# Шлях бандла береться з реальної сторінки, а не зашивається — в імені хеш вмісту.
asset_path=$(curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/" 2>/dev/null \
    | grep -oE 'cache/(css|js)/[^"]+\.(css|js)' | head -1)
if [ -n "$asset_path" ]; then
    expect_status 200 "/$asset_path"
else
    printf '  FAIL  %s\n' "no compiled asset found on the storefront to check"
    fails=$((fails + 1))
fi
# Те саме для решти дозволених дерев: шлях береться з диска, щоб перевірка не
# розсипалась від перейменування теми чи модуля.
for probe in \
    "$(cd .. && ls design/*/css/*.css 2>/dev/null | head -1)" \
    "$(cd .. && ls design/*/preview.png 2>/dev/null | head -1)" \
    "$(cd .. && find backend/design/css -name '*.css' 2>/dev/null | head -1)" \
    "$(cd .. && find Okay/Modules -path '*design/images/*.png' 2>/dev/null | head -1)" \
    "$(cd .. && ls Okay/Modules/*/*/preview.png 2>/dev/null | head -1)" \
    "backend/design/js/okay-file-picker.js"; do
    # Порожній probe означає, що дерево переїхало, а не що все гаразд:
    # мовчазний пропуск прибрав би покриття без жодного сигналу.
    if [ -z "$probe" ]; then
        printf '  FAIL  %s\n' "no sample file found for one of the allowed static trees"
        fails=$((fails + 1))
        continue
    fi
    expect_status 200 "/$probe"
done

# Дерево залежностей не публічне. installed.json — 248 КБ із точними версіями
# всіх залежностей; /vendor/bin/phpunit не має розширення, тож правила за
# розширенням його не ловили; autoload.php під vendor/ ще й виконувався.
for p in /vendor/composer/installed.json /vendor/autoload.php /vendor/bin/phpunit \
         /vendor/composer/ClassLoader.php /vendor/smarty/smarty/src/Smarty.php \
         /ok /composer.json /composer.lock /phpunit.xml /phpstan.neon /README.md \
         /.env /.git/config /.htaccess \
         /config/config.php /tests/bootstrap.php /docs/README.md /dev/docker-compose.yml \
         /1DB_changes/okay_clean.sql \
         /backend/design/js.php /backend/lang/ru.php \
         /design/vibe_shop/js.php /design/vibe_shop/html/index.tpl \
         /Okay/Core/Response.php; do
    expect_status 404 "$p"
done
# Скомпільовані Smarty-шаблони виконувались як PHP і давали 500 з записом у лог.
for d in compiled backend/design/compiled; do
    tpl=$(cd .. && find "$d" -name '*.php' 2>/dev/null | head -1)
    [ -n "$tpl" ] && expect_status 404 "/$tpl"
done

# Самооновний прохід: перебирає реальні записи кореня репозиторію й вимагає 404
# від кожного, крім явно дозволених. Саме він ловить файл, доданий у корінь
# завтра, — переліку доповнювати не треба.
#
# `ls -A`, а не `ls`: без -A крапкові записи (.env, .git, .htaccess) не
# потрапляють у перебір взагалі — а це рівно той клас файлів, заради якого
# перевірка й існує.
for entry in $(cd .. && ls -A); do
    case "$entry" in
        robots.txt) continue ;;
        # index.php віддає 301 на /, це його штатна поведінка.
        index.php)  expect_status 301 "/index.php"; continue ;;
        # ACME-валідація Let's Encrypt — єдиний крапковий шлях, що віддається.
        .well-known) continue ;;
    esac
    expect_status 404 "/$entry"
done

# Точна версія PHP не потрібна браузеру — лише тому, хто добирає під неї
# відомі вразливості. Банер X-Powered-CMS: OkayCMS лишається свідомо: версії
# в ньому немає, а сам рушій ідентифікується й без нього.
headers=$(curl -sSI -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/" 2>/dev/null)
for h in "X-Powered-By"; do
    if printf '%s' "$headers" | grep -qi "^${h}:"; then
        printf '  FAIL  header %s is still sent\n' "$h"
        fails=$((fails + 1))
    else
        printf '  ok    header %s is not sent\n' "$h"
    fi
done
# Версія в банері CMS — окрема історія: її прибрали раніше й повертати не можна.
if printf '%s' "$headers" | grep -qiE "^X-Powered-CMS: OkayCMS [0-9]"; then
    printf '  FAIL  X-Powered-CMS carries a version again\n'
    fails=$((fails + 1))
else
    printf '  ok    X-Powered-CMS carries no version\n'
fi
# Контроль самого вимірювача: заголовок, який точно є, мусить знаходитись.
# Раніше якорем був Server: — тепер його прибирає сам Caddyfile (`header
# -Server`), тож якорем стало Content-Type, який є в кожній відповіді.
if printf '%s' "$headers" | grep -qi "^Content-Type:"; then
    printf '  ok    the header check itself works (Content-Type: is found)\n'
else
    printf '  FAIL  the header check found no Content-Type: — it proves nothing\n'
    fails=$((fails + 1))
fi

# Версія сервера в Server: — підказка для сканерів, як і X-Powered-By.
if printf '%s' "$headers" | grep -qi "^Server:"; then
    printf '  FAIL  header Server is still sent\n'
    fails=$((fails + 1))
else
    printf '  ok    header Server is not sent\n'
fi

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
