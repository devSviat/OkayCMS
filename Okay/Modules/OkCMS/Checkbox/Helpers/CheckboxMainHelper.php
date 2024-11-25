<?php


namespace Okay\Modules\OkCMS\Checkbox\Helpers;


use Okay\Core\BackendTranslations;
use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Entities\DiscountsEntity;
use Okay\Entities\ManagersEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\OkCMS\Checkbox\Entities\ReceiptsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\ShiftsEntity;
use Okay\Modules\OkCMS\Checkbox\Entities\TaxesEntity;
use Okay\Modules\OkCMS\Checkbox\Init\Init;
use Okay\Modules\OkCMS\SmsClub\Helpers\SmsFlyHelper;

class CheckboxMainHelper
{
    protected $baseUrl;
    protected $login;
    protected $password;
    protected $accessToken;
    protected $xLicenseKey = '';
    protected $xClientName = 'OkCMS Checkbox Module';
    protected $date;
    protected $timeout = 5;
    protected $original_product_sum = 0;

    private $settings;
    private $config;
    private $request;
    private $languages;
    private $btr;
    private $queryFactory;
    private $entityFactory;
    private $manager;

    private $errors;
    private $logDir;


    public function __construct()
    {
        $SL = ServiceLocator::getInstance();
        $this->settings         = $SL->getService(Settings::class);
        $this->languages        = $SL->getService(Languages::class);
        $this->queryFactory     = $SL->getService(QueryFactory::class);
        $this->entityFactory    = $SL->getService(EntityFactory::class);
        $this->btr              = $SL->getService(BackendTranslations::class);
        $this->config           = $SL->getService(Config::class);
        $this->request          = $SL->getService(Request::class);

        if($this->settings->okcms__checkbox_devMode) {
            $this->baseUrl = "https://dev-api.checkbox.in.ua/api/v1/";
        } else {
            $this->baseUrl = "https://api.checkbox.in.ua/api/v1/";
        }
        $this->logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR;

        $managersEntity = $this->entityFactory->get(ManagersEntity::class);
        $this->manager = $managersEntity->findOne(['login' => $_SESSION['admin']]);
        /*if($this->settings->okcms__checkbox_cashier_per_manager) {
            $this->login = $this->manager->{Init::CHECKBOX_MANAGER_LOGIN};
            $this->password = $this->manager->{Init::CHECKBOX_MANAGER_PASSWORD};
            $this->xLicenseKey = $this->manager->{Init::CHECKBOX_MANAGER_LICENSE_KEY};
        } else {*/
        $this->login = $this->settings->{Init::CHECKBOX_MANAGER_LOGIN};
        $this->password = $this->settings->{Init::CHECKBOX_MANAGER_PASSWORD};
        $this->xLicenseKey = $this->settings->{Init::CHECKBOX_MANAGER_LICENSE_KEY};
        /*}*/
    }

    /**
     * Проверка подключения и получение токена
     */
    public function getToken(){
        if(!$this->login || !$this->password || !$this->xLicenseKey) {
            return [
                'message' => $this->btr->getTranslation('okcms__checkbox_errors_empty_params')
            ];
        }
        $url = 'cashier/signin';
        $params = [
            'login' => $this->login,
            'password' => $this->password
        ];
        $requestParams = [
            'method' => 'POST'
        ];
        $response = $this->getCurl($url, $params, $requestParams);
        $this->accessToken = $response['access_token'];
        return $response;
    }

    /**
     * Смены и кассиры
     */
    // Создание смены кассира
    public function createShift() {
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = 'shifts';
        $params = [];
        $requestParams = [
            'method' => 'POST'
        ];
        $response = $this->getCurl($url, $params, $requestParams);

        if(empty($this->errors)) {
            $this->saveShift($response);
        }
        return $response;
    }

