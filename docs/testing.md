# Тести й статичний аналіз

PHP на хості може не бути — усі команди нижче так само працюють через контейнер:

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
```

## Запуск

```bash
php vendor/bin/phpunit                        # увесь набір
php vendor/bin/phpunit tests/Core/            # каталог
php vendor/bin/phpunit --filter TplModTest    # один клас

php vendor/bin/phpstan analyse                # статичний аналіз
php vendor/bin/phpcs                          # сумісність із версією PHP

dev/bin/smoke.sh                              # перевірка оточення; запускати з кореня
```

У `composer.json` **немає** скриптів `test`, `lint` чи `stan` — команди викликаються прямо.

## PHPUnit 13

Конфіг — `phpunit.xml`, набір — каталог `tests/`. Налаштування суворі й це навмисно:

```xml
failOnDeprecation="true"
failOnPhpunitDeprecation="true"
failOnNotice="true"
failOnWarning="true"
```

Тобто **`E_USER_DEPRECATED` валить тест**. Наслідок для повсякденної роботи: виклик
застарілого методу хелпера ([helpers.md](helpers.md#застарілі-методи)) або створення
`ServiceLocator` через `new` замість `getInstance()` — це не попередження, а падіння.

`tests/bootstrap.php` підключає автозавантажувач і `Okay/Core/config/constants.php`. Константи
потрібні саме там, бо дата-провайдери в PHPUnit 10+ **статичні** й виконуються ще до `setUp()`.

Що змінилось у 13 порівняно з тим, як писали раніше:

- анотації в докблоках не працюють — потрібні атрибути (`#[DataProvider]`, `#[Test]`);
- дата-провайдер має бути `public static`.

## Що покрито

Близько 80 тестових класів. Найбільші групи — і вони показові:

| Каталог | Класів | Про що |
| ------- | ------ | ------ |
| `tests/Security/` | 28 | CSRF вітрини й адмінки, вхід і відновлення пароля, файловий менеджер, обхід шляхів, заголовки, куки, SVG |
| `tests/Core/` | 28 | Design і Smarty-плагіни, TemplateConfig, консоль, QueryFactory, модулі, сутності |
| `tests/Design/` | 5 | компіляція шаблонів тем і заборонені конструкції Smarty 5 |
| `tests/TplMod/` | 3 | розбір і друк шаблонів |
| `tests/Modules/`, `tests/Admin/`, `tests/Helpers/`, `tests/Seo/`, `tests/Entities/` | 15 | решта |

### Тести-запобіжники

Частина тестів існує не для перевірки логіки, а щоб конкретна тиха поломка не повернулась.
Знати про них варто, бо саме вони падають при, здавалося б, нешкідливій правці:

| Тест | Що не дає зробити |
| ---- | ----------------- |
| `Design/TemplateCompileTest` | зламати компіляцію будь-якого шаблону теми чи адмінки |
| `Design/NoCrossScopeFunctionVariableTest` | покладатись на витік змінної з `{function}` — у Smarty 5 його немає |
| `Design/NoByReferenceModifiersTest` | `reset`, `key`, `next`, `prev`, `end` у шаблоні |
| `Design/NoPluginTagInFunctionPositionTest` | писати тег-модифікатор у позиції виклику: `{date('Y-m-d')}` |
| `TplMod/ThemeTemplatesTplModTest` | додати в шаблон конструкцію, на якій `TplMod` обрізає файл |
| `Core/SmartyPlugins/PluginSignatureTest` | типізувати обʼєкт шаблону в плагіні |
| `Core/Console/CommandNamesTest` | лишити команду без `#[AsCommand]` — це кладе весь `./ok` |
| `Core/QueryFactory/NoPositionalBindsTest` | позиційні `?` у `where()`, яких aura 3 не вміє |
| `Security/NoDatabaseChangeTest`, `Security/UpgradeNotesTest` | розійтись зі станом, зафіксованим ітерацією безпеки |

Причини кожного описані в докблоці самого тесту — там же історія, чому він з'явився.

## PHPStan

Конфіг — `phpstan.neon`, рівень **1**, аналізуються `Okay` і `backend` (не `tests`). Є
`phpstan-baseline.neon` із зафіксованими давніми зауваженнями — нові помилки мають бути
порожніми.

```bash
php vendor/bin/phpstan analyse
```

## PHP_CodeSniffer

`phpcs.xml.dist` — це **не стильовий** набір правил, а перевірка сумісності з версією PHP:
`PHPCompatibility` із `testVersion = 8.4-`. Перевіряються `Okay`, `backend`, `ok` і `tests`.

Попередження не валять перевірку (`ignore_warnings_on_exit`), гейтом є лише помилки. Одне
правило вимкнене свідомо — `ArgumentFunctionsReportCurrentValue`: воно спрацьовує на кожному
`ExtenderFacade::execute(__METHOD__, $result, func_get_args())`, тобто буквально всюди, і
стосується поведінки, яка діє з PHP 7.0.

## `dev/bin/smoke.sh`

Перевірка того, що локальне оточення справді піднялось: чекає, поки сервіси з healthcheck
стануть `healthy`, і далі перевіряє відповіді вітрини й адмінки.

Дивитись треба на **код виходу**, а не на текст: під `debug_mode` фатальна помилка друкується
в тіло сторінки, а HTTP-статус лишається 200
([configuration.md](configuration.md#пастка-фатал-під-debug_mode-віддає-http-200)) — тому
серед перевірок є окрема на витік PHP-діагностики в тіло.

Запускати з кореня репозиторію; `sleep` перед ним не потрібен — скрипт чекає сам.

## Перед PR

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
cd dev && docker compose exec php85 php vendor/bin/phpcs
cd dev && docker compose exec php85 php vendor/bin/phpstan analyse
dev/bin/smoke.sh
```
