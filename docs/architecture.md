# Як влаштований цей форк

OkayCMS — PHP-платформа електронної комерції з модульною архітектурою, власним DI-контейнером
і власною ORM. Цей репозиторій — форк, який розійшовся з апстрімом у трьох речах: версії
залежностей, межі безпеки й оточення розробки. Довідник описує **форк**, а не апстрім;
там, де поведінка відрізняється, це сказано прямо.

## Стек

| Компонент | Версія | Що з цього випливає |
| --------- | ------ | ------------------- |
| PHP | `^8.4` (перевірено на 8.4–8.5) | стокова OkayCMS 4.5.2 на 8.4 не стартує |
| Smarty | 5.8 | `\Smarty_Internal_Template` більше немає, `{function}` тримає присвоєння локальними |
| aura/sqlquery | 3.0 | позиційні `?` у `where()` непридатні |
| aura/sql | 6.0 | шар PDO |
| symfony/console | 8.x | команди через `#[AsCommand]`, `static $defaultName` більше не працює |
| PHPUnit | 13.x | атрибути замість анотацій, статичні дата-провайдери, `failOnDeprecation` |
| libphonenumber | 9.x | `PhoneNumberFormat` — enum, не int |
| psr/log 3, monolog 3, guzzle 8, phpmailer 7 | | |
| php-debugbar | 3.8 (dev-залежність) | панель відладки — [configuration.md](configuration.md) |

Точний перелік — у `composer.json`, зафіксовані версії — у `composer.lock`.

## Карта каталогів

| Шлях | Неймспейс | Що це |
| ---- | --------- | ----- |
| `Okay/Core/` | `Okay\Core` | ядро: DI, ORM, Router, Design/Smarty, Image, Scheduler, Console, TplMod, Security |
| `Okay/Controllers/`, `Okay/Entities/`, `Okay/Helpers/`, `Okay/Requests/` | `Okay\*` | застосунок вітрини |
| `backend/Controllers/`, `backend/Helpers/`, `backend/Requests/` | `Okay\Admin\*` | адмін-панель |
| `Okay/Modules/<Vendor>/<Module>/` | `Okay\Modules\<Vendor>\<Module>` | модулі; цей форк постачає вендора `OkayCMS` |
| `design/<theme>/` | — | теми вітрини (`okay_shop`, `vibe_shop`) |
| `backend/design/` | — | шаблони, стилі й скрипти адмінки |
| `config/` | — | `config.php` + `config.local.php` |
| `tests/` | — | PHPUnit-набір |
| `dev/` | — | локальне оточення (Docker) |
| `compiled/`, `cache/` | — | скомпільовані шаблони та зібрані CSS/JS |

Позначення в довіднику: `Okay\Core\Response` — це неймспейс, `Okay/Core/Response.php` — шлях
у файловій системі. Запис `Okay\Core\Response::setContent()` означає метод `setContent()`
класу `Okay\Core\Response`; якщо метод статичний, це сказано окремо.

## Життєвий цикл запиту вітрини

Вхідні точки — `index.php` і `worker.php` у корені. Обидві — тонкі обгортки навколо
`Okay\Core\Kernel`, який і володіє життєвим циклом:

| Метод | Коли | Що робить |
| ----- | ---- | --------- |
| `boot()` | раз на процес | збирає DI-контейнер і `Config` |
| `handle()` | на кожен запит | сесія, маршрутизація, відповідь |
| `terminate()` | межа запиту | закриває сесію, скидає стан запиту |

