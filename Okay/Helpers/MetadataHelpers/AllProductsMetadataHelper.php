<?php


namespace Okay\Helpers\MetadataHelpers;


use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Entities\PagesEntity;

class AllProductsMetadataHelper extends CommonMetadataHelper
{
    private $metaDelimiter = ', ';

    /** @var string */
    private $keyword;

    /** @var bool */
    private $isAllPages;

    /** @var int */
    private $currentPageNum;

    /** @var array */
    private $metaArray = [];

    /** @var int */
    private $metaRobots = ROBOTS_INDEX_FOLLOW;

    /** @var string|null */
    private $filterAutoMeta;

    public function setUp(
        $keyword = '',
        bool $isAllPages = false,
        int $currentPageNum = 1,
        array $metaArray = [],
        int $metaRobots = ROBOTS_INDEX_FOLLOW
    ): void {
        $this->keyword        = $keyword;
        $this->isAllPages     = $isAllPages;
        $this->currentPageNum = $currentPageNum;
        $this->metaArray      = $metaArray;
        $this->metaRobots     = $metaRobots;
    }

    public function __construct()
    {
        parent::__construct();
        
        if (!$this->keyword) {
            $entityFactory = $this->SL->getService(EntityFactory::class);
            /** @var PagesEntity $pagesEntity */
            $pagesEntity = $entityFactory->get(PagesEntity::class);
            $this->page = $pagesEntity->get('all-products');
        }
    }

    /**
     * Назви обраних фільтрів через кому, як це робить каталог категорії.
     * Без них кожна відфільтрована адреса віддає заголовок усього каталогу,
     * показуючи при цьому інший набір товарів.
     *
     * Уточнення йде лише в h1 і title: meta_description тут зв'язний текст,
     * і назва бренду в кінці речення його ламає.
     *
     * Закриті від індексації сторінки лишаються з базовим заголовком:
     * комбінацій фільтрів там надто багато, щоб кожній давати свій.
     */
    private function hasSelectedFilters(): bool
    {
        return !empty($this->metaArray['brand'])
            || !empty($this->metaArray['filter'])
            || !empty($this->metaArray['features_values']);
    }

    private function getFilterAutoMeta(): string
    {
        if ($this->filterAutoMeta !== null) {
            return $this->filterAutoMeta; // no ExtenderFacade
        }

        if ($this->metaRobots === ROBOTS_NOINDEX_FOLLOW || $this->metaRobots === ROBOTS_NOINDEX_NOFOLLOW) {
            return $this->filterAutoMeta = ''; // no ExtenderFacade
        }

        $parts = [];
        foreach (['brand', 'filter'] as $type) {
            if (!empty($this->metaArray[$type])) {
                $parts[] = implode($this->metaDelimiter, $this->metaArray[$type]);
            }
        }

        if (!empty($this->metaArray['features_values'])) {
            foreach ($this->metaArray['features_values'] as $featureValues) {
                $parts[] = implode($this->metaDelimiter, $featureValues);
            }
        }

        $this->filterAutoMeta = implode($this->metaDelimiter, $parts);

        return $this->filterAutoMeta = ExtenderFacade::execute(__METHOD__, $this->filterAutoMeta, func_get_args());
    }

    private function withFilterAutoMeta(string $template): string
    {
        $filterAutoMeta = $this->getFilterAutoMeta();

        return $filterAutoMeta === '' ? $template : trim($template . ' ' . $filterAutoMeta);
    }

    /**
     * @inheritDoc
     */
    public function getH1Template(): string
    {
        if ($this->keyword) {
            /** @var FrontTranslations $translations */
            $translations = $this->SL->getService(FrontTranslations::class);
            $h1 = $translations->getTranslation('general_search') . ' ' . $this->keyword;
        } else {
            $h1 = $this->withFilterAutoMeta(parent::getH1Template());
        }
        
        return ExtenderFacade::execute(__METHOD__, $h1, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getDescriptionTemplate(): string
    {
        if ($this->keyword) {
            /** @var FrontTranslations $translations */
            $translations = $this->SL->getService(FrontTranslations::class);
            $description = $translations->getTranslation('general_search') . ' ' . $this->keyword;
        } elseif ((int)$this->currentPageNum > 1 || $this->isAllPages === true || $this->hasSelectedFilters()) {
            // Текст написаний під увесь каталог, тож на похідних адресах він був
            // би тим самим описом під іншим набором товарів.
            $description = '';
        } else {
            $description = parent::getDescriptionTemplate();
        }

        return ExtenderFacade::execute(__METHOD__, $description, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getMetaTitleTemplate(): string
    {
        if ($this->keyword) {
            /** @var FrontTranslations $translations */
            $translations = $this->SL->getService(FrontTranslations::class);
            $metaTitle = $translations->getTranslation('general_search') . ' ' . $this->keyword;
        } else {
            $metaTitle = $this->withFilterAutoMeta(parent::getMetaTitleTemplate());
        }

        if ((int)$this->currentPageNum > 1 && $this->isAllPages !== true) {
            /** @var FrontTranslations $translations */
            $translations = $this->SL->getService(FrontTranslations::class);
            $metaTitle .= $translations->getTranslation('meta_page') . ' ' . $this->currentPageNum;
        }
        
        return ExtenderFacade::execute(__METHOD__, $metaTitle, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getMetaKeywordsTemplate(): string
    {
        if ($this->keyword) {
            /** @var FrontTranslations $translations */
            $translations = $this->SL->getService(FrontTranslations::class);
            $metaKeywords = $translations->getTranslation('general_search') . ' ' . $this->keyword;
        } else {
            $metaKeywords = parent::getMetaKeywordsTemplate();
        }
        
        return ExtenderFacade::execute(__METHOD__, $metaKeywords, func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getMetaDescriptionTemplate(): string
    {
        if ($this->keyword) {
            /** @var FrontTranslations $translations */
            $translations = $this->SL->getService(FrontTranslations::class);
            $metaDescription = $translations->getTranslation('general_search') . ' ' . $this->keyword;
        } else {
            $metaDescription = parent::getMetaDescriptionTemplate();
        }
        
        return ExtenderFacade::execute(__METHOD__, $metaDescription, func_get_args());
    }
    
}