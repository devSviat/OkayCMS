<?php

namespace Core\Update;

use Okay\Core\Release\ReleaseManifest;
use Okay\Core\Update\UpdatePackage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Межа «ядро проти магазину» на боці застосування.
 *
 * До цієї перевірки її тримав лише `release-manifest.json` — тобто збірка
 * релізу, чужий для інсталяції репозиторій. Пакет, який заявив `design/` чи
 * `config/config.local.php`, проходив усі перевірки цілісності (він же
 * справжній) і мовчки перезаписував тему магазину або доступи до бази.
 *
 * @see UpdatePackage::assertPathsWithinCoreBoundary()
 */
class UpdatePackageBoundaryTest extends TestCase
{
    /**
     * Головний контроль усієї перевірки: реальний перелік файлів, який
     * PackageBuilder збирає з `release-manifest.json`, мусить проходити межу
     * цілком. Якщо ця пара розійдеться, релізи почнуть падати на кроці
     * `verify` в усіх інсталяцій одночасно — а помітити це треба тут.
     */
    public function testRealReleaseManifestPassesTheBoundary(): void
    {
        $root = dirname(__DIR__, 3);
        $files = (new ReleaseManifest($root . '/release-manifest.json'))->resolveFiles($root);

        $this->assertNotEmpty($files, 'маніфест не дав жодного файлу — перевірка втратила предмет');

        UpdatePackage::assertPathsWithinCoreBoundary(array_fill_keys($files, str_repeat('0', 64)));

        $this->addToAssertionCount(1);
    }

    #[DataProvider('pathsOutsideCoreProvider')]
    public function testPathOutsideCoreIsRejected(string $path): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($path);

        UpdatePackage::assertPathsWithinCoreBoundary([$path => str_repeat('a', 64)]);
    }

    /** @return array<string, array{string}> */
    public static function pathsOutsideCoreProvider(): array
    {
        return [
            'доступи до бази'      => ['config/config.local.php'],
            'завантаження'         => ['files/originals/products/photo.jpg'],
            'тема вітрини'         => ['design/vibe_shop/css/theme.css'],
            'тема адмінки'         => ['backend/design/css/okay.css'],
            'дані імпорту'         => ['backend/files/import/import.csv'],
            'кеш'                  => ['cache/css/bundle.css'],
            'скомпільовані шаблони'=> ['compiled/okay_shop/index.tpl.php'],
            'залежності composer'  => ['vendor/autoload.php'],
            'лог застосунку'       => ['Okay/log/app-2026-08-31.log'],
            'периметр apache'      => ['.htaccess'],
            'правила індексації'   => ['robots.txt'],
            'сторонній модуль'     => ['Okay/Modules/Sviat/Redis/Init/Init.php'],
            'модуль без вендора'   => ['Okay/Modules/Init.php'],
        ];
    }

    #[DataProvider('coreOwnedPathsProvider')]
    public function testCoreOwnedPathIsAllowed(string $path): void
    {
        UpdatePackage::assertPathsWithinCoreBoundary([$path => str_repeat('a', 64)]);

        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function coreOwnedPathsProvider(): array
    {
        return [
            'ядро'                => ['Okay/Core/Update/UpdateRunner.php'],
            'контролер вітрини'   => ['Okay/Controllers/ProductController.php'],
            'контролер адмінки'   => ['backend/Controllers/IndexAdmin.php'],
            'мови адмінки'        => ['backend/lang/ua.php'],
            'штатний модуль'      => ['Okay/Modules/OkayCMS/Feeds/Init/Init.php'],
            'точка входу'         => ['index.php'],
            'консоль'             => ['ok'],
            'core-міграції'       => ['1DB_changes/fork/1.3.2_comments_rating.up.sql'],
            // Каталог, якого ядро ще не має: deny-list не заважає йому рости.
            'майбутній каталог'   => ['Okay/Something/New.php'],
        ];
    }

    /**
     * Сторінка оновлювача лежить під `backend/design/`, тобто в забороненому
     * каталозі, і виняток для неї свідомий: без нього правку в самій сторінці
     * оновлення не доставити ніколи.
     */
    public function testUpdaterOwnTemplateIsTheDocumentedException(): void
    {
        UpdatePackage::assertPathsWithinCoreBoundary([
            'backend/design/html/core_updater.tpl' => str_repeat('a', 64),
        ]);

        $this->addToAssertionCount(1);
    }

    /**
     * На FS, що не розрізняє регістр, `CONFIG/` вказує на той самий каталог,
     * що й `config/`. Перевірка порівнює в нижньому регістрі саме тому.
     */
    public function testCaseVariantOfADeniedPrefixIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertPathsWithinCoreBoundary([
            'CONFIG/config.local.php' => str_repeat('a', 64),
        ]);
    }

    /** Повідомлення несе ВСІ порушення: інакше кожен прогін показував би одне наступне. */
    public function testAllViolationsAreReportedAtOnce(): void
    {
        try {
            UpdatePackage::assertPathsWithinCoreBoundary([
                'config/config.local.php'   => str_repeat('a', 64),
                'Okay/Core/Config.php'      => str_repeat('b', 64),
                'design/vibe_shop/head.tpl' => str_repeat('c', 64),
            ]);
            $this->fail('очікувався RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('config/config.local.php', $e->getMessage());
            $this->assertStringContainsString('design/vibe_shop/head.tpl', $e->getMessage());
            $this->assertStringNotContainsString('Okay/Core/Config.php', $e->getMessage());
        }
    }

    public function testEmptyManifestPasses(): void
    {
        UpdatePackage::assertPathsWithinCoreBoundary([]);

        $this->addToAssertionCount(1);
    }
}
