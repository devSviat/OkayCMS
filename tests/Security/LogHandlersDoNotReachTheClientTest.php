<?php

namespace Security;

use Monolog\Handler\HandlerInterface;
use Okay\Core\OkayContainer\Reference\ServiceReference;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Монолог має три хендлери, які віддають записи не у файл, а у відповідь:
 * два дописують заголовок, третій — <script> у тіло. Будь-який із них
 * перетворює лог застосунку на публічні дані.
 */
class LogHandlersDoNotReachTheClientTest extends TestCase
{
    private const CLIENT_FACING_HANDLERS = [
        \Monolog\Handler\ChromePHPHandler::class,
        \Monolog\Handler\FirePHPHandler::class,
        \Monolog\Handler\BrowserConsoleHandler::class,
    ];

    public function testWiredLogHandlersWriteNowhereNearTheResponse(): void
    {
        $handlers = $this->wiredLogHandlerClasses();

        $this->assertNotEmpty($handlers, 'Логер лишився без жодного хендлера — записи нікуди не йдуть');

        foreach ($handlers as $handler) {
            $this->assertTrue(
                is_a($handler, HandlerInterface::class, true),
                sprintf('%s не є хендлером Monolog', $handler)
            );

            foreach (self::CLIENT_FACING_HANDLERS as $clientFacing) {
                $this->assertFalse(
                    is_a($handler, $clientFacing, true),
                    sprintf('%s віддає записи логу в браузер відвідувача', $handler)
                );
            }
        }
    }

    /**
     * Модулі оголошують сервіси власним Init/services.php — обійти вимогу
     * там так само просто, як і в ядрі.
     */
    public function testNoServiceConfigMentionsAClientFacingHandler(): void
    {
        $offenders = [];
        foreach ($this->serviceConfigFiles() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            foreach (self::CLIENT_FACING_HANDLERS as $clientFacing) {
                if (str_contains($source, $this->shortName($clientFacing))) {
                    $offenders[] = sprintf('%s → %s', $this->relative($file), $clientFacing);
                }
            }
        }

        $this->assertSame([], $offenders, 'Хендлер логу віддає записи в браузер: ' . implode(', ', $offenders));
    }

    /**
     * @return list<class-string>
     */
    private function wiredLogHandlerClasses(): array
    {
        $services = include self::root() . '/Okay/Core/config/services.php';

        $this->assertIsArray($services);
        $this->assertArrayHasKey(LoggerInterface::class, $services);

        $classes = [];
        foreach ($services[LoggerInterface::class]['calls'] ?? [] as $call) {
            if (($call['method'] ?? null) !== 'pushHandler') {
                continue;
            }

            foreach ($call['arguments'] ?? [] as $argument) {
                if (!$argument instanceof ServiceReference) {
                    continue;
                }

                $id = $argument->getName();
                $classes[] = $services[$id]['class'] ?? $id;
            }
        }

        return $classes;
    }

    /**
     * @return list<string>
     */
    private function serviceConfigFiles(): array
    {
        $files = glob(self::root() . '/Okay/Core/config/*.php') ?: [];
        $moduleFiles = glob(self::root() . '/Okay/Modules/*/*/Init/services.php') ?: [];

        return array_values(array_merge($files, $moduleFiles));
    }

    private function relative(string $file): string
    {
        return ltrim(str_replace(self::root(), '', $file), '/');
    }

    private function shortName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
