<?php


namespace Core\SmartyPlugins;


use Design\TemplateTagInventory;
use Okay\Core\SmartyPlugins\Func;
use Okay\Core\SmartyPlugins\Modifier;
use Okay\Core\SmartyPlugins\Plugin;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Design/TemplateTagInventory.php';

/**
 * За типом плагіна двигун вирішує, під яким тегом його реєструвати - і те саме
 * рішення ухвалюється ще двічі при мокуванні плагінів модулів. Плагін під не тим
 * типом лишає тег незареєстрованим, а Smarty 5 на це кидає помилку компіляції.
 *
 * Шляхи мокування працюють лише для не встановлених чи вимкнених модулів, тобто
 * у звичайному прогоні не виконуються взагалі - без цього тесту логіку не тримало
 * б ніщо.
 */
class PluginTypeResolutionTest extends TestCase
{
    public function testFuncSubclassResolvesToFunction(): void
    {
        $this->assertSame(Plugin::TYPE_FUNCTION, Plugin::resolveType(new class extends Func {}));
    }

    public function testModifierSubclassResolvesToModifier(): void
    {
        $this->assertSame(Plugin::TYPE_MODIFIER, Plugin::resolveType(new class extends Modifier {}));
    }

    /**
     * Саме заради цього випадку тут is_subclass_of, а не порівняння прямого
     * батька: у DeepModifier прямий батько - IntermediateModifier, тож старе
     * правило дало б null і плагін лишився б без мока.
     */
    public function testIndirectSubclassStillResolves(): void
    {
        $this->assertNotSame(
            Modifier::class,
            (new \ReflectionClass(DeepModifier::class))->getParentClass()->getName(),
            'фікстура має бути саме дворівневою, інакше тест нічого не доводить'
        );

        $this->assertSame(Plugin::TYPE_MODIFIER, Plugin::resolveType(DeepModifier::class));
    }

    public function testUnrelatedClassResolvesToNull(): void
    {
        $this->assertNull(Plugin::resolveType(new \stdClass()));
        $this->assertNull(Plugin::resolveType(\stdClass::class));
    }

    public function testAcceptsBothObjectAndClassName(): void
    {
        $plugin = new class extends Func {};

        $this->assertSame(Plugin::resolveType($plugin), Plugin::resolveType(get_class($plugin)));
    }

    /**
     * @dataProvider pluginProvider
     */
    public function testEveryShippedPluginResolvesToAType(string $class): void
    {
        $this->assertContains(
            Plugin::resolveType($class),
            [Plugin::TYPE_FUNCTION, Plugin::TYPE_MODIFIER],
            "{$class}: тип не виводиться, плагін лишиться незареєстрованим"
        );
    }

    public static function pluginProvider(): array
    {
        $cases = [];
        foreach (TemplateTagInventory::pluginClasses() as $class) {
            $cases[$class] = [$class];
        }

        return $cases;
    }
}

/** Дворівнева ієрархія для testIndirectSubclassStillResolves(). */
class IntermediateModifier extends Modifier
{
}

class DeepModifier extends IntermediateModifier
{
}
