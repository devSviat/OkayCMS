<?php

namespace Modules\OkayCMS\CoreUpdater;

use Okay\Modules\OkayCMS\CoreUpdater\Helpers\MaintenanceMode;
use PHPUnit\Framework\TestCase;

class MaintenanceModeTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/maintenance-mode-test-' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $flag = $this->tmpDir . '/config/.maintenance';
        if (is_file($flag)) {
            unlink($flag);
        }
        if (is_dir($this->tmpDir . '/config')) {
            rmdir($this->tmpDir . '/config');
        }
        rmdir($this->tmpDir);
    }

    public function testFlagPathJoinsRootAndConfigDotMaintenance(): void
    {
        $this->assertSame(
            $this->tmpDir . '/config/.maintenance',
            MaintenanceMode::flagPath($this->tmpDir)
        );
    }

    /**
     * index.php не може покластися на автозавантаження класу перед
     * check-ом самого прапорця (гейт стоїть до DI), тож дублює цей шлях
     * літералом 'config/.maintenance'. Цей тест — контракт, який ловить
     * розходження, якщо суфікс колись зміниться в одному місці й не в
     * другому.
     */
    public function testFlagPathContractStaysConfigDotMaintenance(): void
    {
        $this->assertSame('/app/config/.maintenance', MaintenanceMode::flagPath('/app'));
    }

    public function testNormalizeTokenPassesThroughStrings(): void
    {
        $this->assertSame('abc', MaintenanceMode::normalizeToken('abc'));
    }

    public function testNormalizeTokenPassesThroughNull(): void
    {
        $this->assertNull(MaintenanceMode::normalizeToken(null));
    }

    /**
     * PHP розбирає ?core_updater_token[]=x у масив — normalizeToken()
     * мусить звести це до null, а не дати TypeError долетіти до
     * allowsRequest(), яка навмисно лишається строго ?string.
     */
    public function testNormalizeTokenTreatsArrayAsAbsentTokenInsteadOfFatal(): void
    {
        $this->assertNull(MaintenanceMode::normalizeToken(['x']));
    }

    public function testEnableWritesJsonWithStartedAtAndTokenAndReturnsToken(): void
    {
        mkdir($this->tmpDir . '/config');
        $flagPath = $this->tmpDir . '/config/.maintenance';

        $before = time();
        $token = MaintenanceMode::enable($flagPath);
        $after = time();

        $this->assertNotSame('', $token);
        $this->assertTrue(is_file($flagPath));

        $decoded = json_decode(file_get_contents($flagPath), true);
        $this->assertSame($token, $decoded['token']);
        $this->assertGreaterThanOrEqual($before, $decoded['startedAt']);
        $this->assertLessThanOrEqual($after, $decoded['startedAt']);
    }

    public function testDisableRemovesFlagFile(): void
    {
        mkdir($this->tmpDir . '/config');
        $flagPath = $this->tmpDir . '/config/.maintenance';
        MaintenanceMode::enable($flagPath);

        MaintenanceMode::disable($flagPath);

        $this->assertFalse(is_file($flagPath));
    }

    public function testDisableOnMissingFlagIsNotAnError(): void
    {
        $flagPath = $this->tmpDir . '/config/.maintenance';

        MaintenanceMode::disable($flagPath);

        $this->assertFalse(is_file($flagPath));
    }

    public function testIsActiveReflectsFlagPresence(): void
    {
        mkdir($this->tmpDir . '/config');
        $flagPath = $this->tmpDir . '/config/.maintenance';

        $this->assertFalse(MaintenanceMode::isActive($flagPath));

        MaintenanceMode::enable($flagPath);

        $this->assertTrue(MaintenanceMode::isActive($flagPath));
    }

    public function testAllowsRequestIsTrueWhenNoFlagPresent(): void
    {
        $flagPath = $this->tmpDir . '/config/.maintenance';

        $this->assertTrue(MaintenanceMode::allowsRequest($flagPath, null));
        $this->assertTrue(MaintenanceMode::allowsRequest($flagPath, 'anything'));
    }

    public function testAllowsRequestIsTrueWhenProvidedTokenMatches(): void
    {
        mkdir($this->tmpDir . '/config');
        $flagPath = $this->tmpDir . '/config/.maintenance';
        $token = MaintenanceMode::enable($flagPath);

        $this->assertTrue(MaintenanceMode::allowsRequest($flagPath, $token));
    }

    public function testAllowsRequestIsFalseWhenProvidedTokenDoesNotMatch(): void
    {
        mkdir($this->tmpDir . '/config');
        $flagPath = $this->tmpDir . '/config/.maintenance';
        MaintenanceMode::enable($flagPath);

        $this->assertFalse(MaintenanceMode::allowsRequest($flagPath, 'wrong-token'));
    }

    public function testAllowsRequestIsFalseWhenNoTokenProvidedWhileActive(): void
    {
        mkdir($this->tmpDir . '/config');
        $flagPath = $this->tmpDir . '/config/.maintenance';
        MaintenanceMode::enable($flagPath);

        $this->assertFalse(MaintenanceMode::allowsRequest($flagPath, null));
    }

    public function testAllowsRequestFailsClosedOnBrokenJsonEvenWithATokenGuess(): void
    {
        mkdir($this->tmpDir . '/config');
        $flagPath = $this->tmpDir . '/config/.maintenance';
        file_put_contents($flagPath, '{not valid json');

        $this->assertFalse(MaintenanceMode::allowsRequest($flagPath, null));
        $this->assertFalse(MaintenanceMode::allowsRequest($flagPath, 'anything'));
    }

    public function testRenderPageContains503CompatibleTextAndNoPhpErrors(): void
    {
        $html = MaintenanceMode::renderPage();

        $this->assertIsString($html);
        $this->assertStringNotContainsString('<?php', $html);
        $this->assertStringNotContainsString('Notice:', $html);
        $this->assertStringNotContainsString('Warning:', $html);
        $this->assertStringContainsString('503', $html);
    }
}
