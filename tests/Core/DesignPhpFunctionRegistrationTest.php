<?php

namespace Core;

use Okay\Core\Design;
use Okay\Core\Modules\Module;
use Okay\Core\Modules\Modules;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Core\TplMod\TplMod;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Smarty\Smarty;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * У Smarty 4 нативну функцію в шаблоні пускала політика безпеки, тож реєстрація
 * мала сенс лише разом із нею. У Smarty 5 політика до цього не має стосунку:
 * реєстрація - єдиний механізм, і однаково для {$x|trim} та {max(1,$n)}.
 *
 * Тому реєстрація не має залежати від smarty_security. Інакше рядок
 * smarty_security = false у config.local.php кладе кожну сторінку обох тем і всю
 * адмінку - а це саме той прапорець, який вмикають, коли щось уже зламалось.
 */
class DesignPhpFunctionRegistrationTest extends TestCase
{
    #[DataProvider('securityProvider')]
    public function testPhpFunctionsAreRegisteredRegardlessOfSecurity(bool $security): void
    {
        $smarty = $this->buildDesignAndGetSmarty($security);

        foreach (['trim', 'intval', 'pathinfo', 'preg_match', 'max'] as $function) {
            $this->assertNotNull(
                $smarty->getRegisteredPlugin('modifier', $function),
                "'{$function}' не зареєстровано при smarty_security = " . var_export($security, true)
            );
        }
    }

    public static function securityProvider(): array
    {
        return ['security увімкнено' => [true], 'security вимкнено' => [false]];
    }

    /**
     * Наші плагіни реєструються першими й тримають свої теги: інакше PHP-функція
     * date() перехопила б тег плагіна Date.
     */
    public function testOurPluginKeepsItsTagAgainstASamePhpFunction(): void
    {
        $smarty = $this->buildDesignAndGetSmarty(true, ['date' => static fn () => 'наш date']);

        $registered = $smarty->getRegisteredPlugin('modifier', 'date');

        $this->assertNotNull($registered);
        $this->assertSame('наш date', $registered[0]());
    }

    private function buildDesignAndGetSmarty(bool $security, array $modifiers = []): Smarty
    {
        $rootDir = sys_get_temp_dir() . '/okaycms-design-test/';

        $frontTemplateConfig = $this->createStub(FrontTemplateConfig::class);
        $frontTemplateConfig->method('getTheme')->willReturn('vibe_shop');

        $smarty = new Smarty();

        $design = new Design(
            $smarty,
            $this->createStub(\Detection\MobileDetect::class),
            $frontTemplateConfig,
            $this->createStub(Module::class),
            $this->createStub(Modules::class),
            $this->createStub(TplMod::class),
            0,
            true,
            false,
            false,
            $security,
            false,
            false,
            $rootDir
        );

        foreach ($modifiers as $tag => $callback) {
            $design->registerPlugin('modifier', $tag, $callback);
        }

        $register = (new ReflectionClass(Design::class))->getMethod('registerSmartyPlugins');
        $register->invoke($design);

        return $smarty;
    }
}
