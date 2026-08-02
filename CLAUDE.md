# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

OkayCMS (fork) — PHP e-commerce platform with a modular architecture, custom DI container, and custom ORM.

## Branches

| Branch | What it is |
| ------ | ---------- |
| `main` | This fork's line of development. Default branch — every PR targets it. |
| `master` | A mirror of `OkayCMS/OkayCMS@master`. Nothing of ours is ever committed here. |
| `develop` | The upstream `develop` line, kept for the same reason. |

A fresh clone has no `upstream` remote — add it once:

```bash
git remote add upstream https://github.com/OkayCMS/OkayCMS.git
```

Pulling a new upstream release and seeing what it changes:

```bash
git fetch upstream
git push origin upstream/master:master   # move the mirror
git log --oneline main..origin/master    # what they added
git diff main...origin/master -- <path>  # what it means for us
```

Upstream changes are merged into `main` deliberately, file by file — this fork has rewritten
security boundaries, themes and the Docker environment, so a blind merge would undo them.

## Stack

- PHP `^8.4` (verified on 8.4–8.5)
- Smarty `^5.8` (templating) — `\Smarty_Internal_Template` is gone; plugins must not type-hint the template object
- Custom DI container (`Okay\Core\OkayContainer`)
- Custom ORM (`Okay\Core\Entity\Entity`)
- Symfony Console `^8.0` (the `./ok` CLI) — commands declare their name via `#[AsCommand]`
- Aura.Sql `^6.0` / Aura.SqlQuery `^3.0` (DB layer)
- PHPUnit `^13.2`, PHPStan `^2.2`, PHP_CodeSniffer `^3.7` (dev)

## Commands

```bash
cd dev && docker compose up -d         # start the local environment (Nginx + php-fpm + MariaDB)
dev/bin/smoke.sh                        # verify the environment came up correctly (run from repo root)
php vendor/bin/phpunit                  # run all tests (config in phpunit.xml, suite dir: tests/)
php vendor/bin/phpunit tests/Core/      # run tests in a directory
php vendor/bin/phpunit --filter TplModTest   # run a single test class

./ok scheduler:run                     # run scheduled tasks (-f / --force ignores schedule & overlaps)
./ok scheduler:list                    # list scheduled tasks
./ok database:deploy                   # apply DB changes
./ok module:create                     # scaffold a new module
```

Run CLI/PHPUnit inside the PHP container if PHP is not on the host: `cd dev && docker compose exec php85 php vendor/bin/phpunit` (the `php85` service runs PHP 8.5; the app requires PHP ≥ 8.4).

Local env config lives in `dev/.env` (copy from `dev/.env-example`). On container creation the default DB dump `1DB_changes/okay_clean.sql` is loaded and the `admin` manager is (re)created. The database now lives on a named Docker volume — `docker compose down -v` destroys it (no more leftover files under a bind-mounted directory).

Admin panel: `http://<VIRTUAL_HOST>/admin`, login `admin`, password `1234`.

App config: `config/config.php` + `config/config.local.php` (INI format; `dev_mode`, `debug_mode`, `smarty_force_compile` live here). `debug_bar = true` вмикає phpdebugbar на вітрині — лише в парі з `debug_mode = true`; шаблон із поясненням у `config/config.local-example.php`.

---

## Architecture

The codebase is split into three top-level code areas plus modules:

| Area | Path | Namespace | Purpose |
| ---- | ---- | --------- | ------- |
| Core | `Okay/Core/` | `Okay\Core` | Framework: DI, Entity ORM, Router, Design/Smarty, Scheduler, Console, Image, etc. |
| Frontend app | `Okay/Controllers`, `Okay/Entities`, `Okay/Helpers`, `Okay/Requests` | `Okay\*` | Storefront controllers, entities, business logic, POST collectors. |
| Backend (admin) app | `backend/Controllers`, `backend/Helpers`, `backend/Requests` | `Okay\Admin\*` | Admin panel. |
| Modules | `Okay/Modules/<Vendor>/<Module>/` | `Okay\Modules\<Vendor>\<Module>` | Pluggable features (this fork ships the `OkayCMS` vendor). |

Service wiring is centralized: core services in `Okay/Core/config/services.php` (and sibling `routes.php`, `helpers.php`, `requests.php`, `parameters.php`, `container.php`); each module wires its own services/routes in `Init/services.php`, `Init/routes.php`, `Init/parameters.php`.

`docs/` is the authoritative reference for each component. Read the relevant doc **before** working with an unfamiliar component. Start from `docs/README.md`, which is the index.

**Caveat:** the reference is being rewritten for this fork (see `docs/superpowers/specs/2026-08-02-docs-rewrite-design.md`). Documents marked 🇷🇺 in `docs/README.md` are still inherited from upstream and are known to contradict the code in places — verify against the source before relying on them.

