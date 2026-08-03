<?php

namespace Security;

use Okay\Core\Adapters\Resize\AdapterManager;
use Okay\Core\Config;
use Okay\Core\Database;
use Okay\Core\EntityFactory;
use Okay\Core\Image;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Settings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Ім'я файлу для ресайзу приходить із маршруту files/resized/{object}/{filename},
 * де {filename} — це (.+), тобто слеші проходять, а контролер ще й робить
 * rawurldecode(). Тому "../" у імені доїжджало до file_exists(), copy() і до
 * шляху запису прев'ю.
 */
class ImageResizePathBoundaryTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/okay-resize-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/orig', 0777, true);
        mkdir($this->root . '/resized', 0777, true);
        mkdir($this->root . '/secret', 0777, true);

        file_put_contents($this->root . '/orig/pic.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        file_put_contents($this->root . '/secret/private.svg', '<svg><!-- таємниця --></svg>');
    }

    protected function tearDown(): void
    {
        foreach (['orig', 'resized', 'secret'] as $dir) {
            foreach (glob($this->root . '/' . $dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->root . '/' . $dir);
        }
        @rmdir($this->root);

        parent::tearDown();
    }

    #[DataProvider('hostilePathProvider')]
    public function testHostilePathIsRefused($filename)
    {
        $result = $this->image()->resize($filename, ['100x100'], 'orig/', 'resized/');

        $this->assertFalse($result, $filename);
    }

    public static function hostilePathProvider()
    {
        return [
            'traversal вгору'   => ['../secret/private.100x100.svg'],
            'глибший traversal' => ['../../etc/passwd.100x100.svg'],
            'traversal усередині' => ['orig/../../secret/private.100x100.svg'],
            'абсолютний шлях'   => ['/etc/passwd.100x100.svg'],
            'зворотний слеш'    => ['..\\secret\\private.100x100.svg'],
            'схема'             => ['php://filter/resource=x.100x100.svg'],
        ];
    }

    public function testNulByteIsRefused()
    {
        $result = $this->image()->resize("pic.100x100.svg\0.png", ['100x100'], 'orig/', 'resized/');

        $this->assertFalse($result);
    }

    /**
     * Гілка copy() для svg — єдина, що не потребує адаптера, тому саме на ній
     * видно, що назовні нічого не копіюється.
     */
    public function testTraversalCopiesNothing()
    {
        $this->image()->resize('../secret/private.100x100.svg', ['100x100'], 'orig/', 'resized/');

        $this->assertSame([], glob($this->root . '/resized/*') ?: []);
    }

    /**
     * getResizeParams() повертає false на імені без суфікса розміру, а
     * результат розкладався через list() без перевірки.
     */
    public function testUnparsableNameIsRefusedWithoutWarnings()
    {
        $result = $this->image()->resize('pic.svg', ['100x100'], 'orig/', 'resized/');

        $this->assertFalse($result);
    }

    /**
     * Головне, чого не можна зламати: звичайне ім'я має різатись як раніше.
     */
    public function testOrdinaryNameStillResolves()
    {
        $result = $this->image()->resize('pic.100x100.svg', ['100x100'], 'orig/', 'resized/');

        $this->assertIsString($result);
        $this->assertFileExists($this->root . '/resized/pic.100x100.svg');
    }

    private function image(): Image
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(null);

        $config = $this->createStub(Config::class);
        $config->method('get')->willReturn('orig/');

        $response = $this->createStub(Response::class);
        $response->method('setStatusCode')->willReturn($response);

        // Request не піддається createStub(): у нього є власний метод
        // method(), а PHPUnit відмовляється дублювати класи з таким іменем.
        $request = new class extends Request {
            public function __construct()
            {
            }
        };

        // Database::__destruct() відключає PDO, якого в дублі немає.
        $database = new class extends Database {
            public function __construct()
            {
            }

            public function __destruct()
            {
            }
        };

        return new Image(
            $settings,
            $config,
            $this->createStub(AdapterManager::class),
            $request,
            $response,
            $this->createStub(QueryFactory::class),
            $database,
            $this->createStub(EntityFactory::class),
            $this->root . '/'
        );
    }
}
