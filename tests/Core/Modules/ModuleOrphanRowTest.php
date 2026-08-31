<?php

namespace Core\Modules;

use Okay\Core\Modules\LicenseModulesTemplates;
use Okay\Core\Modules\Module;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Модуль записаний у `ok_modules`, а каталогу на диску немає — стан, у який
 * потрапляє інсталяція, коли модуль прибрали руками або він переїхав у ядро.
 * Сенс `moduleDirectoryNotExists()` — сказати «пропусти цей модуль», але виклик
 * прибраного в Monolog 2.0 `addWarning()` кидав Error і клав УСЮ адмінку
 * (`Modules::startModules()` → `backend/index.php`). Фронт при цьому лишався
 * живим, тож симптом виглядав як «адмінка раптом померла».
 */
class ModuleOrphanRowTest extends TestCase
{
    public function testOrphanModuleRowIsReportedWithoutFatal(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('installed but not exists'));

        $module = new Module($logger, $this->createStub(LicenseModulesTemplates::class));

        // trigger_error(E_USER_WARNING) усередині — PHPUnit підвищує його до
        // винятку, тому глушимо саме його, а не перевірку логера нижче.
        $notExists = @$module->moduleDirectoryNotExists('NoSuchVendor', 'NoSuchModule');

        $this->assertTrue($notExists);
    }

    public function testExistingModuleDirectoryIsNotReported(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $module = new Module($logger, $this->createStub(LicenseModulesTemplates::class));

        // Каталог, який точно існує в репозиторії.
        $this->assertFalse($module->moduleDirectoryNotExists('OkayCMS', 'Feeds'));
    }
}
