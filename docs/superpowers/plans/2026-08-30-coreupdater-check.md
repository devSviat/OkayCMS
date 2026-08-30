# CoreUpdater: Release Check (Plan C1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A CoreUpdater module skeleton that periodically checks the fork's
GitHub Releases, caches the result (with ETag) in Settings, and exposes a
"latest available release vs installed forkVersion" snapshot for the future
apply-side (Plan C2) and admin UI (Plan D).

**Architecture:** New module `Okay/Modules/OkayCMS/CoreUpdater/`. HTTP layer
follows the codebase's house pattern (raw cURL, as in `Okay/Core/Support.php:152-160`
— Guzzle is present in vendor but unused by core; do not introduce it). All
parsing/decision logic is pure and unit-tested against fixture JSON (CI has
no DB and no network); the thin HTTP + Settings glue is exercised in Plan E.
State lives in `Okay\Core\Settings` (auto-`serialize()`s arrays —
`Settings::initSettings()`/`set()`). Background checking via the module's
`registerSchedule()` (`AbstractInit:594`, house example
`NovaposhtaCost/Init/Init.php:210-224`).

**Tech Stack:** PHP 8.4/8.5, cURL, existing module framework (AbstractInit,
module services.php DI), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-30-core-self-updater-design.md`
(§2 — release filtering; §4 — no-token checks, ETag, 6h cache; §13; §15
step 4). Plan C2 (download/verify/backup/apply/rollback) and Plan D (admin
UI) are explicitly out of scope.

## Global Constraints

- PHP `^8.4` — no 8.5-only syntax.
- Comments: Ukrainian, short, why not what. No placeholders/TODOs. No Russian.
- **CI has no DB and no network**: tests construct no Database/EntityFactory/
  ServiceLocator/Entity/Settings instances and make no HTTP calls. Pure
  logic + fixtures only.
- HTTP: raw cURL per house pattern (`CURLOPT_RETURNTRANSFER`, explicit
  timeouts `CURLOPT_TIMEOUT`/`CURLOPT_CONNECTTIMEOUT`, `curl_errno` check).
  GitHub API requires a `User-Agent` header — set one
  (`OkayCMS-Fork-Updater`). Add `Accept: application/vnd.github+json`.
- Release filter regex (spec §2): `#^okaycms-fork/v(\d+\.\d+\.\d+)$#` —
  anything else ignored silently.
- Module boundary: nothing in core (`Okay/Core/`) changes in this plan; the
  module consumes `Config::$forkVersion` (Plan B) read-only.
- Branch: `feat/coreupdater-check` from `origin/dev` (after Plan B's PR #215
  merges — it depends on `Config::$forkVersion`).

---

## File Structure

- `Okay/Modules/OkayCMS/CoreUpdater/Init/Init.php` — install()/init(),
  schedule registration.
- `Okay/Modules/OkayCMS/CoreUpdater/Init/module.json` — `{"Okay": "4.5.2",
  "version": "1.0.0", ...}`.
- `Okay/Modules/OkayCMS/CoreUpdater/Init/services.php` — DI for the two
  services below.
- `Okay/Modules/OkayCMS/CoreUpdater/Helpers/ReleaseFeed.php` — pure:
  parse GitHub `/releases` JSON, filter fork releases, pick latest, compare
  with installed version, decide snapshot freshness.
- `Okay/Modules/OkayCMS/CoreUpdater/Helpers/UpdateCheckHelper.php` — glue:
  cURL call with ETag, Settings read/write of the snapshot, called by the
  scheduler.
- Tests: `tests/Modules/OkayCMS/CoreUpdater/ReleaseFeedTest.php` +
  fixtures (`releases.json`, `releases-with-noise.json`).

---

### Task 1: Module skeleton

**Files:**
- Create: `Okay/Modules/OkayCMS/CoreUpdater/Init/Init.php`
- Create: `Okay/Modules/OkayCMS/CoreUpdater/Init/module.json`
- Create: `Okay/Modules/OkayCMS/CoreUpdater/Init/services.php`

**Interfaces:**
- Produces: installable module `OkayCMS/CoreUpdater`. `install()` calls
  `CoreMigrator::ensureTable()` через ServiceLocator (спек §7: може, але
  ніщо не залежить); `init()` registers the schedule (Task 4 fills it in —
  in this task `init()` stays empty).

- [ ] **Step 1: Write the three files**

`module.json`:
```json
{
    "Okay": "4.5.2",
    "version": "1.0.0",
    "moduleName": "Оновлення ядра",
    "vendor": {
        "name": "OkayCMS"
    }
}
```

