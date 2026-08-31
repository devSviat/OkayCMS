<?php

namespace Entities;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Кожне поле сутності мусить існувати в схемі, з якою піднімається свіжа
 * інсталяція. Схема тут — дамп ПЛЮС core-міграції форку: дамп лишається
 * стоковим (він приїжджає з апстріму), а свої зміни схеми форк везе
 * окремими `.up.sql`.
 *
 * Колонка `rating` у ok_comments якийсь час не була ні там, ні там — лежала
 * файлом `1DB_changes`, який ніхто не виконував. Кожен свіжий стенд
 * народжувався зі зламаною адмінкою відгуків, і видно це не було: помилка
 * SQL ковтається, сторінка віддає 200 з порожнім списком.
 *
 * Тільки `Okay/Entities/` — таблиці модулів створює їхній `install()`, у
 * чистому дампі їх немає й бути не мусить.
 */
class SchemaMatchesEntitiesTest extends TestCase
{
    /**
     * Таблиці, яких у схемі немає за побудовою.
     *
     * ok_core_migrations створює сам CoreMigrator::ensureTable() при першому
     * застосуванні: мігратор мусить працювати й на базі, розгорнутій до появи
     * самооновлення.
     */
    private const TABLES_CREATED_AT_RUNTIME = [
        'ok_core_migrations',
    ];

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, list<string>> таблиця => її колонки */
    private static function schemaTables(): array
    {
        static $tables = null;
        if ($tables !== null) {
            return $tables;
        }

        $dump = file_get_contents(self::repoRoot() . '/1DB_changes/okay_clean.sql');
        preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\n\)/s', $dump, $matches, PREG_SET_ORDER);

        $tables = [];
        foreach ($matches as $match) {
            preg_match_all('/^\s+`([^`]+)`\s+[a-z]/mi', $match[2], $columns);
            $tables[$match[1]] = $columns[1];
        }

        foreach (self::forkMigrationColumns() as $table => $columns) {
            $tables[$table] = array_merge($tables[$table] ?? [], $columns);
        }

        return $tables;
    }

    /**
     * Колонки, які додають core-міграції форку. Маркер `__` — префікс
     * таблиць, що підставляється під час застосування (CoreMigrator).
     *
     * @return array<string, list<string>>
     */
    private static function forkMigrationColumns(): array
    {
        $added = [];

        foreach (glob(self::repoRoot() . '/1DB_changes/fork/*.up.sql') ?: [] as $path) {
            preg_match_all(
                '/ALTER\s+TABLE\s+`__(\w+)`\s+ADD\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?`(\w+)`/i',
                (string) file_get_contents($path),
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $added['ok_' . $match[1]][] = $match[2];
            }
        }

        return $added;
    }

    /** @return array<string, array{string}> */
    public static function entityClasses(): array
    {
        $cases = [];
        foreach (glob(self::repoRoot() . '/Okay/Entities/*.php') as $file) {
            $class = 'Okay\\Entities\\' . basename($file, '.php');
            $cases[basename($file, '.php')] = [$class];
        }

        return $cases;
    }

    private static function staticProperty(ReflectionClass $class, string $name): mixed
    {
        return $class->hasProperty($name) ? $class->getProperty($name)->getValue() : null;
    }

    #[DataProvider('entityClasses')]
    public function testEveryDeclaredFieldExistsInTheSchema(string $className): void
    {
        $class = new ReflectionClass($className);
        if ($class->isAbstract()) {
            $this->markTestSkipped("{$className} — абстрактна");
        }

        $table = self::staticProperty($class, 'table');
        if (!is_string($table)) {
            $this->markTestSkipped("{$className} не оголошує таблиці");
        }

        $tableName = 'ok_' . ltrim($table, '_');
        if (in_array($tableName, self::TABLES_CREATED_AT_RUNTIME, true)) {
            $this->markTestSkipped("{$tableName} створюється в рантаймі");
        }

        $tables = self::schemaTables();
        $this->assertArrayHasKey($tableName, $tables, "{$className}: таблиці {$tableName} немає в схемі");

        foreach ((array) self::staticProperty($class, 'fields') as $field) {
            // Поля з крапкою або аліасом — вирази join'а (`g.discount`,
            // `g.name as group_name`), власною колонкою таблиці вони не є.
            if (preg_match('/[. ]/', $field)) {
                continue;
            }

            $this->assertContains(
                $field,
                $tables[$tableName],
                "{$className}: поля `{$field}` немає ні в дампі, ні в core-міграціях форку"
            );
        }
    }

    #[DataProvider('entityClasses')]
    public function testEveryDeclaredLangFieldExistsInTheSchema(string $className): void
    {
        $class = new ReflectionClass($className);
        if ($class->isAbstract()) {
            $this->markTestSkipped("{$className} — абстрактна");
        }

        $langTable = self::staticProperty($class, 'langTable');
        $langFields = (array) self::staticProperty($class, 'langFields');
        if (!is_string($langTable) || $langTable === '' || $langFields === []) {
            $this->markTestSkipped("{$className} не має мовних полів");
        }

        $tableName = 'ok_lang_' . ltrim($langTable, '_');
        $tables = self::schemaTables();
        $this->assertArrayHasKey($tableName, $tables, "{$className}: таблиці {$tableName} немає в схемі");

        foreach ($langFields as $field) {
            if (preg_match('/[. ]/', $field)) {
                continue;
            }

            $this->assertContains(
                $field,
                $tables[$tableName],
                "{$className}: мовного поля `{$field}` немає ні в дампі, ні в core-міграціях форку"
            );
        }
    }
}
