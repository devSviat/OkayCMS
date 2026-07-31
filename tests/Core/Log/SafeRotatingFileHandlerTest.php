<?php

namespace Core\Log;

use Monolog\Logger;
use Okay\Core\Log\SafeRotatingFileHandler;
use PHPUnit\Framework\TestCase;

class SafeRotatingFileHandlerTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/okay-log-test-' . getmypid();
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') as $file) {
            @chmod($file, 0666);
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testItStillWritesWhenTheFileIsWritable()
    {
        $logger = new Logger('test');
        $logger->pushHandler(new SafeRotatingFileHandler($this->dir . '/app.log', 2));

        $logger->error('written to disk');

        $written = implode('', array_map('file_get_contents', glob($this->dir . '/*')));
        $this->assertStringContainsString('written to disk', $written);
    }

    /**
     * Недоступний для запису лог не повинен валити запит: до цього виняток
     * Monolog піднімався нагору й сторінка віддавала 500.
     */
    public function testAnUnopenableFileDoesNotThrow()
    {
        // Файл замість теки: відкриття падає з ENOTDIR у будь-якого користувача,
        // тоді як права доступу root просто ігнорує.
        file_put_contents($this->dir . '/blocker', 'not a directory');

        $logger = new Logger('test');
        $logger->pushHandler(new SafeRotatingFileHandler($this->dir . '/blocker/app.log', 2));

        $logger->error('must not escalate into a fatal');

        $this->assertTrue(true);
    }
}
