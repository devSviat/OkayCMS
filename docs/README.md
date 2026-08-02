# Документація OkayCMS (форк)

Довідник описує **цей форк**, а не апстрім. Почніть із [architecture.md](architecture.md) —
там стек, карта каталогів і життєвий цикл запиту.

> **Розділ переписується.** Документи з позначкою «не переписано» успадковані від апстріму й ще не
> звірені з кодом цього форку — місцями вони описують поведінку, якої тут уже немає. План
> переписування — [superpowers/specs/2026-08-02-docs-rewrite-design.md](superpowers/specs/2026-08-02-docs-rewrite-design.md).

## Хочу зробити X

| Треба | Куди |
| ----- | ---- |
| Зрозуміти, як усе влаштоване | [architecture.md](architecture.md) |
| Підняти локальне оточення | [`dev/README.md`](../dev/README.md) |
| Увімкнути відладку, розібратись із директивами | [configuration.md](configuration.md) |
| Написати модуль | [modules/quick_start.md](modules/quick_start.md) (не переписано), [modules/README.md](modules/README.md) (не переписано) |
| Змінити чужий шаблон, не редагуючи його | [tpl_modifiers.md](tpl_modifiers.md) (не переписано) |
| Вклинитись у чужу логіку з модуля | [modules/extenders.md](modules/extenders.md) (не переписано) |
| Працювати з базою | [entities.md](entities.md) (не переписано) |
| Додати сторінку вітрини | [routes.md](routes.md) (не переписано), [controllers.md](controllers.md) (не переписано) |
| Підключити CSS чи JS | [js_css_files.md](js_css_files.md) (не переписано) |
| Перенести свою тему на форк | [theme-porting.md](theme-porting.md) |
| Зрозуміти, що змінила ітерація безпеки | [UPGRADE-security.md](UPGRADE-security.md) |

## Довідник

### Основи

* [Як влаштований цей форк](architecture.md) — стек, каталоги, життєвий цикл запиту, межі безпеки
* [Налаштування](configuration.md) — `config.php` / `config.local.php`, `dev_mode`, `debug_mode`, панель відладки
* [Ядро системи (Core)](core/README.md) — не переписано
* [Режим розробника](dev_mode.md) — не переписано, увійшло в [configuration.md](configuration.md), файл лишається до переписування розділу модулів

### Застосунок

* [Контролери](controllers.md) — не переписано
* [Маршрути](routes.md) — не переписано
* [Сутності (Entities)](entities.md) — не переписано
* [Helpers](helpers.md) — не переписано
* [Requests](requests.md) — не переписано
* [DI-контейнер](di_container.md) — не переписано
* [Service Locator](service_locator.md) — не переписано

### Дизайн і шаблони

* [Модифікація tpl-файлів](tpl_modifiers.md) — не переписано
* [Smarty-плагіни](smarty_plugins.md) — не переписано
* [Підключення зовнішніх файлів дизайну](js_css_files.md) — не переписано
* [Перенесення теми на цей форк](theme-porting.md)

### Модулі

* [Модульність](modules/README.md) — не переписано
* [Модуль, швидкий старт](modules/quick_start.md) — не переписано
* [module.json](modules/module_json.md) — не переписано
* [Ініціалізація модуля](modules/init.md) — не переписано
* [Розширення (extenders)](modules/extenders.md) — не переписано
* [Міграції таблиць](modules/table_migrate.md) — не переписано

### Експлуатація

* [Планувальник](scheduler.md) — не переписано
* [Імпорт](import.md) — не переписано
* [Експорт](export.md) — не переписано
* [Робота зі знижками](discounts_management.md) — не переписано
* [Приклад конфігурації Nginx](nginx/nginx.conf)

### Безпека

* [Оновлення: посилення безпеки](UPGRADE-security.md) — що змінилось і що з цим робити

---

`docs/superpowers/` — це історія планів і специфікацій, а не довідник.
