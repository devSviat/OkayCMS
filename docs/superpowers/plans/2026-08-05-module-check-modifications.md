# Перевірка анкерів `modifications` — план виконання

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Зробити мертвий анкер `modifications` видимим: команда `module:check-modifications` на живому магазині плюс phpunit-гейт на теми в комплекті.

**Architecture:** Правило збігу лишається в `Okay\Core\TplMod\TplMod` і виноситься в три публічні методи (`matches()`, `findMatches()`, `resolveTarget()`), якими користується і рендер, і перевірка. Над ними — `Okay\Core\TplMod\ModificationChecker`, який розв'язує шлях шаблона так само, як `Design::applyTplModifiers()` (суфікс шляху, не «лежить у html теми»), і повертає статус на кожен `change`. Команда й тест — тонкі обгортки над чекером.

**Tech Stack:** PHP 8.4, PHPUnit 13 (`#[DataProvider]`, `createStub`), Symfony Console 8 (`#[AsCommand]`), власний DI-контейнер (`Okay/Core/config/services.php`), власна ORM (`ModulesEntity`).

**Спека:** [2026-08-05-module-check-modifications-design.md](../specs/2026-08-05-module-check-modifications-design.md)

## Global Constraints

- Коментарі — українською або англійською. Російською — ніколи, навіть якщо сусідній код ядра російською.
- Коментарі терсні; на простому коді коментаря немає взагалі.
- У повідомленнях комітів немає трейлерів `Co-Authored-By` і жодної згадки Claude/Anthropic.
- Не правити чужі модулі й `.tpl` тем. Ця робота торкається лише ядра, тестів і документації.
- CRUD — тільки через `Entity`; жодного сирого SQL.
- Тести й PHP запускаються в контейнері, якщо PHP немає на хості:
  `cd dev && docker compose up -d`, далі `docker compose exec php85 php vendor/bin/phpunit …`.
  Далі в плані команди наведені у формі `php vendor/bin/phpunit …` — додайте префікс, якщо треба.
- Механіку ліцензування не описувати ні в коді, ні в коментарях, ні в комітах.

## File Structure

| Файл | Відповідальність |
| --- | --- |
| `Okay/Core/TplMod/TplMod.php` *(modify)* | правило збігу: `matches()`, `findMatches()`, `resolveTarget()`; рендер переписаний на них |
| `Okay/Core/TplMod/CheckStatus.php` *(create)* | enum статусу перевірки одного `change` |
| `Okay/Core/TplMod/DTO/CheckResultDTO.php` *(create)* | результат перевірки одного `change` |
| `Okay/Core/TplMod/ModificationChecker.php` *(create)* | розв'язання шляхів шаблонів + прогін модифікацій через `TplMod` |
| `Okay/Core/config/services.php` *(modify)* | реєстрація `ModificationChecker` |
| `Okay/Core/Console/Commands/Module/ModuleCheckModificationsCommand.php` *(create)* | CLI: модулі з бази, тема, таблиця, код виходу |
| `Okay/Core/Console/Application.php` *(modify)* | реєстрація команди |
| `tests/TplMod/TplModMatchTest.php` *(create)* | `matches()` / `findMatches()` |
| `tests/TplMod/TplModResolveTargetTest.php` *(create)* | `resolveTarget()`, включно з регресом фаталу |
| `tests/TplMod/ModificationCheckerTest.php` *(create)* | чекер на фікстурах |
| `tests/TplMod/fixtures/` *(create)* | шаблони для тестів чекера |
| `tests/TplMod/BundledModificationsTest.php` *(create)* | гейт: 4 модулі × 2 теми × бекенд |
| `docs/cli.md` *(modify)* | опис команди |
| `docs/tpl-modifications.md` *(modify)* | розділ «Як перевірити, що анкери живі» |

---

### Task 1: правило збігу в `TplMod`

Витягуємо предикат і збирач вузлів. `walkByFile()` зберігає свій порядок обходу (вузол → його зміни → діти), інакше поміняється порядок застосування кількох модифікацій до одного дерева.

**Files:**
- Modify: `Okay/Core/TplMod/TplMod.php:50-65`
- Test: `tests/TplMod/TplModMatchTest.php`

**Interfaces:**
- Consumes: `Okay\Core\TplMod\Nodes\BaseNode`, `Okay\Core\Modules\DTO\TplChangeDTO`, `Okay\Core\TplMod\Parser`
- Produces:
  - `TplMod::matches(BaseNode $node, TplChangeDTO $change): bool`
  - `TplMod::findMatches(BaseNode $node, TplChangeDTO $change): array` — `BaseNode[]`, у порядку обходу зверху вниз, включно з переданим вузлом

- [ ] **Step 1: Написати падючий тест**

Створити `tests/TplMod/TplModMatchTest.php`:

```php
<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\Modules\DTO\TplChangeDTO;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;

class TplModMatchTest extends \PHPUnit\Framework\TestCase
{
    private function tplMod(): TplMod
    {
        return new TplMod(new Parser(), $this->createStub(Config::class));
    }

    public function testFindReturnsMatchedNode()
    {
        $tree = (new Parser())->parse('<div class="foo"><span>text</span></div>');

        $matches = $this->tplMod()->findMatches($tree, new TplChangeDTO('class="foo"', ''));

        $this->assertCount(1, $matches);
        $this->assertStringContainsString('class="foo"', $matches[0]->getOriginalElement());
    }

    public function testLikeReturnsMatchedNode()
    {
        $tree = (new Parser())->parse('<div class="foo"><span>text</span></div>');

        $matches = $this->tplMod()->findMatches($tree, new TplChangeDTO('', 'class="fo+"'));

        $this->assertCount(1, $matches);
    }

    /**
     * Рядок є у файлі, але жоден окремий вузол його не містить: парсер розкладає
     * це на елемент <i> і текстовий вузол усередині. Саме на цьому наївна перевірка
     * підрядком у тексті файлу дає хибно живий анкер.
     */
    public function testAnchorSpanningOpenAndCloseTagNeverMatches()
    {
        $tree = (new Parser())->parse('<i>{$purchase->variant->name|escape}</i>');

        $matches = $this->tplMod()->findMatches($tree, new TplChangeDTO('<i>{$purchase->variant->name|escape}</i>', ''));

        $this->assertSame([], $matches);
    }

    public function testSameAnchorInTwoNodesReturnsBoth()
    {
        $tree = (new Parser())->parse('<div class="row"></div><div class="row"></div>');

        $matches = $this->tplMod()->findMatches($tree, new TplChangeDTO('class="row"', ''));

        $this->assertCount(2, $matches);
    }

    public function testEmptyChangeMatchesNothing()
    {
        $tree = (new Parser())->parse('<div class="foo"></div>');

        $this->assertSame([], $this->tplMod()->findMatches($tree, new TplChangeDTO('', '')));
    }
}
```

