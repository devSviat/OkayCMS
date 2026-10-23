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

    public function testAttemptStopsAtTheLimitAndDoesNotCountRefusals()
    {
        $limiter = new AttemptLimiter($this->dir, 2, 300);

        $this->assertTrue($limiter->attempt('k'));
        $this->assertTrue($limiter->attempt('k'));
        $this->assertFalse($limiter->attempt('k'));
        $this->assertFalse($limiter->attempt('k'));

        // Відмова не продовжує блокування: у сховищі рівно дві мітки.
        $files = glob($this->dir . '/*') ?: [];
        $this->assertCount(2, json_decode((string)file_get_contents($files[0]), true));
    }

    public function testWithLimitsSharesStorageButNotLimits()
    {
        $strict = (new AttemptLimiter($this->dir, 10, 300))->withLimits(1, 300);

        $this->assertTrue($strict->attempt('k'));
        $this->assertFalse($strict->attempt('k'));
        $this->assertTrue((new AttemptLimiter($this->dir, 1, 300))->tooManyAttempts('k'));
    }

    /**
     * Справжні паралельні процеси: перевірка й запис мусять бути атомарними,
     * інакше всі читають той самий лічильник і проходять разом.
     */
    public function testConcurrentAttemptsNeverExceedTheLimit()
    {
        $workers = 12;
        $limit = 3;
        $start = microtime(true) + 1.0;
        $script = sprintf(
            'require %s; while (microtime(true) < %F) {} echo (new %s(%s, %d, 300))->attempt("race") ? 1 : 0;',
            var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
            $start,
            '\\' . AttemptLimiter::class,
            var_export($this->dir, true),
            $limit
        );

        $processes = [];
        for ($i = 0; $i < $workers; $i++) {
            $processes[] = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $outputs[$i] = $pipes;
        }

        $allowed = 0;
        foreach ($processes as $i => $process) {
            $allowed += (int)stream_get_contents($outputs[$i][1]);
            $errors = stream_get_contents($outputs[$i][2]);
            proc_close($process);
            $this->assertSame('', $errors);
        }

        $this->assertSame($limit, $allowed);
    }

    public function testRefundGivesBackTheLastAttempt()
    {
        $limiter = new AttemptLimiter($this->dir, 2, 300);
        $limiter->attempt('k');
        $limiter->attempt('k');
        $this->assertFalse($limiter->attempt('k'));

        $limiter->refund('k');

        $this->assertTrue($limiter->attempt('k'));
        $this->assertFalse($limiter->attempt('k'));
    }

    /** Без прибирання кожна адреса й кожен вигаданий логін лишали б по файлу назавжди. */
    public function testPruneRemovesOnlyStaleCounters()
    {
        $limiter = new AttemptLimiter($this->dir, 2, 300);
        $limiter->attempt('old');
        $limiter->attempt('fresh');
        $old = $this->dir . '/' . hash('sha256', 'old') . '.json';
        touch($old, time() - AttemptLimiter::PRUNE_AFTER_SECONDS - 60);

        $this->assertSame(1, $limiter->prune());
        $this->assertFileDoesNotExist($old);
        $this->assertFileExists($this->dir . '/' . hash('sha256', 'fresh') . '.json');
    }

    /**
     * Недоступне сховище не закриває вхід (тека з root-власником замкнула б
     * адмінку всім), але й не мовчить: ліміт у цей час не діє.
     */
    public function testUnavailableStorageIsLoggedAndLetsTheAttemptThrough()
    {
        file_put_contents($this->dir . '-blocker', '');
        $logger = new class extends \Psr\Log\AbstractLogger {
            public array $errors = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                if ($level === 'error') {
                    $this->errors[] = (string)$message;
                }
            }
        };
        $limiter = new AttemptLimiter($this->dir . '-blocker/sub', 1, 300, $logger);

        try {
            $this->assertTrue($limiter->attempt('k'));
            $this->assertTrue($limiter->withLimits(1, 300)->attempt('k'));
            $this->assertCount(2, $logger->errors);
        } finally {
            @unlink($this->dir . '-blocker');
        }
    }

    /**
     * @param int[] $timestamps
     */
    private function writeRaw($key, array $timestamps)
    {
        file_put_contents($this->dir . '/' . hash('sha256', $key) . '.json', json_encode($timestamps));
    }
}
