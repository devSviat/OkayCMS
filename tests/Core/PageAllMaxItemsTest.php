<?php

namespace Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Єдине місце, де вирішується, скільки записів віддає page-all.
 *
 * Логіку легко повторити в контролері чи шаблоні — і саме так уже виникало
 * розходження: порівняння рядка з нулем у PHP і в Smarty дає різний результат,
 * тож кнопка «всі» показувалась там, де page-all уже вимкнено.
 */
class PageAllMaxItemsTest extends TestCase
{
    public static function storedValues(): array
    {
        return [
            'не задане'        => [null, PAGE_ALL_MAX_ITEMS],
            'порожній рядок'   => ['', PAGE_ALL_MAX_ITEMS],
            'нуль рядком'      => ['0', PAGE_ALL_OFF],
            'нуль числом'      => [0, PAGE_ALL_OFF],
            'число рядком'     => ['500', 500],
            'число'            => [500, 500],
            'відʼємне'         => ['-5', PAGE_ALL_MAX_ITEMS],
            'нечислове'        => ['abc', PAGE_ALL_MAX_ITEMS],
            'поза переліком'   => ['750', PAGE_ALL_MAX_ITEMS],
            'захмарне'         => ['999999', PAGE_ALL_MAX_ITEMS],
        ];
    }

    /**
     * Білий список тримає саме функція, а не форма: налаштування може прийти
     * міграцією, модулем або прямим SQL, і тоді довільне число повертає ту саму
     * нестачу пам'яті, заради якої стеля існує.
     */
    #[DataProvider('storedValues')]
    public function testResultIsAlwaysAnOfferedChoice($stored, int $ignored): void
    {
        $this->assertContains(okay_page_all_max_items($stored), PAGE_ALL_ALLOWED_ITEMS);
    }

    #[DataProvider('storedValues')]
    public function testResolvesStoredValue($stored, int $expected): void
    {
        $this->assertSame($expected, okay_page_all_max_items($stored));
    }

    /**
     * Порожній рядок мусить означати «не задане»: так само його трактує
     * модифікатор default у Smarty, і на цьому тримається згода між PHP і
     * шаблоном.
     */
    public function testEmptyStringMatchesSmartyDefaultSemantics(): void
    {
        $this->assertSame(
            okay_page_all_max_items(null),
            okay_page_all_max_items(''),
            'порожній рядок і не задане розійшлись — шаблон і PHP покажуть різне'
        );
    }

    public function testNeverReturnsNegative(): void
    {
        foreach (['-1', '-1000', -7] as $stored) {
            $this->assertGreaterThanOrEqual(PAGE_ALL_OFF, okay_page_all_max_items($stored));
        }
    }

    /**
     * Вимкнути page-all має право лише збережений нуль. Зіпсоване значення, що
     * приводиться до нуля, вимикало б функцію мовчки й невідворотно.
     */
    public function testOnlyAStoredZeroTurnsPageAllOff(): void
    {
        $this->assertSame(PAGE_ALL_OFF, okay_page_all_max_items('0'));

        foreach (['abc', '-5', '', null, [], false] as $stored) {
            $this->assertNotSame(
                PAGE_ALL_OFF,
                okay_page_all_max_items($stored),
                'сміття у налаштуванні вимкнуло page-all'
            );
        }
    }
}