`Init/Init.php`:
```php
<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Release\CoreMigrator;
use Okay\Core\ServiceLocator;

class Init extends AbstractInit
{
    public function install()
    {
        // Трекер самостворюваний (спек §7) - виклик тут лише пришвидшує
        // появу таблиці, ніщо від нього не залежить.
        /** @var CoreMigrator $migrator */
        $migrator = ServiceLocator::getInstance()->getService(CoreMigrator::class);
        $migrator->ensureTable();
    }

    public function init()
    {
    }
}
```

`Init/services.php` (namespace + повернення масиву — house style з
AutoDeploy; поки що порожній масив, Task 3 наповнить):
```php
<?php

namespace Okay\Modules\OkayCMS\CoreUpdater;

$services = [];

return $services;
```

Verify against the real AutoDeploy `Init/services.php` how the array is
returned (variable + return vs direct return) and mirror exactly.

- [ ] **Step 2: Sanity: full suite still green** (module not installed in CI,
  but files must parse — the suite's autoload sweep and any module-scanning
  guard tests will catch syntax issues).

Run: `vendor/bin/phpunit` → PASS, no regression.

- [ ] **Step 3: Commit**

```bash
git add Okay/Modules/OkayCMS/CoreUpdater
git commit -m "feat(coreupdater): скелет модуля OkayCMS/CoreUpdater"
```

---

### Task 2: `ReleaseFeed` — pure parsing/decision logic

**Files:**
- Create: `Okay/Modules/OkayCMS/CoreUpdater/Helpers/ReleaseFeed.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/ReleaseFeedTest.php`
- Create fixtures:
  `tests/Modules/OkayCMS/CoreUpdater/fixtures/releases.json` (two valid fork
  releases 1.1.0 і 1.0.0, кожен з assets: zip + version.json + checksums.txt,
  browser_download_url поля)
  `tests/Modules/OkayCMS/CoreUpdater/fixtures/releases-with-noise.json`
  (валідний 1.1.0 + записи-шум: тег `4.6.0`, тег `okaycms-fork/v1.2.0-rc1`,
  драфт `"draft": true`, prerelease `"prerelease": true`)

**Interfaces:**
- Produces (all static or instance methods of a dependency-free class):
  - `ReleaseFeed::parseLatest(string $releasesJson): ?array` — знаходить
    НАЙНОВІШИЙ (за version_compare, не за порядком у списку) валідний
    fork-реліз: тег відповідає `#^okaycms-fork/v(\d+\.\d+\.\d+)$#`, не
    draft, не prerelease. Повертає
    `['forkVersion' => '1.1.0', 'tag' => 'okaycms-fork/v1.1.0',
      'publishedAt' => '...', 'notesUrl' => html_url,
      'assets' => ['zip' => url, 'versionJson' => url, 'checksums' => url]]`
    або `null` (немає валідних / битий JSON). Asset-и мапляться за іменами
    файлів (`okaycms-fork-v{v}.zip`, `version.json`, `checksums.txt`);
    відсутній обовʼязковий asset → реліз пропускається (неповний реліз не
    можна пропонувати).
  - `ReleaseFeed::isNewerThanInstalled(string $candidate, string $installed): bool`
    — `version_compare($candidate, $installed, '>')`.
  - `ReleaseFeed::isSnapshotFresh(array $snapshot, int $nowTs, int $ttlSeconds): bool`
    — `$snapshot['checkedAt'] + $ttl > $now`; відсутній/битий checkedAt →
    false.

- [ ] **Step 1: Write fixtures and failing tests**

Tests (мінімум): latest-by-version не latest-by-order (у fixture 1.0.0
стоїть ПЕРШИМ у списку, 1.1.0 другим — parseLatest мусить віддати 1.1.0);
noise-фільтрація (upstream-тег, rc, draft, prerelease — усі проігноровані);
відсутній asset → реліз пропущений (додати в noise-fixture реліз 1.3.0 без
checksums.txt — parseLatest поверне 1.1.0); битий JSON → null; порожній
масив → null; isNewerThanInstalled true/false/equal; isSnapshotFresh
свіжий/протухлий/відсутній checkedAt.

- [ ] **Step 2: Run to verify failure** (class not found).

- [ ] **Step 3: Implement** (пряма реалізація, без залежностей; json_decode
  з перевіркою is_array; version_usort кандидатів).

- [ ] **Step 4: Run tests** → PASS. Full suite → no regression.

- [ ] **Step 5: Commit**

```bash
git add Okay/Modules/OkayCMS/CoreUpdater/Helpers/ReleaseFeed.php tests/Modules/OkayCMS/CoreUpdater
git commit -m "feat(coreupdater): ReleaseFeed — розбір GitHub Releases і вибір валідного релізу форку"
```

---

