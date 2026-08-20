# Налаштування

## Два файли

Конфіг застосунку — це два ini-файли, які читає `Okay\Core\Config`:

| Файл | Роль |
| ---- | ---- |
| `config/config.php` | базові значення, спільні для всіх копій; у git |
| `config/config.local.php` | локальні перевизначення; у `.gitignore`, не потрапляє в образ |

Локальний файл перекриває базовий по ключах. Шаблон із поясненнями —
`config/config.local-example.php`:

```bash
cp config/config.local-example.php config/config.local.php
```

Без нього `Config` бере `db_server = localhost` із `config/config.php`, а всередині контейнера
`localhost` — це не MariaDB, тож магазин не достукається до бази.

Обидва файли починаються з `;<? exit(); ?>` — рядок робить їх непридатними для прямого
відкриття через веб.

**Секції (`[database]`, `[php]`, `[smarty]`, `[design]`, `[images]`) — суто для читабельності.**
`Config` читає ini без `INI_SCANNER_SECTIONS` і зливає всі ключі в один простір імен. Ключ
`debug_mode` у секції `[design]` працюватиме так само, як у `[php]`.

## Як читати значення

```php
$config->get('debug_mode');   // Okay\Core\Config::get()
$config->debug_mode;          // те саме через __get()
```

`Config` також формує кілька значень сам, їх немає в ini: `root_dir`, `max_upload_filesize`
(мінімум із `upload_max_filesize`, `post_max_size` і `memory_limit`), `salt`. `Config::set()`
уміє переписати директиву у файлі, де вона визначена.

Модуль може **додати** власні директиви файлом `Okay/Modules/<Vendor>/<Module>/config/config.php`.
Перевизначати системні директиви модуль не може — дубль ключа кидає виняток.

Два ключі, які були в апстрімі й прибрані тут: `root_url` і `subfolder`. Звернення до них
кидає виняток із поясненням, а не повертає порожнє значення.

## Директиви

### База даних

| Директива | Значення |
| --------- | -------- |
| `db_server`, `db_user`, `db_password`, `db_name` | параметри підключення |
| `db_driver` | драйвер PDO (`mysql`) |
| `db_prefix` | префікс таблиць (`ok_`) |
| `db_charset`, `db_names` | кодування підключення |
| `db_sql_mode` | SQL-режим сесії |
| `db_timezone` | зсув часового поясу; типово закоментовано, приклад `+02:00` |

Це єдине місце, де живе пароль до бази — з оточення застосунок його не читає. На проді:
`chmod 600` і власник — користувач деплою.

### PHP

| Директива | Значення |
| --------- | -------- |
| `error_reporting`, `php_charset` | застосовуються засобами PHP |
| `php_locale_*` | **не читає жоден рядок коду** — успадковані від апстріму, значення довідкове |
| `php_timezone` | передається в `date_default_timezone_set()`; у `config.php` закоментований |
| `debug_mode` | див. нижче |
| `tmp_dir` | тимчасовий каталог |

### Smarty

| Директива | Значення |
| --------- | -------- |
| `smarty_compile_check` | перевіряти актуальність скомпільованих шаблонів |
| `smarty_caching`, `smarty_cache_lifetime` | кеш шаблонів |
| `smarty_debugging` | вбудована консоль Smarty |
| `smarty_html_minify` | мініфікація HTML на виході |
| `smarty_security` | політика безпеки Smarty |
| `smarty_force_compile` | компілювати шаблон при кожному запиті |

`smarty_force_compile = true` потрібен, коли налагоджуєте модифікації `.tpl` з модуля:
модифікований шаблон ніде не зберігається як файл, він існує лише у скомпільованому вигляді.
На проді — вимкнути.

### Дизайн і відладка

| Директива | Значення |
| --------- | -------- |
| `debug_translation` | замість відсутнього перекладу друкувати червоним `$lang->ключ not exists`, а для взятого з іншої мови — `from other language` |
| `scripts_defer` | додавати `defer` до підключених скриптів |
| `dev_mode` | режим розробника, див. нижче |
| `preload_head_css`, `preload_head_js`, `preload_footer_css`, `preload_footer_js` | `<link rel="preload">` для зібраних наборів |
| `debug_bar` | панель відладки, див. нижче |
| `disable_tpl_mod` | аварійно вимкнути модифікації `.tpl` з модулів; у `config/config.php` закоментовано |

### Зображення

`resize_adapter` (`Gregwar`, `Imagick` або `GD`), `design_images`, `watermark_file`,
`special_images_dir`, а також пари `original_*_dir` / `resized_*_dir` для товарів, блогу,
брендів, категорій, категорій блогу, авторів, доставок, способів оплати й мов.

## `dev_mode`

Режим розробника додає на сторінки службові позначки. Вмикається `dev_mode = true`.

Що з'являється:

- **Назви груп меню адмінки** — червоним. Потрібні, щоб дізнатись імена груп для
  `extendBackendMenu()` з модуля.
