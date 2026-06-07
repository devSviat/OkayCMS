# PHP 8.5 Support — Final Report

**Date:** 2026-06-07
**Branch:** `feat/php85-support`
**Spec:** `docs/superpowers/specs/2026-06-07-php85-support-design.md`
**Plan:** `docs/superpowers/plans/2026-06-07-php85-support.md`
**Audit:** `docs/superpowers/notes/php85-audit.md`

## Summary

OkayCMS (fork) now runs on PHP 8.5. A dedicated `php85` Docker service was added alongside the existing `php74`; the platform constraint was narrowed to `^8.2`; the dependency toolchain was unblocked; and all PHP 8.x source incompatibilities found via audit, static analysis, and live smoke testing were fixed with minimal, behaviour-preserving changes. The full test suite (158), PHPStan, and PHPCompatibility are green on 8.5, and the storefront, admin, and CLI boot and run on 8.5.

## Files Changed

**Docker / env**
- `dev/docker/php/8.5/Dockerfile` (new) — PHP 8.5-fpm image (pdo_mysql, mysqli, gd, zip, xsl, xmlwriter, Xdebug 3, Composer).
- `dev/docker-compose.yml` — added `php85` service alongside `php74`.

**Dependency / config**
- `composer.json` / `composer.lock` — `php` `^7.4 || ^8.0` → `^8.2`; `phpunit/phpunit` → 9.6.34 (drops `phpspec/prophecy`); `symfony/console|process|lock` → 5.4 LTS; `aura/sql` `^3.0` → `^6.0`; added dev tooling (`phpstan/phpstan`, `squizlabs/php_codesniffer ^3.7`, `phpcompatibility/php-compatibility ^9.3`, `dealerdirect/phpcodesniffer-composer-installer`); `config.allow-plugins`.
- `phpstan.neon`, `phpstan-baseline.neon`, `phpcs.xml.dist` (new).

**Source fixes (PHP 8.x compatibility)**
- `Okay/Core/Money.php` — 2 implicit-nullable params → explicit `?`.
- `Okay/Core/Response.php` — 1 implicit-nullable param → explicit `?`.
- `Okay/Helpers/FilterHelper.php` — 6 implicit-nullable params → explicit `?`.
- `Okay/Helpers/CatalogHelper.php`, `Okay/Helpers/CategoriesHelper.php` — 1 each → explicit `?`.
- `Okay/Modules/OkayCMS/Banners/DTO/{BannerBackupDTO,BannerImageBackupDTO,BannerImageLangBackupDTO,BannerImageSettingsDTO,BannerSettingsDTO}.php` — `jsonSerialize(): array`.
- `Okay/Core/Database.php`, `Okay/Core/Console/Commands/Database/DatabaseDeployCommand.php` — `connect()` → `lazyConnect()` (aura/sql 6.x).
- `backend/design/js/filemanager/include/utils.php` — `utf8_encode()` → `mb_convert_encoding(...)`.
- `backend/design/js/filemanager/UploadHandler.php` — reversed `implode()` arg order corrected.

**Tests (new)**
- `tests/Php85/ImplicitNullableComplianceTest.php` — 5 cases.
- `tests/Php85/TentativeReturnTypeComplianceTest.php` — 5 cases.

**Not committed (per user)**
- `.github/workflows/ci.yml` — PHP 8.2-8.5 matrix workflow (kept in working tree; commit deferred).
- `dev/docker-compose.override.yml` — local helper pointing nginx → php85 for smoke testing.

## Dependencies Updated

| Package | From | To | Reason |
|---------|------|----|--------|
| phpunit/phpunit (dev) | 9.5.10 | 9.6.34 | Required — drops `prophecy` (`php <8.2`) to unblock install on 8.5. |
| aura/sql | 3.0.0 | 6.0.1 | Required — only 6.x removes the instance `connect()` that conflicts with PHP 8.4 static `PDO::connect()`. |
| symfony/console, symfony/process, symfony/lock | 5.3.x | 5.4.x | Optional — latest 5.4 LTS for best 8.x support. |
| phpstan/phpstan, squizlabs/php_codesniffer, phpcompatibility/php-compatibility, dealerdirect/phpcodesniffer-composer-installer (dev) | — | added | Static analysis tooling. |

Not upgraded (work on 8.5 as-is): smarty (3.1.40 — secure/clean version requires breaking major 4.5.3+), aura/sqlquery (2.7.1), monolog (1.x), bramus/router (1.6), and others.

