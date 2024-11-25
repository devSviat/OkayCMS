<?php


namespace Okay\Modules\SimplaMarket\TelegramNotify\Extenders;


use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Entities\CommentsEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\UsersEntity;
use Okay\Helpers\CommentsHelper;
use Okay\Modules\SimplaMarket\TelegramNotify\Helpers\TelegramHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Core\Settings;
use Okay\Core\Request;

class NotifyExtender implements ExtensionInterface
{
    private $telegramHelper;
    private $entityFactory;
    private $commentsHelper;
    private $design;
    private $ordersHelper;
    private $settings;
    private $lastCallbackId;
    private $backEndTranslations;
    private $request;

    public function __construct(
        TelegramHelper $telegramHelper,
        EntityFactory $entityFactory,
        CommentsHelper $commentsHelper,
        Design $design,
        OrdersHelper $ordersHelper,
        Settings $settings,
        BackendTranslations $backendTranslations,
        Request $request
    ) {
        $this->telegramHelper      = $telegramHelper;
        $this->entityFactory       = $entityFactory;
        $this->commentsHelper      = $commentsHelper;
        $this->design              = $design;
        $this->ordersHelper        = $ordersHelper;
        $this->settings            = $settings;
        $this->backEndTranslations = $backendTranslations;
        $this->request             = $request;
        $this->backEndTranslations->initTranslations($this->settings->get("email_lang"));
    }

    public function finalCreateOrderProcedure($result, $order)
    {
        $purchases = $this->ordersHelper->getOrderPurchasesList($order->id);
        $order->purchases =$purchases;
        $usersEntity = $this->entityFactory->get(UsersEntity::class);
        $user = $usersEntity->find(["id" => $order->user_id]);
        $order->user = $user;
        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $order->payment_method_name = $paymentsEntity->findOne(["id" => $order->payment_method_id]);
        $this->telegramHelper->sendNotifyAboutNewOrder($order);
    }

    public function informOrderClose($orderId){
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->findOne(["id"  => $orderId]);
        $purchases = $this->ordersHelper->getOrderPurchasesList($order->id);
        $order->purchases =$purchases;
        $usersEntity = $this->entityFactory->get(UsersEntity::class);
        $user = $usersEntity->find(["id" => $order->user_id]);
        $order->user = $user;
        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $order->payment_method_name = $paymentsEntity->findOne(["id" => $order->payment_method_id]);
        $this->telegramHelper->sendNotifyAboutOrderPayment($order);
    }

    public function addCommentProcedure($commentId)
    {
        /** @var CommentsEntity $commentsEntity */
        $commentsEntity = $this->entityFactory->get(CommentsEntity::class);
        if ($comment = $commentsEntity->findOne(['id' => $commentId])) {
            $comments = $this->commentsHelper->attachTargetEntitiesToComments([$comment]);
            $comment = reset($comments);
            $this->telegramHelper->sendNotifyAboutNewComment($comment);
        }
    }

    public function postCallback()
    {
       if(!empty($this->request->post('callback'))) {
           $this->telegramHelper->sendNotifyAboutCallBack();
       }
    }


}