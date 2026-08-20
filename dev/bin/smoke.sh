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

# Порівнюється рядок цілком: `php -m | grep dom` збігається ще й з `random`.
loaded_extensions=$(docker compose exec -T php85 php -m 2>/dev/null | tr -d '\r')
for ext in pdo_mysql mysqli gd zip xsl xmlwriter SimpleXML dom xmlreader curl mbstring json; do
    if printf '%s\n' "$loaded_extensions" | grep -qxi "$ext"; then
        printf '  ok    %s\n' "extension loaded: $ext"
    else
        printf '  FAIL  %s\n' "extension loaded: $ext"
        fails=$((fails + 1))
    fi
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
# Вебсервер і PHP — один контейнер, тож резолвити нічого. Лишається структурний
# факт: php85 мусить бути в обох мережах — у frontend заради публікації порту, у
# backend заради бази.
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
# Голок кілька: з debug_mode фатал друкується в тіло, а статус лишається 200,
# тож на код відповіді покладатись не можна.
for pg in "/" "/cart" "/blog" "/brands"; do
    for diagnostic in "Deprecated:" "Warning:" "Notice:" "Fatal error:" "Parse error:" "Uncaught"; do
        expect_missing "no PHP diagnostics leak into the page: ${pg} (${diagnostic})" \
            "$diagnostic" \
            curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}${pg}"
    done
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

# Ajax-точки входу адмінки виконуються, а не переписуються на backend/index.php.
# Статус тут і є розрізнювачем: переписаний шлях віддав би 302 на форму входу,
# а виконаний скрипт упирається у власний гейт configure.php (E_USER_ERROR).
expect_status 500 "/backend/ajax/stat.php"

# Заголовки дерева files/ не мусять лягати на фолбек до фронт-контролера: там
# повна HTML-сторінка 404 із Set-Cookie, і `public, max-age` на ній конфліктує
# з `no-store` від PHP, а CSP знімає з неї стилі.
files_fallback() {
    curl -sS -D- -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
        "http://127.0.0.1:${HTTP_PORT}/files/smoke-no-such-file.png"
}
expect_missing "the storefront 404 page under files/ is not marked publicly cacheable" \
    "max-age=31536000" files_fallback
expect_missing "the storefront 404 page under files/ keeps its stylesheets" \
    "Content-Security-Policy" files_fallback

# Контроль: на справжньому файлі ті самі заголовки мусять бути — інакше дві
# перевірки вище проходили б і на порожній відповіді.
# originals/ виключені навмисно: вони свідомо йдуть у фронт-контролер і CSP не
# отримують, тож як контроль не годяться. sort — щоб вибір не залежав від
# порядку обходу каталогів, який на іншій машині інший.
real_file=$(cd .. && find files -type f \( -name '*.png' -o -name '*.jpg' -o -name '*.jpeg' \) \
    -not -path 'files/originals/*' | sort | head -1)
if [ -n "$real_file" ]; then
    # Спершу довести, що файл узагалі віддається: на 404 перевірка CSP нижче
    # нічого не означала б.
    expect_contains "the control file under files/ is served from disk" \
        "200" \
        curl -sS -o /dev/null -w '%{http_code}' -H "Host: ${VIRTUAL_HOST}" \
            "http://127.0.0.1:${HTTP_PORT}/$real_file"
    expect_contains "a real file under files/ still gets the sandbox CSP" \
        "Content-Security-Policy" \
        curl -sS -D- -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
            "http://127.0.0.1:${HTTP_PORT}/$real_file"
else
    printf '  FAIL  %s\n' "no file under files/ to check the CSP control against"
    fails=$((fails + 1))
fi

# 404 від file_server Caddy пише повз обробник header сайту, тож без
# handle_errors заголовок Server лишається у відповіді.
expect_missing "a missing static file does not disclose the server software" \
    "Server:" \
    curl -sS -D- -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
        "http://127.0.0.1:${HTTP_PORT}/js_libraries/smoke-no-such-file.js"

# Той самий обробник знімає з помилки й кеш-заголовки: інакше браузер і CDN
# памʼятають «файла немає» рік і переживають викладку самого файла.
expect_missing "a missing static file is not cached for a year" \
    "max-age=31536000" \
    curl -sS -D- -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
        "http://127.0.0.1:${HTTP_PORT}/js_libraries/smoke-no-such-file.js"