- [ ] **Step 2: Запустити й переконатися, що падає**

Run: `php vendor/bin/phpunit --filter TplModMatchTest`
Expected: FAIL — `Call to undefined method Okay\Core\TplMod\TplMod::findMatches()`

- [ ] **Step 3: Реалізувати**

У `Okay/Core/TplMod/TplMod.php` замінити `walkByFile()` на:

```php
    /**
     * @param BaseNode $node
     * @param TplChangeDTO[] $changes
     * @return void
     */
    private function walkByFile(BaseNode $node, array $changes)
    {
        foreach ($changes as $changeDTO) {
            if ($this->matches($node, $changeDTO)) {
                $this->applyMod($node, $changeDTO);
            }
        }

        if ($node->children()) {
            foreach ($node->children() as $child) {
                $this->walkByFile($child, $changes);
            }
        }
    }

    /**
     * Правило збігу анкера. Єдине місце, де воно живе: ним користуються і рендер,
     * і ModificationChecker.
     */
    public function matches(BaseNode $node, TplChangeDTO $change): bool
    {
        if (!empty($change->getFind())) {
            return strpos($node->getOriginalElement(), $change->getFind()) !== false;
        }

        if (!empty($change->getLike())) {
            return (bool)preg_match('~'.$change->getLike().'~', $node->getOriginalElement());
        }

        return false;
    }

    /**
     * @return BaseNode[] вузли, з якими збігся анкер, у порядку обходу
     */
    public function findMatches(BaseNode $node, TplChangeDTO $change): array
    {
        $matched = [];

        if ($this->matches($node, $change)) {
            $matched[] = $node;
        }

        foreach ($node->children() as $child) {
            $matched = array_merge($matched, $this->findMatches($child, $change));
        }

        return $matched;
    }
```

- [ ] **Step 4: Запустити тести**

Run: `php vendor/bin/phpunit --filter TplModMatchTest`
Expected: PASS, 5 тестів

Run: `php vendor/bin/phpunit tests/TplMod/`
Expected: PASS — `TplModTest`, `TplModParserTest` і `ThemeTemplatesTplModTest` доводять, що рендер не змінився

- [ ] **Step 5: Комміт**

```bash
git add Okay/Core/TplMod/TplMod.php tests/TplMod/TplModMatchTest.php
git commit -m "refactor(TplMod): винести правило збігу анкера в matches()/findMatches()

Рендер лишається на тому самому обході; поруч з'являється збирач усіх
збігів, потрібний перевірці. Тест фіксує випадок, на якому наївна
перевірка підрядком помиляється: <i>{\$var}</i> у файлі є, а в жодному
окремому вузлі його немає."
```

---

### Task 2: `resolveTarget()` — другий шар збігу

`applyMod()` після пошуку анкера ще розв'язує ланцюжок `parent` → `closest*` → `children*`. Зараз обірваний `closestFind` лишає `$node = null` і валить сторінку фаталом; `childrenFind` без збігу мовчить. Обидва стають `null` з одного методу.

**Files:**
- Modify: `Okay/Core/TplMod/TplMod.php:67-100` (початок `applyMod()`)
- Test: `tests/TplMod/TplModResolveTargetTest.php`

**Interfaces:**
- Consumes: `TplMod::matches()` з Task 1
- Produces: `TplMod::resolveTarget(BaseNode $node, TplChangeDTO $change): ?BaseNode` — вузол, який `applyMod()` зрештою змінить, або `null`, якщо ланцюжок обірвався

- [ ] **Step 1: Написати падючий тест**

Створити `tests/TplMod/TplModResolveTargetTest.php`:

```php
<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\Modules\DTO\TplChangeDTO;
use Okay\Core\TplMod\Nodes\BaseNode;
use Okay\Core\TplMod\Nodes\HtmlNode;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;

class TplModResolveTargetTest extends \PHPUnit\Framework\TestCase
{
    private BaseNode $root;
    private HtmlNode $wrapper;
    private HtmlNode $inner;

    protected function setUp(): void
    {
        $this->root = new BaseNode('document');
        $this->wrapper = new HtmlNode('<div class="wrapper">', '</div>');
        $this->inner = new HtmlNode('<span class="inner">', '</span>');
        $this->wrapper->append($this->inner);
        $this->root->append($this->wrapper);
    }

    private function tplMod(): TplMod
    {
        return new TplMod(new Parser(), $this->createStub(Config::class));
    }

    public function testEmptyChainReturnsNodeItself()
    {
        $change = new TplChangeDTO('class="inner"', '');

        $this->assertSame($this->inner, $this->tplMod()->resolveTarget($this->inner, $change));
    }

    public function testParentReturnsParentNode()
    {
        $change = new TplChangeDTO('class="inner"', '');
        $change->setParent();

        $this->assertSame($this->wrapper, $this->tplMod()->resolveTarget($this->inner, $change));
    }

    public function testClosestFindReturnsMatchingAncestor()
    {
        $change = new TplChangeDTO('class="inner"', '');
        $change->setClosestFind('class="wrapper"');

        $this->assertSame($this->wrapper, $this->tplMod()->resolveTarget($this->inner, $change));
    }

    /**
     * Регрес: на коді до цієї задачі цикл while ($node = $node->parent()) доходив
     * до кореня, лишав $node = null, і наступна мутація давала фатал на живій сторінці.
     */
    public function testUnreachableClosestFindReturnsNullInsteadOfFatal()
    {
        $change = new TplChangeDTO('class="inner"', '');
        $change->setClosestFind('class="does-not-exist"');

        $this->assertNull($this->tplMod()->resolveTarget($this->inner, $change));
    }

    public function testParentOfRootReturnsNull()
    {
        $change = new TplChangeDTO('document', '');
        $change->setParent();

        $this->assertNull($this->tplMod()->resolveTarget($this->root, $change));
    }

    public function testChildrenFindReturnsMatchingDescendant()
    {
        $change = new TplChangeDTO('class="wrapper"', '');
        $change->setChildrenFind('class="inner"');

        $this->assertSame($this->inner, $this->tplMod()->resolveTarget($this->wrapper, $change));
    }

    public function testUnmatchedChildrenFindReturnsNull()
    {
        $change = new TplChangeDTO('class="wrapper"', '');
        $change->setChildrenFind('class="does-not-exist"');

        $this->assertNull($this->tplMod()->resolveTarget($this->wrapper, $change));
    }

    public function testUnmatchedChildrenLikeReturnsNull()
    {
        $change = new TplChangeDTO('class="wrapper"', '');
        $change->setChildrenLike('class="does-not-\w+-here"');

        $this->assertNull($this->tplMod()->resolveTarget($this->wrapper, $change));
    }
}
```

