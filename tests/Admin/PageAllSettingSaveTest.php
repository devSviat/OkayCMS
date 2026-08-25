<?php

namespace Admin;

use Okay\Core\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Нуль тут означає «не показувати всі», тобто легальний вибір - а не
 * відсутність значення. Обидва звичні способи прочитати поле його псують:
 * дефолт у post() підставляється через empty(), а відсутнє поле дає той самий
 * нуль, що й свідомий вибір.
 */
class PageAllSettingSaveTest extends TestCase
{
    private const CONTROLLER = 'backend/Controllers/SettingsIndexingAdmin.php';

    private function request(): Request
    {
        // post() читає лише $_POST і не торкається стану обʼєкта.
        return (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    /**
     * Сама пастка: дефолт зʼїдає нуль. Тест тримає її видимою - якщо PHP
     * колись змінить поведінку, ми дізнаємось звідси, а не з проду.
     */
    public function testDefaultSwallowsZero(): void
    {
        $_POST = ['x' => '0'];

        $this->assertSame(
            PAGE_ALL_MAX_ITEMS,
            $this->request()->post('x', 'int', PAGE_ALL_MAX_ITEMS),
            'дефолт більше не підставляється через empty() — коментар у контролері треба оновити'
        );
    }

    public function testWithoutDefaultZeroSurvives(): void
    {
        $_POST = ['x' => '0'];

        $this->assertSame(0, $this->request()->post('x', 'int'));
    }

    /**
     * Відсутнє поле має бути відрізнюваним від нуля, інакше будь-який POST у
     * цей контролер без нього тихо вимкнув би page-all.
     */
    public function testMissingFieldIsDistinguishableFromZero(): void
    {
        $_POST = [];

        $this->assertNull($this->request()->post('x'));
    }

    public function testControllerWritesOnlyWhenTheFieldCame(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::CONTROLLER);

        $this->assertMatchesRegularExpression(
            "~\\\$pageAllMaxItems\\s*=\\s*\\\$this->request->post\(\s*'catalog_page_all_max_items'\s*\)~",
            $source,
            'поле читається з типом або дефолтом — нуль і відсутність зіллються'
        );
        $this->assertMatchesRegularExpression(
            '~if\s*\(\s*\$pageAllMaxItems\s*!==\s*null\s*\)~',
            $source,
            'значення пишеться навіть коли поля не було — POST без нього вимкне page-all'
        );
    }

    /**
     * Канонікал на page-all при вимкненому page-all склеїв би сторінки 2..N із
     * першою: та адреса тепер віддає саме її. Комбінація обирається на тій
     * самій сторінці, тож зберегти її не має бути можливо.
     */
    public function testHarmfulCanonicalCombinationIsCorrectedOnSave(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::CONTROLLER);

        $this->assertMatchesRegularExpression(
            '~\$canonicalCatalogPagination\s*===\s*CANONICAL_PAGE_ALL\s*&&\s*\$pageAllIsOff~',
            $source,
            'шкідлива пара зберігається як є — глибокі сторінки склеяться з першою'
        );
        $this->assertMatchesRegularExpression(
            '~\$canonicalCatalogPagination\s*=\s*CANONICAL_FIRST_PAGE~',
            $source,
            'комбінація виявляється, але не виправляється'
        );
    }

    /**
     * Сусіднє налаштування каноніклу самої адреси page-all шкодить так само:
     * самопосилання зробило б із дубля першої сторінки окрему індексовану
     * адресу, а відсутній канонікал лишив би дубль без указівки на оригінал.
     */
    public function testPageAllCanonicalIsCorrectedWhenTheFeatureIsOff(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::CONTROLLER);

        $this->assertMatchesRegularExpression(
            '~if\s*\(\s*\$pageAllIsOff\s*\)\s*\{\s*'
            . '\$canonicalCatalogPageAll\s*=\s*CANONICAL_FIRST_PAGE~',
            $source,
            'канонікал адреси page-all лишається як є — вимкнений page-all дасть дубль першої сторінки'
        );
    }

    /**
     * Виправлення мусить дивитись на значення з цього ж POST. Інакше воно
     * мовчки залежить від того, що set() стоїть вище за get(): переставити два
     * блоки місцями - і шкідлива пара збережеться, а тести на текст лишаться
     * зеленими.
     */
    public function testCorrectionDoesNotDependOnStatementOrder(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::CONTROLLER);

        $this->assertMatchesRegularExpression(
            '~\$pageAllIsOff\s*=\s*okay_page_all_max_items\(\s*\$pageAllMaxItems\s*\?\?~',
            $source,
            'стан page-all перечитується з налаштувань — виправлення тримається на порядку рядків'
        );
    }

    public function testOnlyAllowedValuesAreStored(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::CONTROLLER);

        $this->assertStringContainsString(
            'PAGE_ALL_ALLOWED_ITEMS',
            $source,
            'без білого списку в налаштування потрапить будь-яке число, зокрема таке, що поверне вичерпання пам\'яті'
        );
    }
}
