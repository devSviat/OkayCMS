<?php

namespace Admin;

use Okay\Core\BackendPostRedirectGet;
use Okay\Core\BackendTranslations;
use Okay\Core\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Entity::add() на невдалій вставці віддає false, а картки адмінки однаково
 * писали «додано»: менеджер бачив зелений алерт, а в базі нічого не було.
 */
class AddFailedIsNotSuccessTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['message_success'], $_SESSION['message_error'], $_SESSION['new_entity_id']);
    }

    public function testEmptyIdReplacesSuccessWithError(): void
    {
        $prg = self::prg();
        $prg->storeMessageSuccess('added');
        $prg->storeNewEntityId(false);

        self::assertArrayNotHasKey('message_success', $_SESSION);
        self::assertArrayNotHasKey('new_entity_id', $_SESSION);
        self::assertSame('general_add_failed', $_SESSION['message_error']);
    }

    public function testRealIdIsKept(): void
    {
        $prg = self::prg();
        $prg->storeMessageSuccess('added');
        $prg->storeNewEntityId(42);

        self::assertSame('added', $_SESSION['message_success']);
        self::assertSame(42, $_SESSION['new_entity_id']);
        self::assertArrayNotHasKey('message_error', $_SESSION);
    }

    /** Без перевірки одразу після add() контролер далі пише категорії, варіанти й картинки до запису, якого немає. */
    public function testEveryCoreAddIsGuardedRightAway(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(glob($root . '/backend/Controllers/*.php'), glob($root . '/Okay/Modules/OkayCMS/*/Backend/Controllers/*.php'));
        $checked = 0;
        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $i => $line) {
                if (!preg_match('/^\s*(\$[\w>\-]+)\s*=\s*\$[\w>\-]+->add\(.*\);\s*$/', $line, $m)) {
                    continue;
                }
                $following = implode('', array_slice($lines, $i + 1, 12));
                if (!str_contains($following, "storeMessageSuccess('added')")) {
                    continue;
                }
                $checked++;
                self::assertStringContainsString(
                    "requireAdded({$m[1]})",
                    $lines[$i + 1] ?? '',
                    basename($file) . ':' . ($i + 1) . ' — результат add() не перевірено'
                );
            }
        }

        self::assertGreaterThanOrEqual(19, $checked, 'пошук місць add() нічого не знаходить — перевірка була б зеленою ні на чому');
    }

    private static function prg(): BackendPostRedirectGet
    {
        $reflector = new ReflectionClass(BackendPostRedirectGet::class);
        $prg = $reflector->newInstanceWithoutConstructor();
        $reflector->getProperty('request')->setValue($prg, (new ReflectionClass(Request::class))->newInstanceWithoutConstructor());
        $reflector->getProperty('backendTranslations')->setValue($prg, new class extends BackendTranslations {
            public function __construct() {}
            public function getTranslation($var) { return $var; }
        });

        return $prg;
    }
}