- [ ] **Step 2: Запустити й переконатися, що падає**

Run: `php vendor/bin/phpunit --filter TplModResolveTargetTest`
Expected: FAIL — `Call to undefined method Okay\Core\TplMod\TplMod::resolveTarget()`

- [ ] **Step 3: Реалізувати**

У `Okay/Core/TplMod/TplMod.php` замінити початок `applyMod()` (рядки з `// Вдруг запросили относительную ноду` до кінця блоку `childrenLike`) на виклик резолвера, а сам резолвер додати поруч:

```php
    private function applyMod(BaseNode $node, TplChangeDTO $changeDTO)
    {
        if (($node = $this->resolveTarget($node, $changeDTO)) === null) {
            return;
        }

        if (!empty($changeDTO->getAppend())) {
```

(решта тіла `applyMod()` лишається без змін)

```php
    /**
     * Вузол, який зрештою буде змінено: parent -> closest* -> children*.
     * null означає, що ланцюжок обірвався і вставляти немає куди.
     */
    public function resolveTarget(BaseNode $node, TplChangeDTO $change): ?BaseNode
    {
        if ($change->isParent()) {
            if (($node = $node->parent()) === null) {
                return null;
            }
        }

        if (!empty($change->getClosestFind())) {
            $find = $change->getClosestFind();
            $node = $this->closestNode($node, static fn(BaseNode $candidate): bool
                => strpos($candidate->getOriginalElement(), $find) !== false);
        } elseif (!empty($change->getClosestLike())) {
            $like = $change->getClosestLike();
            $node = $this->closestNode($node, static fn(BaseNode $candidate): bool
                => (bool)preg_match('~'.$like.'~', $candidate->getOriginalElement()));
        }

        if ($node === null) {
            return null;
        }

        if (!empty($change->getChildrenFind())) {
            return $this->findChildNode($node, $change->getChildrenFind()) ?: null;
        }

        if (!empty($change->getChildrenLike())) {
            return $this->likeChildNode($node, $change->getChildrenLike()) ?: null;
        }

        return $node;
    }

    private function closestNode(BaseNode $node, callable $matches): ?BaseNode
    {
        while ($node = $node->parent()) {
            if ($matches($node)) {
                return $node;
            }
        }

        return null;
    }
```

- [ ] **Step 4: Запустити тести**

Run: `php vendor/bin/phpunit --filter TplModResolveTargetTest`
Expected: PASS, 8 тестів

Run: `php vendor/bin/phpunit tests/`
Expected: PASS, весь набір зелений

- [ ] **Step 5: Комміт**

```bash
git add Okay/Core/TplMod/TplMod.php tests/TplMod/TplModResolveTargetTest.php
git commit -m "fix(TplMod): обірваний closestFind більше не валить сторінку

Цикл while (\$node = \$node->parent()) доходив до кореня, лишав \$node = null,
і наступна мутація давала Call to a member function append() on null. Те саме
для parent: true на кореневому вузлі. Тепер розвʼязання ланцюжка parent ->
closest* -> children* живе в resolveTarget() і повертає null, як це вже робив
childrenFind. Правка теми більше не валить вітрину; мовчазний пропуск видно
командою перевірки."
```

---

### Task 3: `ModificationChecker`

Чекер розв'язує шлях шаблона так само, як `Design::applyTplModifiers()` — суфіксом шляху, а не «лежить у html теми». Саме тому листи (`html/email/`), `backend/design/html/components/` і власні шаблони модулів працюють без окремих випадків.

**Files:**
- Create: `Okay/Core/TplMod/CheckStatus.php`
- Create: `Okay/Core/TplMod/DTO/CheckResultDTO.php`
- Create: `Okay/Core/TplMod/ModificationChecker.php`
- Modify: `Okay/Core/config/services.php` (біля блоку `TplMod::class`, рядок ~548)
- Create: `tests/TplMod/fixtures/theme/html/order.tpl`
- Create: `tests/TplMod/fixtures/theme/html/email/order_mail.tpl`
- Create: `tests/TplMod/fixtures/module/order.tpl`
- Create: `tests/TplMod/ModificationCheckerTest.php`

**Interfaces:**
- Consumes: `TplMod::findMatches()`, `TplMod::resolveTarget()` з Task 1–2; `Okay\Core\Modules\DTO\ModificationDTO`
- Produces:
  - `CheckStatus` — enum: `Ok`, `Multiple`, `NoAnchor`, `ChainBroken`, `FileMissing`; метод `isFailure(): bool`
  - `CheckResultDTO` — конструктор `(string $module, string $file, string $anchor, CheckStatus $status, array $matchedFiles, int $matchCount)`, геттери `getModule()`, `getFile()`, `getAnchor()`, `getStatus()`, `getMatchedFiles()`, `getMatchCount()`
  - `ModificationChecker::check(string $module, array $modifications, array $roots): array` — `CheckResultDTO[]`, по одному на кожен `change`
  - `ModificationChecker::frontRoots(string $rootDir, string $theme): array`
  - `ModificationChecker::backendRoots(string $rootDir): array`

