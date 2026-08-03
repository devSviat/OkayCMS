<?php

namespace Core;

use Okay\Core\Recaptcha;
use Okay\Core\Settings;
use Okay\Core\Validator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * isSafe() був чорним списком з чотирьох підрядків: "<script", "</script",
 * "<iframe", "</iframe". Все інше — обробники подій на будь-якому тезі —
 * проходило наскрізь.
 */
class ValidatorIsSafeTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new Validator(
            $this->createStub(Settings::class),
            $this->createStub(Recaptcha::class)
        );
    }

    #[DataProvider('markupProvider')]
    public function testMarkupIsRejected($value)
    {
        $this->assertFalse($this->validator->isSafe($value), $value);
    }

    public static function markupProvider()
    {
        return [
            'script'       => ['<script>alert(1)</script>'],
            'closing tag'  => ['</script>'],
            'iframe'       => ['<iframe src="//evil"></iframe>'],
            'img onerror'  => ['<img src=x onerror=alert(1)>'],
            'svg onload'   => ['<svg onload=alert(1)>'],
            'body bg'      => ['<body background="javascript:alert(1)">'],
            'a href'       => ['<a href="//evil">клік</a>'],
            'object'       => ['<object data="//evil"></object>'],
            'comment'      => ['<!-- прихований -->'],
            'php open'     => ['<?php echo 1; ?>'],
            'uppercase'    => ['<IMG SRC=x ONERROR=alert(1)>'],
            'after text'   => ['Іван <img src=x onerror=alert(1)>'],
        ];
    }

    /**
     * Свідомо не strip_tags-еквівалентність: вона ріже все після одинокого "<"
     * і тим самим забороняє звичайний текст на кшталт "розмір < 100".
     * Небезпечна саме форма тега — "<" одразу перед іменем.
     */
    #[DataProvider('plainTextProvider')]
    public function testPlainTextIsAccepted($value)
    {
        $this->assertTrue($this->validator->isSafe($value), $value);
    }

    public static function plainTextProvider()
    {
        return [
            'звичайний текст' => ['Іван Петренко'],
            'менше'           => ['розмір < 100 см'],
            'нерівність'      => ['5 < 10 > 3'],
            'стрілка'         => ['a <-> b'],
            'мейл'            => ['ivan@example.com'],
            'вже екрановане'  => ['&lt;script&gt;'],
        ];
    }

    public function testEmptyValueFollowsTheRequiredFlag()
    {
        $this->assertTrue($this->validator->isSafe('', false));
        $this->assertFalse($this->validator->isSafe('', true));
        $this->assertTrue($this->validator->isSafe(null, false));
        $this->assertFalse($this->validator->isSafe(null, true));
    }

    /**
     * isSafe() — спільна передумова для isEmail/isPhone/isName/isDomain,
     * тож розмітка має відсікатись і через них.
     */
    public function testCallersInheritTheGuard()
    {
        $payload = '<img src=x onerror=alert(1)>';

        $this->assertFalse($this->validator->isName($payload));
        $this->assertFalse($this->validator->isAddress($payload));
        $this->assertFalse($this->validator->isComment($payload));
        $this->assertFalse($this->validator->isEmail($payload . '@example.com'));
    }
}