# Адмін-API Caddy мусить бути вимкнений: вебсервер і PHP тут один процес під
# одним UID, тож доступний застосунку control plane означав би виконання коду в
# обхід усього білого списку. Перевіряється з самого PHP, а не з шела — саме він
# і був би атакувальником.
expect_contains "the Caddy admin API is unreachable from application PHP" \
    "unreachable" \
    docker compose exec -T php85 php -r 'echo @file_get_contents("http://127.0.0.1:2019/config/") === false ? "unreachable" : "REACHABLE";'

# Класові дерева адмінки (PSR-4) — не точки входу.
expect_status 404 "/backend/Controllers/IndexAdmin.php"
expect_status 404 "/backend/Helpers/BackendProductsHelper.php"

# Сумісність зі старим URL адмінки і канонізація www.
expect_status 302 "/admin"
expect_contains "www. is redirected to the same host without the prefix" \
    "Location: http://${VIRTUAL_HOST}/" \
    curl -sS -D- -o /dev/null -H "Host: www.${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/"

# Базова трійка заголовків мусить доходити і до PHP-відповіді, і до статики.
# Ставиться вона через `?` (не перетирати SecurityHeaders), а це рівно те місце,
# де помилка дає тихо відсутній заголовок.
for header in "X-Content-Type-Options: nosniff" "X-Frame-Options: SAMEORIGIN" \
              "Referrer-Policy: strict-origin-when-cross-origin"; do
    expect_contains "the storefront sends ${header%%:*}" "$header" \
        curl -sS -D- -o /dev/null -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/"
    expect_contains "robots.txt sends ${header%%:*}" "$header" \
        curl -sS -D- -o /dev/null -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/robots.txt"
done

# request_body max_size сам по собі не відхиляє запит, а мовчки обрізає тіло:
# застосунок отримав би побитий файл замість помилки.
oversized=$(mktemp)
head -c 120000000 /dev/zero > "$oversized"
oversized_code=$(curl -sS -o /dev/null -w '%{http_code}' -H 'Expect:' \
    -H "Host: ${VIRTUAL_HOST}" --data-binary "@${oversized}" \
    "http://127.0.0.1:${HTTP_PORT}/" 2>/dev/null || echo "000")
rm -f "$oversized"
if [ "$oversized_code" = "413" ]; then
    printf '  ok    %s\n' "a body over the limit is refused instead of silently truncated"
else
    printf '  FAIL  %s\n' "a body over the limit is refused instead of silently truncated"
    printf '        expected HTTP 413, actual: %s\n' "$oversized_code"
    fails=$((fails + 1))
fi
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
# Якір — Content-Type, бо він є в кожній відповіді: Server прибирає сам
# Caddyfile, тож на ньому перевірка була б завжди порожньою.
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
echo "Isolation between requests"
# У classic mode процес помирає разом із запитом, тож ці перевірки там
# тривіально зелені. Сенс вони мають у worker mode, і саме тому ганяються в
# обох: розбіжність між режимами і є тим, що треба ловити.

sql() {
    docker compose exec -T mariadb sh -c \
        "mariadb -uroot -p\"\${MYSQL_ROOT_PASSWORD}\" \"\${MYSQL_DATABASE}\" -N -B -e \"$1\"" 2>/dev/null
}

# Ціль переписування вітрини і оголошення воркера вмикаються лише разом. Одна
# без другої дає або воркер, який не отримує запитів, або вітрину, яка виконує
# скрипт воркера як звичайний.
front_entry=$(docker compose exec -T php85 sh -c 'printf %s "${OKAY_FRONT_ENTRY:-}"')
worker_config=$(docker compose exec -T php85 sh -c 'printf %s "${FRANKENPHP_CONFIG:-}"')
case "$worker_config:$front_entry" in
    ":/index.php"|":") mode=classic ;;
    *worker*:/worker.php) mode=worker ;;
    *) mode=broken ;;
esac
if [ "$mode" = "broken" ]; then
    printf '  FAIL  FRANKENPHP_CONFIG and OKAY_FRONT_ENTRY disagree\n'
    printf '        FRANKENPHP_CONFIG=%s OKAY_FRONT_ENTRY=%s\n' "$worker_config" "$front_entry"
    fails=$((fails + 1))
