# Документація OkayCMS (форк)

Довідник описує **цей форк**, а не апстрім. Почніть із [architecture.md](architecture.md) —
там стек, карта каталогів і життєвий цикл запиту.

> **Розділ переписується.** Документи, позначені 🇷🇺, успадковані від апстріму й ще не
> звірені з кодом цього форку — місцями вони описують поведінку, якої тут уже немає. План
> переписування — [superpowers/specs/2026-08-02-docs-rewrite-design.md](superpowers/specs/2026-08-02-docs-rewrite-design.md).

## Хочу зробити X

| Треба | Куди |
| ----- | ---- |
| Зрозуміти, як усе влаштоване | [architecture.md](architecture.md) |
| Підняти локальне оточення | [`dev/README.md`](../dev/README.md) |
| Увімкнути відладку, розібратись із директивами | [configuration.md](configuration.md) |
| Написати модуль | [modules/quick_start.md](modules/quick_start.md) 🇷🇺, [modules/README.md](modules/README.md) 🇷🇺 |
| Змінити чужий шаблон, не редагуючи його | [tpl_modifiers.md](tpl_modifiers.md) 🇷🇺 |
| Вклинитись у чужу логіку з модуля | [modules/extenders.md](modules/extenders.md) 🇷🇺 |
| Працювати з базою | [entities.md](entities.md) 🇷🇺 |
| Додати сторінку вітрини | [routes.md](routes.md) 🇷🇺, [controllers.md](controllers.md) 🇷🇺 |
| Підключити CSS чи JS | [js_css_files.md](js_css_files.md) 🇷🇺 |
| Перенести свою тему на форк | [theme-porting.md](theme-porting.md) |
| Зрозуміти, що змінила ітерація безпеки | [UPGRADE-security.md](UPGRADE-security.md) |

## Довідник

### Основи

* [Як влаштований цей форк](architecture.md) — стек, каталоги, життєвий цикл запиту, межі безпеки
* [Налаштування](configuration.md) — `config.php` / `config.local.php`, `dev_mode`, `debug_mode`, панель відладки
* [Ядро системи (Core)](core/README.md) 🇷🇺
* [Режим розробника](dev_mode.md) 🇷🇺 — увійшло в [configuration.md](configuration.md), файл лишається до переписування розділу модулів

### Застосунок

* [Контролери](controllers.md) 🇷🇺
* [Маршрути](routes.md) 🇷🇺
* [Сутності (Entities)](entities.md) 🇷🇺
* [Helpers](helpers.md) 🇷🇺
* [Requests](requests.md) 🇷🇺
* [DI-контейнер](di_container.md) 🇷🇺
* [Service Locator](service_locator.md) 🇷🇺

### Дизайн і шаблони

* [Модифікація tpl-файлів](tpl_modifiers.md) 🇷🇺
* [Smarty-плагіни](smarty_plugins.md) 🇷🇺
* [Підключення зовнішніх файлів дизайну](js_css_files.md) 🇷🇺
* [Перенесення теми на цей форк](theme-porting.md)

### Модулі

* [Модульність](modules/README.md) 🇷🇺
* [Модуль, швидкий старт](modules/quick_start.md) 🇷🇺
* [module.json](modules/module_json.md) 🇷🇺
* [Ініціалізація модуля](modules/init.md) 🇷🇺
* [Розширення (extenders)](modules/extenders.md) 🇷🇺
* [Міграції таблиць](modules/table_migrate.md) 🇷🇺

### Експлуатація

* [Планувальник](scheduler.md) 🇷🇺
* [Імпорт](import.md) 🇷🇺
* [Експорт](export.md) 🇷🇺
* [Робота зі знижками](discounts_management.md) 🇷🇺
* [Приклад конфігурації Nginx](nginx/nginx.conf)

### Безпека

* [Оновлення: посилення безпеки](UPGRADE-security.md) — що змінилось і що з цим робити

---

`docs/superpowers/` — це історія планів і специфікацій, а не довідник.
