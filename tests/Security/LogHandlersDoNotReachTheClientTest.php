<?php

namespace Security;

use FilesystemIterator;
use Monolog\Handler\HandlerInterface;
use Okay\Core\OkayContainer\Reference\ServiceReference;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

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
     * Конфіг ядра — не єдиний шлях: модуль оголошує сервіси власним
     * Init/services.php, а хендлер можна запушити й просто з коду, як це
     * робить Scheduler. Тому шукаємо по всьому дереву застосунку.
     */
    public function testNoApplicationSourceMentionsAClientFacingHandler(): void
    {
        $sources = $this->phpSources();
        $this->assertNotEmpty($sources, 'Сканувати нічого — перевірка нічого не міряє');

        $offenders = [];
        foreach ($sources as $file) {
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
    private function phpSources(): array
    {
        $files = [];
        foreach (['/Okay', '/backend'] as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                self::root() . $dir,
                FilesystemIterator::SKIP_DOTS
            ));

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php' || !$file->isReadable()) {
                    continue;
                }

                // backend/files — каталог завантажень, а не код застосунку.
                if (str_starts_with($file->getPathname(), self::root() . '/backend/files/')) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
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
