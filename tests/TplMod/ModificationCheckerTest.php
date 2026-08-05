<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\Modules\DTO\ModificationDTO;
use Okay\Core\Modules\DTO\TplChangeDTO;
use Okay\Core\TplMod\CheckStatus;
use Okay\Core\TplMod\DTO\CheckResultDTO;
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

    private function checkOne(ModificationDTO $modification, array $roots): CheckResultDTO
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
