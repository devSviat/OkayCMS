# DI-контейнер і Service Locator

Контейнер — `Okay\Core\OkayContainer\OkayContainer`, синглтон. Він збирається у
`Okay/Core/config/container.php` із двох масивів: сервісів і параметрів.

```php
$services   = require_once __DIR__ . '/services.php';
$parameters = require_once __DIR__ . '/parameters.php';

return OkayContainer::getInstance($services, $parameters);
```

Системні сервіси описані в `Okay/Core/config/services.php`, який наприкінці зливає до себе ще
й `helpers.php` та `requests.php` — тому хелпери й запити резолвляться так само, як будь-який
інший сервіс. Модуль додає своє через `Init/services.php` і `Init/parameters.php`
([modules/structure.md](modules/structure.md#initservicesphp)).

## Оголошення сервіса

```php
use Okay\Core\OkayContainer\Reference\ParameterReference as PR;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;

return [
    // мінімальний випадок
    BRouter::class => [
        'class' => BRouter::class,
    ],

    // Okay/Core/config/services.php
    Money::class => [
        'class' => Money::class,
        'arguments' => [
            new SR(EntityFactory::class),
        ],
        'calls' => [
            [
                'method' => 'configure',
                'arguments' => [
                    new PR('money.decimals_point'),
                    new PR('money.thousands_separator'),
                ],
            ],
        ],
    ],
];
```

| Ключ | Роль |
| ---- | ---- |
| `class` | **обов'язковий**; без нього — `service entry must be an array containing a 'class' key` |
| `arguments` | аргументи конструктора |
| `calls` | методи, що викликаються **після** створення об'єкта: `['method' => …, 'arguments' => [...]]` |

**Рядкового синтаксису `%параметр%` і `@сервіс` тут немає.** Посилання — це об'єкти:
`new SR(Ім'я::class)` для сервіса, `new PR('ключ.підключ')` для параметра. Усе інше в
`arguments` передається як є — рядок лишиться рядком.

Кругові посилання ловляться: сервіс, що прямо чи опосередковано вимагає сам себе, дає
`contains circular reference`.

Сервіси створюються **ліниво** й кешуються: перший `get()` будує об'єкт, наступні віддають
той самий.

## Параметри

`Okay/Core/config/parameters.php` — вкладений масив, адресація крапками:

```php
return [
    'db' => [
        'driver' => '{$db_driver}',
        'dsn'    => '{$db_driver}:host={$db_server};dbname={$db_name};charset={$db_charset}',
    ],
    'template_config' => [
        'compile_css_dir' => 'cache/css/',
    ],
];
```

`new PR('db.dsn')` віддасть відповідне значення; відсутній ключ кидає
`Parameter not found: …`.

Дві підстановки в значеннях параметрів:

| Синтаксис | Звідки береться | Де працює |
| --------- | --------------- | --------- |
| `{$name}` | директива конфіга (`config/config.php`) | будь-де |
| `{%name%}` | налаштування магазину (`Settings`) | **тільки в `calls`** |

Обмеження на `{%name%}` — не домовленість, а поведінка коду: підстановка налаштувань
застосовується під час виклику `calls` і не застосовується до аргументів конструктора.
Параметр із `{%…%}`, переданий в `arguments`, приїде як необроблений рядок. Саме тому
`Money` отримує розділювачі через `configure()`, а не через конструктор.

## Отримання залежностей

### Тайп-хінт

Основний спосіб. Контейнер сам створює те, що вказано типом аргументa:

```php
class SomeController extends AbstractController
{
    public function render(EntityFactory $entityFactory, CartHelper $cartHelper)
    {
        // …
    }
}
```

Працює в методах контролерів (фронтових і бекендових), у `handle()` консольних команд, у
колбеках блоків дизайну. **У конструктор контролера залежності не приходять** — контролери
створює роутер через `new`, без аргументів.

Параметри маршруту приходять **без** тайп-хінта, за іменем змінної — і це те, що відрізняє їх
від сервісів у тому ж списку аргументів ([controllers.md](controllers.md)).

### Конструктор

Для всього, що описане в `services.php`, `helpers.php`, `requests.php` або `Init/services.php`
модуля: аргументи перелічуються явно в `arguments`.

**Автоматичного визначення залежностей за тайп-хінтом конструктора контейнер не робить.**
Кожен аргумент має бути в списку. Автовизначення є лише для *методів* — саме воно й дозволяє
типізувати аргументи методів контролера.

### Service Locator

`Okay\Core\ServiceLocator` — доступ до контейнера там, де ін'єкція недоступна або
нераціональна:

```php
use Okay\Core\ServiceLocator;
use Okay\Core\EntityFactory;

$SL = ServiceLocator::getInstance();
$entityFactory = $SL->getService(EntityFactory::class);
```

Є ще `hasService()` — перевірка без створення об'єкта.

Локатор беруть **через `getInstance()`**: створення через `new` працює, але видає
`E_USER_DEPRECATED`.

Де він доречний:

- `install()` і `update_x_y_z()` модуля — там DI немає взагалі;
- класи, які створюються поза контейнером (сутності, об'єкти запитів);
- одна залежність, потрібна одному методу, коли тягнути її в конструктор не хочеться.

В усіх інших місцях — тайп-хінт.

## Розширення контейнера з модуля

Модулі підмішують своє під час завантаження: параметри зливаються рекурсивно, сервіси —
поверхнево (однакове ім'я сервіса перекриває попереднє). Це відбувається **до** виклику
`Init::init()`, тому в `init()` сервіси модуля вже доступні.

Рекурсивно тут означає **по ключах**: гілки з різними ключами доповнюють одна одну, а
однаковий ключ замінюється — виграє той, хто оголосив пізніше. Скаляр лишається скаляром;
два модулі з однаковим ключем не перетворять його на масив із двох значень.

## Помилки, які видає контейнер

| Повідомлення | Що сталось |
| ------------ | ---------- |
| `Service not found: X` | сервіс не оголошений у жодному `services.php` |
| `X service class does not exist: Y` | у `class` вказано неіснуючий клас; найчастіше — застарілий `vendor/` після зміни гілки |
| `X service entry must be an array containing a 'class' key` | опис сервіса без `class` |
| `X contains circular reference` | кругова залежність. Саме кругова: якщо конструктор сервіса впав, наступне звернення до нього повторить справжню причину, а не це повідомлення |
| `Parameter not found: X` | `new PR()` на неіснуючий ключ |
| `X service asks for call to uncallable method: Y` | у `calls` вказано метод, якого немає |
