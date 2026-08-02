# Маршрути

Маршрути вітрини описані в `Okay/Core/config/routes.php`; маршрути модуля — у його
`Init/routes.php`. Адмінка маршрутів не має: там контролер береться з `?controller=`
([modules/backend.md](modules/backend.md#url)).

## Опис маршруту

```php
// Okay/Core/config/routes.php
return [
    'main' => [
        'slug' => '/',
        'params' => [
            'controller' => 'MainController',
            'method' => 'render',
        ],
    ],
    'cart_remove_item' => [
        'slug' => '/cart/remove/{$variantId}',
        'params' => [
            'controller' => 'CartController',
            'method' => 'removeItem',
        ],
        'patterns' => [
            '{$variantId}' => '([0-9]+)',
        ],
    ],
];
```

| Ключ | Роль |
| ---- | ---- |
| `slug` | шаблон URL; `{$ім'я}` — іменований параметр |
| `params.controller` | **обов'язковий**; ім'я без неймспейса доповнюється до `\Okay\Controllers\` |
| `params.method` | **обов'язковий** |
| `patterns` | регулярні вирази параметрів: `'{$ім'я}' => '([0-9]+)'` |
| `defaults` | значення, що не входять у `slug`, але приходять у метод контролера |
| `to_front` | віддати маршрут у JS: `okay.router['ім'я_маршруту']` |
| `always_active` | маршрут працює навіть при вимкненому сайті |
| `overwrite` | маршрут модуля може замістити системний із таким самим іменем |

Обидва ключі в `params` перевіряються одразу: відсутність будь-якого кидає
`Route "…" must contain two arguments named "controller" and "method" in "params" block`.
Так само перевіряються існування класу й методу — помилка в імені видно на першому ж запиті,
а не тоді, коли хтось відкриє цю адресу.

Є ще службовий ключ `mock`: система сама ставить його маршрутам неактивних модулів, і такі
маршрути не реєструються. Руками його не пишуть.

## Параметри маршруту

Іменований параметр `{$variantId}` приходить у метод контролера як аргумент **із таким самим
іменем і без тайп-хінта**:

```php
public function removeItem($variantId)
```

Без `patterns` параметр отримує вираз `([^/]+)` — «будь-що, крім слеша». `patterns`
підставляються в `slug` як є, тож регулярний вираз задає і формат, і те, чи параметр
обов'язковий.

Значення параметрів санітизуються перед передачею в контролер: `htmlspecialchars()` +
`strip_tags()`.

Щоб зробити параметр необов'язковим, дають аргументу контролера значення за замовчуванням і
пишуть дозвільний вираз у `patterns`. Якщо аргумент не отримав ані значення з маршруту, ані
значення за замовчуванням, роутер кидає `Missing argument "$x" in "Controller->method()"`.

`defaults` описує значення, яких немає в URL, але які потрібні методу:

```php
'defaults' => [
    '{$isIndex}' => true,
],
```

## Мовний префікс

Перед зіставленням до шаблону додається префікс поточної мови. Головна мова (рядок
`ok_languages` із найменшою `position`) префікса не має, і звернення до `/<її-label>/…`
редіректиться на адресу без префікса з кодом 301.

## Маршрут у модулі

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

Ім'я маршруту мусить бути унікальним у межах системи: збіг із системним кидає
`Route name "…" already uses`, якщо маршрут не оголосив `'overwrite' => true` — тоді він
системний замінює. Звідси домовленість іменувати маршрути модуля як `Vendor_Module_щось`.

Маршрути модуля мають пріоритет над системними при злитті, а маршрути **вимкненого** модуля
позначаються як `mock` і не реєструються взагалі.

## Генерація URL

```php
Router::generateUrl($routeName, $params = [], $isAbsolute = false, $langId = null)
```

- `$routeName` — ім'я маршруту;
- `$params` — асоціативний масив; ключі, що є в `slug`, підставляються в URL, решта йде в
  query-рядок;
- `$isAbsolute` — абсолютний URL;
- `$langId` — мова, для якої будувати адресу; для поточної можна не вказувати.

```php
use Okay\Core\Router;

$url = Router::generateUrl('cart_remove_item', ['variantId' => 1], true);
```

Порожнє ім'я маршруту кидає `Empty param "route"`, невідоме — `Route "…" not found`.

У шаблонах — плагін `{url_generator}`, обгортка над тим самим методом. Він приймає `route`,
`absolute`, `lang_id`, а решта параметрів іде в `$params`:

```smarty
{url_generator route='cart_remove_item' variantId=132 absolute=1}
```

У JS доступні лише маршрути з `'to_front' => true`:

```javascript
okay.router['cart_ajax']
```

## Поточний маршрут

| Метод `Router` | Що віддає |
| -------------- | --------- |
| `getCurrentRouteName()` | ім'я поточного маршруту |
| `getCurrentRouteParams()` | параметри без тайп-хінта зі `slug` |
| `getCurrentRouteRequiredParams()` | те саме, лише обов'язкові |
| `getRouteByName($routeName)` | увесь опис зазначеного маршруту |

## Гнучкі маршрути

Адреси товарів, категорій, брендів, сторінок і блогу будуються не сталим рядком, а класами з
`Okay/Core/Routes/`: вони віддають `slug`, `patterns` і `defaults` залежно від налаштувань
магазину — наприклад, чи входить категорія в URL товару. Ці ж класи відповідають за
канонізацію слеша в кінці адреси: зайвий або відсутній слеш дає 301 на канонічний варіант.
