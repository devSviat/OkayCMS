<?php


namespace Core\SmartyPlugins;


use Design\TemplateTagInventory;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Design/TemplateTagInventory.php';

/**
 * Обʼєкт шаблону, який Smarty передає плагіну другим аргументом, називається
 * Smarty_Internal_Template у Smarty 4 і Smarty\Template у Smarty 5. Плагін, що
 * тайп-хінтить будь-яке з цих імен, ламається на іншій версії з TypeError уже в
 * рантаймі — не на компіляції, тож compile-гейт цього не побачить.
 */
class PluginSignatureTest extends TestCase
{
    /**
     * @dataProvider pluginProvider
     */
    public function testRunDoesNotTypeHintASmartyClass(string $class): void
    {
        $run = (new \ReflectionClass($class))->getMethod('run');
        $offenders = [];

        foreach ($run->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type === null) {
                continue;
            }

            $parts = $type instanceof \ReflectionNamedType ? [$type] : $type->getTypes();
            foreach ($parts as $single) {
                $name = ltrim($single->getName(), '\\');
                if (stripos($name, 'Smarty') === 0) {
                    $offenders[] = "\${$parameter->getName()}: {$name}";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "{$class}::run() тайп-хінтить клас Smarty. Імʼя цього класу різне в "
            . 'Smarty 4 і 5 — лишайте параметр без типу.'
        );
    }

    public static function pluginProvider(): array
    {
        $cases = [];
        foreach (TemplateTagInventory::pluginClasses() as $class) {
            if ((new \ReflectionClass($class))->hasMethod('run')) {
                $cases[$class] = [$class];
            }
        }

        return $cases;
    }
}
