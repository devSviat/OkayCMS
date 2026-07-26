# Security Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the 19 confirmed security defects catalogued in `docs/superpowers/specs/2026-07-26-security-hardening-design.md` without modernizing dependencies.

**Architecture:** A new `Okay\Core\Security\` namespace holds small, dependency-free classes — one responsibility each, each unit-testable in isolation. Legacy procedural entrypoints (filemanager, `backend/files/index.php`) keep their shape but delegate every security decision to those classes. Existing public signatures are preserved wherever a module might call them.

**Tech Stack:** PHP 8.5, PHPUnit 9.6, custom DI container (`Okay\Core\OkayContainer`), custom ORM (`Okay\Core\Entity\Entity`), Smarty 4.5, Docker (nginx + php85 + mariadb).

## Global Constraints

- Security work only. No dependency upgrades (Smarty 5, Symfony 8, PHPMailer 7, Intervention Image, `wikimedia/minify` are all out of scope).
- No `strict_types` declarations added to existing files. New files in `Okay/Core/Security/` also omit it, to match the surrounding codebase.
- PHPUnit is 9.6 — use `/** @dataProvider */` docblock annotations, never PHP 8 attributes.
- `phpunit.xml` sets `convertDeprecationsToExceptions`, `convertNoticesToExceptions` and `convertWarningsToExceptions` to `true`. Any PHP warning raised during a test fails that test. This is deliberate — several tasks rely on it.
- **No database changes of any kind.** No `ALTER TABLE`, no `CREATE TABLE`, no new columns, no index changes, no new file in `1DB_changes/`. This is a hard requirement, not a preference — Task 0 installs a guard test that fails the build if a migration file appears or DDL lands in new code, and Task 24 diffs the live schema against a baseline taken before the work starts. (Module `update_x_y_z()` methods are a legitimate part of the architecture and are not restricted; the schema diff is what catches one that actually changes the database.) Every value the plan writes fits a column that already exists:

  | Column | Type | Largest value written | Fits |
  | ------ | ---- | --------------------- | ---- |
  | `ok_managers.password` | `varchar(255)` | Argon2id hash, 97 chars | yes |
  | `ok_users.password` | `varchar(255)` | Argon2id hash, 97 chars | yes |
  | `ok_users.remind_code` | `varchar(32)` | truncated sha256 digest, exactly 32 chars | yes |
  | `ok_orders.payment_details` | `mediumtext` | callback JSON, unchanged in shape | yes |

  Manager recovery stores nothing at all — its token is stateless and signed. If any task appears to need a schema change, stop and report it rather than writing a migration.
- Helper and request methods must keep returning through `ExtenderFacade::execute(__METHOD__, $result, func_get_args())`.
- Never write raw SQL for CRUD — use the `Entity` base class.
- The existing suite (176 tests) must stay green after every task.
- Run everything inside the container: `cd dev && docker compose exec php85 <command>`.

## Commands

```bash
cd dev && docker compose up -d
docker compose exec php85 composer install
docker compose exec php85 php vendor/bin/phpunit                 # full suite
docker compose exec php85 php vendor/bin/phpunit tests/Security   # this plan's tests
docker compose exec php85 php vendor/bin/phpunit --filter PasswordHasherTest
```

`config/config.local.php` is gitignored and already points at the `mariadb` service. Storefront: `http://okaycms.loc`. Admin: `http://okaycms.loc/admin`, `admin` / `1234`.

## File Structure

**New — `Okay/Core/Security/`**

| File | Responsibility |
| ---- | -------------- |
| `PasswordHasher.php` | Argon2id/bcrypt hashing; verification of modern + three legacy formats; rehash detection |
| `RecoveryToken.php` | Customer recovery: opaque token, truncated digest, TTL |
| `AdminRecoveryToken.php` | Manager recovery: stateless HMAC token bound to manager id + current password hash |
| `SafeRedirect.php` | Same-origin URL validation |
| `CustomerCsrfToken.php` | Storefront CSRF token: get / check / rotate |
| `AdminCsrfToken.php` | Backend CSRF token stored in `$_SESSION['id']` |
| `SessionNames.php` | `okay_sid` / `okay_admin_sid`, cookie params, regeneration |
| `SecurityHeaders.php` | Baseline response headers |
| `SvgSanitizer.php` | Allowlist-based SVG rewriting |
| `BackendFileDownloadPolicy.php` | folder + file + extension → required permission |
| `Filemanager/PathResolver.php` | Request path → absolute path confined to a root |
| `Filemanager/AccessGuard.php` | Authenticated-manager + permission check |

**New — other**

| File | Responsibility |
| ---- | -------------- |
| `backend/design/js/filemanager/include/okay_access.php` | Procedural bootstrap that invokes `AccessGuard` |
| `docs/UPGRADE-security.md` | Migration notes for theme and module authors |
| `tests/Security/*.php` | 25 test classes, one per boundary |

**Modified — the significant ones**

| File | Change |
| ---- | ------ |
| `Okay/Core/Managers.php:276-281` | `checkPassword()` delegates to `PasswordHasher`; adds `hashPassword()`, `needsPasswordRehash()` |
| `Okay/Entities/UsersEntity.php:78-140` | `add()`/`update()` hash via `PasswordHasher`; `checkPassword()` verifies in PHP and rehashes |
| `backend/Controllers/AuthAdmin.php` | Recovery rewritten around `AdminRecoveryToken` |
| `Okay/Controllers/UserController.php:173-227` | `passwordRemind()` rewritten around `RecoveryToken` |
| `Okay/Core/Request.php:375-384` | `checkSession()` uses `AdminCsrfToken` + `hash_equals` |
| `backend/index.php:36,40,234-238` | Session name, CSRF token seeding, guard moved before dispatch |
| `index.php:18-21` | Session name |
| `Okay/Core/Response.php:82-86` | Version stripped, baseline headers added |
| `Okay/Helpers/MainHelper.php:459-470` | PRG redirect validated |
| Feeds: 2 base adapters + 14 concrete adapters | Operator allowlist |
| `backend/files/index.php` | Policy + path resolver |
| 12 `setcookie()` call sites | Options-array form |

---

## Follow-ups (out of scope for this branch)

- **Localise `backend/design/html/auth.tpl`.** The admin login template hardcodes every UI string in Russian and has no translation mechanism, unlike the rest of the backend which uses `backend/lang/{en,ru,ua}.php`. Task 5 added two error messages in Russian to match the surrounding file rather than leave the page mixed-language. Either translate the whole template or extract its strings into the lang files.
- **`BackendSettingsHelper` does not survive a partial POST.** `updateGeneralSettings()` passes a missing `license_email` straight to `LicenseModulesTemplates::setLicenseEmail(string)`, which is a `TypeError`. Found while verifying Task 8; unrelated to CSRF and left alone.
- **`minimum-stability: dev` makes `composer update` unsafe.** Updating guzzle alone resolved to a `7.12.x-dev` branch commit until `guzzlehttp/promises` joined the update set. Worth revisiting the stability policy.
- **`expose_php` is on.** Responses carry `X-Powered-By: PHP/8.5.8`. Task 18 removed our own version banner but this one belongs in the production `php.ini`.

---

## Phase 0 — Schema guard

### Task 0: Lock the database schema

**Files:**
- Create: `tests/Security/NoDatabaseChangeTest.php`
- Create: `dev/schema-baseline.txt` (generated, committed)

**Interfaces:**
- Consumes: nothing.
- Produces: a failing test the moment any task introduces a migration, a DDL statement, or a new file in `1DB_changes/`.

**Why this comes first.** Every later task writes to columns that already exist, and none of them needs a schema change. That is easy to say and easy to violate under pressure — a task that hits a length limit or a missing column is exactly where someone reaches for an `ALTER TABLE`. This guard makes that impossible to do quietly.

- [ ] **Step 1: Capture the baseline**

```bash
cd /home/sviat/projects/OkayCMS
ls 1DB_changes/ | sort > dev/schema-baseline.txt
wc -l dev/schema-baseline.txt
```
Expected: 53 lines (`okay_clean.sql` plus 52 `update_*.sql` files). If the count differs, use the real number in the test below.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/NoDatabaseChangeTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

/**
 * Эта итерация не меняет схему БД. Тест держит это свойство:
 * любая новая миграция или DDL в новом коде роняет сборку.
 */
class NoDatabaseChangeTest extends TestCase
{
    public function testNoMigrationFileWasAdded()
    {
        $root = dirname(__DIR__, 2);

        $baseline = file($root . '/dev/schema-baseline.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($baseline);

        $current = scandir($root . '/1DB_changes');
        $current = array_values(array_diff($current, ['.', '..']));
        sort($current);
        sort($baseline);

        $this->assertSame($baseline, $current, 'A file was added to or removed from 1DB_changes/');
    }

    /**
     * @dataProvider ddlKeywordProvider
     */
    public function testSecurityCodeContainsNoDdl($keyword)
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach ($this->phpFiles($root . '/Okay/Core/Security') as $file) {
            $source = file_get_contents($file);
            if ($source !== false && stripos($source, $keyword) !== false) {
                $offenders[] = str_replace($root . '/', '', $file);
            }
        }

        $this->assertSame([], $offenders, $keyword . ' found in Okay/Core/Security');
    }

    public function ddlKeywordProvider()
    {
        return [
            'alter table'  => ['ALTER TABLE'],
            'create table' => ['CREATE TABLE'],
            'drop table'   => ['DROP TABLE'],
            'add column'   => ['ADD COLUMN'],
        ];
    }

