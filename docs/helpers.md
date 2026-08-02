# Helpers

Хелпер виносить логіку з контролера. Його метод перевикористовується в кількох місцях і, що
важливіше, **може бути розширений модулем**. Звідси правило: усе, що складніше за передачу
даних у шаблон, живе в хелпері, а не в контролері.

Живуть в `Okay/Helpers/` (вітрина) і `backend/Helpers/` (адмінка), імена класів закінчуються
на `Helper`. Реєструються в `Okay/Core/config/helpers.php` як звичайні сервіси
([di.md](di.md)); модуль оголошує свої в `Init/services.php`.

```php
// Okay/Core/config/helpers.php
BackendProductsHelper::class => [
    'class' => BackendProductsHelper::class,
    'arguments' => [
        new SR(EntityFactory::class),
        new SR(QueryFactory::class),
        new SR(Database::class),
        new SR(Image::class),
        new SR(Config::class),
        new SR(Request::class),
    ],
],
```

## Контракт

**Метод хелпера зобов'язаний повертати результат через `ExtenderFacade::execute()`.** Метод,
що повертає значення напряму, з модуля не розширюється — а це і є єдина причина, чому шар
хелперів існує.

```php
return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
```

Три аргументи: ім'я методу, значення, аргументи методу. У трейтах і базових класах —
`[static::class, __FUNCTION__]`, бо `__METHOD__` там дав би ім'я трейта
([modules/extenders.md](modules/extenders.md)).

Повертати треба **на кожній гілці**, включно з ранніми виходами:

```php
// Okay/Helpers/ProductsHelper.php
public function getProductList($filter = [])
{
    $productsEntity = $this->entityFactory->get(ProductsEntity::class);

    if ($this->settings->get('missing_products') === MISSING_PRODUCTS_HIDE) {
        $filter['in_stock'] = true;
    }

    $products = $productsEntity->mappedBy('id')->find($filter);

    if (empty($products)) {
        return ExtenderFacade::execute(__METHOD__, [], func_get_args());
    }

    $products = $this->attachVariants($products);

    return ExtenderFacade::execute(__METHOD__, $products, func_get_args());
}
```

Хелпер тут не просто дістає товари, а декорує результат `ProductsEntity::find()` — додає
варіанти й враховує налаштування магазину. Такий хелпер має сенс; хелпер, що лише
перенаправляє виклик у сутність, — переважно ні.

## Використання

```php
public function render(BrandsHelper $brandsHelper)
{
    $brands = $brandsHelper->getList(['visible' => 1], 'name');
    $this->design->assign('brands', $brands);
}
```

## `ValidateHelper`

Виняток із правила «один хелпер — одна сутність»: `Okay\Helpers\ValidateHelper` збирає
валідацію **всіх** [запитів](requests.md) вітрини. Методи називаються від зворотного —
повертають помилку, а не ознаку успіху:

```php
public function getUserError($user, $currentUserId): ?string
public function getUserRegisterError($user): ?string
public function getUserLoginError($email, $password): ?string
public function getFeedbackValidateError($feedback): ?string
public function getCartValidateError($order): ?string
public function getCallbackValidateError($callback): ?string
public function getCommentValidateError($comment): ?string
public function getSubscribeValidateError($subscribe): ?string
```

```php
// Okay/Helpers/ValidateHelper.php
public function getFeedbackValidateError($feedback): ?string
{
    $captchaCode = $this->request->post('captcha_code', 'string');

    $error = null;
    if (!$this->validator->isName($feedback->name, true)) {
        $error = 'empty_name';
    } elseif (!$this->validator->isEmail($feedback->email, true)) {
        $error = 'empty_email';
    } elseif (!$this->validator->isComment($feedback->message, true)) {
        $error = 'empty_text';
    } elseif ($this->settings->get('captcha_feedback')
        && !$this->validator->verifyCaptcha('captcha_feedback', $captchaCode)) {
        $error = 'captcha';
    }

    return ExtenderFacade::execute(__METHOD__, $error, func_get_args());
}
```

Типовий зв'язок «запит → валідація → дія» в контролері:

```php
public function render(CommonRequest $commonRequest, ValidateHelper $validateHelper)
{
    if (($feedback = $commonRequest->postFeedback()) !== null) {
        $this->requireCustomerCsrf();

        if ($error = $validateHelper->getFeedbackValidateError($feedback)) {
            $this->design->assign('error', $error);
        } else {
            // …
        }
    }
}
```

## Застарілі методи

Частина методів позначена як застаріла й лишена заради сумісності — вони викликають
`trigger_error(..., E_USER_DEPRECATED)` і делегують новому методу:

```php
// Okay/Helpers/BrandsHelper.php
public function getBrandsList($filter = [], $sort = null)   // → getList()
```

Перед тим як брати метод із прикладу в старому тексті, варто зазирнути в сам клас: у
`phpunit.xml` увімкнено `failOnDeprecation`, тож у тестах такий виклик валить збірку.

## Хелпери модуля

Модуль може мати власні хелпери — і це рекомендований спосіб організації його коду: винесена в
хелпер логіка стає доступною іншим модулям через розширення. Реєструються так само, як решта
сервісів модуля ([modules/structure.md](modules/structure.md#initservicesphp)).
