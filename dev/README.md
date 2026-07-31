# Docker-оточення OkayCMS

Оточення: Nginx, php-fpm (`php85`), MariaDB, окремий контейнер `scheduler`
(`./ok scheduler:run` за розкладом), а в dev ще й Mailpit для перехоплення
пошти. `docker-compose.yml` — база, спільна для dev і prod.
`docker-compose.override.yml` підвантажується автоматично і додає все
dev-специфічне: bind-mount коду, опубліковані порти, Xdebug, сідовий дамп бази.

## Швидкий старт

```bash
cd dev
cp .env-example .env

# APP_UID/APP_GID мають збігатися з вашим хостовим користувачем, інакше файли,
# які контейнер пише в прив'язану теку проєкту, вийдуть недоступними для
# запису з хоста.
sed -i "s/^APP_UID=.*/APP_UID=$(id -u)/" .env
sed -i "s/^APP_GID=.*/APP_GID=$(id -g)/" .env

docker compose up -d
./bin/smoke.sh
```

`smoke.sh` можна запускати з будь-якої теки (`dev/bin/smoke.sh` з кореня
репозиторію працює так само). Він дочекається, поки всі контейнери стануть
`healthy` і `db-init` завершиться, і прожене перевірки: PHP-конфіг та
розширення, базу на named volume, мережеву ізоляцію mariadb, живий scheduler,
логи nginx і обидва поштові шляхи.

Якщо на машині одночасно кілька копій цього проєкту — задайте кожній свій
`APP_NAME` в `.env` (з нього Compose бере ім'я проєкту і назви мереж:
`<APP_NAME>_frontend`, `<APP_NAME>_backend`).

## Щоденні команди

Виконуються з теки `dev/`.

| Дія | Команда |
| --- | --- |
| Підняти оточення | `docker compose up -d` |
| Зупинити (дані лишаються) | `docker compose down` |
| Логи одного сервісу | `docker compose logs nginx` / `php85` / `scheduler` |
| Логи всіх сервісів у реальному часі | `docker compose logs -f` |
| Перевірити, що все працює | `./bin/smoke.sh` |
| PHPUnit | `docker compose exec php85 php vendor/bin/phpunit` |
| CLI (`./ok`) | `docker compose exec php85 php ok <команда>`, напр. `php ok scheduler:list` |

Логи всіх сервісів (включно з access/error nginx) ідуть у stdout/stderr
контейнера — окремої теки `dev/logs` більше немає.

## `docker compose down -v` знищує базу даних

MariaDB живе на named volume (`db_data`). Просте `docker compose down` дані не
чіпає — саме ним і варто користуватись. `down -v` видаляє volume разом з
даними без попередження й без відновлення; запускайте його лише свідомо, коли
дійсно треба почати з чистої бази (наступний `up -d` знову накотить дамп
`1DB_changes/okay_clean.sql` і перестворить `admin`).

Раніше дані лежали поза Docker-керованими volume, і `down -v` їх не чіпав —
тепер навпаки, тож стара звичка тут небезпечна.

## Пошта

Усі листи з локального оточення перехоплює Mailpit, жоден не йде далі:
`http://127.0.0.1:${MAILPIT_PORT}` (за замовчуванням 8025).

**Налаштування → SMTP в адмінці міняти не треба.** У поставленій базі
`use_smtp = 0`, тож `Okay/Core/Notify.php` шле листи через `mail()`, а
`sendmail_path` в dev-образі веде на `msmtp`, який безумовно перенаправляє все
на `mailpit:1025`. Рядки `smtp_server=mailpit` / `smtp_port=1025`, які
одноразовий контейнер `db-init` записує в `ok_settings` на кожному `up`, —
лише страховка на випадок, якщо хтось сам увімкне SMTP в адмінці: навіть тоді
лист не покине машину. Щоб db-init цього не робив (наприклад, свідомо
тестуєте реальний SMTP), виставте в `.env`:

```
DB_INIT_SMTP=0
```

`TEST_INTERNAL_EMAIL` теж підтримується, але Mailpit — гарантія сильніша за
редірект, тож окремо його налаштовувати для локальної розробки не обов'язково.

