<?php

namespace Core\Import;

use Okay\Core\Import;
use Okay\Core\Import\CsvNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Нормалізатор має сенс лише тоді, коли зіставлення колонок після нього
 * справді працює: Import::initColumns() читає файл із жорстко заданим ";"
 * і назву першої колонки бере як є, разом із BOM.
 */
class ImportColumnsAfterNormalizationTest extends TestCase
{
    private $path;

    private $backup;

    protected function setUp(): void
    {
        parent::setUp();

        $import = new Import();
        $this->path = dirname(__DIR__, 3) . '/' . $import->getImportFilesDir() . $import->getImportFile();

        // Робочий файл імпорту існує в дереві; повертаємо його як був.
        $this->backup = is_file($this->path) ? file_get_contents($this->path) : null;
    }

    protected function tearDown(): void
    {
        if ($this->backup !== null) {
            file_put_contents($this->path, $this->backup);
        } elseif (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function testExcelStyleFileMapsOntoInternalColumns()
    {
        file_put_contents(
            $this->path,
            CsvNormalizer::BOM . "Product,Price,SKU\nЧайник,250,A-1\n"
        );

        (new CsvNormalizer())->normalizeFile($this->path);

        $import = new Import();
        $import->initColumns();

        $this->assertSame(['Product', 'Price', 'SKU'], $import->getColumns());
    }

    /**
     * Без нормалізації той самий файл дає одну колонку з BOM у назві —
     * саме так зіставлення й ламалось.
     */
    public function testWithoutNormalizationTheHeaderIsUnusable()
    {
        file_put_contents(
            $this->path,
            CsvNormalizer::BOM . "Product,Price,SKU\nЧайник,250,A-1\n"
        );

        $import = new Import();
        $import->initColumns();

        $columns = $import->getColumns();

        $this->assertCount(1, $columns);
        $this->assertStringStartsWith(CsvNormalizer::BOM, $columns[0]);
    }

    public function testSemicolonFileStillWorks()
    {
        file_put_contents($this->path, "Product;Price\nЧайник;250\n");

        (new CsvNormalizer())->normalizeFile($this->path);

        $import = new Import();
        $import->initColumns();

        $this->assertSame(['Product', 'Price'], $import->getColumns());
    }
}
