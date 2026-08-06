<?php


namespace Okay\Modules\OkayCMS\Rozetka\Helpers;


use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Entities\BrandsEntity;
use Okay\Entities\CategoriesEntity;
use Okay\Helpers\ProductsHelper;
use Okay\Modules\OkayCMS\Rozetka\Entities\RozetkaFeedsEntity;
use Okay\Modules\OkayCMS\Rozetka\Entities\RozetkaRelationsEntity;

class BackendRozetkaHelper
{
    /** @var QueryFactory */
    private $queryFactory;

    /** @var Request */
    private $request;

    /** @var ProductsHelper */
    private $productsHelper;


    /** @var RozetkaFeedsEntity */
    private $feedsEntity;

    /** @var RozetkaRelationsEntity */
    private $relationsEntity;

    /**
     * Усі зв'язки фідів, прочитані один раз на запит.
     *
     * Сторінка адмінки викликає чотири публічні читання підряд, і кожне з них
     * робило власний SELECT плюс окремий COUNT(*) заради ['limit' => count()].
     * Різнився лише entity_type/include, тож замість чотирьох вибірок по
     * непридатному індексу (провідна колонка feed_id, а фільтр без нього)
     * читається вся таблиця один раз і ділиться в пам'яті.
     *
     * @var object[]|null
     */
    private $allRelations;

    public function __construct(
        EntityFactory  $entityFactory,
        QueryFactory   $queryFactory,
        Request        $request,
        ProductsHelper $productsHelper
    )
    {
        $this->queryFactory   = $queryFactory;
        $this->request        = $request;
        $this->productsHelper = $productsHelper;

        $this->feedsEntity     = $entityFactory->get(RozetkaFeedsEntity::class);
        $this->relationsEntity = $entityFactory->get(RozetkaRelationsEntity::class);
    }

    /**
     * @param array $feed
     * Добавляем новый фид
     * @return integer|bool
     */
    public function addFeed($feed = [
        'name' => 'New Feed',
        'url' => '',
        'enabled' => 0
    ])
    {
        if (empty($feed['url'])) {
            $feed['url'] = $this->feedsEntity->count() + 1;

            while ($this->feedsEntity->findOne(['url' => $feed['url']])) {
                $feed['url']++;
            }
        }

        $feedId = $this->feedsEntity->add($feed);

        return ExtenderFacade::execute(__METHOD__, $feedId, func_get_args());
    }

    /**
     * @param string|integer $feedId
     * Удаляем фид
     */
    public function removeFeed($feedId)
    {
        $this->feedsEntity->delete($feedId);
    }

