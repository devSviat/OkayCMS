# Core Version + Migration Tracking (Plan B) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the fork a machine-readable installed-version marker
(`Config::$forkVersion`) and a core-level DB-migration runner
(`ok_core_migrations` tracking, `.up.sql` files applied by name) that
Plan C's CoreUpdater will call during the apply step.

**Architecture:** One new public property on the existing `Okay\Core\Config`.
One new entity (`CoreMigrationsEntity`, table `__core_migrations` →
`ok_core_migrations`). One new class `Okay\Core\Release\CoreMigrator` in the
same namespace as Plan A's classes, split the same way `PackageBuilder` was:
pure, DB-free logic (pending-set computation, SQL file splitting) fully
unit-tested; a thin DB-touching `apply()` deliberately not unit-tested (CI
has no database — QueryFactory connects in its constructor, so the suite
never opens a connection) and instead exercised end-to-end on the broken dev
stand in Plan E.

**Tech Stack:** PHP 8.4/8.5, existing `Okay\Core` patterns (Entity,
services.php DI), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-30-core-self-updater-design.md`
(§1 — fork version field; §7 — core migrations; §13). One deliberate
deviation from §7, recorded in Task 4: the tracker table is self-creating
(`CREATE TABLE IF NOT EXISTS` inside `CoreMigrator::ensureTable()`) instead
of being created only by the CoreUpdater module's `install()` — the migrator
must be usable from CLI during an update where module state is mid-flight,
and a self-creating idempotent tracker removes that ordering dependency.
The module's `install()` (Plan C) may still call `ensureTable()`.

## Global Constraints

- PHP `^8.4` — no 8.5-only syntax.
- Code comments: Ukrainian, short, why not what. No placeholders/TODOs.
- **CI has no database** (`tests/bootstrap.php` loads only autoload +
  constants + functions; QueryFactory would connect in its constructor):
  no test may construct `Database`, `EntityFactory`, `ServiceLocator`, or
  any Entity instance. Pure-logic tests only.
- **Never use `Database::restore()` for core migrations** — verified
  (`Okay/Core/Database.php:297-328`): it catches `PDOException` and
  `print`s the error, continuing with the next statement. A half-applied
  core migration continuing silently is exactly the failure mode spec §8
  step 9 forbids. `CoreMigrator` executes statements itself and throws.
- Table naming: core entities declare `protected static $table = '__name'`
  (`Okay/Entities/ProductsEntity.php:49`); the `__` prefix resolves to
  `db_prefix` (`ok_` in `config/config.php:21`). Raw SQL built outside the
  Entity/Database layer must substitute the prefix itself via
  `Config::get('db_prefix')`.
- Branch flow: feature branch from `origin/dev`, PR into `dev`
  (release later via release-PR flow).

---

## File Structure

- `Okay/Core/Config.php` — modify: add `$forkVersion` property.
- `Okay/Entities/CoreMigrationsEntity.php` — new entity.
- `Okay/Core/Release/CoreMigrator.php` — new class.
- `Okay/Core/config/services.php` — register `CoreMigrator`.
- `docs/superpowers/specs/2026-08-30-core-self-updater-design.md` — §7
  amendment (self-creating table).
- Tests: `tests/Core/ConfigForkVersionTest.php`,
  `tests/Core/Release/CoreMigratorTest.php`,
  fixtures `tests/Core/Release/fixtures/migrations/*.up.sql`.

---

### Task 1: `Config::$forkVersion`

**Files:**
- Modify: `Okay/Core/Config.php` (property block, next to `$version` at line ~17)
- Create: `tests/Core/ConfigForkVersionTest.php`

**Interfaces:**
- Produces: `Okay\Core\Config::$forkVersion` — public string, SemVer
  `X.Y.Z`, initial value `'1.0.0'` (matches the published
  `okaycms-fork/v1.0.0` release). Consumed later by Plan C/D (updater
  check + admin UI).

- [ ] **Step 1: Write the failing test**

No instantiation — `Config`'s constructor reads real ini files; default
property values are readable via reflection without constructing:

```php
<?php

namespace Core;

use Okay\Core\Config;
use PHPUnit\Framework\TestCase;

class ConfigForkVersionTest extends TestCase
{
    public function testForkVersionDefaultIsSemver(): void
    {
        $defaults = (new \ReflectionClass(Config::class))->getDefaultProperties();

        $this->assertArrayHasKey('forkVersion', $defaults);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $defaults['forkVersion']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Core/ConfigForkVersionTest.php`
Expected: FAIL — `Failed asserting that an array has the key 'forkVersion'`.

- [ ] **Step 3: Write minimal implementation**

In `Okay/Core/Config.php`, directly under the existing `$version` property:

```php
    /*Версія форку (лінійка релізів okaycms-fork/vX.Y.Z)*/
    public string $forkVersion = '1.0.0';
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add Okay/Core/Config.php tests/Core/ConfigForkVersionTest.php
git commit -m "feat(core): поле forkVersion у Config — маркер встановленої версії форку"
```

---

### Task 2: `CoreMigrationsEntity` + `CoreMigrator` pure logic

**Files:**
- Create: `Okay/Entities/CoreMigrationsEntity.php`
- Create: `Okay/Core/Release/CoreMigrator.php`
- Create: `tests/Core/Release/CoreMigratorTest.php`
- Create fixtures:
  `tests/Core/Release/fixtures/migrations/1.1.0_add_rating.up.sql`
  `tests/Core/Release/fixtures/migrations/1.2.0_add_index.up.sql`
  `tests/Core/Release/fixtures/migrations/README.txt` (non-.up.sql file,
  must be ignored by the glob)

**Interfaces:**
- Produces:
  - `Okay\Entities\CoreMigrationsEntity` — fields `id`, `name`,
    `applied_at`; table `__core_migrations`; alias `cmig`.
  - `CoreMigrator::pending(string $migrationsDir, array $appliedNames): array`
    — sorted list of `['name' => basename, 'path' => full path]` for
    `*.up.sql` files not in `$appliedNames`. Pure, no DB.
  - `CoreMigrator::splitSqlFile(string $path): array` — list of complete
    SQL statements (strings); skips `--` comment lines; statement ends at a
    line whose trimmed tail is `;` (the same parsing rules
    `Database::restore()` uses, minus the error swallowing). Pure, no DB.

- [ ] **Step 1: Write fixtures and the failing test**

```sql
-- tests/Core/Release/fixtures/migrations/1.1.0_add_rating.up.sql
-- Тестова міграція: два стейтменти
CREATE TABLE IF NOT EXISTS `test_a` (id INT);
ALTER TABLE `test_a` ADD COLUMN r INT;
```

```sql
-- tests/Core/Release/fixtures/migrations/1.2.0_add_index.up.sql
CREATE INDEX idx_r ON `test_a` (r);
```

```
tests/Core/Release/fixtures/migrations/README.txt
не .up.sql — мігратор не має це бачити
```

```php
<?php

