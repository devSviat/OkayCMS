;<? exit(); ?>
; Template for the local dev runtime config. Copy this file to
; config/config.local.php (gitignored — see the root .gitignore):
;
;   cp config/config.local-example.php config/config.local.php
;
; Without it, Okay\Core\Config falls back to config/config.php, which points
; at db_server = localhost / db_name = okaycms-git — inside the dev container
; "localhost" is not MariaDB, so the storefront cannot reach the database.
;
; The values below match dev/.env-example (db_server = the mariadb service
; name, db_user/db_password = root/MYSQL_ROOT_PASSWORD, db_name =
; MYSQL_DATABASE). If you change MYSQL_ROOT_PASSWORD or MYSQL_DATABASE in
; dev/.env, mirror the same change here — this file is not generated from
; .env, the two are just kept in sync by convention.

[database]
db_server = "mariadb"
db_user = "root"
db_password = "root"
db_name = "okay"
db_driver = mysql
db_prefix = ok_
db_charset = UTF8MB4
db_names = utf8mb4
db_sql_mode = "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"

[php]
debug_mode = true
