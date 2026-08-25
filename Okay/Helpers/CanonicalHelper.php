<?php


namespace Okay\Helpers;


use Okay\Core\Settings;

class CanonicalHelper
{
    private $settings;

    private $catalogPagination;
    private $catalogPageAll;
    private $catalogBrand;
    private $catalogFeatures;
    private $catalogOtherFilter;
    private $catalogFilterPagination;

    private $maxBrandFilterDepth;
    private $maxOtherFilterDepth;
    private $maxFeaturesFilterDepth;
    private $maxFeaturesValuesFilterDepth;
    private $maxFilterDepth;
    
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Вимкнений page-all віддає звичайну першу сторінку, тож канонікал мусить
     * вести саме на неї.
     *
     * Підмінюється налаштування, а не сама сторінка: обнулити $page тут
     * недостатньо - тоді гілка просто не виконається, ключ 'page' лишиться
     * невиставленим, і в канонікал піде поточна адреса разом із page-all.
     */
    private function catalogPageAllCanonical(): int
    {
        if (okay_page_all_max_items($this->settings->get('catalog_page_all_max_items')) === PAGE_ALL_OFF) {
            return CANONICAL_FIRST_PAGE;
        }

        return $this->catalogPageAll;
    }

    public function setParams(
        $catalogPagination,
        $catalogPageAll,
        $catalogBrand,
        $catalogFeatures,
        $catalogOtherFilter,
        $catalogFilterPagination,
        $maxBrandFilterDepth,
        $maxOtherFilterDepth,
        $maxFeaturesFilterDepth,
        $maxFeaturesValuesFilterDepth,
        $maxFilterDepth
    ) {
        $this->catalogPagination = (int)$catalogPagination;
        $this->catalogPageAll = (int)$catalogPageAll;
        $this->catalogBrand = (int)$catalogBrand;
        $this->catalogFeatures = (int)$catalogFeatures;
        $this->catalogOtherFilter = (int)$catalogOtherFilter;
        $this->catalogFilterPagination = (int)$catalogFilterPagination;

        $this->maxBrandFilterDepth = (int)$maxBrandFilterDepth;
        $this->maxOtherFilterDepth = (int)$maxOtherFilterDepth;
        $this->maxFeaturesFilterDepth = (int)$maxFeaturesFilterDepth;
        $this->maxFeaturesValuesFilterDepth = (int)$maxFeaturesValuesFilterDepth;
        $this->maxFilterDepth = (int)$maxFilterDepth;
    }
    
    /**
     * @param string|int $page текущая страница, может быть all
     * @param array $otherFilter одномерный массив, содержащий значения ['discounted', 'featured'...]
     * @param array $featuresFilter
     * @param array $brandsFilter
     * @return array|false
     * 
     * Определение canonical для категории
     */
    public function getCatalogCanonicalData($page, array $otherFilter, array $featuresFilter, array $brandsFilter)
    {
        $result = [];
        
        // Подсчитываем общую глубину фильтра
        $filterDepth = 0;
        if (!empty($otherFilter)) {
            $result['filter'] = null;
            $filterDepth++;
        }
        if (!empty($featuresFilter)) {
            $filterDepth += count($featuresFilter);
            $result = array_merge($result,
                array_fill_keys(array_keys($featuresFilter), null)
            );
        }
        if (!empty($brandsFilter)) {
            $result['brand'] = null;
            $filterDepth++;
        }
        
        if ($filterDepth > $this->maxFilterDepth) {
            if (!empty($page)) {
                $result['page'] = null;
            }
            $result['sort'] = null;
            return $result; // no ExtenderFacade
        }
        
        // Т.к. getCatalogCanonicalDataExecutor не может вернуть связку для доп. фильтров и свойств или бренда,
        // определяем эту связку здесь
        if (!empty($otherFilter)) {
            if (count($otherFilter) > $this->maxOtherFilterDepth) {
                if (!empty($page)) {
                    $result['page'] = null;
                }
                $result['sort'] = null;
                $result['filter'] = null;
                return $result; // no ExtenderFacade
            }
        }
        
        if (($baseCatalogData = $this->getBaseCatalogCanonicalData($page, $otherFilter)) === false) {
            return false; // no ExtenderFacade
        }
        
        if (($catalogData = $this->getCatalogCanonicalDataExecutor($page, $featuresFilter, $brandsFilter)) === false) {
            return false; // no ExtenderFacade
        }
        
        return $catalogData + $baseCatalogData; // no ExtenderFacade
    }
    
