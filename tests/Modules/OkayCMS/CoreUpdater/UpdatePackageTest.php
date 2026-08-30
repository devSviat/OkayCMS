<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdatePackage;
use PHPUnit\Framework\TestCase;

class UpdatePackageTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/update-package';

    // --- parseChecksums ---

    public function testParseChecksumsReadsHashAndNamePairs(): void
    {
        $checksums = UpdatePackage::parseChecksums(file_get_contents(self::FIXTURES . '/checksums.txt'));

        $this->assertSame([
            'archive.bin' => '2a8691efa720e1903c5f4e17982c1d36ad66bdc82e264886a531809af7e417c4',
            'version.json' => '3acf69111239821414b4ba058dcfff8befcea695f31b0dd64effc88fba5b4a98',
        ], $checksums);
    }

    public function testParseChecksumsIgnoresBlankLines(): void
    {
        $checksums = UpdatePackage::parseChecksums("\n" . file_get_contents(self::FIXTURES . '/checksums.txt') . "\n");

        $this->assertCount(2, $checksums);
    }

    public function testParseChecksumsThrowsOnMalformedLine(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::parseChecksums("not-a-valid-checksum-line\n");
    }

    // --- verifyArchiveHash ---

    public function testVerifyArchiveHashPassesWhenHashMatches(): void
    {
        $checksums = UpdatePackage::parseChecksums(file_get_contents(self::FIXTURES . '/checksums.txt'));

        UpdatePackage::verifyArchiveHash(self::FIXTURES . '/archive.bin', $checksums);
        $this->addToAssertionCount(1);
    }

    public function testVerifyArchiveHashThrowsWithExpectedAndActualOnMismatch(): void
    {
        $checksums = UpdatePackage::parseChecksums(file_get_contents(self::FIXTURES . '/checksums-wrong-hash.txt'));

        try {
            UpdatePackage::verifyArchiveHash(self::FIXTURES . '/archive.bin', $checksums);
            $this->fail('Очікувався RuntimeException при розбіжності хешу архіву.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('0000000000000000000000000000000000000000000000000000000000000000', $e->getMessage());
            $this->assertStringContainsString('2a8691efa720e1903c5f4e17982c1d36ad66bdc82e264886a531809af7e417c4', $e->getMessage());
        }
    }

    public function testVerifyArchiveHashThrowsWhenChecksumsHasNoEntryForFile(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::verifyArchiveHash(self::FIXTURES . '/archive.bin', ['other-file' => 'abc']);
    }

    // --- verifyExtractedFiles ---

    public function testVerifyExtractedFilesReturnsVerifiedListOnHappyPath(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest.json'), true)['files'];

        $verified = UpdatePackage::verifyExtractedFiles(self::FIXTURES . '/payload', $manifest);

        $this->assertSame(
            ['Okay/Core/Foo.php', 'Okay/Helpers/Bar.php', 'composer.json'],
            $verified
        );
    }

    public function testVerifyExtractedFilesIgnoresFileInTreeButNotInManifest(): void
    {
        // untracked-extra.php лежить у payload/, але не заявлений у manifest.json.
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest.json'), true)['files'];

        $verified = UpdatePackage::verifyExtractedFiles(self::FIXTURES . '/payload', $manifest);

        $this->assertNotContains('untracked-extra.php', $verified);
    }

    public function testVerifyExtractedFilesThrowsOnWrongHash(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest-wrong-hash.json'), true)['files'];

        $this->expectException(\RuntimeException::class);

        UpdatePackage::verifyExtractedFiles(self::FIXTURES . '/payload', $manifest);
    }

    public function testVerifyExtractedFilesThrowsOnMissingFile(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest-missing-file.json'), true)['files'];

        $this->expectException(\RuntimeException::class);

        UpdatePackage::verifyExtractedFiles(self::FIXTURES . '/payload', $manifest);
    }

    /**
     * Порожній manifest самоузгоджено "проходить" будь-яку перевірку
     * checksums і мовчки застосував би нуль файлів — тут це явний фейл,
     * а не порожній список без роботи.
     */
    public function testVerifyExtractedFilesThrowsOnEmptyManifest(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::verifyExtractedFiles(self::FIXTURES . '/payload', []);
    }

    public function testVerifyExtractedFilesThrowsOnNonStringHashInManifest(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest-non-string-hash.json'), true)['files'];

        $this->expectException(\RuntimeException::class);

        UpdatePackage::verifyExtractedFiles(self::FIXTURES . '/payload', $manifest);
    }

    /**
     * "Ніяких часткових ок" — якщо третій файл зі списку битий, перші два
     * не мають повернутись як частково перевірений результат.
     */
    public function testVerifyExtractedFilesDoesNotReturnPartialResultsOnFailure(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest-wrong-hash.json'), true)['files'];

        try {
            UpdatePackage::verifyExtractedFiles(self::FIXTURES . '/payload', $manifest);
            $this->fail('Очікувався RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertTrue(true);
        }
    }

    // --- assertSafePaths ---

    public function testAssertSafePathsPassesOnHappyPathManifest(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest.json'), true)['files'];

        UpdatePackage::assertSafePaths($manifest);
        $this->addToAssertionCount(1);
    }

    public function testAssertSafePathsThrowsOnDotDotPath(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest-dotdot-path.json'), true)['files'];

        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertSafePaths($manifest);
    }

    public function testAssertSafePathsThrowsOnAbsolutePath(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest-absolute-path.json'), true)['files'];

        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertSafePaths($manifest);
    }

    public function testAssertSafePathsThrowsOnBackslashPath(): void
    {
        $manifest = json_decode(file_get_contents(self::FIXTURES . '/manifest-backslash-path.json'), true)['files'];

        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertSafePaths($manifest);
    }

    /**
     * Свідомий вибір: assertSafePaths() перевіряє шляхи, яких немає —
     * "пакет без файлів" ловить verifyExtractedFiles(), не цей метод.
     */
    public function testAssertSafePathsPassesOnEmptyManifest(): void
    {
        UpdatePackage::assertSafePaths([]);
        $this->addToAssertionCount(1);
    }

    // --- readVersionMeta ---

    public function testReadVersionMetaReturnsTypedFields(): void
    {
        $meta = UpdatePackage::readVersionMeta(self::FIXTURES);

        $this->assertSame([
            'forkVersion' => '1.2.0',
            'upstreamBase' => '4.6.0',
            'minPhp' => '8.4.0',
            'requiresMigrations' => false,
            'releasedAt' => '2026-02-01T00:00:00Z',
        ], $meta);
    }

    public function testReadVersionMetaThrowsWhenFileMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::readVersionMeta(self::FIXTURES . '/payload');
    }

    public function testReadVersionMetaThrowsOnMalformedJson(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::readVersionMeta(self::FIXTURES . '/version-variants/broken-json');
    }

    public function testReadVersionMetaThrowsWhenJsonIsNotAnObject(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::readVersionMeta(self::FIXTURES . '/version-variants/not-object');
    }

    public function testReadVersionMetaThrowsWhenForkVersionIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::readVersionMeta(self::FIXTURES . '/version-variants/no-fork-version');
    }

    public function testReadVersionMetaThrowsWhenForkVersionIsEmpty(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::readVersionMeta(self::FIXTURES . '/version-variants/empty-fork-version');
    }

    // --- assertInstallable ---

    public function testAssertInstallablePassesWhenPackageIsNewerAndPhpSatisfiesMinPhp(): void
    {
        $meta = UpdatePackage::readVersionMeta(self::FIXTURES);

        UpdatePackage::assertInstallable($meta, '1.1.0', '8.5.0');
        $this->addToAssertionCount(1);
    }

    public function testAssertInstallableThrowsWhenPhpIsBelowMinPhp(): void
    {
        $meta = json_decode(file_get_contents(self::FIXTURES . '/version-minphp-too-high.json'), true);

        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertInstallable($meta, '1.1.0', '8.5.0');
    }

    public function testAssertInstallableThrowsWhenPackageVersionEqualsInstalled(): void
    {
        $meta = UpdatePackage::readVersionMeta(self::FIXTURES);

        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertInstallable($meta, '1.2.0', '8.5.0');
    }

    public function testAssertInstallableThrowsWhenPackageVersionIsLowerThanInstalled(): void
    {
        $meta = json_decode(file_get_contents(self::FIXTURES . '/version-lower.json'), true);

        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertInstallable($meta, '1.2.0', '8.5.0');
    }
}
