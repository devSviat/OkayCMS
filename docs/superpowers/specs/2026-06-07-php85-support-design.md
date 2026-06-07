# PHP 8.5 Support — Design Spec

**Date:** 2026-06-07
**Repository:** OkayCMS fork (devSviat/OkayCMS)
**Goal:** Add official, verified PHP 8.5 support. No unrelated modernization.

## Scope & Constraints

- ONLY add and verify PHP 8.5 support.
- No `strict_types` changes.
- No library upgrades unless required for 8.5 compatibility (each upgrade needs explicit approval).
- No business-logic refactoring without a compatibility reason.
- Work iteratively; report after every phase (Findings / Changes / Risks / Next step).
- Small logical commits.

## Decisions (approved)

1. **Container strategy:** add a new, independent `php85` service alongside the existing `php74` service. Do not modify or remove `php74`.
2. **Version range:** drop PHP 7.4. Bump `composer.json` `php` constraint from `^7.4 || ^8.0` to `^8.2` (minimum aligns with the CI matrix 8.2–8.5).
3. **Static analysis:** add PHPStan (with baseline) + PHPCompatibility, dev-only.

## Current State (findings)

- `composer.json`: `"php": "^7.4 || ^8.0"`. Ext reqs: SimpleXML, XMLReader, pdo, json, curl, mbstring, zip.
- Dev deps: `phpunit/phpunit ^9.5`, `maximebf/debugbar ^1.16`, `symfony/var-dumper ^5.4.6`.
- Docker (`dev/`): only `dev/docker/php/7.4/Dockerfile` (`php:7.4-fpm`, Xdebug 2.9.0). Compose services: `php74`, `nginx` (links php74), `mariadb`.
- No CI: `.github/workflows/` does not exist.
- Tests: 7 files under `tests/` (Seo, TplMod, Core/Modules). PHPUnit config `phpunit.xml`, bootstrap `vendor/autoload.php`, deprecations/notices/warnings converted to exceptions.
- Host PHP: 8.4. `composer.lock` present.
- Risk-candidate deps: `smarty ~3.1`, `symfony/* ^5.3`, `aura/sql ^3.0`, `monolog ^1.24`, `gregwar/image 2.*`.

## Plan by Phase

### Phase 1 — Docker env for 8.5
- New `dev/docker/php/8.5/Dockerfile` on `php:8.5-fpm`: install pdo_mysql, mysqli, gd (jpeg/webp/freetype), zip, xsl, xmlwriter; install Composer; `usermod/groupmod` www-data to 1000.
- Xdebug: 2.9.0 won't build on 8.5 — use Xdebug 3.x (`pecl install xdebug`) with appropriate `xdebug.mode`. If no 8.5-compatible Xdebug release exists, skip Xdebug on 8.5 (not needed for tests).
- Add `php85` service to `docker-compose.yml` next to `php74`: same `..:/var/www/html` volume, separate `expose`, separate php config mount. Nginx stays pointed at `php74` by default; switching to 8.5 is opt-in (env/profile), so existing env keeps working.
- Tests on 8.5 run via `docker compose exec php85 ...` without switching nginx.

### Phase 2 — Readiness audit (no code changes)
Inside php85: `composer validate`, `composer check-platform-reqs`, `composer outdated`. Grep for: `create_function`, `each(`, `version_compare`, `PHP_VERSION`, `phpversion`, dynamic properties (`__get`/`__set`, `AllowDynamicProperties`), implicit nullable params, `utf8_encode`/`utf8_decode`, implicit float→int. Output: risk table (file / description / severity / fix).

### Phase 3 — Dependency compatibility
Table: `Package | Current | PHP 8.5 OK? | Action`. Prefer minimal bumps, avoid majors unless required. **Wait for approval before any dependency upgrade.**

### Phase 4 — CI (new)
`.github/workflows/ci.yml`, matrix PHP 8.2/8.3/8.4/8.5. Steps: `composer validate`, `composer install`, PHPUnit, PHPStan, PHPCompatibility (8.5 target). Dedicated commit.

### Phase 5 — Compatibility fixes
Per issue: root cause → why 8.5 exposes it → minimal safe fix → diff summary. Commits grouped by issue type.

### Phase 6 — Testing (TDD)
Reproduce → add failing test (where possible) → fix → verify green. Don't break existing 7 test files. Report new/fixed tests.

### Phase 7 — Static analysis
Add dev deps `phpstan/phpstan` + `phpcompatibility/php-compatibility` (+ phpcs). PHPStan baseline to avoid historical debt; fix only high-confidence 8.5-related issues.

### Phase 8 — Final verification
PHPUnit green on 8.2–8.5; app boots on php85; manual smoke checklist (admin: login/products/categories/orders; storefront: catalog/search/cart/checkout; system: uploads/emails/cron/cache). Final report: Summary / Files Changed / Dependencies Updated / Tests Added / CI Changes / Remaining Risks / PHP 8.5 Support Status.

## Risks

- `php:8.5-fpm` image / 8.5-compatible Xdebug availability on Docker Hub / PECL.
- Symfony 5 not officially targeting 8.5 → deprecation noise (with `convert*ToExceptions` in phpunit.xml this can fail tests; may need to scope deprecation handling).
- Smarty 3.1 / older deps may emit 8.5 deprecations requiring minimal bumps (approval-gated).

## Out of Scope

- General modernization, unrelated refactors, `strict_types`, major upgrades without compatibility need.
