<?php

namespace Okay\Modules\SimplaMarket\InformBackInStock\Backend\Controllers;

use Okay\Admin\Helpers\BackendProductsHelper;
use Okay\Core\QueryFactory\Select;
use Okay\Entities\ProductsEntity;
use Okay\Modules\SimplaMarket\InformBackInStock\Entities\InformBackInStockEntity;

class InformBackInStockAdmin extends \Okay\Admin\Controllers\IndexAdmin
{

    public function fetch(InformBackInStockEntity  $informBackInStockEntity, ProductsEntity $productsEntity, BackendProductsHelper $backendProductsHelper)
    {
        if($this->request->method('post') && $record_id = $this->request->post('delete_record')) {
            $informBackInStockEntity->deleteRec($record_id);
        }

        $products_ids = $informBackInStockEntity->getProductsIds();

        $filter['page'] = max(1, $this->request->get('page', 'integer'));
        $filter['id'] = $products_ids;

        if ($filter['limit'] = $this->request->get('limit', 'integer')) {
            $filter['limit'] = max(1, $filter['limit']);
            $filter['limit'] = min(100, $filter['limit']);
            $_SESSION['products_num_admin'] = $filter['limit'];
        } elseif (!empty($_SESSION['products_num_admin'])) {
            $filter['limit'] = $_SESSION['products_num_admin'];
        } else {
            $filter['limit'] = 25;
        }
        $this->design->assign('current_limit', $filter['limit']);

        if(!empty($filter['id'])){
            //$products_count = $this->products->count_products($filter);
            $products_count = $productsEntity->count($filter);
            // Показать все страницы сразу
            if($this->request->get('page') == 'all') {
                $filter['limit'] = $products_count;
            }

            if($filter['limit']>0) {
                $pages_count = ceil($products_count/$filter['limit']);
            } else {
                $pages_count = 0;
            }
            $filter['page'] = min($filter['page'], $pages_count);
            $this->design->assign('products_count', $products_count);
            $this->design->assign('pages_count', $pages_count);
            $this->design->assign('current_page', $filter['page']);


            $products = $backendProductsHelper->findProductsForProductsAdmin($filter, '');

            $this->design->assign('products', $products);

            $records = null;
            $records_count = 0;
            foreach($informBackInStockEntity->find([]) as $r){
                $records[$r->product_id][] = $r;
                $records_count++;
            }

            $this->design->assign('records_count', $records_count);
            $this->design->assign('records', $records);
        }

        $this->response->setContent($this->design->fetch('inform_back_in_stock_products.tpl'));
    }
}
