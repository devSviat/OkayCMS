<?php

namespace Seo;

use Okay\Core\Settings;
use Okay\Helpers\CanonicalHelper;
use Okay\Helpers\MetaRobotsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Вимкнений page-all віддає звичайну першу сторінку, тож окремою адресою він
 * більше не є. Канонікал і robots мусять бачити її саме такою: інакше вони
 * беруться з налаштувань для page-all, і дубль першої сторінки або склеює з
 * собою всю пагінацію, або стає окремою індексованою адресою.
 *
 * Рішення живе в самих хелперах, а не в збереженні форми: значення може
 * прийти міграцією, модулем чи прямим SQL, і тоді виправляти вже нічого.
 */
class PageAllOffCanonicalTest extends TestCase
{
    protected function setUp(): void
    {
        require_once 'Okay/Core/config/constants.php';
    }

    private function stubSettings($pageAllMaxItems): Settings
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(
            static function ($param) use ($pageAllMaxItems) {
                return $param === 'catalog_page_all_max_items' ? $pageAllMaxItems : null;
            }
        );

        return $settings;
    }

    private function canonical(
        $pageAllMaxItems,
        int $catalogPageAll,
        int $catalogPagination = CANONICAL_FIRST_PAGE,
        int $catalogFilterPagination = CANONICAL_WITHOUT_FILTER_FIRST_PAGE
    ): CanonicalHelper {
        $helper = new CanonicalHelper($this->stubSettings($pageAllMaxItems));
        $helper->setParams(
            $catalogPagination,
            $catalogPageAll,
            CANONICAL_WITHOUT_FILTER,
            CANONICAL_WITHOUT_FILTER,
            CANONICAL_WITHOUT_FILTER,
            $catalogFilterPagination,
            2,
            2,
            2,
            2,
            2
        );

        return $helper;
    }

    private function robots($pageAllMaxItems, int $catalogPageAll): MetaRobotsHelper
    {
        $helper = new MetaRobotsHelper($this->stubSettings($pageAllMaxItems));
        $helper->setParams(
            ROBOTS_INDEX_FOLLOW,
            $catalogPageAll,
            ROBOTS_INDEX_FOLLOW,
            ROBOTS_INDEX_FOLLOW,
            ROBOTS_INDEX_FOLLOW,
            ROBOTS_INDEX_FOLLOW,
            2,
            2,
            2,
            2,
            2
        );

        return $helper;
    }

    /**
     * Самопосилання — найгірший випадок: дубль першої сторінки отримав би
     * власний канонікал і лишився б окремою адресою в індексі.
     */
    public function testSelfCanonicalIsNotIssuedWhenPageAllIsOff(): void
    {
        $result = $this->canonical(PAGE_ALL_OFF, CANONICAL_CURRENT_PAGE)
            ->getCatalogCanonicalData('all', [], [], []);

        // Саме assertArrayHasKey, а не '?? null': відсутній ключ означає «сторінку
        // не чіпаємо», і в канонікал піде поточна адреса разом із page-all.
        // Перевірено живим запитом - на цьому місці тест уже раз пропустив
        // самопосилання.
        $this->assertArrayHasKey('page', $result, 'ключ не виставлено — канонікал лишить адресу page-all');
        $this->assertNull($result['page'], 'дубль першої сторінки канонікалиться сам на себе');
    }

    /**
     * Дзеркало попереднього: поки page-all працює, це справді окрема адреса,
     * і налаштування має діяти як раніше.
     */
    public function testSelfCanonicalStillWorksWhilePageAllIsOn(): void
    {
        $result = $this->canonical(PAGE_ALL_MAX_ITEMS, CANONICAL_CURRENT_PAGE)
            ->getCatalogCanonicalData('all', [], [], []);

        $this->assertSame('all', $result['page']);
    }

    /**
     * «Канонікал відсутній» при вимкненому page-all лишив би дубль першої
     * сторінки взагалі без указівки на оригінал.
     */
    public function testAbsentCanonicalDoesNotLeakWhenPageAllIsOff(): void
    {
        $result = $this->canonical(PAGE_ALL_OFF, CANONICAL_ABSENT)->getCatalogCanonicalData('all', [], [], []);

        $this->assertNotFalse($result, 'дубль першої сторінки лишився без канонікала');
        $this->assertArrayHasKey('page', $result);
        $this->assertNull($result['page'], 'канонікал указує на саму адресу page-all');

        $this->assertFalse(
            $this->canonical(PAGE_ALL_MAX_ITEMS, CANONICAL_ABSENT)->getCatalogCanonicalData('all', [], [], []),
            'при робочому page-all налаштування має діяти як раніше'
        );
    }

    /**
     * Дзеркальна шкода з боку robots: noindex на адресі, яка тепер віддає
     * звичайну першу сторінку, закрив би від індексації саме її.
     */
    public function testPageAllRobotsDoNotApplyWhenPageAllIsOff(): void
    {
        $this->assertSame(
            ROBOTS_INDEX_FOLLOW,
            $this->robots(PAGE_ALL_OFF, ROBOTS_NOINDEX_NOFOLLOW)->getCatalogRobots('all', [], [], []),
            'перша сторінка закрита від індексації налаштуванням для page-all'
        );

        $this->assertSame(
            ROBOTS_NOINDEX_NOFOLLOW,
            $this->robots(PAGE_ALL_MAX_ITEMS, ROBOTS_NOINDEX_NOFOLLOW)->getCatalogRobots('all', [], [], []),
            'при робочому page-all налаштування має діяти як раніше'
        );
    }

    /**
     * Зіпсоване чи незадане значення не має вимикати page-all — тоді й гейт
     * спрацьовувати не повинен. Нуль сюди свідомо не входить.
     */
    #[DataProvider('storedValuesThatKeepPageAllOn')]
    public function testGateFiresOnlyOnADeliberateZero($stored): void
    {
        $result = $this->canonical($stored, CANONICAL_CURRENT_PAGE)
            ->getCatalogCanonicalData('all', [], [], []);

        $this->assertSame('all', $result['page'], 'гейт спрацював на значенні, яке page-all не вимикає');
    }

    public static function storedValuesThatKeepPageAllOn(): array
    {
        return [
            'не задане'      => [null],
            'порожній рядок' => [''],
            'нечислове'      => ['abc'],
            'поза переліком' => ['750'],
            'число рядком'   => ['500'],
        ];
    }

    /**
     * Найсильніша перевірка контракту: за вимкненого page-all його адреса має
     * давати той самий канонікал, що й звичайна перша сторінка, - у будь-якій
     * комбінації налаштувань канонікалів і фільтрів. Точкові тести цього не
     * ловлять: діру дало налаштування пагінації фільтра, до якого гілка page-all
     * навіть не доходить.
     */
    #[DataProvider('canonicalSettingsMatrix')]
    public function testPageAllMatchesTheFirstPageInEverySettingsCombination(
        int $catalogPagination,
        int $catalogPageAll,
        int $catalogFilterPagination,
        array $otherFilter,
        array $brandsFilter
    ): void {
        $helper = $this->canonical(PAGE_ALL_OFF, $catalogPageAll, $catalogPagination, $catalogFilterPagination);

        $this->assertSame(
            $this->comparable($helper->getCatalogCanonicalData('', $otherFilter, [], $brandsFilter)),
            $this->comparable($helper->getCatalogCanonicalData('all', $otherFilter, [], $brandsFilter)),
            'адреса page-all канонікалиться інакше, ніж перша сторінка з тим самим фільтром'
        );
    }

    public static function canonicalSettingsMatrix(): iterable
    {
        require_once 'Okay/Core/config/constants.php';

        $filters = [
            'без фільтра'  => [[], []],
            'other_filter' => [['discounted'], []],
            'brand'        => [[], [7]],
        ];

        foreach ([CANONICAL_FIRST_PAGE, CANONICAL_CURRENT_PAGE, CANONICAL_PAGE_ALL, CANONICAL_ABSENT] as $pagination) {
            foreach ([CANONICAL_FIRST_PAGE, CANONICAL_CURRENT_PAGE, CANONICAL_ABSENT] as $pageAll) {
                $filterPaginations = [
                    CANONICAL_WITHOUT_FILTER_FIRST_PAGE,
                    CANONICAL_FIRST_PAGE,
                    CANONICAL_CURRENT_PAGE,
                    CANONICAL_ABSENT,
                ];
                foreach ($filterPaginations as $filterPagination) {
                    foreach ($filters as $name => [$otherFilter, $brandsFilter]) {
                        $key = sprintf('%d/%d/%d %s', $pagination, $pageAll, $filterPagination, $name);
                        yield $key => [$pagination, $pageAll, $filterPagination, $otherFilter, $brandsFilter];
                    }
                }
            }
        }
    }

    /**
     * Відсутній ключ `page` і `page => null` дають ту саму адресу, тож для
     * порівняння їх треба звести до спільного вигляду.
     *
     * @param array|false $result
     * @return array|false
     */
    private function comparable($result)
    {
        if ($result === false) {
            return false;
        }

        $result['page'] = $result['page'] ?? null;
        ksort($result);

        return $result;
    }

    /**
     * «Канонікал пагінації → сторінка з усіма товарами» при вимкненому page-all
     * зібрав би всю пагінацію на адресу, яка тепер дублює першу сторінку.
     */
    public function testPaginationGluedToPageAllFallsBackToTheFirstPage(): void
    {
        $result = $this->canonical(PAGE_ALL_OFF, CANONICAL_FIRST_PAGE, CANONICAL_PAGE_ALL)
            ->getCatalogCanonicalData('2', [], [], []);

        $this->assertArrayHasKey('page', $result);
        $this->assertNull($result['page'], 'пагінація склеєна на адресу, якої більше немає');

        $this->assertSame(
            'all',
            $this->canonical(PAGE_ALL_MAX_ITEMS, CANONICAL_FIRST_PAGE, CANONICAL_PAGE_ALL)
                ->getCatalogCanonicalData('2', [], [], [])['page'],
            'при робочому page-all налаштування має діяти як раніше'
        );
    }

    /**
     * Гілка page-all у канонікалі спрацьовує лише без фільтра: з фільтром
     * рішення ухвалює налаштування пагінації фільтра, і воно так само вміє
     * лишити поточну адресу.
     */
    public function testFilteredPageAllDoesNotSelfCanonical(): void
    {
        $result = $this->canonical(PAGE_ALL_OFF, CANONICAL_CURRENT_PAGE, CANONICAL_FIRST_PAGE, CANONICAL_CURRENT_PAGE)
            ->getCatalogCanonicalData('all', ['discounted'], [], []);

        $this->assertArrayHasKey('page', $result);
        $this->assertNull($result['page'], 'дубль відфільтрованої першої сторінки канонікалиться сам на себе');
    }
}
