<?php

namespace Admin\Controllers;

use Okay\Core\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Типізований get($name, 'string') прибирає всю пунктуацію, зокрема скісну
 * риску, тож вкладена тека ставала недосяжною: шлях "2026/08" доходив до
 * помічника як "202608". Межу тримає PathResolver, а не цей відсів.
 */
class FilePickerAdminPathTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_GET['path']);
        parent::tearDown();
    }

    public function testTypedStringFilterEatsTheFolderSeparator(): void
    {
        /** @var Request $request */
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        $_GET['path'] = '2026/08';

        $this->assertSame('202608', $request->get('path', 'string'));
        $this->assertSame('2026/08', $request->getRawString('path'));
    }

    public function testControllerReadsThePathRaw(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/backend/Controllers/FilePickerAdmin.php');

        $this->assertStringContainsString("getRawString('path')", $source);
        $this->assertStringNotContainsString("get('path', 'string')", $source);
    }
}
