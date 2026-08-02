# Rewriting `docs/` for this fork

Date: 2026-08-02
Scope: `docs/` (everything except `docs/superpowers/**`, `docs/images/`, `docs/nginx/`)
Branch: a branch per logical block, off `main`
Status: implemented across six stacked PRs

## Problem

`docs/` is inherited from upstream almost in full. Seventeen of the nineteen root files are
written in Russian and describe the system as it was before this fork. Only
`UPGRADE-security.md` and `theme-porting.md` were written here.

The problem is not the language. It is that the reference contradicts the code, and does so
silently — a reader has no way to tell a stale paragraph from a current one. Verified examples:

**`docs/core/Phone.md`** documents `format(string $phoneNumber, int $numberFormat)`. The code
(`Okay/Core/Phone.php:127`) is `format($phoneNumber, $numberFormat = null): string`, plus a new
`resolveFormat($numberFormat): PhoneNumberFormat` (`:161`) that exists because libphonenumber 9
turned `PhoneNumberFormat` into an enum. Anyone following the doc passes an int and gets a
`TypeError` at runtime.

**`docs/smarty_plugins.md`** teaches type-hinting `\Smarty_Internal_Template`. That class does
not exist in Smarty 5, and `tests/Core/SmartyPlugins/PluginSignatureTest.php:22` fails any plugin
that type-hints *any* class whose name starts with `Smarty`. The doc teaches a rule the test
suite rejects.

**`docs/README.md`** does not link `di_container.md`, `service_locator.md`, `scheduler.md` or
`UPGRADE-security.md`; `docs/core/README.md` does not link `Schedule.md`. Two of those —
`scheduler.md` and `core/Schedule.md` — cannot be reached from the index by any path at all;
the other three are reachable only by accident, through a link buried in the prose of an
unrelated page. This is the kind of breakage that is visible in a minute and lives for years.

**`docs/tpl_modifiers.md`** describes `Okay/Core/TemplateModifications/`. The directory is
`Okay/Core/TplMod/`, and the entry point is a Smarty `pre` filter (`Okay/Core/Design.php:196`).

**`docs/files.md`** is four heading lines with no body.

Beyond the outright errors, the fork has a delta the docs never mention at all: the storefront
security boundary (POST + `customer_csrf_token`), the debug bar, Smarty 5, Symfony Console 8,
aura/sqlquery 3, and the ORM's main-language behaviour.

## Goals

A reference where every statement is checked against the current code, written in Ukrainian,
structured around the dominant use case: *writing a module for this fork*.

Non-goals: documenting upstream's roadmap, keeping upstream's file names for their own sake,
and translating text that is going to be rewritten anyway.

## Decisions

**Language.** Ukrainian, matching `UPGRADE-security.md` and `theme-porting.md`. Technical
tokens (class names, paths, config directives, `docker compose`) stay as they are. `CLAUDE.md`
stays in English.

**Structure.** Rebuilt, not patched. Kebab-case filenames. `docs/modules/` splits into "how it
works" and "how to do X" — it is the section people actually read end to end.

**Pitfalls.** A "Пастки" section at the end of the relevant reference, plus a one-screen
`docs/troubleshooting.md` mapping symptom → cause → section. Keeping the explanation next to
the API it belongs to means editing the code and the warning in one place; the symptom index
exists for the reader who does not yet know which file to open.

**`docs/core/`.** Dissolved into the topical references. A five-file directory of thin class
pages was where two of the five orphans came from.

**No link-checking test.** The index and cross-links are verified by hand in each PR.

## Target structure

