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

    public function testUnreachableClosestLikeReturnsNull()
    {
        $change = new TplChangeDTO('class="inner"', '');
        $change->setClosestLike('class="does-not-\w+-here"');

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