    private function phpFiles($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
```

- [ ] **Step 3: Run it against the untouched tree**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter NoDatabaseChangeTest`
Expected: PASS, 5 tests (1 + 4 data rows).

Module `update_x_y_z()` methods are deliberately NOT guarded here — they are the architecture's normal migration mechanism and modules are free to use them. The backstop for a module that does touch the schema is the live-schema diff in Task 24, not this test.

- [ ] **Step 4: Confirm the live schema matches the seed**

```bash
cd dev && docker compose exec -T mariadb mysqldump -uroot -proot --no-data --skip-comments okay \
  | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > "$SCRATCH/schema-before.sql"
wc -l "$SCRATCH/schema-before.sql"
```

`$SCRATCH` is this session's scratchpad directory. The `sed` strips `AUTO_INCREMENT=` counters, which move whenever a row is inserted during manual verification and would otherwise read as a schema change. Keep the dump for Task 24 Step 8. If the container is recreated in the meantime, regenerate it from a clean database before comparing.

- [ ] **Step 5: Commit**

```bash
git add tests/Security/NoDatabaseChangeTest.php dev/schema-baseline.txt
git commit -m "test(security): guard against database schema changes"
```

---

## Phase A — Passwords

### Task 1: `PasswordHasher`

**Files:**
- Create: `Okay/Core/Security/PasswordHasher.php`
- Test: `tests/Security/PasswordHasherTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `PasswordHasher::hash(string $password): string`
  - `PasswordHasher::verify(string $password, ?string $hash, ?string $legacySalt = null): bool`
  - `PasswordHasher::needsRehash(?string $hash): bool`
  - `PasswordHasher::cryptApr1Md5(string $plainpasswd, string $salt = ''): string` (public, so `Managers` can delegate)

- [ ] **Step 1: Write the failing test**

Create `tests/Security/PasswordHasherTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\PasswordHasher;
use PHPUnit\Framework\TestCase;

class PasswordHasherTest extends TestCase
{
    public function testHashProducesModernFormatAndVerifies()
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('secret');

        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $hash);
        $this->assertTrue($hasher->verify('secret', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
        $this->assertFalse($hasher->needsRehash($hash));
    }

    public function testMalformedStoredHashesAreRejectedWithoutWarnings()
    {
        $hasher = new PasswordHasher();

        $this->assertFalse($hasher->verify('secret', null));
        $this->assertFalse($hasher->verify('secret', ''));
        $this->assertFalse($hasher->verify('secret', 'not-a-hash'));
        $this->assertFalse($hasher->verify('secret', '$broken$hash'));
        $this->assertFalse($hasher->verify('secret', '$apr1$12345678$short'));
    }

    public function testLegacyApr1HashVerifiesAndNeedsRehash()
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->cryptApr1Md5('secret', '12345678');

        $this->assertTrue($hasher->verify('secret', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
        $this->assertTrue($hasher->needsRehash($hash));
    }

    public function testLegacySaltedMd5HashVerifiesAndNeedsRehash()
    {
        $hasher = new PasswordHasher();
        $salt = '8e86a279d6e182b3c811c559e6b15484';
        $hash = md5($salt . 'secret' . md5('secret'));

        $this->assertTrue($hasher->verify('secret', $hash, $salt));
        $this->assertFalse($hasher->verify('wrong', $hash, $salt));
        $this->assertTrue($hasher->needsRehash($hash));
    }

    public function testLegacyRawMd5HashVerifies()
    {
        $hasher = new PasswordHasher();
        $hash = md5('secret');

        $this->assertTrue($hasher->verify('secret', $hash));
        $this->assertFalse($hasher->verify('wrong', $hash));
    }

    public function testEmptyPasswordNeverVerifies()
    {
        $hasher = new PasswordHasher();

        $this->assertFalse($hasher->verify('', $hasher->hash('secret')));
        $this->assertFalse($hasher->verify('', md5('')));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter PasswordHasherTest`
Expected: FAIL — `Class "Okay\Core\Security\PasswordHasher" not found`.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/PasswordHasher.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * Единая точка проверки и создания хешей паролей.
 *
 * Новые пароли всегда создаются через password_hash(). Старые форматы
 * (APR1-MD5, salted MD5, raw MD5) проверяются только для обратной
 * совместимости и должны быть перехешированы после успешного входа.
 */
class PasswordHasher
{
    /** Хеш в формате APR1-MD5: $apr1$<salt 1-8>$<22 символа> */
    const APR1_PATTERN = '/^\$apr1\$([.\/0-9A-Za-z]{1,8})\$[.\/0-9A-Za-z]{22}$/';

    /** Любой MD5-хеш (salted или raw) */
    const MD5_PATTERN = '/^[0-9a-f]{32}$/i';

    public function hash($password)
    {
        $password = (string)$password;

        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }

        return password_hash($password, PASSWORD_BCRYPT);
    }

    public function verify($password, $hash, $legacySalt = null)
    {
        $password = (string)$password;
        $hash = (string)$hash;

        if ($password === '' || $hash === '') {
            return false;
        }

        if ($this->isModernHash($hash)) {
            return password_verify($password, $hash);
        }

        if (preg_match(self::APR1_PATTERN, $hash, $matches)) {
            return hash_equals($hash, $this->cryptApr1Md5($password, $matches[1]));
        }

        if (preg_match(self::MD5_PATTERN, $hash)) {
            if ($legacySalt !== null
                && hash_equals(strtolower($hash), md5($legacySalt . $password . md5($password)))
            ) {
                return true;
            }

            return hash_equals(strtolower($hash), md5($password));
        }

        return false;
    }

    public function needsRehash($hash)
    {
        $hash = (string)$hash;

        if ($hash === '') {
            return false;
        }

        if (!$this->isModernHash($hash)) {
            return true;
        }

        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID);
        }

        return password_needs_rehash($hash, PASSWORD_BCRYPT);
    }

    public function isModernHash($hash)
    {
        $info = password_get_info((string)$hash);

        return !empty($info['algo']);
    }

    /**
     * Оставлено для проверки существующих APR1-хешей.
     * Новые пароли этим методом не создаются.
     */
    public function cryptApr1Md5($plainpasswd, $salt = '')
    {
        if (empty($salt)) {
            $salt = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        }
        $len = strlen($plainpasswd);
        $text = $plainpasswd . '$apr1$' . $salt;
        $bin = pack('H32', md5($plainpasswd . $salt . $plainpasswd));
        for ($i = $len; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }
        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $plainpasswd[0];
        }
        $bin = pack('H32', md5($text));
        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $plainpasswd : $bin;
            if ($i % 3) {
                $new .= $salt;
            }
            if ($i % 7) {
                $new .= $plainpasswd;
            }
            $new .= ($i & 1) ? $bin : $plainpasswd;
            $bin = pack('H32', md5($new));
        }
        $tmp = '';
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) {
                $j = 5;
            }
            $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
        }
        $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
        $tmp = strtr(
            strrev(substr(base64_encode($tmp), 2)),
            'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
            './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
        );

        return '$' . 'apr1' . '$' . $salt . '$' . $tmp;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter PasswordHasherTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Run the full suite**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit`
Expected: 177 tests (176 existing + the new file's 6 → 182), 0 failures. Record the new total.

- [ ] **Step 6: Commit**

```bash
git add Okay/Core/Security/PasswordHasher.php tests/Security/PasswordHasherTest.php
git commit -m "feat(security): add PasswordHasher with modern and legacy verification"
```

---

### Task 2: Manager passwords use `PasswordHasher`

**Files:**
- Modify: `Okay/Core/Managers.php:275-281` (`checkPassword`) and `:283-284` (`cryptApr1Md5`)
- Modify: `backend/Controllers/AuthAdmin.php:91-95` (rehash after successful login)
- Modify: `backend/Helpers/BackendManagersHelper.php` (password write path — locate with grep below)
- Test: `tests/Security/ManagerPasswordTest.php`

**Interfaces:**
- Consumes: `PasswordHasher::hash()`, `verify()`, `needsRehash()`, `cryptApr1Md5()` from Task 1.
- Produces:
  - `Managers::checkPassword($password, $crypt_pass): bool` (signature unchanged)
  - `Managers::hashPassword(string $password): string`
  - `Managers::needsPasswordRehash($hash): bool`
  - `Managers::cryptApr1Md5($plainpasswd, $salt = ''): string` (unchanged signature, now delegating)

- [ ] **Step 1: Find every place a manager password is written**

Run:

```bash
cd /home/sviat/projects/OkayCMS
grep -rn "cryptApr1Md5\|'password'" --include="*.php" backend/ Okay/Entities/ManagersEntity.php | grep -v vendor
```

Record the list. Every write site must go through `Managers::hashPassword()` after this task.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/ManagerPasswordTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Managers;
use PHPUnit\Framework\TestCase;

class ManagerPasswordTest extends TestCase
{
    public function testInvalidStoredHashFailsWithoutWarning()
    {
        $managers = new Managers();

        $this->assertFalse($managers->checkPassword('secret', ''));
        $this->assertFalse($managers->checkPassword('secret', 'not-a-hash'));
        $this->assertFalse($managers->checkPassword('secret', '$broken$hash'));
        $this->assertFalse($managers->checkPassword('secret', '$apr1$12345678$short'));
    }

    public function testHashPasswordProducesModernHash()
    {
        $managers = new Managers();
        $hash = $managers->hashPassword('secret');

        $this->assertMatchesRegularExpression('/^\$(argon2id|2y)\$/', $hash);
        $this->assertTrue($managers->checkPassword('secret', $hash));
        $this->assertFalse($managers->checkPassword('wrong', $hash));
        $this->assertFalse($managers->needsPasswordRehash($hash));
    }

    public function testLegacyApr1HashStillVerifiesAndIsFlaggedForRehash()
    {
        $managers = new Managers();
        $hash = $managers->cryptApr1Md5('secret', '12345678');

        $this->assertTrue($managers->checkPassword('secret', $hash));
        $this->assertTrue($managers->needsPasswordRehash($hash));
    }
}
```

Note: `Managers` has a no-argument constructor — verify with `grep -n "function __construct" Okay/Core/Managers.php`. If it takes dependencies, instantiate it through `Okay\Core\OkayContainer` in the test instead, following the pattern in `tests/Core/MoneyTest.php`.

- [ ] **Step 3: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter ManagerPasswordTest`
Expected: FAIL — `checkPassword('secret', '')` raises an undefined-array-key warning, which `convertWarningsToExceptions` turns into a test error, and `hashPassword` does not exist.

- [ ] **Step 4: Rewrite the three methods**

In `Okay/Core/Managers.php`, add the import at the top of the file next to the existing `use` statements:

```php
use Okay\Core\Security\PasswordHasher;
```

Replace lines 275-281 (the `/*Проверка пароля*/` block) with:

```php
    /*Проверка пароля*/
    public function checkPassword($password, $crypt_pass)
    {
        return $this->passwordHasher()->verify($password, $crypt_pass);
    }

    /*Хеширование нового пароля*/
    public function hashPassword($password)
    {
        return $this->passwordHasher()->hash($password);
    }

    /*Нужно ли перехешировать сохранённый пароль*/
    public function needsPasswordRehash($crypt_pass)
    {
        return $this->passwordHasher()->needsRehash($crypt_pass);
    }

    private function passwordHasher()
    {
        if ($this->passwordHasher === null) {
            $this->passwordHasher = new PasswordHasher();
        }

        return $this->passwordHasher;
    }
```

Declare the backing property next to the class's other properties (PHP 8.2+ forbids dynamic properties):

```php
    /** @var PasswordHasher|null */
    private $passwordHasher;
```

Replace the whole body of `cryptApr1Md5` with a delegation, keeping the method for BC:

```php
    /*Шифрование пароля. Оставлено для проверки существующих хешей.*/
    public function cryptApr1Md5($plainpasswd, $salt = '')
    {
        return $this->passwordHasher()->cryptApr1Md5($plainpasswd, $salt);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter ManagerPasswordTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Hash on write and rehash on login**

At every write site found in Step 1, replace `$managers->cryptApr1Md5($password)` with `$managers->hashPassword($password)`.

In `backend/Controllers/AuthAdmin.php`, inside the successful-login branch (currently line 91-95), immediately after `$_SESSION['admin'] = $manager->login;` add:

```php
                    if ($managers->needsPasswordRehash($manager->password)) {
                        $managersEntity->update((int)$manager->id, ['password' => $managers->hashPassword($pass)]);
                    }
```

Check whether `ManagersEntity::update()` re-hashes the `password` key. Run:

```bash
grep -n "password" Okay/Entities/ManagersEntity.php
```

If it does hash internally, pass the plain password and drop the `hashPassword()` call here; if it stores verbatim, keep the call as written. Do not let the password get hashed twice.

- [ ] **Step 7: Verify login end to end**

```bash
cd dev && docker compose exec php85 php -r '
require "vendor/autoload.php";
$h = new Okay\Core\Security\PasswordHasher();
var_dump($h->verify("1234", $h->cryptApr1Md5("1234", "abcdefgh")));
'
```
Expected: `bool(true)`.

Then log in at `http://okaycms.loc/admin` with `admin` / `1234`. The stored hash must be APR1 before the first login and Argon2id after it:

```bash
cd dev && docker compose exec -T mariadb mysql -uroot -proot okay -e "SELECT login, LEFT(password, 10) FROM ok_managers;"
```

- [ ] **Step 8: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Managers.php backend/Controllers/AuthAdmin.php tests/Security/ManagerPasswordTest.php
git commit -m "fix(security): hash manager passwords with password_hash and rehash legacy hashes on login"
```

---

### Task 3: Customer passwords use `PasswordHasher`

**Files:**
- Modify: `Okay/Entities/UsersEntity.php:78-100` (`add`, `update`), `:118-140` (`checkPassword`)
- Test: `tests/Security/CustomerPasswordTest.php`

**Interfaces:**
- Consumes: `PasswordHasher` from Task 1.
- Produces:
  - `UsersEntity::checkPassword(string $email, string $password): int|false` (signature unchanged)
  - `UsersEntity::updatePasswordHash(int $userId, string $hash): void` — writes a ready hash without re-hashing it

- [ ] **Step 1: Read the current implementation**

```bash
sed -n '76,145p' Okay/Entities/UsersEntity.php
```

Note that `checkPassword()` currently finds the user by matching the *encrypted* password in SQL. That cannot work with a per-row salt, so the lookup moves to email-only and verification moves into PHP.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/CustomerPasswordTest.php`. `UsersEntity` needs a database, so this test asserts on the source contract rather than executing queries — the hashing behaviour itself is already covered by `PasswordHasherTest`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class CustomerPasswordTest extends TestCase
{
    public function testEntityHashesThroughPasswordHasher()
    {
        $source = $this->source();

        $this->assertStringContainsString('use Okay\Core\Security\PasswordHasher;', $source);
        $this->assertStringNotContainsString(
            "md5(\$this->salt . \$user['password'] . md5(\$user['password']))",
            $source
        );
    }

    public function testCheckPasswordDoesNotMatchHashesInSql()
    {
        $source = $this->source();

        $this->assertStringNotContainsString("'password' => \$encPassword", $source);
        $this->assertStringContainsString('->verify($password,', $source);
    }

    public function testLegacyHashesAreRehashedAfterSuccessfulCheck()
    {
        $source = $this->source();

        $this->assertStringContainsString('needsRehash', $source);
        $this->assertStringContainsString('updatePasswordHash', $source);
    }

    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Entities/UsersEntity.php');
        $this->assertIsString($source);

        return $source;
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CustomerPasswordTest`
Expected: FAIL on the first assertion — the import is absent.

- [ ] **Step 4: Rewrite the entity's password handling**

Add to the imports at the top of `Okay/Entities/UsersEntity.php`:

```php
use Okay\Core\Security\PasswordHasher;
```

Declare the property alongside the class's other properties:

```php
    /** @var PasswordHasher|null */
    private $passwordHasher;
```

Add the accessor next to the other private methods:

```php
    private function passwordHasher()
    {
        if ($this->passwordHasher === null) {
            $this->passwordHasher = new PasswordHasher();
        }

        return $this->passwordHasher;
    }
```

In `add()` and `update()`, replace both occurrences of

```php
            $user['password'] = md5($this->salt . $user['password'] . md5($user['password']));
```

with

```php
            $user['password'] = $this->passwordHasher()->hash($user['password']);
```

Replace the body of `checkPassword()` with:

```php
    /**
     * @param string $email
     * @param string $password
     * @return int|false
     */
    public function checkPassword($email, $password)
    {
        $user = $this->cols(['id', 'password'])->findOne([
            'email' => $email,
            'limit' => 1,
        ]);

        if (empty($user) || !$this->passwordHasher()->verify($password, $user->password, $this->salt)) {
            return ExtenderFacade::execute([static::class, __FUNCTION__], false, func_get_args());
        }

        $userId = (int)$user->id;

        if ($this->passwordHasher()->needsRehash($user->password)) {
            $this->updatePasswordHash($userId, $this->passwordHasher()->hash($password));
        }

        return ExtenderFacade::execute([static::class, __FUNCTION__], $userId, func_get_args());
    }

    /**
     * Записывает готовый хеш, минуя повторное хеширование в update().
     *
     * @param int $userId
     * @param string $hash
     * @return void
     */
    public function updatePasswordHash($userId, $hash)
    {
        parent::update((int)$userId, ['password' => $hash]);
    }
```

`findOne()` with `cols(['id', 'password'])` returns an object, not a scalar — confirm against `docs/entities.md` and adjust the property access if the fork's `findOne` differs.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CustomerPasswordTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Verify against the real database**

Register a customer on `http://okaycms.loc`, then check the stored hash:

```bash
cd dev && docker compose exec -T mariadb mysql -uroot -proot okay -e "SELECT email, LEFT(password, 10) FROM ok_users ORDER BY id DESC LIMIT 3;"
```
Expected: `$argon2id` prefix for the new account. Log out and back in to confirm verification works.

Then confirm a legacy account still logs in: pick an existing seeded user, reset its hash to the old format and log in.

```bash
cd dev && docker compose exec -T mariadb mysql -uroot -proot okay -e "SELECT id, email FROM ok_users LIMIT 1;"
```

- [ ] **Step 7: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Entities/UsersEntity.php tests/Security/CustomerPasswordTest.php
git commit -m "fix(security): hash customer passwords with password_hash and migrate legacy md5 on login"
```

---

## Phase B — Recovery

### Task 4: `RecoveryToken` and `AdminRecoveryToken`

**Files:**
- Create: `Okay/Core/Security/RecoveryToken.php`
- Create: `Okay/Core/Security/AdminRecoveryToken.php`
- Test: `tests/Security/RecoveryTokenTest.php`

**Interfaces:**
- Consumes: `Okay\Core\Config::$salt` (public property — verify with `grep -n "salt" Okay/Core/Config.php`).
- Produces:
  - `RecoveryToken::create(): string` — 64 lowercase hex characters
  - `RecoveryToken::digest(string $token): string` — 32 lowercase hex characters, fits `ok_users.remind_code`
  - `RecoveryToken::isValidFormat(?string $token): bool`
  - `RecoveryToken::expiresAt(?int $now = null): string` — `Y-m-d H:i:s`, `now + RecoveryToken::TTL`
  - `RecoveryToken::TTL` = 300
  - `AdminRecoveryToken::__construct(Config $config)`
  - `AdminRecoveryToken::create(int $managerId, string $currentPasswordHash, ?int $now = null): string`
  - `AdminRecoveryToken::unverifiedManagerId(?string $token): ?int`
  - `AdminRecoveryToken::managerId(?string $token, string $currentPasswordHash, ?int $now = null): ?int`
  - `AdminRecoveryToken::TTL` = 3600

- [ ] **Step 1: Write the failing test**

Create `tests/Security/RecoveryTokenTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Config;
use Okay\Core\Security\AdminRecoveryToken;
use Okay\Core\Security\RecoveryToken;
use PHPUnit\Framework\TestCase;

class RecoveryTokenTest extends TestCase
{
    public function testCustomerTokenIsOpaqueAndDigestIsStorable()
    {
        $token = new RecoveryToken();
        $plain = $token->create();
        $digest = $token->digest($plain);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $plain);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $digest);
        $this->assertNotSame($plain, $digest);
        $this->assertSame($digest, $token->digest($plain));
    }

    public function testCustomerTokenFormatValidation()
    {
        $token = new RecoveryToken();

        $this->assertTrue($token->isValidFormat($token->create()));
        $this->assertFalse($token->isValidFormat(null));
        $this->assertFalse($token->isValidFormat(''));
        $this->assertFalse($token->isValidFormat('abc'));
        $this->assertFalse($token->isValidFormat(str_repeat('g', 64)));
    }

    public function testCustomerTokenExpiryUsesTtl()
    {
        $token = new RecoveryToken();
        $now = strtotime('2026-07-26 00:00:00');

        $this->assertSame(
            date('Y-m-d H:i:s', $now + RecoveryToken::TTL),
            $token->expiresAt($now)
        );
    }

    public function testAdminTokenCarriesManagerIdentityWithoutSession()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'current-password-hash', 1000);

        $this->assertSame(15, $token->unverifiedManagerId($code));
        $this->assertSame(15, $token->managerId($code, 'current-password-hash', 1200));
    }

    public function testAdminTokenIsInvalidatedByPasswordChange()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'old-password-hash', 1000);

        $this->assertNull($token->managerId($code, 'new-password-hash', 1200));
    }

    public function testAdminTokenExpires()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'current-password-hash', 1000);

        $this->assertNull($token->managerId($code, 'current-password-hash', 1000 + AdminRecoveryToken::TTL + 1));
    }

    public function testAdminTokenRejectsTamperedPayload()
    {
        $token = new AdminRecoveryToken($this->config());
        $code = $token->create(15, 'current-password-hash', 1000);
        $tampered = str_replace('.', 'x.', $code);

        $this->assertNull($token->managerId($tampered, 'current-password-hash', 1200));
        $this->assertNull($token->managerId(null, 'current-password-hash', 1200));
        $this->assertNull($token->managerId('garbage', 'current-password-hash', 1200));
    }

    private function config()
    {
        return new class ('', '') extends Config {
            public function __construct($configFile, $configLocalFile)
            {
                $this->salt = 'test-salt';
            }
        };
    }
}
```

If `Config::$salt` is not public, expose the key differently: add a `AdminRecoveryToken::__construct(Config $config)` that calls `$config->get('salt')` and adjust the anonymous class accordingly. Verify first with `grep -n "salt" Okay/Core/Config.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter RecoveryTokenTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write `RecoveryToken`**

Create `Okay/Core/Security/RecoveryToken.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * Токен восстановления пароля покупателя.
 *
 * В письмо уходит непрозрачный токен, в базу пишется только его digest.
 * Digest обрезан до 32 символов, потому что колонка ok_users.remind_code
 * объявлена как varchar(32); 128 бит достаточно для этой задачи.
 */
class RecoveryToken
{
    /** Время жизни токена в секундах */
    const TTL = 300;

    const TOKEN_PATTERN = '/^[0-9a-f]{64}$/';

    public function create()
    {
        return bin2hex(random_bytes(32));
    }

    public function digest($token)
    {
        return substr(hash('sha256', (string)$token), 0, 32);
    }

    public function isValidFormat($token)
    {
        if (!is_string($token)) {
            return false;
        }

        return (bool)preg_match(self::TOKEN_PATTERN, $token);
    }

    public function expiresAt($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        return date('Y-m-d H:i:s', (int)$now + self::TTL);
    }
}
```

- [ ] **Step 4: Write `AdminRecoveryToken`**

Create `Okay/Core/Security/AdminRecoveryToken.php`:

```php
<?php

namespace Okay\Core\Security;

use Okay\Core\Config;

/**
 * Токен восстановления пароля менеджера.
 *
 * Таблица ok_managers не имеет колонок под восстановление, поэтому токен
 * не хранится: он подписан HMAC-ом и привязан к текущему хешу пароля
 * менеджера. Как только пароль изменён, старый токен становится
 * недействительным — это даёт одноразовость без хранилища.
 */
class AdminRecoveryToken
{
    /** Время жизни токена в секундах */
    const TTL = 3600;

    /** @var string */
    private $key;

    public function __construct(Config $config)
    {
        $this->key = (string)$config->salt;
    }

    public function create($managerId, $currentPasswordHash, $now = null)
    {
        if ($now === null) {
            $now = time();
        }

        $managerId = (int)$managerId;
        $expires = (int)$now + self::TTL;
        $payload = $this->encode($managerId . ':' . $expires);

        return $payload . '.' . $this->sign($managerId, $expires, $currentPasswordHash);
    }

    public function unverifiedManagerId($token)
    {
        $parts = $this->parse($token);

        return $parts === null ? null : $parts['manager_id'];
    }

    public function managerId($token, $currentPasswordHash, $now = null)
    {
        if ($now === null) {
            $now = time();
        }

        $parts = $this->parse($token);
        if ($parts === null) {
            return null;
        }

        if ($parts['expires'] < (int)$now) {
            return null;
        }

        $expected = $this->sign($parts['manager_id'], $parts['expires'], $currentPasswordHash);
        if (!hash_equals($expected, $parts['signature'])) {
            return null;
        }

        return $parts['manager_id'];
    }

    private function parse($token)
    {
        if (!is_string($token) || strpos($token, '.') === false) {
            return null;
        }

        list($payload, $signature) = explode('.', $token, 2);

        $decoded = $this->decode($payload);
        if ($decoded === null || strpos($decoded, ':') === false) {
            return null;
        }

        list($managerId, $expires) = explode(':', $decoded, 2);

        if (!ctype_digit($managerId) || !ctype_digit($expires)) {
            return null;
        }

        return [
            'manager_id' => (int)$managerId,
            'expires'    => (int)$expires,
            'signature'  => $signature,
        ];
    }

    private function sign($managerId, $expires, $currentPasswordHash)
    {
        return hash_hmac(
            'sha256',
            $managerId . ':' . $expires . ':' . (string)$currentPasswordHash,
            $this->key
        );
    }

    private function encode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode($value)
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter RecoveryTokenTest`
Expected: PASS, 7 tests.

- [ ] **Step 6: Register `AdminRecoveryToken` in the container**

`AdminRecoveryToken` takes `Config`, so it must be wired. Open `Okay/Core/config/services.php`, find the block where other `Okay\Core\*` services are registered, and add an entry following the existing style. Confirm the file's format first:

```bash
grep -n "Config::class" Okay/Core/config/services.php | head -5
```

Then verify resolution:

```bash
cd dev && docker compose exec php85 php -r '
require "vendor/autoload.php";
$DI = include "Okay/Core/config/container.php";
var_dump(get_class($DI->get(Okay\Core\Security\AdminRecoveryToken::class)));
'
```
Expected: `string(46) "Okay\Core\Security\AdminRecoveryToken"`.

`RecoveryToken` has no constructor dependencies and can be instantiated directly; no wiring needed.

- [ ] **Step 7: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/RecoveryToken.php Okay/Core/Security/AdminRecoveryToken.php Okay/Core/config/services.php tests/Security/RecoveryTokenTest.php
git commit -m "feat(security): add recovery token services for customers and managers"
```

---

### Task 5: Manager recovery no longer authenticates

**Files:**
- Modify: `backend/Controllers/AuthAdmin.php:25-70`
- Modify: `backend/design/html/auth.tpl` (recovery form: drop `new_login`, add `code`)
- Test: `tests/Security/AdminRecoveryFlowTest.php`

**Interfaces:**
- Consumes: `AdminRecoveryToken` (Task 4), `Managers::hashPassword()` (Task 2).
- Produces: nothing consumed by later tasks.

**What is wrong today.** `AuthAdmin::fetch()` stores the recovery code in `$_SESSION['admin_password_recovery_code']` — the *requester's* session, not the target manager's. Line 53 then resolves the manager from `$this->request->post('new_login')`, so anyone holding a valid code can set any manager's password; when `update()` fails, line 55 creates a brand new manager with an attacker-chosen login. The code never expires and `not_admin_email` at line 32 leaks which addresses are admins.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/AdminRecoveryFlowTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class AdminRecoveryFlowTest extends TestCase
{
    public function testRecoveryIsBoundToTheTokenNotToPostedLogin()
    {
        $source = $this->source();

        $this->assertStringContainsString('AdminRecoveryToken', $source);
        $this->assertStringNotContainsString("\$this->request->post('new_login')", $source);
        $this->assertStringNotContainsString("\$managersEntity->add(['login'", $source);
        $this->assertStringNotContainsString("\$_SESSION['admin_password_recovery_code']", $source);
    }

    public function testRecoveryDoesNotEnumerateAdminEmails()
    {
        $source = $this->source();

        $this->assertStringNotContainsString("\$result->error = 'not_admin_email';", $source);
        $this->assertStringContainsString('$result->send = true;', $source);
    }

    public function testEmptyPasswordIsRejectedBeforeLogin()
    {
        $source = $this->source();

        $this->assertStringContainsString("trim(\$new_password) === ''", $source);
        $this->assertStringContainsString("\$this->design->assign('error_message', 'password_empty');", $source);

        $guard = strpos($source, "trim(\$new_password) === ''");
        $login = strpos($source, "\$_SESSION['admin'] = \$manager->login;");

        $this->assertIsInt($guard);
        $this->assertIsInt($login);
        $this->assertLessThan($login, $guard);
    }

    public function testRecoveryFormNoLongerAsksForLogin()
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/backend/design/html/auth.tpl');

        $this->assertIsString($template);
        $this->assertStringNotContainsString('name="new_login"', $template);
    }

    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/Controllers/AuthAdmin.php');
        $this->assertIsString($source);

        return $source;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter AdminRecoveryFlowTest`
Expected: FAIL on all four tests.

- [ ] **Step 3: Rewrite the recovery request branch**

In `backend/Controllers/AuthAdmin.php`, add to the imports:

```php
use Okay\Core\Security\AdminRecoveryToken;
```

Add `AdminRecoveryToken $recoveryToken` to the `fetch()` signature — the container injects it by type-hint:

```php
    public function fetch(
        Managers $managers,
        ManagersEntity $managersEntity,
        LessonsEntity $lessonsEntity,
        Notify $notify,
        Response $response,
        Validator $validator,
        AdminRecoveryToken $recoveryToken
    ) {
```

Replace lines 26-43 (the `ajax_recovery` branch) with:

```php
        /*Восстановление пароля администратора*/
        $recoveryEmail = $this->request->get('recovery_email');
        if ($this->request->get("ajax_recovery")) {
            $result = new \stdClass();
            if (!$validator->isEmail($recoveryEmail, true)) {
                $result->error = 'wrong_email';
            } else {
                $managerToRecovery = $managersEntity->findOne(['email' => $recoveryEmail]);
                if (!empty($managerToRecovery)) {
                    $code = $recoveryToken->create(
                        (int)$managerToRecovery->id,
                        (string)$managerToRecovery->password
                    );
                    $notify->emailPasswordRecoveryAdmin($managerToRecovery->email, $code);
                }

                // Ответ одинаков независимо от того, существует ли такой менеджер,
                // чтобы форма не работала как оракул для перечисления администраторов.
                $result->send = true;
            }
            $this->response->setContent(json_encode($result), RESPONSE_JSON);
            $this->response->sendContent();
            exit;
        }
```

- [ ] **Step 4: Rewrite the recovery reset branch**

Replace lines 45-69 (the `isset($_SESSION['admin_password_recovery_code'])` branch) with:

```php
        $code = (string)$this->request->get('code');
        $managerIdFromCode = $code === '' ? null : $recoveryToken->unverifiedManagerId($code);
        $managerToRecovery = $managerIdFromCode === null ? null : $managersEntity->get($managerIdFromCode);
        $recoveryIsValid = !empty($managerToRecovery)
            && $recoveryToken->managerId($code, (string)$managerToRecovery->password) !== null;

        if ($recoveryIsValid) {
            $this->design->assign("recovery_mod", true);
            $this->design->assign('recovery_code', $code);

            if ($this->request->method('post')) {
                $new_password = $this->request->post('new_password');
                $new_password_check = $this->request->post('new_password_check');

                if (trim($new_password) === '') {
                    $this->design->assign('error_message', 'password_empty');
                } elseif ($new_password !== $new_password_check) {
                    $this->design->assign('error_message', 'password_wrong');
                } else {
                    $manager = $managerToRecovery;

                    // Смена пароля инвалидирует токен: он подписан старым хешем.
                    $managersEntity->update((int)$manager->id, [
                        'password' => $managers->hashPassword($new_password),
                        'cnt_try'  => 0,
                        'last_try' => null,
                    ]);

                    session_regenerate_id(true);
                    $_SESSION['admin'] = $manager->login;

                    $allManagers = $managersEntity->order('id ASC')->find();
                    $firstManager = reset($allManagers);

                    if ($lessonsEntity->count(['not_done' => 1]) > 0 && $firstManager->id === $manager->id) {
                        $response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=LearningAdmin');
                    }
                    $response->redirectTo($this->request->getRootUrl() . '/backend/index.php');
                }
            }
        } elseif ($this->request->method('post')) {
```

Keep the existing login branch that follows unchanged apart from the rehash added in Task 2.

If `ManagersEntity::update()` hashes the `password` key itself (checked in Task 2 Step 6), pass `$new_password` here instead of `$managers->hashPassword($new_password)`.

- [ ] **Step 5: Update the recovery form**

In `backend/design/html/auth.tpl`, find the recovery form (search for `new_password`). Remove the `new_login` input entirely and add a hidden field carrying the code so the POST stays in recovery mode:

```smarty
<input type="hidden" name="code" value="{$recovery_code|escape}">
```

Confirm the form posts to a URL that preserves `?code=`; if it posts to the bare `auth` URL, the hidden field above is what keeps `$this->request->get('code')` populated — in that case change the controller to read the code from either source:

```php
        $code = (string)($this->request->get('code') ?: $this->request->post('code'));
```

- [ ] **Step 6: Add the `password_empty` translation**

```bash
grep -rn "password_wrong" backend/lang/*.php | head
```

Add a `password_empty` key next to `password_wrong` in `backend/lang/en.php`, `ru.php` and `ua.php`, matching the surrounding style.

- [ ] **Step 7: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter AdminRecoveryFlowTest`
Expected: PASS, 4 tests.

- [ ] **Step 8: Verify the flow end to end**

Request recovery for the seeded admin at `http://okaycms.loc/admin`, take the link from the mail log, open it, submit an empty password (rejected), submit mismatched passwords (rejected), then set a real one. Confirm the same link stops working after the change:

```bash
cd dev && docker compose exec php85 tail -40 dev/logs/application.error.log
```

- [ ] **Step 9: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add backend/Controllers/AuthAdmin.php backend/design/html/auth.tpl backend/lang tests/Security/AdminRecoveryFlowTest.php
git commit -m "fix(security): bind manager recovery to a signed token and stop logging in before password change"
```

---

### Task 6: Customer recovery no longer authenticates

**Files:**
- Modify: `Okay/Controllers/UserController.php:173-227`
- Modify: `design/okay_shop/html/password_remind.tpl`
- Test: `tests/Security/CustomerRecoveryFlowTest.php`

**Interfaces:**
- Consumes: `RecoveryToken` (Task 4), `UsersEntity::updatePasswordHash()` (Task 3).
- Produces: nothing consumed by later tasks.

**What is wrong today.** Line 192 sets `$_SESSION['user_id']` the moment a recovery link is opened, before any password is chosen — so the link *is* a session. `user_not_found` at line 219 leaks account existence, and the raw code is stored in `remind_code` in cleartext.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/CustomerRecoveryFlowTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class CustomerRecoveryFlowTest extends TestCase
{
    public function testRecoveryLinkDoesNotLogTheCustomerIn()
    {
        $source = $this->source();

        $this->assertStringContainsString('RecoveryToken', $source);
        $this->assertStringNotContainsString("\$_SESSION['user_id'] = \$user->id;", $source);
        $this->assertStringNotContainsString("find(['remind_code'=>\$code", $source);
    }

    public function testDigestIsStoredInsteadOfTheRawCode()
    {
        $source = $this->source();

        $this->assertStringContainsString('->digest(', $source);
        $this->assertStringNotContainsString("md5(uniqid(\$this->config->salt, true))", $source);
    }

    public function testResetRequiresNonEmptyMatchingPasswordAndConsumesToken()
    {
        $source = $this->source();

        $this->assertStringContainsString("trim(\$newPassword) === ''", $source);
        $this->assertStringContainsString('$newPassword !== $newPasswordCheck', $source);

        $consume = strpos($source, "'remind_code' => null");
        $login = strpos($source, "\$_SESSION['user_id'] = ");

        $this->assertIsInt($consume);
        $this->assertIsInt($login);
        $this->assertLessThan($login, $consume);
    }

    public function testRequestDoesNotEnumerateCustomerAccounts()
    {
        $source = $this->source();
        $template = file_get_contents(dirname(__DIR__, 2) . '/design/okay_shop/html/password_remind.tpl');

        $this->assertIsString($template);
        $this->assertStringContainsString("\$this->design->assign('email_sent', true);", $source);
        $this->assertStringNotContainsString("'error', 'user_not_found'", $source);
        $this->assertStringNotContainsString('user_not_found', $template);
    }

    private function source()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Controllers/UserController.php');
        $this->assertIsString($source);

        return $source;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CustomerRecoveryFlowTest`
Expected: FAIL on all four tests.

- [ ] **Step 3: Rewrite `passwordRemind()`**

Add to the imports of `Okay/Controllers/UserController.php`:

```php
use Okay\Core\Security\RecoveryToken;
```

Add the class constant next to the controller's other constants (or at the top of the class body if it has none):

```php
    const RECOVERY_SESSION_KEY = 'password_recovery_state';
```

Replace the whole `passwordRemind()` method with:

```php
    public function passwordRemind(UsersEntity $usersEntity, Notify $notify, UserHelper $userHelper, RecoveryToken $recoveryToken, $code = '')
    {
        // Переход по ссылке восстановления не авторизует пользователя.
        // Он только подтверждает токен и открывает форму нового пароля.
        if (!empty($code)) {
            if (!$recoveryToken->isValidFormat($code)) {
                $this->response->redirectTo(Router::generateUrl('password_remind', [], true));
            }

            $user = $usersEntity->findOne(['remind_code' => $recoveryToken->digest($code), 'limit' => 1]);

            if (empty($user) || date('Y-m-d H:i:s') > $user->remind_expire) {
                $this->design->assign('recovery_expired', true);
                $this->design->assign('noindex_follow', true);
                $this->design->assign('canonical', Router::generateUrl('password_remind', [], true));
                $this->response->setContent('password_remind.tpl');
                return;
            }

            $_SESSION[self::RECOVERY_SESSION_KEY] = [
                'user_id'  => (int)$user->id,
                'digest'   => $user->remind_code,
                'expires'  => $user->remind_expire,
            ];

            $this->response->redirectTo(Router::generateUrl('password_remind', [], true));
        }

        $state = isset($_SESSION[self::RECOVERY_SESSION_KEY]) ? $_SESSION[self::RECOVERY_SESSION_KEY] : null;

        // Установка нового пароля по подтверждённому токену
        if (!empty($state) && $this->request->method('post') && $this->request->post('reset_password')) {
            $newPassword = (string)$this->request->post('new_password');
            $newPasswordCheck = (string)$this->request->post('new_password_check');

            $user = $usersEntity->get((int)$state['user_id']);

            if (empty($user) || $user->remind_code !== $state['digest'] || date('Y-m-d H:i:s') > $state['expires']) {
                unset($_SESSION[self::RECOVERY_SESSION_KEY]);
                $this->design->assign('recovery_expired', true);
            } elseif (trim($newPassword) === '') {
                $this->design->assign('recovery_mode', true);
                $this->design->assign('error', 'password_empty');
            } elseif ($newPassword !== $newPasswordCheck) {
                $this->design->assign('recovery_mode', true);
                $this->design->assign('error', 'password_wrong');
            } else {
                // Токен гасится до повышения привилегий, поэтому повторный
                // переход по той же ссылке уже ничего не даёт.
                $usersEntity->update((int)$user->id, ['remind_code' => null, 'remind_expire' => null]);
                $usersEntity->update((int)$user->id, ['password' => $newPassword]);
                unset($_SESSION[self::RECOVERY_SESSION_KEY]);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user->id;

                $userHelper->mergeCart();
                $userHelper->mergeWishlist();
                $userHelper->mergeComparison();
                $userHelper->mergeBrowsedProducts();

                $this->response->redirectTo(Router::generateUrl('user', [], true));
            }
        } elseif (!empty($state)) {
            $this->design->assign('recovery_mode', true);
        }

        // Запрос ссылки восстановления
        if ($this->request->method('post') && $this->request->post('email')) {
            $email = $this->request->post('email');

            $user = $usersEntity->get($email);
            if (!empty($user->id)) {
                $token = $recoveryToken->create();

                $usersEntity->update($user->id, [
                    'remind_code'   => $recoveryToken->digest($token),
                    'remind_expire' => $recoveryToken->expiresAt(),
                ]);

                $notify->emailPasswordRemind($user->id, $token);
            }

            // Ответ одинаков для существующего и несуществующего адреса.
            $this->design->assign('email_sent', true);
        }

        $this->design->assign('noindex_follow', true);
        $this->design->assign('canonical', Router::generateUrl('password_remind', [], true));

        $this->response->setContent('password_remind.tpl');
    }
```

Two things to verify while editing:

- `$this->design->assign('email', $email)` is deliberately gone — echoing the submitted address back is what let the old template distinguish the two outcomes.
- `$usersEntity->update($user->id, ['password' => $newPassword])` relies on Task 3's hashing inside `update()`. Do not hash here.

- [ ] **Step 4: Update the template**

`design/okay_shop/html/password_remind.tpl` needs a reset state. Add, inside the existing form area:

```smarty
{if $recovery_expired}
    <p role="alert">{$lang->password_remind_expired}</p>
{elseif $recovery_mode}
    <form method="post">
        <input type="hidden" name="reset_password" value="1">
        <input type="password" name="new_password" autocomplete="new-password" required>
        <input type="password" name="new_password_check" autocomplete="new-password" required>
        {if $error}<p role="alert">{$lang->{"password_remind_`$error`"}}</p>{/if}
        <button type="submit">{$lang->password_remind_submit}</button>
    </form>
{elseif $email_sent}
    <p role="alert">{$lang->password_remind_email_sent_generic}</p>
{else}
    ...existing email request form...
{/if}
```

Remove any branch that renders `user_not_found`. Match the theme's existing markup and class names — read the file first and mirror its structure rather than pasting this verbatim.

Add the new language keys. Find where the theme's storefront translations live:

```bash
grep -rn "password_remind" design/okay_shop/lang/*.php 2>/dev/null | head
grep -rn "password_remind" Okay/ --include="*.php" | grep -i lang | head
```

Add `password_remind_email_sent_generic`, `password_remind_expired`, `password_remind_password_empty`, `password_remind_password_wrong` and `password_remind_submit` to every language file that already carries `password_remind` keys.

- [ ] **Step 5: Update the recovery email**

`Notify::emailPasswordRemind()` builds the link from the code. Confirm it still works with a 64-character token:

```bash
grep -n "emailPasswordRemind" -A 25 Okay/Core/Notify.php | head -40
```

The route parameter is the token itself, so no change should be needed. If the template hardcodes a length assumption, fix it.

- [ ] **Step 6: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CustomerRecoveryFlowTest`
Expected: PASS, 4 tests.

- [ ] **Step 7: Verify the flow end to end**

Request a reset for a real customer, open the emailed link, confirm you are *not* logged in, set a password, confirm you land in the account, then re-open the same link and confirm it is expired.

- [ ] **Step 8: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Controllers/UserController.php design/okay_shop tests/Security/CustomerRecoveryFlowTest.php
git commit -m "fix(security): store recovery digests and stop authenticating on recovery link"
```

---

## Phase C — Sessions and CSRF

### Task 7: `SessionNames` and split session namespaces

**Files:**
- Create: `Okay/Core/Security/SessionNames.php`
- Modify: `index.php:18-21`
- Modify: `backend/index.php:39-43`
- Modify: `backend/ajax/configure.php:2-4`
- Modify: `backend/design/js/admintooltip/admintooltip.php:15-17`
- Modify: `backend/design/js/filemanager/UploadHandler.php:237-239`
- Modify: `backend/design/js/filemanager/dialog.php:6-9`
- Modify: `backend/design/js/filemanager/config/config.php:9-11`
- Modify: `backend/files/index.php:8-11`
- Test: `tests/Security/SessionNamesTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `SessionNames::FRONTEND` = `'okay_sid'`
  - `SessionNames::BACKEND` = `'okay_admin_sid'`
  - `SessionNames::startFrontend(): void`
  - `SessionNames::startBackend(): void`
  - `SessionNames::regenerate(): void`
  - `SessionNames::cookieParams(): array`

**What is wrong today.** All nine entrypoints call `session_name(md5($_SERVER['HTTP_USER_AGENT']))`. Storefront and admin therefore share one namespace, the name is attacker-influenced through a request header, and nothing regenerates the id on a privilege change.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/SessionNamesTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\SessionNames;
use PHPUnit\Framework\TestCase;

class SessionNamesTest extends TestCase
{
    public function testFrontendAndBackendNamespacesDiffer()
    {
        $this->assertSame('okay_sid', SessionNames::FRONTEND);
        $this->assertSame('okay_admin_sid', SessionNames::BACKEND);
        $this->assertNotSame(SessionNames::FRONTEND, SessionNames::BACKEND);
    }

    public function testCookieParamsAreHardened()
    {
        $params = SessionNames::cookieParams();

        $this->assertTrue($params['httponly']);
        $this->assertSame('Lax', $params['samesite']);
        $this->assertSame('/', $params['path']);
        $this->assertArrayHasKey('secure', $params);
    }

    /**
     * @dataProvider entrypointProvider
     */
    public function testEntrypointsNoLongerDeriveSessionNameFromUserAgent($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

        $this->assertIsString($source, $file);
        $this->assertStringNotContainsString("session_name(md5(\$_SERVER['HTTP_USER_AGENT']))", $source, $file);
    }

    public function entrypointProvider()
    {
        return [
            ['index.php'],
            ['backend/index.php'],
            ['backend/ajax/configure.php'],
            ['backend/design/js/admintooltip/admintooltip.php'],
            ['backend/design/js/filemanager/UploadHandler.php'],
            ['backend/design/js/filemanager/dialog.php'],
            ['backend/design/js/filemanager/config/config.php'],
            ['backend/files/index.php'],
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SessionNamesTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/SessionNames.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * Разделяет пространства сессий витрины и админ-панели.
 *
 * Раньше имя сессии вычислялось как md5(User-Agent): оно было общим для
 * фронта и бэкенда и зависело от заголовка запроса.
 */
class SessionNames
{
    const FRONTEND = 'okay_sid';
    const BACKEND  = 'okay_admin_sid';

    public static function startFrontend()
    {
        self::start(self::FRONTEND);
    }

    public static function startBackend()
    {
        self::start(self::BACKEND);
    }

    public static function regenerate()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function cookieParams()
    {
        return [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public static function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        ) {
            return true;
        }

        return false;
    }

    private static function start($name)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($name);
        session_set_cookie_params(self::cookieParams());
        session_start();
    }
}
```

- [ ] **Step 4: Replace the storefront bootstrap**

In `index.php`, replace lines 18-21:

```php
if (!empty($_SERVER['HTTP_USER_AGENT'])) {
    session_name(md5($_SERVER['HTTP_USER_AGENT']));
}
session_start();
```

with:

```php
\Okay\Core\Security\SessionNames::startFrontend();
```

`index.php` requires `vendor/autoload.php` on line 16, before this point, so the class resolves. Verify the ordering after editing.

- [ ] **Step 5: Replace the backend bootstrap**

In `backend/index.php`, replace lines 39-43:

```php
if(!empty($_SERVER['HTTP_USER_AGENT'])){
    session_name(md5($_SERVER['HTTP_USER_AGENT']));
}
ini_set('session.gc_maxlifetime', 86400); // 86400 = 24 часа
ini_set('session.cookie_lifetime', 0); // 0 - пока браузер не закрыт
session_start();
```

with:

```php
ini_set('session.gc_maxlifetime', 86400); // 86400 = 24 часа
\Okay\Core\Security\SessionNames::startBackend();
```

`session.cookie_lifetime` is now set through `cookieParams()['lifetime'] = 0`, so the `ini_set` for it is redundant.

- [ ] **Step 6: Replace the six remaining entrypoints**

Each of these has the same three-line pattern. Replace it with `\Okay\Core\Security\SessionNames::startBackend();` and make sure `vendor/autoload.php` is required before the call — several of these `chdir()` first, so read each file's opening lines before editing:

- `backend/ajax/configure.php`
- `backend/design/js/admintooltip/admintooltip.php`
- `backend/design/js/filemanager/UploadHandler.php` (line 237, inside a method — the `@session_name(...)` call)
- `backend/design/js/filemanager/dialog.php`
- `backend/design/js/filemanager/config/config.php`
- `backend/files/index.php`

For files that run before `chdir()` to the project root, use a relative require of the autoloader consistent with what the file already does.

- [ ] **Step 7: Regenerate the session on privilege transitions**

Add `\Okay\Core\Security\SessionNames::regenerate();` immediately before each of these assignments:

```bash
grep -rn "_SESSION\['admin'\] = \|_SESSION\['user_id'\] = " --include="*.php" Okay backend | grep -v vendor
```

Tasks 5 and 6 already added `session_regenerate_id(true)` in the two recovery paths — replace those raw calls with `SessionNames::regenerate()` for consistency. Also add it to the logout paths:

```bash
grep -rn "session_destroy\|unset(\$_SESSION\['admin'\])\|unset(\$_SESSION\['user_id'\])" --include="*.php" Okay backend index.php | grep -v vendor
```

- [ ] **Step 8: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SessionNamesTest`
Expected: PASS, 11 tests (3 + 8 data rows).

- [ ] **Step 9: Verify both sessions are independent**

```bash
curl -s -i -H "Host: okaycms.loc" http://127.0.0.1/ | grep -i "set-cookie"
curl -s -i -H "Host: okaycms.loc" http://127.0.0.1/admin | grep -i "set-cookie"
```
Expected: `okay_sid` on the storefront and `okay_admin_sid` on the admin, both with `HttpOnly` and `SameSite=Lax`.

Then log into the admin panel and confirm the storefront still shows you as an anonymous visitor.

- [ ] **Step 10: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/SessionNames.php index.php backend tests/Security/SessionNamesTest.php
git commit -m "fix(security): split storefront and admin session namespaces and regenerate on login"
```