- [ ] **Step 1: Написати падючий тест**

Фікстури.

`tests/TplMod/fixtures/theme/html/order.tpl`:

```smarty
<div class="order">
    {if $delivery}
        <span class="delivery">{$delivery->name|escape}</span>
    {/if}
    <i>{$purchase->variant->name|escape}</i>
</div>
```

`tests/TplMod/fixtures/theme/html/email/order_mail.tpl`:

```smarty
<table class="mail">
    <tr><td class="total">{$order->total_price}</td></tr>
</table>
```

`tests/TplMod/fixtures/module/order.tpl`:

```smarty
<div class="module-order">
    {if $delivery}
        <span class="module-delivery"></span>
    {/if}
</div>
```

`tests/TplMod/ModificationCheckerTest.php`:

```php
<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\Modules\DTO\ModificationDTO;
use Okay\Core\Modules\DTO\TplChangeDTO;
use Okay\Core\TplMod\CheckStatus;
use Okay\Core\TplMod\ModificationChecker;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;

class ModificationCheckerTest extends \PHPUnit\Framework\TestCase
{
    private function checker(): ModificationChecker
    {
        return new ModificationChecker(
            new TplMod(new Parser(), $this->createStub(Config::class)),
            new Parser()
        );
    }

    private function fixtures(string $subDir): string
    {
        return __DIR__ . '/fixtures/' . $subDir;
    }

    /** @return \Okay\Core\TplMod\DTO\CheckResultDTO */
    private function checkOne(ModificationDTO $modification, array $roots)
    {
        $results = $this->checker()->check('Vendor/Module', [$modification], $roots);
        $this->assertCount(1, $results);

        return $results[0];
    }

    public function testLiveAnchorIsOk()
    {
        $result = $this->checkOne(
            new ModificationDTO('order.tpl', [new TplChangeDTO('{if $delivery}', '')]),
            [$this->fixtures('theme')]
        );

        $this->assertSame(CheckStatus::Ok, $result->getStatus());
        $this->assertSame(1, $result->getMatchCount());
    }

    public function testDeadAnchorIsNoAnchor()
    {
        $result = $this->checkOne(
            new ModificationDTO('order.tpl', [new TplChangeDTO('<div class="data_processing_box_container', '')]),
            [$this->fixtures('theme')]
        );

        $this->assertSame(CheckStatus::NoAnchor, $result->getStatus());
        $this->assertTrue($result->getStatus()->isFailure());
    }

    /** Рядок є у файлі, але не у вузлі: анкер мертвий, хоч grep його й знаходить. */
    public function testAnchorSpanningTagsIsNoAnchor()
    {
        $result = $this->checkOne(
            new ModificationDTO('order.tpl', [new TplChangeDTO('<i>{$purchase->variant->name|escape}</i>', '')]),
            [$this->fixtures('theme')]
        );

        $this->assertSame(CheckStatus::NoAnchor, $result->getStatus());
    }

    public function testBrokenChainIsChainBroken()
    {
        $change = new TplChangeDTO('{if $delivery}', '');
        $change->setChildrenFind('class="does-not-exist"');

        $result = $this->checkOne(
            new ModificationDTO('order.tpl', [$change]),
            [$this->fixtures('theme')]
        );

        $this->assertSame(CheckStatus::ChainBroken, $result->getStatus());
    }

    public function testMissingFileIsFileMissing()
    {
        $result = $this->checkOne(
            new ModificationDTO('nowhere.tpl', [new TplChangeDTO('{if $delivery}', '')]),
            [$this->fixtures('theme')]
        );

        $this->assertSame(CheckStatus::FileMissing, $result->getStatus());
        $this->assertSame([], $result->getMatchedFiles());
    }

    /** Шаблон з тим самим базовим імʼям є і в темі, і в модулі - обидва кандидати. */
    public function testAnchorFoundInTwoFilesReportsBoth()
    {
        $result = $this->checkOne(
            new ModificationDTO('order.tpl', [new TplChangeDTO('{if $delivery}', '')]),
            [$this->fixtures('theme'), $this->fixtures('module')]
        );

        $this->assertSame(CheckStatus::Multiple, $result->getStatus());
        $this->assertCount(2, $result->getMatchedFiles());
        $this->assertFalse($result->getStatus()->isFailure());
    }

    /** Листи лежать у html/email/, а в module.json вказані без каталогу. */
    public function testAnchorInEmailSubdirectoryIsFound()
    {
        $result = $this->checkOne(
            new ModificationDTO('order_mail.tpl', [new TplChangeDTO('class="total"', '')]),
            [$this->fixtures('theme')]
        );

        $this->assertSame(CheckStatus::Ok, $result->getStatus());
    }

    public function testEveryChangeGetsItsOwnResult()
    {
        $results = $this->checker()->check(
            'Vendor/Module',
            [new ModificationDTO('order.tpl', [
                new TplChangeDTO('{if $delivery}', ''),
                new TplChangeDTO('class="does-not-exist"', ''),
            ])],
            [$this->fixtures('theme')]
        );

        $this->assertCount(2, $results);
        $this->assertSame(CheckStatus::Ok, $results[0]->getStatus());
        $this->assertSame(CheckStatus::NoAnchor, $results[1]->getStatus());
        $this->assertSame('Vendor/Module', $results[1]->getModule());
    }
}
```

- [ ] **Step 2: Запустити й переконатися, що падає**

Run: `php vendor/bin/phpunit --filter ModificationCheckerTest`
Expected: FAIL — `Class "Okay\Core\TplMod\CheckStatus" not found`

- [ ] **Step 3: Реалізувати**

`Okay/Core/TplMod/CheckStatus.php`:

```php
<?php


namespace Okay\Core\TplMod;


enum CheckStatus
{
    case Ok;
    case Multiple;
    case NoAnchor;
    case ChainBroken;
    case FileMissing;

    public function isFailure(): bool
    {
        return match ($this) {
            self::Ok, self::Multiple => false,
            default => true,
        };
    }
}
```

`Okay/Core/TplMod/DTO/CheckResultDTO.php`:

