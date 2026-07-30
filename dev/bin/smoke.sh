#!/usr/bin/env bash
# Smoke checks for the OkayCMS dev environment.
# Run after `docker compose up -d`:  dev/bin/smoke.sh
set -uo pipefail
cd "$(dirname "$0")/.."

# shellcheck disable=SC1091
set -a; . ./.env; set +a

fails=0

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
    docker compose exec -T php85 php -i
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
expect_contains "the database is on a named volume, not a bind mount" \
    "volume" \
    docker inspect -f '{{range .Mounts}}{{.Type}} {{.Destination}}{{"\n"}}{{end}}' "${APP_NAME}-mariadb"
expect_missing "dev/mysql/DB_data is no longer mounted into the container" \
    "/var/lib/mysql" \
    sh -c "docker inspect -f '{{range .Mounts}}{{.Source}} {{.Destination}}{{\"\n\"}}{{end}}' ${APP_NAME}-mariadb | grep bind"
expect_contains "the admin manager exists with the default password" \
    '$apr1$8m1u0cp4$' \
    docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT password FROM ok_managers WHERE login = \"admin\";"'
expect_contains "the stock MariaDB entrypoint is in use" \
    "docker-entrypoint.sh" \
    docker inspect -f '{{json .Config.Entrypoint}}' "${APP_NAME}-mariadb"

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
