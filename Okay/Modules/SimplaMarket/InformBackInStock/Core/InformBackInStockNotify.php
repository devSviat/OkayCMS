<?php


namespace Okay\Modules\SimplaMarket\InformBackInStock\Core;

use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Notify;
use Okay\Core\Settings;
use Okay\Entities\ProductsEntity;
use Okay\Entities\TranslationsEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\SimplaMarket\InformBackInStock\Entities\InformBackInStockEntity;

class InformBackInStockNotify
{
    private $notify;
    private $settings;
    private $entityFactory;
    private $backendTranslations;
    private $design;
    private $rootDir;
    private $languages;
    private $frontTranslations;

    public function __construct(
        Notify $notify,
        Settings $settings,
        EntityFactory $entityFactory,
        BackendTranslations $backendTranslations,
        Design $design,
        $rootDir,
        Languages $languages,
        FrontTranslations $frontTranslations
    ) {
        $this->notify = $notify;
        $this->settings = $settings;
        $this->entityFactory = $entityFactory;
        $this->backendTranslations = $backendTranslations;
        $this->design = $design;
        $this->rootDir = $rootDir;
        $this->languages = $languages;
        $this->frontTranslations = $frontTranslations;
    }

    public function emailInformBackInStockAdmin($recId)
    {
        /** @var InformBackInStockEntity $informBackInStockEntity */
        $informBackInStockEntity = $this->entityFactory->get(InformBackInStockEntity::class);

        if (!($recData = $informBackInStockEntity->get((int)$recId))) {
            return false;
        }
        $recData->product = $this->entityFactory->get(ProductsEntity::class)->get(intval($recData->product_id));

        $this->design->assign('rec', $recData);

        // Перевод админки
        $this->backendTranslations->initTranslations($this->settings->get('email_lang'));
        $this->design->assign('btr', $this->backendTranslations);

        // Отправляем письмо
        $emailTemplate = $this->design->fetch($this->rootDir.'Okay/Modules/SimplaMarket/InformBackInStock/Backend/design/html/email/email_inform_back_in_stock_admin.tpl');
        $subject = $this->design->getVar('subject');

//        $this->notify->email($this->settings->get('send_mail_inform_back_in_stock'), $subject, $emailTemplate, "$recData->name <$recData->email>", "$recData->name <$recData->email>");
        $this->notify->email($this->settings->get('comment_email'), $subject, $emailTemplate, "$recData->name <$recData->email>", "$recData->name <$recData->email>");

        return true;
    }

    public function emailInformBackInStock($variantId, $rec)
    {
        /** @var InformBackInStockEntity $informBackInStockEntity */
        $informBackInStockEntity = $this->entityFactory->get(InformBackInStockEntity::class);

        /** @var TranslationsEntity $translationsEntity */
        $translationsEntity = $this->entityFactory->get(TranslationsEntity::class);

        /** @var VariantsEntity $variantsEntity */
        $variantsEntity = $this->entityFactory->get(VariantsEntity::class);

        /** @var ProductsEntity $productsEntity */
        $productsEntity = $this->entityFactory->get(ProductsEntity::class);

        if (!($recData = $informBackInStockEntity->get((int)$rec->id))) {
            return false;
        }

        if (!empty($recData->lang_id)) {
            $currentLangId = $this->languages->getLangId();
            $this->languages->setLangId($recData->lang_id);

            // Переинициализируем на новый язык
            $this->frontTranslations->init();

            $this->settings->initSettings();
            $this->design->assign('settings', $this->settings);
            $this->design->assign('lang', $this->frontTranslations);
        }

        $backInStockVariant = $variantsEntity->findOne(['id' => $variantId]);
        $backInStockProduct = $productsEntity->findOne(['id' => $backInStockVariant->product_id]);
        $backInStockProduct->variant = $backInStockVariant;

        $this->design->assign('rec', $recData);
        $this->design->assign('backInStockProduct', $backInStockProduct);

        // Отправляем письмо
        $emailTemplate = $this->design->fetch($this->rootDir.'Okay/Modules/SimplaMarket/InformBackInStock/design/html/email_inform_back_in_stock.tpl');
        $subject = $this->design->getVar('subject');

        $from = ($this->settings->get('notify_from_name') ? $this->settings->get('notify_from_name')." <".$this->settings->get('notify_from_email').">" : $this->settings->get('notify_from_email'));
        $this->notify->email($rec->email, $subject, $emailTemplate, $from);

        if (!empty($currentLangId)) {
            $this->languages->setLangId($currentLangId);
            // Вернем переводы на предыдущий язык
            $this->frontTranslations->init();
            $this->settings->initSettings();
            $this->design->assign('settings', $this->settings);
        }
        return true;

    }

}