    // Проверка статуса смены кассира
    public function checkShift($shiftId) {
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = 'shifts/' . $shiftId;
        $params = [];
        $requestParams = [
            'method' => 'GET'
        ];
        $response = $this->getCurl($url, $params, $requestParams);

        if(empty($this->errors)) {
            $this->saveShift($response);
        }
        return $response;
    }

    // проверка смен. нужна для проверки открытия/закрытия смен по крону, если происходили изменения статуса не на стороне сайта
    // метод обширный, т.к. крон-задачу можно выполнять как с помощью команды wget так и с помощью php
    public function cronCheckShifts() {
        //$shiftsEntity = $this->entityFactory->get(ShiftsEntity::class);

        // тут по умолчанию стоит лимит на последние 5 смен
        // возможно лучше уменьшить до 2-3,
        // т.к. предполагается использование крон задачи круглосуточно с периодичностью 2-5 мин
        $response = $this->getShifts();
        if(!empty($this->errors)) {
            // если случились ошибки при запросе - завершаем всю активность
            // TODO error_log
            exit;
        }

        // разворачиваем массив, потому как он по умолчанию идет в обратном порядке для выборки последних смен
        $shifts = array_reverse($response['results']);
        //$shifts = $response['results'];

        foreach ($response['results'] as $k=>$shift) {
            // особых проверок делать не нужно. все проверки происходят при отображении контента в админ-части
            $this->saveShift($shift);
        }

    }

    // Получить список последних смен
    public function getShifts() {
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = 'shifts';
        $params = [
            'desc'  => true,
            'limit' => 5
        ];
        $requestParams = [
            'method' => 'GET'
        ];
        $response = $this->getCurl($url, $params, $requestParams);

        return $response;
    }

    // Закрытие смены кассира
    public function closeShift() {
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = 'shifts/close';
        $params = [];
        $requestParams = [
            'method' => 'POST'
        ];
        $response = $this->getCurl($url, $params, $requestParams);

        if(empty($this->errors)) {
            $this->saveShift($response);
        }
        return $response;
    }

    // Сохранение смены в БД
    private function saveShift($response) {
        $shift = new \StdClass;
        if(!isset($response['id']) || empty($response['id'])) {
            return false;
        }

        $shift->shift_id = $response['id'];
        $shift->serial = isset($response['serial']) ? $response['serial'] : 0;
        $shift->status = strtoupper(isset($response['status']) ? $response['status'] : 'error');
        $shift->z_report_id = isset($response['z_report']['id']) ? $response['z_report']['id'] : '';
        if($response['opened_at']) {
            $shift->opened_at = date('Y-m-d H:i:s', strtotime('+2 hours', strtotime($response['opened_at'])));
//            $shift->opened_at = date('Y-m-d H:i:s', strtotime($response['opened_at']));
        }
        if($response['closed_at']) {
            $shift->closed_at = date('Y-m-d H:i:s', strtotime('+2 hours', strtotime($response['closed_at'])));
        }
        $shift->full_json_response = json_encode($response);

        $shiftEntity = $this->entityFactory->get(ShiftsEntity::class);
        if($issetShift = $shiftEntity->findOne(['shift_id'=>$shift->shift_id])) {
            $shiftEntity->update($issetShift->id, $shift);
        } else {
            $shiftEntity->add($shift);
        }
    }

    private function getCashier() {
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = 'cashier/me';
        $params = [];
        $requestParams = [
            'method' => 'GET'
        ];
        $response = $this->getCurl($url, $params, $requestParams);

        return $response;
    }

    /**
     * Отчеты
     */
    public function getReportTxt($reportId) {
        $fileName = $this->config->root_dir . $this->config->get('okcms__checkbox_reports') . $reportId . '.txt';
        $fileLink = $this->request->getRootUrl() . '/' . $this->config->get('okcms__checkbox_reports') . $reportId . '.txt';
        if(file_exists($fileName)) {
            return ['link' => $fileLink];
        }
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = "reports/$reportId/text";
        $params = [
        ];
        $requestParams = [
            'method' => 'GET'
        ];
        $response = $this->getCurl($url, $params, $requestParams);
        if(!$this->errors){
            file_put_contents($fileName, $response);
            return ['link' => $fileLink];
        }
        return $response;
    }

