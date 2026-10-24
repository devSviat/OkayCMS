<?php

namespace Core\Import;

use Okay\Core\Export\CsvWriter;
use Okay\Core\Import\CsvNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * З escape '\\' PHP читає слеш перед закривальною лапкою як екранування саме
 * лапки: значення, що закінчувалось на «\», зшивало свій рядок із наступним,
 * і файл розсипався з того місця до кінця — тихо, без помилки.
 */
class CsvTrailingBackslashTest extends TestCase
{
    private const ROWS = [
        ['Модель', 'Коментар'],
        ['A\\B', 'хвіст\\'],
        ['другий', 'рядок'],
    ];

    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'okay-csv');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testExportThenImportKeepsRows(): void
    {
        $f = fopen($this->file, 'wb');
        CsvWriter::putHeader($f, self::ROWS[0]);
        CsvWriter::putRow($f, self::ROWS[1]);
        CsvWriter::putRow($f, self::ROWS[2]);
        fclose($f);

        // Комою, щоб нормалізатор справді переписав файл, а не пропустив як є.
        file_put_contents($this->file, str_replace(';', ',', (string) file_get_contents($this->file)));
        (new CsvNormalizer())->normalizeFile($this->file);

        $f = fopen($this->file, 'rb');
        $read = [];
        while (($row = fgetcsv($f, 0, CsvNormalizer::TARGET_DELIMITER, '"', '')) !== false) {
            $read[] = $row;
        }
        fclose($f);
        $read[0][0] = (new CsvNormalizer())->stripBom($read[0][0]);

        self::assertSame(self::ROWS, $read);
    }

    /** Читання й запис мусять мати один режим: файл, записаний одним, інший читає інакше. */
    public function testNoCsvCallKeepsBackslashEscape(): void
    {
        $root = dirname(__DIR__, 3);
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/Okay'));
        foreach ([...iterator_to_array($files), new \SplFileInfo($root . '/backend/ajax/import.php')] as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach (file($file->getPathname()) as $n => $line) {
                if (preg_match('/\b(fputcsv|fgetcsv|str_getcsv)\(.*\'\\\\\\\\\'\)/', $line)) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ($n + 1);
                }
            }
        }

        self::assertSame([], $offenders);
    }
}
