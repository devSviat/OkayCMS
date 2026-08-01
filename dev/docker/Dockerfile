# syntax=docker/dockerfile:1
#
# Контекст білду — корінь репозиторію, тому шляхи в COPY йдуть з префіксом dev/.
# Стадії: base -> dev | prod -> nginx-prod

ARG PHP_VERSION=8.5

# ─────────────────────────────── base ───────────────────────────────
FROM php:${PHP_VERSION}-fpm AS base

# Образи php:*-fpm перезбираються рідше, ніж Debian патчить пакети. Першим
# шаром — щоб усе нижче лягало на пропатчену базу.
RUN apt-get update \
 && apt-get upgrade -y --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

# install-php-extensions сам підтягує системні бібліотеки й прибирає за собою.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        xsl \
        xmlwriter \
        opcache

# Образи php:*-fpm мають лише C/POSIX, а ImportAdmin попереджає, коли
# setlocale не вдається.
RUN apt-get update \
 && apt-get install -y --no-install-recommends locales \
 && sed -i 's/^# *\(uk_UA.UTF-8 UTF-8\)/\1/' /etc/locale.gen \
 && locale-gen uk_UA.UTF-8 \
 && rm -rf /var/lib/apt/lists/*
ENV LANG=uk_UA.UTF-8 \
    LANGUAGE=uk_UA:uk \
    LC_ALL=uk_UA.UTF-8

# Підганяє www-data під хостового користувача, щоб bind-mounted файли лишались
# доступними для запису. Не UID — це readonly-змінна в bash/zsh.
ARG APP_UID=1000
ARG APP_GID=1000
RUN groupmod -o -g "${APP_GID}" www-data \
 && usermod  -o -u "${APP_UID}" -g "${APP_GID}" www-data

# Додається до шляху сканування, а не монтується поверх /usr/local/etc/php —
# інакше сховала б кожен docker-php-ext-*.ini образу.
ENV PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/usr/local/etc/php/custom.d"
RUN mkdir -p /usr/local/etc/php/custom.d
COPY dev/config/php/custom.d/okay.ini /usr/local/etc/php/custom.d/okay.ini

# Обслуговують сервіс `scheduler`. У base, а не в prod, щоб dev і prod мали ті
# самі бінарники.
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

# msmtp передає mail() у Mailpit; див. sendmail_path у dev.ini.
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
 && mkdir -p compiled cache files backend/files backend/design/compiled Okay/log Okay/xml/compiled \
 && chown -R www-data:www-data vendor compiled cache files backend/files backend/design/compiled Okay/log Okay/xml/compiled

USER www-data

# ───────────────────────────── nginx-prod ───────────────────────────
# Статика — з того самого білду, що й PHP-код, тож контейнери не розійдуться.
FROM nginx:1.29-alpine AS nginx-prod

# Те саме, що й apt-get upgrade у base. Сам nginx не рухається — він
# ставиться з репозиторію nginx.org, а не з alpine/main.
RUN apk upgrade --no-cache

COPY --from=prod /var/www/html /var/www/html
