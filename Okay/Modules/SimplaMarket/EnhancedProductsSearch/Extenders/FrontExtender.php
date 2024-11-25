<?php

namespace Okay\Modules\SimplaMarket\EnhancedProductsSearch\Extenders;

use Okay\Core\Languages;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Entities\ProductsEntity as OriginalProductsEntity;

class FrontExtender implements ExtensionInterface
{
    /** @var Request $request */
    protected $request;

    /** @var Languages $languages */
    protected $languages;

    public function __construct(
        Request $request,
        Languages $languages
    ) {
        $this->request = $request;
        $this->languages = $languages;
    }
    public function customOrder($result, $order = null, array $orderFields = [], array $additionalData = [])
    {
        if ($order != 'position' || empty($additionalData['relevant'])) {
            return $result;
        }

        $langAlias = $this->languages->getLangAlias(
            OriginalProductsEntity::getTableAlias()
        );

        // Якщо було додано сортування "товари не в наявності в кінець списка", запам'ятовуємо його
        if (($additionalData['in_stock_first'] ?? false) === true) {
            $inStockSort = reset($result);
        }

        $keywords = explode(' ', trim($additionalData['relevant']));

        $relevantV1 = [];
        $relevantV2 = [];
        foreach ($keywords as $keyNum=>$keyword) {
            $relevantV1[] = "
                {$langAlias}.`name` LIKE :keyword_sort_relevant_v1_{$keyNum}
                OR {$langAlias}.`name` LIKE :keyword_sort_relevant_v2_{$keyNum}";
            $relevantV2[] = "{$langAlias}.`name` LIKE :keyword_name_{$keyNum}";
        }

        $relevantOrder = vsprintf("(CASE
             WHEN %s THEN 10
             WHEN %s THEN 5
             ELSE 1
            END) DESC",
            [
                implode(' OR ', $relevantV1),
                implode(' OR ', $relevantV2)
            ]
        );
        // Додаємо сортування по релевантності приорітетним
        array_unshift($result, $relevantOrder);

        // Якщо було сортування "товари не в наявності в кінець списка", додаємо це сортування як приорітетне
        if (!empty($inStockSort)) {
            array_unshift($result, $inStockSort);
        }

        return $result;
    }

    public function getOrderProductsAdditionalData($result)
    {
        $keyword = strip_tags($this->request->get('query', null, null, false));
        if (empty($keyword)) {
            $keyword = strip_tags($this->request->get('keyword', null, null, false));
        }
        if (!empty($keyword)) {
            $result['relevant'] = $keyword;
        }
        return $result;
    }

}