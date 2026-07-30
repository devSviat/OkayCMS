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

### Task 5: Production profile

**Files:**
- Create: `dev/docker-compose.prod.yml`, `dev/config/php/prod/prod.ini`, `config/config.local.prod-example.php`
- Modify: `.gitignore` (repo root), `dev/bin/smoke.sh`

**Interfaces:**
- Consumes: the `prod` and `nginx-prod` stages and `Dockerfile.dockerignore` (Task 1); `expect_contains` / `expect_missing` (Task 1).

- [ ] **Step 1: Write the failing check**

Add a separate script `dev/bin/smoke-prod.sh` — these checks build an image rather than talk to the running dev stack, so they do not belong in the main run:

```bash
#!/usr/bin/env bash
# Production image checks. Builds the prod image and inspects it; nothing is deployed.
#   dev/bin/smoke-prod.sh
set -uo pipefail
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a

fails=0
IMAGE="${APP_NAME}-prod-smoke"

echo "Building the prod image"
if ! docker build --target prod -f docker/Dockerfile -t "$IMAGE" .. ; then
    echo "FAIL  the prod stage does not build"
    exit 1
fi

check_absent() {  # check_absent <description> <path>
    if docker run --rm --entrypoint sh "$IMAGE" -c "test -e '$2'" 2>/dev/null; then
        printf '  FAIL  %s (%s is present in the image)\n' "$1" "$2"
        fails=$((fails + 1))
    else
        printf '  ok    %s\n' "$1"
    fi
}

check_present() {  # check_present <description> <path>
    if docker run --rm --entrypoint sh "$IMAGE" -c "test -e '$2'" 2>/dev/null; then
        printf '  ok    %s\n' "$1"
    else
        printf '  FAIL  %s (%s is missing from the image)\n' "$1" "$2"
        fails=$((fails + 1))
    fi
}

echo
echo "Secrets must not be baked in"
check_absent "developer database credentials are not in the image" \
    /var/www/html/config/config.local.php
check_absent "the dev .env is not in the image" /var/www/html/dev/.env

echo
echo "Dev settings must not be present"
check_absent "dev.ini is not in the image" /usr/local/etc/php/custom.d/dev.ini
check_absent "xdebug.ini is not in the image" /usr/local/etc/php/custom.d/xdebug.ini
check_present "prod.ini is in the image" /usr/local/etc/php/custom.d/prod.ini
check_present "dependencies are installed" /var/www/html/vendor/autoload.php

out=$(docker run --rm --entrypoint sh "$IMAGE" -c 'php -m' 2>&1)
if printf '%s' "$out" | grep -qi xdebug; then
    printf '  FAIL  xdebug is absent from the prod image\n'
    fails=$((fails + 1))
else
    printf '  ok    xdebug is absent from the prod image\n'
fi

docker image rm "$IMAGE" >/dev/null 2>&1

echo
if [ "$fails" -gt 0 ]; then
    printf '%d check(s) failed\n' "$fails"
    exit 1
fi
echo "all prod checks passed"
```

```bash
chmod +x dev/bin/smoke-prod.sh
```

- [ ] **Step 2: Run to verify it fails**

```bash
cd dev && ./bin/smoke-prod.sh
```

Expected: FAIL — `dev/config/php/prod/prod.ini` does not exist yet, so the build itself fails at the `COPY` in the prod stage.

**This is the check that matters most.** "developer database credentials are not in the image" is the one that would have caught the real bug: `config/config.local.php` is gitignored, but a build context reads the filesystem, so without `Dockerfile.dockerignore` the dev password ships inside any pushed image.

- [ ] **Step 3: Write the prod ini**

`dev/config/php/prod/prod.ini`:

```ini
; Production-only PHP settings, copied into the `prod` image stage.
; This file is never present in a dev image, and dev.ini is never present here.

error_reporting = E_ALL & ~E_DEPRECATED
display_errors = Off

; Source files never change inside an image, so revalidation is pure overhead.
opcache.enable = 1
opcache.validate_timestamps = 0
opcache.max_accelerated_files = 20000
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
```

