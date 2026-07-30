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
