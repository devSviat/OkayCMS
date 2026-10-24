<?php

namespace Core\Modules;

use Okay\Core\Modules\EntityField;
use Okay\Core\Modules\SqlPresentor;
use PHPUnit\Framework\TestCase;

/**
 * Видалення модуля прибирає лише рядок з ok_modules, а таблиці лишає. Тож
 * install() при перевстановці створює вже наявні таблиці, і голий CREATE TABLE
 * давав помилку 1050 у лозі на кожну.
 */
class CreateTableIfNotExistsTest extends TestCase
{
    public function testTableIsCreatedOnlyIfMissing(): void
    {
        $sql = new SqlPresentor()->createTableQuery('__sviat__probe', [
            new EntityField('id')->setTypeInt(11, false)->setAutoIncrement(),
            new EntityField('name')->setTypeVarchar(255),
        ]);

        self::assertStringStartsWith('CREATE TABLE IF NOT EXISTS __sviat__probe (', $sql);
    }
}
