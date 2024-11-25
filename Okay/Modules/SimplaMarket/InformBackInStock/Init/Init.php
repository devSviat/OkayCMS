<?php


namespace Okay\Modules\SimplaMarket\InformBackInStock\Init;


use Okay\Admin\Helpers\BackendVariantsHelper;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Modules\SimplaMarket\InformBackInStock\Entities\InformBackInStockEntity;
use Okay\Modules\SimplaMarket\InformBackInStock\Extenders\BackendExtender;


class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('DescriptionAdmin');

        $this->migrateEntityTable(InformBackInStockEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('product_id'))->setTypeInt(11),
            (new EntityField('variant_id'))->setTypeInt(11),
            (new EntityField('name'))->setTypeVarchar(255),
            (new EntityField('email'))->setTypeVarchar(255)->setNullable(),
            (new EntityField('lang_id'))->setTypeVarchar(255)->setNullable(),
        ]);
    }

    public function init()
    {
        $this->registerBackendController('DescriptionAdmin');
        $this->addBackendControllerPermission('DescriptionAdmin', 'simplamarket__inform_stock');

        $this->registerBackendController('InformBackInStockAdmin');
        $this->addBackendControllerPermission('InformBackInStockAdmin', 'simplamarket__inform_stock');

        $this->extendBackendMenu('left_users', [
            'left_inform_stock' => ['InformBackInStockAdmin'],
        ]);

        $this->registerQueueExtension(
            ['class' => BackendVariantsHelper::class, 'method' => 'updateVariants'],
            ['class' => BackendExtender::class, 'method' => 'checkProduct']
        );

        // Регистрируем все формы, которые указали в конфиге как шортблоки
        if (file_exists(__DIR__.'/config.php')) {
            $formsConfig = include __DIR__ . '/config.php';
        }

        foreach ($formsConfig as $formConfig) {
            if (!empty($formConfig['shortBlock'])) {
                $this->addFrontBlock($formConfig['shortBlock'], $formConfig['tpl']);
            }
        }

        $this->addFrontBlock('inform_stock_btn', 'inform_stock_btn.tpl');
        $this->addFrontBlock('front_after_footer_content', 'inform_stock_form.tpl');
//        $this->addBackendBlock('settings_notify_custom_block', 'inform_stock_settings.tpl');
    }
}