namespace Core\Release;

use Okay\Core\Release\CoreMigrator;
use PHPUnit\Framework\TestCase;

class CoreMigratorTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures/migrations';
    }

    public function testPendingReturnsUnappliedSqlFilesSorted(): void
    {
        $migrator = new CoreMigrator();

        $pending = $migrator->pending($this->fixturesDir, []);

        $this->assertSame(
            ['1.1.0_add_rating.up.sql', '1.2.0_add_index.up.sql'],
            array_column($pending, 'name')
        );
        $this->assertFileExists($pending[0]['path']);
    }

    public function testPendingSkipsAlreadyAppliedNames(): void
    {
        $migrator = new CoreMigrator();

        $pending = $migrator->pending($this->fixturesDir, ['1.1.0_add_rating.up.sql']);

        $this->assertSame(['1.2.0_add_index.up.sql'], array_column($pending, 'name'));
    }

    public function testPendingIgnoresNonUpSqlFiles(): void
    {
        $migrator = new CoreMigrator();

        $names = array_column($migrator->pending($this->fixturesDir, []), 'name');

        $this->assertNotContains('README.txt', $names);
    }

    public function testPendingOnMissingDirectoryIsEmpty(): void
    {
        $migrator = new CoreMigrator();

        $this->assertSame([], $migrator->pending($this->fixturesDir . '/does-not-exist', []));
    }

    public function testSplitSqlFileSeparatesStatementsAndSkipsComments(): void
    {
        $migrator = new CoreMigrator();

        $statements = $migrator->splitSqlFile($this->fixturesDir . '/1.1.0_add_rating.up.sql');

        $this->assertCount(2, $statements);
        $this->assertStringStartsWith('CREATE TABLE', trim($statements[0]));
        $this->assertStringStartsWith('ALTER TABLE', trim($statements[1]));
        $this->assertStringNotContainsString('--', $statements[0]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Core/Release/CoreMigratorTest.php`
Expected: FAIL — `Class "Okay\Core\Release\CoreMigrator" not found`.

- [ ] **Step 3: Write the entity and the pure part of the migrator**

```php
<?php

namespace Okay\Entities;

use Okay\Core\Entity\Entity;

class CoreMigrationsEntity extends Entity
{
    protected static $fields = [
        'id',
        'name',
        'applied_at',
    ];

    protected static $defaultOrderFields = [
        'id',
    ];

    protected static $table = '__core_migrations';
    protected static $tableAlias = 'cmig';
}
```

```php
<?php

namespace Okay\Core\Release;

class CoreMigrator
{
    /** @return list<array{name: string, path: string}> */
    public function pending(string $migrationsDir, array $appliedNames): array
    {
        $pending = [];

        foreach (glob(rtrim($migrationsDir, '/') . '/*.up.sql') ?: [] as $path) {
            $name = basename($path);
            if (!in_array($name, $appliedNames, true)) {
                $pending[] = ['name' => $name, 'path' => $path];
            }
        }

        usort($pending, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $pending;
    }

    /** @return list<string> повні SQL-стейтменти файла, без коментарів */
    public function splitSqlFile(string $path): array
    {
        $statements = [];
        $current = '';

        foreach (file($path) as $line) {
            if (str_starts_with($line, '--') || trim($line) === '') {
                continue;
            }

            $current .= $line;
            if (str_ends_with(trim($line), ';')) {
                $statements[] = $current;
                $current = '';
            }
        }

        return $statements;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add Okay/Entities/CoreMigrationsEntity.php Okay/Core/Release/CoreMigrator.php \
  tests/Core/Release/CoreMigratorTest.php tests/Core/Release/fixtures/migrations
git commit -m "feat(core): CoreMigrator — облік core-міграцій за іменем файлу"
```

---

### Task 3: `CoreMigrator::apply()` + `ensureTable()` + DI

**No PHPUnit test for the DB-touching methods — deliberate.** CI has no
database (Global Constraints); constructing `EntityFactory`/`ExtendedPdo`
in the suite is impossible without one. The pure logic these methods
compose (`pending()`, `splitSqlFile()`) is covered by Task 2; the DB path
gets its end-to-end verification on the broken dev stand in Plan E,
including the deliberate-failure control (a broken migration must stop the
run and leave a record trail).

**Files:**
- Modify: `Okay/Core/Release/CoreMigrator.php`
- Modify: `Okay/Core/config/services.php`

**Interfaces:**
- Consumes: `Aura\Sql\ExtendedPdo` (registered in services.php as
  `ExtendedPdo::class`), `Okay\Core\EntityFactory`,
  `Okay\Core\Config` (for `db_prefix`), `CoreMigrationsEntity` (Task 2).
- Produces:
  - `CoreMigrator::__construct(ExtendedPdo $pdo, EntityFactory $entityFactory, Config $config)`
    — NOTE: this changes Task 2's zero-arg construction; Task 2's tests
    must be updated in this task to construct with mocks/nulls — see Step 1.
  - `CoreMigrator::ensureTable(): void` — `CREATE TABLE IF NOT EXISTS
    {prefix}core_migrations` (id PK AI, name VARCHAR(255) UNIQUE,
    applied_at DATETIME).
  - `CoreMigrator::apply(string $migrationsDir): array` — ensures table,
    reads applied names via `CoreMigrationsEntity`, executes each pending
    file statement-by-statement via `$pdo->perform()`, records
    `name` + `applied_at` after each file completes; any `PDOException`
    is wrapped in `RuntimeException` naming the file and statement and
    **stops the run** (no swallowing). Returns the list of applied names.

- [ ] **Step 1: Restructure construction without breaking Task 2's tests**

Making the DB dependencies constructor-injected would force Task 2's pure
tests to build an `ExtendedPdo` — impossible in CI. Instead keep the
class constructible bare for the pure methods and inject lazily:

```php
    public function __construct(
        private readonly ?\Aura\Sql\ExtendedPdo $pdo = null,
        private readonly ?\Okay\Core\EntityFactory $entityFactory = null,
        private readonly ?\Okay\Core\Config $config = null
    ) {
    }

    private function requireDb(): void
    {
        if ($this->pdo === null || $this->entityFactory === null || $this->config === null) {
            throw new \LogicException('CoreMigrator: apply()/ensureTable() потребують повної конструкції через DI');
        }
    }
```

Task 2's tests keep constructing `new CoreMigrator()` — unchanged and
still valid. Add one new test to `CoreMigratorTest`:

```php
    public function testApplyWithoutDbDependenciesThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);

        (new CoreMigrator())->apply(__DIR__);
    }
```

- [ ] **Step 2: Run the new test to verify it fails**

Expected: FAIL — `apply()` does not exist yet.

- [ ] **Step 3: Implement `ensureTable()` and `apply()`**

```php
    public function ensureTable(): void
    {
        $this->requireDb();

        $table = $this->config->get('db_prefix') . 'core_migrations';

        // Самостворення трекера: мігратор мусить працювати і з CLI посеред
        // оновлення, коли install() модуля ще/вже не викликався.
        $this->pdo->perform(
            "CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) NOT NULL,
                `applied_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /** @return list<string> імена застосованих цим викликом міграцій */
    public function apply(string $migrationsDir): array
    {
        $this->requireDb();
        $this->ensureTable();

        /** @var \Okay\Entities\CoreMigrationsEntity $migrationsEntity */
        $migrationsEntity = $this->entityFactory->get(\Okay\Entities\CoreMigrationsEntity::class);

        $appliedNames = $migrationsEntity->cols(['name'])->noLimit()->find();
        $appliedNow = [];

        foreach ($this->pending($migrationsDir, $appliedNames) as $migration) {
            foreach ($this->splitSqlFile($migration['path']) as $statement) {
                try {
                    $this->pdo->perform($statement);
                } catch (\PDOException $e) {
                    // Стоп одразу: продовжувати після невдалого стейтмента -
                    // отримати неконсистентну схему з виглядом успіху.
                    throw new \RuntimeException(
                        "Core-міграція {$migration['name']} впала на стейтменті: "
                        . mb_substr(trim($statement), 0, 200),
                        0,
                        $e
                    );
                }
            }

            $migrationsEntity->add([
                'name' => $migration['name'],
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
            $appliedNow[] = $migration['name'];
        }

        return $appliedNow;
    }
```

Implementation notes for the implementer:
- Check `Entity::cols()->noLimit()->find()` returns a flat list of the
  single column's values when `cols(['name'])` is used — that's how
  AutoDeploy's `DeployHelper::getNewMigrations()` consumes it
  (`Okay/Modules/OkayCMS/AutoDeploy/Helpers/DeployHelper.php:129-135`,
  it passes the result straight to `in_array`). Follow the same call
  shape; if the return shape differs from a flat list, adapt the
  `$appliedNames` extraction accordingly and note it in your report.
  Beware the project's known empty-array-filter trap: `find(['id' => []])`
  returns the whole table — here `find()` takes no filter, which is safe,
  but do not "improve" it by passing a filter array.
- `noLimit()` matters: default Entity SELECT limit is 100 records and a
  filter without `page` may behave differently — migrations count can
  exceed 100 over the years.

- [ ] **Step 4: Register in services.php**

Follow the existing style (`Okay/Core/config/services.php`, e.g. the
`DataCleaner::class` block at ~line 357):

```php
    CoreMigrator::class => [
        'class' => CoreMigrator::class,
        'arguments' => [
            new SR(ExtendedPdo::class),
            new SR(EntityFactory::class),
            new SR(Config::class),
        ],
    ],
```

Add the `use Okay\Core\Release\CoreMigrator;` import at the top of the
file with the other imports. Verify `ExtendedPdo::class` is actually the
service id used in this file (read the file; if the PDO service is
registered under a different key, use that one).

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: PASS, no regression (services.php is not loaded by unit tests,
but the class must still parse — the suite's autoload sweep and PHPStan
will catch syntax issues). Also run
`vendor/bin/phpstan analyse Okay/Core/Release Okay/Entities/CoreMigrationsEntity.php`
if a quick scoped run is supported by the repo's phpstan config; otherwise
full `vendor/bin/phpstan analyse`.

- [ ] **Step 6: Commit**

```bash
git add Okay/Core/Release/CoreMigrator.php Okay/Core/config/services.php \
  tests/Core/Release/CoreMigratorTest.php
git commit -m "feat(core): CoreMigrator::apply — виконання core-міграцій зі стопом на помилці"
```

---

### Task 4: Spec amendment (§7 self-creating table)

**Files:**
- Modify: `docs/superpowers/specs/2026-08-30-core-self-updater-design.md`

- [ ] **Step 1: Amend §7**

In the §7 bullet that says the table is created by the CoreUpdater
module's install migration, replace with: the tracker table is
self-creating (`CoreMigrator::ensureTable()`, `CREATE TABLE IF NOT
EXISTS`) so the migrator works from CLI mid-update regardless of module
install ordering; the CoreUpdater module's `install()` may call
`ensureTable()` but nothing depends on it.

- [ ] **Step 2: Commit**

```bash
git add docs/superpowers/specs/2026-08-30-core-self-updater-design.md
git commit -m "docs: §7 — трекер core-міграцій самостворюваний, без залежності від install()"
```

---

## Self-Review

**Spec coverage:** §1 → Task 1. §7 → Tasks 2-3 (+ Task 4 amendment). §13's
Plan-B-relevant lines (Config field, ok_core_migrations) → Tasks 1-3.
CoreUpdater module itself, admin UI, check/download/apply flow — Plan C/D,
out of scope here by design.

**Placeholder scan:** none. Two "verify against the real file" notes
(Entity cols()->find() return shape; ExtendedPdo service key in
services.php) point at named real files with the expected answer stated —
they are verification instructions, not gaps.

**Type consistency:** `pending()`'s return shape (`name`/`path` keys) is
used identically in Task 2's tests and Task 3's `apply()`.
`splitSqlFile()` signature unchanged across tasks. Task 3 explicitly
handles the constructor-compatibility of Task 2's tests (nullable lazy
deps) instead of silently breaking them.

**Scope check:** small, single-PR plan; produces working tested code (pure
parts) plus DB glue verified in Plan E. No decomposition needed.
