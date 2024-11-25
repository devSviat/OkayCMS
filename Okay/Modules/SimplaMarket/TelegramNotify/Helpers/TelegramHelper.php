<?php


namespace Okay\Modules\SimplaMarket\TelegramNotify\Helpers;


use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Core\Router;
use Okay\Core\Settings;
use Okay\Entities\CallbacksEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Helpers\MainHelper;
use Okay\Core\BackendTranslations;
use Okay\Core\FrontTranslations;

class TelegramHelper
{
    private $settings;
    private $enabled;
    private $botToken;
    private $chatId;
    private $messageTemplate;
    private $importantEventsChatId;
    private $secondaryEventsChatId;
    private $modulesChatId;
    private $request;
    private $mainHelper;
    private $entityFactory;
    private $backendTranslation;

    public function __construct(Settings $settings , Request $request, MainHelper $mainHelper , EntityFactory $entityFactory , BackendTranslations $backendTranslation)
    {
        $this->settings = $settings;
        $this->enabled                  = $this->settings->get('notify_telegram_enabled');
        $this->botToken                 = $this->settings->get('notify_telegram_bot_token');
        $this->messageTemplate          = $this->settings->get('notify_telegram_message');
        $this->notify_telegram_chat_id  = $this->settings->get("notify_telegram_chat_id");
        $this->request                  = $request;
        $this->mainHelper               = $mainHelper;
        $this->entityFactory            = $entityFactory;
        $this->backendTranslation       = $backendTranslation;
        $this->notifyByTime             = $this->settings->get("notify_telegram_notify_by_time");
    }

    //  отправка уведомления о новом заказе
    public function sendNotifyAboutNewOrder($order)
    {
        $purchases = PHP_EOL."";
        $currency  = $this->mainHelper->getCurrentCurrency()->code;
        foreach ($order->purchases as $purchase){
            $purchases .= $purchase->product_name." - ".$purchase->undiscounted_price." $currency ".PHP_EOL;
        }

        $parts = [
            '{$order_id}' => $this->escapeMarkdown($order->id),
            // '{$order_summ}' => $this->escapeMarkdown($order->total_price),
            '{$order_summ}' => $this->escapeMarkdown(number_format($order->total_price, 0, '.', ' ')),
            '{$name}' => $this->escapeMarkdown($order->name),
            '{$email}' => $this->escapeMarkdown($order->email),
            '{$order_phone}' => $this->escapeMarkdown($order->phone),
            '{$is_registered}' => $this->escapeMarkdown(empty($order->user)? $this->backendTranslation->getTranslation("notify_telegram_not_registered") : $this->backendTranslation->getTranslation("notify_telegram_registered")),
            '{$user_group}'    => $this->escapeMarkdown(empty($order->user[0]->group_name) ? $this->backendTranslation->getTranslation('notify_telegram_user_is_not_group_member') :  $this->backendTranslation->getTranslation("notify_telegram_user_is_group_member")." {$order->user[0]->group_name} "),
            '{$purchases}'  => $this->escapeMarkdown($purchases),
            '{$order_payment}' => $this->escapeMarkdown($order->payment_method_name->name),
            '{$order_backend_url}' => $this->escapeMarkdown($this->request->getRootUrl() . "/backend/index.php?controller=OrderAdmin&id=$order->id"),
            '{$new_str}'  => $this->escapeMarkdown(PHP_EOL),
        ];

        if ($this->enabled) {
            $message = strtr($this->settings->get('notify_telegram_new_order_not_processed'), $parts);
            $this->sendMessage($message,$this->notify_telegram_chat_id);
        }
    }

    //  отправка уведомления о оплате заказа
    public function sendNotifyAboutOrderPayment($order){
        $parts = [
            '{$order_id}' => $this->escapeMarkdown($order->id),
            '{$order_summ}' => $this->escapeMarkdown($order->total_price),
            '{$order_payment}' => $this->escapeMarkdown($order->payment_method_name->name),
            '{$order_phone}' => $this->escapeMarkdown($order->phone),
            '{$order_backend_url}' => $this->escapeMarkdown($this->request->getRootUrl() . "/backend/index.php?controller=OrderAdmin&id=$order->id"),
            '{$new_str}'  => $this->escapeMarkdown(PHP_EOL),
        ];

        if ($this->enabled){
            $message = strtr($this->settings->get('notify_telegram_order_payment_message'), $parts);
            $this->sendMessage($message,$this->notify_telegram_chat_id);
        }
    }

