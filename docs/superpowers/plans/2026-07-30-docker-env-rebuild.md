# Docker Environment Rebuild Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the `dev/` Docker environment so the database lives in a named volume, the PHP configuration is no longer shadowed by its own bind mount, and an opt-in production profile exists.

**Architecture:** Compose splits into three layers — `docker-compose.yml` (shared), `docker-compose.override.yml` (dev, auto-loaded), `docker-compose.prod.yml` (opt-in via `-f`). One multi-stage `dev/docker/Dockerfile` produces the `dev`, `prod` and `nginx-prod` images, replacing three single-purpose Dockerfiles. A repeatable `dev/bin/smoke.sh` carries the assertions, so every task has a check that fails before the change and passes after.

**Tech Stack:** Docker Compose v5, PHP 8.5-fpm, MariaDB 10.11, nginx 1.29-alpine, Mailpit, `mlocati/php-extension-installer`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-30-docker-dev-prod-design.md`. Read it before starting.
- Branch: `chore/docker-env-rebuild`, already created, spec already committed.
- The service keeps the name **`php85`**. Do not rename it — `CLAUDE.md` documents `docker compose exec php85 php vendor/bin/phpunit`.
- **Never modify `1DB_changes/okay_clean.sql`.** It is the seed dump and is off limits. Writing to the live dev database is fine.
- Comments in new files: Ukrainian or English only. **Never Russian**, even where a neighbouring core file is Russian.
- Commit as `devSviat <devsviat@proton.me>` (`git -c user.name=devSviat -c user.email=devsviat@proton.me commit`). No `Co-Authored-By` and no `Claude-Session` trailers.
- The existing dev database is being **discarded** by decision. Do not write data-migration code.
- Build args are `APP_UID` / `APP_GID`, never `UID` / `GID` — `UID` is readonly in bash and zsh and never reaches Compose.
- All published ports bind to `${BIND_IP:-127.0.0.1}`.

## File Structure

| File | Responsibility |
| --- | --- |
| `dev/docker-compose.yml` | Base: service identity, images, healthchecks, `depends_on`, the `db_data` volume. Shared by dev and prod. |
| `dev/docker-compose.override.yml` | Dev only: source bind mounts, published ports, `container_name`, Xdebug, `db-init`, Mailpit, the seed dump mount. Auto-loaded. |
| `dev/docker-compose.prod.yml` | Prod only: `target: prod`, runtime `config.local.php` mount, log caps. Opt-in. |
| `dev/docker/Dockerfile` | All four stages: `base` → `dev`, `prod`, `nginx-prod`. |
| `dev/docker/Dockerfile.dockerignore` | Keeps dev credentials, the DB directory and `vendor/` out of the build context. |
| `dev/config/php/custom.d/okay.ini` | Settings shared by dev and prod. |
| `dev/config/php/custom.d/dev.ini` | Dev-only PHP settings. Baked into the `dev` stage. |
| `dev/config/php/custom.d/xdebug.ini` | Xdebug client settings. Baked into the `dev` stage. |
| `dev/config/php/prod/prod.ini` | Prod-only PHP settings. Baked into the `prod` stage. |
| `dev/config/mysql/db-init.sh` | Idempotent post-start SQL: admin password, Mailpit SMTP rows. |
| `dev/config/nginx/templates/okay.conf.template` | nginx vhost. Modified: logs to stdout/stderr. |
| `dev/bin/smoke.sh` | Assertion harness. Grows one block per task. |
| `dev/README.md` | Rewritten for the new workflow. |

**Deleted:** `dev/docker/php/8.5/Dockerfile`, `dev/docker/nginx/Dockerfile`, `dev/docker/mysql/Dockerfile`, `dev/config/php/php.ini`, `dev/config/php/conf.d/xdebug.ini`, `dev/config/mysql/entrypoint.sh`, `dev/config/mysql/startup.sh`, `dev/config/nginx/okay.conf`, `dev/logs/`.

**Kept deliberately:** `dev/.htaccess` — it is the only protection for `dev/` under Apache.

---

### Task 1: Compose layout, multi-stage image, and the PHP config fix

This is the largest task because the compose restructure is scaffolding the image work needs: both rewrite the `php85` service definition, and splitting them would mean writing that block twice.

**Files:**
- Create: `dev/bin/smoke.sh`, `dev/docker/Dockerfile`, `dev/docker/Dockerfile.dockerignore`, `dev/config/php/custom.d/okay.ini`, `dev/config/php/custom.d/dev.ini`, `dev/config/php/custom.d/xdebug.ini`, `dev/docker-compose.override.yml`
- Modify: `dev/docker-compose.yml` (full rewrite), `dev/.env-example`, `dev/.env`, `.gitignore` (repo root)
- Delete: `dev/docker/php/8.5/Dockerfile`, `dev/docker/nginx/Dockerfile`, `dev/config/php/php.ini`, `dev/config/php/conf.d/xdebug.ini`

**Interfaces:**
- Produces: image stages named `base`, `dev`, `prod`, `nginx-prod` in `dev/docker/Dockerfile`; the shell function `expect_contains <desc> <needle> <cmd...>` and the counter `fails` in `dev/bin/smoke.sh`, both used by every later task; the env keys `APP_UID`, `APP_GID`, `BIND_IP`, `XDEBUG_MODE`, `TZ`.

- [ ] **Step 1: Write the failing check**

Create `dev/bin/smoke.sh`:

```bash
#!/usr/bin/env bash
# Smoke checks for the OkayCMS dev environment.
# Run after `docker compose up -d`:  dev/bin/smoke.sh
set -uo pipefail
cd "$(dirname "$0")/.."

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
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all checks passed"
```

Make it executable:

```bash
chmod +x dev/bin/smoke.sh
```

- [ ] **Step 2: Run it to verify it fails**

```bash
cd dev && ./bin/smoke.sh
```

Expected: FAIL on "stock extension ini files are not shadowed" — the current bind mount leaves only `xdebug.ini` in `conf.d`. FAIL on "custom.d is on the scan path" and on "timezone is Europe/Kyiv" (currently `europe/kiev`).

- [ ] **Step 3: Write the PHP ini files**

`dev/config/php/custom.d/okay.ini`:

```ini
; Settings shared by every OkayCMS container, dev and prod alike.
; Loaded from /usr/local/etc/php/custom.d, which PHP_INI_SCAN_DIR appends to the
; image's own conf.d — so the stock docker-php-ext-*.ini files stay in place and
; an extension installed in the Dockerfile no longer needs a second manual entry.