```php
<?php


namespace Okay\Core\TplMod\DTO;


use Okay\Core\TplMod\CheckStatus;

class CheckResultDTO
{
    /** @param string[] $matchedFiles */
    public function __construct(
        private string $module,
        private string $file,
        private string $anchor,
        private CheckStatus $status,
        private array $matchedFiles,
        private int $matchCount
    ) {
    }

    public function getModule(): string
    {
        return $this->module;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getAnchor(): string
    {
        return $this->anchor;
    }

    public function getStatus(): CheckStatus
    {
        return $this->status;
    }

    /** @return string[] */
    public function getMatchedFiles(): array
    {
        return $this->matchedFiles;
    }

    public function getMatchCount(): int
    {
        return $this->matchCount;
    }
}
```

`Okay/Core/TplMod/ModificationChecker.php`:

```php
<?php


namespace Okay\Core\TplMod;


use Okay\Core\Modules\DTO\ModificationDTO;
use Okay\Core\Modules\DTO\TplChangeDTO;
use Okay\Core\TplMod\DTO\CheckResultDTO;

/**
 * Рахує те, що робив би TplMod, не вставляючи нічого.
 *
 * Шлях шаблона розвʼязується так само, як у Design::applyTplModifiers(): значення
 * "file" з module.json порівнюється як суфікс шляху, тому листи (html/email/),
 * backend/design/html/components/ і власні шаблони модулів працюють без окремих випадків.
 */
class ModificationChecker
{
    private TplMod $tplMod;
    private Parser $parser;

    /** @var array<string, string[]> */
    private array $templatesByRoot = [];

    /** @var array<string, Nodes\BaseNode> */
    private array $parsed = [];

    public function __construct(TplMod $tplMod, Parser $parser)
    {
        $this->tplMod = $tplMod;
        $this->parser = $parser;
    }

    /** @return string[] */
    public static function frontRoots(string $rootDir, string $theme): array
    {
        $rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return array_merge(
            [$rootDir . 'design' . DIRECTORY_SEPARATOR . $theme],
            (array)glob($rootDir . 'Okay/Modules/*/*/design/html')
        );
    }

    /** @return string[] */
    public static function backendRoots(string $rootDir): array
    {
        $rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return array_merge(
            [$rootDir . 'backend' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'html'],
            (array)glob($rootDir . 'Okay/Modules/*/*/Backend/design/html')
        );
    }

    /**
     * @param ModificationDTO[] $modifications
     * @param string[] $roots
     * @return CheckResultDTO[]
     */
    public function check(string $module, array $modifications, array $roots): array
    {
        $results = [];
        foreach ($modifications as $modification) {
            $candidates = $this->candidates($modification->getFile(), $roots);
            foreach ($modification->getChanges() as $change) {
                $results[] = $this->checkChange($module, $modification->getFile(), $change, $candidates);
            }
        }

        return $results;
    }

    /** @param string[] $candidates */
    private function checkChange(string $module, string $file, TplChangeDTO $change, array $candidates): CheckResultDTO
    {
        $anchor = $change->getFind() !== '' ? $change->getFind() : $change->getLike();

        if ($candidates === []) {
            return new CheckResultDTO($module, $file, $anchor, CheckStatus::FileMissing, [], 0);
        }

        $matchedFiles = [];
        $matchCount = 0;
        $resolvedCount = 0;

        foreach ($candidates as $path) {
            $matches = $this->tplMod->findMatches($this->parse($path), $change);
            if ($matches === []) {
                continue;
            }

            $matchedFiles[] = $path;
            $matchCount += count($matches);
            foreach ($matches as $matchedNode) {
                if ($this->tplMod->resolveTarget($matchedNode, $change) !== null) {
                    $resolvedCount++;
                }
            }
        }

        if ($matchCount === 0) {
            $status = CheckStatus::NoAnchor;
        } elseif ($resolvedCount === 0) {
            $status = CheckStatus::ChainBroken;
        } elseif ($matchCount > 1) {
            $status = CheckStatus::Multiple;
        } else {
            $status = CheckStatus::Ok;
        }

        return new CheckResultDTO($module, $file, $anchor, $status, $matchedFiles, $matchCount);
    }

    /**
     * @param string[] $roots
     * @return string[]
     */
    private function candidates(string $file, array $roots): array
    {
        $suffix = DIRECTORY_SEPARATOR . ltrim($file, '/' . DIRECTORY_SEPARATOR);

        $candidates = [];
        foreach ($roots as $root) {
            foreach ($this->templates($root) as $path) {
                if (str_ends_with($path, $suffix)) {
                    $candidates[] = $path;
                }
            }
        }

        return $candidates;
    }

    /** @return string[] */
    private function templates(string $root): array
    {
        if (isset($this->templatesByRoot[$root])) {
            return $this->templatesByRoot[$root];
        }

        $templates = [];
        if (is_dir($root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if ($item->isFile() && $item->getExtension() === 'tpl') {
                    $templates[] = $item->getPathname();
                }
            }
            sort($templates);
        }

        return $this->templatesByRoot[$root] = $templates;
    }

    private function parse(string $path): Nodes\BaseNode
    {
        // findMatches()/resolveTarget() дерево не міняють, тож розбір кешується
        return $this->parsed[$path] ??= $this->parser->parse(file_get_contents($path));
    }
}
```

У `Okay/Core/config/services.php` одразу після блоку `TplMod::class` додати:

```php
    ModificationChecker::class => [
        'class' => ModificationChecker::class,
        'arguments' => [
            new SR(TplMod::class),
            new SR(TplParser::class),
        ],
    ],
```

і `use Okay\Core\TplMod\ModificationChecker;` до блоку `use` угорі файлу.

- [ ] **Step 4: Запустити тести**

Run: `php vendor/bin/phpunit --filter ModificationCheckerTest`
Expected: PASS, 8 тестів

Run: `php vendor/bin/phpunit tests/`
Expected: PASS

- [ ] **Step 5: Комміт**

```bash
git add Okay/Core/TplMod/CheckStatus.php Okay/Core/TplMod/DTO/CheckResultDTO.php \
        Okay/Core/TplMod/ModificationChecker.php Okay/Core/config/services.php \
        tests/TplMod/ModificationCheckerTest.php tests/TplMod/fixtures
git commit -m "feat(TplMod): чекер анкерів modifications

Прогін модифікацій через ті самі findMatches()/resolveTarget(), якими
користується рендер, із пʼятьма статусами замість тиші. Шлях шаблона
розвʼязується суфіксом, як у Design::applyTplModifiers(), тому листи
й власні шаблони модулів не дають фальшивих помилок."
```