    private function getCatalogCanonicalDataExecutor($page, array $featuresFilter, array $brandsFilter)
    {
        $result = [];

        // Определяем не превысили ли максимальное кол-во свойств или значений одного свойства
        if (!empty($featuresFilter)) {
            if (count($featuresFilter) > $this->maxFeaturesFilterDepth) {
                $result = array_merge($result,
                    array_fill_keys(array_keys($featuresFilter), null)
                );
                if (!empty($brandsFilter)) {
                    $result['brand'] = null;
                }
            } else {
                foreach ($featuresFilter as $values) {
                    if (count($values) > $this->maxFeaturesValuesFilterDepth) {
                        $result = array_merge($result,
                            array_fill_keys(array_keys($featuresFilter), null)
                        );
                        if (!empty($brandsFilter)) {
                            $result['brand'] = null;
                        }
                        break;
                    }
                }
            }
        }
        
        // Не превысили ли максимальное кол-во брендов
        if (!empty($brandsFilter)) {
            if (count($brandsFilter) > $this->maxBrandFilterDepth) {
                $result['brand'] = null;
            }
        }
        
        if (!empty($result)) {
            if (!empty($page)) {
                $result['page'] = null;
            }
            return $result; // no ExtenderFacade
        }
        
        if (!empty($page) && (!empty($featuresFilter) || !empty($brandsFilter))) {
            switch ($this->catalogFilterPagination) {
                case CANONICAL_WITHOUT_FILTER_FIRST_PAGE:
                    $result['page'] = null;
                    
                    if (!empty($featuresFilter)) {
                        // Заполняем массив, где ключи - транслиты свойств, значение null, чтобы удалить эти значения
                        $result = array_merge($result,
                            array_fill_keys(array_keys($featuresFilter), null)
                        );
                    }
                    $result['brand'] = null;
                    break;
                case CANONICAL_FIRST_PAGE:
                    $result['page'] = null;
                    break;
                case CANONICAL_CURRENT_PAGE:
                    $result['page'] = $page;
                    break;
                case CANONICAL_ABSENT:
                    return false; // no ExtenderFacade
            }
            return $result; // no ExtenderFacade
        }
        
        if (!empty($featuresFilter)) {
            switch ($this->catalogFeatures) {
                case CANONICAL_WITHOUT_FILTER:
                    
                    // Заполняем массив, где ключи - транслиты свойств, значение null, чтобы удалить эти значения
                    $result = array_merge($result,
                        array_fill_keys(array_keys($featuresFilter), null)
                    );
                    
                    break;
                case CANONICAL_WITH_FILTER:
                    break;
                case CANONICAL_ABSENT:
                    return false; // no ExtenderFacade
            }
        }
        
        if (!empty($brandsFilter)) {
            switch ($this->catalogBrand) {
                case CANONICAL_WITHOUT_FILTER:
                    $result['brand'] = null;
                    break;
                case CANONICAL_WITH_FILTER:
                    break;
                case CANONICAL_ABSENT:
                    return false; // no ExtenderFacade
            }
        }
        
        return $result; // no ExtenderFacade
    }

    /**
     * @param string|int $page текущая страница, может быть all
     * @param array $otherFilter одномерный массив, содержащий значения ['discounted', 'featured'...]
     * @return array|false
     * 
     * Определение canonical для страниц списков товаров (discounted, all-products, brand)
     */
    private function getBaseCatalogCanonicalData($page, array $otherFilter)
    {
        $result = [
            'sort' => null,
        ];

        if ($this->maxFilterDepth === 0 && !empty($otherFilter)) {
            $result['filter'] = null;
            return $result; // no ExtenderFacade
        }

        if (!empty($otherFilter)) {
            if (count($otherFilter) > $this->maxOtherFilterDepth) {
                $result['filter'] = null;
                return $result; // no ExtenderFacade
            }
        }
        
        if (!empty($page) && !empty($otherFilter)) {
            switch ($this->catalogFilterPagination) {
                case CANONICAL_WITHOUT_FILTER_FIRST_PAGE:
                    $result['page'] = null;
                    $result['filter'] = null;
                    break;
                case CANONICAL_FIRST_PAGE:
                    $result['page'] = null;
                    break;
                case CANONICAL_CURRENT_PAGE:
                    $result['page'] = $page;
                    break;
                case CANONICAL_ABSENT:
                    return false; // no ExtenderFacade
            }
            return $result; // no ExtenderFacade
        }
        
        if (!empty($page)) {
            if ($page == 'all') {
                switch ($this->catalogPageAllCanonical()) {
                    case CANONICAL_FIRST_PAGE:
                        $result['page'] = null;
                        break;
                    case CANONICAL_CURRENT_PAGE:
                        $result['page'] = 'all';
                        break;
                    case CANONICAL_ABSENT:
                        return false; // no ExtenderFacade
                }
            } else {
                switch ($this->catalogPagination) {
                    case CANONICAL_FIRST_PAGE:
                        $result['page'] = null;
                        break;
                    case CANONICAL_CURRENT_PAGE:
                        $result['page'] = $page;
                        break;
                    case CANONICAL_PAGE_ALL:
                        $result['page'] = 'all';
                        break;
                    case CANONICAL_ABSENT:
                        return false; // no ExtenderFacade
                }
            }
        }
        
        if (!empty($otherFilter)) {
            switch ($this->catalogOtherFilter) {
                case CANONICAL_WITHOUT_FILTER:
                    $result['filter'] = null;
                    break;
                case CANONICAL_WITH_FILTER:
                    break;
                case CANONICAL_ABSENT:
                    return false; // no ExtenderFacade
            }
        }
        
        return $result; // no ExtenderFacade
    }
}