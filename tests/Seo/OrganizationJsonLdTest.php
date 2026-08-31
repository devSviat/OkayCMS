<?php

namespace Seo;

use Okay\Admin\Helpers\BackendSettingsHelper;
use Okay\Helpers\MainHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Design\TemplateTagInventory;
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
    /**
     * Рендеримо ту саму конструкцію, що стоїть у head.tpl, справжнім Smarty з
     * набором тегів проєкту: перевіряти самі значення замало - половина фіксу
     * живе в розмітці, і повернення до |escape тестом на значеннях не ловиться.
     */
    private function renderJsonLdValue($value): string
    {
        require_once __DIR__ . '/../Design/TemplateTagInventory.php';

        $compileDir = sys_get_temp_dir() . '/okaycms-jsonld-' . getmypid();
        $smarty = TemplateTagInventory::createSmarty([sys_get_temp_dir()], $compileDir);
        $smarty->assign('v', $value);

        return $smarty->fetch('string:{$v|json_encode:JSON_INVALID_UTF8_SUBSTITUTE}');
    }

    /** @return string[] */
    private function parse(?string $raw): array
    {
        $helper = (new ReflectionClass(BackendSettingsHelper::class))->newInstanceWithoutConstructor();

        return $helper->parseTextareaLines($raw);
    }

    public static function hostileMarkupValues(): array
    {
        return [
            'лапки'          => ['Крамниця "Ромашка"'],
            'апостроф'       => ["Roma's Store"],
            'зворотний слеш' => ['Кабель \\ 5м'],
            'закриття тега'  => ['Ромашка </script><script>alert(1)</script>'],
            'перенос рядка'  => ["Ромашка\r\nStore"],
            'битий UTF-8'    => ["Shop\xC3\x28"],
            'кирилиця'       => ['Запчастини «Ромашка»'],
        ];
    }

    /**
     * Значення в розмітці мусить лишатись валідним JSON за будь-якого вмісту -
     * саме цього не давали ані відсутнє екранування, ані |escape.
     */
    #[DataProvider('hostileMarkupValues')]
    public function testRenderedValueIsAlwaysValidJson(string $value): void
    {
        $rendered = $this->renderJsonLdValue($value);

        $this->assertIsArray(
            json_decode('{"name":' . $rendered . '}', true),
            'блок JSON-LD став невалідним на значенні ' . $value
        );
        $this->assertStringNotContainsStringIgnoringCase(
            '</script',
            $rendered,
            'значення закриває тег <script> зсередини'
        );
    }

    /**
     * Теми шукаються на диску, а не перелічуються: цей же тест їде у форк, де
     * теми звуться інакше, і жорсткий список ловив би лише наші.
     */
    public static function headTemplates(): array
    {
        $found = [];
        foreach (glob(__DIR__ . '/../../design/*/html/head.tpl') ?: [] as $path) {
            $found[basename(dirname(dirname($path)))] = [$path];
        }

        return $found;
    }

    /**
     * Попередній тест доводить, що json_encode дає валідний JSON; цей - що
     * шаблон ним і користується. Без другого відкат до |escape лишився б
     * зеленим: htmlspecialchars - екранування для HTML, а не для JSON.
     */
    #[DataProvider('headTemplates')]
    public function testHeadTemplateEncodesJsonLdValues(string $path): void
    {
        $source = file_get_contents($path);
        preg_match_all('~application/ld\+json.*?</script>~s', $source, $blocks);

        $this->assertNotEmpty($blocks[0], 'у шаблоні не лишилось блоків JSON-LD');

        foreach ($blocks[0] as $block) {
            $this->assertStringNotContainsString(
                '|escape}',
                $block,
                'у JSON-LD повернулось HTML-екранування'
            );
            $this->assertStringContainsString(
                '|json_encode',
                $block,
                'значення JSON-LD підставляються без json_encode'
            );
        }
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
