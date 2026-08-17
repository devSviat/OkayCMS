<?php

namespace Modules\OkayCMS\RozetkaPay;

use PHPUnit\Framework\TestCase;

/**
 * Повернення грошей виконувалось за GET, а `checkSession()` гардить лише
 * небезпечні методи — тобто токен для GET не вимагався ніколи.
 */
class RefundCsrfTest extends TestCase
{
    public function testRefundReadsTheOrderIdFromPostOnly()
    {
        $path = dirname(__DIR__, 4)
            . '/Okay/Modules/OkayCMS/RozetkaPay/Backend/Controllers/RefundAdmin.php';
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        $this->assertStringContainsString("\$this->request->post('order', 'integer')", $source);
        $this->assertStringNotContainsString('$_GET', $source);
    }
}
