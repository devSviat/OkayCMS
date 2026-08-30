# CoreUpdater: Apply Pipeline (Plan C2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The apply side of the self-updater: download → verify → pre-flight
→ backup → maintenance → apply files → core migrations → cache/OPcache →
health check → finalize, with rollback on failure — headless (invocable
runner + status snapshot), UI comes in Plan D.

**Architecture:** All new classes live in the CoreUpdater module
(`Okay/Modules/OkayCMS/CoreUpdater/Helpers/Update*`), except one deliberate
core change: the maintenance-mode gate in `index.php` (checked between
autoload and sessions — before DI/DB). Same testing split as C1: every
decision that can be pure IS pure and fixture-tested (checksum parsing,
per-file verification, backup intersection, spool-and-swap on temp trees,
SQL table-name extraction, state transitions); network/DB/process glue is
thin and Plan E live-verifies it with deliberate-failure controls.

**Tech Stack:** PHP 8.4/8.5, cURL, ZipArchive, symfony/lock (FlockStore —
house precedent: `Okay/Core/Scheduler/Scheduler.php:8-9`), symfony/process
(in require), existing module framework.

**Spec:** `docs/superpowers/specs/2026-08-30-core-self-updater-design.md`
§8 (кроки 3-13), §9, §11. Consumes Plan C1's snapshot (`latest.meta.minPhp`
pre-download gate, origin-pinned asset URLs) and Plan B's
`CoreMigrator::apply()`/`CoreMigrationException`.

## Global Constraints

- PHP `^8.4`; Ukrainian why-comments; no Russian; no TODO/placeholders.
- CI has no DB/network/composer/mysqldump: tests are pure + fixture trees in
  temp dirs only.
- **Download path cURL MUST set `CURLOPT_SSL_VERIFYPEER => true` and
  `CURLOPT_SSL_VERIFYHOST => 2` explicitly** (ledgered C1 ruling — this
  path fetches executable code; env-level default overrides must not apply).
- **Re-validate stored asset URLs at consumption**: before download, assert
  `str_starts_with($url, ReleaseFeed::TRUSTED_ASSET_URL_PREFIX)` again
  (defense-in-depth; the constant exists from C1).
- Fail-safe ordering: NOTHING under the site root is modified before the
  backup step completes; any failure before "apply files" starts must leave
  the installation byte-identical (temp dirs under `files/tmp/updates/` are
  the only writes).
- The applier trusts ONLY `manifest.json`'s path list (spec §5): a path in
  the zip but not in the manifest is never copied; a manifest path
  escaping the root (`..`, absolute, backslash) aborts verification.
- Downgrade guard (spec §11): refuse if package `version.json.forkVersion`
  is not `>` installed `Config::$forkVersion` (explicit-downgrade UX is out
  of scope until Plan D; the runner API takes no downgrade flag yet).
- Branch: `feat/coreupdater-apply` from `origin/dev` (after C1's PR #216
  merges).

---

## File Structure

