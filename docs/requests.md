# Requests

Клас-запит збирає згруповані дані з HTTP-запиту (переважно з POST) і віддає їх готовим
об'єктом. Це прошарок між `$_POST` і бізнес-логікою: контролер не розбирає поля форми сам, а
хелпер не знає, звідки взялися дані.

Живуть в `Okay/Requests/` (вітрина) і `backend/Requests/` (адмінка), імена класів
закінчуються на `Request`. Реєструються в `Okay/Core/config/requests.php` як звичайні сервіси
([di.md](di.md)); модуль оголошує свої в `Init/services.php`.

```php
// Okay/Core/config/requests.php
BackendProductsRequest::class => [
    'class' => BackendProductsRequest::class,
    'arguments' => [
        new SR(Request::class),
    ],
],
```

## Приклад

```php
// Okay/Requests/CommonRequest.php
namespace Okay\Requests;

use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Request;

class CommonRequest
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function postComment()
    {
        $comment = null;
        if ($this->request->post('comment')) {
            $comment = new \stdClass;
            $comment->name  = $this->request->post('name');
            $comment->email = $this->request->post('email');
            $comment->text  = $this->request->post('text');
        }

        return ExtenderFacade::execute(__METHOD__, $comment, func_get_args());
    }
}
```

Використання в контролері:

```php
public function render(CommonRequest $commonRequest)
{
    if (($feedback = $commonRequest->postFeedback()) !== null) {
        // …
    }
}
```

## Контракт

**Метод запиту зобов'язаний повертати результат через `ExtenderFacade::execute()`** — навіть
порожній. Інакше модуль не зможе його розширити, а саме заради розширюваності цей шар і існує.

```php
return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
```

Три аргументи: ім'я методу, значення, яке повертаємо, і аргументи самого методу. У трейтах і
базових класах замість `__METHOD__` беруть `[static::class, __FUNCTION__]` —
[modules/extenders.md](modules/extenders.md).

## `Okay\Core\Request`

Низькорівневий доступ до запиту, яким користуються самі класи-запити:

```php
public function get($name, $type = null, $default = null, $stripTags = true)
public function post($name = null, $type = null, $default = null)
public function files($name, $name2 = null)
public function method($method = null)
public function isPost()
```

`$type` приймає `string`, `integer`/`int`, `float`, `boolean`/`bool`. Тип `string` не просто
приводить до рядка, а **вирізає все, крім літер, цифр, пробілів і `_ - . %`** — для тексту з
розділовими знаками він не підходить.

`get()` за замовчуванням ще й рекурсивно прибирає HTML-теги; `post()` цього не робить.
`post()` без імені повертає сире тіло запиту (`php://input`) — так читають JSON.

`files('myfile', 'name')` дістає елемент двовимірного `$_FILES`.

## Мутації вітрини

Клас-запит нічого не перевіряє щодо безпеки — це робить контролер. Будь-який запит вітрини, що
змінює стан, зобов'язаний першим ділом викликати `requireCustomerCsrf()`
([controllers.md](controllers.md#мутації-вітрини)).
