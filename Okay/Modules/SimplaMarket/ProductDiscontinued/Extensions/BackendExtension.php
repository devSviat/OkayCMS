<?php


namespace Okay\Modules\SimplaMarket\ProductDiscontinued\Extensions;


use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;

class BackendExtension implements ExtensionInterface
{
    private $request;

    public function __construct(
        Request $request
    )
    {
        $this->request = $request;
    }

    public function postProduct($product)
    {
        $product->discontinued = (int) $this->request->post('discontinued');

        return $product;
    }
}