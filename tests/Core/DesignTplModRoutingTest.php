<?php

namespace Core;

use Okay\Core\Design;
use Okay\Core\Modules\Modules;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Модулі змінюють .tpl через modifications у module.json, а Design обирає набір
 * модифікацій - фронтових чи бекендових - за шляхом шаблону, який компілюється.
 *
 * Smarty 4 давав цей шлях у $template->_current_file; Smarty 5 його прибрав, і
 * тепер це $template->getSource()->getFilepath(). Той метод повертає false, коли
 * файлу немає, і null для нефайлових ресурсів (string:, eval:), тож обидва
 * випадки мають деградувати так само тихо, як раніше, а не кидати TypeError.
 */
class DesignTplModRoutingTest extends TestCase
{
    private const ROOT = '/var/www/html/';

    /**
     * @dataProvider filepathProvider
     */
    public function testRoutesByTemplatePath($filepath, string $expected): void
    {
        $used = null;
        $modules = $this->createMock(Modules::class);
        $modules->method('getBackendModulesTplModifications')
            ->willReturnCallback(function () use (&$used) {
                $used = 'backend';
                return [];
            });
        $modules->method('getFrontModulesTplModifications')
            ->willReturnCallback(function () use (&$used) {
                $used = 'front';
                return [];
            });

        $design = (new ReflectionClass(Design::class))->newInstanceWithoutConstructor();
        $this->setPrivate($design, 'modules', $modules);
        $this->setPrivate($design, 'rootDir', self::ROOT);

        $design->applyTplModifiers('<p>вміст</p>', $this->templateStub($filepath));

        $this->assertSame($expected, $used);
    }

    public function filepathProvider(): array
    {
        return [
            'шаблон адмінки' => [self::ROOT . 'backend/design/html/index.tpl', 'backend'],
            'шаблон теми'    => [self::ROOT . 'design/vibe_shop/html/main.tpl', 'front'],
            'шаблон модуля'  => [self::ROOT . 'Okay/Modules/OkayCMS/FAQ/design/html/faq.tpl', 'front'],
            'файлу немає'    => [false, 'front'],
            'нефайловий ресурс' => [null, 'front'],
        ];
    }

    public function testContentIsReturnedUnchangedWhenNothingMatches(): void
    {
        $modules = $this->createMock(Modules::class);
        $modules->method('getFrontModulesTplModifications')->willReturn([]);

        $design = (new ReflectionClass(Design::class))->newInstanceWithoutConstructor();
        $this->setPrivate($design, 'modules', $modules);
        $this->setPrivate($design, 'rootDir', self::ROOT);

        $content = '<p>{$x|escape}</p>';

        $this->assertSame(
            $content,
            $design->applyTplModifiers($content, $this->templateStub(null))
        );
    }

    private function templateStub($filepath): object
    {
        $source = new class ($filepath) {
            private $filepath;
            public function __construct($filepath)
            {
                $this->filepath = $filepath;
            }
            public function getFilepath()
            {
                return $this->filepath;
            }
        };

        return new class ($source) {
            private $source;
            public function __construct($source)
            {
                $this->source = $source;
            }
            public function getSource()
            {
                return $this->source;
            }
        };
    }

    private function setPrivate(object $object, string $property, $value): void
    {
        (new \ReflectionProperty(Design::class, $property))->setValue($object, $value);
    }
}
