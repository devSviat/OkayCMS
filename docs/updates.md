# Оновлення ядра (self-updater)

Модуль `Okay/Modules/OkayCMS/CoreUpdater` перевіряє релізи форку
(`devSviat/OkayCMS` на GitHub) і вміє накотити новий core-код + core-міграції
без `git`/`composer` на сервері — під типову інсталяцію форку: файли на
диску, `www-data` пише напряму. Що саме входить у пакет релізу й формат
`manifest.json`/`checksums.txt` — `docs/superpowers/specs/2026-08-30-core-self-updater-design.md`,
§5-§7.

**Модуль ніде не встановлюється автоматично** — ані `1DB_changes/okay_clean.sql`
(свіжа інсталяція), ані наявний `ok_modules` (наявна) не містять для нього
рядка. Перше увімкнення — вручну, кнопкою «Встановити» в списку модулів
адмінки, як для будь-якого іншого модуля ([modules/lifecycle.md](modules/lifecycle.md#встановлення)).

На момент цього документа в адмінці ще немає кнопки «Оновити» й немає
CLI-команди — це конвеєр без UI (Plan D). Запустити оновлення можна лише
програмно: резолвнути `UpdateRunner` через DI і викликати `run()`.

## Перевірка оновлень

`UpdateCheckHelper::check()` б'є в GitHub Releases репозиторію
(`devSviat/OkayCMS`, лише реліз-теги форку, не апстрім-теги OkayCMS) і кладе
результат у `Settings` під ключем `core_updater__snapshot`. `check()` без
`force=true` не йде в мережу, якщо снапшот свіжіший за TTL
(`UpdateCheckHelper::TTL`, 21600 с = 6 год); частоту можна перевизначити
ключем `core_updater_check_ttl` у `config/config.local.php`. Запит —
ETag-кондиційний (`If-None-Match`); `304` лише освіжає `checkedAt` і
перераховує `installed`/`updateAvailable` з поточної версії форку, тіла
не питає заново.

Планувальник (`Init::init()`, задача «Check for core updates») ганяє
`check()` щодня о 4:30 — див. [cli.md](cli.md#планувальник) про
`scheduler:run`/`scheduler:list`. `getSnapshot()` читає кеш без мережі — і
теж перераховує `installed`/`updateAvailable` наживо, щоб щойно застосоване
оновлення не показувало застиглу стару картину. Помилка мережі не псує
кеш: старий снапшот лишається, до нього лише додаються `lastError`/
`lastErrorAt`.

## Як іде оновлення

`UpdateRunner::run()` виконує весь конвеєр в одному процесі
(`ignore_user_abort(true)`, `set_time_limit(0)`), під ексклюзивним
`flock`-локом (`files/tmp`, `Symfony\Component\Lock\Store\FlockStore`) —
паралельний запуск кидає виняток одразу. Прогрес пишеться в `Settings`
під ключем `core_updater__run` (`UpdateStatus::SETTING_RUN`) після кожного
кроку — для поллінгу з адмінки, коли вона зʼявиться.

Кроки, у порядку `UpdateStatus::STEPS`:

| Крок | Що робить |
| ---- | --------- |
| `download` | качає `{version}.zip` і `checksums.txt` у `files/tmp/updates/{version}/` (лише з `TRUSTED_ASSET_URL_PREFIX` — `https://github.com/devSviat/OkayCMS/releases/download/`) |
| `verify` | звіряє sha256 архіву з `checksums.txt`, розпаковує, тоді звіряє **кожен** файл з `manifest.json` проти дерева й перевіряє шляхи на вихід за межі кореня (`assertSafePaths`) |
| `preflight` | `assertInstallable` (мінімальний PHP, downgrade guard — пакет мусить бути новіший за встановлену версію); корінь доступний для запису; вільного місця > 3× розміру розпакованого пакета; якщо `composer.lock` пакета відрізняється — composer має бути доступний; якщо `version.json` каже `requiresMigrations` — має бути доступний `mysqldump` |
| `backup` | архівує у `files/backups/` файли з `manifest.json`, що фізично існують зараз (лише їх і перезапише apply); якщо є `.up.sql`-міграції — дампить `mysqldump`-ом лише зачеплені ними таблиці (розпізнані по `__marker` у SQL, той самий регекс, що й `CoreMigrator::prefixTables()`) |
| `maintenance_on` | вмикає прапорець `config/.maintenance` із одноразовим токеном |
| `apply_files` | spool-and-swap: кожен файл із `manifest.json` копіюється поруч (`{файл}.core-update.tmp`) і атомарно `rename()`-иться поверх цілі; нічого не видаляється. Тоді, якщо `composer.lock` пакета відрізняється від поточного — `composer install --no-dev --optimize-autoloader` |
| `migrations` | `CoreMigrator::apply()` накочує `.up.sql` із пакета (трекер `ok_core_migrations`) |
| `cache_clear` | `Design::clearCompiled()` + `opcache_reset()`, якщо розширення завантажене |
| `health_check` | HTTP GET на `/?core_updater_health=1` з токеном у заголовку `X-Core-Updater-Token`; очікує JSON із `forkVersion`, **точно** новою версією |
| `finalize` | знімає `config/.maintenance`, прибирає `files/tmp/updates/{version}/` і старі бекапи (`pruneOldBackups`, лишає 3 найновіші файли — з провалу тут прогін уже не падає, лише пише `finalizeWarning`) |

Health-check відповідає **без DI й без БД** — `index.php` читає
`(new ReflectionClass(\Okay\Core\Config::class))->getDefaultProperties()['forkVersion']`
до підйому контейнера. Це навмисно: `Config::$forkVersion` — літерал у
класі (як і `$version`), тож рефлексія доводить, що autoload після
`apply_files` реально підхопив нові core-файли, не піднімаючи роутер чи
БД під технічними роботами.

`root_url` для health-check у звичайному HTTP-запиті бере
`Request::getDomain()`; для CLI-контексту (`Request::getDomain()` пустий,
`$_SERVER['HTTP_HOST']` немає) — фолбек на ключ `Settings`
`core_updater__root_url` (`UpdateRunner::SETTING_ROOT_URL`). Адмінської
форми для цього ключа поки немає (Plan D) — ставиться напряму в
`Settings`; без жодного з двох джерел `run()` кидає явний виняток замість
тихого запиту на `http://`.

## Відновлення після збою

Провал на будь-якому кроці ловиться в `UpdateRunner::handleFailure()`,
стан переходить у `failed` з текстом помилки в `error`.

**Провал до `apply_files`** (`download`/`verify`/`preflight`) — файли й
БД ще не чіпались, rollback не потрібен. Якщо `maintenance_on` устиг
спрацювати — прапорець знімається одразу автоматично
(`maintenanceDisabledAfterFailure` у стані). Найпростіший ре-ран: просто
викликати `UpdateRunner::run()` ще раз — він завжди стартує з нуля
(`UpdateStatus::fresh()`), не продовжує перерваний прогін.

**Провал від `apply_files` до `health_check` включно**
(`UpdateRunner::needsRollback()`) — файли вже могли змінитись, конвеєр
іде в rollback-гілку:

1. `UpdateApplier::restoreFiles()` відновлює файли з бекап-архіву
   (`files/backups/pre-update-{from}-to-{to}-{ts}.zip`) тим самим
   spool-and-swap назад.
2. Повторний health-check — тепер очікує **стару** версію
   (`fromVersion`).
3. Якщо старий код підтвердив себе живим — `config/.maintenance`
   знімається, стан `rolled_back`. Якщо ні — прапорець лишається
   увімкненим, стан отримує `requiresManualIntervention: true` й
   `manualInterventionReason`: сайт свідомо лишається закритим, поки
   хтось не розбереться руками.

**Core-міграції НЕ відкочуються** — DDL rollback ненадійний. Rollback-стан
несе `rolledBackMigrations` (імена застосованих файлів, з
`CoreMigrationException::appliedNames`) — це підказка, що саме
відновлювати вручну з дампу таблиць, а не автоматична дія.

### Ручне відновлення

- Бекапи файлів — `files/backups/pre-update-{from}-to-{to}-{ts}.zip`.
  Якщо в апдейті не було жодного файлу для перезапису, всередині лежить
  лише технічний маркер `.empty` (щоб `ZipArchive` реально записав архів
  на диск) — `restoreFiles()` його пропускає, розпаковувати вручну
  нема сенсу.
- Дампи торкнутих таблиць (лише коли `.up.sql`-міграції щось зачепили) —
  `files/backups/pre-update-{from}-to-{to}-{ts}.sql`, звичайний вивід
  `mysqldump --single-transaction --no-tablespaces`; заливається як
  завжди (`mysql < файл.sql`).
- `finalize` тримає лише **3 найновіших файли в `files/backups/` разом**
  (zip-и й sql-дампи — один спільний пул за mtime, не по три кожного
  типу) — знімайте копію деінде, якщо потрібно тримати довше трьох
  прогонів.
- **Перерваний прогін** (процес убили, OOM, — `flock` звільняється
  автоматично разом із процесом, тож новий `run()` спокійно стартує) не
  лишає явного «failed»: `step` так і завис на останньому записаному
  кроці. `UpdateStatus::isStale($state, time())` — `true`, якщо крок не
  термінальний (`done`/`failed`/`rolled_back`) і `updatedAt` не
  оновлювався понад `STALE_AFTER_SECONDS` (600 с) — це ознака для UI/адміна,
  що прогін мертвий і безпечно запускати новий, а не намагатись
  «дочекатись» цього.
- Зняти технічні роботи вручну — видалити `config/.maintenance`.
  **Лише після підтвердження, що прогін мертвий**: стан у `Settings`-ключі
  `core_updater__run` не в термінальному кроці **і** `updatedAt` старший
  за 10 хв (логіка `UpdateStatus::isStale()` вище), або процес гарантовано
  вбитий. `index.php` перевіряє лише `file_exists()`, більше ніякого
  стану немає — видалення прапорця під час **живого** `apply_files`/
  `migrations` відкриє вітрину над напівзастосованим ядром, це гірше за
  сторінку технічних робіт.
- Обійти технічні роботи, не знімаючи прапорець (щоб довести перевірку
  до кінця) — заголовок `X-Core-Updater-Token` або `?core_updater_token=`
  з токеном, який зберігається в самому `config/.maintenance` (JSON
  `{"startedAt": …, "token": "…"}`). Битий/нечитний JSON трактується як
  **закритий** доступ (fail-closed) — жодного токена не пройде.

## Обмеження діалекту core-міграцій

Core-міграції (`.up.sql` у пакеті релізу) парсяться спрощеним
`CoreMigrator::splitSqlFile()`, не повним SQL-парсером — обмеження
формату й таблиця-маркер `__name` описані в
[`release-migrations/README.md`](../release-migrations/README.md).

## Нотатки з експлуатації

- **`module.json`-ключ `"Okay"` — не гейт, а бейдж сумісності.**
  `ModuleParamsDTO::fromArray()` кладе його в `okayVersion` лише для
  відображення (`backend/design/html/module_list.tpl`) — install/update
  модуля цей ключ ніяк не перевіряє й ним не блокується.
- Root URL для health-check у CLI-контексті — `Settings`-ключ
  `core_updater__root_url`, без адмінської форми (Plan D, див. вище).
