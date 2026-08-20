<?php

namespace Core;

use Okay\Core\OkayContainer\Exception\ContainerException;
use Okay\Core\OkayContainer\OkayContainer;
use Okay\Core\OkayContainer\Reference\ServiceReference;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OkayContainerTest extends TestCase
{
    public function testServiceIsBuiltOnceAndReused(): void
    {
        $container = $this->container(['counter' => ['class' => CountingService::class]]);

        CountingService::$built = 0;

        $first = $container->get('counter');
        $second = $container->get('counter');

        $this->assertSame($first, $second);
        $this->assertSame(1, CountingService::$built, 'сервіс створився більше одного разу');
    }

    public function testDependenciesAreInjected(): void
    {
        $container = $this->container([
            'inner' => ['class' => CountingService::class],
            'outer' => ['class' => DependentService::class, 'arguments' => [new ServiceReference('inner')]],
        ]);

        $outer = $container->get('outer');

        $this->assertInstanceOf(DependentService::class, $outer);
        $this->assertSame($container->get('inner'), $outer->inner);
    }

    /**
     * Замок ловить кільце в залежностях - це його єдина робота, і вона мусить
     * лишитись після того, як замок почав зніматись.
     */
    public function testCircularReferenceIsStillDetected(): void
    {
        $container = $this->container([
            'a' => ['class' => DependentService::class, 'arguments' => [new ServiceReference('b')]],
            'b' => ['class' => DependentService::class, 'arguments' => [new ServiceReference('a')]],
        ]);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/circular reference/');

        $container->get('a');
    }

    /**
     * І на другій спробі теж: якби замок знімався не там, кільце стало б
     * нескінченною рекурсією замість зрозумілої помилки.
     */
    public function testCircularReferenceIsDetectedOnEveryAttempt(): void
    {
        $container = $this->container([
            'a' => ['class' => DependentService::class, 'arguments' => [new ServiceReference('b')]],
            'b' => ['class' => DependentService::class, 'arguments' => [new ServiceReference('a')]],
        ]);

        foreach ([1, 2] as $attempt) {
            try {
                $container->get('a');
                $this->fail("спроба $attempt: кільце не виявлено");
            } catch (ContainerException $e) {
                $this->assertStringContainsString('circular reference', $e->getMessage(), "спроба $attempt");
            }
        }
    }

    /**
     * Було: перша спроба називала справжню причину, друга брехала про кільце -
     * саме тоді, коли діагностика й потрібна.
     */
    public function testFailedConstructorReportsTheSameReasonEveryTime(): void
    {
        $container = $this->container(['boom' => ['class' => ExplodingService::class]]);

        foreach ([1, 2] as $attempt) {
            try {
                $container->get('boom');
                $this->fail("спроба $attempt: виняток не кинуто");
            } catch (\Throwable $e) {
                $this->assertInstanceOf(\RuntimeException::class, $e, "спроба $attempt");
                $this->assertStringContainsString('справжня причина', $e->getMessage(), "спроба $attempt");
                $this->assertStringNotContainsString('circular reference', $e->getMessage(), "спроба $attempt");
            }
        }
    }

    /**
     * Виняток із залежності теж не має лишати замок на батьківському сервісі.
     */
    public function testFailureInsideDependencyLeavesNoLock(): void
    {
        $container = $this->container([
            'boom'  => ['class' => ExplodingService::class],
            'outer' => ['class' => DependentService::class, 'arguments' => [new ServiceReference('boom')]],
        ]);

        foreach ([1, 2] as $attempt) {
            try {
                $container->get('outer');
                $this->fail("спроба $attempt: виняток не кинуто");
            } catch (\Throwable $e) {
                $this->assertStringNotContainsString('circular reference', $e->getMessage(), "спроба $attempt");
            }
        }
    }

    public function testScalarParameterIsReplacedNotAppended(): void
    {
        $container = $this->container([], ['images_dir' => 'core/dir']);

        $container->bindParameters(['images_dir' => 'module/dir']);

        $this->assertSame('module/dir', $container->getParameter('images_dir'));
    }

    public function testNestedParametersMergePerKey(): void
    {
        $container = $this->container([], ['modules' => ['a' => ['x' => 1]]]);

        $container->bindParameters(['modules' => ['b' => ['y' => 2]]]);

        $this->assertSame(1, $container->getParameter('modules.a.x'), 'чужий ключ не має зникати');
        $this->assertSame(2, $container->getParameter('modules.b.y'));
    }

    public function testUnrelatedParametersSurvive(): void
    {
        $container = $this->container([], ['root_dir' => '/var/www', 'db' => ['host' => 'localhost']]);

        $container->bindParameters(['banners' => ['imagesDir' => 'files/banners']]);

        $this->assertSame('/var/www', $container->getParameter('root_dir'));
        $this->assertSame('localhost', $container->getParameter('db.host'));
        $this->assertSame('files/banners', $container->getParameter('banners.imagesDir'));
    }

    /**
     * Контейнер - синглтон із приватним конструктором, а кожен тест потребує
     * свого: getInstance() віддав би один на всіх.
     */
    private function container(array $services, array $parameters = []): OkayContainer
    {
        $reflection = new ReflectionClass(OkayContainer::class);
        $container = $reflection->newInstanceWithoutConstructor();

        foreach (['services' => $services, 'parameters' => $parameters, 'serviceStore' => []] as $name => $value) {
            $reflection->getProperty($name)->setValue($container, $value);
        }

        return $container;
    }
}

class CountingService
{
    public static $built = 0;

    public function __construct()
    {
        self::$built++;
    }
}

class DependentService
{
    public $inner;

    public function __construct($inner = null)
    {
        $this->inner = $inner;
    }
}

class ExplodingService
{
    public function __construct()
    {
        throw new \RuntimeException('справжня причина: конфіг сервіса неповний');
    }
}
