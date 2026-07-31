#!/bin/sh
# Виконується на кожному `docker compose up`, після того як mariadb стане
# healthy, і завершується. Штатний entrypoint образу лишається незайманим,
# тож оновлення образу не може зламати старт.
set -eu

run_sql() {
    mariadb --host=mariadb --user=root --password="${MYSQL_ROOT_PASSWORD}" \
            --database="${MYSQL_DATABASE}"
}

echo "db-init: resetting the 'admin' manager to the default password"
# login не має UNIQUE-обмеження в ok_managers, тож ON DUPLICATE KEY UPDATE
# тут не спрацює — INSERT ... WHERE NOT EXISTS створює рядок, якщо його
# немає, а окремий UPDATE завжди приводить пароль до дефолтного.
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
    # Okay/Core/Notify.php обирає транспорт за налаштуванням use_smtp: якщо
    # воно вимкнене — лист іде через mail() (msmtp -> Mailpit); ці рядки
    # роблять безпечною і другу гілку, тож увімкнення use_smtp в адмінці не
    # дає листам піти на реальний сервер. ok_settings.param — UNIQUE, а в
    # чистому okay_clean.sql рядків smtp ще немає, тому INSERT ... ON
    # DUPLICATE KEY UPDATE, а не звичайний UPDATE.
    run_sql <<'SQL'
INSERT INTO ok_settings (param, value) VALUES ('smtp_server', 'mailpit')
ON DUPLICATE KEY UPDATE value = VALUES(value);
INSERT INTO ok_settings (param, value) VALUES ('smtp_port', '1025')
ON DUPLICATE KEY UPDATE value = VALUES(value);
SQL
fi

echo "db-init: done"
