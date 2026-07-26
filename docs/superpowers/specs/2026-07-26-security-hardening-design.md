# Security Hardening — Design Spec

**Date:** 2026-07-26
**Repository:** OkayCMS fork (devSviat/OkayCMS)
**Goal:** Close the confirmed security defects found in the 4.5.2-derived codebase. No unrelated modernization.

## Scope & Constraints

- ONLY security work. No dependency modernization (Smarty 5, Symfony 8, Intervention Image, etc.) — those are a separate iteration with a different risk profile.
- Breaking changes ARE allowed where a defect cannot otherwise be closed, but each one must ship with a documented migration path.
- Every boundary gets test coverage under `tests/Security/`. The existing 176-test suite must stay green.
- No database changes: no schema migration, no new column, no new file in `1DB_changes/`. Enforced by a guard test.
- No `strict_types` sweep, no business-logic refactoring beyond what a fix requires.
- Small logical commits, grouped by phase.
- PHPUnit stays at 9.6 — use docblock annotations, not PHP 8 attributes.

## Environment

Local stack is `dev/docker-compose.yml` (nginx + php85 + mariadb), PHP 8.5.8, storefront on `http://okaycms.loc`.
`config/config.local.php` is gitignored and points at the `mariadb` service (`db_server = mariadb`, `db_name = okay`, root/root).

Commands:

```bash
cd dev && docker compose up -d
docker compose exec php85 composer install
docker compose exec php85 php vendor/bin/phpunit
docker compose exec php85 php vendor/bin/phpunit tests/Security
```

## Confirmed Defects

Severity reflects exploitability against a default installation.

### Critical

| # | Location | Defect |
| - | -------- | ------ |
| 1 | `Okay/Controllers/UserController.php:192` | Following a password-recovery link sets `$_SESSION['user_id']` before any password change. Anyone who obtains the link (referrer leak, mail forward, proxy log) takes over the account. |
| 2 | `backend/Controllers/AuthAdmin.php:55` | When the recovery `update()` fails, the flow calls `$managersEntity->add(['login' => $new_login, ...])`, creating a manager with an attacker-supplied login. |
| 3 | `backend/design/js/filemanager/{upload,execute,ajax_calls,force_download}.php` | Guarded only by the `$_SESSION['RF']['verify']` flag — no authenticated-manager check. `upload.php:75` fetches an arbitrary remote URL via `curl_init($url)` (SSRF). `svg` is in the `ext_img` upload whitelist with no sanitization (stored XSS). |
| 4 | Feeds, 14 preset adapters | `settings['filter_price']['operator']` and `settings['filter_stock']['operator']` reach SQL directly from POST/stored settings. SQL injection. |
| 5 | `Okay/Core/Managers.php:276` | Manager passwords use APR1-MD5. `checkPassword()` does `explode('$', $hash)[2]` and warns/misbehaves on a malformed stored hash. |
| 6 | `Okay/Entities/UsersEntity.php:81,97,124` | Customer passwords stored as `md5($salt . $password . md5($password))`. |
| 7 | `Okay/Helpers/MainHelper.php:466` | `Response::redirectTo($request->post('prg_seo_hide'))` — unvalidated open redirect. |
| 17 | `Okay/Modules/OkayCMS/WayForPay/Controllers/CallbackController.php:81` | `if (!empty($data->merchantSignature) && $data->merchantSignature != $sign)` — omitting the signature skips verification entirely, so an unauthenticated POST marks an order paid. Line 74 additionally calls `array_key_exists()` on a `stdClass`, which is a `TypeError` on PHP 8, so signed callbacks fatal. |
| 18 | `Okay/Modules/OkayCMS/RozetkaPay/Controllers/CallbackController.php` | No signature verification at all; the only barrier is a matching `$data->id`. Line 58 uses `&&` where `||` is meant, so an order paid through another module passes the payment-method check. |

### Medium

