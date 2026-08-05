<?php


namespace Okay\Admin\Helpers;


use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Request;
use Okay\Entities\CategoriesEntity;
use Okay\Entities\ReportStatEntity;

class BackendCategoryStatsHelper
{
    private $totalPrice;

    private $totalAmount;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var CategoriesEntity
     */
    private $categoriesEntity;

    /**
     * @var ReportStatEntity
     */
    private $reportStatEntity;

    public function __construct(EntityFactory $entityFactory, Request $request)
    {
        $this->request          = $request;
        $this->categoriesEntity = $entityFactory->get(CategoriesEntity::class);
        $this->reportStatEntity = $entityFactory->get(ReportStatEntity::class);
    }

    public function buildFilter()
    {
        $filter = [];

        // Перевірялись $date_from і $date_to, яких у методі немає, — обидва
        // діапазони мовчки не доходили до вибірки, і сторінка завжди
        // рахувала продажі за весь час.
        $dateFrom = strtotime($this->request->getRawString('date_from'));
        $dateTo = strtotime($this->request->getRawString('date_to'));

        if ($dateFrom !== false) {
            $filter['date_from'] = date("Y-m-d 00:00:01", $dateFrom);
        }

        if ($dateTo !== false) {
            $filter['date_to'] = date("Y-m-d 23:59:00", $dateTo);
        }

        $categoryId = $this->request->get('category', 'integer');
        if (!empty($categoryId)) {
            // get() на неіснуючу категорію віддає false, а звернення до
            // властивості false — це Warning, тобто вивід перед заголовками.
            if ($category = $this->categoriesEntity->get($categoryId)) {
                $filter['category_id'] = $category->children;
            }
        }

        $brandId = $this->request->get('brand', 'integer');
        if (!empty($brandId)) {
            $filter['brand_id'] = $brandId;
        }

        return ExtenderFacade::execute(__METHOD__, $filter, func_get_args());
    }

    public function getStatistic($filter)
    {
        $this->totalPrice = 0;
        $this->totalAmount = 0;

        $categories = $this->categoriesEntity->getCategoriesTree();

        $purchases = $this->reportStatEntity->getCategorizedStat($filter);
        if (!empty($category)) {
            $categories_list = $this->catTree([$category], $purchases);
        } else {
            $categories_list = $this->catTree($categories, $purchases);
        }

        return ExtenderFacade::execute(__METHOD__, $categories_list, func_get_args());
    }

    private function catTree($categories, $purchases = [])
    {
        foreach ($categories as $k => $v) {
            if (isset($v->subcategories)) {
                $this->catTree($v->subcategories, $purchases);
            }

            if (isset($purchases[$v->id])) {
                $price = floatval($purchases[$v->id]->price);
                $amount = intval($purchases[$v->id]->amount);
            } else {
                $price = 0;
                $amount = 0;
            }

            $categories[$k]->price  = $price;
            $categories[$k]->amount = $amount;

            $this->totalPrice  += $price;
            $this->totalAmount += $amount;
        }

        return $categories;
    }
}