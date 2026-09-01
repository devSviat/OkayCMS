<?php

namespace Modules\OkayCMS\NovaposhtaCost;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Поле міста мусить нести ref зі списку НП: validation.js вимагає лише
 * непорожній текст, тож «Львв» проходило перевірку з порожнім
 * novaposhta_delivery_city_id.
 *
 * Обробник focusout, який це закриває, працює в парі з відновленням стану
 * після вибору підказки — і саме тут дефект уже траплявся: правку внесли в
 * автокомпліт відділень і забули про адресну доставку. Поле лишалось червоним
 * після коректно вибраного міста. Тест тримає обидві гілки.
 *
 * Перевірка по вихідному коду навмисно: JS-оточення в наборі немає, а форма
 * дефекту — саме відсутній виклик в одній з двох гілок.
 */
class NpCityFieldScriptTest extends TestCase
{
    private function source(): string
    {
        $source = file_get_contents(
            dirname(__DIR__, 4) . '/Okay/Modules/OkayCMS/NovaposhtaCost/design/js/np.js'
        );
        $this->assertIsString($source);

        return $source;
    }

    /** Обидві доставки — до відділення й адресна — мусять відновлювати стан поля. */
    #[DataProvider('cityInputProvider')]
    public function testBothCityFieldsRestoreTheirStateAfterAPick(string $inputClass): void
    {
        $this->assertStringContainsString(
            'npCityChosen(delivery_block.find("input.' . $inputClass . '"))',
            $this->source(),
            "Гілка {$inputClass} не відновлює стан поля: після вибору міста воно лишиться червоним"
        );
    }

    /** @return array<string, array{string}> */
    public static function cityInputProvider(): array
    {
        return [
            'до відділення'    => ['city_novaposhta'],
            'адресна доставка' => ['city_novaposhta_for_door'],
        ];
    }

    /** Обробник має покривати обидва поля, інакше одну доставку не перевіряють узагалі. */
    public function testTheFocusoutHandlerCoversBothCityFields(): void
    {
        $source = $this->source();

        $this->assertMatchesRegularExpression(
            '~input\.city_novaposhta,[^"]*input\.city_novaposhta_for_door~',
            $source
        );
    }

    /**
     * errorsFor() чекає DOM-елемент: із jQuery-обʼєкта idOrName() читає
     * .id || .name, отримує undefined і падає в escapeCssMeta(). Мітка помилки
     * при цьому лишалась на екрані під коректно заповненим полем.
     */
    public function testErrorsForNeverReceivesAJqueryObject(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '~errorsFor\(\s*(\$this|input)\s*\)~',
            $this->source(),
            'errorsFor() кинеться винятком: йому потрібен [0]'
        );
    }
}
