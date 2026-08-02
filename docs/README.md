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
| Написати модуль | [modules/quick-start.md](modules/quick-start.md) |
| Змінити чужий шаблон, не редагуючи його | [tpl-modifications.md](tpl-modifications.md) |
| Вклинитись у чужу логіку з модуля | [modules/extenders.md](modules/extenders.md) |
| Працювати з базою | [entities.md](entities.md) |
| Додати сторінку вітрини | [routes.md](routes.md), [controllers.md](controllers.md) |
| Підключити CSS чи JS | [assets.md](assets.md) |
| Перенести свою тему на форк | [theme-porting.md](theme-porting.md) |
| Зрозуміти, що змінила ітерація безпеки | [UPGRADE-security.md](UPGRADE-security.md) |

## Довідник

### Основи

* [Як влаштований цей форк](architecture.md) — стек, каталоги, життєвий цикл запиту, межі безпеки
* [Налаштування](configuration.md) — `config.php` / `config.local.php`, `dev_mode`, `debug_mode`, панель відладки
* [Ядро системи (Core)](core/README.md) 🇷🇺
* [Режим розробника](dev_mode.md) 🇷🇺 — увійшло в [configuration.md](configuration.md), файл лишається до переписування розділу модулів

### Застосунок

* [Контролери](controllers.md) — фронтові й бекендові, порядок викликів, `Response`
* [Маршрути](routes.md) — опис, параметри, генерація URL
* [Сутності (Entity)](entities.md) — ORM, фільтри, багатомовність
* [Helpers](helpers.md) — бізнес-логіка й контракт розширюваності
* [Requests](requests.md) — збирання даних із запиту
* [DI-контейнер і Service Locator](di.md) — сервіси, параметри, ін'єкція залежностей

### Дизайн і шаблони

* [Шаблони](templates.md) — теми, `Design`, пастки Smarty 5, статичні класи
* [Модифікація `.tpl` з модуля](tpl-modifications.md) — зміна чужої розмітки без її редагування
* [Smarty-плагіни](smarty-plugins.md) — власні теги в шаблонах
* [CSS і JS](assets.md) — конвеєр асетів, налаштування теми, пастки CSS-компілятора
* [Перенесення теми на цей форк](theme-porting.md)

### Модулі

* [Модулі: огляд і карта розділу](modules/README.md)
* [Швидкий старт](modules/quick-start.md) — перший модуль від каркаса до встановлення
* [Структура модуля](modules/structure.md) — каталоги, `module.json`, `settings.xml`, сервіси
* [Життєвий цикл](modules/lifecycle.md) — `install()`, `init()`, `update_x_y_z()`, версії
* [Довідник `AbstractInit`](modules/init-reference.md) — усі доступні методи
* [Таблиці й поля](modules/migrations.md) — міграції та `EntityField`
* [Розширення](modules/extenders.md) — вклинитись у чужий метод
* [Модуль в адмінці](modules/backend.md) — контролери, права, меню, блоки
* [Модуль на вітрині](modules/frontend.md) — сторінка, шаблони, блоки, плагіни

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