`index.php` викликає всі три поспіль, `worker.php` крутить `handle()`/`terminate()` у циклі —
див. [deployment-frankenphp.md](deployment-frankenphp.md#worker-mode).

1. **`display_errors` вимикається** — до того, як щось може впасти.
2. **DI-контейнер** збирається з `Okay/Core/config/container.php` (`Kernel::boot()`), з нього
   береться `Config` — [di.md](di.md).
3. **Сесія.** `SessionNames::isAdmin()` читає бекендову сесію, далі
   `SessionNames::startFrontend()`. Простори сесій вітрини й адмінки розділені, тож порядок
   тут важливий: одночасно активною може бути лише одна.
4. **Панель відладки** піднімається, якщо `debug_bar` **і** `debug_mode` увімкнені.
5. **Мова.** `Router::resolveCurrentLanguage()` визначає мову з префікса URL
   і редіректить `/<головна-мова>/…` на `/…` з кодом 301.
6. **`debug_mode`** вмикає `display_errors` і `error_reporting(E_ALL)`.
7. **Модулі.** `Modules::startEnabledModules()` реєструє параметри, сервіси,
   маршрути й Smarty-плагіни кожного увімкненого модуля та викликає його `Init::init()` —
   [modules/lifecycle.md](modules/lifecycle.md).
8. **Маршрутизація.** `Router::run()` перебирає маршрути, будує з `slug`
   регулярний вираз і віддає збіг у `bramus/router` — [routes.md](routes.md).
9. **Контролер.** `Router::createControllerInstance()` створює контролер **без ін'єкції в
   конструктор** і викликає по черзі `beforeController()`, `onInit()`, метод маршруту,
   `afterController()`. Аргументи кожного з них резолвляться за тайп-хінтом; параметри
   маршруту приходять **без** тайп-хінта — [controllers.md](controllers.md).
10. **Відповідь.** Контролер, який повернув `false`, дає 404 (`ErrorController::pageNotFound`).
    Вміст відправляється в колбеку `bramus/router` через `Response::sendContent()`.

Виняток, що дійшов до `Kernel::handle()`, логується і віддає 500 — але тільки якщо тіло ще не
відправлене; під `debug_mode` трасування друкується на сторінку. Тут є пастка зі статусом
відповіді, вона описана в [configuration.md](configuration.md#debug_mode).

### Завершення запиту без exit

`Response::redirectTo()`, `Response::setHeaderLastModify()` на 304 і рання відсічка
`StorefrontGuard` не викликають `exit`, а кидають `Okay\Core\Http\TerminateRequest`. Код
нижче виклику не виконується — так само, як від `exit`, — але процес лишається живим.
Виняток ловить точка входу: `Kernel::handle()` для вітрини, `backend/index.php` для адмінки.

`TerminateRequest` розширює `\Error`, а не `\Exception`, навмисно: ядро ловить
`catch (\Exception)` навколо контролерів, і виняток-нащадок `Exception` перетворив би
редірект на порожню 200.

## Життєвий цикл запиту адмінки

Вхідна точка — `backend/index.php`. Вона не використовує `Router`: контролер береться з
`?controller=`, метод — після `@` (типово `fetch`).

1. `SessionNames::startBackend()`, далі `$_SESSION['id']` заповнюється **CSRF-токеном
   адмінки**, а не ідентифікатором сесії (`backend/index.php:41-45`).
2. Вихід менеджера обробляється тут-таки: тільки POST, тільки з валідним токеном
   (`backend/index.php:58-64`).
3. `Modules::startAllModules()` — адмінка піднімає й **вимкнені** модулі, щоб їх можна було
   побачити в списку модулів.
4. Ім'я контролера санітизується, права перевіряються в `IndexAdmin::onInit()`, яка повертає
   `bool` — це і є гейт авторизації.
5. **CSRF-перевірка виконується до виклику контролера** (`backend/index.php:212-218`): без
   валідного `session_id` відповідь — 403 `Session expired`. Виняток — `AuthAdmin`, бо форма
   входу рендериться до появи сесії менеджера.

Контролер модуля адресується як `?controller=Vendor.Module.ControllerName` —
[modules/backend.md](modules/backend.md).

## Межі безпеки форку

Це найбільша відмінність від апстріму, і саме вона тихо ламає сторонні теми та модулі.

**Вітрина: мутації йдуть тільки POST-ом із токеном.** Кошик, список бажань, порівняння,
зворотний зв'язок і підписка викликають `AbstractController::requireCustomerCsrf()` першим
рядком. Метод не GET → 405, невалідний токен → 403, і в обох випадках виконання
завершується. Токен живе одночасно в сесії й у куці `okay_csrf` (навмисно не `HttpOnly`, щоб
його читав AJAX теми) і призначається в кожен шаблон як `$customer_csrf_token`.

**Адмінка: `session_id` — це токен, а не ідентифікатор сесії.** Поле з тим самим іменем
лишилось у формах, але значення тепер — окремий CSRF-токен. `Request::checkSession()` при
невалідному токені очищає `$_POST` і повертає `false`.

Повний перелік змін і що з ними робити — [UPGRADE-security.md](UPGRADE-security.md).
Що правити у власній темі — [theme-porting.md](theme-porting.md).

## Куди далі

| Треба | Розділ |
| ----- | ------ |
| Написати модуль | [modules/README.md](modules/README.md) |
| Робота з базою, ORM | [entities.md](entities.md) |
| Зареєструвати сервіс, дістати залежність | [di.md](di.md) |
| Маршрути й контролери | [routes.md](routes.md), [controllers.md](controllers.md) |
| Налаштування, режими відладки | [configuration.md](configuration.md) |
| Шаблони, плагіни, CSS/JS | [templates.md](templates.md), [smarty-plugins.md](smarty-plugins.md), [assets.md](assets.md) |
| Планувальник | [cli.md](cli.md) |
| Перенести тему на форк | [theme-porting.md](theme-porting.md) |
