<?php

namespace Security;

use PHPUnit\Framework\TestCase;

/**
 * Ця ітерація не змінює схему БД. Тест тримає цю властивість:
 * будь-яка нова міграція чи DDL у новому коді валить збірку.
 */
class NoDatabaseChangeTest extends TestCase
{
    public function testNoMigrationFileWasAdded()
    {
        $root = dirname(__DIR__, 2);

        $baseline = file($root . '/dev/schema-baseline.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($baseline);

        $current = scandir($root . '/1DB_changes');
        $current = array_values(array_diff($current, ['.', '..']));
        sort($current);
        sort($baseline);

        $this->assertSame($baseline, $current, 'A file was added to or removed from 1DB_changes/');
    }

    /**
     * @dataProvider ddlKeywordProvider
     */
    public function testSecurityCodeContainsNoDdl($keyword)
    {
        $root = dirname(__DIR__, 2);
        $offenders = [];

        foreach ($this->phpFiles($root . '/Okay/Core/Security') as $file) {
            $source = file_get_contents($file);
            if ($source !== false && stripos($source, $keyword) !== false) {
                $offenders[] = str_replace($root . '/', '', $file);
            }
        }

        $this->assertSame([], $offenders, $keyword . ' found in Okay/Core/Security');
    }

    public function ddlKeywordProvider()
    {
        return [
            'alter table'  => ['ALTER TABLE'],
            'create table' => ['CREATE TABLE'],
            'drop table'   => ['DROP TABLE'],
            'add column'   => ['ADD COLUMN'],
        ];
    }

    private function phpFiles($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
