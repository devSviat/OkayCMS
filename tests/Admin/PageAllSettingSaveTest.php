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
    private const SAVER = 'backend/Helpers/BackendSettingsHelper.php';

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

    public function testSaverWritesOnlyWhenTheFieldCame(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::SAVER);

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

    public function testOnlyAllowedValuesAreStored(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . self::SAVER);

        $this->assertStringContainsString(
            'PAGE_ALL_ALLOWED_ITEMS',
            $source,
            'без білого списку в налаштування потрапить будь-яке число, зокрема таке, що поверне вичерпання пам\'яті'
        );
    }
}