    //  отправка уведомления о неоплаченном заказе (CRON)
    public function checkUnpaidOrders($rootUrl){
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $unpaidOrders = $ordersEntity->cols(["id","payment_method_id","total_price" ,"date" ,"phone"])->find(["status_id" => 1, "paid" => 0 , "last_hour_orders" => true]);
        $paymentMethods = $this->mainHelper->getPaymentMethods();
        foreach ($unpaidOrders as $unpaidOrder){

            foreach ($paymentMethods as $paymentMethod){
                if ($unpaidOrder->payment_method_id == $paymentMethod->id){
                    $unpaidOrder->payment_method_name = $paymentMethod->name;
                }
            }
        }
        foreach ($unpaidOrders as $unpaidOrder){

            $parts = [
                '{$order_id}' => $this->escapeMarkdown($unpaidOrder->id),
                '{$order_summ}' => $this->escapeMarkdown($unpaidOrder->total_price),
                '{$order_payment}' => $this->escapeMarkdown($unpaidOrder->payment_method_name),
                '{$order_phone}' => $this->escapeMarkdown($unpaidOrder->phone),
                '{$order_backend_url}' => $this->escapeMarkdown($rootUrl . "/backend/index.php?controller=OrderAdmin&id=$unpaidOrder->id"),
                '{$new_str}'  => $this->escapeMarkdown(PHP_EOL),
            ];

            if ($this->enabled){
                $message = strtr($this->settings->get('notify_telegram_not_paid_order_message'), $parts);
                $this->sendMessage($message,$this->notify_telegram_chat_id);
            }
        }
    }

    //  отправка уведомления о новом комментарии
    public function sendNotifyAboutNewComment($comment)
    {
        $parts = [
            '{$page_url}' => $this->escapeMarkdown($comment->type == "product" ? Router::generateUrl('product', ['url' => $comment->product->url], true, 3) : Router::generateUrl('post', ['url' => $comment->post->url], true, 3) ),
            '{$page_name}' => $this->escapeMarkdown($comment->type == "product" ? $comment->product->name : $comment->post->name  ),
            '{$name}' => $this->escapeMarkdown($comment->name),
            '{$email}' => $this->escapeMarkdown($comment->email),
            '{$comment}' => $this->escapeMarkdown($comment->text),
            '{$comments_backend_url}' => $this->escapeMarkdown($this->request->getRootUrl()."/backend/index.php?controller=CommentsAdmin&status=unapproved"),
            '{$new_str}'  => $this->escapeMarkdown(PHP_EOL),
        ];
        
        if ($this->enabled) {
            $message = strtr($this->settings->get('notify_telegram_new_comment'), $parts);
            $this->sendMessage($message , $this->notify_telegram_chat_id);
        }
    }

    //  метод отправляет сообщение о оставленной обратной связи
    public function sendNotifyAboutCallBack()
    {
        $parts = [
            '{$page_url}' => $this->request->getCurrentUrl(),
            '{$name}' => $this->request->post('callback_name'),
            '{$phone}' => $this->request->post('callback_phone'),
            '{$message}' => $this->request->post('callback_message'),

        ];

        if ($this->enabled) {
            if (!empty($this->settings->get('notify_telegram_callback'))) {
                $message = strtr($this->settings->get('notify_telegram_callback'), $parts);
                $this->sendMessage($message, $this->notify_telegram_chat_id);
            }
        }
    }

    //  метод отправки сообщений
    public function sendMessage($text,$chatId)
    {
        $data = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => "HTML",
        ];

        if ($this->notifyByTime) {
            $currentDate = strtotime(date("Y-m-d H:i:s"));
            $dateFrom    = strtotime(date('Y-m-d')  ." ". $this->settings->get("notify_telegram_date_from"));
            $dateTo      = strtotime(date('Y-m-d')  ." ". $this->settings->get("notify_telegram_date_to"));

            if ($currentDate < $dateFrom || $currentDate > $dateTo) {
                $data["disable_notification"] = true;
            }
        }

        $query = http_build_query($data);
        file_get_contents("https://api.telegram.org/{$this->botToken}/sendMessage?{$query}");
    }

    //  экранирование спецсимволов
    public function escapeMarkdown($text)
    {
        return strtr($text, [
            '_' => '\_',
            '*' => '\*',
        ]);
    }

}