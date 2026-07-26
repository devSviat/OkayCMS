# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

OkayCMS (fork) — PHP e-commerce platform with a modular architecture, custom DI container, and custom ORM.

## Stack

- PHP `^8.4` (verified on 8.4–8.5)
- Smarty `^4.5` (templating)
- Custom DI container (`Okay\Core\OkayContainer`)
- Custom ORM (`Okay\Core\Entity\Entity`)
- Symfony Console (the `./ok` CLI), Aura.Sql / Aura.SqlQuery (DB layer)

## Commands

```bash
cd dev && docker compose up -d         # start the local environment (Nginx + php-fpm + MariaDB)
php vendor/bin/phpunit                  # run all tests (config in phpunit.xml, suite dir: tests/)
php vendor/bin/phpunit tests/Core/      # run tests in a directory
php vendor/bin/phpunit --filter TplModTest   # run a single test class

./ok scheduler:run                     # run scheduled tasks (-f / --force ignores schedule & overlaps)
./ok scheduler:list                    # list scheduled tasks
./ok database:deploy                   # apply DB changes
./ok module:create                     # scaffold a new module
```

Run CLI/PHPUnit inside the PHP container if PHP is not on the host: `cd dev && docker compose exec php85 php vendor/bin/phpunit` (the `php85` service runs PHP 8.5; the app requires PHP ≥ 8.4).

Local env config lives in `dev/.env` (copy from `dev/.env-example`). On container creation the default DB dump `1DB_changes/okay_clean.sql` is loaded and the `admin` manager is (re)created.

Admin panel: `http://<VIRTUAL_HOST>/admin`, login `admin`, password `1234`.

App config: `config/config.php` + `config/config.local.php` (INI format; `dev_mode`, `debug_mode`, `smarty_force_compile` live here).

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

`docs/` is the authoritative reference for each component. Read the relevant doc **before** working with an unfamiliar component.

| What you need to do | Where to look |
| ------------------- | ------------- |
| Database operations / ORM | `docs/entities.md` |
| DI container, service registration | `docs/di_container.md`, `docs/service_locator.md` |
| Controllers (front/backend) | `docs/controllers.md` |
| Routes | `docs/routes.md` |
| Business logic (helpers) | `docs/helpers.md` |
| Collecting POST data | `docs/requests.md` |
| Creating / structuring a module | `docs/modules/quick_start.md`, `docs/modules/README.md`, `docs/modules/init.md` |
| Extending helpers/requests from a module | `docs/modules/extenders.md` |
| DB migrations | `docs/modules/table_migrate.md` |
| Modifying a `.tpl` without editing it | `docs/tpl_modifiers.md` |
| JS/CSS files | `docs/js_css_files.md` |
| Smarty plugins | `docs/smarty_plugins.md` |
| Scheduler | `docs/scheduler.md` |
| Discounts | `docs/discounts_management.md` |
| Security boundaries and upgrade notes | `docs/UPGRADE-security.md` |
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
- Default SELECT limit is 100 rows. Use `noLimit()` only when necessary.
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

- Don't include files via raw `<script>` / `<link>` tags. Use `design/js.php`, `design/css.php`, or the `{js}` / `{css}` Smarty plugins.

### Controllers

- Front controllers extend `AbstractController` (initializes design, languages, currencies).
- Backend controllers extend `IndexAdmin`; default method is `fetch()`.
- Module backend controller URL format: `?controller=Vendor.Module.ControllerName`.