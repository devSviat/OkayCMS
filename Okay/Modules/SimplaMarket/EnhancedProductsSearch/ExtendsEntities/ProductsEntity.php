<?php


namespace Okay\Modules\SimplaMarket\EnhancedProductsSearch\ExtendsEntities;


use Okay\Core\Languages;
use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Core\QueryFactory;
use Okay\Core\ServiceLocator;
use Okay\Entities\ProductsEntity as OriginalProductsEntity;

class ProductsEntity extends AbstractModuleEntityFilter
{
    public function filter__keyword($keyword)
    {
        $SL = ServiceLocator::getInstance();

        /** @var Languages $lang */
        $lang = $SL->getService(Languages::class);

        /** @var QueryFactory $queryFactory */
        $queryFactory = $SL->getService(QueryFactory::class);

        $tableAlias = OriginalProductsEntity::getTableAlias();
        $langAlias = $lang->getLangAlias(
            OriginalProductsEntity::getTableAlias()
        );
        $langId = $lang->getLangId();

        $keywords = explode(' ', trim($keyword));

        foreach ($keywords as $keyNum=>$keyword) {
                $keywordFilter = [];
                $keywordFilter[] = "{$langAlias}.name LIKE :keyword_name_{$keyNum}";
                $keywordFilter[] = "{$langAlias}.meta_keywords LIKE :keyword_meta_keywords_{$keyNum}";
                $keywordFilter[] = "{$langAlias}.annotation LIKE :keyword_annotation_{$keyNum}";
                $keywordFilter[] = "{$langAlias}.description LIKE :keyword_description_{$keyNum}";
                $keywordFilter[] = "{$langAlias}.meta_keywords LIKE :keyword_meta_keywords_{$keyNum}";
                $keywordFilter[] = "{$tableAlias}.id in (SELECT product_id FROM __variants WHERE sku LIKE :keyword_sku_{$keyNum})";

                $this->select->bindValues([
                    "keyword_name_{$keyNum}" => '%' . $keyword . '%',
                    "keyword_meta_keywords_{$keyNum}" => '%' . $keyword . '%',
                    "keyword_sku_{$keyNum}" => '%' . $keyword . '%',
                    "keyword_features_{$keyNum}" => '%' . $keyword . '%',
                    "keyword_annotation_{$keyNum}" => '%' . $keyword . '%',
                    "keyword_description_{$keyNum}" => '%' . $keyword . '%',
                    "keyword_sort_relevant_v1_{$keyNum}" => $keyword . '%', // Пошукова фраза стоїть на початку слова
                    "keyword_sort_relevant_v2_{$keyNum}" => ' ' . $keyword . '%', // Пошукова фраза стоїть на початку слова
                ]);

                $keywordWhere = implode(' OR ', $keywordFilter);
                $subSelect = $queryFactory->newSelect();
                $subSelect->from('__products_features_values as pfv')
                    ->cols(['product_id'])
                    ->leftJoin('__features_values as fv', 'pfv.value_id=fv.id')
                    ->leftJoin('__lang_features_values as lfv', "fv.id=lfv.feature_value_id AND lfv.lang_id = {$langId}")
                    ->innerJoin(' __features as f', 'fv.feature_id = f.id')
                    ->where("lfv.value LIKE :keyword_features_{$keyNum}");
                $this->select->where('(' . $keywordWhere . ' OR ' . $tableAlias . '.id in (?))', $subSelect);
        }
    }
}