---

### Task 4: гейт на теми в комплекті

Команді з Task 5 потрібні база й активна тема, тож у CI вона не запуститься. Тест обходиться без обох: модулі з файлової системи, теми — глобом.

**Files:**
- Create: `tests/TplMod/BundledModificationsTest.php`

**Interfaces:**
- Consumes: `ModificationChecker::check()`, `ModificationChecker::frontRoots()`, `ModificationChecker::backendRoots()`, `CheckStatus::isFailure()` з Task 3; `Okay\Core\Modules\Module::getModuleParams()`

- [ ] **Step 1: Написати тест**

```php
<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\Modules\LicenseModulesTemplates;
use Okay\Core\Modules\Module;
use Okay\Core\TplMod\ModificationChecker;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

/**
 * Анкери modifications модулів у комплекті мають збігатися в кожній темі, що йде
 * в поставці, і в шаблонах адмінки. Мертвий анкер не ламає сторінку й нічого не пише
 * в лог - модуль просто перестає щось вставляти, і побачити це можна лише очима.
 *
 * Найчастіший регрес форку саме тут: правка backend/design/html після освіження адмінки.
 */
class BundledModificationsTest extends \PHPUnit\Framework\TestCase
{
    #[DataProvider('frontDataProvider')]
    public function testFrontModificationsAnchorsAreAlive(string $vendor, string $moduleName, string $theme)
    {
        $params = $this->module()->getModuleParams($vendor, $moduleName);

        $this->assertAnchorsAlive(
            $vendor . '/' . $moduleName,
            $params->getFrontModifications(),
            ModificationChecker::frontRoots(self::rootDir(), $theme)
        );
    }

    #[DataProvider('backendDataProvider')]
    public function testBackendModificationsAnchorsAreAlive(string $vendor, string $moduleName)
    {
        $params = $this->module()->getModuleParams($vendor, $moduleName);

        $this->assertAnchorsAlive(
            $vendor . '/' . $moduleName,
            $params->getBackendModifications(),
            ModificationChecker::backendRoots(self::rootDir())
        );
    }

    public static function frontDataProvider(): array
    {
        $cases = [];
        foreach (self::bundledModules() as [$vendor, $moduleName]) {
            foreach (self::themes() as $theme) {
                $cases["$vendor/$moduleName @ $theme"] = [$vendor, $moduleName, $theme];
            }
        }

        return $cases;
    }

    public static function backendDataProvider(): array
    {
        $cases = [];
        foreach (self::bundledModules() as [$vendor, $moduleName]) {
            $cases["$vendor/$moduleName"] = [$vendor, $moduleName];
        }

        return $cases;
    }

    /** @return array<int, array{0: string, 1: string}> */
    private static function bundledModules(): array
    {
        $modules = [];
        foreach ((array)glob(self::rootDir() . '/Okay/Modules/*/*/Init/module.json') as $path) {
            $parts = explode(DIRECTORY_SEPARATOR, $path);
            $modules[] = [$parts[count($parts) - 4], $parts[count($parts) - 3]];
        }

        return $modules;
    }

    /** @return string[] */
    private static function themes(): array
    {
        $themes = [];
        foreach ((array)glob(self::rootDir() . '/design/*/html', GLOB_ONLYDIR) as $path) {
            $themes[] = basename(dirname($path));
        }

        return $themes;
    }

    private static function rootDir(): string
    {
        return dirname(__DIR__, 2);
    }

    private function module(): Module
    {
        return new Module(
            $this->createStub(LoggerInterface::class),
            $this->createStub(LicenseModulesTemplates::class)
        );
    }

    private function checker(): ModificationChecker
    {
        return new ModificationChecker(
            new TplMod(new Parser(), $this->createStub(Config::class)),
            new Parser()
        );
    }

    /** @param \Okay\Core\Modules\DTO\ModificationDTO[] $modifications */
    private function assertAnchorsAlive(string $module, array $modifications, array $roots): void
    {
        if ($modifications === []) {
            $this->assertTrue(true, 'модуль нічого не модифікує');

            return;
        }

        $failures = [];
        foreach ($this->checker()->check($module, $modifications, $roots) as $result) {
            if ($result->getStatus()->isFailure()) {
                $failures[] = sprintf(
                    '  %s: %s -> %s',
                    $result->getStatus()->name,
                    $result->getFile(),
                    $result->getAnchor()
                );
            }
        }

        $this->assertSame([], $failures, sprintf(
            "%s: анкери, які нічого не знайшли:\n%s",
            $module,
            implode(PHP_EOL, $failures)
        ));
    }
}
```

- [ ] **Step 2: Запустити**

Run: `php vendor/bin/phpunit --filter BundledModificationsTest`
Expected: PASS. Очікування — зелений старт: усі 8 анкерів чотирьох модулів (`DeliveryFields`, `NovaposhtaCost`, `FastOrder`, `RozetkaPay`) присутні в `okay_shop`, `vibe_shop` і `backend/design/html`.

Якщо якийсь анкер червоний — **не правити тест і не правити тему**. Занотувати модуль, файл, анкер і статус, зупинитися й показати результат: це справжня знахідка, і рішення по ній ухвалює власник.

- [ ] **Step 3: Довести, що тест справді щось ловить (мутація)**

Тимчасово зламати анкер у копії теми й переконатися, що тест червоніє:

```bash
cp design/okay_shop/html/order.tpl /tmp/order.tpl.bak
sed -i 's/{if $delivery}/{if $delivery_broken}/' design/okay_shop/html/order.tpl
php vendor/bin/phpunit --filter BundledModificationsTest
```

Expected: FAIL з `NO_ANCHOR: order.tpl -> {if $delivery}` для `OkayCMS/DeliveryFields` і `OkayCMS/NovaposhtaCost`.

Повернути:

```bash
cp /tmp/order.tpl.bak design/okay_shop/html/order.tpl
git diff --stat design/okay_shop/html/order.tpl
php vendor/bin/phpunit --filter BundledModificationsTest
```