| # | Location | Defect |
| - | -------- | ------ |
| 8 | `index.php:19`, `backend/index.php:40`, +7 more | `session_name(md5($_SERVER['HTTP_USER_AGENT']))` — storefront and admin share one session namespace; no `session_regenerate_id()` on privilege transitions. |
| 9 | `Okay/Core/Request.php:375` | The admin CSRF token is `session_id()` embedded in POST bodies; the check runs after `$_POST` has already been read by callers. Storefront mutations (cart, wishlist, comparison, comments, feedback, subscribe) have no CSRF protection at all. |
| 10 | 12 `setcookie()` call sites | No `httponly`, `secure` or `samesite`. |
| 11 | `Okay/Core/Recaptcha.php:37` | `invalid-input-secret` returns `true` — misconfigured keys silently disable the captcha. |
| 12 | `backend/design/html/auth.tpl:34,58` | `{$smarty.server.HTTP_HOST}` rendered unescaped on a pre-auth page. |
| 13 | `Okay/Core/Response.php:86` | `X-Powered-CMS: OkayCMS <version>` discloses the exact version; no `X-Frame-Options`, `X-Content-Type-Options` or `Referrer-Policy`. |
| 14 | `AuthAdmin.php:32`, `UserController` | Account enumeration via distinct `not_admin_email` / `user_not_found` responses. |
| 15 | 10 call sites | `unserialize()` over database values (manager menus, module settings) without `allowed_classes`. |
| 16 | `Integration1cController.php:57,122` | `filename` from GET flows into a filesystem path. |
| 19 | `backend/files/index.php:39-47` | Only `$file` is sanitized; `$folder` and `$ext` come from GET unfiltered, so `$folder` traverses out of `backend/files/` (`readfile()` is still limited to the csv/image extension branches). Any authenticated manager can download any export regardless of permissions, and `$_SESSION['admin']` is read without an isset guard. |

LiqPay and Fondy verify their callback signatures correctly and need no change.

> **Update during execution:** defects 17 and 18 were dropped from scope. Neither
> payment module is in use, so the maintainer chose not to change them. Both stay
> documented here and in `docs/UPGRADE-security.md` as known-open, so enabling
> either module is an informed decision rather than a surprise.

## Architecture

New namespace `Okay\Core\Security\`. Each class has one responsibility, a narrow public surface, and no dependency on request globals beyond what it is given — so each is unit-testable in isolation.

| Class | Responsibility |
| ----- | -------------- |
| `CustomerCsrfToken` | Storefront CSRF token: `get()`, `check()`, `rotate()`. Backed by session state with a SameSite cookie fallback so the token survives a session-namespace change. |
| `SessionNames` | `okay_sid` / `okay_admin_sid` constants, `startFrontend()`, `startBackend()`, `regenerate()`, `cookieParams()`, `isHttps()`. |
| `AdminCsrfToken` | Backend CSRF token held in `$_SESSION['id']`, so the ~30 admin templates that already print `{$smarty.session.id}` need no edit while the value stops being the session id. |
| `RecoveryToken` | Customer recovery: opaque token generation, `digest()`, TTL handling, `isValidFormat()`. |
| `AdminRecoveryToken` | Manager recovery: stateless token carrying manager id and expiry, signed with an HMAC bound to the current password hash. |
| `SafeRedirect` | `isSameOrigin(?string $url, string $baseUrl): bool` — rejects protocol-relative `//`, backslashes, control characters, userinfo tricks, non-HTTP schemes and foreign hosts, after double `rawurldecode()`. |
| `PasswordHasher` | `hash()` (Argon2id, bcrypt fallback), `verify()` with legacy branches, `needsRehash()`. |
| `SecurityHeaders` | Baseline response headers. |
| `SvgSanitizer` | Element/attribute allowlist over `DOMDocument`; strips scripts, event handlers and dangerous URL schemes. |
| `Filemanager\PathResolver` | Resolves a request-supplied path against the upload root; rejects traversal, absolute paths, scheme paths and NUL bytes. |
| `Filemanager\AccessGuard` | Authenticated-manager + permission check for every filemanager entrypoint. |
| `BackendFileDownloadPolicy` | Maps folder + file + extension to the permission required to download it. |

