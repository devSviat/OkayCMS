<?php

namespace Core\Release;

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
}
