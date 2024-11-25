<?php


namespace Okay\Modules\SimplaMarket\EnhancedProductsSearch\Controllers;


use Okay\Controllers\AbstractController;
use Okay\Core\Image;
use Okay\Core\Money;
use Okay\Core\Router;
use Okay\Helpers\ProductsHelper;

class EnhancedSearchController extends AbstractController
{
    public function ajaxSearch(
        Router $router,
        ProductsHelper $productsHelper,
        Image $image,
        Money $money
    )
    {

        $filter['keyword'] = $this->request->get('query', null, null, false);
        $filter['keyword'] = strip_tags($filter['keyword']);
        $filter['visible'] = true;
        $filter['limit'] = 10;

        $suggestions = [];

        $products = $productsHelper->getList($filter);
        if (!empty($products)) {
            foreach ($products as $product) {
                $suggestion = new \stdClass();
                if (isset($product->image)) {
                    $product->image = $image->getResizeModifier($product->image->filename, 35, 35);
                }

                $product->url = $router->generateUrl('product', ['url' => $product->url]);
                $suggestion->price = $money->convert($product->variant->price);
                $suggestion->currency = $this->currency->sign;
                $suggestion->value = $product->name;
                $suggestion->data = $product;
                $suggestions[] = $suggestion;
            }
        }


        $res = new \stdClass;

        $res->query = $filter['keyword'];
        $res->suggestions = $suggestions;

        $this->response->setContent(json_encode($res), RESPONSE_JSON);
    }
}