### Task 3: `UpdateCheckHelper` — HTTP + Settings glue

**No PHPUnit test — deliberate** (network + Settings/DB both unavailable in
CI; the pure decisions it delegates to are covered by Task 2; the glue is
verified in Plan E on the dev stand, including a real GitHub call).

**Files:**
- Create: `Okay/Modules/OkayCMS/CoreUpdater/Helpers/UpdateCheckHelper.php`
- Modify: `Okay/Modules/OkayCMS/CoreUpdater/Init/services.php`

**Interfaces:**
- Consumes: `Okay\Core\Settings` (get/set — масиви серіалізуються
  автоматично), `Okay\Core\Config` (read `$forkVersion`), `ReleaseFeed`
  (Task 2).
- Produces:
  - `UpdateCheckHelper::__construct(Settings $settings, Config $config)`
  - `UpdateCheckHelper::check(bool $force = false): array` — якщо
    `!$force` і снапшот свіжий (TTL 6*3600) → повертає збережений снапшот.
    Інакше: GET `https://api.github.com/repos/devSviat/OkayCMS/releases?per_page=15`
    cURL-ом (User-Agent, Accept, `If-None-Match` зі збереженого etag;
    таймаути 3/20 як у Support.php). 304 → оновити лише checkedAt. 200 →
    `ReleaseFeed::parseLatest`, зібрати снапшот
    `['checkedAt' => time(), 'etag' => ..., 'installed' => forkVersion,
      'latest' => ...|null, 'updateAvailable' => bool]`,
    записати в Settings ключем `core_updater__snapshot`, повернути.
    HTTP-помилка/timeout → НЕ затирати старий снапшот; повернути старий з
    доданим `'lastError' => ...` (перевірка не має права зіпсувати робочий
    стан кешу — memory-правило про інвалідацію).
  - `UpdateCheckHelper::getSnapshot(): ?array` — читання без мережі (для
    майбутнього UI).
  - Константи: `SETTING_SNAPSHOT = 'core_updater__snapshot'`,
    `TTL = 21600`, `REPO = 'devSviat/OkayCMS'`.
- services.php: реєстрація UpdateCheckHelper з SR(Settings::class),
  SR(Config::class) — house style модульного services.php (namespace
  модуля, повні імена core-класів або use).

- [ ] **Step 1: Implement + register.**
- [ ] **Step 2: Full suite + phpstan** → clean.
- [ ] **Step 3: Commit**

```bash
git add Okay/Modules/OkayCMS/CoreUpdater
git commit -m "feat(coreupdater): перевірка релізів з ETag і кешем у Settings"
```

---

### Task 4: Scheduler registration

**Files:**
- Modify: `Okay/Modules/OkayCMS/CoreUpdater/Init/Init.php`

**Interfaces:**
- Consumes: `UpdateCheckHelper::check()` (Task 3),
  `AbstractInit::registerSchedule()` (house example
  `NovaposhtaCost/Init/Init.php:210-224` — read it and mirror the exact
  Schedule construction).
- Produces: щоденна фонова перевірка (cron `30 4 * * *` — не опівночі, щоб
  не збігатись із типовими нічними джобами), `overlap(false)`, розумний
  timeout (60с — це один HTTP-запит).

- [ ] **Step 1: Add to `init()`** the `registerSchedule` call per the house
  pattern (verify exact imports/classes from the NovaposhtaCost example).
- [ ] **Step 2: Full suite** → no regression.
- [ ] **Step 3: Commit**

```bash
git add Okay/Modules/OkayCMS/CoreUpdater/Init/Init.php
git commit -m "feat(coreupdater): щоденна фонова перевірка оновлень через scheduler"
```

---

## Self-Review

**Spec coverage:** §2 (filter regex, /releases not /tags) → Task 2. §4
(no token, ETag, 6h TTL, cache-first reads, никогда не б'є GitHub синхронно
з рендером — getSnapshot без мережі) → Tasks 2-3. §13 (module skeleton) →
Task 1. Scheduler — спек §4 "перевірка раз на кілька годин" → Task 4
(щоденно; TTL 6h дозволить частіше, якщо адмін натисне "перевірити зараз"
у Plan D).

**Placeholder scan:** none; three verify-against-real-file instructions
(AutoDeploy services.php return shape, NovaposhtaCost Schedule construction,
Support.php cURL options) name their files.

**Type consistency:** snapshot shape (`checkedAt/etag/installed/latest/
updateAvailable`) identical between Task 2's isSnapshotFresh consumer and
Task 3's producer; `parseLatest` return shape consumed by Task 3 verbatim.

**Scope check:** check-side only; no download, no apply, no UI, no core
changes. C2 and D build on these interfaces.
