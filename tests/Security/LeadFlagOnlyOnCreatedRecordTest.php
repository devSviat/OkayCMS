<?php

namespace Security;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Notify;
use Okay\Core\Request;
use Okay\Core\Security\FormToken;
use Okay\Entities\CallbacksEntity;
use Okay\Helpers\CommonHelper;
use Okay\Helpers\ValidateHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Відсіяний повтор заявки свідомо показує те саме «прийнято», що й перша
 * відправка, тож call_sent / message_sent стоять і без нового рядка. Лічильник
 * звернень (подія generate_lead у темі, CRM) на них рахував би лід, якого в
 * базі немає. Для нього є окремий прапорець — лише на створений запис.
 */
class LeadFlagOnlyOnCreatedRecordTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testRepeatedCallbackIsSentButNotCreated(): void
    {
        $token = FormToken::get(CommonHelper::CALLBACK_FORM);
        $_POST['form_token'] = $token;

        $adds = 0;
        $first = $this->processCallback($adds);
        $repeat = $this->processCallback($adds);

        self::assertSame(1, $adds, 'повтор не мав створювати рядок');
        self::assertTrue($first['call_sent'] ?? false);
        self::assertTrue($first['call_created'] ?? false);
        self::assertTrue($repeat['call_sent'] ?? false, 'повтор, як і раніше, бачить «прийнято»');
        self::assertArrayNotHasKey('call_created', $repeat);
    }

    /** Без цього прапорець губився б на редиректі Post-Redirect-Get. */
    public function testCreatedFlagSurvivesTheRedirect(): void
    {
        self::assertContains('call_created', CommonHelper::FLASH_FLAGS);
    }

    public function testFeedbackFlashesCreatedOnlyAfterTheRecordIsWritten(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/Okay/Controllers/FeedbackController.php');

        self::assertStringContainsString("'message_created' => \$created", $source);

        $add = strpos($source, '$feedbacksEntity->add($feedback)');
        $set = strpos($source, '$created = true;');
        self::assertNotFalse($add);
        self::assertNotFalse($set);
        self::assertGreaterThan($add, $set, '$created стає true раніше, ніж запис створено');
        self::assertSame(1, substr_count($source, '$created = true;'));
    }

    /** @return array<string, mixed> змінні, які отримав шаблон */
    private function processCallback(int &$adds): array
    {
        $assigned = [];

        $design = $this->createStub(Design::class);
        $design->method('assign')->willReturnCallback(function ($var, $value) use (&$assigned) {
            $assigned[$var] = $value;
        });

        $callbacks = $this->createStub(CallbacksEntity::class);
        $callbacks->method('add')->willReturnCallback(function () use (&$adds) {
            return ++$adds;
        });
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($callbacks);

        $helper = (new ReflectionClass(CommonHelper::class))->newInstanceWithoutConstructor();
        foreach ([
            'validateHelper' => $this->createStub(ValidateHelper::class),
            'notify' => $this->createStub(Notify::class),
            'design' => $design,
            'entityFactory' => $factory,
            'request' => (new ReflectionClass(Request::class))->newInstanceWithoutConstructor(),
        ] as $property => $value) {
            (new ReflectionProperty($helper, $property))->setValue($helper, $value);
        }

        $callback = (object) ['name' => 'Тест', 'phone' => '+380000000000', 'message' => '', 'url' => '/'];
        (new ReflectionMethod($helper, 'processCallback'))->invoke($helper, $callback);

        return $assigned;
    }
}
