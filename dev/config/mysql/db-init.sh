#!/bin/sh
# Виконується на кожному `docker compose up`, після того як mariadb стане
# healthy, і завершується. Штатний entrypoint образу лишається незайманим,
# тож оновлення образу не може зламати старт.
set -eu

run_sql() {
    mariadb --host=mariadb --user=root --password="${MYSQL_ROOT_PASSWORD}" \
            --database="${MYSQL_DATABASE}" "$@"
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

echo "db-init: applying fork core migrations"
# Базу піднімає штатний entrypoint образу з 1DB_changes/okay_clean.sql, а той
# дамп — стоковий 4.5.2: схемні зміни форку живуть окремими міграціями. Тут
# їх немає чим застосувати штатно (у цьому контейнері немає PHP), тому те
# саме робиться руками — маркер `__` замінюється префіксом, як це робить
# CoreMigrator::prefixTables(). Трекер спільний, тож пізніший
# `php ok core:migrate` ці ж міграції пропустить.
run_sql <<'SQL'
CREATE TABLE IF NOT EXISTS ok_core_migrations (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    applied_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL

for migration in /fork-migrations/*.up.sql; do
    [ -f "$migration" ] || continue
    name="$(basename "$migration")"

    applied="$(printf 'SELECT COUNT(*) FROM ok_core_migrations WHERE name = "%s";\n' "$name" \
        | run_sql --skip-column-names)"
    [ "$applied" = "0" ] || continue

    echo "db-init: applying $name"
    sed 's/`__/`ok_/g' "$migration" | run_sql
    printf 'INSERT INTO ok_core_migrations (name, applied_at) VALUES ("%s", NOW());\n' "$name" | run_sql
done

echo "db-init: done"
