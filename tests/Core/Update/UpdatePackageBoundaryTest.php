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
            'файл просто в Modules/' => ['Okay/Modules/Init.php'],
            'сам каталог модулів'    => ['Okay/Modules/'],
            'лог штатного модуля'    => ['Okay/Modules/OkayCMS/AutoDeploy/log/deploy.log'],
            'tmp штатного модуля'    => ['Okay/Modules/OkayCMS/AutoDeploy/tmp/build.zip'],
            'temp Integration1C'     => ['Okay/Modules/OkayCMS/Integration1C/temp/1c.xml'],
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
    #[DataProvider('updaterOwnAssetsProvider')]
    public function testUpdaterOwnAssetIsTheDocumentedException(string $path): void
    {
        UpdatePackage::assertPathsWithinCoreBoundary([$path => str_repeat('a', 64)]);

        $this->addToAssertionCount(1);
    }

    /**
     * Виняток — простір імен, а не поіменний перелік, і саме це тут
     * зафіксовано. Перевірку виконує код ІНСТАЛЯЦІЇ, тобто старий: якби
     * дозволявся лише сьогоднішній файл, реліз, якому знадобився другий,
     * відхилявся б цілком кожною вже встановленою інсталяцією.
     *
     * @return array<string, array{string}>
     */
    public static function updaterOwnAssetsProvider(): array
    {
        return [
            'сьогоднішній шаблон'   => ['backend/design/html/core_updater.tpl'],
            'розділений шаблон'     => ['backend/design/html/core_updater_steps.tpl'],
            'власний css сторінки'  => ['backend/design/css/core_updater.css'],
        ];
    }

    /** Простір імен вузький: решта теми адмінки лишається закритою. */
    #[DataProvider('adminThemeFilesProvider')]
    public function testOtherAdminThemeFilesStayDenied(string $path): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertPathsWithinCoreBoundary([$path => str_repeat('a', 64)]);
    }

    /** @return array<string, array{string}> */
    public static function adminThemeFilesProvider(): array
    {
        return [
            'головний шаблон' => ['backend/design/html/index.tpl'],
            'стилі адмінки'   => ['backend/design/css/okay.css'],
            'логотип'         => ['backend/design/images/logo_dark.svg'],
            'схоже ім\'я не в тому місці' => ['design/vibe_shop/core_updater.css'],
        ];
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

    /**
     * `./config/x` не починається з `config/`, а на диску вказує рівно туди:
     * copy() у `{root}/./config/x` перезаписує `{root}/config/x`. Перевірено
     * на справжніх файлах — обхід був реальний, не теоретичний.
     *
     * Закрито двома рубежами: assertSafePaths() відхиляє сегмент `.` як
     * некоректний шлях, а межа однаково нормалізує шлях сама — вона не має
     * залежати від того, що першу викликали.
     */
    #[DataProvider('dotSegmentBypassProvider')]
    public function testDotSegmentDoesNotBypassTheBoundary(string $path): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertPathsWithinCoreBoundary([$path => str_repeat('a', 64)]);
    }

    /** @return array<string, array{string}> */
    public static function dotSegmentBypassProvider(): array
    {
        return [
            'доступи до бази через ./'  => ['./config/config.local.php'],
            'чужий модуль через ./'     => ['./Okay/Modules/Sviat/Redis/Init/Init.php'],
            'тема через ./'             => ['./design/vibe_shop/css/theme.css'],
            'сегмент . усередині'       => ['config/./config.local.php'],
            'подвійний слеш'            => ['config//config.local.php'],
        ];
    }

    /** Той самий сегмент відхиляє й перевірка коректності шляху, окремо від межі. */
    public function testAssertSafePathsRejectsDotSegment(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertSafePaths(['./config/config.local.php' => str_repeat('a', 64)]);
    }

    /**
     * Win32 зрізає хвостові крапки й пробіли в іменах, тож "config." на
     * диску відкриється як "config". Той самий клас обходу, що й регістр —
     * і Windows у моделі загроз уже є, assertSafePaths() відхиляє "C:/".
     */
    #[DataProvider('win32FoldingProvider')]
    public function testWin32NameFoldingDoesNotBypassTheBoundary(string $path): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertPathsWithinCoreBoundary([$path => str_repeat('a', 64)]);
    }

    /** @return array<string, array{string}> */
    public static function win32FoldingProvider(): array
    {
        return [
            'хвостова крапка в каталозі' => ['config./config.local.php'],
            'хвостовий пробіл'           => ['config /config.local.php'],
            'крапка в імені файлу'       => ['.htaccess.'],
            'тема з крапкою'             => ['design./vibe_shop/theme.css'],
        ];
    }

    /** Той самий хвіст відхиляє й перевірка коректності шляху, окремо від межі. */
    public function testAssertSafePathsRejectsTrailingDotSegment(): void
    {
        $this->expectException(\RuntimeException::class);

        UpdatePackage::assertSafePaths(['config./config.local.php' => str_repeat('a', 64)]);
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
