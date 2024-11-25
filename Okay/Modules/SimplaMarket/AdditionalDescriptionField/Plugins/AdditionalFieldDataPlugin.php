<?php


namespace Okay\Modules\SimplaMarket\AdditionalDescriptionField\Plugins;


use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\SmartyPlugins\Func;
use Okay\Entities\ProductsEntity;
use Okay\Modules\SimplaMarket\AdditionalDescriptionField\Init\Init;

class AdditionalFieldDataPlugin extends Func
{
    protected $tag = 'additional_field_data';

    /**
     * @var Design
     */
    private $design;

    /**
     * @var ProductsEntity
     */
    private $productsEntity;

    public function __construct(Design $design, EntityFactory $entityFactory)
    {
        $this->design = $design;
        $this->productsEntity = $entityFactory->get(ProductsEntity::class);
    }

    public function run($params)
    {
        if (empty($params['product_id'])) {
            $product = $this->design->getVar('product');
            if (empty($product->id)) {
                return null;
            }
            $productId = $product->id;
        } else {
            $productId = $params['product_id'];
        }

        $product = $this->productsEntity->get((int) $productId);
        if (empty($product->{Init::ADDITIONAL_FIELD_NAME})) {
            return null;
        }

        $this->design->assign('simplamarket__additional_description_field__description', $product->{Init::ADDITIONAL_FIELD_NAME});
        return $this->design->fetch('additional_field_data.tpl');
    }
}