```
docs/
  README.md              index + "I want to do X → go here"
  architecture.md        layers, request lifecycle, bootstrap, the fork's security boundary
  configuration.md       config.php / config.local.php, directives, dev_mode, debug_mode, debug_bar
  di.md                  ← di_container.md + service_locator.md
  routes.md              ← routes.md
  controllers.md         ← controllers.md + core/Response.md
  entities.md            ← entities.md
  helpers.md             ← helpers.md (+ the ExtenderFacade contract)
  requests.md            ← requests.md
  templates.md           Design/Smarty 5, themes, static classes (← core/Phone.md)
  smarty-plugins.md      ← smarty_plugins.md
  tpl-modifications.md   ← tpl_modifiers.md
  assets.md              ← js_css_files.md + the CSS compiler and theme-settings.css
  cli.md                 ./ok, commands, scheduler (← scheduler.md + core/Schedule.md)
  testing.md             phpunit / phpstan / phpcs / smoke.sh
  troubleshooting.md     symptom index
  discounts.md           ← discounts_management.md + core/Discount.md
  import.md  export.md
  UPGRADE-security.md    kept as is
  theme-porting.md       kept as is
  modules/
    README.md            overview + section map
    quick-start.md       a module in 15 minutes
    structure.md         file layout, module.json, settings.xml, module config
    lifecycle.md         install / init / update_x_y_z, versions, boot order
    init-reference.md    the full AbstractInit reference
    extenders.md         ← modules/extenders.md
    migrations.md        ← modules/table_migrate.md + the EntityField API
    backend.md           admin controllers, permissions, menu (← core/ManagerMenu.md), blocks
    frontend.md          a storefront page from a module, templates, design blocks, plugins
```

Removed: `files.md`, `dev_mode.md`, `di_container.md`, `service_locator.md`, `scheduler.md`,
`js_css_files.md`, `tpl_modifiers.md`, `discounts_management.md`, `smarty_plugins.md`,
`modules/module_json.md`, `modules/init.md`, `modules/quick_start.md`,
`modules/table_migrate.md`, and all of `docs/core/`.

## The fork delta the docs must carry

Each row is verified; the file:line is where the claim comes from.

