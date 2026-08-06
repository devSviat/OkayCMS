# `mkdir` у ядрі: `recursive` і перевірка результату — план виконання

**Goal:** Прибрати мовчазний провал створення каталогів у ядрі: дев'ять місць переходять на
ідіому `AttemptLimiter`, `Design` кидає виняток замість попередження, `compiled/` починає
існувати на свіжому клоні.

**Спека:** [2026-08-06-mkdir-recursive-design.md](../specs/2026-08-06-mkdir-recursive-design.md)

## Global Constraints

- Коментарі українською або англійською, терсні; на простому коді коментаря немає.
- Комміти без трейлерів `Co-Authored-By` і без згадок Claude/Anthropic.
- Права `0777` не чіпаємо — окреме питання.
- Гейти: `phpunit`, `phpstan analyse`, `phpcs -q`. Код виходу міряти **без пайпа в `tail`**.
- Запуск у контейнері: `cd dev && docker compose exec -T php85 …`.

## Порядок

### Task 1: `Design` кидає виняток

**Files:** `Okay/Core/Design.php:179-182`, тест `tests/Core/DesignCompileDirTest.php`

- [ ] **Step 1: Тест**

```php
<?php


namespace Core;


use Okay\Core\Design;
use Okay\Core\MobileDetect;
use Okay\Core\Modules\Module;
use Okay\Core\Modules\Modules;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Core\TplMod\TplMod;
use Smarty\Smarty;

/**
 * Каталог компіляції створюється рекурсивно, а неможливість його створити - виняток,
 * а не попередження в лозі. Без нього Smarty не віддасть жодної сторінки.
 */
class DesignCompileDirTest extends \PHPUnit\Framework\TestCase
{
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->cleanup) as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
        $this->cleanup = [];
    }

    private function design(string $rootDir): Design
    {
        $frontTemplateConfig = $this->createStub(FrontTemplateConfig::class);
        $frontTemplateConfig->method('getTheme')->willReturn('okay_shop');

        return new Design(
            new Smarty(),
            $this->createStub(MobileDetect::class),
            $frontTemplateConfig,
            $this->createStub(Module::class),
            $this->createStub(Modules::class),
            $this->createStub(TplMod::class),
            0, false, false, false, false, false, false,
            $rootDir
        );
    }

    public function testCompileDirIsCreatedWithMissingParent()
    {
        $rootDir = sys_get_temp_dir() . '/okay-design-' . getmypid() . '-' . uniqid() . '/';
        $this->cleanup[] = $rootDir;
        $this->cleanup[] = $rootDir . 'compiled';
        $this->cleanup[] = $rootDir . 'compiled/okay_shop';
        mkdir($rootDir);

        $this->design($rootDir);

        $this->assertDirectoryExists($rootDir . 'compiled/okay_shop');
    }

    public function testExistingCompileDirIsLeftAlone()
    {
        $rootDir = sys_get_temp_dir() . '/okay-design-' . getmypid() . '-' . uniqid() . '/';
        $this->cleanup[] = $rootDir;
        $this->cleanup[] = $rootDir . 'compiled';
        $this->cleanup[] = $rootDir . 'compiled/okay_shop';
        mkdir($rootDir . 'compiled/okay_shop', 0777, true);
        file_put_contents($rootDir . 'compiled/okay_shop/marker.txt', 'x');
        $this->cleanup[] = $rootDir . 'compiled/okay_shop/marker.txt';

        $this->design($rootDir);

        $this->assertFileExists($rootDir . 'compiled/okay_shop/marker.txt');
    }

    /**
     * Батько-файл, а не права: тести можуть іти від root, який права обходить,
     * і перевірка була б зелена завжди.
     */
    public function testUncreatableCompileDirThrows()
    {
        $blocker = sys_get_temp_dir() . '/okay-design-blocker-' . getmypid() . '-' . uniqid();
        file_put_contents($blocker, 'not a directory');
        $this->cleanup[] = $blocker;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~compiled/okay_shop~');

        $this->design($blocker . '/');
    }
}
```

- [ ] **Step 2:** `docker compose exec -T php85 php vendor/bin/phpunit --filter DesignCompileDirTest` — очікується падіння третього тесту (виняток не кидається) і, можливо, першого.

- [ ] **Step 3: Реалізація.** У `Okay/Core/Design.php` замінити блок

```php
        // Создаем папку для скомпилированных шаблонов текущей темы
        if (!is_dir($this->smarty->getCompileDir())) {
            mkdir($this->smarty->getCompileDir(), 0777);
        }
```

на

```php
        // Повторна перевірка is_dir після невдалого mkdir - гонка двох запитів,
        // а не надмірність: обидва бачать !is_dir, обидва створюють, один програє.
        $compileDir = $this->smarty->getCompileDir();
        if (!is_dir($compileDir) && !@mkdir($compileDir, 0777, true) && !is_dir($compileDir)) {
            throw new \RuntimeException(sprintf(
                'Cannot create the Smarty compile directory "%s". Without it no page can be rendered.',
                $compileDir
            ));
        }
```

- [ ] **Step 4:** тест зелений, далі весь набір.
- [ ] **Step 5: Комміт.**

### Task 2: решта восьми місць + `.keep_folder`

**Files:** `Okay/Core/Modules/ModuleDesign.php:155`, `Okay/Core/Console/Commands/Module/ModuleCreateCommand.php:107,124`, `Okay/Core/TemplateConfig/FrontTemplateConfig.php:83,87`, `Okay/Core/TemplateConfig/BackendTemplateConfig.php:51,55`, `Okay/Core/Modules/LicenseStorage.php:17`, `compiled/.keep_folder`

- [ ] **Step 1.** Кожен `if (!is_dir($d)) { mkdir($d, …); }` стає

```php
        if (!is_dir($d) && !@mkdir($d, 0777, true) && !is_dir($d)) {
            return;
        }
```

у конструкторах, де `return` доречний, і `continue`/ранній вихід там, де цикл.

- [ ] **Step 2.** `ModuleCreateCommand::createModuleFiles()` наприкінці звіряє, що каталог
модуля з'явився; `handle()` друкує помилку й повертає `Command::FAILURE`, якщо ні.

- [ ] **Step 3.** `compiled/.keep_folder` — порожній файл, як в `Okay/xml/compiled/`.
Переконатись, що `.gitignore` його не ловить (`compiled/*/*.php` не ловить).

- [ ] **Step 4.** Гейти: phpunit, phpstan, phpcs -q (коди виходу окремо), `dev/bin/smoke.sh`,
`./ok scheduler:list`, вітрина 200.

- [ ] **Step 5: Комміт.**

## Фінальна перевірка

- [ ] `grep -rn "mkdir(" Okay/Core backend/Controllers` — жодного виклику без перевірки результату
- [ ] Свіжий клон у tmp: `compiled/` існує
- [ ] `git status` чистий
