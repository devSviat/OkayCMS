<?php

namespace Security;

use Okay\Controllers\SupportController;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Security\AttemptLimiter;
use Okay\Entities\SupportInfoEntity;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionProperty;

/**
 * /support.php — публічний ендпойнт без авторизації, який за збігом ключа
 * перезаписує private_key і public_key у базі. Ключі порівнювались через !=:
 * не constant-time і з приведенням типів.
 */
class SupportEndpointHardeningTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/okay-support-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    public function testNonPostIsRejectedWithoutTouchingTheKeys()
    {
        $entity = $this->entity(['temp_key' => 'abc', 'temp_time' => date('Y-m-d H:i:s')]);
        $entity->expects($this->never())->method('updateInfo');

        $result = $this->call('GET', ['action' => 'new_keys', 'temp_key' => 'abc'], $entity);

        $this->assertSame(0, $result['success']);
    }

    public function testWrongTempKeyDoesNotReplaceTheKeys()
    {
        $entity = $this->entity(['temp_key' => 'правильний', 'temp_time' => date('Y-m-d H:i:s')]);
        $entity->expects($this->never())->method('updateInfo');

        $result = $this->call('POST', [
            'action' => 'new_keys',
            'temp_key' => 'неправильний',
            'private_key' => 'p',
            'public_key' => 'P',
        ], $entity);

        $this->assertSame(0, $result['success']);
    }

    /**
     * Нестрога рівність зрівнює два числові рядки за значенням: "0" == "0.0".
     * md5 з самих цифр — рідкість, але саме на цьому й тримається різниця
     * між != і hash_equals.
     */
    public function testNumericLookalikeKeyIsRejected()
    {
        $entity = $this->entity(['temp_key' => '0.0', 'temp_time' => date('Y-m-d H:i:s')]);
        $entity->expects($this->never())->method('updateInfo');

        $result = $this->call('POST', [
            'action' => 'new_keys',
            'temp_key' => '0',
            'private_key' => 'p',
            'public_key' => 'P',
        ], $entity);

        $this->assertSame(0, $result['success']);
    }

    public function testCorrectTempKeyStillReplacesTheKeys()
    {
        $entity = $this->entity(['temp_key' => 'правильний', 'temp_time' => date('Y-m-d H:i:s')]);

        $captured = null;
        $entity->expects($this->once())->method('updateInfo')->willReturnCallback(
            function ($values) use (&$captured) {
                $captured = $values;
            }
        );

        $result = $this->call('POST', [
            'action' => 'new_keys',
            'temp_key' => 'правильний',
            'private_key' => 'p',
            'public_key' => 'P',
        ], $entity);

        $this->assertSame(1, $result['success']);
        $this->assertSame('p', $captured['private_key']);
        $this->assertSame('P', $captured['public_key']);
        $this->assertNull($captured['temp_key']);
    }

    public function testWrongPublicKeyIsRejected()
    {
        $entity = $this->entity(['public_key' => 'правильний']);
        $entity->expects($this->never())->method('updateInfo');

        $result = $this->call('POST', [
            'action' => 'receive_info',
            'key' => 'неправильний',
            'balance' => 100,
        ], $entity);

        $this->assertSame(0, $result['success']);
    }

    public function testRepeatedFailuresAreThrottled()
    {
        $entity = $this->entity(['temp_key' => 'правильний', 'temp_time' => date('Y-m-d H:i:s')]);
        $limiter = new AttemptLimiter($this->dir, 3, 300);

        $payload = ['action' => 'new_keys', 'temp_key' => 'ні', 'private_key' => 'p', 'public_key' => 'P'];
        for ($i = 0; $i < 3; $i++) {
            $this->call('POST', $payload, $entity, $limiter);
        }

        // Ліміт вичерпано: далі не пускають навіть з правильним ключем.
        $entity->expects($this->never())->method('updateInfo');
        $payload['temp_key'] = 'правильний';
        $result = $this->call('POST', $payload, $entity, $limiter);

        $this->assertSame(0, $result['success']);
        $this->assertSame('too_many_attempts', $result['error']);
    }

    public function testMalformedBodyIsRejected()
    {
        $entity = $this->entity(['temp_key' => 'abc']);
        $entity->expects($this->never())->method('updateInfo');

        $result = $this->callRaw('POST', 'це не json', $entity);

        $this->assertSame(0, $result['success']);
    }

    public function testJsonArrayBodyIsRejected()
    {
        $entity = $this->entity(['temp_key' => 'abc']);
        $entity->expects($this->never())->method('updateInfo');

        $result = $this->callRaw('POST', '["new_keys"]', $entity);

        $this->assertSame(0, $result['success']);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function call($method, array $payload, $entity, ?AttemptLimiter $limiter = null)
    {
        return $this->callRaw($method, json_encode($payload), $entity, $limiter);
    }

    /**
     * @return array<string, mixed>
     */
    private function callRaw($method, $body, $entity, ?AttemptLimiter $limiter = null)
    {
        $request = new class($method, $body) extends Request {
            public function __construct(private string $requestMethod, private string $body)
            {
            }

            public function method($method = null)
            {
                if (empty($method)) {
                    return $this->requestMethod;
                }

                return strtoupper((string)$method) === $this->requestMethod;
            }

            public function post($name = null, $type = null, $default = null)
            {
                return empty($name) ? $this->body : null;
            }
        };

        $captured = null;
        $response = $this->createStub(Response::class);
        $response->method('setContent')->willReturnCallback(
            function ($content) use (&$captured, $response) {
                $captured = $content;
                return $response;
            }
        );
        $response->method('setStatusCode')->willReturn($response);

        $controller = (new ReflectionClass(SupportController::class))->newInstanceWithoutConstructor();
        foreach (['request' => $request, 'response' => $response] as $name => $value) {
            (new ReflectionProperty(SupportController::class, $name))->setValue($controller, $value);
        }

        $controller->checkDomain(
            $entity,
            $limiter ?: new AttemptLimiter($this->dir, 10, 300),
            $this->createStub(LoggerInterface::class)
        );

        return json_decode((string)$captured, true);
    }

    private function entity(array $info)
    {
        $entity = $this->createMock(SupportInfoEntity::class);
        $entity->method('getInfo')->willReturn((object)($info + [
            'temp_key' => null,
            'temp_time' => null,
            'public_key' => null,
            'private_key' => null,
            'new_messages' => 0,
            'balance' => 0,
        ]));

        return $entity;
    }
}
