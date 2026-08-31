# Консоль і планувальник

CLI-точка входу — файл `ok` у корені проєкту. Побудований на Symfony Console **8**.

```bash
php ok                       # список команд
php ok <команда> --help      # довідка про команду
```

Якщо PHP на хості немає, усе те саме йде через контейнер:

```bash
cd dev && docker compose exec php85 php ok <команда>
```

## Що є

| Команда | Що робить |
| ------- | --------- |
| `database:deploy` | заливає чистий дамп бази |
| `module:create` | створює каркас нового модуля |
| `module:check-modifications` | перевіряє, що анкери `modifications` досі щось знаходять |
| `scheduler:run` | виконує всі готові до запуску задачі |
| `scheduler:task <task_id>` | виконує одну задачу |
| `scheduler:list` | показує перелік зареєстрованих задач |
| `release:build-package` | збирає пакет релізу форку (zip + `manifest.json` + `checksums.txt`) |

### `database:deploy`

```bash
php ok database:deploy [--file_path=1DB_changes/okay_clean.sql] [--yes]
```

`--yes` (`-y`) пропускає підтвердження — для CI, скриптів провізіонування й агентів. Штатний
`--no-interaction` тут не годиться: він повертає **типову** відповідь на підтвердження, а вона
`false`, тобто розгортання просто скасовується.

