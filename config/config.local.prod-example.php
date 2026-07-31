;<? exit(); ?>
; Template for the production runtime config. Copy this file to
; config/config.local.prod.php (gitignored — see the root .gitignore), fill in
; real values, then:
;   - chmod 600 it, owned by the deploy user;
;   - never let it enter a build context (already excluded in the repo-root
;     .dockerignore, verified by dev/bin/smoke-prod.sh);
;   - let docker-compose.prod.yml mount it read-only at
;     /var/www/html/config/config.local.php — do not bake it into the image.
;
; OkayCMS reads its DB credentials from this file, not from the environment.
; docker-compose.prod.yml does not use Docker secrets (removed — the
; MariaDB root/app passwords live in env vars, protected only by host file
; permissions, same as everything else). This file is the only place the
; application's own DB password lives, which is why it gets chmod 600 + a
; read-only bind mount instead of shipping in the image.

[database]
db_server = "mariadb"

; Unprivileged account only. Dev deliberately connects as root (see
; config/config.local.php); production must not inherit that — create a
; dedicated MySQL user restricted to MYSQL_DATABASE with ordinary DML/DDL
; grants, nothing instance-wide.
db_user = "okay"
db_password = "CHANGE_ME"
db_name = "okay"
db_driver = mysql
db_prefix = ok_
db_charset = UTF8MB4
db_names = utf8mb4
db_sql_mode = "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"

[php]
; Never true in production: debug_mode also relaxes display_errors (see
; index.php) and would leak stack traces/paths to visitors.
debug_mode = false
