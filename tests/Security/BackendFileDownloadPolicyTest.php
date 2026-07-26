<?php

namespace Security;

use Okay\Core\Security\BackendFileDownloadPolicy;
use PHPUnit\Framework\TestCase;

class BackendFileDownloadPolicyTest extends TestCase
{
    /**
     * Цели ровно те, что переписывает nginx (dev/config/nginx/okay.conf).
     *
     * @dataProvider knownTargetProvider
     */
    public function testKnownTargetsMapToSpecificPermissions($folder, $file, $ext, $permission)
    {
        $policy = new BackendFileDownloadPolicy();

        $this->assertSame($permission, $policy->permissionFor($folder, $file, $ext));
    }

    public function knownTargetProvider()
    {
        return [
            'products export' => ['export', 'export', 'csv', 'export'],
            'orders export'   => ['export', 'export_orders', 'csv', 'orders'],
            'category stats'  => ['export', 'export_stat', 'csv', 'category_stats'],
            'sales report'    => ['export', 'export_stat_products', 'csv', 'sales_report'],
            'users export'    => ['export_users', 'users', 'csv', 'users'],
            'subscribes'      => ['export_users', 'subscribes', 'csv', 'subscribes'],
            'import example'  => ['import', 'example', 'csv', 'import'],
            'import file'     => ['import', 'import', 'csv', 'import'],
            'watermark png'   => ['watermark', 'watermark', 'png', 'settings'],
            'watermark jpg'   => ['watermark', 'watermark', 'jpg', 'settings'],
        ];
    }

    public function testExtensionMatchingIsCaseInsensitive()
    {
        $policy = new BackendFileDownloadPolicy();

        $this->assertSame('export', $policy->permissionFor('export', 'export', 'CSV'));
    }

    /**
     * @dataProvider deniedProvider
     */
    public function testUnknownCombinationsAreDenied($folder, $file, $ext)
    {
        $policy = new BackendFileDownloadPolicy();

        $this->assertNull($policy->permissionFor($folder, $file, $ext));
    }

    public function deniedProvider()
    {
        return [
            'unknown file'      => ['export', 'unknown', 'csv'],
            'unknown folder'    => ['unknown', 'export', 'csv'],
            'forbidden ext'     => ['export', 'export', 'php'],
            'license folder'    => ['license', 'license', 'csv'],
            'traversal folder'  => ['../../config', 'config', 'php'],
            'traversal in file' => ['export', '../../../etc/passwd', 'csv'],
            'watermark as csv'  => ['watermark', 'watermark', 'csv'],
            'null folder'       => [null, 'export', 'csv'],
            'null file'         => ['export', null, 'csv'],
            'null ext'          => ['export', 'export', null],
            'array folder'      => [['export'], 'export', 'csv'],
        ];
    }

    public function testEntrypointUsesThePolicyAndTheResolver()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/backend/files/index.php');
        $this->assertIsString($source);

        $this->assertStringContainsString('BackendFileDownloadPolicy', $source);
        $this->assertStringContainsString('PathResolver', $source);
        $this->assertStringContainsString('permissionFor(', $source);

        // Права проверяются, а не просто наличие менеджера
        $this->assertStringContainsString('in_array($requiredPermission', $source);

        // $_SESSION['admin'] больше не читается без проверки на существование
        $this->assertStringContainsString("empty(\$_SESSION['admin']) ? null :", $source);
    }
}
