<?php

namespace Seo;

use Okay\Admin\Helpers\BackendSettingsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Посилання на соцмережі приходять із textarea, а браузер шле її вміст із CRLF.
 * Розділення по PHP_EOL лишало на кожному рядку хвостовий \r, і той потрапляв
 * усередину рядка JSON-LD Organization. Для JSON це керуючий символ, тобто весь
 * блок ставав невалідним і пошуковик відкидав його цілком - разом із назвою,
 * логотипом і sameAs, на кожній сторінці сайту.
 *
 * Помилка мовчазна: HTML-посилання на вітрині лишались робочими, а розмітка
 * ламалась лише в JSON, куди ніхто не дивиться очима.
 */
class OrganizationJsonLdTest extends TestCase
{
    /** @return string[] */
    private function parse(?string $raw): array
    {
        $helper = (new ReflectionClass(BackendSettingsHelper::class))->newInstanceWithoutConstructor();

        return $helper->parseTextareaLines($raw);
    }

    public static function lineEndingsProvider(): array
    {
        return [
            'CRLF, як шле браузер' => ["https://fb.com/a\r\nhttps://ig.com/b"],
            'LF'                   => ["https://fb.com/a\nhttps://ig.com/b"],
            'CR'                   => ["https://fb.com/a\rhttps://ig.com/b"],
        ];
    }

    #[DataProvider('lineEndingsProvider')]
    public function testEveryLineEndingYieldsCleanValues(string $raw): void
    {
        $this->assertSame(['https://fb.com/a', 'https://ig.com/b'], $this->parse($raw));
    }

    /**
     * Головне твердження: значення, що пішли в розмітку, дають валідний JSON.
     * Саме це й ламалось - решта перевірок лише пояснює, чому.
     */
    public function testParsedValuesProduceValidJsonLd(): void
    {
        $links = $this->parse("https://fb.com/a\r\nhttps://ig.com/b\r\n");

        $markup = '{"@type":"Organization","sameAs":["' . implode('","', $links) . '"]}';

        $this->assertIsArray(
            json_decode($markup, true),
            'JSON-LD Organization невалідний: ' . json_last_error_msg()
        );
    }

    /**
     * Показує саму ваду: неочищене значення дає рівно ту помилку, яку віддавав прод.
     */
    public function testRawValueWithCarriageReturnBreaksJson(): void
    {
        $markup = '{"sameAs":["https://fb.com/a' . "\r" . '"]}';

        $this->assertNull(json_decode($markup, true));
        $this->assertSame(JSON_ERROR_CTRL_CHAR, json_last_error());
    }

    public function testBlankLinesAreDropped(): void
    {
        $this->assertSame(['https://fb.com/a'], $this->parse("\r\n  \r\nhttps://fb.com/a\r\n\r\n"));
    }

    /**
     * Ключі мають бути наскрізними: раніше порожні рядки прибирались через
     * unset(), а діри в ключах перетворюють масив на обʼєкт при json_encode.
     */
    public function testKeysAreSequential(): void
    {
        $links = $this->parse("https://a\r\n\r\nhttps://b\r\n\r\nhttps://c");

        $this->assertSame([0, 1, 2], array_keys($links));
        $this->assertStringStartsWith('[', json_encode($links));
    }

    public function testEmptyInputGivesEmptyList(): void
    {
        $this->assertSame([], $this->parse(''));
        $this->assertSame([], $this->parse(null));
    }

    /**
     * У вже збережених налаштуваннях хвостовий \r лишився, тож фронт мусить
     * чистити значення й на читанні - інакше правка спрацює лише після того,
     * як хтось вручну перезбереже налаштування теми.
     *
     * Перевірка по джерелу свідома: цикл живе всередині
     * MainHelper::setDesignDataProcedure(), яка тягне за собою півядра.
     */
    public function testFrontNormalisesAlreadyStoredValues(): void
    {
        $source = file_get_contents(__DIR__ . '/../../Okay/Helpers/MainHelper.php');

        $this->assertMatchesRegularExpression(
            '~\$socialUrl\s*=\s*trim\(~',
            $source,
            'фронт віддає збережене значення як є — старий \r знову потрапить у JSON-LD'
        );
    }
}
