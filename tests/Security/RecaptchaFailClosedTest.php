<?php

namespace Security;

use Okay\Core\Recaptcha;
use Okay\Core\Request;
use Okay\Core\Settings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Капча має падати закрито: будь-яка відповідь, яку не вдалося прочитати як
 * успіх, - це відмова. Раніше помилка в ключі беззвучно вимикала капчу на
 * всьому сайті, бо гілка invalid-input-secret повертала true.
 *
 * Тести викликають Recaptcha::check() по-справжньому. Звернення до Google
 * підмінене: request() у класі оголошений protected саме для цього, тож
 * перевіряється рішення, а не форма гілок у вихідному коді.
 */
class RecaptchaFailClosedTest extends TestCase
{
    #[DataProvider('apiResponseProvider')]
    public function testCheckDecision($expected, $response, $why)
    {
        $this->assertSame($expected, $this->check($response), $why);
    }

    public static function apiResponseProvider()
    {
        return [
            'успіх' => [
                true, ['success' => true], 'валідна відповідь має пропускати',
            ],
            'явна відмова' => [
                false, ['success' => false], 'success=false - це відмова',
            ],
            'ключа success немає' => [
                false, ['foo' => 'bar'],
                'відповідь без success не можна читати як успіх',
            ],
            'success порожній рядок' => [
                false, ['success' => ''], 'порожнє значення - не успіх',
            ],
            'відповідь не масив' => [
                false, null,
                'нечитабельна відповідь API (мережа лягла, JSON битий) - це відмова',
            ],
            'відповідь рядок' => [
                false, 'Service Unavailable', 'HTML замість JSON - це відмова',
            ],
            'битий секретний ключ' => [
                false, ['success' => true, 'error-codes' => ['invalid-input-secret']],
                'помилка в ключі не має вимикати капчу для всіх',
            ],
            'битий ключ не першим у списку' => [
                false,
                ['success' => true, 'error-codes' => ['timeout-or-duplicate', 'invalid-input-secret']],
                'API повертає масив кодів, тож дивитись треба на весь, а не на перший',
            ],
        ];
    }

    /**
     * Мовчазна відмова капчі непомітна ззовні: форма просто перестає
     * приймати людей. Тому неправильний ключ має лишати слід у лозі.
     */
    public function testMisconfigurationIsLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('secret'));

        $recaptcha = $this->recaptcha('v2', $logger);
        $recaptcha->stubbedResponse = ['success' => true, 'error-codes' => ['invalid-input-secret']];

        $this->assertFalse($recaptcha->check());
    }

    public function testUnreadableResponseIsLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $recaptcha = $this->recaptcha('v2', $logger);
        $recaptcha->stubbedResponse = null;

        $this->assertFalse($recaptcha->check());
    }

    /**
     * У v3 успіх - ще не пропуск: рішення ухвалює поріг "людяності".
     */
    #[DataProvider('v3ScoreProvider')]
    public function testV3AppliesTheScoreThreshold($expected, $score, $why)
    {
        $recaptcha = $this->recaptcha('v3');
        $recaptcha->stubbedResponse = [
            'success' => true,
            'action'  => 'cart',
            'score'   => $score,
        ];

        $this->assertSame($expected, $recaptcha->check(), $why);
    }

    public static function v3ScoreProvider()
    {
        // Поріг для 'cart' у стабі налаштувань - 0.5
        return [
            'нижче порога'  => [false, 0.2, 'бал нижчий за поріг має відсіюватись'],
            'рівно поріг'   => [true,  0.5, 'рівність порогу пропускає'],
            'вище порога'   => [true,  0.9, 'бал вищий за поріг пропускає'],
        ];
    }

    private function check($response)
    {
        $recaptcha = $this->recaptcha('v2');
        $recaptcha->stubbedResponse = $response;

        return $recaptcha->check();
    }

    private function recaptcha($captchaType, ?LoggerInterface $logger = null)
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('__get')->willReturnCallback(static function ($name) use ($captchaType) {
            $values = [
                'captcha_type'             => $captchaType,
                'secret_recaptcha'         => 'test-secret',
                'secret_recaptcha_v3'      => 'test-secret',
                'secret_recaptcha_invisible' => 'test-secret',
                'recaptcha_scores'         => ['cart' => 0.5, 'product' => 0.5, 'other' => 0.5],
            ];

            return $values[$name] ?? null;
        });

        // Request конструюється без аргументів і читає лише суперглобали;
        // сам він тут не задіяний - його використовує тільки request(),
        // який підмінено.
        return new class ($settings, new Request(), $logger ?? new NullLogger()) extends Recaptcha {
            public $stubbedResponse = null;

            protected function request()
            {
                return $this->stubbedResponse;
            }
        };
    }
}
