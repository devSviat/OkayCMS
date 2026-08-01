<?php


namespace Core\SmartyPlugins;


use Okay\Core\SmartyPlugins\Plugins\FirstKey;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FirstKeyPluginTest extends TestCase
{
    #[DataProvider('firstKeyProvider')]
    public function testReturnsFirstKey($input, $expected): void
    {
        $this->assertSame($expected, (new FirstKey())->run($input));
    }

    public static function firstKeyProvider(): array
    {
        return [
            'рядковий ключ'      => [['preset_a' => 1, 'preset_b' => 2], 'preset_a'],
            'числовий ключ'      => [[5 => 'a', 9 => 'b'], 5],
            'список'             => [['a', 'b'], 0],
            'порожній масив'     => [[], null],
            'не масив'           => ['рядок', false],
            'null'               => [null, false],
        ];
    }

    /**
     * Модифікатор існує саме тому, що key() працює лише за посиланням. Копія має
     * лишити вихідний масив із незрушеним внутрішнім вказівником.
     */
    public function testDoesNotMoveTheCallersArrayPointer(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        next($array);

        $this->assertSame('a', (new FirstKey())->run($array));
        $this->assertSame('b', key($array));
    }
}
