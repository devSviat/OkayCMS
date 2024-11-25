<?php


namespace Okay\Modules\SimplaMarket\AdditionalDescriptionField\Extensions;

use Okay\Core\Request;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Modules\SimplaMarket\AdditionalDescriptionField\Init\Init;
use Okay\Core\EntityFactory;

class BackendProductsRequestExtension implements ExtensionInterface
{
    /**
     * @var
     */
    private $request;
    private $entityFactory;

    public function __construct(Request $request, EntityFactory $entityFactory)
    {
        $this->request = $request;
        $this->entityFactory = $entityFactory;
    }

    public function extendPostProduct($product)
    {
        $product->{Init::ADDITIONAL_FIELD_NAME} = $this->request->post('simplamarket__additional_description_field__description');

        return $product;
    }


}