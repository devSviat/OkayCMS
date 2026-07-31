#!/usr/bin/env bash
# Smoke-перевірки dev-оточення OkayCMS.
# Запуск у будь-який момент після `docker compose up -d`: dev/bin/smoke.sh
# Скрипт сам чекає, поки кожен healthchecked-сервіс стане healthy (wait_for_healthy
# нижче), тож викликати перед ним `sleep` не потрібно.
set -uo pipefail
cd "$(dirname "$0")/.."

# shellcheck disable=SC1091
set -a; . ./.env; set +a

# wait_for_healthy: блокує виконання, поки кожен сервіс із healthcheck у
# змерджованому compose-конфігу не стане "healthy", або падає з таймаутом.
#
# db-init свідомо не чекається тут: це одноразовий сервіс, який виконується й
# виходить з кодом 0, тож "healthy" він не покаже ніколи.
wait_for_healthy() {
    local timeout=120 waited=0 services svc cid status all_healthy

    if command -v jq >/dev/null 2>&1; then
        services=$(docker compose config --format json 2>/dev/null \
            | jq -r '.services | to_entries[] | select(.value.healthcheck != null) | .key')
    else
        # Без jq — жорстко заданий список. Додаючи healthcheck новому сервісу,
        # онови і цей список.
        services="mariadb php85 nginx scheduler"
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

# wait_for_db_init: db-init не має healthcheck і wait_for_healthy його
# пропускає, але перевірка "admin manager exists" нижче залежить від того, що
# він уже завершився. Опитує контейнер до виходу й падає з логами та кодом
# виходу, якщо той не встиг за таймаут або завершився не з 0.
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

# expect_contains <description> <needle> <command...>
expect_contains() {
    local desc=$1 needle=$2
    shift 2
    local out
    out=$("$@" 2>&1) || true
    # Порівняння шаблоном bash, а не `printf | grep -q`: під `set -o pipefail`
    # grep -q виходить одразу після збігу, printf отримує SIGPIPE, і весь
    # конвеєр вважається невдалим саме тоді, коли голку знайдено — і тим
    # частіше, чим більший вивід команди.
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
# Дивимось саме на монтування в /var/lib/mysql, а не на «чи є серед монтувань
# хоч один том»: mariadb має ще й том сідового дампа, тож грубіша перевірка
# пройшла б навіть якщо сама база лежить у bind mount. Це вже повністю
# покриває "не bind mount": {{.Type}} для /var/lib/mysql може бути лише
# "volume", "bind" або "tmpfs", тож "volume" виключає bind. Окрема перевірка
# "bind більше не змонтований" тут раніше не мала сенсу: Go-шаблон друкував
# лише {{.Source}} {{.Destination}} (без {{.Type}}), тож підрядок "bind" міг
# з'явитись лише якщо він був у самому шляху — `grep bind` завжди повертав
# порожній вивід і expect_missing проходив незалежно від реального стану.
expect_contains "the database is on a named volume, not a bind mount" \
    "volume" \
    docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Type}}{{end}}{{end}}' "$mariadb_cid"
