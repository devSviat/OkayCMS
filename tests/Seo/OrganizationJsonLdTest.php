<?php

namespace Seo;

use Okay\Admin\Helpers\BackendSettingsHelper;
use Okay\Helpers\MainHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Керуючий символ усередині рядка робить увесь блок JSON-LD невалідним, і
 * пошуковик відкидає його цілком - разом із назвою, логотипом і sameAs.
 *
 * Вада мовчазна: HTML-посилання на вітрині лишаються робочими, ламається лише
 * JSON, куди не дивляться очима.
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

    /**
     * Байт 0x85 - продовження кирилиці в UTF-8. Розділення регуляркою, яка
     * його матчить, рве посилання посеред літери й зберігає два побиті рядки.
     */
    public function testMultibyteUrlSurvivesSplitting(): void
    {
        $this->assertSame(
            ['https://facebook.com/хата', 'https://ig.com/b'],
            $this->parse("https://facebook.com/хата\r\nhttps://ig.com/b")
        );
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

    public function testBlankLinesAreDropped(): void
    {
        $this->assertSame(['https://fb.com/a'], $this->parse("\r\n  \r\nhttps://fb.com/a\r\n\r\n"));
    }

    /** Порожні рядки прибираються, а не лишають дірки в ключах. */
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
     * У вже збережених налаштуваннях хвостовий \r лишився, тож фронт чистить
     * значення й на читанні - інакше правка спрацює лише після того, як хтось
     * вручну перезбереже налаштування теми.
     */
    public function testFrontNormalisesAlreadyStoredValues(): void
    {
        $helper = (new ReflectionClass(MainHelper::class))->newInstanceWithoutConstructor();

        $socials = $helper->buildSocialLinks([
            "https://www.facebook.com/example/\r",
            '  https://www.instagram.com/example/  ',
            '',
        ]);

        $this->assertSame(
            ['https://www.facebook.com/example/', 'https://www.instagram.com/example/'],
            array_column($socials, 'url'),
            'фронт віддає збережене значення як є — старий \r знову потрапить у JSON-LD'
        );
    }
}