else
    printf '  ok    request mode is consistent (%s)\n' "$mode"
fi

# Скрипт воркера не є публічним файлом: він іде у фронт-контролер, як і будь-що
# інше поза білим списком.
expect_contains "worker.php is not served as a file" \
    "<!DOCTYPE html" \
    sh -c "curl -sS -H 'Host: ${VIRTUAL_HOST}' 'http://127.0.0.1:${HTTP_PORT}/worker.php'"

jar_a=$(mktemp)
jar_b=$(mktemp)
jar_m=$(mktemp)

storefront() {
    curl -sS -b "$1" -c "$1" -L -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}$2"
}

# Валюта пізнається за міткою **біля числа**, а не за розміткою перемикача:
# перемикач перелічує всі валюти, тож обидві мітки є на сторінці завжди. Одні
# теми друкують знак, інші код, і мітка стоїть то перед числом, то після нього -
# перевіряються всі чотири поєднання, і жодне не залежить від теми.
#
# Сторінка читається в змінну, а не в конвеєр: grep -q виходить на першому
# збігу, і curl падає на SIGPIPE саме тоді, коли мітку знайдено.
priced_in() { # сторінка, код, знак
    local page=$1 label
    for label in "$2" "$3"; do
        [ -n "$label" ] || continue
        [[ "$page" =~ [0-9][[:space:]]*"$label" ]] && return 0
        [[ "$page" =~ "$label"[[:space:]]*[0-9] ]] && return 0
    done
    return 1
}

currency_pair=$(sql "SELECT CONCAT(id, '|', code, '|', sign) FROM ok_currencies WHERE enabled=1 ORDER BY id LIMIT 2;")
cur_a=$(printf '%s\n' "$currency_pair" | sed -n 1p | cut -d'|' -f1)
code_a=$(printf '%s\n' "$currency_pair" | sed -n 1p | cut -d'|' -f2)
sign_a=$(printf '%s\n' "$currency_pair" | sed -n 1p | cut -d'|' -f3)
cur_b=$(printf '%s\n' "$currency_pair" | sed -n 2p | cut -d'|' -f1)
code_b=$(printf '%s\n' "$currency_pair" | sed -n 2p | cut -d'|' -f2)
sign_b=$(printf '%s\n' "$currency_pair" | sed -n 2p | cut -d'|' -f3)
priced_page="/products/$(sql "SELECT url FROM ok_products WHERE visible=1 ORDER BY id LIMIT 1;")"

if [ -z "${cur_a:-}" ] || [ -z "${cur_b:-}" ] || [ "$code_a" = "$code_b" ]; then
    printf '  FAIL  need two enabled currencies to prove sessions do not leak\n'
    fails=$((fails + 1))
else
    storefront "$jar_a" "/?currency_id=${cur_a}" > /dev/null
    storefront "$jar_b" "/?currency_id=${cur_b}" > /dev/null

    page_a=$(storefront "$jar_a" "$priced_page")
    page_b=$(storefront "$jar_b" "$priced_page")

    # Контроль вимірювача: якщо сторінка не показує знак обраної валюти або
    # показує обидва, решта перевірки нічого не доводить.
    if ! priced_in "$page_a" "$code_a" "$sign_a" \
        || ! priced_in "$page_b" "$code_b" "$sign_b" \
        || priced_in "$page_a" "$code_b" "$sign_b"; then
        printf '  FAIL  the currency probe cannot tell two sessions apart on %s\n' "$priced_page"
        printf '        expected A=%s/%s B=%s/%s\n' "$code_a" "$sign_a" "$code_b" "$sign_b"
        fails=$((fails + 1))
    else
        leaked=no
        for _ in 1 2 3; do
            page_a=$(storefront "$jar_a" "$priced_page")
            page_b=$(storefront "$jar_b" "$priced_page")
            priced_in "$page_a" "$code_a" "$sign_a" || leaked=yes
            priced_in "$page_b" "$code_b" "$sign_b" || leaked=yes
            priced_in "$page_a" "$code_b" "$sign_b" && leaked=yes
        done
        if [ "$leaked" = "no" ]; then
            printf '  ok    currency does not leak between two customer sessions\n'
        else
            printf '  FAIL  currency leaks between customer sessions\n'
            fails=$((fails + 1))
        fi
    fi

    # Запит без куки не має успадкувати сесію попереднього відвідувача.
    anon_sid=$(curl -sS -D- -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
        "http://127.0.0.1:${HTTP_PORT}/" | tr -d '\r' \
        | sed -n 's/^[Ss]et-[Cc]ookie: okay_sid=\([^;]*\).*/\1/p' | head -1)
    a_sid=$(awk '/okay_sid/ {print $7}' "$jar_a" | head -1)
    if [ -n "$anon_sid" ] && [ "$anon_sid" != "$a_sid" ]; then
        printf '  ok    an anonymous request starts its own session\n'
    else
        printf '  FAIL  an anonymous request reused another visitor session\n'
        fails=$((fails + 1))
    fi
