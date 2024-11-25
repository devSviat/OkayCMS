<?php


namespace Okay\Modules\SimplaMarket\TelegramNotify\Backend\Controllers;


use Okay\Admin\Controllers\IndexAdmin;

class OrderNotifyTelegramAdmin extends IndexAdmin
{
    public function fetch()
    {
        if ($this->request->method('POST')) {
            $this->settings->set('notify_telegram_enabled', $this->request->post('notify_telegram_enabled'));
            $this->settings->set('notify_telegram_bot_token', $this->request->post('notify_telegram_bot_token'));

            $this->settings->set('notify_telegram_chat_id', $this->request->post('notify_telegram_chat_id'));
            $this->settings->set('notify_telegram_new_comment', $this->request->post('notify_telegram_new_comment'));
            $this->settings->set('notify_telegram_new_order_not_processed', $this->request->post('notify_telegram_new_order_not_processed'));
            $this->settings->set('notify_telegram_order_payment_message', $this->request->post('notify_telegram_order_payment_message'));
            $this->settings->set('notify_telegram_not_paid_order_message', $this->request->post('notify_telegram_not_paid_order_message'));

            $this->settings->set('notify_telegram_notify_by_time', $this->request->post('notify_telegram_notify_by_time'));
            $this->settings->set('notify_telegram_date_from', $this->request->post('notify_telegram_date_from'));
            $this->settings->set('notify_telegram_date_to', $this->request->post('notify_telegram_date_to'));

            $this->settings->set('notify_telegram_callback', $this->request->post('notify_telegram_callback'));
        }

        $this->response->setContent($this->design->fetch('telegram_notify_settings.tpl'));
    }
}