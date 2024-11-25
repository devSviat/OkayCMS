<?php


namespace Okay\Modules\SimplaMarket\EnhancedProductsSearch\Init;


use Okay\Core\Modules\AbstractInit;
use Okay\Entities\ProductsEntity;
use Okay\Helpers\ProductsHelper;
use Okay\Modules\SimplaMarket\EnhancedProductsSearch\Extenders\FrontExtender;
use Okay\Modules\SimplaMarket\EnhancedProductsSearch\ExtendsEntities\ProductsEntity as ProductsFilter;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('DescriptionAdmin');
    }


    public function init()
    {
        $this->registerBackendController('DescriptionAdmin');
        $this->addBackendControllerPermission('DescriptionAdmin', 'products');


        $this->registerEntityFilter(
            ProductsEntity::class,
            'keyword',
            ProductsFilter::class,
            'filter__keyword'
        );

        $this->registerChainExtension(
            [ProductsEntity::class, 'customOrder'],
            [FrontExtender::class, 'customOrder']
        );

        $this->registerChainExtension(
            [ProductsHelper::class, 'getOrderProductsAdditionalData'],
            [FrontExtender::class, 'getOrderProductsAdditionalData']
        );
    }
}