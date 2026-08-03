# Документація OkayCMS (форк)

Довідник описує **цей форк**, а не апстрім. Кожне твердження звірене з кодом.

**Спершу запустити.** Оточення — Docker: [`dev/README.md`](../dev/README.md), команди швидкого
старту — у [кореневому README](../README.md). Інших способів розгорнути форк довідник не описує.

**Далі читати** [architecture.md](architecture.md) — стек, карта каталогів, життєвий цикл запиту.

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
| Перенести тему звідси на стокову OkayCMS | [theme-to-stock.md](theme-to-stock.md) |
| Зрозуміти, що змінили ітерації безпеки | [UPGRADE-security.md](UPGRADE-security.md) |
| **Оновити магазин на нову версію форку** | [UPGRADE-security.md](UPGRADE-security.md) — там є дія, обов'язкова для всіх: конфіг nginx |
| **Щось зламалось, і воно мовчить** | [troubleshooting.md](troubleshooting.md) |

## Довідник

### Основи

* [Як влаштований цей форк](architecture.md) — стек, каталоги, життєвий цикл запиту, межі безпеки
* [Налаштування](configuration.md) — `config.php` / `config.local.php`, `dev_mode`, `debug_mode`, панель відладки
* [Симптом → причина](troubleshooting.md) — покажчик тихих поломок

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
* [Перенесення теми на цей форк](theme-porting.md) — навіщо мутації пішли на POST і що змінити у своїй темі
* [Перенесення теми звідси на стокову](theme-to-stock.md) — зворотний напрям: що переїде саме, а що руками

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

* [Консоль і планувальник](cli.md) — `./ok`, власні команди, задачі за розкладом
* [Тести й статичний аналіз](testing.md) — phpunit, phpstan, phpcs, `smoke.sh`
* [Імпорт](import.md) — що система робить із файлом на вході; як модуль додає власні поля
* [Експорт](export.md) — формат вигрузки; як модуль додає власні колонки й критерії відбору
* [Знижки](discounts.md) — набори, знаки, власна знижка з модуля
* [Приклад конфігурації Nginx](nginx/nginx.conf)

### Безпека

* [Оновлення: посилення безпеки](UPGRADE-security.md) — обидві ітерації: що змінилось, що
  ламається у сторонніх темах і що обов'язково зробити на сервері

---

`docs/superpowers/` — це історія планів і специфікацій, а не довідник.