date.timezone = Europe/Kyiv

; Errors are logged, never printed. Note that for web requests OkayCMS decides
; this itself: index.php:14 forces display_errors off before anything else runs,
; and only debug_mode turns it back on. This line therefore governs the ./ok CLI,
; PHPUnit, and failures that happen before the bootstrap.
display_errors = Off
log_errors = On

; error_log is deliberately left unset. The php:fpm image already points the FPM
; error log at /proc/self/fd/2 with catch_workers_output = yes, so PHP's default
; SAPI target reaches `docker compose logs`. CLI falls back to stderr.

memory_limit = 1024M
max_execution_time = 18000

upload_max_filesize = 1000M
post_max_size = 1000M
```

`dev/config/php/custom.d/dev.ini`:

```ini
; Development-only PHP settings, copied into the `dev` image stage.
; A prod image does not contain this file at all, so these cannot leak.

; E_ALL rather than the old E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED mask:
; on PHP 8.4/8.5 the deprecation notices are the point.
error_reporting = E_ALL
display_errors = On

; mail() is routed to Mailpit. The SMTP() path in Okay/Core/Notify.php is
; redirected separately through ok_settings — see dev/config/mysql/db-init.sh.
sendmail_path = "/usr/bin/msmtp --host=mailpit --port=1025 --tls=off --auth=off --from=okaycms@localhost -t"
```

`dev/config/php/custom.d/xdebug.ini`:

```ini
; Xdebug 3 client settings. The mode itself comes from the XDEBUG_MODE
; environment variable set in docker-compose.override.yml, which takes precedence
; over ini and can be changed without rebuilding the image.
;
; There is no `zend_extension = xdebug` line here on purpose: install-php-extensions
; writes conf.d/docker-php-ext-xdebug.ini, and conf.d is no longer shadowed.

xdebug.start_with_request = trigger
xdebug.client_host = host.docker.internal
xdebug.client_port = 9001
xdebug.log = /tmp/xdebug.log
```

- [ ] **Step 4: Write the multi-stage Dockerfile**

`dev/docker/Dockerfile`:

```dockerfile
# syntax=docker/dockerfile:1
#
# Build context is the repository root, so COPY paths are prefixed with dev/.
# Stages: base -> dev | prod -> nginx-prod

ARG PHP_VERSION=8.5

# ─────────────────────────────── base ───────────────────────────────
FROM php:${PHP_VERSION}-fpm AS base

# install-php-extensions resolves the system libraries each extension needs and
# cleans up after itself. It replaces the hand-rolled apt-get blocks, which had
# `update` and `install` in separate layers — the classic stale-cache bug.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        xsl \
        xmlwriter \
        opcache

# Matches the host user so bind-mounted files stay writable. Named APP_UID rather
# than UID because UID is readonly in bash and zsh and never reaches Compose.
ARG APP_UID=1000
ARG APP_GID=1000
RUN groupmod -o -g "${APP_GID}" www-data \
 && usermod  -o -u "${APP_UID}" -g "${APP_GID}" www-data

# Append our directory to the scan path instead of mounting over /usr/local/etc/php,
# which used to hide every docker-php-ext-*.ini the image generates.
ENV PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/usr/local/etc/php/custom.d"
RUN mkdir -p /usr/local/etc/php/custom.d
COPY dev/config/php/custom.d/okay.ini /usr/local/etc/php/custom.d/okay.ini

WORKDIR /var/www/html

# ──────────────────────────────── dev ───────────────────────────────
FROM base AS dev

