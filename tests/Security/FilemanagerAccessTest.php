<?php

namespace Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FilemanagerAccessTest extends TestCase
{
    #[DataProvider('guardedEntrypointProvider')]
    public function testEntrypointRequiresAnAuthenticatedManager($file)
    {
        $source = $this->read($file);

        $this->assertStringContainsString('okay_access.php', $source, $file);
    }

    #[DataProvider('guardedEntrypointProvider')]
    public function testGuardPrecedesAnyConfigurationOrFilesystemWork($file)
    {
        $source = $this->read($file);

        $guard = strpos($source, 'okay_access.php');
        $this->assertIsInt($guard, $file);

        foreach (["include 'config/config.php'", "include 'include/utils.php'", 'require(\'UploadHandler.php\')'] as $needle) {
            $at = strpos($source, $needle);
            if ($at !== false) {
                $this->assertLessThan($at, $guard, $file . ' -> ' . $needle);
            }
        }
    }

    public static function guardedEntrypointProvider()
    {
        return [
            'dialog'   => ['backend/design/js/filemanager/dialog.php'],
            'upload'   => ['backend/design/js/filemanager/upload.php'],
            'execute'  => ['backend/design/js/filemanager/execute.php'],
            'ajax'     => ['backend/design/js/filemanager/ajax_calls.php'],
            'download' => ['backend/design/js/filemanager/force_download.php'],
        ];
    }

    public function testRemoteUrlUploadIsGone()
    {
        $source = $this->read('backend/design/js/filemanager/upload.php');

        $this->assertStringNotContainsString('curl_init(', $source);
        $this->assertStringNotContainsString("\$_POST['url']", $source);
    }

    public function testSvgUploadsAreSanitized()
    {
        $source = $this->read('backend/design/js/filemanager/UploadHandler.php');

        $this->assertStringContainsString('SvgSanitizer', $source);
    }

    public function testAccessGuardResolvesTheManagerFromTheSession()
    {
        $source = $this->read('Okay/Core/Security/Filemanager/AccessGuard.php');

        $this->assertStringContainsString("\$_SESSION['admin']", $source);
        $this->assertStringContainsString('ManagersEntity', $source);
        $this->assertStringContainsString('403', $source);
    }

    private function read($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        return $source;
    }
}
