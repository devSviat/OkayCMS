# Перенесення теми на цей форк

Тема тут бере участь у захисті, а не лише в оформленні. Тема під стокову OkayCMS 4.5.2
на форку працюватиме частково і ламатиметься тихо: HTTP 200, порожня консоль, порожній лог.

Двигунова частина цих змін — [UPGRADE-security.md](UPGRADE-security.md).

## `design/okay_shop` тут — адаптована тема

Розходиться зі стоковою на 10 файлів:

```bash
git remote add upstream https://github.com/OkayCMS/OkayCMS.git   # один раз
git fetch upstream
git diff upstream/master main -- design/okay_shop
```

| Файл | Навіщо | Треба форку |
| ---- | ------ | ----------- |
| `js/okay.js` | CSRF-токен і POST для мутацій | так |
| `html/cart_purchases.tpl` | видалення з кошика: посилання → POST-форма | так |
| `html/pop_up_cart.tpl` | те саме у спливному кошику | так |
| `html/feedback.tpl` | токен у формі зворотного звʼязку | так |
| `html/password_remind.tpl` | форма встановлення нового пароля | так |
| `lang/{en,ru,ua}.php` | 6 ключів для тієї форми | так |
| `html/breadcrumb.tpl` | апстрімний баг | ні |
| `html/product_list.tpl` | апстрімний баг | ні |

Два останні до форку стосунку не мають: апстрімний шаблон звіряє `$controller` зі
значеннями `Comparison`, `LoginController` і `RegisterController`, яких немає в маршрутах
ні тут, ні в стоковій.

## Що правити у власній темі

### 1. Токен у кожній формі, що мутує

`AbstractController` призначає змінну в кожному шаблоні вітрини.

```smarty
<input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
```

### 2. Токен у кожному мутуючому ajax

Токен дублюється в куку `okay_csrf` — навмисно не `HttpOnly`, щоб скрипт її прочитав:

```javascript
function okayCsrfToken() {
  var match = document.cookie.match(/(?:^|;\s*)okay_csrf=([0-9a-f]{64})/);
  return match ? match[1] : "";
}
```

Далі `customer_csrf_token: okayCsrfToken()` у дані запиту і `type: "post"`.

### 3. Видалення з кошика — форма, а не посилання

`/cart/remove/{variantId}` більше не GET:

```smarty
<form method="post" action="{url_generator route="cart_remove_item" variantId=$purchase->variant->id}">
    <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
    <button type="submit">…</button>
</form>
```

### 4. Сторінка відновлення пароля

Посилання з листа більше не авторизує — воно веде на форму нового пароля. Шаблон обробляє
три стани: `$recovery_expired`, `$recovery_mode` і звичайну форму запиту листа. Форма
надсилає `reset_password=1`, `new_password`, `new_password_check`.

Мовні ключі (зразок — `design/okay_shop/lang/ua.php`):
`password_remind_letter_sent_generic`, `password_remind_expired`,
`password_remind_password_empty`, `password_remind_password_wrong`,
`password_remind_new_password`, `password_remind_save`.

### 5. `$_POST`, не `$_GET`

`cartAjax`, `wishlist` і `comparison` читають параметри з `$_POST`. Тема, що надсилає їх у
рядку запиту, отримає 403 або 405.

Орієнтир: у штатних темах 6 hidden-інпутів, 6 ajax-викликів і 2 дописування токена до вже
серіалізованої форми.

## Сумісність зі стоковою OkayCMS

Штатні теми ходять до кошика, обраного й порівняння через `okayAjax()`: параметри йдуть і в
тіло, і в рядок запиту (форк читає `$_POST`, стокова — `$_GET`), токен — тільки в тіло, щоб
не потрапити в логи й `Referer`. Зворотне не працює: стокова тема на форку не пройде
CSRF-перевірку.

**Перевірено частково.** Стокову 4.5.2 піднімали окремим стеком із гілки `master`:

- на PHP 8.5 не стартує — але через залежність, а не через сам двигун: стокова обмежує
  `aura/sql: "^3.0"` і фіксує 3.0.0, а підтримка PHP 8.4+ зʼявилась лише в aura/sql 6.x.
  Старий пакет зустрічається з новим статичним `PDO::connect()` і дає
  `Cannot make static method PDO::connect() non static`. `composer update` це не лікує —
  стеля `^3.0` не пускає до 6.x, треба правити стокову `composer.json`;
- на PHP 8.3 стартує, з темою звідси віддає `/`, `/catalog`, `/cart` як `200`;
- ajax кошика на тому ж стенді дає `500` і з нашою темою, і з апстрімною, і на звичайному
  GET — тобто ламається раніше, ніж транспорт починає мати значення.

Дуальний транспорт лишається задумом, а не виміряним фактом. Хто має робочу стокову на
PHP 8.0–8.2 — перевірка на пʼять хвилин, результат варто дописати сюди.

### Якщо сумісність не потрібна

Знімається патчем на три кроки в `js/okay.js`, окрема копія теми для цього не потрібна:

1. видалити `okayAjax()`;
2. замінити шість викликів `okayAjax({` на `$.ajax({` — `type: "post"` і
   `customer_csrf_token: okayCsrfToken()` там уже є;
3. `okayCsrfToken()` не чіпати — токен потрібен форку в будь-якому разі.

Шаблони не змінюються. Другої копії теми це не варте: `design/vibe_shop` — 102 файли й
25 256 рядків, сумісність зачіпає близько тридцяти.

## Як переконатись, що тема жива

Зелені тести цього не доводять — сторінок вони не відкривають. Руками: додати в кошик,
змінити кількість, видалити, обране, порівняння, зворотний звʼязок, підписка, відновлення
пароля.

`tests/Security/StorefrontCsrfGuardTest.php` перевіряє обидві теми на `okayCsrfToken`,
`okayAjax` і кількість викликів. Свою тему в репозиторії — додайте в провайдер цього тесту.
