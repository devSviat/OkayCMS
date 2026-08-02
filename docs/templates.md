# Шаблони

Шаблонізатор — Smarty **5.8**. Обгортка над ним — `Okay\Core\Design`.

## Теми

Теми вітрини лежать у `design/<тема>/`; активна визначається налаштуваннями магазину.
Шаблони адмінки — у `backend/design/html/` і темами не перемикаються.

```
design/<тема>/
  html/            .tpl-шаблони
  css/  js/  images/
  lang/            переклади вітрини
  css.php  js.php  реєстрація стилів і скриптів теми
  modules/<Vendor>/<Module>/   перевизначення файлів модуля цією темою
```

Каталог `modules/` у темі перевизначає файли модуля: якщо тема має власну версію шаблону,
стилю чи скрипта, береться вона. Так можна правити вигляд модуля, не чіпаючи сам модуль.

Скомпільовані шаблони — у `compiled/<тема>/`, кеш — у `cache/`. Після зміни блоку
`modifications` у `module.json` каталог `compiled/` треба почистити.

## `Design`

| Метод | Що робить |
| ----- | --------- |
| `assign($var, $value, $dynamicJs = false)` | передати змінну в шаблон |
| `assignJsVar($var, $value)` | передати змінну в JS; читається як `okay.var_name` |
| `fetch($template, $forceMinify = false)` | відрендерити шаблон і повернути рядок |
| `templateExists($tplFile)` | чи існує такий шаблон |
| `setModuleDir($moduleClassName)` / `rollbackTemplatesDir()` | тимчасово перемкнути каталог шаблонів на каталог модуля й повернути назад |
| `getVar($name)` | прочитати раніше призначену змінну |

Третій аргумент `assign()` — `$dynamicJs`: значення додатково кладеться в
`scripts.tpl` теми як звичайна Smarty-змінна.

Пара `setModuleDir()` / `rollbackTemplatesDir()` потрібна модулю, який рендерить власний
`.tpl` із розширення чи хелпера. Виклик обов'язково парний — стек попередніх каталогів
розкручується у зворотному порядку.

У контролері вміст сторінки віддають через `Response::setContent()`
([controllers.md](controllers.md#відповідь)).

## Пастки Smarty 5

Це не теоретичні застереження: кожна з трьох ламала цей магазин, і на кожну є тест, який не
дає їй повернутись.

### `{function}` тримає присвоєння локальними

У Smarty 4 присвоєння всередині `{function}` було видиме назовні, у Smarty 5 — ні. Лічильник
чи прапорець, який шаблон накопичує у `{function}` і читає після неї, **тихо збивається**:
розмітка лишається валідною, сторінка не падає, значення неправильні.

Саме так `backend/design/html/menu.tpl` видавав два однакових `index`, а контролер розкладає
пункти меню в масив за `index` — і збереження меню втрачало пункти.

Лікування: явний `scope=`, `{counter}` або відмова від перетину межі `{function}`.
Стереже `tests/Design/NoCrossScopeFunctionVariableTest.php`.

### Модифікатори, що працюють через посилання, не працюють

Smarty передає модифікатору значення, а не посилання, тож `reset()`, `key()`, `next()`,
`prev()`, `end()` і `each()` у шаблоні не працюють — навіть зареєстровані. У Smarty 4 вони
мовчки працювали, тому в шаблонах і завелись; Smarty 5 каже це прямо.

Заміни: `|first` і `|first_key` — обидва беруть значення з власної копії масиву.
Стереже `tests/Design/NoByReferenceModifiersTest.php`.

### Модифікатор у позиції виклику функції

`{date('Y-m-d')}` потрапляє не в PHP-функцію, а в плагін із тегом `date` — і той повертає
формат замість дати. Деталі — [smarty-plugins.md](smarty-plugins.md#пастка-smarty-5-модифікатор-у-позиції-виклику).

## Нативні PHP-функції

Smarty 5 не викликає нативну функцію, поки її не зареєстровано. Директив `php_functions` і
`php_modifiers` у політиці безпеки більше не існує — єдиний спосіб дозволити функцію це
зареєструвати її як модифікатор.

Дозволений перелік — `Design::$allowedPhpFunctions`. Він **не залежить** від
`smarty_security`: із вимкненою політикою шаблони мають працювати так само.

## Статичні класи

Дозволені класи перелічені в `Design::STATIC_CLASSES`; Smarty шукає їх за **літеральним
токеном**, тому `\Okay\Core\Phone` і `Okay\Core\Phone` — різні ключі.

### `Okay\Core\Phone`

Форматування телефонів. Доступний у шаблонах як статичний клас і як модифікатор `|phone`.

```php
public static function format($phoneNumber, $numberFormat = null): string
public static function toSave($phoneNumber): ?string
public static function clear($phoneNumber): string
public static function isValid($phoneNumber): bool
public static function resolveFormat($numberFormat): PhoneNumberFormat
public function getPhoneExample(): string
```

```smarty
{$user->phone|phone}
```

**`$numberFormat` — не `int`.** У libphonenumber 9 `PhoneNumberFormat` став **enum**, і саме
тому в класі з'явився `resolveFormat()`: налаштування `phone_default_format` зберігається
числом і числом же приходить із шаблонів, тож перед передачею в бібліотеку його треба
перетворити на випадок enum. Нерозпізнане значення дає `E164`.

Формати:

| Випадок enum | Приклад |
| ------------ | ------- |
| `E164` | `+380442903833` |
| `INTERNATIONAL` | `+380 44 290 3833` |
| `NATIONAL` | `044 290 3833` |
| `RFC3966` | `tel:+380-44-290-3833` |

`format()` без явного формату бере його з налаштувань магазину. `toSave()` **завжди** пише
E164 — у базі телефони зберігаються саме так — і повертає `null`, якщо номер розібрати не
вдалося:

```php
$order->phone = Phone::toSave($this->request->post('phone'));
```

`isValid()` валідує з урахуванням регіону з налаштувань (`phone_default_region`).

## Конфіг шаблонізатора

Директиви `smarty_*` описані в [configuration.md](configuration.md#smarty). Найважливіші під
час розробки:

- `smarty_force_compile = true` — компілювати шаблон при кожному запиті; потрібно, коли
  налагоджуєте модифікації `.tpl`;
- `smarty_html_minify` — мініфікація HTML на виході;
- `smarty_security` — політика безпеки Smarty; каталоги, з яких дозволено включати файли,
  обмежені активною темою, `backend/design` і `Okay/Modules`.

## Далі

- Змінити чужий шаблон, не редагуючи його — [tpl-modifications.md](tpl-modifications.md).
- Власний тег у шаблоні — [smarty-plugins.md](smarty-plugins.md).
- CSS і JS — [assets.md](assets.md).
- Своя тема на цьому форку — [theme-porting.md](theme-porting.md).