## Tests Added

- 10 new tests across 2 classes (subprocess compile guards for implicit-nullable and tentative-return-type deprecations). Suite: 148 → **158**, all passing on 8.5 (2 pre-existing PHPUnit-9.6→10 warnings, unrelated to 8.5).
- Broader `Okay/` coverage was scoped out to a separate follow-up plan per decision.

## CI Changes

- `.github/workflows/ci.yml` authored (matrix 8.2/8.3/8.4/8.5: composer validate, install, PHPCompatibility, PHPUnit; PHPStan once on 8.5). **Commit deferred** at user request.

## Remaining Risks

1. **Vendor deprecation noise in debug mode.** `aura/sqlquery` 2.7.1, `smarty` 3.1.40, `bramus/router` 1.6 emit implicit-nullable deprecations on 8.4+; visible only with `debug_mode=true`, silent in production. First-party code is clean.
2. **Smarty security debt (pre-existing, unrelated to 8.5).** 3.1.40 is affected by CVE-2024-35226 (high). The whole `<4.5.3` line is affected. Recommend a separate task to evaluate Smarty 4.5.3+/5.x (note: 5.x moves to the `Smarty\Smarty` namespace and will require code changes).
3. **php74 service is now vestigial.** With 7.4 dropped, the app cannot boot on php74 (composer platform_check requires ≥8.2). Consider repointing nginx to php85 / retiring php74 as a cleanup.
4. **Not smoke-tested end-to-end:** admin login POST (CSRF/session), email send, file upload, full checkout flow. Login form, catalog, cart, search, blog, brands, sitemap, and the scheduler CLI (DB-backed) were verified at HTTP/CLI level.

## Addendum — runtime deprecation sweep (debug-mode crawl)

After the initial "boots on 8.5" milestone, a debug-mode crawl of the storefront
and admin surfaced data-dependent runtime deprecations not visible to static
analysis. All first-party occurrences were fixed and the crawl now reports
**zero** first-party and **zero** vendor deprecations.

**Dependency upgrades (approved):**
- `smarty/smarty` 3.1.40 → 4.5.6 — implicit-nullable + dynamic-property floods + CVE-2024-35226; keeps the `\Smarty` API (Smarty 5 namespacing avoided).
- `aura/sqlquery` 2.7.1 → 2.8.1 — fixes `preg_split()` null-limit while staying on the 2.x API (no QueryFactory interface migration; aura/sqlquery 3.0 was evaluated and rejected as too invasive for the reward).
- `matthiasmullie/minify` 1.3.66 → 1.3.75 — drops `parent` in callables.

**First-party runtime fixes:**
- `Okay/Core/Request.php` — `(string)` cast before `preg_replace` in `get()`/`post()`.
- `Okay/Entities/ManagersEntity.php` — guard `unserialize()`, default `menu` to `[]` (also clears the `ManagerMenu` "false to array" deprecation).
- `Okay/Core/Design.php` — register security-allowed PHP functions as Smarty 4 modifiers (after the custom-modifier flush) to satisfy Smarty 4's "register your modifiers" requirement without `already registered` conflicts.
- `Okay/Core/QueryFactory/SqlQuery.php` — add `resetFlags()` (forward-compat).
- `backend/Controllers/IndexAdmin.php` — drop deprecated `curl_close()`.

**Detection method (reusable):** with `debug_mode=true`, crawl pages then
`docker logs <php85> | grep 'Deprecated:'` and split first-party (`/Okay/`,
`/backend/`) from vendor (`/vendor/<pkg>/`). Production (`debug_mode=false`,
`error_reporting` excludes `E_DEPRECATED`) never emitted these.

**Tests added (this sweep):** `tests/Php85/DynamicPropertyComplianceTest`,
`NullArgumentSafetyTest` (now incl. Request), `AuraSqlQueryCompatTest`. Suite: **166** green.

**Known dev-only note:** on a cold Smarty cache, the first request to a complex
template can exceed the fpm timeout (one-off 502); subsequent requests are 200.
Pre-warm or pre-compile templates in deployment.

## PHP 8.5 Support Status

**Supported and verified.** On PHP 8.5.7: storefront and admin return HTTP 200, the `./ok` CLI runs against the DB, and PHPUnit (158), PHPStan, and PHPCompatibility (testVersion 8.2-) all pass. No known blocking compatibility issues remain in first-party code.
