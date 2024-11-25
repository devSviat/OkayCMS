<?php


namespace Okay\Modules\SimplaMarket\ProductDiscontinued\Init;


use Okay\Admin\Requests\BackendProductsRequest;
use Okay\Core\Database;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\QueryFactory;
use Okay\Entities\ModulesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Modules\SimplaMarket\ProductDiscontinued\Extensions\BackendExtension;

class Init extends AbstractInit
{
    const PERMISSION = 'simplamarket__product_discontinued';
    const ADDITIONAL_FIELD = 'discontinued';

    public function install()
    {
        $this->PriorityModule();
        $this->setBackendMainController('DescriptionAdmin');
        $this->migrateEntityField(ProductsEntity::class, (new EntityField(self::ADDITIONAL_FIELD))->setTypeTinyInt(1, false)->setDefault(0));
    }

    public function init()
    {
        $this->registerEntityField(ProductsEntity::class, self::ADDITIONAL_FIELD);

        $this->addPermission(self::PERMISSION);
        $this->registerBackendController('DescriptionAdmin');
        $this->addBackendControllerPermission('DescriptionAdmin', self::PERMISSION);

        $this->registerChainExtension(
            ['class' => BackendProductsRequest::class, 'method' => 'postProduct'],
            ['class' => BackendExtension::class, 'method' => 'postProduct']
        );

        $this->addBackendBlock('product_switch_checkboxes', 'discontinued_checkbox.tpl');
    }

    private function PriorityModule()
    {
        /** @var OkayContainer $DI */
        $DI = include 'Okay/Core/config/container.php';

        /** @var QueryFactory $queryFactory */
        $queryFactory = $DI->get(QueryFactory::class);

        /** @var Database $db */
        $db = $DI->get(Database::class);

        $select = $queryFactory->newSelect()
            ->from(ModulesEntity::getTable())
            ->cols(['module_name', 'position'])
            ->orderBy(['position ASC']);

        $db->query($select);
        $modules = $db->results();


        $ProductDiscontinued = 0;
        $ThreeProductStockStates = 0;


        foreach ($modules as $items) {
            if ($items->module_name == "ThreeProductStockStates") {
                $ThreeProductStockStates = $items->position;
            }

            if ($items->module_name == "ProductDiscontinued") {
                $ProductDiscontinued = $items->position;
            }
        }

        if ($ProductDiscontinued >= $ThreeProductStockStates) {
            $select = $queryFactory->newSelect()
                ->from(ModulesEntity::getTable())
                ->cols(['MAX(position) as max_position']);

            $db->query($select);
            $max_position = $db->results()[0]->max_position;


            $update = $queryFactory->newUpdate();
            $update->table(ModulesEntity::getTable())
                ->cols(['position'])
                ->where('module_name = :module_name')
                ->limit(1)
                ->bindValues([
                    'module_name' => 'ThreeProductStockStates',
                    'position' => $max_position + 1
                ])
                ->ignore(1);

            $db->query($update);
        }
    }
}
