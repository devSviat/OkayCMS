<?php

namespace Core\Scheduler;

use Okay\Core\Scheduler\Task;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * overlap(false): друга копія задачі не виконується, поки першу не відпущено.
 * Раніше Task::run() ігнорував результат acquire(), і scheduler:task -f чи
 * гонка між isDue() і run() запускали задачу двічі.
 */
class TaskOverlapTest extends TestCase
{
    public static int $runs = 0;

    private string $dir;

    public static function work(): void
    {
        self::$runs++;
    }

    protected function setUp(): void
    {
        self::$runs = 0;
        $this->dir = sys_get_temp_dir() . '/task-overlap-' . uniqid('', true);
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function task(LockFactory $factory, bool $overlap): Task
    {
        return new Task([self::class, 'work'], '* * * * *', 60, 'test', $overlap, $factory->createLock('task-overlap-test'));
    }

    public function testTaskWithoutOverlapDoesNotRunWhileAnotherCopyHoldsTheLock(): void
    {
        $factory = new LockFactory(new FlockStore($this->dir));
        $other = $factory->createLock('task-overlap-test');
        $this->assertTrue($other->acquire());

        $this->task($factory, false)->run();

        $this->assertSame(0, self::$runs);
        $other->release();
    }

    /** Контроль: без чужого блокування задача виконується, з overlap(true) — завжди. */
    public function testTaskRunsWhenFreeOrWhenOverlapIsAllowed(): void
    {
        $factory = new LockFactory(new FlockStore($this->dir));
        $this->task($factory, false)->run();
        $this->assertSame(1, self::$runs);

        $other = $factory->createLock('task-overlap-test');
        $this->assertTrue($other->acquire());
        $this->task($factory, true)->run();
        $this->assertSame(2, self::$runs);
        $other->release();
    }
}
