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

    /**
     * Коли задані обидва ключі, like перевіряється й тоді, коли find не збігся:
     * в оригінальному walkByFile() це був elseif, тобто АБО, а не пріоритет find.
     */
    public function testLikeIsCheckedWhenFindIsSetButDoesNotMatch()
    {
        $tree = (new Parser())->parse('<div class="foo"></div>');

        $matches = $this->tplMod()->findMatches($tree, new TplChangeDTO('class="does-not-exist"', 'class="fo+"'));

        $this->assertCount(1, $matches);
    }

    public function testEmptyChangeMatchesNothing()
    {
        $tree = (new Parser())->parse('<div class="foo"></div>');

        $this->assertSame([], $this->tplMod()->findMatches($tree, new TplChangeDTO('', '')));
    }
}