One class lives outside `Okay\Core\Security\` because it is module-specific: `Okay\Modules\OkayCMS\RozetkaPay\Core\CallbackSignature` signs and verifies the callback URL that authenticates inbound RozetkaPay notifications.

### Key decision: no database changes at all

This is a hard constraint on the whole iteration, not just a preference about tables. No `ALTER TABLE`, no `CREATE TABLE`, no new column, no index change, no file added to `1DB_changes/`. A guard test enforces it and the final verification pass diffs the live schema against a baseline.

Module `update_x_y_z()` upgrade methods are not restricted — they are the architecture's normal migration mechanism. This iteration simply has no reason to add one, and the live-schema diff is what would catch it if any code did change the database.

Every value written fits a column that already exists, verified against the running database:

| Column | Type | Largest value written | Fits |
| ------ | ---- | --------------------- | ---- |
| `ok_managers.password` | `varchar(255)` | Argon2id hash, 97 chars | yes |
| `ok_users.password` | `varchar(255)` | Argon2id hash, 97 chars | yes |
| `ok_users.remind_code` | `varchar(32)` | truncated sha256 digest, exactly 32 chars | yes |
| `ok_orders.payment_details` | `mediumtext` | callback JSON, shape unchanged | yes |

That constraint is what shapes the two recovery designs below.

Customer recovery moves from "token in `remind_code`, trusted on sight" to "digest in `remind_code`, exchanged for a reset state". The digest reuses the existing `remind_code` column and `remind_expire` TTL. Because `ok_users.remind_code` is `varchar(32)`, the stored digest is `sha256` of the token truncated to 32 hex characters — 128 bits, which is ample for the preimage resistance this lookup needs, and it avoids an `ALTER TABLE`.

`ok_managers` has no recovery columns at all, so manager recovery uses a stateless signed token instead: manager id and expiry, authenticated by an HMAC keyed with the config salt and bound to the manager's *current* password hash. The token therefore self-invalidates the moment the password changes, giving one-time use with no storage.

Both flows upgrade by deploying code only — no schema migration.

Password migration is likewise in-place: legacy hashes stay valid for verification and are transparently rehashed to Argon2id on the next successful login. No forced reset, no schema change (the `password` column already holds a variable-length hash).

## Phases

### Phase A — Passwords (defects 5, 6)

`PasswordHasher` becomes the single verification path for both `Okay\Core\Managers` and `Okay\Entities\UsersEntity`:

1. `password_verify()` first — covers Argon2id and bcrypt.
2. Legacy branches, each gated on a format check so a malformed stored hash returns `false` instead of emitting warnings: APR1 (`$apr1$` + 8-char salt), salted MD5 (`md5($salt . $password . md5($password))`), raw MD5.
3. On a successful legacy verification, rehash with `password_hash()` and persist.

New and changed passwords are written only through `hash()`. `cryptApr1Md5()` stays for verification of existing hashes and is no longer used to produce them.

### Phase B — Recovery (defects 1, 2, 14)

Following a recovery link no longer authenticates. It validates the token digest, stores a short-lived reset state, and renders the new-password form. Login happens only after a successful change:

- Empty or whitespace-only password is rejected (`password_empty`).
- Mismatched confirmation is rejected (`password_wrong`).
- The token is consumed *before* the session is elevated, so a replayed link is inert.
- `$managersEntity->add(['login' => $new_login, ...])` is removed; recovery operates strictly on the manager bound to the token.

Recovery *requests* always render the same "email sent" outcome regardless of whether the address exists, removing the enumeration oracle in both flows.

### Phase C — Sessions and CSRF (defects 8, 9)

`session_name(md5($_SERVER['HTTP_USER_AGENT']))` is replaced across all 9 call sites: `okay_sid` for the storefront, `okay_admin_sid` for the backend and its ajax/filemanager entrypoints. `session_regenerate_id(true)` runs after manager login, customer login, recovery login and logout.

Storefront mutations require `POST` plus a valid `customer_csrf_token`:

- `GET` against a mutation endpoint returns 405.
- A missing or wrong token returns 403.
- Covered endpoints: cart add/remove/quantity/coupon, checkout, wishlist, comparison, comments, feedback, subscribe.

Theme forms in `design/okay_shop/html/` gain a hidden `customer_csrf_token` field; AJAX callers send it explicitly. Editing the bundled theme is correct here — the "don't edit theme templates" rule in `CLAUDE.md` governs modules extending a theme, not the product's own theme in its own repository.

The admin guard in `Request::checkSession()` stops using `session_id()` as the token and runs before any mutation reads `$_POST`.

### Phase D — Filemanager (defect 3)

- `include/okay_access.php` is required as the first statement of `dialog.php`, `upload.php`, `execute.php`, `ajax_calls.php` and `force_download.php`. It resolves the current manager and aborts with 403 when the manager is absent or lacks the needed permission.
- Remote URL upload (`curl_init($url)` in `upload.php`) is removed. The filemanager no longer fetches arbitrary server-side URLs.
- SVG uploads stay allowed but pass through `SvgSanitizer` before the file is written. The sanitizer works on an allowlist: permitted elements and attributes survive, everything else is dropped — `<script>`, `<foreignObject>`, `on*` handlers, and `href`/`xlink:href` values whose scheme is not `http`, `https` or a relative path. A file that fails to parse is rejected.
- `copy`, `cut`, `chmod`, preview, download, upload and archive extraction resolve their local paths through `PathResolver` instead of concatenating request parameters.
- `backend/files/index.php` (defect 19) resolves `folder` and `ext` through `BackendFileDownloadPolicy` and the resulting path through `PathResolver`. Unknown folder/file/extension combinations are denied, the download is bound to the permission the policy names, and `$_SESSION['admin']` is read defensively.

### Phase E — Injection and redirects (defects 4, 7, 15, 16)

- `normalizeComparisonOperator()` lands on `AbstractPresetAdapter` and `AbstractBackendPresetAdapter` with the allowlist `['<', '>', '=']` and a safe default. All 14 adapters route both the runtime read and the backend write through it.
- `MainHelper::activatePRG()` redirects only when `SafeRedirect::isSameOrigin()` passes.
- Every `unserialize()` over database content gets `['allowed_classes' => false]`.
- `Integration1cController` resolves `filename` through `PathResolver` before touching the filesystem.

### Phase F — Headers, cookies, captcha (defects 10, 11, 12, 13)

- `X-Powered-CMS` drops the version component.
- `SecurityHeaders` adds `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` to HTML responses.
- All 12 `setcookie()` call sites move to the options-array form with `httponly`, `samesite=Lax`, and `secure` when the request is HTTPS.
- `auth.tpl` escapes `HTTP_HOST`, `$login` and `$recovery_login`.
- reCAPTCHA fails closed on `invalid-input-secret` and writes a diagnostic log entry so the misconfiguration is visible.

### Phase G — Payment callbacks (defects 17, 18)

**WayForPay.** The signature becomes mandatory: a callback without `merchantSignature` is rejected with 400. Comparison uses `hash_equals()`. The signature payload is collected by reading object properties (`property_exists`/`isset`) rather than `array_key_exists()` on a `stdClass`, which fixes the PHP 8 `TypeError` in the same change.

**RozetkaPay.** The gateway's inbound signature scheme is not something this codebase can verify from the module alone, so authentication moves to a mechanism fully under our control: `CreatePayment` appends an HMAC over the order id (keyed with `rozetkapay_secretkey`) to `callback_url`, and the callback verifies it with `hash_equals()` before doing anything else. The broken `empty($method) && $method->module !== ...` condition becomes `||`.

## Testing

`tests/Security/` mirrors the existing suite's conventions (PHPUnit 9.6, plain `TestCase`, no attributes). One class per boundary:

| Test | Covers |
| ---- | ------ |
| `NoDatabaseChangeTest` | No migration file added to `1DB_changes/`, no DDL in new security code |
| `PasswordHasherTest` | Argon2id/bcrypt round-trip, each legacy branch, malformed hashes rejected without warnings, `needsRehash()` for legacy formats |
| `ManagerPasswordTest` | `Managers` delegates to the hasher; malformed stored hash returns false without warnings |
| `CustomerPasswordTest` | `UsersEntity` no longer matches hashes in SQL and rehashes legacy formats |
| `RecoveryTokenTest` | Customer digest and TTL; manager token identity, password-hash binding, expiry, tampering |
| `AdminRecoveryFlowTest` | Recovery bound to the token, no manager creation, empty password rejected before login, no enumeration |
| `CustomerRecoveryFlowTest` | Recovery link does not authenticate, digest stored, token consumed before elevation, no enumeration |
| `SessionNamesTest` | Namespaces differ, cookie params hardened, no entrypoint derives the name from the User-Agent |
| `AdminCsrfTest` | Token is not the session id, fails closed, rotates, guard runs before dispatch |
| `CustomerCsrfTokenTest` | Token is not the session id, fails closed on null/wrong, rotates, survives session reset via cookie |
| `StorefrontCsrfGuardTest` | Every mutation controller invokes the guard; theme forms carry the token |
| `FilemanagerPathResolverTest` | Traversal, absolute, scheme and NUL-byte paths rejected; legitimate nested paths resolved |
| `SvgSanitizerTest` | Scripts, `on*` handlers and `javascript:` hrefs stripped; benign shapes preserved; unparsable input rejected |
| `FilemanagerAccessTest` | Each of the 5 entrypoints requires the guard; remote upload gone; SVG sanitized |
| `BackendFileDownloadPolicyTest` | Known exports map to permissions; unknown folder/file/extension denied |
| `FeedFilterOperatorTest` | Allowlist is exactly `<`, `>`, `=`; all 14 adapters normalize |
| `SafeRedirectTest` | Same-origin allowed; `//host`, backslash, `javascript:`, encoded and foreign-host variants rejected |
| `UnserializeHardeningTest` | No call site deserializes without `allowed_classes`; 1C filenames resolved |
| `SecurityHeadersTest` | Baseline headers present; version stripped from `X-Powered-CMS` |
| `CookieAttributesTest` | Every `setcookie()` uses the options form with `httponly` and `samesite` |
| `RecaptchaFailClosedTest` | `invalid-input-secret` yields a failed check and is logged |
| `AdminAuthTemplateEscapingTest` | `auth.tpl` escapes host and login values |
| `WayForPayCallbackTest` | Signature mandatory, verified before payment, no `array_key_exists()` on an object |
| `RozetkaPayCallbackTest` | Callback URL signed and verified before payment; payment-method check fixed |
| `UpgradeNotesTest` | Upgrade notes cover every breaking change |

Regression guard: the existing 176 tests must remain green after every phase.

## Risks and Migration

**All sessions are invalidated once.** Splitting `session_name` logs out every active customer and manager on deploy. One-time, unavoidable — the shared namespace is the defect.

**Custom themes and third-party modules that POST to storefront mutation endpoints will break** until they send `customer_csrf_token`. This is the widest-reaching change in the set.

**Filemanager remote-URL upload disappears.** Any workflow that relied on pasting a URL must upload the file instead.

**SVG uploads are rewritten, not passed through.** Animation and scripting inside uploaded SVGs will not survive sanitization.

`docs/UPGRADE-security.md` documents each of these with the concrete change a theme or module author has to make.

## Out of Scope

- Dependency modernization (Smarty 5, Symfony 8, PHPMailer 7, Intervention Image, `wikimedia/minify`).
- Replacing abandoned packages still in use: `haydenpierce/class-finder` (`Okay/Core/Languages.php:255`), `snowplow/referer-parser`, `axy/sourcemap`. They function correctly on PHP 8.5 today.
- Content Security Policy. It needs a per-theme inventory of inline scripts and belongs in its own iteration.
- Rate limiting and brute-force lockout beyond the existing `cnt_try` / `last_try` mechanism.