| Fact | Lands in |
| ---- | -------- |
| PHP `^8.4` (runs on 8.5), Smarty 5.8.4, symfony/console 8.1, phpunit 13.2, psr/log 3, guzzle 8, phpmailer 7, libphonenumber 9 | `architecture.md`, `testing.md`, `CLAUDE.md` |
| The base table mirrors the **main** language — the `ok_languages` row with the smallest `position` (`Okay/Core/Languages.php:41-52`). `update()` strips lang fields outside the main language (`Languages.php:167`), `add()` does not (`CRUD.php:143`) | `entities.md` + pitfall |
| "LIMIT 100 by default" is wrong in both directions: with neither `limit` nor `page` no LIMIT is emitted at all (`Okay/Core/Entity/filter.php:56-70`, `vendor/aura/sqlquery/src/Common/Select.php:150-157`) | `entities.md` |
| aura/sqlquery 3.0.0: positional `?` in `where()` is unusable; arrays in `$bind` are substituted textually via `str_replace` (`vendor/aura/sqlquery/src/AbstractQuery.php:429-441`), so no placeholder name may be a prefix of another. Real workaround: `Okay/Entities/FeaturesValuesEntity.php:483-499` | `entities.md` + pitfall |
| The DI container has no `%param%` / `@service` string syntax — only `new SR()` / `new PR()`; `{%settings%}` resolves in `calls` only, never in `arguments` (`Okay/Core/config/parameters.php:5-8`) | `di.md` |
| Storefront mutations: POST + `customer_csrf_token` (`Okay/Core/Security/CustomerCsrfToken.php`, `AbstractController::requireCustomerCsrf()` `:163`). Admin: POST + `session_id` (`AdminCsrfToken`, `Request::checkSession()` `:380`, `backend/index.php:58-64,212-218`) | `architecture.md`, `controllers.md`; consistent with `UPGRADE-security.md` and `theme-porting.md` |
| Debug bar: `debug_bar = true` **together with** `debug_mode` (`index.php:29-34`); php-debugbar 3.8.0 is a dev dependency; inline assets via `DebugBar::getInlineAssets()`; no custom widgets or JS remain | `configuration.md` |
| `QueryFormatter` and `PDOCollector` are a temporary workaround for php-debugbar#1072, to be deleted once 3.8.1 ships | `configuration.md`, stated with the deletion condition |
| Commands declare their name via `#[AsCommand]`; `static $defaultName` is gone (`tests/Core/Console/CommandNamesTest.php`); `Application::addCommand()` replaced `add()` | `cli.md` |
| A Smarty plugin must not type-hint the template object at all; native PHP functions are registered as modifiers from the `Design::$allowedPhpFunctions` whitelist | `smarty-plugins.md`, `templates.md` |
| `Okay\Controllers\AbstractController` (not `Okay\Core\`), and it is not `abstract`; the property is `$tableAlias` (not `$alias`); `$defaultActiveField`, `EntityRepository` and `EntityFieldsHelper` do not exist | `controllers.md`, `entities.md` |
| `ExtenderFacade::execute(__METHOD__, …)` is the common form (1186 sites), but traits and base classes must use `[static::class, __FUNCTION__]` (219 sites) | `helpers.md`, `modules/extenders.md` |
| `update_x_y_z()` methods also run on a clean install (`Okay/Core/Modules/Installer.php:62-68`); a version must be exactly three integers or `getMathVersion()` returns 0 | `modules/lifecycle.md` |
| `module.json` keys actually parsed: `version`, `vendor.email`, `vendor.site`, `Okay`, `modifications.{front,backend}`. `moduleName` is ignored. A modifier's value may name a tpl file (`Modules.php:237-246`) | `modules/structure.md`, `tpl-modifications.md` |
| `settings.xml` is read only for enabled payment and delivery modules; the payment template ignores `type` entirely | `modules/structure.md` |
| `./ok module:create` does not scaffold `Init/module.json` | `modules/quick-start.md` |

## Pitfalls to document

| Pitfall | Where | Source |
| ------- | ----- | ------ |
| TplMod truncates a template silently: `<` outside an opening `{if}`, a variable tag name, a tag opened inside one `{if}` branch and closed after `{/if}` | `tpl-modifications.md` | `Okay/Core/TplMod/Parser.php:60,64,88-91`; `tests/TplMod/ThemeTemplatesTplModTest.php:12-22` |
| The CSS compiler is line-based: one `var()` per line, `var(--x, fallback)` never resolves, `/*` inside `url()` starts a comment | `assets.md` | `Okay/Core/TemplateConfig/CssConfig.php:275-300,332-345` |
| Smarty 5 keeps assignments inside `{function}` local | `templates.md` | `tests/Design/NoCrossScopeFunctionVariableTest.php` |
| `debug_mode = true` prints a fatal into the body while the status stays 200 (`catch (\Exception)`, not `\Throwable`; the body is already flushed) | `configuration.md` | `index.php:97,103`; `Okay/Core/Router.php:236-245` |
| `{js}` routed through the Smarty plugin writes into `cache/css/` | `assets.md` | `Okay/Core/TemplateConfig/FrontTemplateConfig.php:416` |

## Delivery

Six PRs, one logical block each, every one branched off `main` and left in a self-consistent
state: no orphaned document and no dead link at any step. During the transition `docs/README.md`
lists both the rewritten Ukrainian documents and the inherited ones still awaiting their turn.

1. Foundation — this spec, `README.md`, `architecture.md`, `configuration.md`; drop `files.md`;
   fix the Stack section and the docs table in `CLAUDE.md`.
2. `docs/modules/` in full.
3. Core and data — `entities.md`, `di.md`, `routes.md`, `controllers.md`, `helpers.md`,
   `requests.md`.
4. Templates and assets — `templates.md`, `smarty-plugins.md`, `tpl-modifications.md`,
   `assets.md`; fix the dead upstream URL in `design/*/js.php` and `design/*/css.php`.
5. Tooling — `cli.md`, `testing.md`.
6. Business logic and the final pass — `discounts.md`, `import.md`, `export.md`,
   `troubleshooting.md`; remove `docs/core/`; verify the index, the cross-links and the
   agreement with `UPGRADE-security.md` and `theme-porting.md`.

## Verification bar

- Every statement is checked against the code, never against the previous version of the
  document.
- Every code sample is either copied from a real file in this repository, or its signatures,
  namespaces and parameter names are grepped in the file it claims to come from.
- Where the code spells something oddly, the doc spells it the same way:
  `them_settings_filename` (`Okay/Core/config/parameters.php:54`) and `int $timout`
  (`Okay/Core/Scheduler/Task.php:42`) are documented verbatim, not silently corrected.
- Before each PR: all relative links under `docs/` resolve, and every `.md` outside
  `docs/superpowers/` is reachable from `docs/README.md`.
- After each PR, with exit codes, all inside the container:
  `php vendor/bin/phpunit`, `php vendor/bin/phpcs`, `php vendor/bin/phpstan analyse`, and
  `dev/bin/smoke.sh` from the repo root.
