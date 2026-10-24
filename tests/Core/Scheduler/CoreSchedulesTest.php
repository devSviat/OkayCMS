<?php

namespace Core\Scheduler;

use Okay\Core\Scheduler\Scheduler;
use Okay\Core\Security\AttemptLimiter;
use Okay\Core\Security\ManagerSessions;
use PHPUnit\Framework\TestCase;

class CoreSchedulesTest extends TestCase
{
    /** Без цієї задачі файли лічильників спроб накопичуються назавжди. */
    public function testAttemptLimiterIsPrunedEveryHour(): void
    {
        $dir = sys_get_temp_dir() . '/okay-core-schedules-' . bin2hex(random_bytes(4));
        $scheduler = new Scheduler($dir, $dir);

        $tasks = (new \ReflectionProperty(Scheduler::class, 'tasks'))->getValue($scheduler);
        $prune = array_values(array_filter($tasks, fn($task) => $task->getCommand() === [AttemptLimiter::class, 'prune']));

        $this->assertCount(1, $prune);
        $this->assertSame('17 * * * *', $prune[0]->getSchedule()->getExpression());
    }

    /** Без цієї задачі реєстр сесій менеджерів росте з кожним входом. */
    public function testManagerSessionsArePrunedDaily(): void
    {
        $dir = sys_get_temp_dir() . '/okay-core-schedules-' . bin2hex(random_bytes(4));
        $scheduler = new Scheduler($dir, $dir);

        $tasks = (new \ReflectionProperty(Scheduler::class, 'tasks'))->getValue($scheduler);
        $prune = array_values(array_filter($tasks, fn($task) => $task->getCommand() === [ManagerSessions::class, 'prune']));

        $this->assertCount(1, $prune);
        $this->assertSame('43 3 * * *', $prune[0]->getSchedule()->getExpression());
    }
}
