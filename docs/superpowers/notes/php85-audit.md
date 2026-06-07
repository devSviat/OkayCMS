# PHP 8.5 Readiness Audit

**Date:** 2026-06-07
**Environment:** `php85` container, PHP 8.5.7 NTS + Xdebug 3.5.1.

## Tooling results

- `composer validate --strict`: valid; one pre-existing warning — `sabberworm/php-css-parser` uses unbound `*` constraint (not 8.5-related).
- `composer check-platform-reqs`: **all ext-* OK on 8.5**. Single failure: `php` — `phpspec/prophecy` requires `<8.2`.
- PHPUnit baseline on 8.5: **148/148 OK** (151 assertions) with `convertDeprecationsToExceptions=true`. → no 8.5 deprecations in code paths under test.

## Risk table

| # | File | Description | Severity | Recommended fix |
|---|------|-------------|----------|-----------------|
| 1 | `composer.lock` (dev) | `phpspec/prophecy 1.14.0` (transitive via `phpunit/phpunit 9.5.10`) requires `php <8.2`; blocks clean `composer install` on 8.5. Workaround used for baseline: `--ignore-platform-req=php`. | High (blocks CI install) | Upgrade dev deps: `phpunit/phpunit` to a 8.5-capable line (^10/^11) which pulls a compatible prophecy, OR drop prophecy. Approval-gated (Phase 3). |
| 2 | `Okay/Helpers/FilterHelper.php` (6×: L269,295,320,348,378,408), `Okay/Helpers/CatalogHelper.php:301`, `Okay/Helpers/CategoriesHelper.php:89`, `Okay/Core/Response.php:167`, `Okay/Core/Money.php:57,110` | Implicit-nullable params (`string $x = null`, `int $precision = null`) — deprecated since PHP 8.4, still deprecated in 8.5. Emits `E_DEPRECATED` at compile time. | Medium | Add explicit `?` to each typed param: `?string $x = null`, `?int $precision = null`. Pure signature change, no behavior change. 11 sites. |
| 3 | `backend/design/js/filemanager/include/utils.php:743` | `utf8_encode()` deprecated since PHP 8.2. | Low | Replace with `mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1')`. Bundled filemanager tool; only on certain uploads. |
| 4 | `backend/design/js/filemanager/include/utils.php:1326`, `.../UploadHandler.php:319`, `.../php_image_magician.php:863` | `version_compare(PHP_VERSION, ...)` legacy checks (5.x era). | Info | Harmless on 8.5 (comparisons only). No change required. |

**Not problems (verified):**
- `each(` hits in `backend/design/js/filemanager/dialog.php` are jQuery `.each()` inside JS, not PHP `each()`.
- `__get`/`__set` in `Okay/Core/{Settings,Config,FrontTranslations,BackendTranslations}.php` are legitimate magic methods — these classes are dynamic-property safe.
- `phpversion()` in `backend/Controllers/SystemAdmin.php:13` is display-only.

**To be confirmed by PHPCompatibility (Phase 7):** broad dynamic-property writes (deprecated 8.2+) on classes lacking `__set`/`#[AllowDynamicProperties]`, and any 8.5-specific deprecations not surfaced by grep.

## composer outdated --direct (raw)

Most packages have a major available, but "major available" ≠ "current breaks on 8.5". Compatibility assessed per-package in the dependency table below (Phase 3).

## Dependency compatibility table (Phase 3)

`composer check-platform-reqs` confirms **every runtime dependency composer-allows PHP 8.5** (no `<8.x` constraint). Only `phpspec/prophecy` (dev, transitive) blocks. "Major available" in `outdated` ≠ "current breaks on 8.5".

| Package | Current | PHP 8.5 compatible? | Action required |
|---------|---------|---------------------|-----------------|
| **phpunit/phpunit** (dev) | 9.5.10 | No (pulls prophecy <8.2) | **Required.** `composer update phpunit/phpunit --with-all-dependencies` → 9.6.34, **removes prophecy** entirely (optional since 9.6). Stays on phpunit 9.x, no `phpunit.xml` migration. |
| **phpspec/prophecy** (dev, transitive) | 1.14.0 | No (`php <8.2`) | Removed by the phpunit 9.6 update above. Not used by the 6 existing tests. |
| smarty/smarty | 3.1.40 | Functional (renders OK), but floods implicit-nullable deprecations | **Recommended** minor bump within major: `^3.1.48` (3.1.48 added PHP 8.2 support; later 3.1.x fixed implicit-nullable). Silences deprecation noise. No breaking changes. |
| symfony/console | 5.3.7 | Yes (runs) | Optional: bump to latest 5.4.x (minor, LTS) for best 8.x support. Low priority. |
| symfony/process | 5.3.7 | Yes | Optional minor bump to 5.4.x. |
| symfony/lock | 5.3.4 | Yes | Optional minor bump to 5.4.x. |
| symfony/var-dumper (dev) | 5.4.23 | Yes | None. |
| monolog/monolog | 1.26.1 | Yes (runs); 1.x is EOL | None required. Possible deprecation noise; major (2/3) is a breaking upgrade — skip unless it breaks. |
| aura/sql | 3.0.0 | Yes | None. |
| aura/sqlquery | 2.7.1 | Yes | None. |
| gregwar/image | 2.x | Yes | None. |
| phpmailer/phpmailer | 6.5.1 | Yes | None. |
| matthiasmullie/minify | 1.3.66 | Yes | None. |
| giggsey/libphonenumber-for-php | 8.12.36 | Yes | None. |
| dragonmantank/cron-expression | 3.1.0 | Yes | None. |
| bramus/router | 1.6 | Yes | None. |
| haydenpierce/class-finder | 0.3.3 | Yes | None. |
| sabberworm/php-css-parser | 8.3.1 | Yes | None (unbound `*` constraint is a pre-existing hygiene warning). |
| psr/log, psr/container | 1.1.x | Yes | None. |
| rosell-dk/webp-convert | 2.7.0 | Yes | None. |
| mobiledetect/mobiledetectlib | 2.8.37 | Yes | None. |
| axy/sourcemap | 0.1.5 | Yes | None. |
| snowplow/referer-parser | 0.2.0 | Yes | None. |
| orhanerday/open-ai | 5.0 | Yes | None. |
| maximebf/debugbar (dev) | 1.17.2 | Yes (abandoned) | None for 8.5. |

**Summary of proposed dependency changes (approval-gated):**
1. **Required:** update `phpunit/phpunit` → 9.6.34 (drops prophecy). Unblocks clean `composer install` / CI on 8.5.
2. **Recommended:** bump `smarty/smarty` constraint to `^3.1.48` to silence the implicit-nullable deprecation flood.
3. **Optional (low priority):** bump `symfony/console|process|lock` to latest 5.4.x.

## Test coverage observation (per user request)

Coverage is effectively absent: **6 test files** for **620 PHP files in `Okay/`** + **216 in `backend/`** (<1%). Existing tests: MetaRobotsHelper, CanonicalHelper, TplMod, TplModParser, ModulesInstaller, AbstractExtender. `Okay/Helpers` (48 files), `Okay/Core` (220), `Okay/Entities` (46), `Okay/Controllers` (23) are almost entirely untested. The 8.5 fixes touch `Money`, `Response`, `FilterHelper`, `CatalogHelper`, `CategoriesHelper` — all currently untested. See scope decision in the conversation.