fi

# Кошик покупця: те саме, але видно в грошах. Товар лежить у сесії, тож
# перевіряється тим самим способом - двома сесіями поперемінно.
add_to_cart() { # jar, url товару, variant_id
    local token
    token=$(storefront "$1" "/products/$2" \
        | grep -oP 'name="customer_csrf_token"[^>]*value="\K[^"]+' | head -1)
    curl -sS -b "$1" -c "$1" -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
        -d "customer_csrf_token=${token}&amount=1" \
        "http://127.0.0.1:${HTTP_PORT}/cart/$3"
}

cart_variants() {
    storefront "$1" "/cart" | grep -oP '(?<=cart/remove/)[0-9]+' | sort -u | tr '\n' ' ' | sed 's/ $//'
}

cart_pair=$(sql "SELECT v.id FROM ok_variants v JOIN ok_products p ON p.id = v.product_id WHERE p.visible = 1 ORDER BY v.id LIMIT 2;" | tr '\n' ' ')
var_a=$(printf '%s' "$cart_pair" | awk '{print $1}')
var_b=$(printf '%s' "$cart_pair" | awk '{print $2}')
product_url=$(sql "SELECT url FROM ok_products WHERE visible=1 ORDER BY id LIMIT 1;")

if [ -z "${var_a:-}" ] || [ -z "${var_b:-}" ] || [ "$var_a" = "$var_b" ]; then
    printf '  FAIL  need two variants of visible products to check the cart\n'
    fails=$((fails + 1))
else
    add_to_cart "$jar_a" "$product_url" "$var_a"
    add_to_cart "$jar_b" "$product_url" "$var_b"

    got_a=$(cart_variants "$jar_a")
    got_b=$(cart_variants "$jar_b")

    if [ "$got_a" != "$var_a" ] || [ "$got_b" != "$var_b" ]; then
        printf '  FAIL  carts do not hold what was put in them: A=[%s] want %s, B=[%s] want %s\n' \
            "$got_a" "$var_a" "$got_b" "$var_b"
        fails=$((fails + 1))
    else
        printf '  ok    the cart does not leak between two customer sessions\n'
    fi
fi

# Переклади теми й ядра приходять із PHP-файлів. Якщо їх включити через
# require_once, у живому процесі другий запит дістане порожній масив - сторінка
# лишиться без підписів, а форми без значень у кнопках. Ззовні це виглядає як
# «нічого не сталось», тож перевіряється явно.
theme=$(sql "SELECT value FROM ok_settings WHERE param='theme';")
main_lang=$(sql "SELECT label FROM ok_languages ORDER BY position LIMIT 1;")
lang_file="design/${theme}/lang/${main_lang}.php"

