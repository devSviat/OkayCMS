<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendFilePickerHelper;
use Okay\Core\Config;
use Okay\Core\Image;
use Okay\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Вибирач файлів редактора: що показує, що приймає й що видаляє.
 *
 * Білий список тут - остання межа перед каталогом, доступним по HTTP,
 * тож перевіряються і прийняття, і відмова.
 */
class BackendFilePickerHelperTest extends TestCase
{
    private $root;
    private $documentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/okay_picker_' . uniqid();
        mkdir($this->root . '/files/uploads', 0777, true);

        $this->documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 3);
        Request::setDomain('shop.test');
        Request::setProtocol('http');
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);

        Request::setDomain('');
        Request::setProtocol('');
        if ($this->documentRoot === null) {
            unset($_SERVER['DOCUMENT_ROOT']);
        } else {
            $_SERVER['DOCUMENT_ROOT'] = $this->documentRoot;
        }

        parent::tearDown();
    }

    public function testListsOnlyWhitelistedExtensions(): void
    {
        $this->give(['photo.jpg', 'song.mp3', 'sheet.xlsx', 'shell.php', 'page.html', 'notes.txt']);

        $names = $this->names($this->helper()->findFiles('', 'file', '', 1, 60));

        sort($names);
        $this->assertSame(['photo.jpg', 'sheet.xlsx', 'song.mp3'], $names);
    }

    public function testTypeNarrowsTheList(): void
    {
        $this->give(['photo.jpg', 'logo.svg', 'song.mp3', 'sheet.xlsx']);

        $image = $this->names($this->helper()->findFiles('', 'image', '', 1, 60));
        $media = $this->names($this->helper()->findFiles('', 'media', '', 1, 60));

        sort($image);
        $this->assertSame(['logo.svg', 'photo.jpg'], $image);
        $this->assertSame(['song.mp3'], $media);
    }

    public function testExtensionIsMatchedCaseInsensitively(): void
    {
        $this->give(['SHOT.JPG']);

        $this->assertSame(['SHOT.JPG'], $this->names($this->helper()->findFiles('', 'image', '', 1, 60)));
    }

    public function testSearchMatchesPartOfTheName(): void
    {
        $this->give(['okay_broadway.jpg', 'gallery_1.jpg']);

        $this->assertSame(['okay_broadway.jpg'], $this->names($this->helper()->findFiles('', 'file', 'broad', 1, 60)));
    }

    public function testFoldersAreListedSeparatelyFromFiles(): void
    {
        $this->give(['photo.jpg']);
        mkdir($this->uploads() . '/2026');

        $list = $this->helper()->findFiles('', 'file', '', 1, 60);

        $this->assertSame([['name' => '2026', 'path' => '2026']], $list['folders']);
        $this->assertSame(['photo.jpg'], $this->names($list));
    }

    public function testPaginationSlicesTheList(): void
    {
        $this->give(['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg', 'e.jpg']);

        $list = $this->helper()->findFiles('', 'file', '', 2, 2);

        $this->assertCount(2, $list['files']);
        $this->assertSame(2, $list['page']);
        $this->assertSame(3, $list['pagesCount']);
        $this->assertSame(5, $list['total']);
    }

    public function testPageBeyondTheLastOneFallsBackToTheLast(): void
    {
        $this->give(['a.jpg', 'b.jpg', 'c.jpg']);

        $this->assertSame(2, $this->helper()->findFiles('', 'file', '', 99, 2)['page']);
    }

    public function testTraversalPathListsNothing(): void
    {
        $this->give(['photo.jpg']);
        file_put_contents($this->root . '/files/secret.jpg', 'x');

        $list = $this->helper()->findFiles('../', 'file', '', 1, 60);

        $this->assertSame([], $list['files']);
        $this->assertSame([], $list['folders']);
    }

    public function testUploadAcceptsAWhitelistedExtension(): void
    {
        $uploaded = $this->helper()->uploadFile($this->file('photo.jpg', 'binary'));

        $this->assertSame('photo.jpg', $uploaded['name']);
        $this->assertSame('http://shop.test/files/uploads/photo.jpg', $uploaded['url']);
        $this->assertFileExists($this->uploads() . '/photo.jpg');
    }

    #[DataProvider('rejectedFileProvider')]
    public function testUploadRejectsExecutableExtensions(string $name): void
    {
        $this->assertFalse($this->helper()->uploadFile($this->file($name, '<?php echo 1;')), $name);
        $this->assertCount(2, scandir($this->uploads()), $name . ' лишився в каталозі');
    }

    public static function rejectedFileProvider(): array
    {
        return [
            'php'    => ['shell.php'],
            'phtml'  => ['shell.phtml'],
            'html'   => ['page.html'],
            'htaccess' => ['.htaccess'],
            'без розширення' => ['README'],
        ];
    }

    public function testUploadStripsThePathFromTheName(): void
    {
        $uploaded = $this->helper()->uploadFile($this->file('../../evil.jpg', 'binary'));

        $this->assertSame('evil.jpg', $uploaded['name']);
        $this->assertFileExists($this->uploads() . '/evil.jpg');
        $this->assertFileDoesNotExist($this->root . '/evil.jpg');
    }

    public function testUploadDoesNotOverwriteAnExistingFile(): void
    {
        $this->give(['photo.jpg']);

        $uploaded = $this->helper()->uploadFile($this->file('photo.jpg', 'new'));

        $this->assertSame('photo_1.jpg', $uploaded['name']);
        $this->assertSame('photo.jpg', file_get_contents($this->uploads() . '/photo.jpg'));
    }

    public function testUploadRefusesAFileOverTheSizeLimit(): void
    {
        $file = $this->file('huge.jpg', 'binary');
        $file['size'] = 10485761;

        $this->assertFalse($this->helper()->uploadFile($file));
        $this->assertFileDoesNotExist($this->uploads() . '/huge.jpg');
    }

    public function testUploadRefusesATraversalPath(): void
    {
        $this->assertFalse($this->helper()->uploadFile($this->file('photo.jpg', 'binary'), '../'));
    }

    public function testUploadedSvgLosesItsScripts(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script>'
             . '<rect onload="alert(2)" width="1" height="1"/></svg>';

        $uploaded = $this->helper()->uploadFile($this->file('logo.svg', $svg));

        $stored = file_get_contents($this->uploads() . '/' . $uploaded['name']);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
    }

    public function testUnsanitizableSvgIsNotKept(): void
    {
        $this->assertFalse($this->helper()->uploadFile($this->file('broken.svg', 'зовсім не xml')));
        $this->assertFileDoesNotExist($this->uploads() . '/broken.svg');
    }

    public function testDeleteRemovesTheFile(): void
    {
        $this->give(['photo.jpg']);

        $this->assertTrue($this->helper()->deleteFile('', 'photo.jpg'));
        $this->assertFileDoesNotExist($this->uploads() . '/photo.jpg');
    }

    public function testDeleteRefusesToLeaveTheUploadsDirectory(): void
    {
        file_put_contents($this->root . '/files/secret.jpg', 'x');

        $this->assertFalse($this->helper()->deleteFile('', '../secret.jpg'));
        $this->assertFileExists($this->root . '/files/secret.jpg');
    }

    public function testDeleteRefusesToLeaveViaThePath(): void
    {
        file_put_contents($this->root . '/files/secret.jpg', 'x');

        $this->assertFalse($this->helper()->deleteFile('../', 'secret.jpg'));
        $this->assertFileExists($this->root . '/files/secret.jpg');
    }

    public function testDeleteDoesNotRemoveDirectories(): void
    {
        mkdir($this->uploads() . '/2026');

        $this->assertFalse($this->helper()->deleteFile('', '2026'));
        $this->assertDirectoryExists($this->uploads() . '/2026');
    }

    /**
     * Apache із "AddHandler ... .php" шукає своє розширення в будь-якій позиції
     * імені, тож shell.php.jpg на шаредхостингу виконується. Перевірено в
     * php:8.4-apache: RemoveType у files/.htaccess цього не зупиняє.
     */
    public function testUploadFlattensInnerExtensions(): void
    {
        $uploaded = $this->helper()->uploadFile($this->file('shell.php.jpg', 'binary'));

        $this->assertSame('shell_php.jpg', $uploaded['name']);
        $this->assertFileExists($this->uploads() . '/shell_php.jpg');
        $this->assertFileDoesNotExist($this->uploads() . '/shell.php.jpg');
    }

    public function testUploadKeepsASingleDotName(): void
    {
        $uploaded = $this->helper()->uploadFile($this->file('photo.jpg', 'binary'));

        $this->assertSame('photo.jpg', $uploaded['name']);
    }

    public function testNestedFolderIsListed(): void
    {
        mkdir($this->uploads() . '/2026/08', 0777, true);
        file_put_contents($this->uploads() . '/2026/08/nested.jpg', 'x');

        $this->assertSame(['nested.jpg'], $this->names($this->helper()->findFiles('2026/08', 'file', '', 1, 60)));
    }

    /** Значення з запиту приходить як є, і path[]=x доходить сюди масивом. */
    public function testNonStringPathIsRefusedEverywhere(): void
    {
        $this->give(['photo.jpg']);
        $helper = $this->helper();

        $this->assertSame([], $helper->findFiles(['x'], 'file', '', 1, 60)['files']);
        $this->assertFalse($helper->uploadFile($this->file('photo.jpg', 'binary'), ['x']));
        $this->assertFalse($helper->deleteFile(['x'], 'photo.jpg'));
        $this->assertNull($helper->parentPath(['x']));
        $this->assertFileExists($this->uploads() . '/photo.jpg');
    }

    public function testParentPathClimbsOneLevelAndStopsAtTheRoot(): void
    {
        $helper = $this->helper();

        $this->assertNull($helper->parentPath(''));
        $this->assertSame('', $helper->parentPath('2026'));
        $this->assertSame('2026', $helper->parentPath('2026/08'));
    }

    private function helper(): BackendFilePickerHelper
    {
        $config = $this->createStub(Config::class);
        $root = $this->root . '/';
        $config->method('get')->willReturnCallback(
            static fn ($name) => $name === 'root_dir' ? $root : null
        );

        // Request не піддається mock: у нього є метод method(). Тому справжній
        // обʼєкт, а URL задано статичними сеттерами в setUp().
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        // Справжній correctFilename: саме він вирішує, що лишиться від імені файла.
        $image = (new ReflectionClass(Image::class))->newInstanceWithoutConstructor();

        return new BackendFilePickerHelperWithoutUpload($config, $image, $request);
    }

    private function uploads(): string
    {
        return $this->root . '/files/uploads';
    }

    private function give(array $names): void
    {
        foreach ($names as $name) {
            file_put_contents($this->uploads() . '/' . $name, $name);
        }
    }

    private function file(string $name, string $body): array
    {
        $tmp = $this->root . '/tmp_' . uniqid();
        file_put_contents($tmp, $body);

        return ['name' => $name, 'tmp_name' => $tmp, 'error' => 0, 'size' => strlen($body)];
    }

    private function names(array $list): array
    {
        return array_column($list['files'], 'name');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->removeTree($child) : unlink($child);
        }

        rmdir($path);
    }
}

/**
 * move_uploaded_file поза HTTP-запитом завжди повертає false.
 */
class BackendFilePickerHelperWithoutUpload extends BackendFilePickerHelper
{
    protected function moveUploadedFile($from, $to)
    {
        return rename($from, $to);
    }
}