- **Назви шорт-блоків** — червоним, з підсвіткою межі блоку при наведенні. Це імена, які
  приймає `addBackendBlock()` / `addFrontBlock()`. Стилі для підсвітки на вітрину додає
  `FrontTemplateConfig` саме під `dev_mode`.
- **Коментарі навколо модифікованих ділянок `.tpl`** — `TplMod` обгортає кожну застосовану
  зміну коментарем із назвою модуля.
- **Пункт меню «Шаблони листів (debug)»** в адмінці.

`dev_mode` не впливає на відображення помилок — це `debug_mode`.

## `debug_mode`

Вмикає `display_errors` і `error_reporting(E_ALL)` для вітрини (`index.php:48-51`) і для
адмінки (`backend/index.php:47-50`). Без нього помилки йдуть у `Okay/log/`, а відвідувач
бачить порожню сторінку.

Додатково `debug_mode` **знімає try/catch навколо виклику контролера** у `Router`
(`Okay/Core/Router.php:236-245`): у звичайному режимі виняток контролера логується і
перетворюється на 404, у режимі відладки — летить далі, до `index.php`.

На проді — завжди `false`: він показує відвідувачам трасування стека й шляхи на сервері.

### Пастка: фатал під `debug_mode` віддає HTTP 200

Обробник у `index.php:97` ловить `\Exception`, а не `\Throwable`. Фатальні `\Error` і
`\TypeError` до нього не доходять: PHP друкує їх у тіло відповіді (бо `display_errors`
увімкнено), статус ніхто не змінює — і сторінка віддається з кодом **200**.

Навіть справжній `\Exception` не завжди дає 500. `index.php:103` виставляє заголовок, але
тіло сторінки вже відправлене раніше — `Response::sendContent()` викликається в колбеку
роутера. Будь-який виняток після цієї миті натикається на «headers already sent», і відповідь
лишається 200 із трасуванням, дописаним у кінець сторінки.

**Наслідок для перевірок:** під `debug_mode` код відповіді нічого не доводить. Grepати треба
тіло, а не статус.

## Панель відладки

Потрібні **дві** директиви одночасно (`index.php:32`):

```ini
debug_mode = true
debug_bar  = true
```

При `debug_mode = false` панель не підніметься навіть із `debug_bar = true`.

Панель — це `php-debugbar/php-debugbar` 3.8, **dev-залежність**. На інсталяції, розгорнутій
через `composer install --no-dev`, класу немає, і `DebugBar::init()` тихо нічого не робить.
Власних віджетів і власного JS у форку не лишилось — усе, що показує панель, дає бібліотека.

Збирачі: PHP-інфо, повідомлення, дані запиту, пам'ять, таймлайн, значення конфіга (свій
збирач), хвіст системного лога через monolog-міст і SQL-запити.

**Асети.** Файлові стилі й скрипти панелі проходять звичайним конвеєром CSS/JS. Inline-асети
через нього не проходять — `JavascriptRenderer::render()` їх не друкує, тож їх віддає окремий
метод, а шаблон теми виводить обидві частини:

```smarty
{if $debug_bar_renderer}
    {$debug_bar_inline_assets}
    {$debug_bar_renderer->render()}
{/if}
```

Обидві змінні призначає `Okay\Helpers\MainHelper` (`DebugBar::getRenderer()` і
`DebugBar::getInlineAssets()`).

### Тимчасові класи, які треба видалити

`Okay\Core\DebugBar\DataFormatter\QueryFormatter` і
`Okay\Core\DebugBar\DataCollectors\PDOCollector` існують **тільки** як обхід бага
php-debugbar 3.8.0: булеве значення прив'язки долітає до `quoteBinding(string $binding)` і
кладе сторінку через `TypeError`. Апстрім це виправив
([php-debugbar#1072](https://github.com/php-debugbar/php-debugbar/issues/1072)), але в 3.8.0
фікс не встиг. Другий клас потрібен лише тому, що сеттера для форматера в бібліотеці немає.

**Умова видалення:** вихід php-debugbar 3.8.1. Після підняття версії обидва класи
видаляються, а `DebugBar::initCollectors()` повертається на `\DebugBar\DataCollector\PDO\PDOCollector`.

`Okay\Core\DebugBar\DataCollectors\ConfigCollector` — не обхід і не видаляється: він показує
історію присвоєнь кожної директиви й те, звідки взялося чинне значення (`config.php`,
`config.local.php` чи конфіг модуля).

## Оточення розробки

Локальне оточення (Nginx + php-fpm + MariaDB) описане окремо — `dev/README.md`. Його змінні
живуть у `dev/.env` (копія з `dev/.env-example`) і з `config/config.local.php` **не
генеруються**: якщо міняєте `MYSQL_ROOT_PASSWORD` чи `MYSQL_DATABASE` у `dev/.env`, змініть і
в локальному конфізі.