# Кандидати - досить довгі значення без розмітки, щоб збіг на сторінці не був
# випадковим.
candidates=$(docker compose exec -T php85 php -r "
    \$lang = [];
    if (!is_file('${lang_file}')) { exit; }
    require '${lang_file}';
    foreach (\$lang as \$value) {
        if (is_string(\$value) && mb_strlen(\$value) >= 8 && !preg_match('~[<>{}\\\\\$]~', \$value)) {
            echo \$value, PHP_EOL;
        }
    }
" 2>/dev/null | head -60)

jar_t=$(mktemp)
first_page=$(storefront "$jar_t" "/")
marker=""
while IFS= read -r candidate; do
    [ -n "$candidate" ] || continue
    if [[ "$first_page" == *"$candidate"* ]]; then
        marker=$candidate
        break
    fi
done <<< "$candidates"

if [ -z "$marker" ]; then
    printf '  FAIL  no theme translation from %s is visible on the storefront: the check proves nothing\n' "$lang_file"
    fails=$((fails + 1))
else
    lost=no
    for _ in 1 2 3 4; do
        page=$(storefront "$jar_t" "/")
        [[ "$page" == *"$marker"* ]] || lost=yes
    done
    if [ "$lost" = "no" ]; then
        printf '  ok    theme translations survive later requests in the same process\n'
    else
        printf '  FAIL  theme translations vanish after the first request ("%s" is gone)\n' "$marker"
        fails=$((fails + 1))
    fi
fi
rm -f "$jar_t"

# Привілей менеджера не має переходити на наступний анонімний запит: саме на
# цьому тримаються показ невидимих сутностей і обхід site_work=off.
hidden_url=$(sql "SELECT url FROM ok_products WHERE visible=1 ORDER BY id LIMIT 1;")
if [ -z "${hidden_url:-}" ]; then
    printf '  FAIL  no product to check manager privileges with\n'
    fails=$((fails + 1))
else
    csrf=$(curl -sS -c "$jar_m" -H "Host: ${VIRTUAL_HOST}" \
        "http://127.0.0.1:${HTTP_PORT}/backend/index.php?controller=AuthAdmin" \
        | grep -oP 'name="session_id"\s+value="\K[^"]+' | head -1)
    curl -sS -b "$jar_m" -c "$jar_m" -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
        -d "login=admin&password=1234&session_id=${csrf}" \
        "http://127.0.0.1:${HTTP_PORT}/backend/index.php?controller=AuthAdmin"

    # Що вхід узагалі відбувся: інакше 404 у менеджера читався б як
    # "привілеїв немає", хоча насправді немає сесії.
    logged_in=$(curl -sS -o /dev/null -w '%{http_code}' -b "$jar_m" \
        -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/backend/index.php?controller=ProductsAdmin")

    sql "UPDATE ok_products SET visible=0 WHERE url='${hidden_url}';"

    manager_code=$(curl -sS -o /dev/null -w '%{http_code}' -b "$jar_m" \
        -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/products/${hidden_url}")
    anon_code=$(curl -sS -o /dev/null -w '%{http_code}' \
        -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/products/${hidden_url}")

    sql "UPDATE ok_products SET visible=1 WHERE url='${hidden_url}';"

    if [ "$logged_in" != "200" ]; then
        printf '  FAIL  the manager probe proves nothing: the admin login did not take (%s)\n' "$logged_in"
        fails=$((fails + 1))
    elif [ "$manager_code" != "200" ]; then
        printf '  FAIL  a logged-in manager got %s on a hidden product: privileges are\n' "$manager_code"
        printf '        decided once per process instead of once per request\n'
        fails=$((fails + 1))
    elif [ "$anon_code" = "404" ]; then
        printf '  ok    manager privileges do not carry over to the next anonymous request\n'
    else
        printf '  FAIL  an anonymous request saw a hidden product (%s) after a manager request\n' "$anon_code"
        fails=$((fails + 1))
    fi
fi

# Slug частини роутів будується з URL запиту, який зараз обробляється. Якщо
# перелік роутів пережити межу запиту, у живому процесі все, що не збіглося з
# першим URL, віддає 404 - і це не 404 застосунку, а мовчазна втрата розділу.
#
# Роут блогу з категорією саме такий, тож він і править за пробу.
blog_url=$(curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/all-posts" \
    | grep -oP 'href="\K/blog/[^"]+' | head -1)

if [ -z "${blog_url:-}" ]; then
    printf '  FAIL  no path-derived route on /all-posts: the check proves nothing\n'
    fails=$((fails + 1))
else
    # Головна першою: саме вона в живому процесі й лишала по собі свої роути.
    curl -sS -o /dev/null -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/"
    route_code=$(curl -sS -o /dev/null -w '%{http_code}' -H "Host: ${VIRTUAL_HOST}" \
        "http://127.0.0.1:${HTTP_PORT}${blog_url}")

    if [ "$route_code" = "200" ]; then
        printf '  ok    routes are rebuilt per request, not per process\n'
    else
        printf '  FAIL  %s gave %s after another page: routes outlived the request\n' "$blog_url" "$route_code"
        fails=$((fails + 1))
    fi
fi

rm -f "$jar_a" "$jar_b" "$jar_m"

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