Expected: `git diff --stat` порожній, тест зелений.

- [ ] **Step 4: Комміт**

```bash
git add tests/TplMod/BundledModificationsTest.php
git commit -m "test(TplMod): гейт на анкери modifications модулів у комплекті

Правка backend/design/html після освіження адмінки або порт теми мовчки
знімають вставки модулів. Тест звіряє анкери всіх модулів у комплекті
з обома темами й шаблонами адмінки; бази й контейнера не потребує.
Перевірено мутацією: зламаний {if \$delivery} робить його червоним."
```

---

### Task 5: команда `module:check-modifications`

**Files:**
- Create: `Okay/Core/Console/Commands/Module/ModuleCheckModificationsCommand.php`
- Modify: `Okay/Core/Console/Application.php:8-21`
- Modify: `docs/cli.md`
- Modify: `docs/tpl-modifications.md`

**Interfaces:**
- Consumes: `ModificationChecker` (`check()`, `frontRoots()`, `backendRoots()`), `CheckStatus`, `CheckResultDTO` з Task 3
- Produces: команда `module:check-modifications` з опціями `--all`, `--theme=`; exit 1 при будь-якому `isFailure()` або модулі, відсутньому в коді

- [ ] **Step 1: Написати команду**

`Okay/Core/Console/Commands/Module/ModuleCheckModificationsCommand.php`:

```php
<?php

namespace Okay\Core\Console\Commands\Module;

use Okay\Core\Console\Command;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Module;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Core\TplMod\CheckStatus;
use Okay\Core\TplMod\DTO\CheckResultDTO;
use Okay\Core\TplMod\ModificationChecker;
use Okay\Entities\ModulesEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'module:check-modifications',
    description: 'Checks that every modifications anchor declared in module.json still matches a template node.'
)]
class ModuleCheckModificationsCommand extends Command
{
    private const ANCHOR_EXCERPT_LENGTH = 60;

    protected function configure(): void
    {
        $this->setHelp(
            'A modifications anchor that no longer matches makes the module insert nothing, '
            . 'without an exception and without a log entry. This command reports such anchors.'
        );

        $this->addOption('all', null, InputOption::VALUE_NONE, 'Check disabled modules too.');
        $this->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme to check front modifications against.');
    }

    protected function handle(
        EntityFactory $entityFactory,
        Module $module,
        ModificationChecker $checker,
        FrontTemplateConfig $frontTemplateConfig
    ): int {
        $rootDir = dirname(__DIR__, 5);
        $theme = $this->input->getOption('theme') ?: $frontTemplateConfig->getTheme();

        if (!is_dir($rootDir . '/design/' . $theme)) {
            $this->output->writeln("<error>Theme '{$theme}' not found in design/</error>");

            return Command::FAILURE;
        }

        /** @var ModulesEntity $modulesEntity */
        $modulesEntity = $entityFactory->get(ModulesEntity::class);
        $filter = $this->input->getOption('all') ? [] : ['enabled' => 1];

        $results = [];
        $missingModules = [];
        foreach ($modulesEntity->find($filter) as $moduleRow) {
            $name = $moduleRow->vendor . '/' . $moduleRow->module_name;

            if ($module->moduleDirectoryNotExists($moduleRow->vendor, $moduleRow->module_name)) {
                $missingModules[] = $name;
                continue;
            }

            $params = $module->getModuleParams($moduleRow->vendor, $moduleRow->module_name);

            $results = array_merge(
                $results,
                $checker->check($name, $params->getFrontModifications(), ModificationChecker::frontRoots($rootDir, $theme)),
                $checker->check($name, $params->getBackendModifications(), ModificationChecker::backendRoots($rootDir))
            );
        }

        return $this->report($results, $missingModules, $theme);
    }

    /**
     * @param CheckResultDTO[] $results
     * @param string[] $missingModules
     */
    private function report(array $results, array $missingModules, string $theme): int
    {
        $verbose = $this->output->isVerbose();
        $failures = 0;

        $table = new Table($this->output);
        $table->setHeaders(['Module', 'File', 'Anchor', 'Status']);

        foreach ($results as $result) {
            $isFailure = $result->getStatus()->isFailure();
            if ($isFailure) {
                $failures++;
            } elseif (!$verbose && $result->getStatus() === CheckStatus::Ok) {
                continue;
            }

            $table->addRow([
                $result->getModule(),
                $result->getFile(),
                $this->excerpt($result->getAnchor()),
                $this->formatStatus($result),
            ]);
        }

        foreach ($missingModules as $name) {
            $table->addRow([$name, '-', '-', '<error>MODULE MISSING</error>']);
        }

        $table->render();

        $this->output->writeln(sprintf(
            'Theme: %s. Checked %d anchors, %d failed, %d modules enabled but absent from the code.',
            $theme,
            count($results),
            $failures,
            count($missingModules)
        ));

        if (!$verbose) {
            $this->output->writeln('<comment>Run with -v to see healthy anchors and matched files.</comment>');
        }

        return $failures === 0 && $missingModules === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function formatStatus(CheckResultDTO $result): string
    {
        return match ($result->getStatus()) {
            CheckStatus::Ok => '<info>OK</info>',
            CheckStatus::Multiple => sprintf(
                '<comment>MULTIPLE</comment> (%d nodes in %d files)',
                $result->getMatchCount(),
                count($result->getMatchedFiles())
            ),
            CheckStatus::NoAnchor => '<error>NO ANCHOR</error>',
            CheckStatus::ChainBroken => '<error>CHAIN BROKEN</error> (anchor found, closest/children did not)',
            CheckStatus::FileMissing => '<error>FILE MISSING</error>',
        };
    }

    private function excerpt(string $anchor): string
    {
        $anchor = trim(preg_replace('~\s+~', ' ', $anchor));

        return mb_strlen($anchor) > self::ANCHOR_EXCERPT_LENGTH
            ? mb_substr($anchor, 0, self::ANCHOR_EXCERPT_LENGTH - 1) . '…'
            : $anchor;
    }
}
```

У `Okay/Core/Console/Application.php` додати `use Okay\Core\Console\Commands\Module\ModuleCheckModificationsCommand;` і рядок `ModuleCheckModificationsCommand::class,` у масив `$commands` одразу після `ModuleCreateCommand::class,`.

