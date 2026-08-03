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

Близько 80 тестових класів. Найбільші групи:

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

### Набір перевірений мутацією

Кожен із **79 класів** пройшов мутацію: у код чи артефакт вносилась саме та поломка, яку тест
обіцяє ловити. Усі падають — декоративних тестів у наборі немає.

Якщо повторюєте таку перевірку, мутація має змінювати **спостережувану** поведінку і бити
саме в те, що тест обіцяє. `SessionNames::isValidSessionId` можна зробити завжди-істинним, і
`SessionNamesTest` не помітить — сесії з підробленим ідентифікатором усе одно немає, назовні
нічого не змінилось.

### Твердження про ім'я — цілим словом

`assertStringContainsString('ClassName', $source)` проходить і після перейменування класу, бо
довше ім'я **містить** старе. `\b` не рятує: у `X-Frame-Options-ZZ` межа слова після
`Options` є, дефіс — не символ слова. Кордон має забороняти й дефіс:

```php
$this->assertMatchesRegularExpression('~(?<![\w-])AdminRecoveryToken(?![\w-])~', $source);
```

### Межа жанру

Статичний вартовий бачить, що виклик **є**, але не те, що він **робить**: `AdminLogoutTest`
проходить, навіть якщо зробити `destroyBackend()` порожнім. Такі місця закриваються лише
поведінковими тестами.

## PHPStan

Конфіг — `phpstan.neon`, рівень **5**, аналізуються `Okay` і `backend` (не `tests`). Легасі
зафіксоване в `phpstan-baseline.neon` (947 записів) — новий код перевіряється по-справжньому,
прогін має бути порожнім.

```bash
php vendor/bin/phpstan analyse
```

### Baseline прив'язаний до стану дерева

`reportUnmatchedIgnoredErrors: false` гасить запис, який більше ні на що не вказує, — правка,
що зсуває рядки у вже описаному файлі, не має вимагати редагування конфіга.

Зворотний бік цим **не** покривається: нова помилка в описаному файлі або перевищення записаної
кількості — це звичайні помилки, і прогін впаде. Тому після злиття гілок, які правлять уже описані
файли, baseline треба перегенерувати:

```bash
php vendor/bin/phpstan analyse --generate-baseline
```

### Локально зелено ≠ зелено в CI

У раннері GitHub встановлені розширення, яких немає в нашому образі — зокрема `imagick`. PHPStan
із завантаженим розширенням бере справжню рефлексію, без нього — власний стаб, і значення констант
різняться. Запис baseline, що містить таке значення в тексті повідомлення, збігається локально й не
збігається в CI.

Одного разу це приховало справжній дефект. Шукати такі міни так:

```bash
grep -n "[0-9] given" phpstan-baseline.neon
```

Записи з символічними іменами (`PhoneNumberFormat::E164`) безпечні; небезпечні ті, де стоїть
числове значення константи розширення. Після злиття дивіться на статус прогону, а не лише на
локальний:

```bash
gh run list --repo devSviat/OkayCMS --branch main --limit 5 --json name,conclusion,headSha
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

## Що робить CI

`.github/workflows/ci.yml` — на кожен PR:

| Крок | Що перевіряє |
| ---- | ------------ |
| `composer validate --strict` | коректність `composer.json` |
| `composer audit --locked` | вразливості в залежностях; `--abandoned=report`, тобто покинутий пакет не валить збірку |
| `vendor/bin/phpcs -q` | сумісність із версією PHP, на матриці 8.4 і 8.5 |
| `vendor/bin/phpunit` | тести, на тій самій матриці |
| `vendor/bin/phpstan analyse` | статичний аналіз, окремою джобою на 8.5 |
| `gitleaks detect` | секрети по **всій** історії |

Розібрані вручну спрацювання Gitleaks перелічені у `.gitleaksignore` поіменно — не звуженням
області сканування, інакше новий секрет у тих самих файлах теж лишився б непоміченим.

`.github/workflows/docker-security.yml` — Trivy по зібраних прод-образах; на PR не запускається,
лише на push у `main` і за розкладом.

## Перед PR

```bash
cd dev && docker compose exec php85 php vendor/bin/phpunit
cd dev && docker compose exec php85 php vendor/bin/phpcs
cd dev && docker compose exec php85 php vendor/bin/phpstan analyse
dev/bin/smoke.sh
```

Після злиття — перевірте, що прогін у CI зелений: локальний і CI-прогін не еквівалентні, див.
розділ про PHPStan вище.
