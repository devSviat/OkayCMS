# Модуль в адмінці

## Контролер

Файл — `Backend/Controllers/<Name>.php`, неймспейс —
`Okay\Modules\<Vendor>\<Module>\Backend\Controllers`. Три вимоги, які перевіряються під час
реєстрації:

1. файл лежить саме в `Backend/Controllers/`;
2. клас успадковує `Okay\Admin\Controllers\IndexAdmin`;
3. клас має метод `fetch()`.

Порушення будь-якої кидає виняток на `init()`, тобто одразу.

```php
// Okay/Modules/OkayCMS/FAQ/Backend/Controllers/FAQsAdmin.php
namespace Okay\Modules\OkayCMS\FAQ\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Modules\OkayCMS\FAQ\Entities\FAQEntity;

class FAQsAdmin extends IndexAdmin
{
    public function fetch(EntityFactory $entityFactory)
    {
        $FAQEntity = $entityFactory->get(FAQEntity::class);

        $faqs = $FAQEntity->find(['limit' => 20]);
        $this->design->assign('faqs', $faqs);

        $this->response->setContent($this->design->fetch('faqs.tpl'));
    }
}
```

Аргументи `fetch()` резолвляться через DI за тайп-хінтом. Базовий клас дає `$this->design`,
`$this->request`, `$this->response` та інші сервіси.

Шаблон береться з `Backend/design/html/` модуля — систему на цей каталог перемикає сама
адмінка, коли бачить, що запитаний контролер належить модулю.

## Реєстрація і права

```php
public function init()
{
    $this->registerBackendController('FAQsAdmin');
    $this->addBackendControllerPermission('FAQsAdmin', 'okaycms__faq__faq');
}
```

`addBackendControllerPermission()` сам додає дозвіл у загальний список — окремий
`addPermission()` не потрібен. Він потрібен лише тоді, коли дозвіл є, а контролера під нього
немає:

```php
$this->addPermission('okaycms__novaposhta_cost');
```

Один контролер може мати лише один дозвіл: друга спроба кидає
`Permission for controller "…" already exists`.

Дозволи менеджера перевіряються в `IndexAdmin::onInit()` — вона повертає `bool`, і це і є гейт
авторизації.

## URL

```
?controller=Vendor.Module.ControllerName
?controller=Vendor.Module.ControllerName@methodName
```

Без `@` викликається `fetch()`. Ім'я контролера санітизується: усе, крім латиниці, цифр, крапки
і `@`, вирізається.

## Головний контролер модуля

```php
public function install()
{
    $this->setBackendMainController('FAQsAdmin');
}
```

Це контролер, на який відкривається модуль зі списку модулів. Пишеться **коротке ім'я класу**,
не `Vendor.Module.Controller`.

## Пункт меню

```php
$this->extendBackendMenu('left_faq_title', [
    'left_faq_title' => ['FAQsAdmin', 'FAQAdmin'],
]);
```

Перший аргумент — ключ перекладу групи меню. Наявний ключ означає, що пункти допишуться в
кінець наявної групи; новий — що з'явиться нова група. Ключі масиву — ключі перекладу пунктів,
значення — короткі імена контролерів (метод сам робить із них `Vendor.Module.Controller`).
Кілька контролерів на один пункт означає, що пункт лишається активним і на другому контролері
(типово: список + форма редагування).

Третій аргумент — іконка: або шлях до файлу відносно каталогу модуля, або текст SVG.

Дубль пари «група → пункт» кидає `Menu item by path … already in use`.

**Щоб дізнатися імена наявних груп**, увімкніть `dev_mode` — назви груп з'являться в меню
червоним ([../configuration.md](../configuration.md#dev_mode)).

Контролер, зареєстрований без власного пункту меню, автоматично прив'язується до розділу
«Модулі» — щоб під час роботи з ним активним лишався хоч якийсь пункт.

## Меню швидкого редагування

```php
$this->addFastMenuItem('property', [
    'controller' => 'Vendor.Module.Controller',
    'translation' => 'translation_var_add',
], [
    'controller' => 'Vendor.Module.Controller',
    'translation' => 'translation_var_edit',
    'params' => ['id' => 'id'],
    'action' => 'edit',
]);
```

Меню спливає при наведенні на елемент з атрибутом `data-property`. У кожного пункту
обов'язкові `controller` і `translation`; `action` приймає лише `add` або `edit`.

## Блоки дизайну

Адмінка розмічена іменованими блоками, у які модулі дописують свою розмітку. Кілька модулів в
одному блоці виводяться послідовно, у порядку модулів у списку.

```php
$this->addBackendBlock('product_variant', 'product_variant_block.tpl');
```

Файл шукається в `Backend/design/html/` модуля; неіснуючий кидає виняток.

Третім аргументом можна передати колбек, що виконається перед відмальовуванням блоку — його
аргументи резолвляться через DI:

```php
$this->addBackendBlock(
    'notification_counters',
    'counter_block.tpl',
    function (Design $design, CurrenciesEntity $currenciesEntity) {
        if (!$currenciesEntity->findOne(['code' => 'UAH'])) {
            $design->assign('uahCurrencyError', true);
        }
    }
);
```

**Щоб дізнатися імена блоків**, увімкніть `dev_mode`: в адмінці з'являться червоні підписи, а
наведення підсвічує межі блоку.

## Оновлення сутності через AJAX

```php
$this->extendUpdateObject('OkayCMS.FAQ.FAQEntity', 'okaycms__faq__faq', FAQEntity::class);
```

Дозволяє оновлювати сутність AJAX-запитом з адмінки — наприклад, перемикачем видимості в
списку. Псевдонім вказується в розмітці як `data-controller="OkayCMS.FAQ.FAQEntity"`. Дубль
псевдоніма кидає виняток.

## Переклади

`Backend/lang/<label>.php` — файли з масивом `$lang`:

```php
// Okay/Modules/OkayCMS/FAQ/Backend/lang/ua.php
$lang['left_faq_title'] = 'FAQ';
$lang['faq_add'] = 'Додати FAQ';
```

Файл вибирається за мовою менеджера; якщо його немає — береться `en.php`, а якщо немає і його —
перший файл у каталозі. Ці ж ключі використовуються в `extendBackendMenu()` і в `settings.xml`
(як `{$lang->ключ}`).

## Стилі й скрипти

`Backend/design/css.php` і `Backend/design/js.php` — [../assets.md](../assets.md).
Прямі теги `<script>` і `<link>` у шаблонах не використовуються.

## CSRF

Кожна форма адмінки має нести поле `session_id`:

```smarty
<input type="hidden" name="session_id" value="{$smarty.session.id}">
```

У цьому форку значення поля — **окремий CSRF-токен**, а не ідентифікатор сесії. Перевірка
відбувається **до** виклику контролера: POST без валідного токена отримує 403 `Session expired`
і до вашого `fetch()` не доходить. Деталі — [../UPGRADE-security.md](../UPGRADE-security.md).
