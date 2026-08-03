<?php

namespace Core\Import;

use Okay\Core\Import\CsvNormalizer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Імпорт читав файл із жорстко заданим роздільником ";" і не знімав BOM.
 * Експорт з Excel дає і те, й інше: невидимий BOM приклеювався до назви
 * першої колонки, і зіставлення колонок мовчки ламалось.
 */
class CsvNormalizerTest extends TestCase
{
    private $file;

    private $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'okay-import');
        $this->normalizer = new CsvNormalizer();
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    public function testBomIsStrippedFromTheFirstColumnName()
    {
        $this->write(CsvNormalizer::BOM . "Product;Price\nЧайник;250\n");

        $this->normalizer->normalizeFile($this->file);

        $this->assertSame(['Product', 'Price'], $this->firstRow());
        $this->assertStringNotContainsString(CsvNormalizer::BOM, file_get_contents($this->file));
    }

    #[DataProvider('delimiterProvider')]
    public function testDelimiterIsDetectedAndNormalised($delimiter)
    {
        $this->write("Product{$delimiter}Price{$delimiter}SKU\nЧайник{$delimiter}250{$delimiter}A-1\n");

        $this->normalizer->normalizeFile($this->file);

        $this->assertSame(['Product', 'Price', 'SKU'], $this->firstRow());
        $this->assertSame(['Чайник', '250', 'A-1'], $this->rowAt(1));
    }

    public static function delimiterProvider()
    {
        return [
            'крапка з комою' => [';'],
            'кома'           => [','],
            'табуляція'      => ["\t"],
            'вертикальна'    => ['|'],
        ];
    }

    public function testExcelSepHintIsHonouredAndDropped()
    {
        $this->write("sep=,\nProduct,Price\nЧайник,250\n");

        $this->normalizer->normalizeFile($this->file);

        $this->assertSame(['Product', 'Price'], $this->firstRow());
        $this->assertSame(['Чайник', '250'], $this->rowAt(1));
        $this->assertStringNotContainsString('sep=', file_get_contents($this->file));
    }

    /**
     * Значення з комою всередині лапок не має розбивати рядок на колонки —
     * саме на цьому ламається наївний explode.
     */
    public function testQuotedValuesSurviveTheRewrite()
    {
        $this->write("Product,Description\nЧайник,\"білий, 1.5 л\"\n");

        $this->normalizer->normalizeFile($this->file);

        $this->assertSame(['Чайник', 'білий, 1.5 л'], $this->rowAt(1));
    }

    public function testAlreadyNormalFileIsLeftAlone()
    {
        $content = "Product;Price\nЧайник;250\n";
        $this->write($content);

        $this->normalizer->normalizeFile($this->file);

        $this->assertSame($content, file_get_contents($this->file));
    }

    public function testCyrillicHeadersSurvive()
    {
        $this->write(CsvNormalizer::BOM . "Назва,Ціна\nҐалаґан,10\n");

        $this->normalizer->normalizeFile($this->file);

        $this->assertSame(['Назва', 'Ціна'], $this->firstRow());
        $this->assertSame(['Ґалаґан', '10'], $this->rowAt(1));
    }

    public function testUnreadableFileIsReported()
    {
        $this->assertFalse($this->normalizer->normalizeFile($this->file . '-нема'));
    }

    public function testEmptyFileIsReported()
    {
        $this->write('');

        $this->assertFalse($this->normalizer->normalizeFile($this->file));
    }

    private function write($content)
    {
        file_put_contents($this->file, $content);
    }

    /**
     * @return string[]
     */
    private function firstRow()
    {
        return $this->rowAt(0);
    }

    /**
     * @return string[]
     */
    private function rowAt($index)
    {
        $rows = [];
        $handle = fopen($this->file, 'rb');
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows[$index];
    }
}