    /**
     * @param array $feeds
     * Обновляем полученные фиды
     */
    public function updateFeeds($feeds)
    {
        foreach ($feeds as $feedId => $feed) {
            $this->feedsEntity->update($feedId, $feed);
        }

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * @param string|integer|array $feeds
     * Валидируем фиды. Проверяем URL на уникальность
     * @return array
     * Возвращаем ошибки, индивидуальные для каждого фида
     */
    public function validateFeeds($feeds)
    {
        $errors = [];
        foreach ($feeds as $feedId => $feed) {
            if (($dbFeed = $this->feedsEntity->findOne(['url' => $feed['url']])) && ($dbFeed->id != $feedId)) {
                $errors['feeds'][$feedId]['url'] = true;
            } else if (preg_match('/[А-я]/', $feed['url'])) {
                $errors['feeds'][$feedId]['url_cyrillic'] = true;
            }
        }

        return ExtenderFacade::execute(__METHOD__, $errors, func_get_args());
    }

    /**
     * @param string|integer $feedId
     * Закрепляяем все категории за фидом
     */
    public function addAllCategories($feedId)
    {
        $this->relationsEntity->removeAllCategoriesByFeedId($feedId);

        $this->forgetRelations();

        $select = $this->queryFactory->newSelect();
        $select ->from(CategoriesEntity::getTable())
            ->cols(['id']);
        $categoriesIds = $select->results('id');
        $rows = [];
        foreach ($categoriesIds as $categoryId) {
            $rows[] = [
                'feed_id'     => $feedId,
                'entity_id'   => $categoryId,
                'entity_type' => 'category',
                'include'     => 1
            ];
        }

        $this->relationsEntity->addRelations($rows);

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * @param array $relatedCategories
     * Закрепляем за фидом вручуню отмеченные категории
     */
    public function updateRelatedCategories($relatedCategories)
    {
        $this->relationsEntity->removeAllCategories();

        $this->forgetRelations();

        if (!empty($relatedCategories)) {
            $rows = [];
            foreach ($relatedCategories as $feedId => $categoriesIds) {
                foreach ($categoriesIds as $categoryId) {
                    $rows[] = [
                        'feed_id'     => $feedId,
                        'entity_id'   => $categoryId,
                        'entity_type' => 'category',
                        'include'     => 1
                    ];
                }
            }

            $this->relationsEntity->addRelations($rows);
        }

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * @param string|integer $feedId
     * Закрепяем все бренды за фидом
     */
    public function addAllBrands($feedId)
    {
        $this->relationsEntity->removeAllBrandsByFeedId($feedId);

        $this->forgetRelations();

        $select = $this->queryFactory->newSelect();
        $select ->from(BrandsEntity::getTable())
            ->cols(['id']);
        $brandsIds = $select->results('id');
        $rows = [];
        foreach ($brandsIds as $brandId) {
            $rows[] = [
                'feed_id'     => $feedId,
                'entity_id'   => $brandId,
                'entity_type' => 'brand',
                'include'     => 1
            ];
        }

        $this->relationsEntity->addRelations($rows);

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * @param array $relatedBrands
     * Закрепляем за фидом вручуню отмеченные бренды
     */
    public function updateRelatedBrands($relatedBrands)
    {
        $this->relationsEntity->removeAllBrands();

        $this->forgetRelations();

        if (!empty($relatedBrands)) {
            $rows = [];
            foreach ($relatedBrands as $feedId => $brandsIds) {
                foreach ($brandsIds as $brandId) {
                    $rows[] = [
                        'feed_id'     => $feedId,
                        'entity_id'   => $brandId,
                        'entity_type' => 'brand',
                        'include'     => 1
                    ];
                }
            }

            $this->relationsEntity->addRelations($rows);
        }

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * Закрепляем за фидом вручуню отмеченные продукты
     */
    public function updateRelatedProducts()
    {
        $this->relationsEntity->removeAllRelatedProducts();

        $this->forgetRelations();

        $feeds = $this->feedsEntity->noLimit()->find();

        $rows = [];
        foreach ($feeds as $feed) {
            $relatedProducts = $this->request->post("related_products_{$feed->id}");
            if (!empty($relatedProducts)) {
                $relatedProducts = array_unique($relatedProducts);
                foreach ($relatedProducts as $productId) {
                    $rows[] = [
                        'feed_id'     => $feed->id,
                        'entity_id'   => $productId,
                        'entity_type' => 'product',
                        'include'     => 1
                    ];
                }
            }
        }

        $this->relationsEntity->addRelations($rows);

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * Закрепляем за фидом вручуню отмеченные продукты не для выгрузки
     */
    public function updateNotRelatedProducts()
    {
        $this->relationsEntity->removeAllNotRelatedProducts();

        $this->forgetRelations();

        $feeds = $this->feedsEntity->noLimit()->find();

        $rows = [];
        foreach ($feeds as $feed) {
            $notRelatedProducts = $this->request->post("not_related_products_{$feed->id}");
            if (!empty($notRelatedProducts)) {
                $notRelatedProducts = array_unique($notRelatedProducts);
                foreach ($notRelatedProducts as $productId) {
                    $rows[] = [
                        'feed_id'     => $feed->id,
                        'entity_id'   => $productId,
                        'entity_type' => 'product',
                        'include'     => 0
                    ];
                }
            }
        }

        $this->relationsEntity->addRelations($rows);

        return ExtenderFacade::execute(__METHOD__, null, func_get_args());
    }

    /**
     * Уся таблиця зв'язків, прочитана один раз на запит.
     *
     * @return object[]
     */
    private function getAllRelations()
    {
        if ($this->allRelations === null) {
            $this->allRelations = $this->relationsEntity->noLimit()->find();
        }

        return $this->allRelations;
    }

    /**
     * Кеш живе рівно один запит. Скидається після кожного запису, щоб читання
     * після збереження не показувало стан до нього.
     */
    private function forgetRelations()
    {
        $this->allRelations = null;
    }

    /**
     * @param string $entityType
     * @param int|null $include
     * @return object[]
     */
    private function filterRelations($entityType, $include = null)
    {
        $filtered = [];
        foreach ($this->getAllRelations() as $relation) {
            if ($relation->entity_type !== $entityType) {
                continue;
            }
            if ($include !== null && (int)$relation->include !== $include) {
                continue;
            }
            $filtered[] = $relation;
        }

        return $filtered;
    }

    /**
     * @param object[] $relations
     * @return array
     */
    private function groupProductsByFeed(array $relations)
    {
        $ids = [];
        foreach ($relations as $relation) {
            $ids[] = $relation->entity_id;
        }

        // Порожній масив у фільтрі — це «нічого не закріплено», а не «фільтра
        // немає». autoFilter() (Okay/Core/Entity/filter.php) мовчки викидає
        // порожній масив, тож без цієї перевірки getList(['id' => []]) віддавав
        // увесь каталог — а це стан свіжовстановленого модуля.
        $products = empty($ids) ? [] : $this->productsHelper->getList(['id' => $ids]);

        $grouped = [];
        foreach ($relations as $relation) {
            // Зв'язок переживає видалений товар: без перевірки в масив для
            // шаблона потрапляв null, а PHP писав попередження.
            if (!isset($products[$relation->entity_id])) {
                continue;
            }
            $grouped[$relation->feed_id][] = $products[$relation->entity_id];
        }

        return $grouped;
    }

    /**
     * @return array
     * Достаем массив ids закрепённых категорий
     */
    public function getAllRelatedCategoriesIds()
    {
        $relatedCategoriesIds = [];
        foreach ($this->filterRelations('category') as $categoryRelation) {
            $relatedCategoriesIds[$categoryRelation->feed_id][] = $categoryRelation->entity_id;
        }

        return ExtenderFacade::execute(__METHOD__, $relatedCategoriesIds, func_get_args());
    }

    /**
     * @return array
     * Достаем массив ids закрепённых брендов
     */
    public function getAllRelatedBrandsIds()
    {
        $relatedBrandsIds = [];
        foreach ($this->filterRelations('brand') as $brandRelation) {
            $relatedBrandsIds[$brandRelation->feed_id][] = $brandRelation->entity_id;
        }

        return ExtenderFacade::execute(__METHOD__, $relatedBrandsIds, func_get_args());
    }

    /**
     * @return array
     * Достаем массив закрепённых продуктов
     * @throws \Exception
     */
    public function getAllRelatedProducts()
    {
        $relatedProducts = $this->groupProductsByFeed($this->filterRelations('product', 1));

        return ExtenderFacade::execute(__METHOD__, $relatedProducts, func_get_args());
    }

    /**
     * @return array
     * Достаем массив закрепённых продуктов не для выгрузки
     * @throws \Exception
     */
    public function getAllNotRelatedProducts()
    {
        $notRelatedProducts = $this->groupProductsByFeed($this->filterRelations('product', 0));

        return ExtenderFacade::execute(__METHOD__, $notRelatedProducts, func_get_args());
    }
}