---

### Task 8: Admin CSRF token stops being the session id

**Files:**
- Create: `Okay/Core/Security/AdminCsrfToken.php`
- Modify: `Okay/Core/Request.php:375-384`
- Modify: `backend/index.php:44` (`$_SESSION['id'] = session_id();`) and `:234-238` (guard position)
- Test: `tests/Security/AdminCsrfTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `AdminCsrfToken::get(): string` — 64 hex characters, created once per session
  - `AdminCsrfToken::check(?string $token): bool` — constant-time
  - `AdminCsrfToken::rotate(): string`
  - `Request::checkSession(): bool` (signature unchanged)

**What is wrong today.** `$_SESSION['id'] = session_id()` is rendered into ~30 admin templates as `<input name="session_id" value="{$smarty.session.id}">`, so the session identifier is written into every page. `Request::checkSession()` compares that value with `session_id()` using `!=`. Worse, `backend/index.php` calls the guard at line 235 — *after* the controller has already run at line 207 — so it protects nothing.

Keeping the field name `session_id` and the template expression `{$smarty.session.id}` unchanged means no template edits: only the *value* behind them changes.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/AdminCsrfTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\AdminCsrfToken;
use PHPUnit\Framework\TestCase;

class AdminCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    public function testTokenIsNotTheSessionIdAndIsStable()
    {
        $token = AdminCsrfToken::get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertNotSame(session_id(), $token);
        $this->assertSame($token, AdminCsrfToken::get());
    }

    public function testCheckFailsClosed()
    {
        AdminCsrfToken::get();

        $this->assertFalse(AdminCsrfToken::check(null));
        $this->assertFalse(AdminCsrfToken::check(''));
        $this->assertFalse(AdminCsrfToken::check('wrong'));
    }

    public function testRotateInvalidatesThePreviousToken()
    {
        $old = AdminCsrfToken::get();
        $new = AdminCsrfToken::rotate();

        $this->assertNotSame($old, $new);
        $this->assertFalse(AdminCsrfToken::check($old));
        $this->assertTrue(AdminCsrfToken::check($new));
    }

    public function testGuardRunsBeforeControllerDispatch()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/index.php');
        $this->assertIsString($source);

        $guard = strpos($source, '$request->checkSession()');
        $dispatch = strpos($source, "call_user_func_array([\$backend, \$methodName]");

        $this->assertIsInt($guard);
        $this->assertIsInt($dispatch);
        $this->assertLessThan($dispatch, $guard);
    }

    public function testSessionIdIsNoLongerPublishedAsTheToken()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/index.php');
        $this->assertIsString($source);

        $this->assertStringNotContainsString("\$_SESSION['id'] = session_id();", $source);
        $this->assertStringContainsString('AdminCsrfToken::get()', $source);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter AdminCsrfTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/AdminCsrfToken.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * CSRF-токен админ-панели.
 *
 * Хранится в $_SESSION['id'], потому что шаблоны уже печатают это значение
 * как {$smarty.session.id} в поле session_id. Значение больше не совпадает
 * с идентификатором сессии, поэтому сам идентификатор не попадает в HTML.
 */
class AdminCsrfToken
{
    const SESSION_KEY = 'id';

    public static function get()
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !self::isWellFormed($_SESSION[self::SESSION_KEY])) {
            return self::rotate();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function rotate()
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    public static function check($token)
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string)$_SESSION[self::SESSION_KEY], $token);
    }

    private static function isWellFormed($token)
    {
        return is_string($token) && (bool)preg_match('/^[0-9a-f]{64}$/', $token);
    }
}
```

- [ ] **Step 4: Rewrite `Request::checkSession()`**

Add the import to `Okay/Core/Request.php`:

```php
use Okay\Core\Security\AdminCsrfToken;
```

Replace lines 375-384 with:

```php
    public function checkSession()
    {
        if (empty($_POST)) {
            return true;
        }

        $token = isset($_POST['session_id']) ? $_POST['session_id'] : null;

        if (!AdminCsrfToken::check($token)) {
            $_POST = [];
            return false;
        }

        return true;
    }
```

`unset($_POST)` in the old code removed the superglobal entirely, which later code then re-read as an undefined variable. Assigning `[]` keeps the variable defined and empty.

- [ ] **Step 5: Seed the token and move the guard**

In `backend/index.php`, replace line 44:

```php
$_SESSION['id'] = session_id();
```

with:

```php
$_SESSION['id'] = \Okay\Core\Security\AdminCsrfToken::get();
```

Then move the guard. Delete the block at lines 234-238:

```php
// Проверка сессии для защиты от xss
if (!$request->checkSession()) {
    unset($_POST);
    trigger_error('Session expired', E_USER_WARNING);
}
```

and insert this immediately before `$backend = new $controllerName($manager, $backendControllerName, $methodName);` (line 200):

```php
// CSRF-проверка выполняется до вызова контроллера, иначе мутация уже произошла.
// AuthAdmin исключён: форма входа рендерится до появления сессии менеджера.
if ($backendControllerName !== 'AuthAdmin' && !$request->checkSession()) {
    $response->setStatusCode(403);
    $response->setContent('Session expired');
    $response->sendContent();
    exit;
}
```

Verify the exact variable names at that point in the file before pasting — `$backendControllerName` is assigned at line 126 and reassigned inside the module branches, so read lines 160-200 first and use whichever variable holds the resolved controller name at line 200.

- [ ] **Step 6: Check the ajax entrypoints still work**

Five backend ajax scripts call `checkSession()` themselves:

```bash
grep -rn "checkSession" backend/ajax/
```

They already run the guard before doing work, so they need no change — but confirm each one sends `session_id` from its JavaScript caller. `backend/design/html/index.tpl:944` reads `session_id = '{$smarty.session.id}'`, which now carries the new token automatically.

- [ ] **Step 7: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter AdminCsrfTest`
Expected: PASS, 5 tests.

- [ ] **Step 8: Verify in the browser**

Log in, then exercise the admin panel: save a product, save a category, reorder something via drag-and-drop, use the quick-edit inline save, and save a template file in the design editor. All must succeed. Then confirm the token is not the session id:

```bash
curl -s -i -H "Host: okaycms.loc" http://127.0.0.1/admin | grep -io "okay_admin_sid=[^;]*"
```

and compare against the `session_id` hidden field in a logged-in page's HTML — they must differ.

- [ ] **Step 9: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/AdminCsrfToken.php Okay/Core/Request.php backend/index.php tests/Security/AdminCsrfTest.php
git commit -m "fix(security): replace session-id CSRF token and run the admin guard before dispatch"
```

---

### Task 9: `CustomerCsrfToken`

**Files:**
- Create: `Okay/Core/Security/CustomerCsrfToken.php`
- Test: `tests/Security/CustomerCsrfTokenTest.php`

**Interfaces:**
- Consumes: `SessionNames::isHttps()` (Task 7).
- Produces:
  - `CustomerCsrfToken::SESSION_KEY` = `'customer_csrf_token'`
  - `CustomerCsrfToken::COOKIE_NAME` = `'okay_csrf'`
  - `CustomerCsrfToken::get(): string` — 64 hex characters
  - `CustomerCsrfToken::check(?string $token): bool`
  - `CustomerCsrfToken::rotate(): string`

- [ ] **Step 1: Write the failing test**

Create `tests/Security/CustomerCsrfTokenTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\CustomerCsrfToken;
use PHPUnit\Framework\TestCase;

class CustomerCsrfTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_COOKIE = [];
    }

    public function testTokenIsOpaqueAndStable()
    {
        $token = CustomerCsrfToken::get();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertNotSame(session_id(), $token);
        $this->assertSame($token, CustomerCsrfToken::get());
        $this->assertTrue(CustomerCsrfToken::check($token));
    }

    public function testCheckFailsClosed()
    {
        CustomerCsrfToken::get();

        $this->assertFalse(CustomerCsrfToken::check(null));
        $this->assertFalse(CustomerCsrfToken::check(''));
        $this->assertFalse(CustomerCsrfToken::check('wrong'));
    }

    public function testTokenSurvivesSessionResetViaCookie()
    {
        $token = CustomerCsrfToken::get();
        $_SESSION = [];

        $this->assertTrue(CustomerCsrfToken::check($token));
    }

    public function testRotateInvalidatesThePreviousToken()
    {
        $old = CustomerCsrfToken::get();
        $new = CustomerCsrfToken::rotate();

        $this->assertNotSame($old, $new);
        $this->assertTrue(CustomerCsrfToken::check($new));
    }
}
```

Note `testTokenSurvivesSessionResetViaCookie`: `get()` writes the token into `$_COOKIE` as well as `$_SESSION`, so the check still succeeds after the session array is cleared. This matters in production because Task 7 changes the session name, which drops every existing session on deploy — without the cookie fallback, the first POST after the upgrade would 403.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CustomerCsrfTokenTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/CustomerCsrfToken.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * CSRF-токен витрины.
 *
 * Значение дублируется в SameSite=Lax куку: при смене имени сессии
 * (см. SessionNames) серверное состояние теряется, а форма, отрендеренная
 * до обновления, должна остаться рабочей.
 */
class CustomerCsrfToken
{
    const SESSION_KEY = 'customer_csrf_token';
    const COOKIE_NAME = 'okay_csrf';

    public static function get()
    {
        if (!empty($_SESSION[self::SESSION_KEY]) && self::isWellFormed($_SESSION[self::SESSION_KEY])) {
            return $_SESSION[self::SESSION_KEY];
        }

        if (!empty($_COOKIE[self::COOKIE_NAME]) && self::isWellFormed($_COOKIE[self::COOKIE_NAME])) {
            $_SESSION[self::SESSION_KEY] = $_COOKIE[self::COOKIE_NAME];

            return $_SESSION[self::SESSION_KEY];
        }

        return self::rotate();
    }

