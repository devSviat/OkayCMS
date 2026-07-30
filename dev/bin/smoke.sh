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
        services="mariadb php85 nginx"
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
expect_contains "the database is on a named volume, not a bind mount" \
    "volume" \
    docker inspect -f '{{range .Mounts}}{{.Type}} {{.Destination}}{{"\n"}}{{end}}' "$mariadb_cid"
expect_missing "dev/mysql/DB_data is no longer mounted into the container" \
    "/var/lib/mysql" \
    sh -c "docker inspect -f '{{range .Mounts}}{{.Source}} {{.Destination}}{{\"\n\"}}{{end}}' $mariadb_cid | grep bind"
expect_contains "the admin manager exists with the default password" \
    '$apr1$8m1u0cp4$' \
    docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT password FROM ok_managers WHERE login = \"admin\";"'

echo
echo "Logging"
expect_contains "nginx access logs reach docker compose logs" \
    "GET /" \
    sh -c "curl -sS -o /dev/null -H 'Host: ${VIRTUAL_HOST}' http://127.0.0.1:${HTTP_PORT}/ ; sleep 1 ; docker compose logs --tail=20 nginx"
expect_missing "nginx no longer writes into the repository" \
    "dev/logs" \
    docker compose exec -T nginx cat /etc/nginx/conf.d/okay.conf

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