- `index.php` — modify: maintenance gate (the plan's only core-file edit).
- `Okay/Modules/OkayCMS/CoreUpdater/Helpers/MaintenanceMode.php` — flag
  file lifecycle + token bypass (pure logic on injected paths).
- `.../Helpers/UpdateStatus.php` — Settings-backed step/progress snapshot
  for polling; pure state-transition core.
- `.../Helpers/UpdatePackage.php` — pure: checksums.txt parsing, zip-listing
  sanity, per-file manifest verification against an extracted dir, path
  safety (`..`/absolute), version/downgrade/minPhp gates given
  version.json content.
- `.../Helpers/UpdateDownloader.php` — thin: cURL download (SSL flags,
  origin re-pin), unzip via ZipArchive.
- `.../Helpers/UpdateBackup.php` — pure core: intersection of current tree
  with manifest paths → list to archive; SQL table-name extraction from
  migration files (reusing the `__name` marker convention). Thin: zip
  creation, mysqldump via symfony/process.
- `.../Helpers/UpdateApplier.php` — pure core: spool-and-swap plan
  (copy/replace/delete-nothing semantics) executed against injected
  root; works on fixture trees in tests. Thin: `composer install` via
  symfony/process, `Design::clearCompiled()`, `opcache_reset()`.
- `.../Helpers/UpdateRunner.php` — orchestrator: lock (FlockStore на
  `files/tmp/`), step sequence, UpdateStatus updates, health check,
  finalize/rollback. Thin by nature; its step-ORDER table is a pure
  constant tested for completeness against spec §8.
- Tests under `tests/Modules/OkayCMS/CoreUpdater/` + fixtures.
- `docs/updates.md` — NEW (spec §15 step 8 pulled in here for the
  apply-side content that now exists): операційний опис процедури,
  відновлення після збою, обмеження діалекту міграцій (посилання), нотатки
  з C1-леджера (ручне перше встановлення модуля; семантика "Okay" у
  module.json).

---

### Task 1: `MaintenanceMode` + gate в `index.php`

**Files:**
- Create: `Okay/Modules/OkayCMS/CoreUpdater/Helpers/MaintenanceMode.php`
- Modify: `index.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/MaintenanceModeTest.php`

**Interfaces:**
- `MaintenanceMode` — ВСІ методи статичні й працюють з переданим шляхом
  прапорця (тестовані на temp-файлах):
  - `flagPath(string $rootDir): string` → `{$root}/config/.maintenance`.
  - `enable(string $flagPath): string` — пише JSON
    `{"startedAt": time(), "token": bin2hex(random_bytes(16))}`, повертає
    token.
  - `disable(string $flagPath): void` — unlink (відсутність файла — не
    помилка).
  - `isActive(string $flagPath): bool`.
  - `allowsRequest(string $flagPath, ?string $providedToken): bool` — true
    якщо прапорця нема АБО токен збігається (hash_equals). Битий JSON у
    прапорці = active без токен-обходу (fail-closed).
  - `renderPage(): string` — статичний HTML 503-сторінки (укр., без
    залежностей) — повертає рядок, щоб бути тестованим.
- Gate в `index.php`: одразу ПІСЛЯ `require vendor/autoload.php` і ДО
  `SessionNames::*` (рядки ~16-21 — до DI і БД):
  ```php
  // Оновлення ядра: вітрина закрита, health-check проходить за токеном
  // з прапорця (див. CoreUpdater/MaintenanceMode).
  $maintenanceFlag = __DIR__ . '/config/.maintenance';
  if (!\Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode::allowsRequest(
      $maintenanceFlag,
      $_SERVER['HTTP_X_CORE_UPDATER_TOKEN'] ?? ($_GET['core_updater_token'] ?? null)
  )) {
      http_response_code(503);
      header('Retry-After: 120');
      echo \Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode::renderPage();
      exit;
  }
  ```
  `backend/index.php` СВІДОМО без gate — адмінка лишається живою, щоб
  бачити прогрес/статус (задокументувати коментарем чому саме).
  Клас модуля доступний у index.php через composer PSR-4 autoload
  (`Okay\` → `Okay/`) — модуль не мусить бути "встановленим" для
  автозавантаження класу; якщо файл відсутній (стара інсталяція до
  оновлення) — `class_exists` guard перед викликом, gate пропускає.
- Tests: enable/disable/isActive на temp-шляху; allowsRequest без
  прапорця/з токеном/з чужим токеном/з битим JSON; renderPage містить 503-
  сумісний текст і не містить PHP-помилок; flagPath склеює шлях коректно.

**Кроки:** TDD (тести → fail → impl) → gate в index.php (без тесту —
smoke в CI його виконує на кожному запиті!) → full suite + phpstan →
commit `feat(coreupdater): maintenance mode з токен-обходом для health-check`.

УВАГА для імплементера: `tests/Security/PublicSurfaceTest.php` може
реагувати на новий шлях `config/.maintenance`? Ні — файл створюється в
runtime, не в репозиторії; але `docs/UPGRADE-security.md`-конвенція
вимагає, щоб nginx/htaccess не віддавали `config/*` — це вже так. Нічого
не робити, лише не зламати test.

---

### Task 2: `UpdateStatus`

**Files:**
- Create: `.../Helpers/UpdateStatus.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/UpdateStatusTest.php`

**Interfaces:**
- Кроки — константа-послідовність (public const STEPS) рівно зі спека §8:
  `download, verify, preflight, backup, maintenance_on, apply_files,
  migrations, cache_clear, health_check, finalize` (+ термінальні стани
  `done`, `failed`, `rolled_back`).
- Pure core (тестований): `UpdateStatus::advance(array $state, string
  $step, array $extra = []): array` — валідує, що перехід іде вперед по
  STEPS (назад/стрибок через невідомий крок → LogicException), мержить
  `extra` (напр. progress лічильники), ставить `updatedAt`.
  `UpdateStatus::fail(array $state, string $error): array`,
  `::rolledBack(array $state, array $appliedMigrations): array`,
  `::fresh(string $fromVersion, string $toVersion): array`.
- Glue: `__construct(Settings)` + `save(array $state)` / `load(): ?array`
  під ключем `core_updater__run` (той самий auto-serialize патерн).
- Виявлення перерваного апдейту (спек §11): `isStale(array $state, int
  $nowTs): bool` — не термінальний стан і `updatedAt` старший за 10 хв.
- Tests: повний прохід по STEPS; заборона регресу; fail з будь-якого
  кроку; isStale свіжий/протухлий/термінальний.

Commit: `feat(coreupdater): статус прогону оновлення для поллінгу`.

---

### Task 3: `UpdatePackage` — верифікація пакета (pure)

**Files:**
- Create: `.../Helpers/UpdatePackage.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/UpdatePackageTest.php` +
  fixtures (мінізбірка: `manifest.json` на 3 файли, `version.json`,
  `checksums.txt`, дерево `payload/` — і навмисно біті варіанти)

**Interfaces (усі pure, шляхи інжектяться):**
- `parseChecksums(string $checksumsTxt): array` — `filename => sha256`
  (формат `hash  name`, як пише PackageBuilder).
- `verifyArchiveHash(string $zipPath, array $checksums): void` — throws
  RuntimeException з очікуваним/фактичним при розбіжності.
- `verifyExtractedFiles(string $extractedDir, array $manifestFiles): array`
  — для КОЖНОГО файла з manifest: існує і sha256 збігається; повертає
  перелік перевірених; будь-яка розбіжність/відсутність → RuntimeException
  (ніяких часткових "ок").
- `assertSafePaths(array $manifestFiles): void` — жоден шлях не
  абсолютний, без `..`, без `\` (спек §5 defense-in-depth) → RuntimeException.
- `readVersionMeta(string $extractedDir): array` — version.json пакета:
  forkVersion/upstreamBase/minPhp/requiresMigrations (типізовано, як
  parseVersionMeta у C1 — можна перевикористати ReleaseFeed::parseVersionMeta).
- `assertInstallable(array $versionMeta, string $installedVersion, string
  $phpVersion): void` — minPhp gate (version_compare) і downgrade guard
  (forkVersion мусить бути строго новішим) → RuntimeException з людським
  повідомленням укр.
- Tests: кожен метод — happy + кожен вид збою (битий hash, відсутній файл,
  зайвий у дереві але не в manifest = ігнорується, `..` у шляху, minPhp
  вищий за поточний, рівна/нижча версія).

Commit: `feat(coreupdater): верифікація пакета — checksums, manifest, гейти`.

---

### Task 4: `UpdateDownloader` (thin) 

**Files:**
- Create: `.../Helpers/UpdateDownloader.php`
- Modify: `.../Init/services.php`

**Interfaces:**
- `__construct(Config $config)` (для root_dir).
- `download(array $assets, string $version): array` — паранойя-перевірка
  префікса URL (`ReleaseFeed::TRUSTED_ASSET_URL_PREFIX`) → cURL із
  ЯВНИМИ `CURLOPT_SSL_VERIFYPEER=true`, `SSL_VERIFYHOST=2`,
  `FOLLOWLOCATION=true` (redirect на objects.githubusercontent.com
  легітимний — але `CURLOPT_REDIR_PROTOCOLS` лише https), таймаути
  завантаження більші (CONNECTTIMEOUT 5, TIMEOUT 300), запис через
  `CURLOPT_FILE` у `files/tmp/updates/{version}/` (mkdir recursive).
  Качає zip + checksums.txt. Повертає локальні шляхи.
- `extract(string $zipPath, string $targetDir): void` — ZipArchive::open
  з перевіркою `=== true` (прецедент PackageBuilder після фіксу),
  extractTo, close-перевірка. Прецедент у кодовій базі:
  `backend/Helpers/BackendModulesHelper.php:145-153`.
- НЕ тестується юнітами (мережа) — Plan E, включно з контролем "підмінений
  checksums.txt мусить зупинити" (це вже Task 3 логіка, але контур цілком).

Commit: `feat(coreupdater): завантаження і розпакування пакета з жорстким TLS`.

---

### Task 5: `UpdateBackup`

**Files:**
- Create: `.../Helpers/UpdateBackup.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/UpdateBackupTest.php` (pure
  частини на fixture-деревах у temp)
- Modify: `.../Init/services.php`

**Interfaces:**
- Pure (тестовано): `collectBackupList(string $rootDir, array
  $manifestFiles): array` — файли з manifest, які ІСНУЮТЬ зараз (їх
  перезапишуть) → відносні шляхи. `extractTouchedTables(array
  $migrationSqlContents, string $prefix): array` — імена таблиць із
  `__name`-маркерів у CREATE/ALTER/INSERT/UPDATE/DROP/RENAME (регекс по
  тому ж патерну, що CoreMigrator::prefixTables), уже з префіксом;
  унікальні.
- Thin: `createFilesBackup(string $rootDir, array $relativePaths, string
  $backupZipPath): void` (ZipArchive, перевірки open/close);
  `dumpTables(array $tables, string $outFile): void` — mysqldump через
  symfony/process з кредами з Config (db_server/user/password/name),
  `--single-transaction --no-tablespaces`; біндити пароль через env
  `MYSQL_PWD` (не в argv — видно в ps).
- `isMysqldumpAvailable(): bool` (Process 'mysqldump --version').
- Політика (ledger-рулінг, задокументувати в коді коротко): якщо
  `requiresMigrations` і mysqldump недоступний → RuntimeException ДО
  будь-яких змін (fail-safe, спек §8.6 вимагає дамп торкнутих таблиць).
- Ротація: `pruneOldBackups(string $backupsDir, int $keep = 3): array`
  (pure-ish, тестовано на temp-дереві; спек §9).
- Tests: collectBackupList (існуючі/нові файли), extractTouchedTables
  (усі види DDL/DML, дедуплікація, ігнор рядків без маркерів),
  pruneOldBackups (лишає 3 найновіші за mtime/іменем).

Commit: `feat(coreupdater): резервна копія файлів і дамп торкнутих таблиць`.

---

### Task 6: `UpdateApplier`

**Files:**
- Create: `.../Helpers/UpdateApplier.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/UpdateApplierTest.php`
- Modify: `.../Init/services.php`

**Interfaces:**
- Pure-виконуване на fixture-деревах (тестовано):
  `applyFiles(string $extractedPayloadDir, string $rootDir, array
  $manifestFiles, callable $onProgress = null): array` — для кожного
  manifest-шляху: mkdir цільової директорії, копія у
  `{$target}.core-update.tmp` поруч + `rename()` (atomic на тій самій ФС);
  повертає перелік застосованих; будь-який збій → RuntimeException із
  переліком уже застосованих (для rollback-звіту). НІЧОГО не видаляє
  (спек: файли лише додаються/замінюються).
  `restoreFiles(string $backupZipPath, string $rootDir): array` — зворотній
  spool-and-swap із backup-архіву (той самий tmp+rename патерн).
- Thin: `runComposerIfNeeded(string $rootDir, string $extractedPayloadDir):
  ?string` — якщо composer.lock у payload відрізняється від поточного:
  знайти composer (`composer`/`composer.phar` у PATH або корені) і
  `composer install --no-dev --optimize-autoloader --no-interaction`
  (symfony/process, timeout 600); ЯКЩО composer недоступний а lock
  відрізняється — RuntimeException (pre-flight у Task 7 ловить це ДО
  змін; тут — страховка). Однаковий lock → null, кроку нема.
  `clearCaches(Design $design): void` — `$design->clearCompiled()` +
  `if (function_exists('opcache_reset')) opcache_reset();`.
- Tests: applyFiles на temp-дереві (нові файли, заміна існуючих, вкладені
  директорії, збій на read-only цілі → виняток із переліком застосованих);
  restoreFiles повертає попередній вміст byte-exact; composer-lock-diff
  визначення (порівняння вмісту, не mtime).

Commit: `feat(coreupdater): застосування файлів spool-and-swap і відкат з копії`.

---

### Task 7: `UpdateRunner` — оркестрація + pre-flight + health check

**Files:**
- Create: `.../Helpers/UpdateRunner.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/UpdateRunnerStepsTest.php`
- Modify: `.../Init/services.php`

**Interfaces:**
- `__construct(UpdateCheckHelper, UpdateStatus, UpdateDownloader,
  UpdateBackup, UpdateApplier, CoreMigrator, Settings, Config, Design)`.
- `run(): array` — повний конвеєр за STEPS з Task 2; кожен крок
  обгорнутий: advance → дія → save; try/catch зовні: fail-статус →
  rollback-гілка (якщо apply_files уже почався) → rolled_back/failed.
  `ignore_user_abort(true)` + `set_time_limit(0)` на старті.
- Lock: `Symfony\Component\Lock\LockFactory` + `FlockStore(files/tmp)` —
  той самий патерн, що Scheduler.php; `->acquire()` без блокування, зайнято
  → RuntimeException "оновлення вже виконується".
- Pre-flight (крок `preflight`, ДО backup): версійні гейти
  (UpdatePackage::assertInstallable по РОЗПАКОВАНОМУ version.json),
  writability пробою (`is_writable` корінь + спроба tmp-файла в Okay/Core),
  вільне місце (`disk_free_space` > 3×розмір розпакованого), composer
  доступний якщо lock відрізняється, mysqldump якщо requiresMigrations.
  Будь-який фейл — до жодних змін.
- Health check: `checkHealth(string $token): bool` — cURL на
  `{root_url}/?core_updater_health=1&core_updater_token={token}`; треба
  легкий обробник: На maintenance-гейті в index.php (Task 1) ДО решти
  bootstrap додати: якщо `$_GET['core_updater_health']` і токен валідний →
  `echo json_encode(['forkVersion' => ...])` з `Okay\Core\Config`?
  Config ще не сконструйований там... ПРОСТІШЕ і надійніше: читати
  forkVersion БЕЗ бутстрапа — розпарсити `Okay/Core/Config.php` регексом
  небезпечно… Рішення: health-відповідь віддає САМ гейт одразу після
  autoload: `(new \ReflectionClass(\Okay\Core\Config::class))
  ->getDefaultProperties()['forkVersion']` — клас автозавантажиться,
  конструктор не потрібен, БД не потрібна. Це доводить: нові файли ядра
  читаються, autoload живий, PHP не падає. Runner звіряє відповідь із
  цільовою версією (маркер зі спека §8.11 — відсікає кешовану сторінку).
  Після health — disable maintenance, статус done, прибрати
  files/tmp/updates/{version}.
- Rollback-гілка: restoreFiles → (migrations НЕ відкочуються — статус
  rolled_back несе appliedMigrations з CoreMigrationException + шлях дампа,
  спек §9) → повторний checkHealth (зі старою версією як маркером) →
  disable maintenance ЛИШЕ якщо health ок; інакше maintenance лишається і
  статус явно каже "потрібне ручне втручання".
- Pure-тестоване: таблиця відповідності STEPS→методи повна (тест: кожен
  крок зі спека §8 має обробник; порядок збігається з UpdateStatus::STEPS);
  логіка вибору rollback-гілки (fail до/після apply_files) як чиста
  функція `needsRollback(array $state): bool` — тестована.
- Решта — Plan E наживо, включно з обов'язковими контролями: битий
  checksum → стоп до backup; бита міграція → rolled_back зі списком;
  вбитий процес посеред apply → isStale виявлення.

Commit: `feat(coreupdater): оркестратор оновлення з pre-flight, health-check і відкатом`.

---

### Task 8: `docs/updates.md`

Операційний документ (укр.): як працює перевірка (C1), як піде оновлення
(C2 кроки), відновлення після збою (ре-ран, ручний rollback з backup-zip,
де лежать дампи, як зняти maintenance вручну — видалити
`config/.maintenance`), обмеження діалекту міграцій (посилання на
release-migrations/README.md), нотатки з леджера C1: перше встановлення
модуля на існуючій інсталяції — вручну через адмінку модулів;
`module.json`-ключ "Okay" — бейдж сумісності, не гейт. Плюс згадка в
`docs/README.md`/індексі, якщо там є перелік доків (перевірити).

Commit: `docs: updates.md — операційна процедура самооновлення`.

---

## Self-Review

**Spec coverage:** §8.3→Task 4; §8.4→Task 3; §8.5→Task 3 (assertInstallable,
плюс pre-download гейт уже в C1 latest.meta); §8.6→Task 5; §8.7→Task 1;
§8.8→Task 6; §8.9→Plan B CoreMigrator (Task 7 викликає); §8.10→Task 6
clearCaches; §8.11→Task 7 checkHealth (маркер версії — реалізовано через
ReflectionClass getDefaultProperties, без бутстрапа); §8.12-13→Task 7;
§9→Tasks 5 (ротація) + 6 (restoreFiles) + 7 (rollback-гілка, maintenance
до health); §11 перерваний апдейт→Task 2 isStale; §11 паралельні
спроби→Task 7 lock; §11 downgrade→Task 3 assertInstallable.

**Свідомі рішення поза буквою спека (задокументовані в задачах):**
(1) health-check через ReflectionClass::getDefaultProperties замість
нового контролера — не потребує роутера/БД під maintenance і чесно
перевіряє autoload+нові файли; (2) mysqldump відсутній + міграції є →
відмова до змін; composer відсутній + lock відрізняється → те саме
(fail-safe для класичного хостингу); (3) CLI-команди немає (модулі не
реєструють console-команди; AJAX-запуск із ignore_user_abort за спеком §8,
запуск з Plan D). Всі три — в docs/updates.md.

**Type consistency:** STEPS констант Task 2 = порядок спека §8 = таблиця
обробників Task 7 (тест на повноту звʼязує їх механічно).
CoreMigrationException.appliedNames (Plan B) споживається rollback-гілкою
Task 7. TRUSTED_ASSET_URL_PREFIX (C1) споживається Task 4.

**Placeholder scan:** немає; verify-інструкції називають реальні файли
(BackendModulesHelper unzip, Scheduler FlockStore, PublicSurfaceTest).

**Scope check:** headless apply-конвеєр + доки; UI (кнопки/поллінг/явний
downgrade) — Plan D; живі прогони з контролями — Plan E.