- [ ] **Step 2: Підняти оточення й запустити команду**

```bash
cd dev && docker compose up -d && cd ..
dev/bin/smoke.sh
cd dev && docker compose exec php85 php ok module:check-modifications; echo "exit=$?"
```

Expected: таблиця й підсумковий рядок; на чистій базі очікується `0 failed`, `exit=0`. Якщо шлях до `./ok` у контейнері інший — знайти його `docker compose exec php85 ls`.

Перевірити опції:

```bash
cd dev && docker compose exec php85 php ok module:check-modifications --all -v; echo "exit=$?"
cd dev && docker compose exec php85 php ok module:check-modifications --theme=vibe_shop; echo "exit=$?"
cd dev && docker compose exec php85 php ok module:check-modifications --theme=nope; echo "exit=$?"
```

Expected: `--all -v` показує і здорові анкери; `--theme=vibe_shop` зелений; `--theme=nope` друкує `Theme 'nope' not found` і дає `exit=1`.

- [ ] **Step 3: Довести, що команда справді ловить мертвий анкер**

```bash
cp design/okay_shop/html/order.tpl /tmp/order.tpl.bak
sed -i 's/{if $delivery}/{if $delivery_broken}/' design/okay_shop/html/order.tpl
cd dev && docker compose exec php85 php ok module:check-modifications; echo "exit=$?"
```

Expected: рядки `NO ANCHOR` для `OkayCMS/DeliveryFields` і `OkayCMS/NovaposhtaCost`, `exit=1`.

```bash
cp /tmp/order.tpl.bak design/okay_shop/html/order.tpl
git diff --stat design/okay_shop/html/order.tpl
```

Expected: порожньо.

- [ ] **Step 4: Документація**

У `docs/cli.md` додати команду до переліку в тому ж форматі, що й сусідні, з текстом:

> `./ok module:check-modifications` — перевіряє, що кожен анкер `modifications` із `module.json`
> увімкнених модулів досі збігається з вузлом шаблона. Мертвий анкер не кидає винятку й нічого
> не пише в лог: модуль просто перестає щось вставляти. Опції: `--all` — разом із вимкненими
> модулями, `--theme=` — тема для фронтових модифікацій (типово активна), `-v` — показати
> здорові анкери й файли, у яких вони збіглися. Ненульовий код виходу — є мертвий анкер або
> модуль, увімкнений у базі, але відсутній у коді.

У `docs/tpl-modifications.md` додати наприкінці розділ:

> ## Як перевірити, що анкери живі
>
> `TplMod` шукає анкер серед **вузлів** розібраного шаблона, а не в тексті файлу. Тому
> `grep` тут оманливий: рядок `<i>{$var}</i>` у файлі є, а в жодному окремому вузлі його
> немає — парсер розкладає це на елемент `<i>` і текстовий вузол усередині.
>
> Якщо анкер не збігся, вставки просто не буде: без винятку, без запису в лог, без поломки
> сторінки. Перевіряти треба явно:
>
> ```bash
> ./ok module:check-modifications        # увімкнені модулі, активна тема
> ./ok module:check-modifications --all -v
> ```
>
> Модулі в комплекті додатково закриті тестом `tests/TplMod/BundledModificationsTest.php`:
> він звіряє їхні анкери з усіма темами поставки й шаблонами адмінки, бази не потребує.
>
> Перевірка каже лише, що місце для вставки знайшлось. Чи блок правильно виглядає, чи валідна
> вставлена розмітка і чи анкер збігся саме там, де задумано, — видно лише очима на сторінці.

- [ ] **Step 5: Прогнати весь набір і статичний аналіз**

Run: `php vendor/bin/phpunit tests/`
Expected: PASS

Run: `php vendor/bin/phpstan analyse`
Expected: без нових помилок (порівняти з `git stash`-прогоном, якщо база помилок непорожня)

Run: `php vendor/bin/phpcs Okay/Core/TplMod Okay/Core/Console/Commands/Module`
Expected: без нових зауважень

- [ ] **Step 6: Комміт**

```bash
git add Okay/Core/Console/Commands/Module/ModuleCheckModificationsCommand.php \
        Okay/Core/Console/Application.php docs/cli.md docs/tpl-modifications.md
git commit -m "feat(cli): команда module:check-modifications

Мертвий анкер modifications не кидає винятку й нічого не пише в лог -
модуль просто перестає щось вставляти. Команда рахує те саме, що робив би
TplMod, і розрізняє мертвий анкер, обірваний ланцюжок closest/children,
відсутній файл і збіг у кількох вузлах. Перевірено на живому оточенні:
зламаний {if \$delivery} дає NO ANCHOR і код виходу 1."
```

---

## Фінальна перевірка

- [ ] `php vendor/bin/phpunit tests/` — весь набір зелений, у переліку є `TplModMatchTest`, `TplModResolveTargetTest`, `ModificationCheckerTest`, `BundledModificationsTest`
- [ ] `dev/bin/smoke.sh` — оточення живе
- [ ] Вітрина й сторінка адмінки відкриваються очима: рефакторинг `applyMod()` торкається кожного шаблона, який модифікують модулі. Мінімум — `/`, `/cart`, `/order/<id>` в адмінці, `?controller=SettingsAdmin`
- [ ] `git status` чистий: жодної тимчасової правки в `design/` не лишилось
- [ ] PR у `main`, опис без згадок Claude/Anthropic

## Що лишається поза цією роботою

- Рантайм-сигналів немає: ні логу, ні винятку під час запиту. Рішення власника.
- `appendBefore`/`appendAfter` на вузлі без батька досі фаталять (`$parent->children()` на `null`) — окремий випадок, не чіпаємо.
- Квірк: власні бекендові шаблони модуля (`Okay/Modules/*/*/Backend/design/html/`) отримують **фронтові** модифікації, бо `Design::applyTplModifiers()` шукає в шляху `backend/design/html` із малої літери. Зафіксовано, не міняємо.
- Наступна задача черги — `mkdir()` без `recursive` у `Design.php:181`, беклог [2026-08-05-smarty-compile-dir.md](2026-08-05-smarty-compile-dir.md).
