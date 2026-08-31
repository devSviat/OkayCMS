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

        // Ні виводу, ні trigger_error: обидва друкують у тіло відповіді до
        // заголовків при display_errors, і наступний header() падає з
        // "headers already sent" — валився кошик і редиректи вітрини.
        $raised = [];
        set_error_handler(static function (int $no, string $str) use (&$raised): bool {
            $raised[] = $str;
            return true;
        });

        ob_start();
        try {
            $notExists = $module->moduleDirectoryNotExists('NoSuchVendor', 'NoSuchModule');
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
        }

        $this->assertTrue($notExists);
        $this->assertSame('', $output, 'Гілка відсутнього модуля не має нічого друкувати');
        $this->assertSame([], $raised, 'Гілка відсутнього модуля не має піднімати PHP-помилок');
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
