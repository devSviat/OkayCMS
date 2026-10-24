<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Відкликана сесія перестає діяти лише там, де вхід звіряє її з реєстром.
 * Тому кожен файл, що стартує бекендову сесію, мусить викликати
 * ManagerSessions::enforce() після контейнера й до першого читання
 * $_SESSION['admin']. Новий вхід без цього — червоний тест.
 */
class ManagerSessionsEnforcedTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string, array{string}> */
    public static function backendEntrypoints(): array
    {
        $root = self::root();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $cases = [];
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if ($file->getExtension() !== 'php' || preg_match('~/(vendor|tests|compiled|cache|node_modules|\.git)/~', $path)) {
                continue;
            }
            $relative = substr($path, strlen($root) + 1);
            if ($relative === 'Okay/Core/Security/SessionNames.php') {
                continue;
            }
            if (str_contains((string)file_get_contents($path), 'startBackend()')) {
                $cases[$relative] = [$relative];
            }
        }
        ksort($cases);

        return $cases;
    }

    public function testTheKnownEntrypointsAreFound(): void
    {
        $this->assertSame(
            ['backend/ajax/configure.php', 'backend/design/js/admintooltip/admintooltip.php', 'backend/files/index.php', 'backend/index.php'],
            array_keys(self::backendEntrypoints())
        );
    }

    #[DataProvider('backendEntrypoints')]
    public function testEntrypointEnforcesTheRegistryBeforeReadingTheAdminMark(string $file): void
    {
        $source = self::codeWithoutComments((string)file_get_contents(self::root() . '/' . $file));

        $start = strpos($source, 'startBackend()');
        $container = strpos($source, "include 'Okay/Core/config/container.php'");
        $enforce = strpos($source, '(\Okay\Core\Security\ManagerSessions::class)->enforce();');
        $firstRead = strpos($source, "\$_SESSION['admin']");

        $this->assertNotFalse($container, "{$file}: контейнер не підключено");
        $this->assertNotFalse($enforce, "{$file}: немає ManagerSessions::enforce()");
        $this->assertGreaterThan($start, $enforce, "{$file}: enforce() до старту сесії нічого не бачить");
        $this->assertGreaterThan($container, $enforce, "{$file}: enforce() до контейнера");
        if ($firstRead !== false) {
            $this->assertLessThan($firstRead, $enforce, "{$file}: \$_SESSION['admin'] читається до enforce()");
        }
    }

    /** Коментар над викликом теж згадує $_SESSION['admin'], а рахується лише код. */
    private static function codeWithoutComments(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /** Вітрина визнає адміна лише через валідатор, і реєструє його контейнер. */
    public function testContainerRegistersTheStorefrontValidator(): void
    {
        $source = (string)file_get_contents(self::root() . '/Okay/Core/config/container.php');

        $this->assertMatchesRegularExpression(
            '~SessionNames::setAdminSessionValidator\(\s*static fn\(string \$login, string \$sessionId\): bool => \$container->get\(ManagerSessions::class\)->isValid\(\$login, \$sessionId\)\s*\);~',
            $source
        );
    }

    /** До старту сесії вітрини бекендова лише читається, перевірка — після контейнера. */
    public function testStorefrontPreloadsBeforeStartingItsSession(): void
    {
        $source = (string)file_get_contents(self::root() . '/index.php');

        $preload = strpos($source, 'SessionNames::preloadBackendSession();');
        $start = strpos($source, 'SessionNames::startFrontend();');

        $this->assertNotFalse($preload);
        $this->assertLessThan($start, $preload);
    }
}
