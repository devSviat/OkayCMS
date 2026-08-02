# Контролери

Контролери діляться на фронтові (вітрина) і бекендові (адмінка). І ті, й ті бувають
системними та модульними.

## Фронтові контролери

Живуть у неймспейсі `Okay\Controllers\`. Викликаються маршрутом ([routes.md](routes.md)); один
контролер може мати кілька методів, на які вказують різні маршрути.

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

### `AbstractController`

Базовий клас — **`Okay\Controllers\AbstractController`**, не `Okay\Core\AbstractController`
(такого класу немає). Попри назву, він **не `abstract`** і не має жодного абстрактного методу:
успадкування нічого не зобов'язує реалізувати.

Він ініціалізує дизайн, мови, валюти, користувача, кошик, список бажань і порівняння, а також
призначає в шаблони CSRF-токен вітрини. Доступні поля:

| Поле | Що це |
| ---- | ----- |
| `$this->design` | шар шаблонів |
| `$this->request`, `$this->response` | запит і відповідь |
| `$this->settings`, `$this->config` | налаштування магазину й конфіг |
| `$this->entityFactory` | фабрика сутностей |
| `$this->router`, `$this->serviceLocator` | роутер і локатор служб |
| `$this->cart`, `$this->wishList`, `$this->comparison` | кошик, список бажань, порівняння |
| `$this->user`, `$this->group` | поточний покупець і його група |
| `$this->language`, `$this->languages` | поточна мова й перелік мов |
| `$this->currency`, `$this->currencies` | поточна валюта й перелік валют |
| `$this->page` | сторінка, що відповідає поточному URL |

Успадковувати `AbstractController` **не обов'язково**. Для легкого контролера (наприклад,
простого AJAX) можна не успадковувати — тоді все потрібне береться ін'єкцією в метод. Ціна:
у такого контролера немає ні `$this->design`, ні ініціалізованої мови, ні токена в шаблонах.
Реальний приклад — `Okay\Controllers\SubscribeController`, який через це носить власну копію
перевірки CSRF.

### Порядок викликів

Роутер створює контролер через `new` **без аргументів** — ін'єкції в конструктор немає — і
далі викликає:

1. `beforeController()` — якщо метод існує;
2. `onInit()` — якщо метод існує;
3. метод, зазначений у маршруті;
4. `afterController()` — **тільки якщо метод контролера не повернув `false`**.

Аргументи кожного з чотирьох резолвляться незалежно.

У `AbstractController` всі три хуки оголошені як `final` — перевизначити їх у нащадку не
можна. Контролер, що не успадковує `AbstractController`, може оголосити власні `onInit()` і
`afterController()`.

**Повернення `false` з методу означає 404**: роутер віддає `ErrorController::pageNotFound`.

### Сервіси й параметри маршруту в одному списку

Це головна річ, яку треба зрозуміти про аргументи контролера:

```php
// маршрут: '/cart/remove/{$variantId}'
public function removeItem(CartHelper $cartHelper, $variantId)
```

| Аргумент | Як резолвиться |
| -------- | -------------- |
| **з тайп-хінтом** | сервіс із контейнера; якщо тип — нащадок `Entity`, береться з `EntityFactory` |
| **без тайп-хінта** | параметр маршруту з таким самим іменем, далі — `defaults`, далі — значення за замовчуванням |

Тобто тайп-хінт — це і є перемикач між «дай сервіс» і «дай шматок URL». Порядок аргументів
довільний.

Якщо для аргументу без тайп-хінта не знайшлося ні параметра маршруту, ні `defaults`, ні
значення за замовчуванням — роутер кидає `Missing argument "$x" in "Controller->method()"`.
Тип, якого немає в контейнері, дасть виняток контейнера ([di.md](di.md#помилки-які-видає-контейнер)).

### Мутації вітрини

Будь-який метод, що змінює стан, зобов'язаний першим рядком викликати:

```php
protected function requireCustomerCsrf()
```

Метод не POST → 405, невалідний токен → 403, виконання припиняється. Токен доступний у
шаблонах як `$customer_csrf_token` і дублюється в куці `okay_csrf` (навмисно не `HttpOnly`,
щоб його читав AJAX теми).

У поточному коді це роблять контролери кошика, списку бажань, порівняння, зворотного зв'язку й
підписки. Деталі — [UPGRADE-security.md](UPGRADE-security.md), розмітка форм —
[theme-porting.md](theme-porting.md).

## Відповідь

`Okay\Core\Response`:

```php
public function setContent($content, $type = null): self
public function setContentType(string $type): self
public function setStatusCode($statusCode): self
public function addHeader($headerContent, $replace = true, $responseCode = null): self
public function sendStream(string $content, ?string $type = null): void
public static function redirectTo(string $resource, int $responseCode = 302): void
```

Типи вмісту — константи `RESPONSE_HTML`, `RESPONSE_JSON`, `RESPONSE_XML`, `RESPONSE_TEXT`.

```php
$this->response->setContent(json_encode($result), RESPONSE_JSON);
```

Три особливості, про які легко спіткнутись:

- `setContent()` **накопичує** вміст, а не заміщає: кілька викликів дадуть кілька частин
  відповіді;
- `setStatusCode()` **не приймає коди редіректів** — 301, 302, 303, 307, 308 кидають виняток
  із порадою взяти `redirectTo()`. Сам `redirectTo()` статичний і завершує виконання;
- `sendStream()` віддає дані негайно, тож тип вмісту й заголовки треба виставити **до** першого
  виклику.

## Бекендові контролери

Живуть у неймспейсі `Okay\Admin\Controllers` (каталог `backend/Controllers/`), успадковують
`Okay\Admin\Controllers\IndexAdmin`. Типовий метод — `fetch()`.

`IndexAdmin::onInit()` крім ініціалізації виконує **перевірку прав** і повертає `bool` — це і
є гейт авторизації адмінки. Конструктор `IndexAdmin` приймає три позиційні значення
(менеджер, ім'я контролера, метод) і викликається не роутером, а `backend/index.php`.

Маршрутів адмінка не має: контролер береться з `?controller=`, метод — після `@`, типово
`fetch()`.

## Контролери в модулі

- Фронтовий: клас у `Controllers/` модуля, маршрут у `Init/routes.php` —
  [modules/frontend.md](modules/frontend.md#сторінка-вітрини).
- Бекендовий: клас у `Backend/Controllers/`, реєстрація `registerBackendController()`, URL
  `?controller=Vendor.Module.ControllerName` — [modules/backend.md](modules/backend.md).
