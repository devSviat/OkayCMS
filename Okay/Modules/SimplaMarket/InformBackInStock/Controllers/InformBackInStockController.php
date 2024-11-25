<?php

namespace Okay\Modules\SimplaMarket\InformBackInStock\Controllers;


use Okay\Controllers\AbstractController;
use Okay\Core\FrontTranslations;
use Okay\Entities\VariantsEntity;
use Okay\Modules\SimplaMarket\InformBackInStock\Core\InformBackInStockNotify;
use Okay\Modules\SimplaMarket\InformBackInStock\Entities\InformBackInStockEntity;


class InformBackInStockController extends AbstractController
{
    public function createNote(
        InformBackInStockEntity $informBackInStockEntity,
        InformBackInStockNotify $notify,
        FrontTranslations $frontTranslations,
        VariantsEntity $variantsEntity)
    {
        $result = null;
        if($this->request->method('post')){
            $rec = new \stdClass();
            $rec->variant_id = $this->request->post('variant_id','integer');
            $rec->email      = $this->request->post('email');
            $rec->name       = trim($this->request->post('name'));
            $rec->lang_id    = $_SESSION['lang_id'];

            $rec->product_id = $variantsEntity->cols(['product_id'])->findOne(['id' => $rec->variant_id]);

            if(empty($rec->name)){
                $result = ['error_empty' => $frontTranslations->getTranslation("form_enter_name")];
            } else if(!filter_var($rec->email, FILTER_VALIDATE_EMAIL)){
                $result = ['error_empty' => $frontTranslations->getTranslation("form_enter_email")];
            } else {
                $recId      = $informBackInStockEntity->addRec($rec);

                if(!empty($recId)){
                    // Отправляем письмо администратору
                    $notify->emailInformBackInStockAdmin($recId);
                    $result = ['success' => 1];
                } else {
                    $result = ['error' => $frontTranslations->getTranslation("sent_earlier")];
                }
            }
        }
        $this->response->setContent(json_encode($result), RESPONSE_JSON);
    }
}