# Модуль на вітрині

## Сторінка вітрини

Front-контролер реєструється **маршрутом**, а не методом `AbstractInit`. Файл —
`Init/routes.php`:

```php
// Okay/Modules/OkayCMS/FAQ/Init/routes.php
namespace Okay\Modules\OkayCMS\FAQ;

return [
    'OkayCMS_FAQ_main' => [
        'slug' => '/faq',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FAQController',
            'method' => 'render',
        ],
    ],
];
```

Формат той самий, що й у системних маршрутів — [../routes.md](../routes.md). Дві особливості
модульних:

- **Ім'я маршруту має бути унікальним у межах системи.** Збіг із системним маршрутом кидає
  `Route name "…" already uses` — якщо тільки маршрут не оголосив `'overwrite' => true`, і
  тоді він системний замінює. Тому імена іменують за схемою `Vendor_Module_щось`.
- **У вимкненого модуля маршрути позначаються як мок** і не реєструються взагалі.

Контролер:

```php
// Okay/Modules/OkayCMS/FAQ/Controllers/FAQController.php
namespace Okay\Modules\OkayCMS\FAQ\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Core\EntityFactory;

class FAQController extends AbstractController
{
    public function render(EntityFactory $entityFactory)
    {
        $FAQEntity = $entityFactory->get(FAQEntity::class);

        $this->design->assign('faqs', $FAQEntity->find(['visible' => 1]));
        $this->response->setContent('faq.tpl');
    }
}
```

Аргументи з тайп-хінтом резолвляться через DI; параметри маршруту приходять **без** тайп-хінта,
за іменем змінної. Докладно — [../controllers.md](../controllers.md).

Шаблон `faq.tpl` береться з `design/html/` модуля. Тема може його перевизначити, поклавши свій
файл у `design/<тема>/modules/<Vendor>/<Module>/html/`.

## Мутуючий запит

Будь-який запит вітрини, що змінює стан, у цьому форку зобов'язаний бути POST-ом із CSRF-токеном.
Свій контролер захищайте так само, як це роблять контролери кошика:

```php
public function ajaxUpdate()
{
    $this->requireCustomerCsrf();
    // …
}
```

Метод не GET → 405, невалідний токен → 403, виконання припиняється. Токен доступний у шаблонах
як `$customer_csrf_token` і дублюється в куці `okay_csrf` для AJAX. Деталі —
[../UPGRADE-security.md](../UPGRADE-security.md), приклади розмітки —
[../theme-porting.md](../theme-porting.md).

## Блоки дизайну

Вітрина теж розмічена іменованими блоками — їх менше, ніж в адмінці.

```php
$this->addFrontBlock('front_cart_delivery', 'front_cart_delivery_block.tpl');
```

Файл шукається спершу в темі — `design/<тема>/modules/<Vendor>/<Module>/html/<файл>`, і лише
потім у `design/html/` модуля. Тобто тема може перевизначити блок модуля, не чіпаючи сам модуль.

Третім аргументом — колбек із DI-аргументами, що виконається перед відмальовуванням:

```php
$this->addFrontBlock(
    'front_email_order_user_contact_info',
    'order_email_delivery_info.tpl',
    function (Design $design) {
        if ($delivery = $design->getVar('delivery')) {
            // …
        }
    }
);
```

Імена доступних блоків показує `dev_mode`
([../configuration.md](../configuration.md#dev_mode)).

Блок приймає не лише `.tpl`: `NovaposhtaCost` реєструє в блоці `front_scripts_after_validate`
файл `validation.js`.

## Зміна чужого шаблону

Якщо потрібного блоку немає, шаблон змінюється декларативно — блоком `modifications` у
`module.json`, без редагування самого файлу:

```json
{
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

Значення операції може бути як розміткою, так і **іменем файлу** з `design/html/` модуля — як
тут. Синтаксис і пастки — [../tpl_modifiers.md](../tpl_modifiers.md).

Після зміни `modifications` треба почистити `compiled/`. Для налагодження зручний
`smarty_force_compile = true`.

## Стилі й скрипти

`design/css.php` і `design/js.php` модуля повертають масиви описів файлів:

```php
// Okay/Modules/OkayCMS/FAQ/design/js.php
use Okay\Core\TemplateConfig\Js;

return [
    (new Js('faq.js')),
];
```

Файли беруться з `design/js/` і `design/css/` модуля, і тема може їх перевизначити тим самим
способом, що й шаблони. Прямі теги `<script>` і `<link>` не використовуються —
[../js_css_files.md](../js_css_files.md).

## Переклади вітрини

`design/lang/<label>.php` — за тим самим принципом, що й переклади адмінки. Зразки —
`DeliveryFields` і `FastOrder`.

## Smarty-плагіни модуля

`Init/SmartyPlugins.php` повертає масив описів сервісів (той самий формат, що й
`Init/services.php`) — сам файл нічого не реєструє, реєстрацією займається система:

```php
// Okay/Modules/OkayCMS/FastOrder/Init/SmartyPlugins.php
return [
    FastOrderPlugin::class => [
        'class' => FastOrderPlugin::class,
        'arguments' => [ /* … */ ],
    ],
];
```

Клас плагіна успадковує `Okay\Core\SmartyPlugins\Func` або `Modifier`; тег береться з
властивості `$tag` або з імені класу в нижньому регістрі. Докладно —
[../smarty_plugins.md](../smarty_plugins.md).

**Плагін вимкненого модуля не зникає — він підміняється заглушкою**, що повертає порожній
рядок. Шаблон, який його викликає, не ламається, а мовчки нічого не друкує.

## Сторінка модуля оплати

Модуль із типом `MODULE_TYPE_PAYMENT` має містити клас `PaymentForm` у корені неймспейсу
модуля, що реалізує `Okay\Core\Modules\Interfaces\PaymentFormInterface`:

```php
public function checkoutForm($orderId);
```

Клас знаходять **за домовленістю** — `Okay\Modules\<Vendor>\<Module>\PaymentForm` — і беруть із
контейнера, тож він має бути оголошений в `Init/services.php`. Під час відмальовування форми
система сама перемикає Smarty на `design/html/` модуля й повертає каталог назад після.
Зразки: `LiqPay`, `Fondy`, `WayForPay`, `RozetkaPay`.
