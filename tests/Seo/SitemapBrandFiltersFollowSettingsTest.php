<?php

namespace Seo;

use Okay\Core\EntityFactory;
use Okay\Core\Response;
use Okay\Helpers\CanonicalHelper;
use Okay\Helpers\FilterHelper;
use Okay\Helpers\MainHelper;
use Okay\Helpers\MetaRobotsHelper;
use Okay\Helpers\SiteMapHelper;
use Okay\Core\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Бренд-фільтр потрапляє в мапу лише тоді, коли налаштування каталогу роблять
 * його самостійною сторінкою. Помилка тут нічого видимого не ламає: мапа
 * віддається успішно, а в Search Console осідають «Submitted URL marked
 * noindex» або «Alternate page with proper canonical tag» — по одному запису
 * на кожну зайву адресу.
 */
class SitemapBrandFiltersFollowSettingsTest extends TestCase
{
    private $argv;

    /**
     * За замовчуванням canonical_category_brand склеює фільтр із категорією.
     * Перевірка стоїть перед першим запитом, тож такому магазину процедура не
     * коштує навіть звернення до бази.
     */
    public function testWritesNothingWhenCanonicalPointsAtTheCategory(): void
    {
        $entityFactory = $this->createMock(EntityFactory::class);
        $entityFactory->expects($this->never())->method('get');

        $helper = $this->makeHelper(
            $entityFactory,
            $this->createStub(FilterHelper::class),
            ROBOTS_INDEX_FOLLOW,
            ['brand' => null, 'sort' => null]
        );

        $helper->writeCategoryBrandsProcedure();
    }

    public function testWritesNothingWhenBrandFiltersAreNoindex(): void
    {
        $entityFactory = $this->createMock(EntityFactory::class);
        $entityFactory->expects($this->never())->method('get');

        $helper = $this->makeHelper(
            $entityFactory,
            $this->createStub(FilterHelper::class),
            ROBOTS_NOINDEX_FOLLOW,
            []
        );

        $helper->writeCategoryBrandsProcedure();
    }

    /**
     * Прихований предок ховає всю гілку. Якщо дивитись лише на visible самої
     * категорії, у мапу поїдуть адреси, які віддають 404.
     */
    public function testSkipsCategoriesHiddenByAnAncestor(): void
    {
        $categoriesEntity = $this->createStub(\Okay\Entities\CategoriesEntity::class);
        $categoriesEntity->method('find')->willReturn([
            $this->category('parts', 0),
            $this->category('spares', 1),
        ]);

        $entityFactory = $this->createStub(EntityFactory::class);
        $entityFactory->method('get')->willReturn($categoriesEntity);

        $filterHelper = $this->createMock(FilterHelper::class);
        $filterHelper->method('prepareFilterGetBrands')->willReturn([]);
        // Видима категорія лишилась одна, тож і звернення по бренди одне.
        $filterHelper->expects($this->once())->method('getBrands')->willReturn([]);

        $helper = $this->makeHelper($entityFactory, $filterHelper, ROBOTS_INDEX_FOLLOW, []);

        $helper->writeCategoryBrandsProcedure();
    }

    /**
     * Процедура без виклику з контролера мертва, і помітити це можна лише
     * порахувавши адреси в готовій мапі.
     */
    public function testControllerCallsTheProcedure(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Controllers/SiteMapController.php');

        $this->assertStringContainsString('writeCategoryBrandsProcedure()', $source);
    }

    /**
     * Конструктор читає аргументи CLI, а під PHPUnit там аргументи самого
     * ранера. Підставляємо виклик у тій формі, у якій мапу генерує cron.
     */
    protected function setUp(): void
    {
        $this->argv = $GLOBALS['argv'] ?? null;
        $GLOBALS['argv'] = ['ok', 'root_url=https://example.com'];
    }

    protected function tearDown(): void
    {
        $GLOBALS['argv'] = $this->argv;
    }

    private function makeHelper(
        $entityFactory,
        $filterHelper,
        int $robots,
        $canonical
    ): SiteMapHelper {
        $metaRobotsHelper = $this->createStub(MetaRobotsHelper::class);
        $metaRobotsHelper->method('getCatalogRobots')->willReturn($robots);

        $canonicalHelper = $this->createStub(CanonicalHelper::class);
        $canonicalHelper->method('getCatalogCanonicalData')->willReturn($canonical);

        $language = new \stdClass();
        $language->label = 'ua';

        $mainHelper = $this->createStub(MainHelper::class);
        $mainHelper->method('getCurrentLanguage')->willReturn($language);

        return new SiteMapHelper(
            $entityFactory,
            $this->createStub(Response::class),
            $mainHelper,
            $this->createStub(Settings::class),
            $filterHelper,
            $metaRobotsHelper,
            $canonicalHelper
        );
    }

    private function category(string $url, int $visible): \stdClass
    {
        $category = new \stdClass();
        $category->id       = 1;
        $category->url      = $url;
        $category->visible  = $visible;
        $category->children = [1];
        $category->path     = [$category];

        return $category;
    }
}