# Перевіряємо, що пароль справді підходить, а не що в базі лежить конкретний
# хеш. OkayCMS перехешовує застарілі формати після успішного входу
# (Okay\Core\Security\PasswordHasher), тож звірка з рядком із сідового дампа
# ламалась після першого ж входу в адмінку.
admin_hash=$(docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT password FROM ok_managers WHERE login = \"admin\";"' \
    2>/dev/null | tr -d '\r\n')
expect_contains "admin still authenticates with the default password" \
    "OK" \
    docker compose exec -T php85 php -r \
    'require "/var/www/html/vendor/autoload.php"; $h = new Okay\Core\Security\PasswordHasher(); echo $h->verify("1234", $argv[1]) ? "OK" : "FAIL";' \
    -- "$admin_hash"
# Реальний запит клієнта з хоста, а не просто TCP-хендшейк: саме це потрібно
# DBeaver/PHPStorm. Регресує мовчки, якщо mariadb колись втратить (dev-only)
# приєднання до frontend або опублікований порт — жодна перевірка всередині
# контейнера цього не побачить. Див. коментар mariadb.networks у
# docker-compose.override.yml.
expect_contains "mariadb is reachable from the host, not just from inside a container" \
    "ok_managers" \
    docker run --rm --network host mariadb:10.11 mariadb \
    -h 127.0.0.1 -P "${MYSQL_PORT}" -uroot -p"${MYSQL_ROOT_PASSWORD}" \
    -e "SHOW TABLES LIKE 'ok_managers';" "${MYSQL_DATABASE}"

echo
echo "Network segmentation"
# Ім'я мережі рахується з APP_NAME (.env), а НЕ вичитується з самого
# контейнера mariadb: Compose іменує мережі як <lowercase(project)>_<key>
# (див. docker-compose.yml, networks.backend), тож це джерело незалежне від
# того, що перевіряється нижче. Раніше backend_net брався з мережевого
# списку самого mariadb-контейнера, а потім перевірялось, що той самий
# список містить те саме значення — тавтологія, яка не могла впасти навіть
# якби mariadb взагалі відключили від backend.
backend_net="$(printf '%s' "${APP_NAME:?err}" | tr '[:upper:]' '[:lower:]')_backend"
expect_contains "the backend network is internal (no route off the host)" \
    "true" \
    docker network inspect "$backend_net" --format '{{.Internal}}'
expect_contains "mariadb is attached to the backend network" \
    "$backend_net" \
    docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$mariadb_cid"
# mariadb у dev додатково приєднана до frontend (docker-compose.override.yml),
# інакше опублікований порт вище був би тихим no-op'ом: internal:true означає,
# що Docker не створює NAT-правило для контейнера без маршруту назовні. У
# прод (docker-compose.prod.yml) mariadb лишається лише в backend і нічого не
# публікує.
expect_contains "nginx (frontend-only) can still resolve php85 over the frontend network" \
    "php85" \
    docker compose exec -T nginx getent hosts php85

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
expect_contains "nginx access logs reach docker compose logs" \
    "GET /" \
    sh -c "curl -sS -o /dev/null -H 'Host: ${VIRTUAL_HOST}' http://127.0.0.1:${HTTP_PORT}/ ; sleep 1 ; docker compose logs --tail=20 nginx"
# Перевірка результату, а не конфігу: робимо запит з унікальним маркером і
# шукаємо його в усьому робочому дереві. Якщо nginx кудись пише файлом, маркер
# там опиниться — на відміну від грепання конкретного файлу конфігу, перевірка
# не залежить від його імені чи шляху.
log_marker="smoke-nolog-$$"
curl -sS -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
    "http://127.0.0.1:${HTTP_PORT}/${log_marker}" 2>/dev/null || true
sleep 1
expect_missing "nginx writes no log files into the working tree" \
    "$log_marker" \
    docker compose exec -T nginx sh -c \
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
# Mailpit накопичує всі повідомлення до перестворення контейнера, тож "total"
# зростає між прогонами, а не скидається щоразу. Фіксоване значення пройшло б
# лише на щойно створеному стеку й падало б на кожному наступному прогоні.
# Тому фіксуємо лічильник до відправки, відправляємо, і звіряємо, що він
# зріс — результат, а не абсолютне число.
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
# Навмисно з Host: localhost, а не просто звернення на 127.0.0.1: образ nginx
# має власний default.conf із `server_name localhost`, і саме рядок "localhost"
# є точним збігом для нього — так заходить людина в браузері. Без цього
# заголовка перевірка не відтворила б регрес, коли штатний default.conf
# виграє в нашого віртуального хоста і показує заглушку "Welcome to nginx!".
expect_missing "http://localhost/ is not the stock nginx placeholder" \
    "Welcome to nginx" \
    curl -sS -H "Host: localhost" "http://127.0.0.1:${HTTP_PORT}/"
expect_contains "http://localhost/ serves the storefront" \
    "OkayCMS" \
    curl -sS -H "Host: localhost" "http://127.0.0.1:${HTTP_PORT}/"
expect_contains "the virtual host still serves the storefront" \
    "OkayCMS" \
    curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/"

# dev працює з error_reporting = E_ALL, тож будь-який Notice чи Deprecated
# друкується прямо в сторінку — і робить це ДО заголовків, тобто ламає
# редіректи, а не лише псує вигляд.
#
# Межа цих перевірок: вони роблять лише GET і покривають рендеринг сторінок.
# Логіку, що виконується лише при відправці форми (наприклад оформлення
# замовлення), вони не покривають — для цього потрібен браузерний сценарій.
for pg in "/" "/cart" "/blog" "/brands"; do
    expect_missing "no PHP diagnostics leak into the page: ${pg}" \
        "Deprecated:" \
        curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}${pg}"
done

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