    /**
     * Чеки
     */
    public function createReceipt($orderId, $isReturn = false, $receiptId = null, $sendMessage = null) {
        $discountsEntity = $this->entityFactory->get(DiscountsEntity::class);

        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->get((int)$orderId);
        if(!$order) {
            return (object)['message' => $this->btr->getTranslation('okcms__checkbox_errors_find_order')];
        }
        $SL = ServiceLocator::getInstance();
        $ordersHelper = $SL->getService(OrdersHelper::class);
        $purchases = $ordersHelper->getOrderPurchasesList(intval($order->id));
        if(!$purchases) {
            return (object)['message' => $this->btr->getTranslation('okcms__checkbox_errors_find_purchases')];
        }

        // Если нет открытых смен - запишем пустой чек, чтоб потом его создать
        $shiftsEntity = $this->entityFactory->get(ShiftsEntity::class);
        $openedShiftsCount = $shiftsEntity->count(['OPENED'=>1]);
        if(!$openedShiftsCount) {
            return $this->saveReceipt([], $orderId, $isReturn);
        }

        $sendEmail = false;
        $sendSMS = false;
        $sendMessageSetting = (int)$this->settings->get('okcms__checkbox_sendMessage');
        $isSended = false;
        if ($sendMessage && $sendMessageSetting) {
            switch($sendMessageSetting) {
                case 1:
                    if ($order->email) {
                        $sendEmail = true;
                    }
                    break;
                case 2:
                    if ($order->phone) {
                        $sendSMS = true;
                    }
                    break;
                case 3:
                    if ($order->email) {
                        $sendEmail = true;
                    }
                    if ($order->phone) {
                        $sendSMS = true;
                    }
                    break;
                case 4:
                    if ($order->email) {
                        $sendEmail = true;
                    } elseif ($order->phone) {
                        $sendSMS = true;
                    }
                    break;
                case 5:
                    if ($order->phone) {
                        $sendEmail = true;
                    } elseif ($order->email) {
                        $sendSMS = true;
                    }
                    break;
            }
        }


        # Создаем заказ. Заполняем необходимые данные
        $goods = [];
        $discounts = [];
        $payments = [];
        $total_sum = 0;
        $cashier = $this->getCashier();
        if(!empty($this->errors)) {
            return $cashier;
        }

        // --весь этот блок нужен, чтоб при наличии украинского языка писать названия товаров в чеке на нем
        session_write_close();
        unset($_SESSION['lang_id']);
        unset($_SESSION['admin_lang_id']);

        $SL = ServiceLocator::getInstance();
        $languagesService = $SL->getService(Languages::class);
        $languages = $languagesService->getAllLanguages();
        foreach($languages as $language) {
            if($language->label == 'ua') {
                $languagesService->setLangId($language->id);
            }
        }
        $productsEntity = $this->entityFactory->get(ProductsEntity::class);
        $variantsEntity = $this->entityFactory->get(VariantsEntity::class);
        // --#

        $taxesEntity = $this->entityFactory->get(TaxesEntity::class);
        foreach ($purchases as $purchase) {
            $purchase->taxes = $taxesEntity->getProductTaxesCodes($purchase->product_id);
            // --
            if($product = $productsEntity->findOne(['id' => $purchase->product_id])) {
                $variant = $variantsEntity->findOne(['id' => $purchase->variant_id]);
                $purchase->fullProductName = $product->name . ($variant && $variant->name ? (' - ' . $variant->name) : '');
            }
            // --#
        }

        foreach($purchases as $purchase) {
            $total_sum += (float)$purchase->price * (int)$purchase->amount;

            $goodItem = [
                'good' => [
                    'code' => $purchase->variant_id,
                    'name' => $purchase->fullProductName ? $purchase->fullProductName : ($purchase->product_name . ($purchase->variant_name ? (' - ' . $purchase->variant_name) : '')),
                    'price' => $purchase->price * 100,
                    'tax' => $purchase->taxes
                ],
                'quantity' => (int)$purchase->amount * 1000
            ];
            if($isReturn) {
                $goodItem['is_return'] = true;
            }
            $goods[] = $goodItem;
        }

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $paymentMethod = $paymentsEntity->findOne(['id'=>$order->payment_method_id]);
        $payments[] = [
            'type' => $paymentMethod->{Init::CHECKBOX_PAYMENT_TYPE_FIELD} ? $paymentMethod->{Init::CHECKBOX_PAYMENT_TYPE_FIELD} : 'CASH',
            //'type' => 'CASH',
            //'value' => $total_sum*100,
            //'value' => (int)$order->total_price*100,
//            'value' => intval(round($order->total_price)*100)
            'value' => intval($total_sum*100)
        ];

        $foundOrderDiscounts = $discountsEntity->find(['entity' => 'order', 'entity_id' => $orderId]);
        foreach ($foundOrderDiscounts as $i => $foundOrderDiscount) {

            $discounts[$i]['type'] = 'DISCOUNT';
            $discounts[$i]['value'] = (float)$foundOrderDiscount->value;

            if ($foundOrderDiscount->name && $foundOrderDiscount->description) {
                $discounts[$i]['name'] = $foundOrderDiscount->name . ' ' . $foundOrderDiscount->description;
            } elseif ($foundOrderDiscount->name) {
                $discounts[$i]['name'] = $foundOrderDiscount->name;
            }

            if ($foundOrderDiscount->type == 'percent') {
                $discounts[$i]['mode'] = 'PERCENT';
            } else {
                $discounts[$i]['mode'] = 'VALUE';
            }
        }

        $orderSend = [
            'cashier_name' => $cashier['full_name'],
            'goods' => $goods,
            'payments' => $payments,
        ];
        if(!empty($discounts)) {
            $orderSend['discounts'] = $discounts;
        }
        if($sendEmail) {
            $orderSend['delivery'] = ['email' => $order->email];
            $isSended = true;
        }
        if($this->settings->okcms__checkbox_receiptText) {
            $text = $this->settings->okcms__checkbox_receiptText;
            if (strpos($text, '[order_id]') !== false) {
                $text    = str_replace('[order_id]', $order->id, $text);
            }
            $orderSend['footer'] = $text;
        }

        # ###########################################
        $url = "receipts/sell";
        $params = [
        ];
        $params = $orderSend;
        $requestParams = [
            'method' => 'POST'
        ];
        $response = $this->getCurl($url, $params, $requestParams);
        if(empty($this->errors)) {
            $receipt = $this->saveReceipt($response, $orderId, $isReturn, $receiptId);
            if ($receipt) {
                sleep(5);
                $uploadResponse = $this->getReceiptPdf($receipt['receipt_id']);
                if (!$uploadResponse['message']) {
                    $SMSText = $this->settings->get('okcms__checkbox_messageText');
                    if ($sendSMS && !empty($SMSText)) {
                        /** @var SmsFlyHelper $smsFlyHelper */
                        $smsFlyHelper = ServiceLocator::getInstance()->getService(SmsFlyHelper::class);
                        $SMSText      = $smsFlyHelper->replaceShortTemplate($order->id, null, $SMSText);
                        $smsFlyHelper->send($order->phone, $SMSText);
                        $isSended = true;
                    }
                }
            }
            if ($isSended) {
                $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);
                $receiptsEntity->update((int)$receipt['id'], ['sended' => date("Y-m-d H:i:s", time())]);
            }
            return $receipt;
            //return $this->saveReceipt($response, $orderId, $isReturn, $receiptId);
        }
        return $response;
    }
    private function saveReceipt($response, $orderId, $isReturn = false, $receiptId = null) {
        $receipt = new \StdClass;
        /*if(!isset($response['id']) || empty($response['id'])) {
            return false;
        }*/ //теперь пишем все чеки

        $receipt->order_id = $orderId;
        $receipt->is_return = (int)$isReturn;

        if(!empty($response) && $response['id']) {
            $receipt->receipt_id = $response['id'];

            if($response['created_at']) {
                $receipt->created_at = date('Y-m-d H:i:s', strtotime('+2 hours', strtotime($response['created_at'])));
            }
            if($response['updated_at']) {
                $receipt->updated_at = date('Y-m-d H:i:s', strtotime('+2 hours', strtotime($response['updated_at'])));
            }
            $receipt->full_json_response = json_encode($response);
        }

        $filter = [];
        if($receiptId) {
            $filter['id'] = $receiptId;
        } elseif(isset($receipt->receipt_id)) {
            $filter['receipt_id'] = $receipt->receipt_id;
        }

        $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);
        if(!empty($filter) && $issetReceipt = $receiptsEntity->findOne($filter)) {
            $receiptsEntity->update($issetReceipt->id, $receipt);
        } else {
            $issetReceipt = new \StdClass;
            $issetReceipt->id = $receiptsEntity->add($receipt);
        }

        if(isset($issetReceipt->id)) {
            return (array)$receiptsEntity->findOne(['id'=>$issetReceipt->id]);
        }
    }

    public function createReceiptService($value) {
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = "receipts/service";
        $params = [
            'payment' => [
                'type' => 'CASH',
                'value' => $value * 100
            ]
        ];
        $requestParams = [
            'method' => 'POST'
        ];
        $response = $this->getCurl($url, $params, $requestParams);
        if(empty($this->errors)) {
            $this->saveReceipt($response, 0);
        }
        return $response;
    }

    public function getReceiptPdf($receiptId) {
        $fileName = $this->config->root_dir . $this->config->get('okcms__checkbox_receipts') . $receiptId . '.pdf';
        $fileLink = $this->request->getRootUrl() . '/' . $this->config->get('okcms__checkbox_receipts') . $receiptId . '.pdf';
        if(file_exists($fileName)) {
            return [
                'link' => $fileLink,
                'filename' => $fileName
            ];
        }
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = "receipts/$receiptId/pdf";
        $params = [
        ];
        $requestParams = [
            'method' => 'GET',
            'Content-Type' => 'application/pdf'
        ];
        $response = $this->getCurl($url, $params, $requestParams);
        if(!$this->errors){
            file_put_contents($fileName, $response);
            return [
                'link' => $fileLink,
                'filename' => $fileName
            ];
        }
        return $response;
    }

    public function checkEmptyReceipts() {
        // Для начала проверим наличие открытой смены кассира
        $shiftsEntity = $this->entityFactory->get(ShiftsEntity::class);
        $openedShiftsCount = $shiftsEntity->count(['OPENED'=>1]);
        if(!$openedShiftsCount) {
            return false;
        }
        // Проверим наличие пустых чеков
        $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);
        $receipts = $receiptsEntity->order('id')->find(['receipt_id'=>'']);
        if(empty($receipts)) {
            return false;
        }
        foreach($receipts as $receipt) {
            $this->createReceipt($receipt->order_id, $receipt->is_return, $receipt->id);
        }

    }

    public function createReceiptsByOrderPaid($ids, $status) {
        if(!$status || !$ids || empty($ids)) {
            return false;
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);
        $receiptsEntity = $this->entityFactory->get(ReceiptsEntity::class);

        foreach($ids as $id) {
            $order = $ordersEntity->findOne(['id'=>$id]);
            if(!$order) {
                continue;
            }
            if(!$purchasesEntity->count(['order_id' => $order->id])) {
                continue;
            }
            $lastOrderReceipt = $receiptsEntity->findOne(['order_id' => $order->id]);
            if($lastOrderReceipt && (!$lastOrderReceipt->receipt_id || ($lastOrderReceipt->receipt_id && !$lastOrderReceipt->is_return))) {
                continue;
            }
            $this->createReceipt($order->id);
        }
    }

    public function getAllTaxes() {
        exit;
        $this->clearErrors();
        if(!$this->accessToken) {
            $tokenResponse = $this->getToken();
            if(!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $url = 'tax';
        $params = [];
        $requestParams = [
            'method' => 'GET'
        ];
        $response = $this->getCurl($url, $params, $requestParams);

        return $response;
    }


    private function clearErrors() {
        $this->errors = [];
    }

    private function getCurl($url, $params = [], $requestParams = []) {
        $ch = curl_init();

        if($requestParams['method'] == 'GET'){
            if(!empty($params)) {
                $url .= '?' . http_build_query($params);
            }
        } elseif(!empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
        $url = $this->baseUrl . $url;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.115 Safari/537.36");
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        if (strstr($this->baseUrl, 'https://')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestParams['method']);

        $headers = [
            "Content-Type: application/json",
            "Accept: application/json;charset=UTF-8",
        ];
        /*if($requestParams['Content-Type']) {
            $headers[] = "Content-Type: " . $requestParams['Content-Type'];
        } else {
            $headers[] = "Content-Type: application/json";
        }*/
        if($this->accessToken){
            $headers[] = "Authorization: Bearer " . $this->accessToken;
            if($this->xLicenseKey){
                $headers[] = "X-License-Key: " . $this->xLicenseKey;
            }
            if($this->xClientName){
                $headers[] = "X-Client-Name: " . $this->xClientName;
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $this->log(date("H:i:s") . " - Запрос по адресу: $url", "\n");

        $data = curl_exec($ch);

        $curlInfo = curl_getinfo($ch);

        curl_close($ch);

        return $this->getCurlResponse($data, $url, $params, $curlInfo, $headers);

    }

    private function getCurlResponse($data, $url, $params = [], $curlInfo = [], $headers = []){
        if (!$data) {
            return false;
        }
        /*
        $dataArray = explode("\r\n\r\n", $data, 2);
        list($header, $body) = $dataArray;
        // эта штука нужна, если в на стрйках curl выставлен параметр CURLOPT_HEADER, и то если передается только один блок заголовков
        */
        $body = $data;

        $response = json_decode( $body,1);
        if(isset($response['message'])){
            $this->errors['message'] = $response['message'];
            $this->errors['requestData']['url'] = $url;
            $this->errors['requestData']['headers'] = $headers;
            $this->errors['requestData']['curlInfo'] = $curlInfo;
            $this->errors['requestData']['data'] = $params;
            //$this->errors['responseHeader'] = $header;
            $this->errors['response'] = $response;

            $this->log("Ответ с ошибками");
            $this->log($this->errors);
        } else {
            $this->log("Код ответа: " . $curlInfo['http_code']);
            $this->log("Ответ: " . print_r($response, true));
            $this->log("Ответ: " . print_r($body, true));
        }
        if($curlInfo['content_type'] == 'text/html; charset=utf-8'
            || $curlInfo['content_type'] == 'text/plain; charset=utf-8'
            || strpos($curlInfo['content_type'], 'application/pdf') !== false){
            return $body;
        }
        return $response;
    }

    private function log($message, $tabs = "", $clear = false){
        if($clear){
            file_put_contents($this->logDir . DIRECTORY_SEPARATOR .date('Y-m-d').'.txt', $tabs . print_r($message, 1) . "\n");
        }else{
            file_put_contents($this->logDir . DIRECTORY_SEPARATOR .date('Y-m-d').'.txt', $tabs . print_r($message, 1) . "\n", FILE_APPEND);
        }
    }
}