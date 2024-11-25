<?php


namespace Okay\Modules\SimplaMarket\TelegramNotify\Init;


use Okay\Core\Modules\AbstractInit;
use Okay\Entities\CallbacksEntity;
use Okay\Entities\OrdersEntity;
use Okay\Helpers\CommentsHelper;
use Okay\Helpers\CommonHelper;
use Okay\Helpers\OrdersHelper;
use Okay\Helpers\UserHelper;
use Okay\Modules\SimplaMarket\TelegramNotify\Backend\Controllers\OrderNotifyTelegramAdmin;
use Okay\Modules\SimplaMarket\TelegramNotify\Extenders\NotifyExtender;
use Okay\Modules\SimplaMarket\TelegramNotify\ExtendsEntities\OrdersEntityExtend;
use Okay\Requests\CommonRequest;
use Okay\Requests\UserRequest;
use Okay\Core\Request;
use Okay\Core\Scheduler\Schedule;
use Okay\Modules\SimplaMarket\TelegramNotify\Helpers\TelegramHelper;


class  Init extends AbstractInit
{

    /**
     * Метод, который вызывается во время установки модуля
     */
    public function install()
    {
        $this->setBackendMainController('OrderNotifyTelegramAdmin');
    }

    /**
     * Метод, который вызывается для каждого модуля во время каждого запуска системы
     */
    public function init()
    {
        $this->registerBackendController('OrderNotifyTelegramAdmin');
        $this->addBackendControllerPermission('OrderNotifyTelegramAdmin', 'orders');

        $this->registerEntityFilter(
            OrdersEntity::class,
            'last_hour_orders',
            OrdersEntityExtend::class,
            'filter_last_hour_orders'
        );

        //Установим планировщик на выполнение раз в 5 минут
        $this->registerSchedule(
            (new Schedule(function (TelegramHelper $telegramHelper)
            {
                $DI = include 'Okay/Core/config/container.php';
                $requests = $DI->get(Request::class);
                $args     = $requests->getArgv();
                $telegramHelper->checkUnpaidOrders($args["root_url"]);
            }))
                ->name('Send notify telegram')
                ->time('*/5 * * * *')
                ->overlap(false)
                ->timeout(3600)
        );

        $this->registerQueueExtension(
            [OrdersHelper::class, 'finalCreateOrderProcedure'],
            [NotifyExtender::class, 'finalCreateOrderProcedure']
        );
        
        $this->registerQueueExtension(
            [CommentsHelper::class, 'addCommentProcedure'],
            [NotifyExtender::class, 'addCommentProcedure']
        );

        $this->registerQueueExtension(
            [OrdersEntity::class ,'close'],
            [NotifyExtender::class , 'informOrderClose']
        );

        $this->registerQueueExtension(
            [CommonRequest::class, 'postCallback'],
            [NotifyExtender::class, 'postCallback']
        );
    }
}