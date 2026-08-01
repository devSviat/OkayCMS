<?php

namespace Core\Modules;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Installer;
use Okay\Core\Modules\DTO\ModuleParamsDTO;
use Okay\Core\Modules\Module;
use Okay\Core\Modules\VersionControl;
use Okay\Entities\ModulesEntity;
use PHPUnit\Framework\TestCase;

/**
 * `ok_modules.version` може бути NULL - модуль стоїть, а версія не записана.
 * Це давало два симптоми на сторінці модулів: "Deprecated: version_compare():
 * Passing null" у списку і кнопку оновлення, яка мовчки нічого не робила.
 *
 * convertDeprecationsToExceptions у phpunit.xml робить ці тести чутливими:
 * повернення null у version_compare() або explode() завалить їх само собою.
 */
class ModuleVersionNullTest extends TestCase
{
    public function testVersionCompareSurvivesNull(): void
    {
        $versionControl = new VersionControl();

        $this->assertSame(-1, $versionControl->versionCompare(null, '1.2.0'));
        $this->assertTrue($versionControl->lessThan(null, '1.2.0'));
        $this->assertFalse($versionControl->greaterThan(null, '1.2.0'));
    }

    public function testMathVersionSurvivesNull(): void
    {
        $module = $this->moduleMock();

        $this->assertSame(0, $module->getMathVersion(null));
        $this->assertSame(0, $module->getMathVersion(''));
        $this->assertSame(101102100, $module->getMathVersion('1.2.0'));
    }

    /**
     * Головне: з невідомою встановленою версією кнопка оновлення має хоч щось
     * зробити - записати версію з маніфеста, - але не запускати міграції, бо
     * мігрувати нема від чого.
     */
    public function testUnknownInstalledVersionRecordsManifestVersionWithoutMigrating(): void
    {
        $installer = $this->installerFor((object)[
            'id' => 12,
            'vendor' => 'OkayCMS',
            'module_name' => 'NovaposhtaCost',
            'version' => null,
        ], $updated);

        $installer->update(12);

        $this->assertSame([12, ['version' => '1.2.0']], $updated, 'версію треба записати з module.json');
        $this->assertSame([], $installer->calledUpdateMethods, 'міграції на невідомій версії запускати не можна');
    }

    public function testKnownOlderVersionStillRunsItsUpdateMethods(): void
    {
        $installer = $this->installerFor((object)[
            'id' => 12,
            'vendor' => 'OkayCMS',
            'module_name' => 'NovaposhtaCost',
            'version' => '1.0.0',
        ], $updated);

        $installer->update(12);

        $this->assertSame([12, ['version' => '1.2.0']], $updated);
        $this->assertSame(['update_1_1_0', 'update_1_2_0'], $installer->calledUpdateMethods);
    }

    private function installerFor(object $module, &$updated): SpyInstaller
    {
        $updated = null;

        $modulesEntity = $this->createMock(ModulesEntity::class);
        $modulesEntity->method('findOne')->willReturn($module);
        $modulesEntity->method('update')->willReturnCallback(
            function ($id, $object) use (&$updated) {
                $updated = [$id, $object];
            }
        );

        $entityFactory = $this->createMock(EntityFactory::class);
        $entityFactory->method('get')->willReturn($modulesEntity);

        $params = $this->createMock(ModuleParamsDTO::class);
        $params->method('getVersion')->willReturn('1.2.0');
        $params->method('getMathVersion')->willReturn(101102100);

        $moduleCore = $this->getMockBuilder(Module::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getModuleParams', 'getInitClassName'])
            ->getMock();
        $moduleCore->method('getModuleParams')->willReturn($params);
        $moduleCore->method('getInitClassName')->willReturn(StubVersionedInit::class);

        return new SpyInstaller($entityFactory, $moduleCore);
    }

    /**
     * @return Module&\PHPUnit\Framework\MockObject\MockObject
     */
    private function moduleMock()
    {
        return $this->getMockBuilder(Module::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}

/**
 * Підміняє лише створення Init-обʼєкта, щоб міграції не чіпали БД.
 */
class SpyInstaller extends Installer
{
    public array $calledUpdateMethods = [];

    protected function getInitObject($init, $moduleId, $vendorName, $moduleName)
    {
        return new class ($this->calledUpdateMethods) {
            private array $log;

            public function __construct(array &$log)
            {
                $this->log = &$log;
            }

            public function __call($name, $arguments)
            {
                $this->log[] = $name;
            }
        };
    }
}

/**
 * Джерело update-методів для рефлексії: справжній Init тягне за собою половину ядра.
 */
class StubVersionedInit
{
    public function update_1_1_0(): void
    {
    }

    public function update_1_2_0(): void
    {
    }
}
