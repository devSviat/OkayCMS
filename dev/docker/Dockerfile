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

# tini and supercronic back the `scheduler` service (dev/docker-compose.yml):
# tini is PID 1 so signals are forwarded and dead children are reaped, and
# supercronic is the cron daemon that actually calls `./ok scheduler:run`.
# Both live in this stage, not just `prod`, so the same binaries back the
# scheduler in dev too — no separate install path to drift out of sync.
ARG SUPERCRONIC_VERSION=v0.2.48
ARG TARGETARCH
RUN apt-get update \
 && apt-get install -y --no-install-recommends tini \
 && rm -rf /var/lib/apt/lists/* \
 && case "${TARGETARCH}" in \
        amd64) SUPERCRONIC_SHA256=88c1b66b94c486f972fdd1a4d1f901e3e75ff04f749cddd60c5db573e3a33c6c ;; \
        arm64) SUPERCRONIC_SHA256=50ae8755e04fa72812d0a1bc47a112a856811cc91cce7b6c875c378a850788bc ;; \
        *) echo "unsupported architecture for supercronic: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
 && curl -fsSL -o /usr/local/bin/supercronic \
        "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-${TARGETARCH}" \
 && echo "${SUPERCRONIC_SHA256}  /usr/local/bin/supercronic" | sha256sum -c - \
 && chmod +x /usr/local/bin/supercronic
COPY dev/config/supercronic/crontab /etc/supercronic/crontab

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
 && mkdir -p compiled cache files backend/files backend/design/compiled Okay/log \
 && chown -R www-data:www-data vendor compiled cache files backend/files backend/design/compiled Okay/log

USER www-data

# ───────────────────────────── nginx-prod ───────────────────────────
# Static assets come from the same build as the PHP code, so the two containers
# cannot drift apart.
FROM nginx:1.29-alpine AS nginx-prod
COPY --from=prod /var/www/html /var/www/html
