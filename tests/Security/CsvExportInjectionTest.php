<?php

namespace Security;

use Okay\Core\Export\CsvWriter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * У backend-експорти потрапляють значення, які вводить покупець: ім'я,
 * коментар, адреса. Значення, що починається з =, +, - або @, Excel і
 * LibreOffice виконують як формулу при відкритті файла.
 */
class CsvExportInjectionTest extends TestCase
{
    #[DataProvider('formulaProvider')]
    public function testFormulaLikeValueIsNeutralised($value)
    {
        $escaped = CsvWriter::escapeValue($value);

        $this->assertSame("'" . $value, $escaped, $value);
    }

    public static function formulaProvider()
    {
        return [
            'dde'        => ['=cmd|\'/c calc\'!A1'],
            'формула'    => ['=1+1'],
            'плюс'       => ['+1-1'],
            'мінус'      => ['-2+3'],
            'at'         => ['@SUM(A1:A9)'],
            'табуляція'  => ["\t=1+1"],
            'CR'         => ["\r=1+1"],
        ];
    }

    #[DataProvider('benignProvider')]
    public function testOrdinaryValueIsUntouched($value)
    {
        $this->assertSame($value, CsvWriter::escapeValue($value));
    }

    public static function benignProvider()
    {
        return [
            'імʼя'      => ['Іван Петренко'],
            'порожнє'   => [''],
            'число'     => ['1250.00'],
            'телефон'   => ['380671234567'],
            'мейл'      => ['ivan@example.com'],
            'коментар'  => ['Ціна 5 < 10, беру'],
        ];
    }

    public function testNonStringValuesSurvive()
    {
        $this->assertSame('5', CsvWriter::escapeValue(5));
        $this->assertSame('', CsvWriter::escapeValue(null));
    }

    /**
     * Кирилиця має лишитись читабельною після повного циклу запис -> читання.
     * Раніше файл наприкінці перекодовувався у Windows-1251 і все поза цим
     * набором губилось.
     */
    public function testRoundTripKeepsUtf8AndSplitsColumns()
    {
        $file = tempnam(sys_get_temp_dir(), 'okay-csv');

        $handle = fopen($file, 'ab');
        CsvWriter::putHeader($handle, ['Ім’я', 'Коментар'], ';');
        CsvWriter::putRow($handle, ['Іван', '=cmd|\'/c calc\'!A1'], ';');
        CsvWriter::putRow($handle, ['Ґалаґан', 'ціна < 100 ₴'], ';');
        fclose($handle);

        $raw = file_get_contents($file);

        $this->assertStringStartsWith(CsvWriter::BOM, $raw);
        $this->assertTrue(mb_check_encoding($raw, 'UTF-8'));
        $this->assertStringContainsString('Ґалаґан', $raw);
        $this->assertStringContainsString('₴', $raw);
        $this->assertStringNotContainsString(';=cmd', $raw);

        $rows = [];
        $handle = fopen($file, 'rb');
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        unlink($file);

        $this->assertCount(3, $rows);
        $this->assertSame('Коментар', $rows[0][1]);
        $this->assertSame("'=cmd|'/c calc'!A1", $rows[1][1]);
        $this->assertSame('Ґалаґан', $rows[2][0]);
    }

    /**
     * BOM пишеться рівно один раз — файл збирається частинами між
     * AJAX-сторінками, і другий BOM усередині став би видимим сміттям.
     */
    public function testBomIsWrittenOnceForAppendedPages()
    {
        $file = tempnam(sys_get_temp_dir(), 'okay-csv');

        $handle = fopen($file, 'ab');
        CsvWriter::putHeader($handle, ['a'], ';');
        fclose($handle);

        $handle = fopen($file, 'ab');
        CsvWriter::putRow($handle, ['b'], ';');
        fclose($handle);

        $raw = file_get_contents($file);
        unlink($file);

        $this->assertSame(1, substr_count($raw, CsvWriter::BOM));
    }

    /**
     * Жодна точка експорту не має лишитись на старому перекодуванні.
     */
    public function testNoExportStillTranscodesToWindows1251()
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach (self::exportFiles() as $file) {
            $source = file_get_contents($root . '/' . $file);
            if (is_string($source) && stripos($source, 'Windows-1251') !== false) {
                $offenders[] = $file;
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * @return string[]
     */
    private static function exportFiles()
    {
        return [
            'backend/Helpers/BackendExportHelper.php',
            'backend/ajax/export_orders.php',
            'backend/ajax/export_users.php',
            'backend/ajax/export_stat.php',
            'backend/ajax/export_subscribes.php',
            'backend/Controllers/ReportStatsAdmin.php',
        ];
    }
}
