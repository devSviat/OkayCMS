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

Якщо модуль додає на вітрину власну форму, у неї закладаються два різні поля.

### Навіщо їх два

Вони відповідають на різні питання, і жодне не замінює інше.

**`customer_csrf_token` — «хто це надіслав?»** Куки браузер підставляє сам, тож будь-яка чужа
сторінка може змусити браузер вашого покупця надіслати запит у ваш магазин — від його імені й
без його відома. Токен це зупиняє: чужий сайт не може прочитати наше значення й не підставить
його у форму.

**`form_token` — «це надсилали вперше?»** Покупець двічі клацнув кнопку, оновив сторінку по
`F5`, повернувся кнопкою «Назад» — і магазин отримав дві однакові заявки й два листи. Тут немає
зловмисника: запит цілком законний, просто зайвий.

Звідси і головне непорозуміння, якого варто уникнути: **перевірка CSRF від повторів не рятує**.
Подвійний клік несе абсолютно правильний CSRF-токен — бо він і справді від нашої форми. І
навпаки: `form_token` не є перевіркою безпеки, вигадане значення в ньому пройде.

Тому вони й поводяться протилежно:

| | `customer_csrf_token` | `form_token` |
| --- | --- | --- |
| Питання | хто надіслав | чи вперше |
| Живе | одне значення на всю сесію | нове на кожен рендер форми |
| Спрацьовує | скільки завгодно разів | рівно один раз |
| Обовʼязкове | **так**, якщо форма щось змінює | ні, але без нього захист слабший |

Злити їх в одне не вийде: одноразовий CSRF-токен ламав би кожну вже відкриту сторінку, а
токен повтору не годиться на роль рубежу безпеки.

### Що покласти у форму

```smarty
<form method="post" action="{url_generator route="Vendor.Module.Action"}">
    <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
    {form_token name="vendor_module_action"}
    …
</form>
```

Значення `customer_csrf_token` кладе `AbstractController` у кожен шаблон вітрини — включно з
шаблонами модулів, — тож готувати нічого не треба. Без цього поля серверна перевірка віддасть
403 і запис не створиться.

`form_token` ставте всюди, де повторна відправка створює зайвий рядок або зайвий лист: заявка,
відгук, коментар, замовлення. Імʼя форми вибирайте своє й унікальне — під ним сервер веде
перелік уже використаних токенів, тож дві форми з одним іменем гаситимуть одна одну.

Перелік використаних, а не «очікуваний токен» — саме тому дві вкладки з тією самою формою
працюють обидві: у них різні токени, і жоден ще не витрачений.

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

Три речі, на яких легко спіткнутись:

- **Виклик має бути до запису**, інакше рядок уже створено, і гасити нема чого.
- **Не кладіть у `$payload` те, що змінює сама дія.** Оформлення замовлення очищає кошик, тож
  відбиток, порахований зі складу кошика, у повторного запиту вийде інший — і повтор
  проскочить. Беріть дані форми, а не стан, який дія руйнує.
- **Редирект завершує процес.** `Response::redirectTo()` закінчується `exit`, тож
  `ExtenderFacade::execute()` ставте **перед** ним — інакше розширення модулів мовчки не
  спрацьовуватимуть саме на успішному шляху.

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
