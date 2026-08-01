<?php

namespace Modules\OkayCMS\NovaposhtaCost;

use PHPUnit\Framework\TestCase;

/**
 * Невдалий розрахунок доставки не показував нічого - напис "Вычисляем..."
 * лишався в кошику назавжди. Повідомлення про помилку тримається на трьох
 * незалежних місцях, і розʼїхатись вони можуть мовчки.
 */
class NPCalcErrorMessageTest extends TestCase
{
    private const KEY = 'np_cart_calculate_error';

    private const MODULE = __DIR__ . '/../../../../Okay/Modules/OkayCMS/NovaposhtaCost';

    /**
     * @dataProvider languageProvider
     */
    public function testEveryLanguageDefinesTheMessage(string $language): void
    {
        $lang = [];
        require self::MODULE . '/design/lang/' . $language . '.php';

        $this->assertArrayHasKey(self::KEY, $lang);
        $this->assertNotSame('', trim($lang[self::KEY]));
    }

    public function languageProvider(): array
    {
        return ['ua' => ['ua'], 'ru' => ['ru'], 'en' => ['en']];
    }

    public function testExtenderExportsTheMessageToJs(): void
    {
        $this->assertStringContainsString(
            "assignJsVar('" . self::KEY . "'",
            file_get_contents(self::MODULE . '/Extenders/FrontExtender.php')
        );
    }

    public function testCartScriptReportsAFailedCalculation(): void
    {
        $js = file_get_contents(self::MODULE . '/design/js/np.js');

        $this->assertStringContainsString('okay.' . self::KEY, $js, 'np.js має читати експортований рядок');
        $this->assertMatchesRegularExpression(
            '~\berror:\s*function~',
            $js,
            'у запиті розрахунку має бути гілка error, інакше збій мережі лишає "Вычисляем..."'
        );
    }
}
