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

`requireCustomerCsrf()` живе в `AbstractController`, тож із хелпера чи будь-якого іншого класу
недоступний. Там візьміть той самий сервіс через DI:

```php
use Okay\Core\Security\StorefrontGuard;

public function __construct(StorefrontGuard $storefrontGuard)
{
    $this->storefrontGuard = $storefrontGuard;
}

public function handlePost()
{
    $this->storefrontGuard->requireCustomerCsrf();
    // …
}
```

## Форма в модулі

Якщо модуль додає на вітрину власну форму, у неї закладаються два різні поля. Вони вирішують
різні задачі, і плутати їх не варто.

| Поле | Від чого | Обовʼязкове? |
| ---- | -------- | ------------ |
| `customer_csrf_token` | підробка запиту з чужого сайту | **так**, якщо форма щось змінює |
| `form_token` | повтор тієї самої відправки | ні, але без нього захист слабший |

```smarty
<form method="post" action="{url_generator route="Vendor.Module.Action"}">
    <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
    {form_token name="vendor_module_action"}
    …
</form>
```

**`customer_csrf_token` обовʼязковий.** Без нього серверна перевірка віддасть 403 і запис не
створиться. Значення кладе `AbstractController` у кожен шаблон вітрини — включно з шаблонами
модулів, — тож нічого готувати не треба.

**`form_token` не обовʼязковий**, але потрібен усюди, де повторна відправка створює зайвий
рядок або зайвий лист: заявка, відгук, коментар, замовлення. Імʼя форми вибирайте своє й
унікальне — під ним токен лежить у сесії, тож дві форми з одним іменем гаситимуть одна одну.

На сервері рішення «нова відправка чи повтор» приймає один виклик:

```php
use Okay\Core\Security\FormToken;

const ORDER_FORM = 'vendor_module_action';

if (!FormToken::accept(self::ORDER_FORM, $this->request->post('form_token'), $payload)) {
    // Повтор. Це не помилка користувача: показуйте той самий успіх,
    // що й перша відправка, або ведіть на вже створений обʼєкт.
    return;
}
```

`$payload` — дані форми (обʼєкт або масив). Вони йдуть у запасний відбиток: якщо поле
`form_token` у запиті відсутнє, повтором вважається та сама відправка протягом 10 хвилин.
Відбиток менш точний, тому це саме запасний шлях.

Дві речі, на яких легко спіткнутись:

- **Виклик має бути до запису**, інакше рядок уже створено, і гасити нема чого.
- **Не кладіть у `$payload` те, що змінює сама дія.** Оформлення замовлення очищає кошик, тож
  відбиток, порахований зі складу кошика, у повторного запиту вийде інший — і повтор
  проскочить. Беріть дані форми, а не стан, який дія руйнує.

Зразки в дереві: `Okay/Helpers/CommonHelper.php` (зворотний дзвінок),
`Okay/Modules/OkayCMS/FastOrder/Controllers/FastOrderController.php` (замовлення через AJAX).

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
тут. Синтаксис і пастки — [../tpl-modifications.md](../tpl-modifications.md).

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
[../assets.md](../assets.md).

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
[../smarty-plugins.md](../smarty-plugins.md).

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
