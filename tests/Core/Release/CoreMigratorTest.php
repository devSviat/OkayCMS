<?php

namespace Core\Release;

use Okay\Core\Release\CoreMigrationException;
use Okay\Core\Release\CoreMigrator;
use PHPUnit\Framework\TestCase;

class CoreMigratorTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures/migrations';
    }

    public function testPendingReturnsUnappliedSqlFilesSorted(): void
    {
        $migrator = new CoreMigrator();

        $pending = $migrator->pending($this->fixturesDir, []);

        $this->assertSame(
            ['1.1.0_add_rating.up.sql', '1.2.0_add_index.up.sql'],
            array_column($pending, 'name')
        );
        $this->assertFileExists($pending[0]['path']);
    }

    public function testPendingSkipsAlreadyAppliedNames(): void
    {
        $migrator = new CoreMigrator();

        $pending = $migrator->pending($this->fixturesDir, ['1.1.0_add_rating.up.sql']);

        $this->assertSame(['1.2.0_add_index.up.sql'], array_column($pending, 'name'));
    }

    public function testPendingIgnoresNonUpSqlFiles(): void
    {
        $migrator = new CoreMigrator();

        $names = array_column($migrator->pending($this->fixturesDir, []), 'name');

        $this->assertNotContains('README.txt', $names);
    }

    public function testPendingOnMissingDirectoryIsEmpty(): void
    {
        $migrator = new CoreMigrator();

        $this->assertSame([], $migrator->pending($this->fixturesDir . '/does-not-exist', []));
    }

    public function testSplitSqlFileSeparatesStatementsAndSkipsComments(): void
    {
        $migrator = new CoreMigrator();

        $statements = $migrator->splitSqlFile($this->fixturesDir . '/1.1.0_add_rating.up.sql');

        $this->assertCount(2, $statements);
        $this->assertStringStartsWith('CREATE TABLE', trim($statements[0]));
        $this->assertStringStartsWith('ALTER TABLE', trim($statements[1]));
        $this->assertStringNotContainsString('--', $statements[0]);
    }

    public function testApplyWithoutDbDependenciesThrowsLogicException(): void
    {
        $this->expectException(\LogicException::class);

        (new CoreMigrator())->apply(__DIR__);
    }

    public function testPendingSortsNaturallyNotLexically(): void
    {
        $dir = sys_get_temp_dir() . '/core-migrator-natsort-' . uniqid();
        mkdir($dir);
        touch($dir . '/1.2.0_add_index.up.sql');
        touch($dir . '/1.11.0_add_column.up.sql');

        try {
            $migrator = new CoreMigrator();

            // strcmp сортує "1.11.0_*" перед "1.2.0_*" (посимвольно '1' < '2');
            // натуральне порівняння читає "11" і "2" як числа.
            $this->assertSame(
                ['1.2.0_add_index.up.sql', '1.11.0_add_column.up.sql'],
                array_column($migrator->pending($dir, []), 'name')
            );
        } finally {
            unlink($dir . '/1.2.0_add_index.up.sql');
            unlink($dir . '/1.11.0_add_column.up.sql');
            rmdir($dir);
        }
    }

    public function testPrefixTablesRewritesDoubleUnderscoreMarker(): void
    {
        $migrator = new CoreMigrator();

        $sql = $migrator->prefixTables(
            'SELECT * FROM `__test_table` WHERE id = 1',
            'ok_'
        );

        $this->assertSame('SELECT * FROM `ok_test_table` WHERE id = 1', $sql);
    }

    public function testPrefixTablesLeavesQuotedDoubleUnderscoreAlone(): void
    {
        $migrator = new CoreMigrator();

        $sql = $migrator->prefixTables("SELECT '__not_a_table'", 'ok_');

        $this->assertSame("SELECT '__not_a_table'", $sql);
    }

    public function testCoreMigrationExceptionCarriesAppliedNames(): void
    {
        $exception = new CoreMigrationException('впала', ['1.1.0_a.up.sql'], null);

        $this->assertSame(['1.1.0_a.up.sql'], $exception->appliedNames);
        $this->assertSame('впала', $exception->getMessage());
    }

    /**
     * Маркер `__` у лапках префікса не отримує — так задумано (тест вище),
     * інакше підстановка калічила б рядкові літерали. Тому міграція, яка
     * питає `INFORMATION_SCHEMA` про `'__comments'`, шукає таблицю з таким
     * буквальним іменем: перевірка проходить, не знаходить нічого і мовчки
     * вирішує не те. Пастка з тих, що вилазять уже на чужій базі.
     */
    public function testShippedMigrationsDoNotQuoteTheTableMarker(): void
    {
        $migrationsDir = dirname(__DIR__, 3) . '/1DB_changes/fork';
        if (!is_dir($migrationsDir)) {
            $this->markTestSkipped('жодної core-міграції ще немає');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($migrationsDir, \FilesystemIterator::SKIP_DOTS)
        );

        $checked = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.up.sql')) {
                continue;
            }

            $checked++;
            $this->assertDoesNotMatchRegularExpression(
                '/[\'"]__[a-z_]+/i',
                file_get_contents($file->getPathname()),
                $file->getFilename() . ': маркер `__` у лапках не отримає префікса'
            );
        }

        $this->assertGreaterThan(0, $checked, 'жодного .up.sql не перевірено');
    }
}
