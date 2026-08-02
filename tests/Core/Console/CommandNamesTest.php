<?php

namespace Core\Console;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Symfony 7 прибрав `protected static $defaultName`: команда без імені кладе весь
 * `./ok` фатальною помилкою ще на реєстрації. Тести цього не помічали, бо консоль
 * у них не піднімається — звідси ця перевірка.
 */
class CommandNamesTest extends TestCase
{
    /** @return list<array{0: string}> */
    public static function commandClassProvider(): array
    {
        $dir = dirname(__DIR__, 3) . '/Okay/Core/Console/Commands';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        $classes = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = trim(str_replace($dir, '', $file->getPathname()), '/');
            $classes[] = ['Okay\\Core\\Console\\Commands\\' . str_replace(['/', '.php'], ['\\', ''], $relative)];
        }

        return $classes;
    }

    /** @dataProvider commandClassProvider */
    public function testEveryCommandDeclaresItsNameThroughTheAttribute(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsCommand::class);

        $this->assertCount(1, $attributes, "$class must carry one #[AsCommand]");
        $this->assertNotEmpty($attributes[0]->newInstance()->name, "$class must have a command name");
    }

    /** @dataProvider commandClassProvider */
    public function testNoCommandReliesOnTheRemovedStaticProperty(string $class): void
    {
        $source = file_get_contents((new \ReflectionClass($class))->getFileName());

        $this->assertStringNotContainsString('$defaultName', $source);
        $this->assertStringNotContainsString('$defaultDescription', $source);
    }
}
