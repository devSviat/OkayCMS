<?php

namespace Core\Modules;

use Okay\Core\Modules\LicenseStorage;
use PHPUnit\Framework\TestCase;

/**
 * Невдалий запит за ліцензією не зберігає файл ліцензії, тож без спільної паузи
 * кожен наступний запит вітрини знову чекав на curl - при недоступному
 * маркетплейсі це 3 с на кожного анонімного відвідувача. Сесійна пауза, що була
 * тут раніше, для відвідувачів без cookie не діє взагалі.
 */
class LicenseStorageTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/license-storage-' . uniqid() . '/';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
    }

    public function testRequestsAreAllowedWhenNothingSuppressedThem(): void
    {
        $this->assertTrue((new LicenseStorage($this->dir))->isRequestAllowed());
    }

    public function testSuppressionBlocksSubsequentRequests(): void
    {
        $storage = new LicenseStorage($this->dir);

        $storage->suppressRequestsFor(1200);

        $this->assertFalse(
            $storage->isRequestAllowed(),
            'Після невдачі наступний запит не має знову йти в мережу.'
        );
    }

    public function testSuppressionIsSharedBetweenInstances(): void
    {
        (new LicenseStorage($this->dir))->suppressRequestsFor(1200);

        $this->assertFalse(
            (new LicenseStorage($this->dir))->isRequestAllowed(),
            'Пауза має діяти для всіх відвідувачів, а не лише для того, хто спіймав невдачу.'
        );
    }

    public function testSuppressionExpires(): void
    {
        $storage = new LicenseStorage($this->dir);

        $storage->suppressRequestsFor(-1);

        $this->assertTrue(
            $storage->isRequestAllowed(),
            'Коли пауза минула, за ліцензією треба сходити знову.'
        );
    }

    public function testSuppressionCanBeLifted(): void
    {
        $storage = new LicenseStorage($this->dir);
        $storage->suppressRequestsFor(1200);

        $storage->allowRequests();

        $this->assertTrue(
            $storage->isRequestAllowed(),
            'Зміна пошти ліцензії в адмінці має знімати паузу негайно.'
        );
    }

    public function testLiftingSuppressionIsSafeWhenNoneWasSet(): void
    {
        $storage = new LicenseStorage($this->dir);

        $storage->allowRequests();

        $this->assertTrue($storage->isRequestAllowed());
    }
}
