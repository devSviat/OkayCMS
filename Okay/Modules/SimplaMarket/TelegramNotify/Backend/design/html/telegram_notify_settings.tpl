{$meta_title = $btr->notify_telegram_module_title|escape scope=global}

<div class="col-lg-12 col-md-12">
    <div class=" heading_page">
            {$btr->notify_telegram_module_title}
    </div>
</div>
<class class="row">
    <div class="col-lg-6">
        <div class="alert alert--icon alert--info">
            <div class="alert__content">
                <div class="alert__title mb-h">{$btr->notify_telegram_instruction_title}</div>
                <div class="text_box">
                    <div class="mb-1">
                        {$btr->notify_telegram_instruction_description}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="alert alert--icon alert--info">
            <div class="alert__content">
                <div class="alert__title mb-h">Cron</div>
                <div class="text_box">
                    <div class="mb-1">
                        {$btr->notify_telegram_instruction_description_cron}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="session_id" value="{$smarty.session.id}">

        <div class="boxed">
            <div class="col-lg-12 col-md-12 mb-1 mt-1">
                <input type="checkbox" class="" id="notify_telegram_enabled" name="notify_telegram_enabled" {if $settings->notify_telegram_enabled}checked{/if}>
                <label for="notify_telegram_check">{$btr->notify_telegram_check_label}</label>
            </div>

            <div class="col-lg-12 col-md-12 mb-1 mt-1">
                <div class="alert alert--icon alert--info">
                    <div class="alert__content">
                        <div class="alert__title mb-h">{$btr->notify_telegram_instruction_title}</div>
                        <div class="text_box">
                            {$btr->notify_telegram_sent_time_instruction}
                        </div>
                    </div>
                </div>
                <div class="alert alert--icon alert--warning">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->general_module_notice}</div>
                        <p>{$btr->notify_telegram_time_alert}</p>
                    </div>
                </div>
                <div class="col-lg-12 col-md-12 mb-1 mt-1">
                    <input type="checkbox" class="" id="notify_telgram_by_time" name="notify_telegram_notify_by_time" {if $settings->notify_telegram_notify_by_time}checked{/if}>
                    <label for="notify_telegram_check">{$btr->notify_telegram_notify_by_time}</label>
                </div>
                <div class="notify_date">
                    <label for="date_from">{$btr->notify_telegram_notify_from_time}</label>
                    <input  name="notify_telegram_date_from" id="datetimepicker_from" class="send_date_from"  type="text" value="{$settings->notify_telegram_date_from}">
                    <label for="date_from">{$btr->notify_telegram_notify_to_time}</label>
                    <input  name="notify_telegram_date_to" id="datetimepicker_to" class="send_date_to" type="text" value="{$settings->notify_telegram_date_to}">
                </div>
            </div>

            <div class="col-lg-12 col-md-12 mb-1">
                <div class="heading_label">{$btr->notify_telegram_bot_token_label}</div>
                <div class="row">
                    <div class="col-lg-5">
                        <input type="text" class="form-control" name="notify_telegram_bot_token" value="{$settings->notify_telegram_bot_token}">
                    </div>
                </div>
            </div>

            <div class="col-lg-12 col-md-12 mb-1">
                <div class="heading_label">{$btr->notify_telegram_chat_id_label}</div>
                <div class="row">
                    <div class="col-lg-5">
                        <input type="text" class="form-control" name="notify_telegram_chat_id" value="{$settings->notify_telegram_chat_id}">
                    </div>
                </div>

                <div class="col-lg-12 col-md-12 mb-1 mt-2 boxed">
                    <div class="tabs">
                        <div class="heading_tabs">
                            <div class="tab_navigation">
                                <a href="#ok_important_new_comment" class="heading_box tab_navigation_link">{$btr->notify_telegram_new_comment}</a>
                                <a href="#ok_important_new_order" class="heading_box tab_navigation_link">{$btr->notify_telegram_new_order_not_processed}</a>
                                <a href="#ok_important_order_payment_mess" class="heading_box tab_navigation_link">{$btr->notify_telegram_order_payment_message}</a>
                                <a href="#ok_important_not_paid_order_mess" class="heading_box tab_navigation_link">{$btr->notify_telegram_not_paid_order_message}</a>
                                <a href="#ok_important_callback" class="heading_box tab_navigation_link">{$btr->notify_telegram_new_callback}</a>
                            </div>
                        </div>
                        <div class="tab_container">

                            {* Уведомление об оставленном комментарии *}
                            <div id="ok_important_new_comment" class="tab">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="alert alert--icon alert--info">
                                            <div class="alert__content">
                                                <div class="alert__title mb-h">{$btr->notify_telegram_instruction_title}</div>
                                                <div class="text_box">
                                                    <div class="mb-1">
                                                        {$btr->notify_telegram_instruction_tags}
                                                    </div>
                                                    <ul>
                                                        <li><b>{literal}{$page_name}{/literal}</b> - {$btr->notify_telegram_instruction_page_name}</li>
                                                        <li><b>{literal}{$page_url}{/literal}</b> - {$btr->notify_telegram_instruction_page_url}</li>
                                                        <li><b>{literal}{$name}{/literal}</b> - {$btr->notify_telegram_instruction_name}</li>
                                                        <li><b>{literal}{$email}{/literal}</b> - Email</li>
                                                        <li><b>{literal}{$comment}{/literal}</b> - {$btr->notify_telegram_instruction_text}</li>
                                                        <li><b>{literal}{$comments_backend_url}{/literal}</b> - {$btr->notify_telegram_comments_backend_url}</li>
                                                        <li><b>{literal}{$new_str}{/literal}</b> - {$btr->notify_telegram_new_str}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="heading_label">{$btr->notify_telegram_new_comment}</div>
                                <div class="row">
                                    <div class="col-lg-5">
                                        <textarea class="form-control okay_textarea" name="notify_telegram_new_comment" cols="20" rows="5">{$settings->notify_telegram_new_comment}</textarea>
                                    </div>
                                </div>
                            </div>

                            {* Уведомление о заказе, который требует обработки *}
                            <div id="ok_important_new_order" class="tab">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="alert alert--icon alert--info">
                                            <div class="alert__content">
                                                <div class="alert__title mb-h">{$btr->notify_telegram_instruction_title}</div>
                                                <div class="text_box">
                                                    <div class="mb-1">
                                                        {$btr->notify_telegram_instruction_tags}
                                                    </div>
                                                    <ul>
                                                        <li><b>{literal}{$order_id}{/literal}</b> - {$btr->notify_telegram_instruction_order_id}</li>
                                                        <li><b>{literal}{$order_summ}{/literal}</b> - {$btr->notify_telegram_instruction_total_price}</li>
                                                        <li><b>{literal}{$name}{/literal}</b> - {$btr->notify_telegram_instruction_name}</li>
                                                        <li><b>{literal}{$email}{/literal}</b> - Email</li>
                                                        <li><b>{literal}{$order_phone}{/literal}</b> - {$btr->notify_telegram_instruction_phone}</li>
                                                        <li><b>{literal}{$is_registered}{/literal}</b> - {$btr->notify_telegram_is_registered}</li>
                                                        <li><b>{literal}{$user_group}{/literal}</b> - {$btr->notify_telegram_user_group}</li>
                                                        <li><b>{literal}{$purchases}{/literal}</b> - {$btr->notify_telegram_purchases}</li>
                                                        <li><b>{literal}{$order_payment}{/literal}</b> - {$btr->notify_telegram_order_payment_method}</li>
                                                        <li><b>{literal}{$order_backend_url}{/literal}</b> - {$btr->notify_telegram_order_backend_url}</li>
                                                        <li><b>{literal}{$new_str}{/literal}</b> - {$btr->notify_telegram_new_str}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="heading_label">{$btr->notify_telegram_new_order_not_processed}</div>
                                <div class="row">
                                    <div class="col-lg-5">
                                        <textarea class="form-control okay_textarea" name="notify_telegram_new_order_not_processed" cols="20" rows="5">{$settings->notify_telegram_new_order_not_processed}</textarea>
                                    </div>
                                </div>
                            </div>

                            {* Уведомление об оплате заказа *}
                            <div id="ok_important_order_payment_mess" class="tab">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="alert alert--icon alert--info">
                                            <div class="alert__content">
                                                <div class="alert__title mb-h">{$btr->notify_telegram_instruction_title}</div>
                                                <div class="text_box">
                                                    <div class="mb-1">
                                                        {$btr->notify_telegram_instruction_tags}
                                                    </div>
                                                    <ul>
                                                        <li><b>{literal}{$order_id}{/literal}</b> - {$btr->notify_telegram_instruction_order_id}</li>
                                                        <li><b>{literal}{$order_summ}{/literal}</b> - {$btr->notify_telegram_instruction_total_price}</li>
                                                        <li><b>{literal}{$order_payment}{/literal}</b> - {$btr->notify_telegram_order_payment_method}</li>
                                                        <li><b>{literal}{$order_phone}{/literal}</b> - {$btr->notify_telegram_instruction_phone}</li>
                                                        <li><b>{literal}{$order_backend_url}{/literal}</b> - {$btr->notify_telegram_order_backend_url}</li>
                                                        <li><b>{literal}{$new_str}{/literal}</b> - {$btr->notify_telegram_new_str}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="heading_label">{$btr->notify_telegram_order_payment_message}</div>
                                <div class="row">
                                    <div class="col-lg-5">
                                        <textarea class="form-control okay_textarea" name="notify_telegram_order_payment_message" cols="20" rows="5">{$settings->notify_telegram_order_payment_message}</textarea>
                                    </div>
                                </div>
                            </div>

                            {* Уведомление о неоплаченном заказе (CRON) *}
                            <div id="ok_important_not_paid_order_mess" class="tab">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="alert alert--icon alert--info">
                                            <div class="alert__content">
                                                <div class="alert__title mb-h">{$btr->notify_telegram_instruction_title}</div>
                                                <div class="text_box">
                                                    <div class="mb-1">
                                                        {$btr->notify_telegram_instruction_tags}
                                                    </div>
                                                    <ul>
                                                        <li><b>{literal}{$order_id}{/literal}</b> - {$btr->notify_telegram_instruction_order_id}</li>
                                                        <li><b>{literal}{$order_summ}{/literal}</b> - {$btr->notify_telegram_instruction_total_price}</li>
                                                        <li><b>{literal}{$order_payment}{/literal}</b> - {$btr->notify_telegram_order_payment_method}</li>
                                                        <li><b>{literal}{$order_phone}{/literal}</b> - {$btr->notify_telegram_instruction_phone}</li>
                                                        <li><b>{literal}{$order_backend_url}{/literal}</b> - {$btr->notify_telegram_order_backend_url}</li>
                                                        <li><b>{literal}{$new_str}{/literal}</b> - {$btr->notify_telegram_new_str}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="heading_label">{$btr->notify_telegram_order_payment_message}</div>
                                <div class="row">
                                    <div class="col-lg-5">
                                        <textarea class="form-control okay_textarea" name="notify_telegram_not_paid_order_message" cols="20" rows="5">{$settings->notify_telegram_not_paid_order_message}</textarea>
                                    </div>
                                </div>
                            </div>

                            {* Уведомление об оставленной обратной связи *}
                            <div id="ok_important_callback" class="tab">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="alert alert--icon alert--info">
                                            <div class="alert__content">
                                                <div class="alert__title mb-h">{$btr->notify_telegram_instruction_title}</div>
                                                <div class="text_box">
                                                    <div class="mb-1">
                                                        {$btr->notify_telegram_instruction_tags}
                                                    </div>
                                                    <ul>
                                                        <li><b>{literal}{$page_url}{/literal}</b> - {$btr->notify_telegram_callback_message_pageurl}</li>
                                                        <li><b>{literal}{$name}{/literal}</b> - {$btr->notify_telegram_callback_name}</li>
                                                        <li><b>{literal}{$phone}{/literal}</b> - Телефон</li>
                                                        <li><b>{literal}{$message}{/literal}</b> - {$btr->notify_telegram_callback_message}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="heading_label">{$btr->notify_telegram_new_order_not_processed}</div>
                                <div class="row">
                                    <div class="col-lg-5">
                                        <textarea class="form-control okay_textarea" name="notify_telegram_callback" cols="20" rows="5">{$settings->notify_telegram_callback}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <div class="col-lg-12 col-md-12 button--basic">
                <button type="submit" class="btn btn_small btn_blue float-md-left">{$btr->notify_telegram_button_label}</button>
            </div>

    </form>
</class>