    public static function rotate()
    {
        $token = bin2hex(random_bytes(32));

        $_SESSION[self::SESSION_KEY] = $token;
        $_COOKIE[self::COOKIE_NAME] = $token;

        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $token, [
                'expires'  => 0,
                'path'     => '/',
                'secure'   => SessionNames::isHttps(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return $token;
    }

    public static function check($token)
    {
        if (!is_string($token) || !self::isWellFormed($token)) {
            return false;
        }

        foreach ([self::sessionToken(), self::cookieToken()] as $known) {
            if ($known !== null && hash_equals($known, $token)) {
                return true;
            }
        }

        return false;
    }

    private static function sessionToken()
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !self::isWellFormed($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        return (string)$_SESSION[self::SESSION_KEY];
    }

    private static function cookieToken()
    {
        if (empty($_COOKIE[self::COOKIE_NAME]) || !self::isWellFormed($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }

        return (string)$_COOKIE[self::COOKIE_NAME];
    }

    private static function isWellFormed($token)
    {
        return is_string($token) && (bool)preg_match('/^[0-9a-f]{64}$/', $token);
    }
}
```

`httponly` is deliberately `false`: the storefront JavaScript reads this cookie to attach the token to AJAX mutations. The cookie is not a credential — it only has to match what the server already knows.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CustomerCsrfTokenTest`
Expected: PASS, 4 tests.

- [ ] **Step 5: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/CustomerCsrfToken.php tests/Security/CustomerCsrfTokenTest.php
git commit -m "feat(security): add customer CSRF token with cookie fallback"
```

---

### Task 10: CSRF guard on storefront mutations

**Files:**
- Modify: `Okay/Controllers/AbstractController.php` (add the guard helper)
- Modify: `Okay/Controllers/CartController.php:222,228` (`removeItem`, `addItem`), `:155` (`cartAjax`)
- Modify: `Okay/Controllers/WishListController.php:24` (`ajaxUpdate`)
- Modify: `Okay/Controllers/ComparisonController.php:20` (`ajaxUpdate`)
- Modify: `Okay/Controllers/SubscribeController.php:16` (`ajaxSubscribe`)
- Modify: `Okay/Controllers/FeedbackController.php:15` (`render`, POST branch only)
- Modify: `Okay/Helpers/CommentsHelper.php:179` (comment submission)
- Modify: `Okay/Core/config/routes.php:73-92` (cart add/remove become POST-only)
- Test: `tests/Security/StorefrontCsrfGuardTest.php`

**Interfaces:**
- Consumes: `CustomerCsrfToken` (Task 9).
- Produces:
  - `AbstractController::requireCustomerCsrf(): void` — sends 405 for a non-POST request, 403 for a bad token, and `exit`s in both cases
  - `AbstractController::customerCsrfToken(): string` — assigned to Smarty as `customer_csrf_token`

**What is wrong today.** `/cart/{variantId}` and `/cart/remove/{variantId}` are plain GET routes: any third-party page can add to or empty a visitor's cart with an `<img>` tag. `cartAjax`, `wishlist_ajax`, `comparison_ajax`, `ajax_subscribe`, the feedback form and comment submission accept cross-site POSTs unchecked.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/StorefrontCsrfGuardTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class StorefrontCsrfGuardTest extends TestCase
{
    /**
     * @dataProvider guardedControllerProvider
     */
    public function testMutationControllersInvokeTheGuard($file, $expectedCalls)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

        $this->assertIsString($source, $file);
        $this->assertSame(
            $expectedCalls,
            substr_count($source, 'requireCustomerCsrf()'),
            $file
        );
    }

    public function guardedControllerProvider()
    {
        return [
            ['Okay/Controllers/CartController.php', 3],
            ['Okay/Controllers/WishListController.php', 1],
            ['Okay/Controllers/ComparisonController.php', 1],
            ['Okay/Controllers/SubscribeController.php', 1],
            ['Okay/Controllers/FeedbackController.php', 1],
        ];
    }

    public function testAbstractControllerExposesTheGuardAndTheToken()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Controllers/AbstractController.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('function requireCustomerCsrf', $source);
        $this->assertStringContainsString('function customerCsrfToken', $source);
        $this->assertStringContainsString("assign('customer_csrf_token'", $source);
        $this->assertStringContainsString('setStatusCode(405)', $source);
        $this->assertStringContainsString('setStatusCode(403)', $source);
    }

    public function testCartMutationRoutesArePostOnly()
    {
        $routes = include dirname(__DIR__, 2) . '/Okay/Core/config/routes.php';

        $this->assertIsArray($routes);
        $this->assertSame('POST', $routes['cart_add_item']['params']['method_request'] ?? null);
        $this->assertSame('POST', $routes['cart_remove_item']['params']['method_request'] ?? null);
    }

    public function testThemeFormsCarryTheToken()
    {
        $root = dirname(__DIR__, 2) . '/design/okay_shop/html/';

        foreach (['cart.tpl', 'product.tpl'] as $template) {
            $source = file_get_contents($root . $template);
            $this->assertIsString($source, $template);
            $this->assertStringContainsString('customer_csrf_token', $source, $template);
        }
    }
}
```

Before running this, confirm how the router expresses an HTTP-method constraint:

```bash
grep -rn "method_request\|REQUEST_METHOD\|'POST'" Okay/Core/Router.php Okay/Core/Routes/ | head -20
```

If the fork's router has no method constraint, drop `testCartMutationRoutesArePostOnly` and enforce the method inside `requireCustomerCsrf()` alone — the guard already returns 405 for non-POST, which achieves the same result.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter StorefrontCsrfGuardTest`
Expected: FAIL on every test.

- [ ] **Step 3: Add the guard to `AbstractController`**

Add the import to `Okay/Controllers/AbstractController.php`:

```php
use Okay\Core\Security\CustomerCsrfToken;
```

Add these two methods to the class:

```php
    /**
     * Токен для форм витрины. Шаблоны печатают его как
     * <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
     *
     * @return string
     */
    protected function customerCsrfToken()
    {
        return CustomerCsrfToken::get();
    }

    /**
     * Пропускает только POST с корректным токеном.
     * Мутирующие эндпоинты витрины обязаны вызывать этот метод первым.
     *
     * @return void
     */
    protected function requireCustomerCsrf()
    {
        if (!$this->request->method('post')) {
            $this->response->setStatusCode(405);
            $this->response->setContent('Method Not Allowed', RESPONSE_TEXT);
            $this->response->sendContent();
            exit;
        }

        $token = $this->request->post('customer_csrf_token');

        if (!CustomerCsrfToken::check($token)) {
            $this->response->setStatusCode(403);
            $this->response->setContent('Forbidden', RESPONSE_TEXT);
            $this->response->sendContent();
            exit;
        }
    }
```

In whatever method `AbstractController` already uses to seed common Smarty variables (search for an existing `$this->design->assign(` in the constructor or an `init`-style method), add:

```php
        $this->design->assign('customer_csrf_token', CustomerCsrfToken::get());
```

- [ ] **Step 4: Guard the cart**

In `Okay/Controllers/CartController.php`, make `requireCustomerCsrf()` the first statement of `cartAjax()`, `removeItem()` and `addItem()`:

```php
    public function removeItem(Cart $cart, $variantId)
    {
        $this->requireCustomerCsrf();

        // ...existing body
    }
```

Do the same in `addItem()` and `cartAjax()`.

- [ ] **Step 5: Guard the remaining endpoints**

Add `$this->requireCustomerCsrf();` as the first statement of:

- `Okay/Controllers/WishListController.php::ajaxUpdate()`
- `Okay/Controllers/ComparisonController.php::ajaxUpdate()`
- `Okay/Controllers/SubscribeController.php::ajaxSubscribe()`
- `Okay/Controllers/FeedbackController.php::render()` — **only inside the POST branch**, since this method also renders the empty form on GET. Read the method first and place the call immediately after the `if ($this->request->method('post'))` check.

For comments, `Okay/Helpers/CommentsHelper.php:179` handles submission through `$this->commentsRequest->postComment()`. A helper has no `$this->request`/`$this->response`, so guard it at the controller that calls the helper instead:

```bash
grep -rn "CommentsHelper" --include="*.php" Okay/Controllers/ | head
```

Add the guard in each calling controller's POST branch, following the FeedbackController pattern.

- [ ] **Step 6: Make the cart routes POST-only**

In `Okay/Core/config/routes.php`, add the method constraint to `cart_add_item` and `cart_remove_item` using whatever key the router supports (confirmed in Step 1). If the router has no such key, skip this step — the guard covers it.

- [ ] **Step 7: Add the token to theme forms and AJAX**

Every form and AJAX call that hits a guarded endpoint needs the token.

Find the forms:

```bash
grep -rln "cart_add_item\|cart_remove_item\|cart_ajax\|wishlist.php\|comparison.php\|ajax/subscribe" design/okay_shop/html/ design/okay_shop/js/
```

In each `.tpl` form add:

```smarty
<input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
```

Where a cart action is currently an `<a href>`, convert it to a small POST form or to a JavaScript-driven POST — the theme's existing add-to-cart button is already a form in most themes, so check before rewriting markup.

For AJAX, the token is readable from the `okay_csrf` cookie set in Task 9. Add a single helper near the top of the theme's main script and use it in every mutation request:

```javascript
function okayCsrfToken() {
    var match = document.cookie.match(/(?:^|;\s*)okay_csrf=([0-9a-f]{64})/);
    return match ? match[1] : '';
}
```

Then include `customer_csrf_token: okayCsrfToken()` in the data of each mutating `$.post` / `$.ajax` call.

- [ ] **Step 8: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter StorefrontCsrfGuardTest`
Expected: PASS.

- [ ] **Step 9: Verify the guard actually blocks**

```bash
# GET against a mutation must be refused
curl -s -o /dev/null -w "add via GET: %{http_code}\n" -H "Host: okaycms.loc" http://127.0.0.1/cart/1

# POST without a token must be refused
curl -s -o /dev/null -w "POST no token: %{http_code}\n" -X POST -H "Host: okaycms.loc" http://127.0.0.1/ajax/wishlist.php
```
Expected: `405` and `403`.

Then, in a browser: add a product to the cart, change its quantity, remove it, apply a coupon, add to wishlist, add to comparison, subscribe, submit feedback and post a comment. Every one must still work.

- [ ] **Step 10: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Controllers Okay/Core/config/routes.php Okay/Helpers/CommentsHelper.php design/okay_shop tests/Security/StorefrontCsrfGuardTest.php
git commit -m "fix(security): require POST and a CSRF token for storefront mutations"
```

---

## Phase D — Filemanager

### Task 11: `PathResolver`

**Files:**
- Create: `Okay/Core/Security/Filemanager/PathResolver.php`
- Test: `tests/Security/FilemanagerPathResolverTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `PathResolver::__construct(string $rootDir)`
  - `PathResolver::resolve(?string $relativePath): ?string` — absolute path inside the root, or `null`
  - `PathResolver::root(): string`

- [ ] **Step 1: Write the failing test**

Create `tests/Security/FilemanagerPathResolverTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\Filemanager\PathResolver;
use PHPUnit\Framework\TestCase;

class FilemanagerPathResolverTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/okay-path-resolver-' . getmypid();
        @mkdir($this->root . '/nested/deep', 0777, true);
        file_put_contents($this->root . '/nested/deep/file.txt', 'x');
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/nested/deep/file.txt');
        @rmdir($this->root . '/nested/deep');
        @rmdir($this->root . '/nested');
        @rmdir($this->root);

        parent::tearDown();
    }

    public function testResolvesPathsInsideTheRoot()
    {
        $resolver = new PathResolver($this->root);

        $this->assertSame(realpath($this->root . '/nested/deep/file.txt'), $resolver->resolve('nested/deep/file.txt'));
        $this->assertSame(realpath($this->root . '/nested'), $resolver->resolve('nested'));
        $this->assertSame(realpath($this->root), $resolver->resolve(''));
        $this->assertSame(realpath($this->root), $resolver->resolve('.'));
    }

    /**
     * @dataProvider rejectedPathProvider
     */
    public function testRejectsUnsafePaths($path)
    {
        $resolver = new PathResolver($this->root);

        $this->assertNull($resolver->resolve($path));
    }

    public function rejectedPathProvider()
    {
        return [
            'traversal'          => ['../../etc/passwd'],
            'nested traversal'   => ['nested/../../../etc/passwd'],
            'absolute unix'      => ['/etc/passwd'],
            'absolute windows'   => ['C:\\Windows\\win.ini'],
            'backslash'          => ['nested\\..\\..\\etc'],
            'scheme http'        => ['http://example.com/x'],
            'scheme php'         => ['php://input'],
            'scheme data'        => ['data:text/plain,x'],
            'nul byte'           => ["nested/deep/file.txt\0.png"],
            'null input'         => [null],
        ];
    }

    public function testRootIsNormalised()
    {
        $resolver = new PathResolver($this->root . '/');

        $this->assertSame(realpath($this->root), $resolver->root());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter FilemanagerPathResolverTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/Filemanager/PathResolver.php`:

```php
<?php

namespace Okay\Core\Security\Filemanager;

/**
 * Приводит путь из запроса к абсолютному пути внутри разрешённого корня.
 *
 * Возвращает null для traversal, абсолютных путей, схем и NUL-байтов —
 * вызывающий код обязан считать null отказом, а не "путь не найден".
 */
class PathResolver
{
    /** @var string */
    private $root;

    public function __construct($rootDir)
    {
        $resolved = realpath((string)$rootDir);

        if ($resolved === false) {
            throw new \InvalidArgumentException('Filemanager root does not exist: ' . $rootDir);
        }

        $this->root = $resolved;
    }

    public function root()
    {
        return $this->root;
    }

    public function resolve($relativePath)
    {
        if (!is_string($relativePath)) {
            return null;
        }

        if (strpos($relativePath, "\0") !== false) {
            return null;
        }

        if (strpos($relativePath, '\\') !== false) {
            return null;
        }

        // Схемы (http://, php://, data:) и абсолютные пути недопустимы.
        if (preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*:~', $relativePath)) {
            return null;
        }

        if ($relativePath !== '' && $relativePath[0] === '/') {
            return null;
        }

        $candidate = $this->root;
        if ($relativePath !== '' && $relativePath !== '.') {
            $candidate .= '/' . $relativePath;
        }

        $resolved = realpath($candidate);
        if ($resolved === false) {
            return null;
        }

        if ($resolved !== $this->root && strpos($resolved, $this->root . '/') !== 0) {
            return null;
        }

        return $resolved;
    }
}
```

`realpath()` resolves symlinks, so the containment check also blocks a symlink inside the upload root that points outside it.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter FilemanagerPathResolverTest`
Expected: PASS, 12 tests (2 + 10 data rows).

- [ ] **Step 5: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/Filemanager/PathResolver.php tests/Security/FilemanagerPathResolverTest.php
git commit -m "feat(security): add filemanager path resolver"
```

---

### Task 12: `SvgSanitizer`

**Files:**
- Create: `Okay/Core/Security/SvgSanitizer.php`
- Test: `tests/Security/SvgSanitizerTest.php`

**Interfaces:**
- Consumes: nothing. Requires `ext-dom` (bundled with PHP by default — confirm with `docker compose exec php85 php -m | grep -i dom`).
- Produces:
  - `SvgSanitizer::sanitize(string $svg): ?string` — rewritten SVG, or `null` when the input is not parsable SVG
  - `SvgSanitizer::sanitizeFile(string $path): bool` — sanitizes in place, returns `false` and leaves the file untouched on rejection

- [ ] **Step 1: Add the extension requirement**

`composer.json` already requires `ext-SimpleXML` and `ext-XMLReader`. Add `"ext-dom": "*"` to the `require` block, next to them.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/SvgSanitizerTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\SvgSanitizer;
use PHPUnit\Framework\TestCase;

class SvgSanitizerTest extends TestCase
{
    public function testBenignShapesSurvive()
    {
        $sanitizer = new SvgSanitizer();
        $result = $sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            . '<path d="M0 0 L10 10" fill="#ff0000"/><circle cx="5" cy="5" r="4"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringContainsString('<path', $result);
        $this->assertStringContainsString('d="M0 0 L10 10"', $result);
        $this->assertStringContainsString('<circle', $result);
        $this->assertStringContainsString('viewBox="0 0 10 10"', $result);
    }

    public function testScriptElementsAreRemoved()
    {
        $sanitizer = new SvgSanitizer();
        $result = $sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('alert', $result);
        $this->assertStringContainsString('<rect', $result);
    }

    public function testEventHandlerAttributesAreRemoved()
    {
        $sanitizer = new SvgSanitizer();
        $result = $sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1" onload="alert(1)" onclick="alert(2)"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('onload', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testDangerousUrlSchemesAreRemoved()
    {
        $sanitizer = new SvgSanitizer();
        $result = $sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<a xlink:href="javascript:alert(1)"><rect width="1" height="1"/></a>'
            . '<image href="data:text/html,<script>alert(1)</script>"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('javascript:', $result);
        $this->assertStringNotContainsString('data:text/html', $result);
    }

    public function testForeignObjectAndUseAreRemoved()
    {
        $sanitizer = new SvgSanitizer();
        $result = $sanitizer->sanitize(
            '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe src="x"></iframe></foreignObject>'
            . '<rect width="1" height="1"/></svg>'
        );

        $this->assertIsString($result);
        $this->assertStringNotContainsString('foreignObject', $result);
        $this->assertStringNotContainsString('iframe', $result);
    }

    public function testExternalEntitiesAreNotResolved()
    {
        $sanitizer = new SvgSanitizer();
        $result = $sanitizer->sanitize(
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
            . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>'
        );

        if ($result !== null) {
            $this->assertStringNotContainsString('root:', $result);
            $this->assertStringNotContainsString('/bin/', $result);
        } else {
            $this->assertNull($result);
        }
    }

    /**
     * @dataProvider rejectedInputProvider
     */
    public function testNonSvgInputIsRejected($input)
    {
        $sanitizer = new SvgSanitizer();

        $this->assertNull($sanitizer->sanitize($input));
    }

    public function rejectedInputProvider()
    {
        return [
            'empty'      => [''],
            'plain text' => ['not an svg at all'],
            'html'       => ['<html><body>hi</body></html>'],
            'broken xml' => ['<svg><rect'],
        ];
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SvgSanitizerTest`
Expected: FAIL — class not found.

- [ ] **Step 4: Write the implementation**

Create `Okay/Core/Security/SvgSanitizer.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * Переписывает SVG по белому списку элементов и атрибутов.
 *
 * Загруженный SVG исполняется браузером как документ, поэтому он проходит
 * через санитайзер до записи на диск. Всё, чего нет в списках ниже,
 * удаляется; непарсящийся вход отклоняется целиком.
 */
class SvgSanitizer
{
    /** @var string[] */
    private static $allowedElements = [
        'svg', 'g', 'defs', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern',
        'clipPath', 'mask', 'symbol', 'marker',
    ];

    /** @var string[] */
    private static $allowedAttributes = [
        'id', 'class', 'style', 'transform',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'width', 'height', 'd', 'points', 'viewBox', 'preserveAspectRatio',
        'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset',
        'opacity', 'offset', 'stop-color', 'stop-opacity',
        'gradientUnits', 'gradientTransform', 'spreadMethod',
        'clip-path', 'clip-rule', 'mask',
        'font-family', 'font-size', 'font-weight', 'font-style',
        'text-anchor', 'dominant-baseline', 'letter-spacing',
        'xmlns', 'xmlns:xlink', 'version',
    ];

    public function sanitize($svg)
    {
        $svg = (string)$svg;

        if (trim($svg) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = false;

        // LIBXML_NONET и отсутствие LIBXML_NOENT не дают резолвить внешние сущности.
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false || $document->documentElement === null) {
            return null;
        }

        if (strtolower($document->documentElement->localName) !== 'svg') {
            return null;
        }

        $this->stripDoctype($document);
        $this->cleanElement($document->documentElement);

        $result = $document->saveXML($document->documentElement);

        return $result === false ? null : $result;
    }

    public function sanitizeFile($path)
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        $clean = $this->sanitize($contents);
        if ($clean === null) {
            return false;
        }

        return file_put_contents($path, $clean) !== false;
    }

    private function stripDoctype(\DOMDocument $document)
    {
        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node->nodeType === XML_DOCUMENT_TYPE_NODE) {
                $document->removeChild($node);
            }
        }
    }

    private function cleanElement(\DOMElement $element)
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                if (!in_array($child->localName, self::$allowedElements, true)) {
                    $element->removeChild($child);
                    continue;
                }

                $this->cleanElement($child);
                continue;
            }

            if ($child->nodeType === XML_PI_NODE
                || $child->nodeType === XML_COMMENT_NODE
                || $child->nodeType === XML_ENTITY_REF_NODE
            ) {
                $element->removeChild($child);
            }
        }

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = $attribute->nodeName;

            if (stripos($name, 'on') === 0) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if (in_array($name, ['href', 'xlink:href', 'src'], true)) {
                if (!$this->isSafeUrl($attribute->nodeValue)) {
                    $element->removeAttributeNode($attribute);
                }
                continue;
            }

            if (!in_array($name, self::$allowedAttributes, true)) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            if ($name === 'style' && $this->styleIsDangerous($attribute->nodeValue)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private function isSafeUrl($value)
    {
        $value = trim((string)$value);

        if ($value === '') {
            return false;
        }

        if ($value[0] === '#' || $value[0] === '/') {
            return true;
        }

        if (!preg_match('~^([a-zA-Z][a-zA-Z0-9+.-]*):~', $value, $matches)) {
            // Относительный путь без схемы.
            return true;
        }

        return in_array(strtolower($matches[1]), ['http', 'https'], true);
    }

    private function styleIsDangerous($value)
    {
        $value = strtolower((string)$value);

        foreach (['javascript:', 'expression(', 'url(', '@import', 'behavior:'] as $needle) {
            if (strpos($value, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
```

`use` and `image` are deliberately absent from the element allowlist: both can reference external documents, and neither is needed for the icon-style SVGs a shop uploads.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SvgSanitizerTest`
Expected: PASS, 10 tests (6 + 4 data rows).

- [ ] **Step 6: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/SvgSanitizer.php composer.json tests/Security/SvgSanitizerTest.php
git commit -m "feat(security): add allowlist-based SVG sanitizer"
```

---

### Task 13: Filemanager access guard and hardened operations

**Files:**
- Create: `Okay/Core/Security/Filemanager/AccessGuard.php`
- Create: `backend/design/js/filemanager/include/okay_access.php`
- Modify: `backend/design/js/filemanager/dialog.php`
- Modify: `backend/design/js/filemanager/upload.php`
- Modify: `backend/design/js/filemanager/execute.php`
- Modify: `backend/design/js/filemanager/ajax_calls.php`
- Modify: `backend/design/js/filemanager/force_download.php`
- Modify: `backend/design/js/filemanager/UploadHandler.php`
- Test: `tests/Security/FilemanagerAccessTest.php`

**Interfaces:**
- Consumes: `PathResolver` (Task 11), `SvgSanitizer` (Task 12).
- Produces:
  - `AccessGuard::__construct(\Okay\Core\EntityFactory $entityFactory)`
  - `AccessGuard::currentManager(): ?object` — the manager behind `$_SESSION['admin']`, or `null`
  - `AccessGuard::requireManager(string $permission = null): object` — sends 403 and exits when unauthorized

**What is wrong today.** Four of the five entrypoints check only `$_SESSION['RF']["verify"] != "RESPONSIVEfilemanager"` — a flag, not an identity. `upload.php:75` does `curl_init($url)` on a request-supplied URL, turning the admin filemanager into an SSRF primitive. `svg` sits in `ext_img` with no sanitization.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/FilemanagerAccessTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class FilemanagerAccessTest extends TestCase
{
    /**
     * @dataProvider guardedEntrypointProvider
     */
    public function testEntrypointRequiresAnAuthenticatedManager($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

        $this->assertIsString($source, $file);
        $this->assertStringContainsString('okay_access.php', $source, $file);
    }

    public function guardedEntrypointProvider()
    {
        return [
            'dialog'   => ['backend/design/js/filemanager/dialog.php'],
            'upload'   => ['backend/design/js/filemanager/upload.php'],
            'execute'  => ['backend/design/js/filemanager/execute.php'],
            'ajax'     => ['backend/design/js/filemanager/ajax_calls.php'],
            'download' => ['backend/design/js/filemanager/force_download.php'],
        ];
    }

    public function testRemoteUrlUploadIsDisabled()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/design/js/filemanager/upload.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString('curl_init(', $source);
    }

    public function testSvgUploadsAreSanitized()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/design/js/filemanager/UploadHandler.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('SvgSanitizer', $source);
    }

    public function testGuardIsTheFirstExecutableStatement()
    {
        foreach ($this->guardedEntrypointProvider() as $row) {
            $file = $row[0];
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

            $guard = strpos($source, 'okay_access.php');
            $config = strpos($source, "include 'config/config.php'");

            $this->assertIsInt($guard, $file);

            if ($config !== false) {
                $this->assertLessThan($config, $guard, $file);
            }
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter FilemanagerAccessTest`
Expected: FAIL on all four tests.

- [ ] **Step 3: Write `AccessGuard`**

Create `Okay/Core/Security/Filemanager/AccessGuard.php`:

```php
<?php

namespace Okay\Core\Security\Filemanager;

use Okay\Core\EntityFactory;
use Okay\Entities\ManagersEntity;

/**
 * Проверка авторизованного менеджера для процедурных точек входа
 * файлового менеджера. Флага $_SESSION['RF']['verify'] недостаточно:
 * он говорит лишь о том, что менеджер когда-то открывал диалог.
 */
class AccessGuard
{
    /** @var EntityFactory */
    private $entityFactory;

    public function __construct(EntityFactory $entityFactory)
    {
        $this->entityFactory = $entityFactory;
    }

    public function currentManager()
    {
        if (empty($_SESSION['admin'])) {
            return null;
        }

        /** @var ManagersEntity $managersEntity */
        $managersEntity = $this->entityFactory->get(ManagersEntity::class);
        $manager = $managersEntity->get($_SESSION['admin']);

        return empty($manager) ? null : $manager;
    }

    public function requireManager($permission = null)
    {
        $manager = $this->currentManager();

        if ($manager === null) {
            $this->deny();
        }

        if ($permission !== null && !$this->hasPermission($manager, $permission)) {
            $this->deny();
        }

        return $manager;
    }

    public function hasPermission($manager, $permission)
    {
        if (empty($manager) || empty($manager->permissions)) {
            return false;
        }

        return in_array($permission, (array)$manager->permissions, true);
    }

    private function deny()
    {
        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: text/plain; charset=utf-8');
        }

        echo 'Forbidden';
        exit;
    }
}
```

Verify how `$manager->permissions` is shaped — `ManagersEntity` may unserialize it into an array already:

```bash
grep -n "permissions" Okay/Entities/ManagersEntity.php | head
```

- [ ] **Step 4: Write the procedural bootstrap**

Create `backend/design/js/filemanager/include/okay_access.php`:

```php
<?php

/**
 * Единая точка проверки доступа для процедурных входов файлового менеджера.
 * Подключается ПЕРВОЙ строкой каждой точки входа, до чтения конфигурации
 * и до любых операций с файловой системой.
 */

use Okay\Core\EntityFactory;
use Okay\Core\Security\Filemanager\AccessGuard;
use Okay\Core\Security\SessionNames;

$okayFilemanagerRoot = realpath(__DIR__ . '/../../../../..');

require_once $okayFilemanagerRoot . '/vendor/autoload.php';

SessionNames::startBackend();

$okayPreviousCwd = getcwd();
chdir($okayFilemanagerRoot);

$okayDI = include $okayFilemanagerRoot . '/Okay/Core/config/container.php';

/** @var EntityFactory $okayEntityFactory */
$okayEntityFactory = $okayDI->get(EntityFactory::class);

$okayAccessGuard = new AccessGuard($okayEntityFactory);
$okayManager = $okayAccessGuard->requireManager();

if ($okayPreviousCwd !== false) {
    chdir($okayPreviousCwd);
}
```

The `chdir()` dance is required because the container bootstrap resolves paths relative to the project root, while the filemanager scripts run with their own working directory. Verify against `dialog.php`, which already does `chdir('backend/design/js/filemanager/')` at line 25.

- [ ] **Step 5: Wire the guard into all five entrypoints**

Add this as the first executable statement (after the opening `<?php`) of `upload.php`, `execute.php`, `ajax_calls.php` and `force_download.php`:

```php
require_once __DIR__ . '/include/okay_access.php';
```

`dialog.php` already resolves a manager at lines 19-23. Replace that block with the same `require_once` so there is exactly one implementation of the check.

Read each file's first 15 lines before editing — several start with `$config = include 'config/config.php';`, and the guard must precede it.

- [ ] **Step 6: Remove remote URL upload**

Open `backend/design/js/filemanager/upload.php` and delete the branch containing `curl_init($url)` (around line 75) together with the surrounding remote-upload handling. Where the code decided between a local and a remote upload, keep only the local path. If the UI still shows a "upload from URL" field, remove it from `dialog.php` too.

- [ ] **Step 7: Sanitize uploaded SVGs**

In `backend/design/js/filemanager/UploadHandler.php`, find where a completed upload is finalized (search for `move_uploaded_file` or the method that writes the final file). After the file is in place, add:

```php
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'svg') {
            $sanitizer = new \Okay\Core\Security\SvgSanitizer();
            if (!$sanitizer->sanitizeFile($filePath)) {
                @unlink($filePath);
                // Отклоняем файл: SVG не распарсился или не является SVG.
                return false;
            }
        }
```

Adjust `$filePath` and the failure return to match the surrounding method's conventions — read it first.

- [ ] **Step 8: Route filesystem operations through `PathResolver`**

In `ajax_calls.php` and `execute.php`, find every place a local path is built from a request parameter (`copy`, `cut`, `chmod`, `rename`, `delete`, preview, archive extraction). Search for the concatenation pattern:

```bash
grep -n "current_path\|\$_POST\['path'\]\|\$_GET\['path'\]\|\$_POST\['file'\]" backend/design/js/filemanager/ajax_calls.php backend/design/js/filemanager/execute.php | head -30
```

For each, construct the resolver once near the top of the file:

```php
$okayPathResolver = new \Okay\Core\Security\Filemanager\PathResolver($config['current_path']);
```

and replace direct concatenation with:

```php
$resolved = $okayPathResolver->resolve($requestPath);
if ($resolved === null) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}
```

`$config['current_path']` is `'../../../../files/uploads/'` relative to the filemanager directory — confirm the working directory at the point of construction and pass an absolute path if needed.

- [ ] **Step 9: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter FilemanagerAccessTest`
Expected: PASS, 8 tests (3 + 5 data rows).

- [ ] **Step 10: Verify unauthenticated access is refused**

```bash
for f in dialog.php upload.php execute.php ajax_calls.php force_download.php; do
  printf "%-20s " "$f"
  curl -s -o /dev/null -w "%{http_code}\n" -H "Host: okaycms.loc" \
    "http://127.0.0.1/backend/design/js/filemanager/$f"
done
```
Expected: `403` for every one.

- [ ] **Step 11: Browser smoke test as a logged-in manager**

Open the filemanager from a TinyMCE image field and verify: the file list loads, an image uploads, a preview opens, rename works, a folder can be created, download works, and delete works. Then upload an SVG containing `<script>alert(1)</script>` and confirm the stored file has no script element:

```bash
cd dev && docker compose exec php85 sh -c 'grep -l script /var/www/html/files/uploads/*.svg 2>/dev/null || echo "no script tags in uploaded svg"'
```

- [ ] **Step 12: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/Filemanager backend/design/js/filemanager tests/Security/FilemanagerAccessTest.php
git commit -m "fix(security): require an authenticated manager for filemanager and remove remote URL upload"
```

---

### Task 14: `BackendFileDownloadPolicy` and `backend/files/index.php`

**Files:**
- Create: `Okay/Core/Security/BackendFileDownloadPolicy.php`
- Modify: `backend/files/index.php`
- Test: `tests/Security/BackendFileDownloadPolicyTest.php`

**Interfaces:**
- Consumes: `PathResolver` (Task 11), `AccessGuard` (Task 13).
- Produces:
  - `BackendFileDownloadPolicy::permissionFor(?string $folder, ?string $file, ?string $ext): ?string`

**What is wrong today.** `backend/files/index.php` sanitizes `$file` with `preg_replace("/[^A-Za-z0-9_]+/", "", $file)` but takes `$folder` and `$ext` from `$_GET` untouched, then builds `__DIR__.'/'.$folder.'/'.$file.'.'.$ext`. `$folder` therefore traverses out of `backend/files/`. Any authenticated manager can download any export regardless of their permissions, and `$_SESSION['admin']` is read on line 33 without an isset guard.

- [ ] **Step 1: Enumerate the real download targets**

```bash
ls -R backend/files/
grep -rn "files/index.php" --include="*.php" --include="*.tpl" --include="*.js" backend/ | head -20
```

Record every `folder` / `file` / `ext` triple the admin UI actually requests, plus the permission the page requiring it uses. The mapping in Step 3 must cover exactly those and nothing more.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/BackendFileDownloadPolicyTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\BackendFileDownloadPolicy;
use PHPUnit\Framework\TestCase;

class BackendFileDownloadPolicyTest extends TestCase
{
    public function testKnownExportsMapToSpecificPermissions()
    {
        $policy = new BackendFileDownloadPolicy();

        $this->assertSame('export', $policy->permissionFor('export', 'export', 'csv'));
        $this->assertSame('users', $policy->permissionFor('export_users', 'users', 'csv'));
        $this->assertSame('subscribes', $policy->permissionFor('export_users', 'subscribes', 'csv'));
        $this->assertSame('import', $policy->permissionFor('import', 'example', 'csv'));
    }

    /**
     * @dataProvider deniedProvider
     */
    public function testUnknownCombinationsAreDenied($folder, $file, $ext)
    {
        $policy = new BackendFileDownloadPolicy();

        $this->assertNull($policy->permissionFor($folder, $file, $ext));
    }

    public function deniedProvider()
    {
        return [
            'unknown file'      => ['export', 'unknown', 'csv'],
            'unknown folder'    => ['unknown', 'export', 'csv'],
            'forbidden ext'     => ['export', 'export', 'php'],
            'traversal folder'  => ['../../config', 'config', 'php'],
            'traversal in file' => ['export', '../../../etc/passwd', 'csv'],
            'null folder'       => [null, 'export', 'csv'],
            'null file'         => ['export', null, 'csv'],
            'null ext'          => ['export', 'export', null],
        ];
    }
}
```

Adjust the four expectations in `testKnownExportsMapToSpecificPermissions` to the real triples found in Step 1 — do not invent folder names.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/BackendFileDownloadPolicy.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * Белый список того, что вообще можно скачать через backend/files/index.php,
 * и права, которые для этого нужны. Всё, чего нет в таблице, запрещено.
 */
class BackendFileDownloadPolicy
{
    /**
     * folder => [ file => [ ext => permission ] ]
     *
     * @var array
     */
    private static $map = [
        'export' => [
            'export'        => ['csv' => 'export'],
            'export_orders' => ['csv' => 'orders'],
        ],
        'export_users' => [
            'users'      => ['csv' => 'users'],
            'subscribes' => ['csv' => 'subscribes'],
        ],
        'import' => [
            'example' => ['csv' => 'import'],
            'import'  => ['csv' => 'import'],
        ],
    ];