RUN install-php-extensions xdebug
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# msmtp hands mail() to Mailpit; see sendmail_path in dev.ini.
RUN apt-get update \
 && apt-get install -y --no-install-recommends msmtp \
 && rm -rf /var/lib/apt/lists/*

COPY dev/config/php/custom.d/dev.ini    /usr/local/etc/php/custom.d/dev.ini
COPY dev/config/php/custom.d/xdebug.ini /usr/local/etc/php/custom.d/xdebug.ini

# ─────────────────────────────── prod ───────────────────────────────
FROM base AS prod

COPY dev/config/php/prod/prod.ini /usr/local/etc/php/custom.d/prod.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
 && composer clear-cache \
 && mkdir -p compiled cache files backend/design/compiled Okay/log \
 && chown -R www-data:www-data vendor compiled cache files backend/design/compiled Okay/log

USER www-data

# ───────────────────────────── nginx-prod ───────────────────────────
# Static assets come from the same build as the PHP code, so the two containers
# cannot drift apart.
FROM nginx:1.29-alpine AS nginx-prod
COPY --from=prod /var/www/html /var/www/html
```

`dev/docker/Dockerfile.dockerignore` (BuildKit reads a per-Dockerfile ignore file at `<dockerfile-path>.dockerignore`):

```
# Keeps developer credentials and local state out of the build context, and
# therefore out of the production image. config/config.local.php is gitignored,
# but a build context reads the filesystem rather than git — without this line
# the dev database password would be baked into a pushed image.
config/config.local.php
config/config.local.prod.php

.git
vendor
node_modules

dev/.env
dev/mysql
dev/logs
dev/bin

compiled/*/*.php
backend/design/compiled/*.php
cache
Okay/log
.phpunit.result.cache
```

- [ ] **Step 5: Rewrite the base compose file**

`dev/docker-compose.yml`:

```yaml
# Base configuration, shared by dev and prod.
#
#   dev   docker compose up -d                       (override.yml auto-loads)
#   prod  docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
#
# Everything dev-specific — source bind mounts, published ports, Xdebug, the seed
# dump — lives in docker-compose.override.yml, so it cannot reach production.

name: ${APP_NAME:?err}

services:
  php85:
    build:
      context: ..
      dockerfile: dev/docker/Dockerfile
      target: dev
      args:
        APP_UID: ${APP_UID:-1000}
        APP_GID: ${APP_GID:-1000}
    restart: unless-stopped
    environment:
      TZ: ${TZ:-Europe/Kyiv}
      TEST_INTERNAL_EMAIL: ${TEST_INTERNAL_EMAIL:-}
      PRODUCTION_DOMAIN: ${PRODUCTION_DOMAIN:-}
    depends_on:
      mariadb:
        condition: service_healthy
    healthcheck:
      test: ["CMD-SHELL", "php -r 'exit(0);'"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 10s

  nginx:
    image: nginx:1.29-alpine
    restart: unless-stopped
    depends_on:
      php85:
        condition: service_started
    environment:
      TZ: ${TZ:-Europe/Kyiv}
      # Service DNS, not ${APP_NAME}-php85: the vhost no longer depends on the
      # project name, so `docker compose -p other-name up` works.
      FASTCGI: php85
      VIRTUAL_HOST: ${VIRTUAL_HOST:?err}
    volumes:
      - './config/nginx/templates:/etc/nginx/templates:ro'
    healthcheck:
      # Config validation, deliberately not a request against the site. The real
      # failure mode here is a template that did not render (a missing FASTCGI or
      # VIRTUAL_HOST), which `nginx -t` catches. Fetching a page would instead
      # make nginx's health depend on php-fpm and the database, so the container
      # would flap whenever the stack is merely slow to start.
      test: ["CMD-SHELL", "nginx -t"]
      interval: 30s
      timeout: 5s
      retries: 3

  mariadb:
    image: mariadb:10.11
    restart: unless-stopped
    environment:
      TZ: ${TZ:-Europe/Kyiv}
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:?err}
      MYSQL_USER: ${MYSQL_USER:-okay}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:?err}
      MYSQL_DATABASE: ${MYSQL_DATABASE:?err}
    volumes:
      - './mysql/DB_data:/var/lib/mysql'
      - './../1DB_changes/okay_clean.sql:/docker-entrypoint-initdb.d/init.sql'
      - './config/mysql/startup.sh:/always-initdb.d/startup.sh:ro'
      - './config/mysql/entrypoint.sh:/custom-entrypoint.sh:ro'
    entrypoint: /custom-entrypoint.sh
    command: ["mysqld"]

networks:
  default:
    name: ${NETWORK_NAME:?err}
```

The `mariadb` service is carried over unchanged on purpose — Task 2 replaces it. Changing it here would leave this task untestable.

- [ ] **Step 6: Write the dev override file**

`dev/docker-compose.override.yml`:

```yaml
# Development overrides. Compose loads this automatically, so plain
# `docker compose up -d` still gives a dev environment.
#
# For personal tweaks create docker-compose.local.yml (gitignored) and attach it
# explicitly:  docker compose -f docker-compose.yml -f docker-compose.override.yml \
#                             -f docker-compose.local.yml up -d

services:
  php85:
    container_name: ${APP_NAME:?err}-php85
    environment:
      # Xdebug 3 reads this and it wins over the ini file, so debugging is
      # switched on and off without rebuilding the image.
      XDEBUG_MODE: ${XDEBUG_MODE:-off}
      PHP_IDE_CONFIG: serverName=${VIRTUAL_HOST:?err}
    volumes:
      - '..:/var/www/html'
    extra_hosts:
      - "host.docker.internal:host-gateway"

  nginx:
    container_name: ${APP_NAME:?err}-nginx
    hostname: ${VIRTUAL_HOST:?err}
    ports:
      - '${BIND_IP:-127.0.0.1}:${HTTP_PORT:?err}:80'
    volumes:
      - '..:/var/www/html'

  mariadb:
    container_name: ${APP_NAME:?err}-mariadb
    ports:
      - '${BIND_IP:-127.0.0.1}:${MYSQL_PORT:?err}:3306'
```

- [ ] **Step 7: Add the new environment keys**

Append to `dev/.env-example`, and make the same additions to your local `dev/.env`:

```bash
# Uid/gid the container's www-data is mapped to. Must match your host user
# (`id -u` / `id -g`), otherwise bind-mounted files come out unwritable.
APP_UID=1000
APP_GID=1000

# Interface the published ports bind to. 127.0.0.1 keeps the database and the
# site off the local network; set to 0.0.0.0 only if you deliberately want
# another machine to reach them.
BIND_IP=127.0.0.1

# Xdebug: off | debug | develop | profile (comma-separated for several).
# Takes effect on `docker compose up -d`, no rebuild needed.
XDEBUG_MODE=off

# Database account the init script uses. The application itself connects as root
# in dev — see config/config.local.php.
MYSQL_USER=okay

TZ=Europe/Kyiv
```

- [ ] **Step 8: Free the override file from .gitignore**

In the repository root `.gitignore`, replace the line `dev/docker-compose.override.yml` with:

```
dev/docker-compose.local.yml
```

The override file is now committed dev configuration; `docker-compose.local.yml` takes over as the ignored personal-tweaks file.

- [ ] **Step 9: Delete the superseded files**

```bash
git rm -r dev/docker/php dev/docker/nginx dev/config/php/php.ini dev/config/php/conf.d
```

- [ ] **Step 10: Rebuild and run the checks**

```bash
cd dev
docker compose down
docker compose build php85
docker compose up -d
./bin/smoke.sh
```

Expected: PASS on every check. If `conf.d` still shows only `xdebug.ini`, the old bind mount survived — confirm `./config/php` no longer appears under `php85.volumes`.

- [ ] **Step 11: Confirm the site and the test suite still work**

```bash
docker compose exec php85 php vendor/bin/phpunit
curl -sS -o /dev/null -w '%{http_code}\n' -H "Host: ${VIRTUAL_HOST}" http://127.0.0.1:${HTTP_PORT}/
```

Expected: PHPUnit green, HTTP `200`. Then open `http://okaycms.loc/` in a browser and look at it — a 200 from a broken page is still a broken page.

- [ ] **Step 12: Commit**

```bash
git add -A dev .gitignore
git -c user.name=devSviat -c user.email=devsviat@proton.me commit -m "build(docker): split compose into base and override, rebuild the PHP image

The php85 service mounted ./config/php over /usr/local/etc/php, which hid
every docker-php-ext-*.ini the image generates — that is why php.ini
carried manual 'extension =' lines, and why a newly installed extension
silently failed to load. PHP_INI_SCAN_DIR now appends a custom.d
directory instead, leaving conf.d intact.

The three single-purpose Dockerfiles collapse into one multi-stage file.
The nginx and mysql images existed only to usermod uid 1000; the PHP one
now takes APP_UID/APP_GID as build args instead of hardcoding it."
```

---

### Task 2: Move the database to a named volume and replace the custom entrypoint

**Files:**
- Create: `dev/config/mysql/db-init.sh`
- Modify: `dev/docker-compose.yml` (mariadb service, new `volumes:` block), `dev/docker-compose.override.yml` (seed mount, `db-init` service), `dev/bin/smoke.sh`, `dev/.gitignore`
- Delete: `dev/docker/mysql/Dockerfile`, `dev/config/mysql/entrypoint.sh`, `dev/config/mysql/startup.sh`

**Interfaces:**
- Consumes: `expect_contains` from `dev/bin/smoke.sh` (Task 1).
- Produces: the named volume `db_data`; the `db-init` service; the env key `DB_INIT_SMTP`.

- [ ] **Step 1: Write the failing checks**

Insert this block into `dev/bin/smoke.sh`, immediately before the final `echo` / `if [ "$fails" -gt 0 ]` section:

```bash
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
```

`smoke.sh` needs `APP_NAME` from the env file. Add this directly under the `cd "$(dirname "$0")/.."` line:

```bash
# shellcheck disable=SC1091
set -a; . ./.env; set +a
```

- [ ] **Step 2: Run to verify they fail**

```bash
cd dev && ./bin/smoke.sh
```

Expected: FAIL on the named-volume check (the mount is still `bind`) and on the entrypoint check (it is currently `/custom-entrypoint.sh`).

- [ ] **Step 3: Write the init script**

`dev/config/mysql/db-init.sh`:

```sh
#!/bin/sh
# Runs on every `docker compose up`, after mariadb reports healthy, then exits.
#
# Replaces the old config/mysql/entrypoint.sh, which reimplemented the official
# image's internals (docker_setup_env, docker_init_database_dir, ...) purely to
# obtain an /always-initdb.d hook the image does not provide. The stock
# entrypoint is now left untouched, so an image update cannot break startup.
set -eu

run_sql() {
    mariadb --host=mariadb --user=root --password="${MYSQL_ROOT_PASSWORD}" \
            --database="${MYSQL_DATABASE}"
}

echo "db-init: resetting the 'admin' manager to the default password"
# Plain idempotent SQL instead of the old CREATE PROCEDURE / CALL / DROP dance.
run_sql <<'SQL'
INSERT INTO ok_managers (login, password, email, lang, menu_status)
SELECT 'admin', '$apr1$8m1u0cp4$MYUZf5fVcidsoTaFb0P9P1', 'support@demo.com', 'ua', 1
WHERE NOT EXISTS (SELECT 1 FROM ok_managers WHERE login = 'admin');

UPDATE ok_managers
SET password = '$apr1$8m1u0cp4$MYUZf5fVcidsoTaFb0P9P1'
WHERE login = 'admin';
SQL

if [ "${DB_INIT_SMTP:-1}" = "1" ]; then
    echo "db-init: pointing the SMTP settings at mailpit:1025"
    # Okay/Core/Notify.php picks a transport from the use_smtp setting: falsy
    # sends through mail(), which msmtp already hands to Mailpit. These rows make
    # the other branch safe too, so turning use_smtp on in the admin panel cannot
    # reach a real mail server from a developer's machine.
    # ok_settings.param is UNIQUE, and a stock okay_clean.sql has no smtp rows at
    # all, so this has to insert rather than update.
    run_sql <<'SQL'
INSERT INTO ok_settings (param, value) VALUES ('smtp_server', 'mailpit')
ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO ok_settings (param, value) VALUES ('smtp_port', '1025')
ON DUPLICATE KEY UPDATE value = VALUES(value);
SQL
fi

echo "db-init: done"
```

- [ ] **Step 4: Point the base compose file at a named volume**

In `dev/docker-compose.yml`, replace the whole `mariadb` service body's `volumes`/`entrypoint`/`command` section with:

```yaml
    volumes:
      - 'db_data:/var/lib/mysql'
    healthcheck:
      test: ["CMD", "/usr/local/bin/healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 30
      start_period: 30s
```

and add a top-level block just above `networks:`:

```yaml
volumes:
  db_data:
```

- [ ] **Step 5: Add the seed mount and the db-init service to the dev override**

In `dev/docker-compose.override.yml`, extend the `mariadb` service with the seed dump, and append a new service:

```yaml
  mariadb:
    container_name: ${APP_NAME:?err}-mariadb
    ports:
      - '${BIND_IP:-127.0.0.1}:${MYSQL_PORT:?err}:3306'
    volumes:
      # Read-only: this is the seed dump and must never be written back to.
      - '../1DB_changes/okay_clean.sql:/docker-entrypoint-initdb.d/init.sql:ro'

  db-init:
    image: mariadb:10.11
    container_name: ${APP_NAME:?err}-db-init
    restart: "no"
    depends_on:
      mariadb:
        condition: service_healthy
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:?err}
      MYSQL_DATABASE: ${MYSQL_DATABASE:?err}
      DB_INIT_SMTP: ${DB_INIT_SMTP:-1}
    volumes:
      - './config/mysql/db-init.sh:/db-init.sh:ro'
    entrypoint: ["/bin/sh", "/db-init.sh"]
```

- [ ] **Step 6: Delete the superseded files and their ignore rules**

```bash
git rm -r dev/docker/mysql dev/config/mysql/entrypoint.sh dev/config/mysql/startup.sh
```

In `dev/.gitignore`, delete the line `mysql/DB_data/`. Then remove the directory that is no longer used:

```bash
rm -rf dev/mysql
```

Add `DB_INIT_SMTP=1` to `dev/.env-example` and `dev/.env`, under the `TZ` line, with the comment:

```bash
# Set to 0 to stop db-init from rewriting the SMTP settings to point at Mailpit.
DB_INIT_SMTP=1
```

- [ ] **Step 7: Recreate the environment from scratch**

The old data is being discarded by decision, so this is a clean rebuild:

```bash
cd dev
docker compose down
docker compose up -d
docker compose logs db-init
```

Expected in the logs: `db-init: resetting the 'admin' manager to the default password`, then `db-init: done`.

- [ ] **Step 8: Run the checks**

```bash
./bin/smoke.sh
```

Expected: PASS on every check, including the four from Task 1.

- [ ] **Step 9: Confirm the admin panel and the reset cycle**

Open `http://okaycms.loc/admin` and log in as `admin` / `1234` — in a browser, looking at the page. Then prove the reset works, which is the whole point of the change:

```bash
docker compose down -v && docker compose up -d && sleep 40 && ./bin/smoke.sh
```

Expected: the volume is recreated, the dump reloads, all checks pass again.

- [ ] **Step 10: Commit**

```bash
git add -A dev
git -c user.name=devSviat -c user.email=devsviat@proton.me commit -m "build(docker): move the database to a named volume

dev/mysql/DB_data put 339 MB of InnoDB files inside the working tree,
kept out of git only by a .gitignore line, and forced a whole Dockerfile
whose only job was usermod -u 1000 mysql — hardcoded, so anyone whose uid
is not 1000 could not start the container. 'docker compose down -v' now
resets the database instead of leaving files behind.

The custom entrypoint, which copied the official image's internals to get
an /always-initdb.d hook, is replaced by a one-shot db-init service that
waits for the healthcheck and runs plain idempotent SQL."
```

---

### Task 3: Send nginx logs to the container's stdout

**Files:**
- Modify: `dev/config/nginx/templates/okay.conf.template`, `dev/bin/smoke.sh`, `dev/.gitignore`
- Delete: `dev/config/nginx/okay.conf`, `dev/logs/`

**Interfaces:**
- Consumes: `expect_contains` from `dev/bin/smoke.sh` (Task 1).

- [ ] **Step 1: Write the failing check**

Add to `dev/bin/smoke.sh`, before the final summary block:

```bash
echo
echo "Logging"
expect_contains "nginx access logs reach docker compose logs" \
    "GET /" \
    sh -c "curl -sS -o /dev/null -H 'Host: ${VIRTUAL_HOST}' http://127.0.0.1:${HTTP_PORT}/ ; sleep 1 ; docker compose logs --tail=20 nginx"
expect_missing "nginx no longer writes into the repository" \
    "dev/logs" \
    docker compose exec -T nginx cat /etc/nginx/conf.d/okay.conf
```

- [ ] **Step 2: Run to verify it fails**

```bash
cd dev && ./bin/smoke.sh
```

Expected: FAIL on both — the vhost still points `access_log` and `error_log` at `/var/www/html/dev/logs/`.

- [ ] **Step 3: Redirect the logs**

In `dev/config/nginx/templates/okay.conf.template`, replace:

```nginx
    access_log /var/www/html/dev/logs/application.access.log;
    error_log /var/www/html/dev/logs/application.error.log;
```

with:

```nginx
    # Container-native logging: `docker compose logs nginx` instead of files
    # inside the working tree.
    access_log /dev/stdout;
    error_log  /dev/stderr;
```

- [ ] **Step 4: Remove the log directory and the stale generated vhost**

The old setup mounted `./config/nginx` onto `/etc/nginx/conf.d`, so the entrypoint's rendered `okay.conf` was written back into the repository. Task 1 mounts only `templates/`, so the rendered file now stays inside the container.

```bash
git rm -r dev/logs dev/config/nginx/okay.conf
```

In `dev/.gitignore`, delete these three lines:

```
config/nginx/okay.conf
logs/application.error.log
logs/application.access.log
```

- [ ] **Step 5: Run the checks**

```bash
docker compose up -d --force-recreate nginx
./bin/smoke.sh
```

Expected: PASS on every check.

- [ ] **Step 6: Commit**

```bash
git add -A dev
git -c user.name=devSviat -c user.email=devsviat@proton.me commit -m "build(nginx): log to stdout instead of into the working tree

nginx wrote its access and error logs to /var/www/html/dev/logs, so
'docker compose logs nginx' showed nothing. Mounting only templates/
also stops the entrypoint from rendering okay.conf back into the repo,
which is why that file was gitignored."
```

---

### Task 4: Mailpit on both mail transports

`Okay/Core/Notify.php` picks its transport at line 180: `use_smtp` truthy calls `SMTP()`, otherwise `mail()`. A stock `okay_clean.sql` has no `use_smtp` row, so `mail()` is the live path — but both need covering.

**Files:**
- Modify: `dev/docker-compose.override.yml`, `dev/.env-example`, `dev/.env`, `dev/bin/smoke.sh`

**Interfaces:**
- Consumes: `expect_contains` (Task 1); `db-init` and `DB_INIT_SMTP` (Task 2); `sendmail_path` from `dev.ini` and the `msmtp` package (Task 1).
- Produces: the `mailpit` service; the env key `MAILPIT_PORT`.

- [ ] **Step 1: Write the failing check**

Add to `dev/bin/smoke.sh`, before the final summary block:

```bash
echo
echo "Mail"
expect_contains "mail() is routed to Mailpit via msmtp" \
    "msmtp" \
    docker compose exec -T php85 php -r 'echo ini_get("sendmail_path");'
expect_contains "the SMTP settings point at Mailpit" \
    "mailpit" \
    docker compose exec -T mariadb sh -c \
    'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT value FROM ok_settings WHERE param = \"smtp_server\";"'
expect_contains "a message sent from PHP arrives in Mailpit" \
    '"total":1' \
    sh -c "docker compose exec -T php85 php -r 'mail(\"smoke@example.com\", \"smoke test\", \"body\");' ; sleep 2 ; curl -sS http://127.0.0.1:${MAILPIT_PORT:-8025}/api/v1/messages?limit=1"
```

- [ ] **Step 2: Run to verify it fails**

```bash
cd dev && ./bin/smoke.sh
```

Expected: FAIL on the delivery check — there is no Mailpit container yet, so the curl fails. The first two may already pass from Tasks 1 and 2.

- [ ] **Step 3: Add the Mailpit service**

Append to `dev/docker-compose.override.yml`:

```yaml
  mailpit:
    image: axllent/mailpit:v1.27
    container_name: ${APP_NAME:?err}-mailpit
    restart: unless-stopped
    environment:
      TZ: ${TZ:-Europe/Kyiv}
      # Okay/Core/Notify.php:92 sets SMTPAuth = true unconditionally, so Mailpit
      # has to accept an AUTH command it would otherwise refuse on a plain
      # connection. Both flags are dev-only and this service is dev-only.
      MP_SMTP_AUTH_ACCEPT_ANY: 1
      MP_SMTP_AUTH_ALLOW_INSECURE: 1
    ports:
      - '${BIND_IP:-127.0.0.1}:${MAILPIT_PORT:-8025}:8025'
```

- [ ] **Step 4: Add the port to the env files**

Add to `dev/.env-example` and `dev/.env`:

```bash
# Mailpit web UI. Every message the site sends is caught here and none leave the
# machine, which is a stronger guarantee than TEST_INTERNAL_EMAIL's redirect.
MAILPIT_PORT=8025
```

- [ ] **Step 5: Run the checks**

```bash
docker compose up -d
./bin/smoke.sh
```

Expected: PASS on every check.

- [ ] **Step 6: Look at it**

Open `http://127.0.0.1:8025` and confirm the "smoke test" message is listed, with a readable body. Then flip the other transport and confirm it also lands:

```bash
docker compose exec -T mariadb sh -c \
  'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "INSERT INTO ok_settings (param, value) VALUES (\"use_smtp\", \"1\") ON DUPLICATE KEY UPDATE value = \"1\";"'
```

Send a test email from the admin panel, confirm it appears in Mailpit, then set `use_smtp` back to `0` the same way.

- [ ] **Step 7: Commit**

```bash
git add -A dev
git -c user.name=devSviat -c user.email=devsviat@proton.me commit -m "feat(docker): catch outgoing mail with Mailpit

Notify.php sends two ways — mail() when use_smtp is falsy, PHPMailer over
SMTP otherwise — so both are redirected: msmtp hands mail() to Mailpit,
and db-init points the smtp_server/smtp_port settings at it. Nothing
leaves the machine, which TEST_INTERNAL_EMAIL alone did not guarantee."
```

---

### Task 5: Production profile — REVISED 2026-07-30

> **This section was rewritten after the owner shared `devSviat/broken`, a second
> OkayCMS deployment that runs production on Dokploy.** The original version
> published ports, had no persistent storage for runtime-written paths, and had no
> scheduler. All three were wrong. Decisions taken: support **both** Dokploy and
> standalone; **add a scheduler service**.

**Files:**
- Create: `dev/docker-compose.prod.yml`, `dev/docker-compose.standalone.yml`, `config/config.local.prod-example.php`, `dev/bin/smoke-prod.sh`
- Modify: `dev/docker-compose.yml` (networks, anchors, volumes), `dev/docker/Dockerfile` (scheduler entrypoint), `.gitignore` (repo root)
- Note: `dev/config/php/prod/prod.ini` **already exists** — created during Task 1 to make the Dockerfile's prod stage buildable. Do not recreate it.

**Interfaces:**
- Consumes: the `prod` and `nginx-prod` stages and `Dockerfile.dockerignore` (Task 1); `expect_contains` / `expect_missing` (Task 1).

#### 5.1 The data-loss problem this task must solve

OkayCMS writes into its own source tree. In production the source tree lives inside
the image, so anything written at runtime is destroyed on the next deploy unless it
is mounted. Verified against this repository:

| Path | Written by | Tracked in git? | If not persisted |
| --- | --- | --- | --- |
| `files/` | product image uploads, resizes | no | **product photos lost** |
| `backend/files/{export,export_users,import,watermark}` | CSV export/import, watermark upload | no | exports and watermark lost |
| `cache/` | CSS/JS bundles | no | regenerated — harmless |
| `compiled/`, `backend/design/compiled/`, `Okay/xml/compiled/` | Smarty | no | regenerated — **use tmpfs** |
| `Okay/log/` | application log | no | history lost |
| `design/*/css/theme-settings.css` | `CssConfig`, from the admin theme editor | **yes** | see below — **not** a volume |
| `design/*/lang/local.*.php` | admin translation editor | **yes** | see below — **not** a volume |
| `robots.txt` | admin editor | **yes** | see below — **not** a volume |

**The last three stay in the image. Do not mount them.** The owner edits these in
the IDE, pushes to GitHub, and deploys from there, so git is the source of truth and
the deployed file must match it. Persisting them in a volume would do real harm: the
running site would keep a stale copy and silently ignore every subsequent deploy.

The tradeoff that must be written into the README, because it will surprise someone
otherwise: **editing the theme, translations, or robots.txt through the admin panel
in production has no lasting effect** — the next deploy overwrites it from git. That
is deliberate under this workflow, not a bug. Anyone wanting a theme change makes it
in the repository.

One thing to check during implementation rather than assume: the prod stage runs as
`www-data`, and `design/` is not among the directories chowned in the Dockerfile. If
the admin theme editor attempts a write there it may fail with a permission error
rather than failing silently. Find out which it does and record it — a loud failure
is the better outcome here and worth keeping.

#### 5.2 Base file changes (`dev/docker-compose.yml`)

Adopted from `broken`, which gets these right:

- **Two networks.** `frontend` (bridge) and `backend` (`internal: true`). `mariadb`
  joins only `backend`, so it has no route off the host at all. `php85` and the
  scheduler join both. This replaces the single default network.
- **A YAML anchor for the shared environment**, `x-php-env: &php-env`, consumed by
  `php85` and the scheduler so their env cannot drift apart.
- **Named volumes** for `files`, `backend_files`, `app_cache`, `app_log`.
- **tmpfs** for `compiled/` and `backend/design/compiled/`, sized as in `broken`
  (`256m` and `64m`), so Smarty output is cleared on every container recreate.

Keep the existing `no-new-privileges`, the real php-fpm listener healthcheck, and
the `healthcheck.sh --connect --innodb_initialized` probe for MariaDB. Note for
anyone comparing with `broken`: its MariaDB probe passes the root password on the
healthcheck command line, which then shows up in `docker inspect`. Ours does not.

#### 5.3 Scheduler service

`./ok scheduler:run` exists and `docs/scheduler.md` documents it, but nothing runs
it — scheduled tasks simply never fire in production. Add a `scheduler` service on
the same image as `php85`, following `broken`:

- `supercronic` invoked through `tini` as PID 1, so signals and zombies are handled.
- A crontab at `/etc/supercronic/crontab` calling `./ok scheduler:run`.
- Its own healthcheck — the image's php-fpm healthcheck must be overridden, because
  the scheduler does not listen on 9000. `broken` uses `pgrep -f supercronic`; that
  proves the process exists, not that jobs run, so treat it as a liveness check and
  say so in a comment rather than overselling it.
- Same volumes as `php85` for `files` and `Okay/log`.

#### 5.4 `docker-compose.prod.yml` — no published ports

Dokploy attaches Traefik directly to the container, so production publishes nothing.
Contents:

- `php85` and `scheduler` at `target: prod`, or pulled from a registry with
  `pull_policy: always` and a version variable. Follow `broken`'s pattern of
  `${OKAY_VERSION:-latest}` **and copy its warning**: the default must not be `:?`
  because that breaks the first Dokploy redeploy before env vars exist in the UI —
  but a production stack that silently runs `:latest` is its own hazard, so the
  README must say the variable is mandatory in the Dokploy prod environment.
- The runtime `config.local.php` mounted read-only from outside the image.
- `restart: unless-stopped`, `deploy.resources.limits`, and a `max-size` logging cap.
- MariaDB publishes nothing and stays on the `backend` network.
- An `x-traefik-labels` comment block showing the label shape, with a pointer to
  `broken`'s working example — do not invent router names, Dokploy generates them.

#### 5.5 `docker-compose.standalone.yml`

For a plain host with no Dokploy: adds `ports: ['${BIND_IP:-127.0.0.1}:${HTTP_PORT}:80']`
to nginx and nothing else. Documented invocation:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.standalone.yml up -d
```

#### 5.6 Secrets

Use Docker secrets with the image's `*_FILE` convention for `MYSQL_ROOT_PASSWORD`
and `MYSQL_PASSWORD` — plain `environment:` values are visible to anyone who can run
`docker inspect`, which was demonstrated on the running stack. `secrets:` with a
`file:` source works in plain Compose; Swarm is not required.

State the limit honestly in the README: this protects the MariaDB container's own
credentials. It does **not** protect the application's database password, because
OkayCMS reads that from `config/config.local.php`, not from the environment. For
that file the guidance is `chmod 600`, owned by the deploy user, mounted read-only,
and excluded from the build context — the exclusion is already done and verified.

#### 5.7 Steps

- [ ] **Step 1: Write the failing check** — extend `dev/bin/smoke-prod.sh` (create it
      per the original Task 5 text, which still stands) with assertions that the prod
      image contains no `config/config.local.php`, no `dev/.env`, no `dev.ini`, no
      xdebug, and that `prod.ini` is present. Add one further assertion: that the
      composed prod config publishes **no** ports —
      `docker compose -f docker-compose.yml -f docker-compose.prod.yml config` must
      contain no `published:` entry.
- [ ] **Step 2: Run it, watch it fail.**
- [ ] **Step 3:** base-file changes from 5.2.
- [ ] **Step 4:** scheduler service and its crontab from 5.3.
- [ ] **Step 5:** `docker-compose.prod.yml` from 5.4.
- [ ] **Step 6:** `docker-compose.standalone.yml` from 5.5.
- [ ] **Step 7:** secrets from 5.6, and `config/config.local.prod-example.php`.
- [ ] **Step 8:** run `dev/bin/smoke-prod.sh`; then confirm the **dev** stack still
      passes `dev/bin/smoke.sh` after the base-file changes — the two networks and the
      new volumes touch dev as well, and that is the likeliest place to break
      something.
- [ ] **Step 9:** verify persistence for real, which is the point of 5.1: bring up the
      standalone prod stack, upload an image through the admin panel, change a theme
      colour, then `docker compose down && up -d` and confirm **both** survived.
      A passing config check is not evidence that data persists.
- [ ] **Step 10: Commit.**

### Task 6: Documentation

**Files:**
- Modify: `dev/README.md` (substantial rewrite), `CLAUDE.md`

- [ ] **Step 1: Rewrite `dev/README.md`**

Keep the existing Ukrainian sections that are still accurate — image resizing via `PRODUCTION_DOMAIN`, the Xdebug/PhpStorm walkthrough with its two screenshots, and the reverse-proxy tip — and rewrite the rest. The new document must cover:

1. **Quick start** — copy `.env-example` to `.env`, set `APP_UID`/`APP_GID` to `id -u` / `id -g`, `docker compose up -d`, then `dev/bin/smoke.sh`.
2. **A warning, prominently placed:** `docker compose down -v` now **deletes the database**. It previously left files in `dev/mysql/DB_data`. This inverts a habit and will surprise someone.
3. **Xdebug** — `XDEBUG_MODE` in `.env`, values `off` / `debug`, effective on `up -d` with no rebuild.
4. **Mail** — everything is caught by Mailpit at `http://127.0.0.1:8025`; both transports are covered; `DB_INIT_SMTP=0` opts out.
5. **Logs** — `docker compose logs nginx`, no longer files under `dev/logs`.
6. **Ports** — bound to `127.0.0.1` by default; `BIND_IP=0.0.0.0` to expose deliberately.
7. **Production** — the exact `-f docker-compose.yml -f docker-compose.prod.yml` invocation, the `config.local.prod.php` step, and an honest limitations section: no TLS, no backups, no secrets manager, **single-node only** because `Config::salt` derives from `stat()` of `config/config.php`, so every rebuild invalidates admin recovery tokens and two replicas can disagree.

- [ ] **Step 2: Update `CLAUDE.md`**

In the Commands section, under the `cd dev && docker compose up -d` line, note that `dev/bin/smoke.sh` verifies the environment and that `docker compose down -v` destroys the database. Leave `docker compose exec php85 …` as it is — the service name has not changed.

- [ ] **Step 3: Verify the documented commands actually work**

Run every command block in the new README verbatim, from a clean `docker compose down -v`. A README that has not been executed is a guess.

- [ ] **Step 4: Commit**

```bash
git add dev/README.md CLAUDE.md
git -c user.name=devSviat -c user.email=devsviat@proton.me commit -m "docs(docker): document the rebuilt environment

Covers the named volume and the fact that 'down -v' now destroys the
database, the XDEBUG_MODE switch, Mailpit, stdout logging, loopback port
binding, and the production profile's limitations — no TLS, no backups,
single-node because Config::salt derives from stat() of config.php."
```

---

## Self-Review

**Spec coverage.** Every spec section maps to a task: §1 file layout → Tasks 1–3, 5; §2 `PHP_INI_SCAN_DIR` → Task 1; §3 Dockerfile → Task 1; §4 database and `db-init` → Task 2; §5 Mailpit → Task 4; §6 the fix table → C3/S1/S3/S6/H4 in Task 1, S4 in Task 1 (`expose: 9001` is simply not carried into the rewritten file), S2 in Tasks 1–2, S5 in Task 1, H3 in Task 3, H2 in Task 1's ini files; §6a error handling → Task 1; §7 prod profile → Task 5; §8.1 `config.local.php` → Tasks 1 and 5; §8.2 the salt constraint → documented in Tasks 5 and 6. Verification items 1–11 are distributed across the tasks' check steps.

**Naming consistency.** `expect_contains` / `expect_missing` / `fails` are defined in Task 1 and used unchanged in Tasks 2–4; `check_absent` / `check_present` belong to `smoke-prod.sh` only. Stage names `base` / `dev` / `prod` / `nginx-prod` match between the Dockerfile, `docker-compose.yml` (`target: dev`) and `docker-compose.prod.yml` (`target: prod`, `target: nginx-prod`). Env keys `APP_UID`, `APP_GID`, `BIND_IP`, `XDEBUG_MODE`, `MYSQL_USER`, `TZ`, `DB_INIT_SMTP`, `MAILPIT_PORT` are introduced once and referenced consistently.

**One deviation from the spec, recorded here rather than silently applied.** The spec's verification item 11 expected `php -i | grep error_log` to show `/dev/stderr`. Checking the running container showed the `php:fpm` image already ships `docker.conf` with `error_log = /proc/self/fd/2`, `catch_workers_output = yes` and `clear_env = no`. Setting `error_log` in our own ini would fight a correct existing arrangement, so `okay.ini` leaves it unset and relies on PHP's default SAPI target. The comment in `okay.ini` states this.
