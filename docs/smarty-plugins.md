# Smarty-плагіни

Плагін додає в шаблон свій тег. Працює в одному з двох режимів: **функція**
(`{some_plugin var1=foo}`) або **модифікатор** (`{$name|some_modifier:foo}`).

Системні плагіни лежать в `Okay\Core\SmartyPlugins\Plugins` і реєструються в
`Okay/Core/SmartyPlugins/SmartyPlugins.php`. Плагіни модуля — у `Plugins/` модуля, опис — в
`Init/SmartyPlugins.php` ([modules/frontend.md](modules/frontend.md#smarty-плагіни-модуля)).

## Клас плагіна

Успадковує `Okay\Core\SmartyPlugins\Func` (функція) або
`Okay\Core\SmartyPlugins\Modifier` (модифікатор) і реалізує метод `run()`. Тег береться з
властивості `$tag`, а якщо її немає — з короткого імені класу в нижньому регістрі.

```php
// Okay/Core/SmartyPlugins/Plugins/Plural.php
namespace Okay\Core\SmartyPlugins\Plugins;

use Okay\Core\SmartyPlugins\Modifier;

class Plural extends Modifier
{
    public function run($number, $singular, $plural1, $plural2 = null)
    {
        // …
    }
}
```

Базовий клас визначає тип **за успадкуванням**, причому через `is_subclass_of()` — проміжний
клас в ієрархії дозволений. Плагін, який не успадковує ні `Func`, ні `Modifier`, лишає тег
незареєстрованим, а Smarty 5 на невідомий тег кидає помилку компіляції.

## Пастка Smarty 5: не типізуйте об'єкт шаблону

У Smarty 4 другим аргументом плагіна-функції приходив `Smarty_Internal_Template`, у Smarty 5 —
`Smarty\Template`. Плагін, який тайп-хінтить будь-яке з цих імен, ламається на іншій версії з
`TypeError` — **у рантаймі**, а не на компіляції, тож жоден compile-гейт цього не побачить.

Тому правило тут не «використовуйте нову назву», а **не типізуйте цей аргумент узагалі**:

```php
public function run($params, $smarty)          // ✓
public function run($params, \Smarty\Template $smarty)   // ✗
```

За цим стежить `tests/Core/SmartyPlugins/PluginSignatureTest.php` — він валить будь-який
плагін, чий `run()` типізує клас із префіксом `Smarty`. Реєстраційна обгортка теж передає
шаблон як `$smarty = null`, без типу.

## Аргументи в режимі функції

Усі параметри виклику приходять одним асоціативним масивом:

```smarty
{some_plugin var1=foo var2=bar}
```

```php
public function run($params, $smarty)
{
    // $params = ['var1' => 'foo', 'var2' => 'bar'];
}
```

За домовленістю плагін приймає параметр `var` — ім'я змінної, у яку класти результат:

```smarty
{get_new_products var=new_products limit=5}
{if $new_products}
    {foreach $new_products as $product}…{/foreach}
{/if}
```

```php
if (!empty($params['var'])) {
    $smarty->assign($params['var'], $products);
}
```

## `{form_token}` — одноразовий токен форми

Плагін ядра, який друкує готове приховане поле:

```smarty
{form_token name="callback"}
```
→ `<input type="hidden" name="form_token" value="…">`

Імʼя поля одне на всі форми, а значення нове на кожен показ форми. Сервер веде облік витрачених
токенів окремо для кожного `name=`, тож форми на одній сторінці незалежні. Без `name=` плагін
не друкує нічого.

Токен захищає не від підробки запиту — це робить `customer_csrf_token` — а від зайвої повторної
відправки. Що з цим робити на боці сервера: [modules/frontend.md](modules/frontend.md#форма-в-модулі).

## Аргументи в режимі модифікатора

Перший аргумент — те, до чого застосували модифікатор; далі — параметри через двокрапку:

```smarty
{$product->name|some_modifier:foo:bar}
```

```php
public function run($productName, $param1, $param2 = null)
{
    // $param1 = 'foo'; $param2 = 'bar';
}
```

## Пастка Smarty 5: модифікатор у позиції виклику

Smarty 5 компілює виклик функції в шаблоні через пошук **модифікатора**. Тому `{date('Y-m-d')}`
потрапляє не в PHP-функцію `date()`, а в плагін із тегом `date`: плагін отримує формат замість
дати, не розбирає його й повертає як є. Шаблон компілюється чисто, помилки немає — у вивід
їде рядок `Y-m-d`.

Модифікатор викликається **тільки через пайп**: `{$value|date:'Y-m-d'}`. За цим стежить
`tests/Design/NoPluginTagInFunctionPositionTest.php`.

(Плагін-**функція**, викликана з дужками, у Smarty 5 не резолвиться взагалі й падає на
компіляції — її ловить звичайний compile-гейт. Тихо неправильний вивід дають саме
модифікатори.)

## Реєстрація

Плагіни — звичайні сервіси DI ([di.md](di.md)):

```php
// Okay/Core/SmartyPlugins/SmartyPlugins.php
$plugins = [
    Plugins\GetBrands::class => [
        'class' => Plugins\GetBrands::class,
        'arguments' => [
            new SR(EntityFactory::class),
            new SR(BrandsHelper::class),
        ],
    ],
];

$DI->bindServices($plugins);

foreach ($plugins as $plugin) {
    $p = $DI->get($plugin['class']);
    $p->register($DI->get(Design::class), $DI->get(Module::class));
}
```

`register()` не віддає тег Smarty напряму: він кладе замикання в чергу `Design`, а звідти
теги переносяться в Smarty перед першим `fetch()`. Причина — Smarty 5 кидає виняток на
повторній реєстрації тега, тож кожна реєстрація йде під перевіркою.

Для плагіна **модуля** обгортка додатково перемикає каталог шаблонів на каталог модуля перед
викликом `run()` і повертає назад після — щоб плагін міг рендерити власні `.tpl`.

## Плагіни вимкнених модулів

Тег вимкненого модуля не зникає — він підміняється заглушкою, що повертає порожній рядок.
Шаблон, який його викликає, не ламається, а мовчки нічого не друкує.

## Нативні PHP-функції в шаблоні

Smarty 5 не викликає нативну функцію, поки її не зареєстровано — однаково в позиції
модифікатора `{$x|trim}` і в позиції виклику `{max(1,$n)}`. Директив `php_functions` і
`php_modifiers` у політиці безпеки більше не існує.

Дозволений перелік — властивість `Design::$allowedPhpFunctions` (близько тридцяти функцій:
`trim`, `date`, `sprintf`, `preg_replace`, `json_decode`, `max`, `min` тощо). Реєструються
вони як модифікатори й **не залежать від `smarty_security`**: із вимкненою політикою шаблони
мають працювати так само.

Вбудованих модифікаторів Smarty в цьому списку навмисно немає: розширення резолвляться
раніше за наші реєстрації, тож запис був би мертвим.

## Статичні класи в шаблоні

Smarty забороняє статичний доступ до незареєстрованого класу. Перелік дозволених —
`Design::STATIC_CLASSES`. Пошук іде за **літеральним токеном**, як його написано в шаблоні,
тому `\Okay\Core\Phone` і `Okay\Core\Phone` — різні ключі, і обидві форми перелічені окремо.

Власного хука сюди модуль не має — класи модулів, потрібні їхнім шаблонам, перелічені в тому
самому списку.