    public function permissionFor($folder, $file, $ext)
    {
        if (!is_string($folder) || !is_string($file) || !is_string($ext)) {
            return null;
        }

        $ext = strtolower($ext);

        if (!isset(self::$map[$folder][$file][$ext])) {
            return null;
        }

        return self::$map[$folder][$file][$ext];
    }
}
```

Because the lookup is a strict three-level array hit, traversal strings never match — they simply are not keys.

- [ ] **Step 4: Rewrite `backend/files/index.php`**

Replace lines 31-50 (from the `ManagersEntity` lookup through the `file_exists` check) with:

```php
use Okay\Core\Security\BackendFileDownloadPolicy;
use Okay\Core\Security\Filemanager\PathResolver;

/** @var ManagersEntity $managersEntity */
$managersEntity = $entityFactory->get(ManagersEntity::class);
$manager = empty($_SESSION['admin']) ? null : $managersEntity->get($_SESSION['admin']);

if (empty($manager)) {
    exit();
}

$folder = isset($_GET['folder']) ? (string)$_GET['folder'] : '';
$file   = isset($_GET['file']) ? (string)$_GET['file'] : '';
$ext    = isset($_GET['ext']) ? (string)$_GET['ext'] : '';

$policy = new BackendFileDownloadPolicy();
$requiredPermission = $policy->permissionFor($folder, $file, $ext);

if ($requiredPermission === null) {
    exit();
}

if (empty($manager->permissions) || !in_array($requiredPermission, (array)$manager->permissions, true)) {
    exit();
}

$resolver = new PathResolver(__DIR__);
$file = $resolver->resolve($folder . '/' . $file . '.' . $ext);

if ($file === null || !is_file($file)) {
    exit();
}
```

Keep the `use` statements at the top of the file with the existing ones rather than mid-file. The `if ($ext == 'csv')` / image branch below stays as-is: `$ext` is now guaranteed to be one of the whitelisted values.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter BackendFileDownloadPolicyTest`
Expected: PASS, 9 tests (1 + 8 data rows).

- [ ] **Step 6: Verify traversal is refused and real downloads work**

```bash
curl -s -o /dev/null -w "traversal: %{http_code} %{size_download}b\n" -H "Host: okaycms.loc" \
  "http://127.0.0.1/backend/files/index.php?folder=../../config&file=config&ext=php"
```
Expected: an empty body (the script `exit()`s).

Then, logged in as the admin, export products from the admin panel and confirm the CSV downloads.

- [ ] **Step 7: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/BackendFileDownloadPolicy.php backend/files/index.php tests/Security/BackendFileDownloadPolicyTest.php
git commit -m "fix(security): bind backend downloads to a permission allowlist and reject traversal"
```

---

## Phase E — Injection and redirects

### Task 15: Feed filter operator allowlist

**Files:**
- Modify: `Okay/Modules/OkayCMS/Feeds/Core/Presets/AbstractPresetAdapter.php`
- Modify: `Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/AbstractBackendPresetAdapter.php`
- Modify: 7 runtime adapters in `Okay/Modules/OkayCMS/Feeds/Core/Presets/Adapters/`
- Modify: 7 backend adapters in `Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/Adapters/`
- Test: `tests/Security/FeedFilterOperatorTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `AbstractPresetAdapter::normalizeComparisonOperator($operator): string`
  - `AbstractBackendPresetAdapter::normalizeComparisonOperator($operator): string`

**What is wrong today.** `FacebookAdapter.php:60` reads `$operator = $this->feed->settings['filter_price']['operator'];` and interpolates it straight into SQL:

```php
->where("(v.price*cur.rate_to/cur.rate_from) {$operator} :filter_price_value")
```

and, worse, into a quoted literal:

```php
->where("IF(v.stock IS NULL, IF ('{$operator}' = '<' OR '{$operator}' = '=', false, true), v.stock {$operator} :filter_stock_value)")
```

The value originates from `$postSettings['filter_price']['operator']` in the backend adapters, so an admin-level POST reaches SQL verbatim, and the single-quoted form allows a quote breakout.

- [ ] **Step 1: List every call site**

```bash
grep -rn "\['operator'\]" Okay/Modules/OkayCMS/Feeds/ --include="*.php"
```

Expected: 14 files, 28 occurrences. Record the list — every one must be normalized.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/FeedFilterOperatorTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class FeedFilterOperatorTest extends TestCase
{
    /**
     * @dataProvider runtimeAdapterProvider
     */
    public function testRuntimeAdaptersNormalizeOperators($file)
    {
        $source = $this->source($file);

        $this->assertStringContainsString('normalizeComparisonOperator', $source, $file);
        $this->assertStringNotContainsString(
            "\$operator = \$this->feed->settings['filter_price']['operator'];",
            $source,
            $file
        );
        $this->assertStringNotContainsString(
            "\$operator = \$this->feed->settings['filter_stock']['operator'];",
            $source,
            $file
        );
    }

    /**
     * @dataProvider backendAdapterProvider
     */
    public function testBackendAdaptersNormalizePostedOperators($file)
    {
        $source = $this->source($file);

        $this->assertStringContainsString('normalizeComparisonOperator', $source, $file);
        $this->assertStringNotContainsString(
            "'operator' => \$postSettings['filter_price']['operator']",
            $source,
            $file
        );
        $this->assertStringNotContainsString(
            "'operator' => \$postSettings['filter_stock']['operator']",
            $source,
            $file
        );
    }

    /**
     * @dataProvider baseAdapterProvider
     */
    public function testAllowlistIsNarrow($file)
    {
        $source = $this->source($file);

        $this->assertStringContainsString("in_array(\$operator, ['<', '>', '='], true)", $source, $file);
    }

    public function runtimeAdapterProvider()
    {
        return $this->rows('Okay/Modules/OkayCMS/Feeds/Core/Presets/Adapters/', [
            'FacebookAdapter.php',
            'GoogleMerchantAdapter.php',
            'HotlineAdapter.php',
            'PriceUaAdapter.php',
            'PromUaAdapter.php',
            'RozetkaAdapter.php',
            'YmlAdapter.php',
        ]);
    }

    public function backendAdapterProvider()
    {
        return $this->rows('Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/Adapters/', [
            'BackendFacebookAdapter.php',
            'BackendGoogleMerchantAdapter.php',
            'BackendHotlineAdapter.php',
            'BackendPriceUaAdapter.php',
            'BackendPromUaAdapter.php',
            'BackendRozetkaAdapter.php',
            'BackendYmlAdapter.php',
        ]);
    }

    public function baseAdapterProvider()
    {
        return [
            ['Okay/Modules/OkayCMS/Feeds/Core/Presets/AbstractPresetAdapter.php'],
            ['Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/AbstractBackendPresetAdapter.php'],
        ];
    }

    private function rows($dir, array $files)
    {
        $rows = [];
        foreach ($files as $file) {
            $rows[$file] = [$dir . $file];
        }

        return $rows;
    }

    private function source($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
```

Confirm the seven filenames in each provider against the real directory listing from Step 1 and correct any mismatch before running.

- [ ] **Step 3: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter FeedFilterOperatorTest`
Expected: FAIL on all 16 rows.

- [ ] **Step 4: Add the normalizer to both base adapters**

Add this method to `Okay/Modules/OkayCMS/Feeds/Core/Presets/AbstractPresetAdapter.php`:

```php
    /**
     * Оператор сравнения попадает в SQL-фрагмент, поэтому допускается
     * только из белого списка. Любое другое значение трактуется как '='.
     *
     * @param mixed $operator
     * @return string
     */
    protected function normalizeComparisonOperator($operator)
    {
        $operator = is_string($operator) ? trim($operator) : '';

        if (in_array($operator, ['<', '>', '='], true)) {
            return $operator;
        }

        return '=';
    }
```

Add the identical method to `Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/AbstractBackendPresetAdapter.php`. Verify that file exists first:

```bash
ls Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/
```

If the backend base class has a different name, use the actual one and update the test's `baseAdapterProvider` to match.

- [ ] **Step 5: Normalize in the 7 runtime adapters**

In each file under `Okay/Modules/OkayCMS/Feeds/Core/Presets/Adapters/`, replace

```php
            $operator = $this->feed->settings['filter_price']['operator'];
```

with

```php
            $operator = $this->normalizeComparisonOperator($this->feed->settings['filter_price']['operator']);
```

and the same for `filter_stock`.

- [ ] **Step 6: Normalize in the 7 backend adapters**

In each file under `Okay/Modules/OkayCMS/Feeds/Backend/Core/Presets/Adapters/`, replace

```php
                'operator' => $postSettings['filter_price']['operator'],
```

with

```php
                'operator' => $this->normalizeComparisonOperator($postSettings['filter_price']['operator']),
```

and the same for `filter_stock`. Normalizing on write as well as on read means stored settings get cleaned up the next time a feed is saved.

- [ ] **Step 7: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter FeedFilterOperatorTest`
Expected: PASS, 16 tests.

- [ ] **Step 8: Verify a feed still renders**

In the admin panel, open a feed preset, set a price filter to `>` with a value, save, and open the generated feed URL. Confirm the XML renders and the filter is applied. Then check the stored setting:

```bash
cd dev && docker compose exec -T mariadb mysql -uroot -proot okay -e "SELECT id, LEFT(settings, 200) FROM ok_okay_cms__feeds__feeds LIMIT 3;"
```

- [ ] **Step 9: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Modules/OkayCMS/Feeds tests/Security/FeedFilterOperatorTest.php
git commit -m "fix(security): restrict feed filter comparison operators to an allowlist"
```

---

### Task 16: `SafeRedirect` and the PRG open redirect

**Files:**
- Create: `Okay/Core/Security/SafeRedirect.php`
- Modify: `Okay/Helpers/MainHelper.php:459-470`
- Test: `tests/Security/SafeRedirectTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `SafeRedirect::isSameOrigin(?string $url, string $baseUrl): bool`

**What is wrong today.** `MainHelper::activatePRG()` does `Response::redirectTo($request->post("prg_seo_hide"))` with no validation, so any page can post a form that bounces a visitor to an attacker's site under the shop's domain in the referrer chain.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/SafeRedirectTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\SafeRedirect;
use PHPUnit\Framework\TestCase;

class SafeRedirectTest extends TestCase
{
    const BASE = 'http://okaycms.loc';

    /**
     * @dataProvider allowedProvider
     */
    public function testSameOriginUrlsAreAllowed($url)
    {
        $this->assertTrue(SafeRedirect::isSameOrigin($url, self::BASE), $url);
    }

    public function allowedProvider()
    {
        return [
            'root'              => ['/'],
            'path'              => ['/catalog/shoes'],
            'path with query'   => ['/catalog?page=2'],
            'absolute same host'=> ['http://okaycms.loc/catalog'],
            'https same host'   => ['https://okaycms.loc/catalog'],
        ];
    }

    /**
     * @dataProvider rejectedProvider
     */
    public function testForeignAndMalformedUrlsAreRejected($url)
    {
        $this->assertFalse(SafeRedirect::isSameOrigin($url, self::BASE), var_export($url, true));
    }

    public function rejectedProvider()
    {
        return [
            'null'                  => [null],
            'empty'                 => [''],
            'protocol relative'     => ['//evil.com/x'],
            'encoded protocol rel'  => ['%2f%2fevil.com/x'],
            'backslash'             => ['/\\evil.com'],
            'backslash pair'        => ['\\\\evil.com'],
            'foreign host'          => ['http://evil.com/x'],
            'foreign host https'    => ['https://evil.com/x'],
            'javascript scheme'     => ['javascript:alert(1)'],
            'data scheme'           => ['data:text/html,<script>alert(1)</script>'],
            'newline injection'     => ["/catalog\r\nSet-Cookie: a=b"],
            'nul byte'              => ["/catalog\0"],
            'userinfo trick'        => ['http://okaycms.loc@evil.com/'],
            'relative no slash'     => ['catalog'],
        ];
    }
}
```

`'relative no slash'` is rejected deliberately: a bare `catalog` is ambiguous, and every legitimate caller in this codebase passes an absolute path or a full URL.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SafeRedirectTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/SafeRedirect.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * Проверка того, что редирект остаётся в пределах текущего origin.
 *
 * Используется везде, где адрес перехода приходит из запроса.
 */
class SafeRedirect
{
    public static function isSameOrigin($url, $baseUrl)
    {
        if (!is_string($url) || $url === '') {
            return false;
        }

        // Двойное декодирование, чтобы %2f%2f не проскочило как //.
        $decoded = rawurldecode($url);
        $decoded = rawurldecode($decoded);

        if (preg_match('/[\x00-\x1f\x7f]/', $decoded)) {
            return false;
        }

        if (strpos($decoded, '\\') !== false) {
            return false;
        }

        if (strpos($decoded, '//') === 0) {
            return false;
        }

        if ($decoded[0] === '/') {
            return true;
        }

        $parsed = @parse_url($decoded);
        if ($parsed === false || empty($parsed['scheme']) || empty($parsed['host'])) {
            return false;
        }

        if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // Учётные данные в URL используются, чтобы замаскировать чужой хост.
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        $base = @parse_url($baseUrl);
        if ($base === false || empty($base['host'])) {
            return false;
        }

        return strtolower($parsed['host']) === strtolower($base['host']);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SafeRedirectTest`
Expected: PASS, 19 tests (5 + 14 data rows).

- [ ] **Step 5: Apply it to the PRG helper**

In `Okay/Helpers/MainHelper.php`, add the import:

```php
use Okay\Core\Security\SafeRedirect;
```

Replace the body of `activatePRG()`:

```php
    public function activatePRG()
    {
        /** @var Request $request */
        $request = $this->SL->getService(Request::class);

        if ($prgSeoHide = $request->post("prg_seo_hide")) {
            // Адрес приходит из POST, поэтому уводить с текущего домена нельзя.
            if (SafeRedirect::isSameOrigin($prgSeoHide, $request->getBasePathWithDomain())) {
                Response::redirectTo($prgSeoHide);
                exit;
            }
        }

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }
```

Confirm the base-URL accessor's name — `Request` exposes both `getRootUrl()` and `getBasePathWithDomain()`:

```bash
grep -n "function getBasePathWithDomain\|function getRootUrl\|function getDomainWithProtocol" Okay/Core/Request.php
```

Use the one that returns scheme + host.

- [ ] **Step 6: Apply it to every other request-derived redirect**

```bash
grep -rn "redirectTo(" --include="*.php" Okay backend | grep -iE "post\(|get\(|REQUEST_URI|HTTP_REFERER" | grep -v vendor
```

For each hit, wrap the target in the same `isSameOrigin()` check, falling back to the site root when it fails. Pay particular attention to `$_SESSION['before_auth_url']` in `backend/Controllers/AuthAdmin.php` — it is populated from the request and used as a redirect target after login.

- [ ] **Step 7: Verify the open redirect is closed**

```bash
curl -s -o /dev/null -w "external: %{http_code} -> %{redirect_url}\n" -X POST \
  -H "Host: okaycms.loc" -d "prg_seo_hide=http://evil.com/" http://127.0.0.1/
curl -s -o /dev/null -w "internal: %{http_code} -> %{redirect_url}\n" -X POST \
  -H "Host: okaycms.loc" -d "prg_seo_hide=/catalog" http://127.0.0.1/
```
Expected: the first must not redirect to `evil.com`; the second must still redirect to `/catalog`.

- [ ] **Step 8: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/SafeRedirect.php Okay/Helpers/MainHelper.php backend/Controllers/AuthAdmin.php tests/Security/SafeRedirectTest.php
git commit -m "fix(security): validate request-derived redirects against the current origin"
```

---

### Task 17: `unserialize()` hardening and the 1C path

**Files:**
- Modify: `Okay/Entities/ManagersEntity.php:43,56`
- Modify: `Okay/Modules/OkayCMS/Banners/Init/Init.php:187,204`
- Modify: `Okay/Modules/OkayCMS/Feeds/Entities/FeedsEntity.php:45,51,57,62`
- Modify: `Okay/Modules/OkayCMS/NovaposhtaCost/Extenders/BackendExtender.php:88`
- Modify: `Okay/Helpers/OrdersHelper.php:69`
- Modify: `Okay/Modules/OkayCMS/Integration1C/Controllers/Integration1cController.php:57,107,122`
- Test: `tests/Security/UnserializeHardeningTest.php`

**Interfaces:**
- Consumes: `PathResolver` (Task 11).
- Produces: nothing consumed by later tasks.

**What is wrong today.** Ten call sites run `unserialize()` over database columns with no `allowed_classes` option, so a value written by any SQL-injection or a compromised backup becomes object instantiation. Separately, `Integration1cController` takes `filename` from `$_GET` and passes it to `$integration1C->getFullPath($filename)`; the `preg_match` at line 109 only constrains the *shape* of the name, not its directory.

- [ ] **Step 1: Enumerate every call site**

```bash
grep -rn "unserialize(" --include="*.php" Okay backend | grep -v vendor
```

Expected: 10 hits. Any additional hit found here must be fixed too.

- [ ] **Step 2: Write the failing test**

Create `tests/Security/UnserializeHardeningTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class UnserializeHardeningTest extends TestCase
{
    public function testNoCallSiteUnserializesWithoutAnAllowedClassesOption()
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach ($this->phpFiles($root . '/Okay') as $file) {
            $source = file_get_contents($file);
            if ($source === false) {
                continue;
            }

            if (preg_match_all('/unserialize\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $tail = substr($source, $match[1], 250);
                    if (strpos($tail, 'allowed_classes') === false) {
                        $offenders[] = str_replace($root . '/', '', $file);
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)));
    }

    public function testIntegration1cResolvesFilenamesThroughThePathResolver()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/Okay/Modules/OkayCMS/Integration1C/Controllers/Integration1cController.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('PathResolver', $source);
    }

    private function phpFiles($dir)
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter UnserializeHardeningTest`
Expected: FAIL, listing the 10 offending files.

- [ ] **Step 4: Harden every `unserialize()` call**

At each site, add the second argument. For example, in `Okay/Entities/ManagersEntity.php:43`:

```php
                $m->menu = unserialize($m->menu, ['allowed_classes' => false]);
```

and at line 56:

```php
            $manager->menu = !empty($manager->menu)
                ? unserialize($manager->menu, ['allowed_classes' => false])
                : [];
```

Apply the same change to the other eight sites. None of these columns is supposed to contain objects — they hold arrays of settings and menu entries — so `false` is correct everywhere. After each file, re-run the storefront and the relevant admin page to confirm nothing depended on object deserialization.

- [ ] **Step 5: Resolve the 1C filename**

In `Okay/Modules/OkayCMS/Integration1C/Controllers/Integration1cController.php`, read the surrounding method first:

```bash
sed -n '45,130p' Okay/Modules/OkayCMS/Integration1C/Controllers/Integration1cController.php
grep -n "function getFullPath" -A 15 Okay/Modules/OkayCMS/Integration1C/*.php Okay/Modules/OkayCMS/Integration1C/**/*.php 2>/dev/null
```

Find the directory `getFullPath()` builds against, then replace each `$integration1C->getFullPath($filename)` with a resolved path:

```php
use Okay\Core\Security\Filemanager\PathResolver;

// ...

$resolver = new PathResolver($integration1C->getUploadDir());
$xmlFileName = $resolver->resolve($filename);

if ($xmlFileName === null) {
    throw new \Exception('Wrong filename');
}
```

Use whatever accessor the module exposes for its upload directory; if there is none, add one that returns the same base path `getFullPath()` already uses internally.

- [ ] **Step 6: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter UnserializeHardeningTest`
Expected: PASS, 2 tests.

- [ ] **Step 7: Verify nothing regressed**

Log into the admin panel and open: the managers list (menu unserialization), a banner (Banners settings), a feed's settings page (Feeds settings), an order with a delivery method (OrdersHelper), and the Nova Poshta delivery settings. All must render.

- [ ] **Step 8: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay tests/Security/UnserializeHardeningTest.php
git commit -m "fix(security): forbid object instantiation in unserialize and resolve 1C filenames"
```

---

## Phase F — Headers, cookies, captcha

### Task 18: `SecurityHeaders` and version disclosure

**Files:**
- Create: `Okay/Core/Security/SecurityHeaders.php`
- Modify: `Okay/Core/Response.php:82-86`
- Test: `tests/Security/SecurityHeadersTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `SecurityHeaders::defaults(): string[]` — header lines ready for `Response::addHeader()`

**What is wrong today.** `Response::__construct()` emits `X-Powered-CMS: OkayCMS ' . $version`, telling any scanner exactly which release to target. No framing, sniffing or referrer policy is set.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/SecurityHeadersTest.php`:

```php
<?php

namespace Security;

use Okay\Core\Security\SecurityHeaders;
use PHPUnit\Framework\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function testDefaultsCoverFramingSniffingAndReferrer()
    {
        $headers = SecurityHeaders::defaults();

        $this->assertContains('X-Frame-Options: SAMEORIGIN', $headers);
        $this->assertContains('X-Content-Type-Options: nosniff', $headers);
        $this->assertContains('Referrer-Policy: strict-origin-when-cross-origin', $headers);
    }

    public function testResponseNoLongerAdvertisesTheVersion()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Response.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString("'X-Powered-CMS: OkayCMS ' . \$version", $source);
        $this->assertStringContainsString('SecurityHeaders::defaults()', $source);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SecurityHeadersTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the implementation**

Create `Okay/Core/Security/SecurityHeaders.php`:

```php
<?php

namespace Okay\Core\Security;

/**
 * Базовые защитные заголовки HTML-ответа.
 *
 * CSP сюда сознательно не входит: она требует инвентаризации инлайновых
 * скриптов темы и выносится в отдельную итерацию.
 */
class SecurityHeaders
{
    public static function defaults()
    {
        return [
            'X-Frame-Options: SAMEORIGIN',
            'X-Content-Type-Options: nosniff',
            'Referrer-Policy: strict-origin-when-cross-origin',
        ];
    }
}
```

- [ ] **Step 4: Apply the headers in `Response`**

In `Okay/Core/Response.php`, add the import:

```php
use Okay\Core\Security\SecurityHeaders;
```

Replace the constructor body:

```php
    public function __construct(AdapterManager $adapterManager, string $version)
    {
        $this->adapterManager = $adapterManager;
        $this->type = RESPONSE_HTML;

        // Точная версия больше не публикуется: она превращает баннер в
        // готовую цель для сканеров.
        $this->addHeader('X-Powered-CMS: OkayCMS');

        foreach (SecurityHeaders::defaults() as $header) {
            $this->addHeader($header);
        }
    }
```

The `$version` parameter stays in the signature — it is injected by the container and removing it would require touching `Okay/Core/config/services.php` and `parameters.php`. If PHPStan flags the now-unused parameter, add it to the baseline rather than changing the wiring.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter SecurityHeadersTest`
Expected: PASS, 2 tests.

- [ ] **Step 6: Verify over HTTP**

```bash
curl -s -I -H "Host: okaycms.loc" http://127.0.0.1/ | grep -iE "x-frame|x-content|referrer|x-powered"
```
Expected: the three security headers present, and `X-Powered-CMS: OkayCMS` with no version.

- [ ] **Step 7: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Security/SecurityHeaders.php Okay/Core/Response.php tests/Security/SecurityHeadersTest.php
git commit -m "fix(security): add baseline response headers and drop the version banner"
```

---

### Task 19: Cookie attributes

**Files:**
- Modify: `Okay/Core/BrowsedProducts.php:93`
- Modify: `Okay/Core/Comparison.php:242,260`
- Modify: `Okay/Core/Cart.php:176,182,321`
- Modify: `Okay/Core/WishList.php:116,134`
- Modify: `Okay/Core/UserReferer/UserReferer.php:78`
- Modify: `Okay/Helpers/UserHelper.php:290`
- Modify: `backend/Controllers/IndexAdmin.php:278`
- Modify: `backend/design/js/filemanager/dialog.php:87`
- Modify: `index.php:64`
- Test: `tests/Security/CookieAttributesTest.php`

**Interfaces:**
- Consumes: `SessionNames::isHttps()` (Task 7).
- Produces: nothing consumed by later tasks.

**What is wrong today.** All twelve `setcookie()` calls use the positional form with at most a path, so none of them carries `httponly`, `secure` or `samesite`. The cart, comparison, wishlist and browsing-history cookies are readable by any injected script and are sent on cross-site requests.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/CookieAttributesTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class CookieAttributesTest extends TestCase
{
    /**
     * @dataProvider cookieFileProvider
     */
    public function testEverySetcookieUsesTheOptionsArrayForm($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        preg_match_all('/setcookie\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $match) {
            $tail = substr($source, $match[1], 400);

            $this->assertStringContainsString('samesite', $tail, $file);
            $this->assertStringContainsString('httponly', $tail, $file);
        }
    }

    public function cookieFileProvider()
    {
        return [
            ['Okay/Core/BrowsedProducts.php'],
            ['Okay/Core/Comparison.php'],
            ['Okay/Core/Cart.php'],
            ['Okay/Core/WishList.php'],
            ['Okay/Core/UserReferer/UserReferer.php'],
            ['Okay/Helpers/UserHelper.php'],
            ['backend/Controllers/IndexAdmin.php'],
            ['index.php'],
        ];
    }
}
```

`backend/design/js/filemanager/dialog.php` is excluded from the provider only because its `last_position` cookie is a UI preference set from procedural code — fix it in Step 3 anyway, then add it to the provider.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CookieAttributesTest`
Expected: FAIL on every file.

- [ ] **Step 3: Convert each call**

The mechanical transformation, using `Okay/Core/Cart.php:176` as the model. Before:

```php
            setcookie('shopping_cart', $_COOKIE['shopping_cart'], time() + 30 * 24 * 3600, '/');
```

After:

```php
            setcookie('shopping_cart', $_COOKIE['shopping_cart'], [
                'expires'  => time() + 30 * 24 * 3600,
                'path'     => '/',
                'secure'   => \Okay\Core\Security\SessionNames::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
```

Deletion calls follow the same shape with `'expires' => time() - 3600`.

Apply `'httponly' => true` everywhere **except** any cookie the storefront JavaScript genuinely reads. Check before flipping each one:

```bash
grep -rn "shopping_cart\|comparison\|wishlist\|browsed_products\|admin_login\|userReferer\|last_position" design/ backend/design/js/ --include="*.js" --include="*.tpl" | grep -i "cookie" | head -20
```

Where JavaScript reads a cookie, either set `'httponly' => false` for that one cookie and note why in a comment, or move the value into a data attribute rendered server-side. Prefer the second for `shopping_cart`, since exposing cart contents to script is what makes it interesting to an attacker.

- [ ] **Step 4: Add an import where needed**

Files using the fully-qualified `\Okay\Core\Security\SessionNames::isHttps()` inline need no import. For the class files, add `use Okay\Core\Security\SessionNames;` at the top and drop the leading backslash.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter CookieAttributesTest`
Expected: PASS, 8 tests.

- [ ] **Step 6: Verify over HTTP**

```bash
curl -s -i -H "Host: okaycms.loc" http://127.0.0.1/cart | grep -i "set-cookie"
```
Expected: `SameSite=Lax` and `HttpOnly` on the storefront cookies.

Then in a browser: add to cart, compare two products, add to wishlist, browse a few products, and confirm each feature still works across a page reload.

- [ ] **Step 7: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay backend index.php tests/Security/CookieAttributesTest.php
git commit -m "fix(security): set httponly, secure and samesite on all cookies"
```

---

### Task 20: reCAPTCHA fail-closed and admin template escaping

**Files:**
- Modify: `Okay/Core/Recaptcha.php:33-50`
- Modify: `backend/design/html/auth.tpl:34,58`
- Test: `tests/Security/RecaptchaFailClosedTest.php`
- Test: `tests/Security/AdminAuthTemplateEscapingTest.php`

**Interfaces:**
- Consumes: `Psr\Log\LoggerInterface` (already wired in the container).
- Produces: nothing consumed by later tasks.

**What is wrong today.** `Recaptcha::check()` returns `true` when the API answers `invalid-input-secret`, so a typo in the secret key silently disables the captcha shop-wide with no signal. `auth.tpl` prints `{$smarty.server.HTTP_HOST}` unescaped on a page served before authentication.

- [ ] **Step 1: Write the failing tests**

Create `tests/Security/RecaptchaFailClosedTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class RecaptchaFailClosedTest extends TestCase
{
    public function testInvalidSecretNoLongerPassesTheCheck()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Recaptcha.php');

        $this->assertIsString($source);

        $marker = strpos($source, 'invalid-input-secret');
        $this->assertIsInt($marker);

        $branch = substr($source, $marker, 400);
        $this->assertStringNotContainsString('return true;', $branch);
        $this->assertStringContainsString('return false;', $branch);
    }

    public function testMisconfigurationIsLogged()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Recaptcha.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('LoggerInterface', $source);
        $this->assertStringContainsString('invalid-input-secret', $source);
    }

    public function testMissingSuccessKeyIsTreatedAsFailure()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/Recaptcha.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString("\$response['success'] == false", $source);
        $this->assertStringContainsString("empty(\$response['success'])", $source);
    }
}
```

Create `tests/Security/AdminAuthTemplateEscapingTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class AdminAuthTemplateEscapingTest extends TestCase
{
    public function testPreAuthTemplateEscapesRequestAndServerValues()
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/backend/design/html/auth.tpl');

        $this->assertIsString($template);
        $this->assertStringContainsString('{$smarty.server.HTTP_HOST|escape}', $template);
        $this->assertStringNotContainsString('{$smarty.server.HTTP_HOST}', $template);
    }

    public function testLoginValueIsEscaped()
    {
        $template = file_get_contents(dirname(__DIR__, 2) . '/backend/design/html/auth.tpl');

        $this->assertIsString($template);
        $this->assertStringNotContainsString('value="{$login}"', $template);
    }
}
```

`assertStringNotContainsString('{$smarty.server.HTTP_HOST}', ...)` also matches the escaped form's prefix — no it does not: `{$smarty.server.HTTP_HOST|escape}` contains `{$smarty.server.HTTP_HOST` but not the closing `}` directly after it, so the assertion is exact.

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter "RecaptchaFailClosedTest|AdminAuthTemplateEscapingTest"`
Expected: FAIL on all five tests.

- [ ] **Step 3: Fix the captcha**

Read the current method first:

```bash
sed -n '1,60p' Okay/Core/Recaptcha.php
```

Add a `LoggerInterface` to the constructor following the pattern used elsewhere in `Okay/Core/`, then replace the `check()` body:

```php
    public function check()
    {
        $response = $this->request();

        if (!is_array($response)) {
            $this->logger->warning('Recaptcha: unreadable API response');
            return false;
        }

        if (isset($response['error-codes'])
            && in_array('invalid-input-secret', (array)$response['error-codes'], true)
        ) {
            // Раньше здесь стоял return true, и опечатка в ключе бесшумно
            // отключала капчу на всём сайте.
            $this->logger->error('Recaptcha: invalid secret key, check the captcha settings');
            return false;
        }

        if (empty($response['success'])) {
            return false;
        }

        if ($this->settings->captcha_type == 'v3') {
            return $this->calcIsHumanV3($response);
        }

        return true;
    }
```

Register the logger in the service definition if `Recaptcha` is wired explicitly:

```bash
grep -n "Recaptcha" Okay/Core/config/services.php
```

- [ ] **Step 4: Fix the template**

In `backend/design/html/auth.tpl`, add `|escape` at lines 34 and 58:

```smarty
<p class="auth_heading_promo">на сайте {$smarty.server.HTTP_HOST|escape}</p>
```

Then check whether the login field echoes the submitted value — `AuthAdmin` assigns `$login` on a failed attempt:

```bash
grep -n "login" backend/design/html/auth.tpl | head -20
```

Add `|escape` to every `{$login}` and `{$recovery_login}` output. Smarty 4 does not escape by default in this project — verify with `grep -n "escape_html" Okay/Core/Design.php`; if `$smarty->escape_html` is already `true`, the template additions are still correct and harmless.

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter "RecaptchaFailClosedTest|AdminAuthTemplateEscapingTest"`
Expected: PASS, 5 tests.

- [ ] **Step 6: Verify the XSS is closed**

```bash
curl -s -H "Host: okaycms.loc\"><script>alert(1)</script>" http://127.0.0.1/admin | grep -o "alert(1)" | head
```
Expected: no output, or the payload appearing only in escaped form. Then load `http://okaycms.loc/admin` normally and confirm the page still renders.

- [ ] **Step 7: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Core/Recaptcha.php Okay/Core/config/services.php backend/design/html/auth.tpl tests/Security
git commit -m "fix(security): fail closed on recaptcha misconfiguration and escape pre-auth output"
```

---

## Phase G — Payment callbacks — SKIPPED

**Not implemented. Decided during execution: neither payment module is in use.**

Defects 17 and 18 stay open by choice, not by oversight. Both are recorded in
`docs/UPGRADE-security.md` so anyone who does enable these modules knows what
they are switching on:

- **17, WayForPay.** `CallbackController.php:81` reads
  `if (!empty($data->merchantSignature) && $data->merchantSignature != $sign)`,
  so omitting the field skips verification entirely and an unauthenticated POST
  with a correct order id and amount marks the order paid. Line 74 additionally
  calls `array_key_exists()` on a `stdClass`, a `TypeError` on PHP 8, so a
  genuinely signed callback fatals before the comparison is even reached. This
  was implemented and verified end to end during execution — a forged callback
  was refused with 400 while a correctly signed one was accepted — then reverted
  in full at the maintainer's request.
- **18, RozetkaPay.** The callback performs no authentication at all; the only
  barrier is a matching `$data->id`. Line 58 also uses `&&` where `||` is meant,
  so a payment method belonging to another module passes the check.

One line in `WayForPay/Controllers/CallbackController.php` is deliberately kept:
the `unserialize(..., ['allowed_classes' => false])` from Task 17. It belongs to
the codebase-wide sweep, not to this phase, and `UnserializeHardeningTest` scans
the whole `Okay/` tree — removing it fails that test.

The original tasks are kept below for whoever picks this up.

---

## Phase G (original tasks, not executed)

### Task 21: WayForPay signature becomes mandatory

**Files:**
- Modify: `Okay/Modules/OkayCMS/WayForPay/Controllers/CallbackController.php:70-108`
- Test: `tests/Security/WayForPayCallbackTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing consumed by later tasks.

**What is wrong today.** Two separate defects in one method:

1. Line 81 reads `if (!empty($data->merchantSignature) && $data->merchantSignature != $sign)`. When the field is absent the whole condition is false, so the callback proceeds and line 104 marks the order paid. An unauthenticated POST with the correct order id and amount buys goods for free.
2. Line 74 calls `array_key_exists($dataKey, $data)` where `$data` is a `stdClass` from `json_decode()`. PHP 8 removed object support from `array_key_exists()`, so this throws a `TypeError` — meaning a *legitimate* signed callback fatals before reaching the comparison.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/WayForPayCallbackTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class WayForPayCallbackTest extends TestCase
{
    public function testSignatureIsMandatory()
    {
        $source = $this->source();

        $this->assertStringNotContainsString(
            "if (!empty(\$data->merchantSignature) && \$data->merchantSignature != \$sign)",
            $source
        );
        $this->assertStringContainsString('empty($data->merchantSignature)', $source);
        $this->assertStringContainsString('hash_equals(', $source);
    }

    public function testSignaturePayloadDoesNotUseArrayKeyExistsOnAnObject()
    {
        $source = $this->source();

        $this->assertStringNotContainsString('array_key_exists($dataKey, $data)', $source);
        $this->assertStringContainsString('property_exists($data, $dataKey)', $source);
    }

    public function testSignatureIsVerifiedBeforeTheOrderIsMarkedPaid()
    {
        $source = $this->source();

        $verify = strpos($source, 'hash_equals(');
        $paid = strpos($source, "['paid' => 1]");

        $this->assertIsInt($verify);
        $this->assertIsInt($paid);
        $this->assertLessThan($paid, $verify);
    }

    public function testArrayKeyExistsOnStdClassWouldThrowOnPhp8()
    {
        $this->expectException(\TypeError::class);

        $object = json_decode('{"a":1}');
        array_key_exists('a', $object);
    }

    private function source()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/Okay/Modules/OkayCMS/WayForPay/Controllers/CallbackController.php'
        );
        $this->assertIsString($source);

        return $source;
    }
}
```

The last test documents *why* the second change is needed; if PHP ever softens that behaviour the test tells us the rationale changed.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter WayForPayCallbackTest`
Expected: FAIL on the first three tests, PASS on the fourth.

- [ ] **Step 3: Rewrite the verification block**

In `Okay/Modules/OkayCMS/WayForPay/Controllers/CallbackController.php`, replace lines 70-86 with:

```php
        $settings = unserialize($method->settings, ['allowed_classes' => false]);

        // Подпись обязательна. Раньше её отсутствие полностью пропускало
        // проверку, и заказ можно было пометить оплаченным без оплаты.
        if (empty($data->merchantSignature) || !is_string($data->merchantSignature)) {
            $logger->warning("WayForPay notice: 'Missing merchant signature'. Order №{$orderId}");
            $this->response->setContent("Invalid merchant signature")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        $sign = [];
        foreach ($keysForSignature as $dataKey) {
            // $data — stdClass из json_decode(); array_key_exists() на объекте
            // выбрасывает TypeError начиная с PHP 8.
            if (property_exists($data, $dataKey)) {
                $sign[] = $data->$dataKey;
            }
        }

        $sign = implode(';', $sign);
        $sign = hash_hmac('md5', $sign, $settings['wayforpay_secretkey']);

        if (!hash_equals($sign, (string)$data->merchantSignature)) {
            $logger->warning("WayForPay notice: 'Invalid merchant signature'. Order №{$orderId}");
            $this->response->setContent("Invalid merchant signature")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }
```

The `unserialize()` hardening is folded in here because this line is inside the block being rewritten; Task 17 covers the other nine sites.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter WayForPayCallbackTest`
Expected: PASS, 4 tests.

- [ ] **Step 5: Verify the callback rejects a forged request**

Create an order in the shop with WayForPay selected, note its id and total, then:

```bash
curl -s -w "\nHTTP %{http_code}\n" -X POST -H "Host: okaycms.loc" \
  -H "Content-Type: application/json" \
  -d '{"orderReference":"1","amount":100,"currency":"UAH","transactionStatus":"Approved"}' \
  http://127.0.0.1/payment/OkayCMS/WayForPay/callback
```

Confirm the exact callback URL first:

```bash
grep -rn "slug" Okay/Modules/OkayCMS/WayForPay/Init/routes.php
```

Expected: `HTTP 400` with `Invalid merchant signature`, and the order still unpaid:

```bash
cd dev && docker compose exec -T mariadb mysql -uroot -proot okay -e "SELECT id, paid FROM ok_orders ORDER BY id DESC LIMIT 3;"
```

- [ ] **Step 6: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Modules/OkayCMS/WayForPay tests/Security/WayForPayCallbackTest.php
git commit -m "fix(security): require a valid WayForPay signature before marking an order paid"
```

---

### Task 22: RozetkaPay callback authentication

**Files:**
- Modify: `Okay/Modules/OkayCMS/RozetkaPay/Models/Gateway/CreatePayment.php:48`
- Modify: `Okay/Modules/OkayCMS/RozetkaPay/Controllers/CallbackController.php:22-80`
- Create: `Okay/Modules/OkayCMS/RozetkaPay/Core/CallbackSignature.php`
- Test: `tests/Security/RozetkaPayCallbackTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `CallbackSignature::__construct(string $secretKey)`
  - `CallbackSignature::sign(int $orderId): string`
  - `CallbackSignature::verify(int $orderId, ?string $signature): bool`

**What is wrong today.** The callback performs no authentication whatsoever. The only barrier is `$data->id !== $createDetails->id` at line 75 — a value the gateway echoes back, not a secret we can rely on. Line 58 also reads `if (empty($method) && $method->module !== "OkayCMS/RozetkaPay")`: with `&&`, an empty `$method` dereferences null, and a payment method belonging to a different module passes the check.

Rather than guess at an undocumented inbound signature scheme, the callback URL itself carries an HMAC we generate at `CreatePayment` time and verify on the way back. The gateway calls exactly the URL it was given, so this authenticates the callback with a secret only we and the gateway's stored configuration know.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/RozetkaPayCallbackTest.php`:

```php
<?php

namespace Security;

use Okay\Modules\OkayCMS\RozetkaPay\Core\CallbackSignature;
use PHPUnit\Framework\TestCase;

class RozetkaPayCallbackTest extends TestCase
{
    public function testSignatureRoundTrips()
    {
        $signature = new CallbackSignature('secret-key');
        $token = $signature->sign(42);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertTrue($signature->verify(42, $token));
    }

    public function testSignatureIsBoundToTheOrderAndTheKey()
    {
        $signature = new CallbackSignature('secret-key');
        $token = $signature->sign(42);

        $this->assertFalse($signature->verify(43, $token));
        $this->assertFalse((new CallbackSignature('other-key'))->verify(42, $token));
    }

    public function testVerifyFailsClosed()
    {
        $signature = new CallbackSignature('secret-key');

        $this->assertFalse($signature->verify(42, null));
        $this->assertFalse($signature->verify(42, ''));
        $this->assertFalse($signature->verify(42, 'nope'));
    }

    public function testCallbackVerifiesBeforeMarkingPaid()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/Okay/Modules/OkayCMS/RozetkaPay/Controllers/CallbackController.php'
        );
        $this->assertIsString($source);

        $verify = strpos($source, 'CallbackSignature');
        $paid = strpos($source, "['paid' => 1]");

        $this->assertIsInt($verify);
        $this->assertIsInt($paid);
        $this->assertLessThan($paid, $verify);
    }

    public function testPaymentMethodCheckUsesOr()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/Okay/Modules/OkayCMS/RozetkaPay/Controllers/CallbackController.php'
        );
        $this->assertIsString($source);

        $this->assertStringNotContainsString(
            'empty($method) && $method->module !== "OkayCMS/RozetkaPay"',
            $source
        );
        $this->assertStringContainsString('empty($method) || $method->module !== "OkayCMS/RozetkaPay"', $source);
    }

    public function testCallbackUrlIsSignedOnCreate()
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/Okay/Modules/OkayCMS/RozetkaPay/Models/Gateway/CreatePayment.php'
        );
        $this->assertIsString($source);

        $this->assertStringContainsString('CallbackSignature', $source);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter RozetkaPayCallbackTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `CallbackSignature`**

Create `Okay/Modules/OkayCMS/RozetkaPay/Core/CallbackSignature.php`:

```php
<?php

namespace Okay\Modules\OkayCMS\RozetkaPay\Core;

/**
 * Подпись callback-URL.
 *
 * Шлюз вызывает ровно тот адрес, который мы передали при создании платежа,
 * поэтому HMAC в query-строке аутентифицирует входящий вызов, не завися от
 * недокументированной схемы подписи со стороны шлюза.
 */
class CallbackSignature
{
    const PARAM = 'okay_sign';

