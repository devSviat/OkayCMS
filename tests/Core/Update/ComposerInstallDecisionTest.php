<?php

namespace Core\Update;

use Okay\Core\Update\ComposerInstallDecision;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * composer install після оновлення потрібен лише тоді, коли змінилось те, що
 * визначає прод-дерево vendor/ і автозавантажувач. Раніше composer.lock
 * порівнювався цілим файлом, і бамп dev-інструмента вимагав composer від
 * кожної інсталяції.
 */
class ComposerInstallDecisionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/composer-decision-' . uniqid('', true);
        mkdir($this->dir . '/root', 0777, true);
        mkdir($this->dir . '/payload', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private const LOCK = [
        '_readme' => ['This file locks the dependencies'],
        'content-hash' => 'aaa',
        'packages' => [['name' => 'aura/sql', 'version' => '5.0.2']],
        'packages-dev' => [['name' => 'phpunit/phpunit', 'version' => '13.0.0']],
        'aliases' => [],
        'minimum-stability' => 'stable',
        'platform' => ['php' => '^8.4'],
    ];

    private const JSON = [
        'require' => ['php' => '^8.4', 'aura/sql' => '^5.0'],
        'require-dev' => ['phpunit/phpunit' => '^13.0'],
        'autoload' => ['psr-4' => ['Okay\\' => 'Okay/']],
        'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests/']],
    ];

    /** @return array<string, array{0: array, 1: array, 2: bool}> */
    public static function changeProvider(): array
    {
        $lock = self::LOCK;
        $json = self::JSON;

        $devBump = $lock;
        $devBump['packages-dev'][0]['version'] = '13.1.0';
        $devBump['content-hash'] = 'bbb';

        $prodBump = $lock;
        $prodBump['packages'][0]['version'] = '5.0.3';

        $platform = $lock;
        $platform['platform']['php'] = '^8.5';

        $reordered = array_reverse($lock, true);

        $devJson = $json;
        $devJson['require-dev']['phpstan/phpstan'] = '^2.0';
        $devJson['autoload-dev']['psr-4']['Fixtures\\'] = 'tests/fixtures/';

        $rootAutoload = $json;
        $rootAutoload['autoload']['files'] = ['Okay/helpers.php'];

        return [
            'нічого не змінилось'                  => [$lock, $json, false],
            'лише packages-dev і content-hash'     => [$devBump, $json, false],
            'лише require-dev і autoload-dev'      => [$lock, $devJson, false],
            'інший порядок ключів'                 => [$reordered, $json, false],
            'версія прод-пакета'                   => [$prodBump, $json, true],
            'platform'                             => [$platform, $json, true],
            // У lock така зміна видна лише через content-hash, який відкидається.
            'autoload кореня в composer.json'      => [$lock, $rootAutoload, true],
        ];
    }

    #[DataProvider('changeProvider')]
    public function testInstallIsRequiredOnlyForProductionChanges(array $packageLock, array $packageJson, bool $expected): void
    {
        $this->write('root/composer.lock', self::LOCK);
        $this->write('root/composer.json', self::JSON);
        $this->write('payload/composer.lock', $packageLock);
        $this->write('payload/composer.json', $packageJson);

        self::assertSame($expected, ComposerInstallDecision::isRequired($this->dir . '/root', $this->dir . '/payload'));
    }

    public function testPackageWithoutLockNeedsNoInstall(): void
    {
        $this->write('root/composer.lock', self::LOCK);

        self::assertFalse(ComposerInstallDecision::isRequired($this->dir . '/root', $this->dir . '/payload'));
    }

    public function testMissingCurrentLockNeedsInstall(): void
    {
        $this->write('payload/composer.lock', self::LOCK);

        self::assertTrue(ComposerInstallDecision::isRequired($this->dir . '/root', $this->dir . '/payload'));
    }

    /** Без розбору — та сама відповідь, що й побайтове порівняння. */
    public function testUnparsableLockFallsBackToByteComparison(): void
    {
        file_put_contents($this->dir . '/root/composer.lock', '{broken');
        file_put_contents($this->dir . '/payload/composer.lock', '{broken!');
        self::assertTrue(ComposerInstallDecision::isRequired($this->dir . '/root', $this->dir . '/payload'));

        file_put_contents($this->dir . '/payload/composer.lock', '{broken');
        self::assertFalse(ComposerInstallDecision::isRequired($this->dir . '/root', $this->dir . '/payload'));
    }

    private function write(string $path, array $data): void
    {
        file_put_contents($this->dir . '/' . $path, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function vendorWith(string $installedJson): string
    {
        $vendor = $this->dir . '/vendor-' . uniqid();
        mkdir($vendor . '/composer', 0777, true);
        file_put_contents($vendor . '/composer/installed.json', $installedJson);

        return $vendor;
    }

    private function lock(string $json): string
    {
        $path = $this->dir . '/lock-' . uniqid() . '.json';
        file_put_contents($path, $json);

        return $path;
    }

    public function testVendorInSyncHasNothingMissing(): void
    {
        $lock = $this->lock('{"packages": [{"name": "a/b", "version": "1.0.0", "dist": {"reference": "abc"}}]}');
        $vendor = $this->vendorWith('{"packages": [{"name": "A/B", "version": "1.0.0", "dist": {"reference": "abc"}}]}');

        self::assertSame([], ComposerInstallDecision::missingFromVendor($lock, $vendor));
    }

    public function testMissingOtherVersionAndOtherCommitAreReported(): void
    {
        $lock = $this->lock('{"packages": ['
            . '{"name": "a/new", "version": "1.0.0"},'
            . '{"name": "a/bumped", "version": "2.0.0"},'
            . '{"name": "a/branch", "version": "dev-main", "source": {"reference": "new"}},'
            . '{"name": "a/same", "version": "1.0.0"}]}');
        $vendor = $this->vendorWith('{"packages": ['
            . '{"name": "a/bumped", "version": "1.9.0"},'
            . '{"name": "a/branch", "version": "dev-main", "source": {"reference": "old"}},'
            . '{"name": "a/same", "version": "1.0.0"}]}');

        self::assertSame(['a/new 1.0.0', 'a/bumped 2.0.0', 'a/branch dev-main'], ComposerInstallDecision::missingFromVendor($lock, $vendor));
    }

    /** Формат composer 1 — сам список пакетів, без обгортки. */
    public function testComposerOneInstalledFormatIsRead(): void
    {
        $lock = $this->lock('{"packages": [{"name": "a/b", "version": "1.0.0"}]}');

        self::assertSame([], ComposerInstallDecision::missingFromVendor($lock, $this->vendorWith('[{"name": "a/b", "version": "1.0.0"}]')));
    }

    public function testWithoutVendorEverythingIsMissing(): void
    {
        $lock = $this->lock('{"packages": [{"name": "a/b", "version": "1.0.0"}]}');

        self::assertSame(['a/b 1.0.0'], ComposerInstallDecision::missingFromVendor($lock, $this->dir . '/no-vendor'));
    }
}