- [ ] **Step 4: Write the prod compose file**

`dev/docker-compose.prod.yml`:

```yaml
# Production overrides. Never loaded automatically — opt in explicitly:
#
#   docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
#
# Note the absent -f docker-compose.override.yml: the dev overrides (source bind
# mounts, Xdebug, the seed dump, Mailpit) are simply not part of this stack.
#
# Out of scope on purpose, rather than half-built: TLS, backups, a secrets
# manager. A reverse proxy in front is assumed to terminate HTTPS.
#
# Single-node only. Okay\Core\Config derives $salt from stat() of
# config/config.php — its device id, inode and mtime — and AdminRecoveryToken
# signs with it. Two replicas built or stored differently disagree on the salt,
# and every image rebuild invalidates outstanding recovery tokens.

x-logging: &logging
  driver: json-file
  options:
    max-size: "10m"
    max-file: "3"

services:
  php85:
    build:
      target: prod
    environment:
      XDEBUG_MODE: "off"
    volumes:
      # Supplied at runtime, never baked into the image. This is where the
      # unprivileged database account and debug_mode = false are set.
      - '../config/config.local.prod.php:/var/www/html/config/config.local.php:ro'
    logging: *logging

  nginx:
    build:
      context: ..
      dockerfile: dev/docker/Dockerfile
      target: nginx-prod
    image: ${APP_NAME:?err}-nginx-prod
    ports:
      - '${BIND_IP:-127.0.0.1}:${HTTP_PORT:?err}:80'
    logging: *logging

  mariadb:
    # No ports published: the database is reachable only over the compose network.
    logging: *logging
```

- [ ] **Step 5: Write the runtime config template**

`config/config.local.prod-example.php`:

```php
;<? exit(); ?>

; Production configuration template. Copy to config/config.local.prod.php,
; fill in the real values, and keep that copy out of git — the prod compose file
; mounts it at runtime, and Dockerfile.dockerignore keeps it out of the image.

[database]

db_server = "mariadb"

; Not root. This is the unprivileged account MYSQL_USER creates in dev/.env.
db_user = "okay"
db_password = ""
db_name = "okay"

db_driver = mysql
db_prefix = ok_
db_charset = UTF8MB4
db_names = utf8mb4
db_sql_mode = "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"

[system]

; The real control over error output. index.php:14 forces display_errors off
; before anything runs and only this setting turns it back on, so false here
; means errors are never shown regardless of any php.ini.
debug_mode = false
dev_mode = false
smarty_force_compile = false
```

Add to the repository root `.gitignore`, next to the existing `config/config.local.php` line:

```
config/config.local.prod.php
```

- [ ] **Step 6: Run the checks**

```bash
cd dev && ./bin/smoke-prod.sh
```

Expected: PASS on every check. If "developer database credentials are not in the image" fails, BuildKit did not read the per-Dockerfile ignore file — move it with `git mv dev/docker/Dockerfile.dockerignore .dockerignore` and re-run.

- [ ] **Step 7: Confirm the full prod stack builds**

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
```

Expected: all three images build. Do **not** claim the prod profile runs — it is not deployed anywhere and that is not being tested.

- [ ] **Step 8: Commit**

```bash
git add -A dev config/config.local.prod-example.php .gitignore
git -c user.name=devSviat -c user.email=devsviat@proton.me commit -m "build(docker): add an opt-in production profile

Production reads its database credentials from config/config.local.php
like dev does. That file is gitignored, but a build context reads the
filesystem rather than git, so a naive COPY would bake the developer's
password into a pushed image; the dockerignore keeps it out and the prod
compose file supplies the file at runtime instead.

smoke-prod.sh asserts exactly that, along with the absence of dev.ini and
xdebug from the image. The profile is single-node: Config::salt is
derived from stat() of config/config.php."
```

---

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