    /** @var string */
    private $secretKey;

    public function __construct($secretKey)
    {
        $this->secretKey = (string)$secretKey;
    }

    public function sign($orderId)
    {
        return hash_hmac('sha256', 'rozetkapay:' . (int)$orderId, $this->secretKey);
    }

    public function verify($orderId, $signature)
    {
        if (!is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($orderId), $signature);
    }
}
```

- [ ] **Step 4: Sign the callback URL at creation**

In `Okay/Modules/OkayCMS/RozetkaPay/Models/Gateway/CreatePayment.php`, read the surrounding context:

```bash
sed -n '30,60p' Okay/Modules/OkayCMS/RozetkaPay/Models/Gateway/CreatePayment.php
```

Replace line 48:

```php
            'callback_url' => $order['callback_url'],
```

with:

```php
            'callback_url' => $this->signCallbackUrl(
                $order['callback_url'],
                (int)$order['id'],
                $order['settings']['rozetkapay_secretkey']
            ),
```

and add the helper to the same class:

```php
    private function signCallbackUrl($callbackUrl, $orderId, $secretKey)
    {
        $signature = new \Okay\Modules\OkayCMS\RozetkaPay\Core\CallbackSignature($secretKey);
        $separator = strpos($callbackUrl, '?') === false ? '?' : '&';

        return $callbackUrl . $separator
            . \Okay\Modules\OkayCMS\RozetkaPay\Core\CallbackSignature::PARAM . '='
            . $signature->sign($orderId);
    }
```

Confirm that `$order['id']` is the shop's order id in that array — the surrounding code uses `external_id` for the same value, so check which key is populated and use whichever matches what the callback receives as `$data->external_id`.

- [ ] **Step 5: Verify the signature in the callback**

In `Okay/Modules/OkayCMS/RozetkaPay/Controllers/CallbackController.php`, insert the verification immediately after `$method` is resolved (currently line 57-63) and fix the boolean operator at the same time:

```php
        $method = $paymentsEntity->get((int) $order->payment_method_id);
        if (empty($method) || $method->module !== "OkayCMS/RozetkaPay") {
            $logger->warning("RozetkaPay notice: 'Invalid payment method'. Order №{$orderId}");
            $this->response->setContent("Invalid payment method")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }

