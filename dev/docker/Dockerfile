# syntax=docker/dockerfile:1
#
# Контекст білду — корінь репозиторію, тому шляхи в COPY йдуть з префіксом dev/.
# Стадії: base -> dev | prod
#
# Вебсервер і PHP — один процес (FrankenPHP на базі Caddy), тож окремого
# контейнера nginx і стадії nginx-prod більше немає.

# ─────────────────────────────── base ───────────────────────────────
# Версії образів запінені до патча, оновлює їх Dependabot (.github/dependabot.yml).
# Образ побудований на офіційному php:8.5, Debian 13 (trixie).
FROM dunglas/frankenphp:1.12.7-php8.5-trixie AS base

# Образи оновлюються рідше, ніж Debian патчить пакети. Першим шаром — щоб усе
# нижче лягало на пропатчену базу.
RUN apt-get update \
 && apt-get upgrade -y --no-install-recommends \
 && rm -rf /var/lib/apt/lists/*

# install-php-extensions уже є в образі й сам підтягує системні бібліотеки.
# opcache, mbstring, curl, json, dom, xmlreader, xmlwriter і SimpleXML
# скомпільовані в базовий образ, тому тут лише те, чого бракує.
RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        xsl

# Базовий образ має лише C/POSIX, а ImportAdmin попереджає, коли setlocale
# не вдається.
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

# Стан і сертифікати Caddy. Процес працює під www-data (див. USER у стадіях
# нижче), тож теки мусять належати йому — інакше сервер не стартує.
RUN chown -R www-data:www-data /data/caddy /config/caddy

# Обслуговують сервіс `scheduler`. У base, а не в prod, щоб dev і prod мали ті
# самі бінарники.
ARG SUPERCRONIC_VERSION=v0.2.49
ARG TARGETARCH
RUN apt-get update \
 && apt-get install -y --no-install-recommends tini \
 && rm -rf /var/lib/apt/lists/* \
 && case "${TARGETARCH}" in \
        amd64) SUPERCRONIC_SHA256=a53ae236602c7338aba3fbaff40bda6300eae3b9fedb8261eb06cfe3724430c1 ;; \
        arm64) SUPERCRONIC_SHA256=02aa0cb229ba09050cba6638059dadb9eedc2276632ea43d6a57a2f8c1629dd5 ;; \
        *) echo "unsupported architecture for supercronic: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
 && curl -fsSL -o /usr/local/bin/supercronic \
        "https://github.com/aptible/supercronic/releases/download/${SUPERCRONIC_VERSION}/supercronic-linux-${TARGETARCH}" \
 && echo "${SUPERCRONIC_SHA256}  /usr/local/bin/supercronic" | sha256sum -c - \
 && chmod +x /usr/local/bin/supercronic
COPY dev/config/supercronic/crontab /etc/supercronic/crontab

WORKDIR /var/www/html

# Шлях саме /etc/frankenphp/Caddyfile: образ запускається з
# `--config /etc/frankenphp/Caddyfile`, а не з /etc/caddy/, як каже загальна
# документація Caddy.
#
# Останнім шаром base навмисно: конфіг правиться найчастіше, а вище лежить
# завантаження supercronic по мережі — у зворотному порядку кожна правка
# Caddyfile тягла б його наново.
COPY dev/config/caddy/Caddyfile /etc/frankenphp/Caddyfile

# Caddyfile слухає :8080, а не :80. Причина — no-new-privileges:true в compose:
# він блокує file capabilities, тож cap_net_bind_service на бінарнику
# frankenphp під non-root не спрацював би.
EXPOSE 8080

# ──────────────────────────────── dev ───────────────────────────────
FROM base AS dev

RUN install-php-extensions xdebug
COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer

# msmtp передає mail() у Mailpit; див. sendmail_path у dev.ini.
RUN apt-get update \
 && apt-get install -y --no-install-recommends msmtp \
 && rm -rf /var/lib/apt/lists/*

COPY dev/config/php/custom.d/dev.ini    /usr/local/etc/php/custom.d/dev.ini
COPY dev/config/php/custom.d/xdebug.ini /usr/local/etc/php/custom.d/xdebug.ini

# HOME і XDG_CONFIG_HOME образу належать root, а exec тут іде під www-data:
# без цього composer щоразу качає все наново («Cannot create cache directory»),
# а config --global і require падають.
ENV COMPOSER_HOME=/var/www/.composer \
    COMPOSER_CACHE_DIR=/var/www/.composer/cache
RUN mkdir -p "${COMPOSER_CACHE_DIR}" && chown -R www-data:www-data /var/www/.composer

# php-fpm скидав привілеї сам: майстер під root, воркери під www-data.
# У FrankenPHP процес один, тож користувача треба задати явно — інакше
# compiled/ і cache/ у bind-mount заповнились би файлами root.
USER www-data

# ─────────────────────────────── prod ───────────────────────────────
FROM base AS prod

COPY dev/config/php/prod/prod.ini /usr/local/etc/php/custom.d/prod.ini
COPY --from=composer:2.10.2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY . /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress \
 && composer clear-cache \
 && mkdir -p compiled cache files backend/files backend/design/compiled Okay/log Okay/xml/compiled \
 && chown -R www-data:www-data vendor compiled cache files backend/files backend/design/compiled Okay/log Okay/xml/compiled

USER www-data
