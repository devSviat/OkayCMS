<?php


namespace Okay\Modules\SimplaMarket\AdditionalDescriptionField\Init;


use Okay\Admin\Requests\BackendProductsRequest;
use Okay\Entities\ProductsEntity;
use Okay\Core\Modules\EntityField;
use Okay\Core\Modules\AbstractInit;
use Okay\Modules\SimplaMarket\AdditionalDescriptionField\Extensions\BackendProductsRequestExtension;

class Init extends AbstractInit
{
    const ADDITIONAL_FIELD_NAME = 'simplamarket__additional_description_field__description';

    public function install()
    {
        $this->setBackendMainController('DescriptionAdmin');
        $this->migrateEntityField(ProductsEntity::class,
            (new EntityField(self::ADDITIONAL_FIELD_NAME))->setTypeText()->setNullable()->setIsLang());
    }

    public function init()
    {
        $this->registerBackendController('DescriptionAdmin');
        $this->addBackendControllerPermission('DescriptionAdmin', 'products');

        $this->registerEntityField(ProductsEntity::class, self::ADDITIONAL_FIELD_NAME, true);

        $this->registerChainExtension(
            [BackendProductsRequest::class,          'postProduct'],
            [BackendProductsRequestExtension::class, 'extendPostProduct']);

        $this->addBackendBlock('product_custom_block', 'additional_description_field.tpl');
    }
}