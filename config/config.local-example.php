;<? exit(); ?>
; Шаблон локального конфігу. Скопіюйте його в config/config.local.php
; (файл у .gitignore і не потрапляє ані в git, ані в образ):
;
;   cp config/config.local-example.php config/config.local.php
;
; Без нього Okay\Core\Config бере значення з config/config.php, де вказано
; db_server = localhost і db_name = okaycms-git. Усередині контейнера
; "localhost" — це не MariaDB, тож магазин не достукається до бази.
;
; Значення нижче збігаються з dev/.env-example. Якщо змінюєте
; MYSQL_ROOT_PASSWORD чи MYSQL_DATABASE у dev/.env — змініть і тут: цей файл
; із .env не генерується, їх тримають у відповідності вручну.
;
; ── Для розробки ────────────────────────────────────────────────────────
; debug_mode тут вимкнений, як і на проді. Щоб побачити помилки замість
; порожньої сторінки, поставте debug_mode = true у своїй копії — це вмикає
; display_errors (див. index.php).
;
; ── Для продакшну ───────────────────────────────────────────────────────
; Той самий файл, два інші значення. docker-compose.prod.yml монтує його
; ззовні лише для читання й ніколи не запікає в образ, тож на прод-хості
; створіть config/config.local.php з такими змінами:
;
;   db_user      окремий обліковий запис MySQL, обмежений однією базою.
;                Тут у dev стоїть root — прод цього успадковувати не має.
;   db_password  реальний пароль того облікового запису.
;
; debug_mode і debug_bar на проді лишаються false: перший показує відвідувачам
; трасування стека та шляхи на сервері, другий — ще й SQL-запити.
;
; Це єдине місце, де живе пароль застосунку до бази — з оточення OkayCMS його
; не читає. Тому на прод-хості: chmod 600 і власник — користувач деплою.
;
; ── Панель відладки ─────────────────────────────────────────────────────
; debug_bar вмикає phpdebugbar на сторінках вітрини: таймлайн, SQL-запити,
; значення конфіга і хвіст системного лога. Працює лише в парі з
; debug_mode = true — при debug_mode = false панель не піднімається навіть
; із debug_bar = true (index.php перевіряє обидва).
;
; Секції у файлі — суто для читабельності: Config зчитує ini без секцій і
; зливає всі ключі в один простір імен.

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
; Друкувати помилки на сторінці. Вимкнений — вони йдуть у Okay/log/
debug_mode = false

[design]
; Панель відладки. Потрібен ще й debug_mode = true
debug_bar = false
