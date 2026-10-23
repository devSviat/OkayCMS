<?php

namespace Security;

use Okay\Core\Security\SessionNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Ідентифікатор сесії, якого сервер не видавав, має замінюватись новим.
 * Без строгого режиму підкинутий заздалегідь id (фіксація сесії) ставав
 * сесією жертви, а файл сесії створювався з іменем, вигаданим нападником.
 */
class SessionStrictModeTest extends TestCase
{
    private const FOREIGN_ID = 'attackerchosen0123456789abcdefgh';

    /** @return array<string, array{0: string, 1: string}> */
    public static function sessionProvider(): array
    {
        return [
            'вітрина' => [SessionNames::FRONTEND, 'startFrontend'],
            'адмінка' => [SessionNames::BACKEND, 'startBackend'],
        ];
    }

    #[RunInSeparateProcess]
    #[DataProvider('sessionProvider')]
    public function testForeignSessionIdIsReplaced(string $name, string $start): void
    {
        $dir = sys_get_temp_dir() . '/strict-' . uniqid('', true);
        mkdir($dir);
        ini_set('session.save_path', $dir);
        ini_set('session.use_strict_mode', '0');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        session_id(self::FOREIGN_ID);
        SessionNames::$start();

        $this->assertNotSame(self::FOREIGN_ID, session_id());
        $this->assertFileDoesNotExist($dir . '/sess_' . self::FOREIGN_ID);
    }

    /** Контроль: id, який сервер сам видав, лишається тим самим. */
    #[RunInSeparateProcess]
    public function testOwnSessionIdIsKept(): void
    {
        $dir = sys_get_temp_dir() . '/strict-' . uniqid('', true);
        mkdir($dir);
        ini_set('session.save_path', $dir);
        $_SERVER['REQUEST_METHOD'] = 'GET';

        SessionNames::startFrontend();
        $_SESSION['marker'] = 1;
        $issued = session_id();
        session_write_close();

        session_id($issued);
        SessionNames::startFrontend();

        $this->assertSame($issued, session_id());
        $this->assertSame(1, $_SESSION['marker'] ?? null);
    }
}
