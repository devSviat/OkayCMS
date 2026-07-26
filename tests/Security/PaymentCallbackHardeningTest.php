<?php

namespace Security;

use PHPUnit\Framework\TestCase;

class PaymentCallbackHardeningTest extends TestCase
{
    private function source($relativePath)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        $this->assertIsString($source, $relativePath);

        return $source;
    }

    private function rozetkaPayCallback()
    {
        return $this->source('Okay/Modules/OkayCMS/RozetkaPay/Controllers/CallbackController.php');
    }

    /**
     * Було: empty($method) && $method->module !== "OkayCMS/RozetkaPay".
     * За && перевірка модуля не спрацьовувала ніколи, тому колбек одного
     * модуля проходив для замовлення, оплаченого через інший.
     */
    public function testPaymentMethodCheckUsesOr()
    {
        $source = $this->rozetkaPayCallback();

        $this->assertStringContainsString(
            'if (empty($method) || $method->module !== "OkayCMS/RozetkaPay")',
            $source
        );
        $this->assertStringNotContainsString(
            'empty($method) && $method->module',
            $source
        );
    }

    /**
     * Прив'язка колбека до платежу тримається лише на збереженому id.
     * Якщо його немає, порівняння зводилось до null !== null.
     */
    public function testStoredPaymentIdIsRequiredBeforeItIsCompared()
    {
        $source = $this->rozetkaPayCallback();

        $this->assertStringContainsString('!isset($createDetails->id)', $source);
        $this->assertStringContainsString('hash_equals((string) $createDetails->id', $source);
        $this->assertStringNotContainsString('$data->id !== $createDetails->id', $source);
    }

    public function testCallbackBodyIsValidatedBeforeUse()
    {
        $source = $this->rozetkaPayCallback();

        $this->assertStringContainsString('!is_object($data)', $source);
        $this->assertStringContainsString('!isset($data->details->amount)', $source);
        $this->assertStringContainsString('!is_numeric($data->details->amount)', $source);
    }

    /**
     * getPaymentDetails() читав $data[0] без перевірки — на замовленні без
     * платежу це давало звернення до неіснуючого індексу.
     */
    public function testPaymentDetailsLookupHandlesAnEmptyResult()
    {
        $source = $this->rozetkaPayCallback();

        $this->assertMatchesRegularExpression(
            '/if \(empty\(\$data\[0\]\)\) \{\s*return null;/',
            $source
        );
    }

    /**
     * Ключ мерчанта йде в заголовку Authorization, тому вимикати перевірку
     * сертифіката не можна: MITM читав би його відкритим текстом.
     */
    public function testRozetkaPayClientVerifiesTheServerCertificate()
    {
        $source = $this->source(
            'Okay/Modules/OkayCMS/RozetkaPay/Models/Gateway/Client/HttpCurl.php'
        );

        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER => 2', $source);
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $source);
        $this->assertStringNotContainsString('CURLOPT_SSL_VERIFYPEER => 0', $source);
    }

    /**
     * Ключ збірки — секрет; нестроге != порівнює два "числові" рядки
     * ('0e123...') як рівні й до того ж не є сталим за часом.
     */
    public function testAutoDeployBuildKeyIsComparedWithHashEquals()
    {
        $source = $this->source(
            'Okay/Modules/OkayCMS/AutoDeploy/Controllers/BuildController.php'
        );

        $this->assertStringContainsString('hash_equals((string) $currentBuildKey, $buildKey)', $source);
        $this->assertStringNotContainsString('$buildKey != $currentBuildKey', $source);
    }

    /**
     * Ключ друкувався назад у статус деплою — не відлунюємо секрет.
     */
    public function testWrongBuildKeyIsNotEchoedBack()
    {
        $source = $this->source(
            'Okay/Modules/OkayCMS/AutoDeploy/Controllers/BuildController.php'
        );

        $this->assertStringNotContainsString('Build key \\"{$buildKey}\\"', $source);
    }

    /**
     * Відповідь версійного сервісу осідає в сесії та рендериться в адмінці.
     */
    public function testAdminVersionCheckVerifiesTheServerCertificate()
    {
        $source = $this->source('backend/Controllers/IndexAdmin.php');

        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER, 2', $source);
        $this->assertStringNotContainsString('CURLOPT_SSL_VERIFYPEER, 0', $source);
    }
}