## Xdebug

Режим перемикається змінною `XDEBUG_MODE` в `.env` (`off`, `debug`,
`develop`, `profile` — через кому для кількох) і діє одразу на
`docker compose up -d`, без перезбирання образу:

```bash
sed -i "s/^XDEBUG_MODE=.*/XDEBUG_MODE=debug/" .env
docker compose up -d
```

Налаштування в PhpStorm:

1. **Settings → PHP → Servers** — додати сервер. Host і Port — як `VIRTUAL_HOST`
   і `HTTP_PORT` у `.env`. Use path mappings — увімкнути, корінь проєкту на
   хості змапити на `/var/www/html` у контейнері.
2. **Settings → PHP → Debug → Xdebug** — Debug port: `9001`. Це порт, заданий
   в образі (`dev/config/php/custom.d/xdebug.ini`), а не типовий для Xdebug 3
   `9003`; зі значенням `9003` PhpStorm просто ніколи не дочекається
   з'єднання.
3. **Run → Edit Configurations** — нова конфігурація `PHP Remote Debug`.
   Server — сервер з кроку 1, IDE key — `PHPSTORM`. Назву конфігурації
   зручно робити `host:port`.
4. Увімкнути "Start Listening for PHP Debug Connections" і відкрити сторінку
   сайту в браузері.

## Порти й доступ до бази

`HTTP_PORT` (nginx) і `MAILPIT_PORT` (Mailpit) за замовчуванням прив'язані
лише до `127.0.0.1` — з інших машин у мережі оточення недоступне. Щоб
свідомо відкрити їх назовні:

```
BIND_IP=0.0.0.0
```

і `docker compose up -d` — перестворить лише сервіси, що публікують порти.

`MYSQL_PORT` працює так само: база доступна з хоста на
`${BIND_IP:-127.0.0.1}:${MYSQL_PORT}`, тож DBeaver, вбудований Database tool
PHPStorm чи звичайний клієнт підключаються напряму:

```bash
mariadb --skip-ssl -h 127.0.0.1 -P "$MYSQL_PORT" -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
```

(`--skip-ssl` потрібен новішим клієнтам mariadb — образ бази TLS не піднімає;
без прапорця свіжий клієнт відмовляється підключатись.)

Це виняток лише для dev. У базовому файлі `mariadb` сидить у мережі `backend`
(`internal: true`, без маршруту з хоста) — так лишається і в проді.
`docker-compose.override.yml` (тільки dev) додатково підключає `mariadb` до
`frontend`, інакше опублікований порт був би no-op: Docker не публікує порт
контейнера, чия єдина мережа internal.

## Scheduler

Контейнер `scheduler` — `supercronic` під `tini`, щохвилини виконує
`php ok scheduler:run`; сама команда вирішує, які завдання дійсно due за їх
cron-правилом. Перевірити, що воркер живий:

```bash
docker compose logs scheduler
docker compose exec scheduler pgrep -fa supercronic
```

## Ресайз зображень

Коли на локальний сервер береться production-база, оригінали зображень
товарів/категорій зазвичай відсутні. Вкажіть у `.env` `PRODUCTION_DOMAIN` —
домен, з якого взято базу, — і нарізки підвантажаться звідти по
http. Працює лише для розмірів ресайзу, що збігаються з production. В
`originals` на локальному сервері нічого не додається, лише в `resized`.

## Продакшн

Оверлей `docker-compose.prod.yml` ніколи не підвантажується сам — додається
явно поверх бази. Портів не публікує, монтує `config/config.local.prod.php`
в `php85`/`scheduler` read-only. Дві форми:

**1. За PaaS/проксі, який сам приєднується до контейнера** (Dokploy, Coolify,
власний Traefik) — проксі ходить до `nginx` мережею `frontend`, портів не
треба:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

**2. Standalone, без проксі перед оточенням** — третій файл публікує порт
nginx і більше нічого; порядок важливий (`prod` перед `standalone`, щоб його
`ports:` ліг поверх того самого сервісу):

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml \
               -f docker-compose.standalone.yml up -d
