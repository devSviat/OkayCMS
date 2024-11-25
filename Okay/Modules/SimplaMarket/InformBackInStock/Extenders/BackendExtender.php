<?php


namespace Okay\Modules\SimplaMarket\InformBackInStock\Extenders;


use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Notify;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\ProductsEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\SimplaMarket\InformBackInStock\Core\InformBackInStockNotify;
use Okay\Modules\SimplaMarket\InformBackInStock\Entities\InformBackInStockEntity;


class BackendExtender implements ExtensionInterface
{
    private $request;
    private $entityFactory;
    private $design;
    private $backendTranslations;
    private $settings;
    private $notify;

    public function __construct(
        Request $request,
        EntityFactory $entityFactory,
        Design $design,
        BackendTranslations $backendTranslations,
        Settings $settings,
        InformBackInStockNotify $notify
    )
    {
        $this->request             = $request;
        $this->entityFactory       = $entityFactory;
        $this->design              = $design;
        $this->backendTranslations = $backendTranslations;
        $this->settings            = $settings;
        $this->notify              = $notify;
    }

    public function checkProduct($null, $product, $productVariants)
    {
        if (!empty($productVariants)) {
            foreach ($productVariants as $variant) {
                $informBackInStockEntity = $this->entityFactory->get(InformBackInStockEntity::class);
                //если активен и кол-во больше 0, шлем и-мейлы
                if ($product->visible && $variant->stock > 0) {

                    foreach ($informBackInStockEntity->getRecords($variant->id) as $rec) {
                        $this->notify->emailInformBackInStock($variant->id, $rec);
                    }

                    //удаляем все подписки на товар
                    $informBackInStockEntity->deleteRecords($variant->id);
                }
            }
        }
    }
}