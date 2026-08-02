# Структура модуля

Модуль лежить у `Okay/Modules/<Vendor>/<Module>/`, неймспейс —
`Okay\Modules\<Vendor>\<Module>`. Обов'язковий лише `Init/Init.php`; решта каталогів
з'являється за потреби.

```
Init/
  Init.php          обов'язковий: клас Init із install() та init()
  module.json       метадані модуля й модифікації .tpl
  routes.php        маршрути вітрини
  services.php      сервіси модуля для DI
  parameters.php    параметри модуля
  SmartyPlugins.php Smarty-плагіни модуля
Backend/
  Controllers/      контролери адмінки
  design/html/      шаблони адмінки
  design/css/       стилі адмінки
  design/js/        скрипти адмінки
  design/images/
  design/css.php    реєстрація стилів адмінки
  design/js.php     реєстрація скриптів адмінки
  lang/             переклади адмінки: en.php, ru.php, ua.php …
Controllers/        контролери вітрини
Entities/           сутності модуля
Extenders/          розширення: FrontExtender.php, BackendExtender.php
Helpers/            бізнес-логіка модуля
Requests/           збирачі POST-даних
config/config.php   власні директиви конфіга
design/
  html/             шаблони вітрини
  css/  js/  images/
  lang/             переклади вітрини
  css.php  js.php   реєстрація стилів і скриптів вітрини
settings.xml        налаштування модуля оплати чи доставки
preview.(jpg|jpeg|png|gif|svg)   картинка для списку модулів
```

Реальний зразок середнього розміру — `Okay/Modules/OkayCMS/FAQ/`.

## `module.json`

Лежить у `Init/module.json`. Шлях жорстко зашитий — в іншому місці файл не знайдуть.

Відсутній файл не помилка: модуль отримає версію `1.0.0`. Зламаний JSON теж не кидає виняток —
помилка йде в лог, а модуль працює з порожніми метаданими. Це та поведінка, за якої «оновлення
не застосовується» виглядає як тиша.

Ключі, які код справді читає:

| Ключ | Тип | Що робить |
| ---- | --- | --------- |
| `version` | `"X.Y.Z"` | версія модуля; **рівно три числа** |
| `vendor.email` | рядок | пошта вендора в списку модулів |
| `vendor.site` | рядок | сайт вендора |
| `Okay` | рядок | версія ядра, під яку зроблено модуль; адмінка порівнює її з поточною й попереджає про розбіжність |
| `modifications.front` | масив | модифікації шаблонів вітрини |
| `modifications.backend` | масив | модифікації шаблонів адмінки |

```json
{
  "version": "1.2.0",
  "vendor": {
    "email": "info@okay-cms.com",
    "site": "https://okay-cms.com"
  },
  "modifications": {
    "front": [
      {
        "file": "order.tpl",
        "changes": [
          { "find": "{if $delivery}", "appendAfter": "order_delivery_info.tpl" }
        ]
      }
    ]
  }
}
```

Чого **не** треба писати:

- `math_version` — ключ парситься, але одразу перезаписується обчисленим значенням із `version`.
- `moduleName` — трапляється в реальних файлах, але код його не читає взагалі.

Синтаксис блоку `modifications` — [../tpl_modifiers.md](../tpl_modifiers.md).

## Версія модуля

Версія перетворюється на число для порівнянь: кожна з трьох частин збільшується на 100 і
склеюється, тож `1.2.0` → `101102100`. Наслідки:

- версія **мусить** мати рівно три числові частини через крапку; `1.2` чи `1.2.0-beta` дають 0;
- частина ≥ 900 ламає кодування, бо перестає бути тризначною.

## `Init/services.php`

Файл повертає масив описів сервісів у тому самому форматі, що й системний
[`Okay/Core/config/services.php`](../di.md). Тут оголошуються хелпери, запити,
розширення — усе, що має отримувати залежності через конструктор.

```php
// Okay/Modules/OkayCMS/Banners/Init/services.php
use Okay\Core\OkayContainer\Reference\ParameterReference as PR;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;

return [
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Design::class),
            new SR(Module::class),
            new SR(BannersHelper::class),
        ],
    ],
    BannersBackupHelper::class => [
        'class' => BannersBackupHelper::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(Languages::class),
            new PR('banners.imagesDir'),
        ],
    ],
];
```

Клас **розширення**, не оголошений тут, усе одно спрацює — але буде створений через
`new $class()` без аргументів. Конструктор з обов'язковими параметрами в такому разі покладе
запит. Див. [extenders.md](extenders.md#реєстрація).

## `Init/parameters.php`

Масив параметрів, доступних як `new PR('ключ.підключ')`. Значення в `{$фігурних дужках зі
знаком долара}` підставляються з конфіга:

```php
// Okay/Modules/OkayCMS/Banners/Init/parameters.php
return [
    'banners' => [
        'imagesDir' => '{$banners_images_dir}',
    ],
];
```

## `config/config.php` модуля

Ini-файл із власними директивами модуля. Директиву, яка вже є в системному
`config/config.php`, оголосити не можна — це кидає `Duplicate parameter`. Значення з
`config/config.local.php` завжди мають пріоритет над модульними.

## `settings.xml` — налаштування оплати й доставки

Лежить **у корені модуля**, не в `Init/`. Читається лише для **увімкнених** модулів із типом
`MODULE_TYPE_PAYMENT` або `MODULE_TYPE_DELIVERY` — для всіх інших файл ігнорується цілком.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<module>
    <settings>
        <variable>service_type</variable>
        <name>{$lang->settings_np_service_type}</name>
        <options>
            <name>{$lang->settings_np_service_dd}</name>
            <value>DoorsDoors</value>
        </options>
        <options>
            <name>{$lang->settings_np_service_wd}</name>
            <value>WarehouseDoors</value>
        </options>
    </settings>
    <settings type="text">
        <variable>wayforpay_merchant</variable>
        <name>{$lang->way_for_pay_merchant}</name>
    </settings>
</module>
```

Що читає парсер:

| Вузол | Роль |
| ----- | ---- |
| `<settings>` | одне налаштування; повторюваний |
| `<variable>` | ключ налаштування |
| `<name>` | підпис; `{$lang->ключ}` підставляється з `Backend/lang/` модуля |
| `<options>` | варіант вибору; повторюваний. Наявність хоч одного перетворює поле на список |
| `<options><name>`, `<options><value>` | підпис і значення варіанта |
| `<value>` | значення; використовується **лише** коли тип — `checkbox` |
| атрибут `type` | `hidden`, `text`, `date`, `checkbox`; будь-що інше → `text` |

Дві пастки:

- атрибут `type` враховується **тільки якщо немає `<options>`**;
- шаблон способу оплати ігнорує `type` узагалі — він дивиться лише на кількість варіантів:
  більше одного → випадний список, один → чекбокс, жодного → текстове поле. Тип впливає лише
  на форму способу доставки.

Кореневий елемент (`<module>`) парсеру байдужий, як і будь-які інші вузли — наприклад `<name>`
на рівні модуля не читається ніде.

## `preview`

`preview.jpg`, `.jpeg`, `.png`, `.gif` або `.svg` у корені модуля — картинка в списку модулів
адмінки.
