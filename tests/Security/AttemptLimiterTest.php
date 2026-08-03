<?php

namespace Security;

use Okay\Core\Security\AttemptLimiter;
use PHPUnit\Framework\TestCase;

class AttemptLimiterTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/okay-attempt-limiter-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->dir);
        }

        parent::tearDown();
    }

    public function testCleanKeyIsNotLimited()
    {
        $limiter = new AttemptLimiter($this->dir, 3, 300);

        $this->assertFalse($limiter->tooManyAttempts('1.2.3.4'));
    }

    public function testLimitTripsOnTheConfiguredAttempt()
    {
        $limiter = new AttemptLimiter($this->dir, 3, 300);

        $limiter->registerFailure('1.2.3.4');
        $limiter->registerFailure('1.2.3.4');
        $this->assertFalse($limiter->tooManyAttempts('1.2.3.4'));

        $limiter->registerFailure('1.2.3.4');
        $this->assertTrue($limiter->tooManyAttempts('1.2.3.4'));
    }

    public function testKeysAreIndependent()
    {
        $limiter = new AttemptLimiter($this->dir, 1, 300);

        $limiter->registerFailure('1.2.3.4');

        $this->assertTrue($limiter->tooManyAttempts('1.2.3.4'));
        $this->assertFalse($limiter->tooManyAttempts('5.6.7.8'));
    }

    public function testSuccessClearsTheCounter()
    {
        $limiter = new AttemptLimiter($this->dir, 1, 300);

        $limiter->registerFailure('1.2.3.4');
        $limiter->reset('1.2.3.4');

        $this->assertFalse($limiter->tooManyAttempts('1.2.3.4'));
    }

    public function testFailuresOutsideTheWindowDoNotCount()
    {
        $limiter = new AttemptLimiter($this->dir, 1, 1);

        $limiter->registerFailure('1.2.3.4');
        $this->assertTrue($limiter->tooManyAttempts('1.2.3.4'));

        // Вікно в 1 секунду: пишемо мітку, що вже вийшла за нього.
        $this->writeRaw('1.2.3.4', [time() - 5]);

        $this->assertFalse($limiter->tooManyAttempts('1.2.3.4'));
    }

    /**
     * Ключ приходить ззовні (IP, ідентифікатор дії) і не має ставати шляхом.
     */
    public function testKeyNeverBecomesAPath()
    {
        $limiter = new AttemptLimiter($this->dir, 1, 300);

        $limiter->registerFailure('../../etc/passwd');

        $files = glob($this->dir . '/*') ?: [];
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('~/[0-9a-f]{64}\.json$~', $files[0]);
    }

    public function testCorruptStorageIsTreatedAsClean()
    {
        $limiter = new AttemptLimiter($this->dir, 1, 300);
        $limiter->registerFailure('1.2.3.4');

        $files = glob($this->dir . '/*') ?: [];
        file_put_contents($files[0], 'не json');

        $this->assertFalse($limiter->tooManyAttempts('1.2.3.4'));
    }

    /**
     * @param int[] $timestamps
     */
    private function writeRaw($key, array $timestamps)
    {
        file_put_contents($this->dir . '/' . hash('sha256', $key) . '.json', json_encode($timestamps));
    }
}
