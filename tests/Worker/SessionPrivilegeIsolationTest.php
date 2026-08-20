<?php

namespace Worker;

use Okay\Core\Security\SessionNames;
use Okay\Core\Worker\RequestScopedState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Два запити поспіль в одному процесі - саме те, чого немає під php-fpm і що
 * стає нормою у worker mode.
 *
 * isAdmin() мемоїзує логін менеджера, а ядро довіряє цьому значенню показ
 * невидимих сутностей і обхід site_work=off. Якщо мемоїзація переживе межу
 * запиту, це обхід автентифікації, а не втрата швидкодії.
 */
class SessionPrivilegeIsolationTest extends TestCase
{
    private string $savePath = '';

    protected function setUp(): void
    {
        $this->savePath = sys_get_temp_dir() . '/okay-worker-session-' . getmypid();
        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0700, true);
        }

        ini_set('session.save_path', $this->savePath);
        $_COOKIE = [];
        RequestScopedState::reset();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->savePath . '/sess_*') ?: [] as $file) {
            unlink($file);
        }

        $_COOKIE = [];
        RequestScopedState::reset();
    }

    #[RunInSeparateProcess]
    public function testManagerIdentityDoesNotSurviveTheRequestBoundary(): void
    {
        $id = $this->writeBackendSession('admin');

        $_COOKIE[SessionNames::BACKEND] = $id;
        $this->assertTrue(SessionNames::isAdmin(), 'менеджера не впізнано - перевірка нічого не доводить');

        // Межа запиту.
        RequestScopedState::reset();
        unset($_COOKIE[SessionNames::BACKEND]);

        $this->assertFalse(
            SessionNames::isAdmin(),
            'анонімний запит після запиту менеджера лишився з його привілеями'
        );
    }

    /**
     * Зворотний бік тієї самої мемоїзації: вона фіксує і відсутність логіна,
     * тож менеджер після аноніма втратив би доступ до невидимих сутностей.
     */
    #[RunInSeparateProcess]
    public function testAnonymousIdentityDoesNotSurviveEither(): void
    {
        $this->assertFalse(SessionNames::isAdmin());

        RequestScopedState::reset();
        $_COOKIE[SessionNames::BACKEND] = $this->writeBackendSession('admin');

        $this->assertTrue(
            SessionNames::isAdmin(),
            'запит менеджера після анонімного лишився без привілеїв'
        );
    }

    /** @return string ідентифікатор створеної бекендової сесії */
    private function writeBackendSession(string $login): string
    {
        $length = (int) ini_get('session.sid_length');
        $id     = substr(str_repeat('0123456789abcdefghijklmnopqrstuv', 2), 0, $length > 0 ? $length : 32);

        file_put_contents(
            $this->savePath . '/sess_' . $id,
            'admin|' . serialize($login)
        );

        return $id;
    }
}
