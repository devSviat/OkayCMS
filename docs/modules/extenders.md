# Розширення

Розширення — головний спосіб змінити чужу поведінку, не редагуючи чужий код. Модуль
підв'язується до методу ядра, хелпера, запиту чи навіть іншого модуля й отримує керування
щоразу, коли той метод повертає значення.

## Що можна розширити

Метод, який віддає результат так:

```php
// Okay/Helpers/AuthorsHelper.php
return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
```

У репозиторії таких місць близько 1400 — практично весь публічний API хелперів, запитів,
сутностей і чималої частини ядра. Хелпери й запити покриті найповніше; у сутностей покриті
CRUD-операції.

Друга форма трапляється в трейтах і базових класах:

```php
// Okay/Core/Entity/CRUD.php
return ExtenderFacade::execute([static::class, __FUNCTION__], $result, func_get_args());
```

Вона там обов'язкова, бо `__METHOD__` у трейті дав би ім'я трейта. Для вас це означає, що
тригером буде **конкретний клас** — `Okay\Entities\ProductsEntity::find`, а не
`Okay\Core\Entity\CRUD::find`.

Розширюване не все: `Entity::getSelect()` навмисно віддає результат без хука, як і
`Entity::flush()`.

`Entity::customChangeSelect()` розширюваний — через нього модуль отримує готовий
`Select` і може дописати в нього умову. Але **аліаси джойнів не є контрактом**:
сутність вільна не приєднати таблицю, якої не просив жоден фільтр. У
`FeaturesValuesEntity` так і зроблено — `pf`, `f` і `p` зʼявляються лише за
потреби. Умова, що посилається на відсутній аліас, не впаде помітно:
`Database::query()` запише помилку в `Okay/log/` і поверне `false`, тобто
вибірка мовчки стане порожньою. Якщо модулю потрібна чужа таблиця — приєднуйте
її самі або оголосіть власний фільтр через `registerEntityFilter()`.

## Два види

| | Ланцюгове (`chain`) | Чергове (`queue`) |
| --- | --- | --- |
| Навіщо | змінити результат | побічна дія: лист, лог, запис у базу |
| Повертає значення | **мусить** | ігнорується |
| Що отримує | результат попереднього розширення в ланцюгу | остаточний результат ланцюга |
| Коли виконується | першим | після всіх ланцюгових |

Спершу відпрацьовують усі ланцюгові — кожен наступний отримує результат попереднього.
Отримане значення повертається викликачу **і** передається всім черговим.

## Аргументи

Метод розширення отримує:

1. **першим аргументом** — значення, що розширюється;
2. **далі** — усі аргументи, з якими викликали початковий метод.

```php
// розширюємо DeliveriesHelper::prepareDeliveryPriceInfo($delivery, $cart)
public function setCartDeliveryPrice($deliveryPriceInfo, $delivery, $cart)
{
    // …
    return $deliveryPriceInfo;   // обов'язково для ланцюгового
}
```

Необов'язковий аргумент початкового методу має бути необов'язковим і в розширенні.

## Ланцюгове розширення

```php
// Okay/Modules/OkayCMS/NovaposhtaCost/Init/Init.php
public function init()
{
    $this->registerChainExtension(
        [DeliveriesHelper::class, 'prepareDeliveryPriceInfo'],
        [FrontExtender::class, 'setCartDeliveryPrice']
    );
}
```

## Чергове розширення

```php
$this->registerQueueExtension(
    [OrdersHelper::class, 'finalCreateOrderProcedure'],
    [FrontExtender::class, 'setCartDeliveryDataProcedure']
);
```

Обидва методи приймають і довшу форму — `['class' => X::class, 'method' => 'y']`. Модулі
репозиторію користуються короткою.

## Клас розширення

Живе в `Extenders/`, за домовленістю — `FrontExtender.php` і `BackendExtender.php`.
**Мусить реалізовувати `Okay\Core\Modules\Extender\ExtensionInterface`** — це порожній
інтерфейс-мітка, але без нього реєстрація кидає виняток.

```php
// Okay/Modules/OkayCMS/Banners/Extenders/FrontExtender.php
namespace Okay\Modules\OkayCMS\Banners\Extenders;

use Okay\Core\Modules\Extender\ExtensionInterface;

class FrontExtender implements ExtensionInterface
{
    private $entityFactory;
    private $design;

    public function __construct(EntityFactory $entityFactory, Design $design, Module $module, BannersHelper $bannersHelper)
    {
        $this->entityFactory = $entityFactory;
        $this->design = $design;
        // …
    }
}
```

## Реєстрація

Потрібні **два** кроки:

1. **`Init::init()`** — прив'язати тригер до обробника (приклади вище).
2. **`Init/services.php`** — оголосити клас розширення сервісом:

```php
FrontExtender::class => [
    'class' => FrontExtender::class,
    'arguments' => [
        new SR(EntityFactory::class),
        new SR(Design::class),
    ],
],
```

Другий крок формально необов'язковий: якщо сервіса немає, розширення створюється через
`new $class()` **без аргументів**. Конструктор з обов'язковими параметрами в такому разі
покладе запит — тобто без `services.php` розширення може мати лише порожній конструктор.

## Що перевіряється під час реєстрації

Реєстрація кидає виняток, якщо:

- методу, який розширюють, не існує — `Expandable "Class::method()" is not a method`;
- метод розширення не викликається — `Method Class::method is not callable`;
- клас розширення не реалізує `ExtensionInterface`.

Усі три спрацьовують в `init()`, тобто на кожному запиті — помилку видно одразу.

## Порядок виконання

Розширення виконуються в порядку реєстрації модулів, тобто за `ok_modules.position`. Якщо два
модулі підв'язались ланцюгом до одного методу, другий отримає результат першого. Саме тому
порядок модулів у списку значущий.

## Застарілі методи

`Okay/Core/config/deprecated_methods.php` дозволяє перенаправити розширення зі старого методу
на новий: модуль, підв'язаний до застарілого імені, продовжує працювати, але отримує
`E_USER_DEPRECATED`. Наразі мапа порожня; формат описаний у шапці самого файлу.

## Коли розширення не підходить

- Треба змінити розмітку чужого шаблону → блок `modifications` у `module.json`
  ([../tpl-modifications.md](../tpl-modifications.md)).
- Треба вставити свою розмітку у відведене місце → блоки дизайну
  ([backend.md](backend.md#блоки-дизайну), [frontend.md](frontend.md#блоки-дизайну)).
- Треба додати умову у вибірку чужої сутності → фільтр сутності
  ([init-reference.md](init-reference.md#registerentityfilter)).
- Треба додати свою мережу в кнопки «поділитися» → `Okay\Core\SocialShare::addNetwork()`
  з `Init::init()`. Сервіс у контейнері один, тож мережу побачать і список галочок у
  налаштуваннях теми, і кнопки на вітрині:

  ```php
  ServiceLocator::getInstance()
      ->getService(SocialShare::class)
      ->addNetwork('mastodon', 'Mastodon', 'https://example.social/share?text={title}%20{url}');
  ```

  `{url}` і `{title}` підставляються вже закодованими. Розширювати
  `BackendSettingsHelper::getJsSocials()` чи `getJsCustomSocials()` для цього не треба —
  обидва лишились тільки заради сумісності й на вітрину більше не впливають.
