#!/usr/bin/env bash
# Smoke checks for the OkayCMS dev environment.
# Run any time after `docker compose up -d`:  dev/bin/smoke.sh
# The script waits for every healthchecked service to report healthy itself
# (see wait_for_healthy below), so callers do not need to sleep first.
set -uo pipefail
cd "$(dirname "$0")/.."

# shellcheck disable=SC1091
set -a; . ./.env; set +a

# wait_for_healthy: block until every service that declares a healthcheck in
# the merged compose config reports "healthy", or fail loudly after a bounded
# timeout. Without this, whether the checks below pass depended on how long
# the caller happened to sleep after `up -d` — a harness that fails at random
# just teaches people to shrug at red output.
#
# db-init is deliberately never waited on: it is a one-shot service that runs
# once and exits 0, so it never reports "healthy" and never will.
wait_for_healthy() {
    local timeout=120 waited=0 services svc cid status all_healthy

    if command -v jq >/dev/null 2>&1; then
        services=$(docker compose config --format json 2>/dev/null \
            | jq -r '.services | to_entries[] | select(.value.healthcheck != null) | .key')
    else
        # No jq: fall back to a hardcoded list. Whoever adds a healthcheck to
        # a new service (there is no jq way to skip this comment) must add it
        # here too.
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

# wait_for_db_init: db-init is a one-shot service (runs the seed SQL, then
# exits 0) so it deliberately has no healthcheck and wait_for_healthy skips
# it. But the "admin manager exists" check below depends on it having
# actually finished. On a fast machine db-init happens to be done before we
# get here, which made this pass by luck rather than by a real guarantee.
# Poll until the container exits, then fail loudly — with its logs and exit
# code — if it either times out or exited non-zero.
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

# dump_actual_output <out>: print the actual captured output on failure, so a
# mismatch shows whether the command errored, returned empty, or returned
# something unexpected instead of just restating what we hoped to see.
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
    # Порівняння вбудованим шаблоном bash, а не `printf | grep -q`. З pipefail
    # конвеєр ламався на великому виводі: grep -q виходить одразу після збігу,
    # printf отримує SIGPIPE, і pipefail оголошує весь конвеєр невдалим — тобто
    # асершен падав саме тоді, коли голку БУЛО знайдено. На малому виводі printf
    # встигав завершитись, тому баг спав до першої перевірки HTML-сторінки.
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
    # Той самий шаблон bash замість `printf | grep -q` — див. коментар вище
    # про SIGPIPE під pipefail.
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
# хоч один том». Стара версія грепала весь список на слово volume і проходила
# б навіть тоді, коли база знову лежить у bind mount, — бо в mariadb є ще й
# том сідового дампа.
expect_contains "the database is on a named volume, not a bind mount" \
    "volume" \
    docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/lib/mysql"}}{{.Type}}{{end}}{{end}}' "$mariadb_cid"
expect_missing "dev/mysql/DB_data is no longer mounted into the container" \
    "/var/lib/mysql" \
    sh -c "docker inspect -f '{{range .Mounts}}{{.Source}} {{.Destination}}{{\"\n\"}}{{end}}' $mariadb_cid | grep bind"
expect_contains "the admin manager exists with the default password" \
    '$apr1$8m1u0cp4$' \
    docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT password FROM ok_managers WHERE login = \"admin\";"'
# Real client query from the host, not just a TCP handshake: this is the
# thing DBeaver/PHPStorm actually need to work, and it silently regresses if
# mariadb ever loses its (dev-only) frontend attachment or its published
# port, without any container-side check noticing. See
# docker-compose.override.yml's mariadb.networks comment for why dev
# deliberately differs from the base file/prod here.
expect_contains "mariadb is reachable from the host, not just from inside a container" \
    "ok_managers" \
    docker run --rm --network host mariadb:10.11 mariadb \
    -h 127.0.0.1 -P "${MYSQL_PORT}" -uroot -p"${MYSQL_ROOT_PASSWORD}" \
    -e "SHOW TABLES LIKE 'ok_managers';" "${MYSQL_DATABASE}"

echo
echo "Network segmentation"
# Ім'я мережі береться з самого контейнера, а не з .env. Раніше тут стояло
# "${NETWORK_NAME}-backend", і фіксоване ім'я було не лише крихким для цієї
# перевірки — воно ламало ізоляцію: два стеки цього проєкту з різними -p
# опинялись в одній мережі, і prod-стек ходив у dev-базу. Тепер мережі іменує
# Compose за проєктом, тож єдине надійне джерело — сам контейнер.
backend_net=$(docker inspect -f \
    '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' "$mariadb_cid" \
    | grep -- '_backend$' | head -1)
expect_contains "the backend network is internal (no route off the host)" \
    "true" \
    docker network inspect "$backend_net" --format '{{.Internal}}'
expect_contains "mariadb is attached to the backend network" \
    "$backend_net" \
    docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' "$mariadb_cid"
# mariadb also joins frontend in dev (docker-compose.override.yml) so its
# published port actually works for host tools — internal:true means Docker
# never wires a NAT rule for a container that ISN'T also on a routable
# network, so without this the port mapping above would be a silent no-op.
# That is dev-only: the base file and docker-compose.prod.yml keep mariadb
# on backend only and publish nothing (verify with
# `docker compose -f docker-compose.yml -f docker-compose.prod.yml config`).
expect_contains "nginx (frontend-only) can still resolve php85 over the frontend network" \
    "php85" \
    docker compose exec -T nginx getent hosts php85

echo
echo "Scheduler"
scheduler_cid=$(docker compose ps -q scheduler)
expect_contains "the scheduler container is running" \
    "true" \
    docker inspect -f '{{.State.Running}}' "$scheduler_cid"
# This only proves the supercronic process under tini is alive, not that any
# scheduled job has run or succeeded — see the healthcheck's own comment in
# docker-compose.yml.
expect_contains "supercronic is running inside the scheduler container" \
    "supercronic" \
    docker compose exec -T scheduler pgrep -fa supercronic

echo
echo "Logging"
expect_contains "nginx access logs reach docker compose logs" \
    "GET /" \
    sh -c "curl -sS -o /dev/null -H 'Host: ${VIRTUAL_HOST}' http://127.0.0.1:${HTTP_PORT}/ ; sleep 1 ; docker compose logs --tail=20 nginx"
# Перевірка результату, а не конфігу. Робимо запит з унікальним маркером і
# шукаємо його в усьому робочому дереві: якщо nginx кудись пише файлом, маркер
# там опиниться.
#
# Попередня версія грепала /etc/nginx/conf.d/okay.conf на рядок "dev/logs". Її
# зламало перейменування шаблону на default.conf: cat почав падати з
# "No such file or directory", вивід більше не містив голки, і expect_missing
# проходив завжди. Перевірка, нездатна впасти, гірша за відсутню — вона
# виглядає як покриття. Тепер тут немає залежності від імені файлу.
log_marker="smoke-nolog-$$"
curl -sS -o /dev/null -H "Host: ${VIRTUAL_HOST}" \
    "http://127.0.0.1:${HTTP_PORT}/${log_marker}" 2>/dev/null || true
sleep 1
expect_missing "nginx writes no log files into the working tree" \
    "$log_marker" \
    docker compose exec -T nginx sh -c \
    "grep -r '$log_marker' /var/www/html 2>/dev/null"
# Саме grep -r, а не -rl. З -l друкуються ІМЕНА файлів, а імʼя файлу маркера не
# містить — expect_missing шукав би голку у виводі, де її не може бути, і
# проходив би завжди. Та сама пастка, що й у попередній версії цієї перевірки:
# порівнювати треба з тим, що команда справді друкує.

echo
echo "Mail"
expect_contains "mail() is routed to Mailpit via msmtp" \
    "msmtp" \
    docker compose exec -T php85 php -r 'echo ini_get("sendmail_path");'
expect_contains "the SMTP settings point at Mailpit" \
    "mailpit" \
    docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT value FROM ok_settings WHERE param = \"smtp_server\";"'
# Mailpit keeps every message until the container is recreated, so its
# "total" is cumulative across runs, not per-test. Asserting a fixed
# "total":1 passes only on a freshly created stack and fails on any second
# run against an otherwise healthy environment (observed "total":2 here) —
# exactly the "harness that fails at random" this project's checks are
# supposed to avoid. Capture the count before sending, send, then assert it
# went up — an outcome, not an absolute number.
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
# Навмисно БЕЗ заголовка Host. Кожна інша перевірка тут ходила з
# `-H "Host: $VIRTUAL_HOST"`, і саме тому повз них пройшов регрес: образ nginx
# має власний default.conf із `server_name localhost`, який є точним збігом для
# http://localhost/ і виграє в нашого віртуального хоста. Замість магазину
# показувалась заглушка "Welcome to nginx!". Людина заходить саме так.
# Host: localhost задано явно. Просте звернення на 127.0.0.1 НЕ відтворює
# проблему — точним збігом для штатного `server_name localhost` є саме рядок
# "localhost", тож запит із Host: 127.0.0.1 і так потрапляє до нашого сервера.
expect_missing "http://localhost/ is not the stock nginx placeholder" \
    "Welcome to nginx" \
    curl -sS -H "Host: localhost" "http://127.0.0.1:${HTTP_PORT}/"
expect_contains "http://localhost/ serves the storefront" \
    "OkayCMS" \
    curl -sS -H "Host: localhost" "http://127.0.0.1:${HTTP_PORT}/"
expect_contains "the virtual host still serves the storefront" \
    "OkayCMS" \
    curl -sS -H "Host: ${VIRTUAL_HOST}" "http://127.0.0.1:${HTTP_PORT}/"

# Оскільки dev працює з error_reporting = E_ALL, будь-який Notice чи Deprecated
# друкується просто в сторінку — і робить це ДО заголовків, тобто ламає редіректи,
# а не лише псує вигляд. Саме так було зламане оформлення замовлення: присвоєння
# неоголошених властивостей у CartHelper давало "Creation of dynamic property
# Okay\Core\Cart::$purchasesToDB", і редірект на сторінку замовлення не відправлявся.
#
# Межі цих перевірок, щоб не створювати хибного відчуття безпеки: вони роблять
# лише GET і покривають рендеринг сторінок. Той баг із кошиком вони НЕ спіймали б —
# prepareCart() виконується тільки при відправці замовлення. Перевірено прямо:
# з тимчасово прибраним оголошенням властивості всі чотири лишались зеленими.
# Повне покриття чекауту потребує браузерного сценарію, якому тут не місце.
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