```

Не додавайте `standalone` поверх форми (1) — зайва публікація порту без
користі.

У standalone `BIND_IP` за замовчуванням `0.0.0.0` (не `127.0.0.1`, як у dev):
хост сам приймає трафік ззовні. Дефолт спрацьовує, лише якщо змінна не
задана — dev-івський `.env` поруч виставляє `BIND_IP=127.0.0.1` явно й
перекриє його, тож на прод-хості або не тримайте dev `.env`, або задайте
`BIND_IP` свідомо. Перевірити, що вийшло:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml \
               -f docker-compose.standalone.yml config | grep -A2 published
```

**Перед першим запуском:**

- Скопіювати `config/config.local.prod-example.php` в
  `config/config.local.prod.php`, прописати `db_password` для окремого
  непривілейованого MySQL-користувача (dev ходить під root — прод не має),
  `debug_mode = false`, виставити `chmod 600` і власника — деплой-юзера.
- Задати `MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `MYSQL_USER`,
  `MYSQL_DATABASE` — у `.env` на standalone-хості або в панелі змінних PaaS.
- За замовчуванням образ **збирається локально** з поточного коду
  (`APP_PULL_POLICY=build`), і тег `APP_IMAGE_TAG` — просто локальна мітка.
  Нічого налаштовувати не треба.

  Ці змінні знадобляться, лише якщо ви збираєте образи в CI, пушите їх у
  реєстр і деплоїте готовими: тоді задайте `APP_IMAGE` (шлях у реєстрі),
  `APP_IMAGE_TAG` (конкретний незмінний тег, напр. `v1.4.2`) і
  `APP_PULL_POLICY=always`. Фіксований тег потрібен, щоб було видно, яка
  версія коду виконується, і щоб було куди відкотитись.

  Це **тег вашого власного образу**, зібраного з цього форку, а не версія
  OkayCMS. Знизити його до апстрімного OkayCMS неможливо й не потрібно:
  апстрім розрахований на старіший PHP, а це оточення — на 8.5.

**Ініціалізація бази.** У проді немає ні `db-init`, ні сідового дампа —
свідомо, щоб `up` ніколи не міг затерти живі дані. База піднімається
порожньою:

- *Новий магазин* — розгорнути чисту схему і одразу змінити пароль `admin`
  через адмінку:

  ```bash
  docker compose -f docker-compose.yml -f docker-compose.prod.yml \
    exec -T php85 php ok database:deploy
  ```

- *Наявний магазин* — відновити свій бекап у контейнер `mariadb` замість
  `database:deploy`. Міграції модулів після оновлення коду — окремо, див.
  `docs/modules/table_migrate.md`.

## Обмеження

- **Немає TLS, немає бекапів.** Перед оточенням мається на увазі проксі, що
  термінує TLS. Резервне копіювання `db_data` і `files/` — відповідальність
  оператора.
- **Лише один вузол.** `Okay\Core\Config` рахує `$salt` із `stat()` файлу
  `config/config.php` (inode/mtime). Кожен ребілд образу — нова сіль, тобто
  всі видані токени відновлення пароля адміністратора стають недійсними, а
  дві репліки одночасно порахують різну сіль і не звірять токен одна одної.
  Горизонтальне масштабування не підтримується.
- **Паролі бази захищені лише правами доступу на хості** — `chmod 600` на
  `.env` і `config/config.local.prod.php`, власник — деплой-юзер. Змінні
  оточення видно у `docker inspect`, а пароль застосунку до бази однаково
  лежить відкритим текстом у `config.local.prod.php`, бо звідти OkayCMS його
  й читає.
- **Редагування теми чи `robots.txt` через адмінку тут не працює** — файли
  закомічені в git, потрапляють в образ через `COPY .` і належать `root`,
  а php-fpm у prod виконується під `www-data`. Це свідоме рішення (тема
  котиться з репозиторію), але поведінка при спробі зберегти різна:
  `RobotsAdmin` перевіряє `is_writable()` і показує помилку, а
  `CssConfig::updateCssVariables()` цієї перевірки не робить — запис мовчки
  провалюється, а адмінка все одно каже "збережено". Це вада `CssConfig`,
  не документу; виправляється окремо, в коді застосунку.