        $settings = $paymentsEntity->getPaymentSettings($method->id);
        $signature = new CallbackSignature($settings['rozetkapay_secretkey']);

        // Раньше подпись не проверялась вовсе: заказ можно было пометить
        // оплаченным неаутентифицированным POST-запросом.
        if (!$signature->verify((int)$orderId, $this->request->get(CallbackSignature::PARAM))) {
            $logger->warning("RozetkaPay notice: 'Invalid callback signature'. Order №{$orderId}");
            $this->response->setContent("Invalid callback signature")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }
```

Add the import:

```php
use Okay\Modules\OkayCMS\RozetkaPay\Core\CallbackSignature;
```

Confirm how settings are read — the WayForPay controller uses `unserialize($method->settings)` while Fondy uses `$paymentsEntity->getPaymentSettings()`. Use whichever this module already uses elsewhere:

```bash
grep -rn "getPaymentSettings\|unserialize" Okay/Modules/OkayCMS/RozetkaPay/ | head
```

Also guard `$data->details->amount` at line 65, which currently dereferences without an `isset` check:

```php
        $amount = isset($data->details->amount) ? $data->details->amount : null;
        if ($amount === null) {
            $this->response->setContent("Wrong data")->setStatusCode(400);
            $this->response->sendContent();
            exit;
        }
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter RozetkaPayCallbackTest`
Expected: PASS, 6 tests.

- [ ] **Step 7: Verify a forged callback is rejected**

```bash
grep -rn "slug" Okay/Modules/OkayCMS/RozetkaPay/Init/routes.php
```

Then POST an unsigned callback to that URL and confirm `HTTP 400` plus an unchanged `paid` flag, exactly as in Task 21 Step 5.

- [ ] **Step 8: Note the operational consequence**

Payments created *before* this change have an unsigned `callback_url` stored at the gateway. Their callbacks will now be rejected. Add this to `docs/UPGRADE-security.md` in Task 23: deploy at a quiet moment, and reconcile any in-flight RozetkaPay orders manually from the gateway's dashboard.

- [ ] **Step 9: Run the full suite and commit**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

```bash
cd /home/sviat/projects/OkayCMS
git add Okay/Modules/OkayCMS/RozetkaPay tests/Security/RozetkaPayCallbackTest.php
git commit -m "fix(security): authenticate RozetkaPay callbacks with a signed callback URL"
```

---

## Phase H — Documentation and final verification

### Task 23: Upgrade notes

**Files:**
- Create: `docs/UPGRADE-security.md`
- Modify: `CLAUDE.md` (add a pointer to the new doc in the docs table)
- Test: `tests/Security/UpgradeNotesTest.php`

**Interfaces:**
- Consumes: everything above.
- Produces: nothing.

- [ ] **Step 1: Write the failing test**

Create `tests/Security/UpgradeNotesTest.php`:

```php
<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class UpgradeNotesTest extends TestCase
{
    /**
     * @dataProvider requiredTopicProvider
     */
    public function testUpgradeNotesCoverEveryBreakingChange($topic)
    {
        $notes = file_get_contents(dirname(__DIR__, 2) . '/docs/UPGRADE-security.md');

        $this->assertIsString($notes);
        $this->assertStringContainsString($topic, $notes);
    }

    public function requiredTopicProvider()
    {
        return [
            ['okay_sid'],
            ['okay_admin_sid'],
            ['customer_csrf_token'],
            ['okay_csrf'],
            ['RozetkaPay'],
            ['SVG'],
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter UpgradeNotesTest`
Expected: FAIL — file not found.

- [ ] **Step 3: Write the upgrade notes**

Create `docs/UPGRADE-security.md` covering, with a concrete "what to change" for each:

1. **Sessions are invalidated once.** `session_name` moved from `md5(User-Agent)` to `okay_sid` (storefront) and `okay_admin_sid` (admin). Every customer and manager is logged out on deploy. Nothing to change in code.
2. **Storefront mutations require POST and a CSRF token.** List the guarded endpoints (`/cart/{id}`, `/cart/remove/{id}`, `/ajax/cart_ajax.php`, `/ajax/wishlist.php`, `/ajax/comparison.php`, `/ajax/subscribe`, feedback, comments). Show the hidden-field snippet for templates and the `okayCsrfToken()` cookie reader for AJAX. State the failure modes: 405 for GET, 403 for a bad token.
3. **Passwords are rehashed transparently.** No action required; legacy APR1 and MD5 hashes keep working until the account's next login.
4. **Recovery links no longer authenticate.** Themes overriding `password_remind.tpl` must add the reset form — list the required field names (`new_password`, `new_password_check`, `reset_password`, `customer_csrf_token`) and the new language keys.
5. **Filemanager: remote URL upload removed, SVG sanitized.** Uploaded SVGs lose scripting and animation.
6. **RozetkaPay callbacks are signed.** In-flight payments created before the upgrade will be rejected; reconcile them from the gateway dashboard.
7. **`X-Powered-CMS` no longer carries the version**, and three security headers are added — relevant to anyone parsing the banner or embedding the shop in an iframe.
8. **Cookies gained `HttpOnly`.** Any custom JavaScript reading `shopping_cart`, `comparison`, `wishlist` or `browsed_products` directly must move to a server-rendered data attribute.

- [ ] **Step 4: Link it from `CLAUDE.md`**

Add a row to the docs table in `CLAUDE.md`:

```markdown
| Security boundaries and upgrade notes | `docs/UPGRADE-security.md` |
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd dev && docker compose exec php85 php vendor/bin/phpunit --filter UpgradeNotesTest`
Expected: PASS, 6 tests.

- [ ] **Step 6: Commit**

```bash
git add docs/UPGRADE-security.md CLAUDE.md tests/Security/UpgradeNotesTest.php
git commit -m "docs(security): add upgrade notes for the hardening release"
```

---

### Task 24: Full verification pass

**Files:**
- No production changes. Fixes discovered here belong in the task that introduced the problem.

- [ ] **Step 1: Run the whole suite**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```
Expected: 0 failures, 0 errors. Record the final test count and compare it against the 176 baseline — the delta must equal the number of tests added across Tasks 1-23.

- [ ] **Step 2: Run the security suite alone**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit tests/Security
```
Expected: 25 test classes, 0 failures.

- [ ] **Step 3: Run static analysis**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpstan analyse --memory-limit=1G
```
Expected: no new errors beyond `phpstan-baseline.neon`. If the new `Okay/Core/Security/` classes produce findings, fix the code rather than extending the baseline.

- [ ] **Step 4: Run the code sniffer**

```bash
cd dev && docker compose exec php85 php vendor/bin/phpcs
```
Expected: clean for the new files. Fix style violations in `Okay/Core/Security/` directly.

- [ ] **Step 5: Storefront smoke test**

```bash
for path in / /catalog /cart /user/login /password_remind; do
  printf "%-20s " "$path"
  curl -s -o /dev/null -w "%{http_code}\n" -H "Host: okaycms.loc" "http://127.0.0.1$path"
done
```
Expected: 200 or a deliberate redirect for every path, no 500.

Then in a browser, complete a full purchase: browse → add to cart → change quantity → apply a coupon → checkout → place the order. Confirm the order appears in the admin panel.

- [ ] **Step 6: Admin smoke test**

Log in, then exercise: product save, category save, order view and status change, export products, import a CSV, open the filemanager and upload an image, edit a template in the design editor, and save the general settings. Everything must work.

- [ ] **Step 7: Confirm the headers and cookies in one place**

```bash
curl -s -I -H "Host: okaycms.loc" http://127.0.0.1/ | grep -iE "x-frame|x-content|referrer|x-powered|set-cookie"
```
Expected: three security headers, a version-free `X-Powered-CMS`, and `okay_sid` with `HttpOnly; SameSite=Lax`.

- [ ] **Step 8: Confirm the schema never moved**

```bash
cd dev && docker compose exec -T mariadb mysqldump -uroot -proot --no-data --skip-comments okay \
  | sed -E 's/ AUTO_INCREMENT=[0-9]+//' > "$SCRATCH/schema-after.sql"
diff "$SCRATCH/schema-before.sql" "$SCRATCH/schema-after.sql" && echo "schema unchanged"
```
Expected: `schema unchanged`. Any difference means a task wrote DDL and must be reverted — this iteration ships no migration.

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit --filter NoDatabaseChangeTest
git status --short 1DB_changes/
```
Expected: the guard passes and `1DB_changes/` is untouched.

- [ ] **Step 9: Confirm every defect is closed**

Walk the defect table in `docs/superpowers/specs/2026-07-26-security-hardening-design.md` from 1 to 19 and check off each one against the task that fixed it:

| Defect | Task |
| ------ | ---- |
| 1 Customer recovery authenticates | 6 |
| 2 Recovery creates managers | 5 |
| 3 Filemanager auth, SSRF, SVG | 12, 13 |
| 4 Feeds SQL operator | 15 |
| 5 Manager password storage | 1, 2 |
| 6 Customer password storage | 1, 3 |
| 7 PRG open redirect | 16 |
| 8 Shared session namespace | 7 |
| 9 CSRF (admin and storefront) | 8, 9, 10 |
| 10 Cookie attributes | 19 |
| 11 reCAPTCHA fail-open | 20 |
| 12 `auth.tpl` XSS | 20 |
| 13 Version disclosure, headers | 18 |
| 14 Account enumeration | 5, 6 |
| 15 `unserialize()` | 17, 21 |
| 16 1C filename path | 17 |
| 17 WayForPay signature | 21 |
| 18 RozetkaPay signature | 22 |
| 19 `backend/files/index.php` | 14 |

Any row without a green test is unfinished work, not a documentation gap.

- [ ] **Step 10: Final commit**

```bash
cd /home/sviat/projects/OkayCMS
git status
git log --oneline dc2733b..HEAD
```

Confirm the history reads as one logical commit per defect group, then push.