| What you need to do | Where to look |
| ------------------- | ------------- |
| Understand the fork: stack, layout, request lifecycle | `docs/architecture.md` |
| Config directives, `dev_mode` / `debug_mode` / debug bar | `docs/configuration.md` |
| Database operations / ORM | `docs/entities.md` |
| DI container, service registration, ServiceLocator | `docs/di.md` |
| Controllers (front/backend) | `docs/controllers.md` |
| Routes | `docs/routes.md` |
| Business logic (helpers) | `docs/helpers.md` |
| Collecting POST data | `docs/requests.md` |
| Creating / structuring a module | `docs/modules/README.md` (section index), `docs/modules/quick-start.md`, `docs/modules/structure.md` |
| Module lifecycle: `install()` / `init()` / `update_x_y_z()` | `docs/modules/lifecycle.md` |
| Full `AbstractInit` method reference | `docs/modules/init-reference.md` |
| Extending helpers/requests from a module | `docs/modules/extenders.md` |
| DB migrations | `docs/modules/migrations.md` |
| Admin section from a module | `docs/modules/backend.md` |
| Storefront page from a module | `docs/modules/frontend.md` |
| Templates, themes, Smarty 5 pitfalls | `docs/templates.md` |
| Modifying a `.tpl` without editing it | `docs/tpl-modifications.md` |
| JS/CSS files | `docs/assets.md` |
| Smarty plugins | `docs/smarty-plugins.md` |
| CLI commands, scheduler | `docs/cli.md` |
| Tests, phpstan, phpcs, smoke checks | `docs/testing.md` |
| Discounts | `docs/discounts.md` |
| Security boundaries and upgrade notes | `docs/UPGRADE-security.md` |
| Porting a theme to this fork | `docs/theme-porting.md` |
| Import / Export | `docs/import.md`, `docs/export.md` |

> Path notation in docs: `Okay\Core\Response` = namespace (backslash); `Okay/Core/Response.php` = filesystem path (forward slash).

---

## Key Constraints

### Do not modify core or other modules

Do not edit core files, theme `.tpl` files, or another module's files. Extend behavior via an **Extender** (for PHP) or the `modifications` block in `module.json` (for templates).

### DI

- Inject dependencies via **type-hint** in constructor or method arguments — the container instantiates them.
- Route parameters in controllers come **without** type-hint (e.g. `$url`).
- Use `ServiceLocator` only where DI is unavailable (e.g. module `update_1_x_x()` upgrade methods).

### Entity (ORM)

- Never write raw SQL for `get`, `find`, `insert`, `update`, `delete` — the base `Entity` class handles them. Do not write raw SQL for CRUD at all.
- There is no implicit row cap: `find([])` emits **no** `LIMIT` at all. The 100-row default is the page size and only applies when `page` is passed without `limit`. Pass `limit` explicitly; `noLimit()` is rarely needed. See `docs/entities.md`.
- Language fields (`$langFields`) live in `__lang_{table}` and are joined automatically.
- Custom filter inside an Entity → declare a protected `filter__<name>($val, $filter)` method.
- To add a filter/field to another Entity from a module → `registerEntityFilter()` / `registerEntityField()` in `Init::init()`.

### Helpers and Requests

- A helper/request method must return its result **only** via `ExtenderFacade::execute(__METHOD__, $result, func_get_args())` — otherwise it cannot be extended by modules.

### Modules

- Required: `Init/Init.php` with `install()` and `init()`.
  - `install()` runs once on install (migrations, `setBackendMainController`). Call `setModuleType()` only for payment/delivery/XML modules (`MODULE_TYPE_PAYMENT`, `MODULE_TYPE_DELIVERY`, `MODULE_TYPE_XML`).
  - `init()` runs on every request (register controllers, extenders, menu items, entity fields).
  - Upgrade methods `update_1_2_0()` run when upgrading to that version.

### Extenders

- **ChainExtender**: receives the result, modifies it, **must return** it.
- **QueueExtender**: side effects only (email, logging), returns nothing.
- Extender args: 1st = helper's return value, 2nd+ = helper's original args.
- Must implement `ExtensionInterface`, be registered in `Init/services.php`, and live in `Extenders/FrontExtender.php` / `Extenders/BackendExtender.php`.

### TPL

- Don't edit theme/core `.tpl` files — use the `modifications` block in `module.json`.
- After changing `modifications`, clear the `compiled/` directory.
- For debugging modifications set `smarty_force_compile = true` in `config/config.local.php` (remove on prod).

### JS / CSS

- Don't include files via raw `<script>` / `<link>` tags. Use the theme's `design/<theme>/js.php` and `design/<theme>/css.php` (a module uses its own `design/js.php` / `design/css.php`), or the `{js}` / `{css}` Smarty plugins.

### Controllers

- Front controllers extend `AbstractController` (initializes design, languages, currencies).
- Backend controllers extend `IndexAdmin`; default method is `fetch()`.
- Module backend controller URL format: `?controller=Vendor.Module.ControllerName`.