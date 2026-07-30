#!/usr/bin/env bash
# Smoke checks for the OkayCMS *production* image and the composed prod config.
# Unlike smoke.sh, this never talks to a running stack — it builds a throwaway
# `prod`-stage image from scratch and interrogates that directly, plus checks
# the merged `docker compose ... config` output for the one thing an image
# alone can never prove: whether the composed stack publishes any ports.
#
# Run any time, no `docker compose up -d` required:  dev/bin/smoke-prod.sh
set -uo pipefail
cd "$(dirname "$0")/.."   # now in dev/

# shellcheck disable=SC1091
# Only used here for MYSQL_*/APP_NAME/NETWORK_NAME/VIRTUAL_HOST so `docker
# compose ... config` below can interpolate the base file's required (":?err")
# variables. None of the values themselves matter to these checks.
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

# run_in_image <shell-command>: run a throwaway, already-removed-on-exit
# container from the image just built and capture its output. --entrypoint sh
# bypasses the image's real php-fpm entrypoint so we can run one-liners.
run_in_image() {
    docker run --rm --entrypoint sh "$image_tag" -c "$1" 2>&1
}

# dump_actual_output <out>: show what a failed check actually produced instead
# of just restating what we hoped for.
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
# This is the check that matters most. config/config.local.php is gitignored,
# but a Docker build reads the filesystem, not git — without the matching line
# in the root .dockerignore, the developer's live database password would be
# baked into every image ever pushed from this checkout.
expect_contains "config/config.local.php did not make it into the image" \
    "absent" \
    run_in_image 'test -f /var/www/html/config/config.local.php && echo present || echo absent'
expect_contains "dev/.env did not make it into the image" \
    "absent" \
    run_in_image 'test -f /var/www/html/dev/.env && echo present || echo absent'
expect_contains "dev/secrets did not make it into the image" \
    "absent" \
    run_in_image 'test -d /var/www/html/dev/secrets && echo present || echo absent'

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
# The single most important property of docker-compose.prod.yml: Dokploy
# attaches Traefik straight to the container, so the composed stack must
# publish nothing. A config check is not a running-stack check, but it is the
# only thing that can be asserted without a real Dokploy/Traefik instance in
# front, and it does catch a stray `ports:` creeping back into either file.
#
# This must be two checks, not one. `docker compose ... config` errors with
# "no such file" if docker-compose.prod.yml is simply missing, and that error
# text does not contain "published:" either — so a lone expect_missing on
# "published:" would report success for the wrong reason (the command failed,
# not "no ports found"). Assert the config actually rendered first.
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
