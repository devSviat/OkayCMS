<?php


namespace TplMod;


use Okay\Core\Config;
use Okay\Core\Modules\DTO\ModificationDTO;
use Okay\Core\Modules\LicenseModulesTemplates;
use Okay\Core\Modules\Module;
use Okay\Core\TplMod\ModificationChecker;
use Okay\Core\TplMod\Parser;
use Okay\Core\TplMod\TplMod;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

/**
 * Анкери modifications модулів у комплекті мають збігатися в кожній темі, що йде
 * в поставці, і в шаблонах адмінки. Мертвий анкер не ламає сторінку й нічого не пише
 * в лог - модуль просто перестає щось вставляти, і побачити це можна лише очима.
 *
 * Найчастіший регрес форку саме тут: правка backend/design/html після освіження адмінки.
 */
class BundledModificationsTest extends \PHPUnit\Framework\TestCase
{
    #[DataProvider('frontDataProvider')]
    public function testFrontModificationsAnchorsAreAlive(string $vendor, string $moduleName, string $theme)
    {
        $params = $this->module()->getModuleParams($vendor, $moduleName);

        $this->assertAnchorsAlive(
            $vendor . '/' . $moduleName,
            $params->getFrontModifications(),
            ModificationChecker::frontRoots(self::rootDir(), $theme)
        );
    }

    #[DataProvider('backendDataProvider')]
    public function testBackendModificationsAnchorsAreAlive(string $vendor, string $moduleName)
    {
        $params = $this->module()->getModuleParams($vendor, $moduleName);

        $this->assertAnchorsAlive(
            $vendor . '/' . $moduleName,
            $params->getBackendModifications(),
            ModificationChecker::backendRoots(self::rootDir())
        );
    }

    public static function frontDataProvider(): array
    {
        $cases = [];
        foreach (self::bundledModules() as [$vendor, $moduleName]) {
            foreach (self::themes() as $theme) {
                $cases["$vendor/$moduleName @ $theme"] = [$vendor, $moduleName, $theme];
            }
        }

        return $cases;
    }

    public static function backendDataProvider(): array
    {
        $cases = [];
        foreach (self::bundledModules() as [$vendor, $moduleName]) {
            $cases["$vendor/$moduleName"] = [$vendor, $moduleName];
        }

        return $cases;
    }

    /** @return array<int, array{0: string, 1: string}> */
    private static function bundledModules(): array
    {
        $modules = [];
        foreach ((array)glob(self::rootDir() . '/Okay/Modules/*/*/Init/module.json') as $path) {
            $parts = explode(DIRECTORY_SEPARATOR, $path);
            $modules[] = [$parts[count($parts) - 4], $parts[count($parts) - 3]];
        }

        return $modules;
    }

    /** @return string[] */
    private static function themes(): array
    {
        $themes = [];
        foreach ((array)glob(self::rootDir() . '/design/*/html', GLOB_ONLYDIR) as $path) {
            $themes[] = basename(dirname($path));
        }

        return $themes;
    }

    private static function rootDir(): string
    {
        return dirname(__DIR__, 2);
    }

    private function module(): Module
    {
        return new Module(
            $this->createStub(LoggerInterface::class),
            $this->createStub(LicenseModulesTemplates::class)
        );
    }

    private function checker(): ModificationChecker
    {
        return new ModificationChecker(
            new TplMod(new Parser(), $this->createStub(Config::class)),
            new Parser()
        );
    }

    /**
     * @param ModificationDTO[] $modifications
     * @param string[] $roots
     */
    private function assertAnchorsAlive(string $module, array $modifications, array $roots): void
    {
        if ($modifications === []) {
            $this->assertTrue(true, 'модуль нічого не модифікує');

            return;
        }

        $failures = [];
        foreach ($this->checker()->check($module, $modifications, $roots) as $result) {
            if ($result->isFailure()) {
                $failures[] = sprintf(
                    '  %s: %s -> %s',
                    $result->getStatus()->name,
                    $result->getFile(),
                    $result->getAnchor()
                );
            }
        }

        $this->assertSame([], $failures, sprintf(
            "%s: анкери, які нічого не знайшли:\n%s",
            $module,
            implode(PHP_EOL, $failures)
        ));
    }
}