Виконує SQL-дамп інструкція за інструкцією; може перезаписати доступи до бази в конфізі й
почистити демо-вміст. **З механізмом модулів не пов'язаний**: він не викликає ані `Installer`,
ані міграції. Дамп містить таблицю `ok_modules` разом із рядками вбудованих модулів, тому
після розгортання вони вже «встановлені» — див.
[modules/lifecycle.md](modules/lifecycle.md#модулі-що-постачаються-з-системою).

### `module:create`

Інтерактивно питає вендора й ім'я модуля і розкладає каркас —
[modules/quick-start.md](modules/quick-start.md).

### `module:check-modifications`

```bash
php ok module:check-modifications [--all] [--theme=okay_shop] [-v]
```

Перевіряє, що кожен анкер `modifications` із `module.json` увімкнених модулів досі збігається
з вузлом шаблона. Мертвий анкер не кидає винятку й нічого не пише в лог: модуль лишається
увімкненим, його сторінка в адмінці відкривається, а вставки на сторінці немає.

`--all` бере й вимкнені модулі, `--theme=` задає тему для фронтових модифікацій (типово
активна), `-v` показує здорові анкери й файли, у яких вони збіглися. Ненульовий код виходу —
є мертвий анкер або модуль, увімкнений у базі, але відсутній у коді.

Що саме перевіряється й чого перевірка не робить — [tpl-modifications.md](tpl-modifications.md#як-перевірити-що-анкери-живі).

### `release:build-package`

```bash
php ok release:build-package --fork-version=1.1.0 [--repo-path=…] [--output-dir=build/release] [--manifest=release-manifest.json] [--migrations=dev/release-migrations] [--upstream-base=…]
```

Пакує репозиторій у пакет релізу форку — вхід `UpdateDownloader`/`UpdatePackage` на
боці CMS: `manifest.json` (шлях → sha256), `checksums.txt` для самого архіву, і
**всі** `.up.sql` з `dev/release-migrations/` (рекурсивно, не лише нові з часу
попереднього релізу — [чому](updates.md#оновлення-через-кілька-версій)).
`--upstream-base` типово береться з
`Config::$version` за `--repo-path`. Викликається з `.github/workflows/release.yml`
при тегуванні релізу — вручну лише для локальної перевірки пакування. Що споживає
результат на боці інсталяції — [updates.md](updates.md).

## Своя команда

Клас успадковує `Okay\Core\Console\Command`, ім'я оголошується **атрибутом `#[AsCommand]`**:

```php
namespace Okay\Core\Console\Commands\Database;

use Okay\Core\Console\Command;
use Okay\Core\Config;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'database:deploy', description: 'Deploys a clean database.')]
class DatabaseDeployCommand extends Command
{
    protected function configure(): void
    {
        $this->setHelp('This command allows you to deploy a clean database.');
    }

    protected function handle(Config $config): int
    {
        // …
        return Command::SUCCESS;
    }
}
```

Дві речі, які відрізняються від звичайної Symfony-команди:

- **Реалізується `handle()`, а не `execute()`.** Базовий клас оголошує `execute()` як
  `final` і делегує в `handle()`, попередньо резолвлячи його аргументи через DI за
  тайп-хінтом. Команда без `handle()` кидає `Command must implement method 'handle'.`
- **`protected static $defaultName` більше не працює.** Symfony його прибрав, і команда без
  імені кладе **весь `./ok`** фатальною помилкою ще на реєстрації — не на виклику цієї
  команди, а на запуску будь-якої. За цим стежить `tests/Core/Console/CommandNamesTest.php`:
  він вимагає рівно один `#[AsCommand]` із непорожнім іменем і окремо шукає в коді залишки
  `$defaultName` / `$defaultDescription`.

Інтерактивні помічники базового класу:

```php
protected function ask(string $question, $default = null)
protected function askConfirmation(string $question, bool $default = true, string $trueAnswerRegex = '/^y/i')
protected function askChoice(string $question, array $choices, $default = null)
```

Команда додається в перелік `Okay\Core\Console\Application::$commands`. Реєстрація йде через
`addCommand()` — у Symfony 8 це заміна старому `add()`.

**Діагностика в консолі не глушиться.** Раніше `ok` починався з `error_reporting(false)`,
через що планувальник — єдина поверхня, що працює без нагляду, — була повністю німою. Тепер
`error_reporting(E_ALL)` при вимкненому `display_errors`: повідомлення йдуть у stderr через
`log_errors`, а не в stdout команди.

## Планувальник

### Запуск

Планувальник не демон: його треба смикати щохвилини із зовнішнього крона.

```
* * * * * php /шлях/до/проєкту/ok scheduler:run
```

`scheduler:run` сам вирішує, чи є що виконувати: кожна задача має власне cron-правило й захист
від накладання. Тобто щохвилинний виклик — це дешева перевірка «чи щось готове», а не запуск
усіх задач щохвилини.

У локальному оточенні це робить окремий сервіс `scheduler` (supercronic) із тим самим
crontab-рядком. Логи: `docker compose logs scheduler`.

### Реєстрація задачі

Задачі реєструє модуль в `init()`:

```php
use Okay\Core\Scheduler\Schedule;

public function init()
{
    $this->registerSchedule(
        (new Schedule([NPCacheHelper::class, 'cronUpdateCitiesCache']))
            ->name('Parses NP cities to the db cache')
            ->time('0 0 * * *')
            ->overlap(false)
            ->timeout(3600)
    );
}
```

Задачі існують **тільки після підняття модулів**, тому всі три команди планувальника спершу
піднімають увімкнені модулі.

### `Schedule`

Конструктор приймає задачу в одному з трьох виглядів:

| Вигляд | Формат | Приклад |
| ------ | ------ | ------- |
| команда оболонки | рядок | `'whoami'` |
| виклик методу | `[Клас, 'метод']` | `[NPCacheHelper::class, 'cronUpdateCitiesCache']` |
| анонімна функція | `Closure` | `function (ProductsEntity $e) { … }` |

Аргументи методу чи функції резолвляться через DI за тайп-хінтами й значеннями за
замовчуванням.

```php
public function name(string $name): self          // назва в переліку задач
public function time(string $time): self          // cron-вираз; типово '0 0 * * *'
public function timeout(int $timeout): self       // секунди; типово 3600
public function overlap(bool $value = true): self // типово true — дозволено кілька екземплярів
```

`overlap(false)` бере блокування на час виконання. Ключ блокування — хеш команди; для
`Closure` — хеш її вихідного тексту й статичних змінних.

### Виконання

`scheduler:run` **запускає кожну готову задачу окремим підпроцесом** (`php ok scheduler:task
<id>`), стежить за таймаутом кожного й пише в лог момент старту, завершення або таймауту.
Задача, що перевищила `timeout`, знімається.

Логи — `Okay/log/scheduler/scheduler.log`, з ротацією.

### Команди

```bash
php ok scheduler:run [-f|--force]
php ok scheduler:task [-f|--force] <task_id>
php ok scheduler:list
```

`-f` виконує задачі в обхід правил часу й накладання. `scheduler:list` друкує таблицю з
id, назвою, часом і командою.
