{* Title *}
{$meta_title = $btr->keycrm_title scope=global}

{*Вывод успешных сообщений*}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $execution_time != ''}
                            <div>{$btr->keycrm_work_orders_ok|escape}</div>
                            {if $count_work_orders > 0}
                                <div>{$btr->keycrm_work_orders|escape} {$count_work_orders} {$btr->keycrm_work_orders2|escape}</div>
                            {/if}
                            <div>{$btr->keycrm_work_orders_time|escape}: {$execution_time}</div>
                        {/if}
                    </div>
                </div>
                {if $smarty.get.return}
                    <a class="alert__button" href="{$smarty.get.return}">
                        {include file='svg_icon.tpl' svgId='return'}
                        <span>{$btr->general_back|escape}</span>
                    </a>
                {/if}
            </div>
        </div>
    </div>
{/if}

{if $message_error}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--error">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_error=='1'}
                        {else}
                            {$message_error|escape}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

{*Название страницы*}
<div class="row">
    <form id="key_crm" method="post" class="fn_form_list">
        <input type=hidden name="session_id" value="{$smarty.session.id}">

        <div class="col-lg-12 col-md-12">
            <div class="wrap_heading">
                <div class="box_heading heading_page">
                    {$btr->keycrm_title|escape}
                </div>
            </div>
            <div class="alert alert--icon">
                <div class="alert__content">
                    <div class="alert__title">{$btr->keycrm_key_description_title|escape}</div>
                    <p>{$btr->keycrm_key_description4|escape}</p>
                    <div class="alert__title">{$btr->keycrm_key_description_title2|escape}</div>
                    <p>{$btr->keycrm_key_description5|escape}</p>
                    <div class="alert__title">{$btr->keycrm_key_description_title3|escape}</div>
                    <p>{$btr->keycrm_key_description6|escape}</p>
                    <p>{$btr->keycrm_key_description7|escape}</p>
                    <p>{$btr->keycrm_key_description8|escape}</p>
                    <p>{$btr->keycrm_key_description9|escape}</p>
                </div>
            </div>

            <div class="alert alert--icon alert--warning">
                <div class="alert__content">
                    <div class="alert__title">{$btr->keycrm_key_description_attention_title}</div>
                    <p>{$btr->keycrm_key_description_attention_description}</p>
                </div>
            </div>

            <div class="alert alert--icon alert--info">
                <div class="alert__content">
                    <div class="alert__title">{$btr->keycrm_installation_title}</div>
                    <p>{$btr->keycrm_installation_description_1}</p>
                    <p>{$btr->keycrm_installation_description_2}</p>
                <p><strong>{$btr->keycrm_installation_description_3}</strong></p>
                </div>
            </div>

            <div class="okay_list col-lg-6 col-md-6 boxed" style="text-align: initial;">
                <div class="">
                    <div class="mb-1">
                        {$btr->keycrm_key|escape}
                        <input type="text" class="form-control" name="api_key_crm" value="{$api_key}">
                    </div>
                    {$btr->keycrm_key_source|escape}
                    <select name="source_crm" class="selectpicker form-control mb-1" data-live-search="true">
                        <option value="0">Dropdown list</option>
                        {foreach $sourceCRM as $source}
                            <option value="{$source->id}"
                                    {if $sourceCRMID->value == $source->id}selected {/if}>{$source->name}
                            </option>
                        {/foreach}
                    </select>

                    <div class="okay_list_boding mt-1">
                        <div class="okay_list_row fn_sort_item">
                            <span>{$btr->keycrm__amount_paket_orders|escape}</span>
                            <div class="okay_list_boding okay_list_order_stg_sts_status2">
                                <select name="keycrm__amount_paket_orders"
                                        class="selectpicker form-control col-xs-12 px-0  ml-1">
                                    <option value="1" {if $settings->keycrm__amount_paket_orders == 1} selected{/if}>1
                                    </option>
                                    <option value="10" {if $settings->keycrm__amount_paket_orders == 10 || !$settings->keycrm__amount_paket_orders} selected{/if}>
                                        10
                                    </option>
                                    <option value="30" {if $settings->keycrm__amount_paket_orders == 30} selected{/if}>
                                        30
                                    </option>
                                    <option value="50" {if $settings->keycrm__amount_paket_orders == 50} selected{/if}>
                                        50
                                    </option>
                                    <option value="75" {if $settings->keycrm__amount_paket_orders == 75} selected{/if}>
                                        75
                                    </option>
                                    <option value="100" {if $settings->keycrm__amount_paket_orders == 100} selected{/if}>
                                        100
                                    </option>
                                    <option value="150" {if $settings->keycrm__amount_paket_orders == 150} selected{/if}>
                                        150
                                    </option>
                                    <option value="0" {if $settings->keycrm__amount_paket_orders == 0} selected{/if}>{$btr->keycrm__amount_paket_orders_no_select|escape}</option>
                                </select>
                            </div>
                        </div>

                        <div class="activity_of_switch_item mb-1">
                            <div class="okay_switch okay_switch--nowrap clearfix">
                                <label class="switch switch-default mr-1">
                                    <input class="switch-input" name="used_add_timer_block__date_before" value='1'
                                           type="checkbox"
                                           {if $settings->used_add_timer_block__date_before}checked=""{/if}/>
                                    <span class="switch-label"></span>
                                    <span class="switch-handle"></span>
                                </label>
                                <label class="switch_label mr-0">{$btr->used_add_timer_block__date_before}
                                    <i class="fn_tooltips"
                                       title="{$btr->used_add_timer_block__date_before_placehold|escape}">
                                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                    </i></label>

                                <div class="ml-1">
                                    <div><input name="add_timer_block__date_before" id="datetimepicker_date" readonly
                                                class="add_timer_block__date_before" type="text" value=""
                                                style="width: 120px; text-align: center;"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-1">{$btr->keycrm__count_send_orders|escape}:
                            <strong>{$count_send_orders|escape}</strong>
                            (<span>{$btr->keycrm__count_all_orders|escape}: {$count_all_orders|escape}</span>,
                            <span style="color: #e83841">{$btr->keycrm__count_error_orders|escape}: {$count_error_orders|escape}</span>)
                        </div>
                        <button type="submit" style="text-align: center;"
                                class="btn btn_small {if $count_send_orders > 0}btn_blue{/if}"
                                name="sendAllOrder" value="1" {if $count_send_orders <= 0}onclick="return false;"{/if}>
                            <span>{$btr->keycrm_key_all_order|escape}</span>
                        </button>
                    </div>

                    <div class="activity_of_switch_item mt-1">
                        <div class="okay_switch okay_switch--nowrap clearfix">
                            <label class="switch switch-default mr-1">
                                <input class="switch-input" name="keycrm__checkbox_disable_send_delivery_price" value='1' type="checkbox"
                                       {if $settings->keycrm__checkbox_disable_send_delivery_price}checked=""{/if}/>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                            <label class="switch_label mr-0">{$btr->keycrm__checkbox_disable_send_delivery_price}
                                {*<i class="fn_tooltips" title="{$btr->keycrm__checkbox_disable_send_delivery_price|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>*}</label>
                        </div>
                    </div>

                    <div class="activity_of_switch_item mt-1">
                        <div class="okay_switch okay_switch--nowrap clearfix">
                            <label class="switch switch-default mr-1">
                                <input class="switch-input" name="keycrm__checkbox_disable_send_separate_delivery_price" value='1' type="checkbox"
                                       {if $settings->keycrm__checkbox_disable_send_separate_delivery_price}checked=""{/if}/>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                            <label class="switch_label mr-0">{$btr->keycrm__checkbox_disable_send_separate_delivery_price}
                                {*<i class="fn_tooltips" title="{$btr->keycrm__checkbox_disable_send_separate_delivery_price|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>*}</label>
                        </div>
                    </div>

                    <div class="activity_of_switch_item mt-2">
                        <div class="okay_switch okay_switch--nowrap clearfix">
                            <label class="switch switch-default mr-1">
                                <input class="switch-input" name="keycrm__send_status_when_update" value='1' type="checkbox"
                                       {if $settings->keycrm__send_status_when_update}checked=""{/if}/>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                            <label class="switch_label mr-0">{$btr->keycrm__send_status_when_update}
                                <i class="fn_tooltips" title="{$btr->keycrm__send_status_when_update_info|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i></label>
                        </div>
                    </div>
                </div>

                    <div class="activity_of_switch_item mt-2">
                        <div class="okay_switch okay_switch--nowrap clearfix">
                            <label class="switch switch-default mr-1">
                                <input class="switch-input" name="keycrm__activate_debug" value='1' type="checkbox"
                                       {if $settings->keycrm__activate_debug}checked=""{/if}/>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                            <label class="switch_label mr-0">{$btr->keycrm__activate_debug}
                                <i class="fn_tooltips" title="{$btr->keycrm__activate_debug_info|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i></label>
                        </div>
                    </div>

                <div class="col-lg-12 col-md-12 mt-2">
                    <button type="submit" class="btn btn_small btn_blue float-md-right">
                        <span>{$btr->general_apply|escape}</span>
                    </button>
                </div>
            </div>

            <div class="okay_list col-lg-6 col-md-6 boxed">
                {* payment methods*}
                <div class="alert__title">{$btr->keycrm_key_description_payment_method_title}</div>
                <div class="okay_list_head">
                    <div class="okay_list_heading col-lg-6 col-md-6">{$btr->general_name|escape}</div>
                    <div class="okay_list_heading col-lg-6 col-md-6">{$btr->keycrm__slect_assotiation|escape}</div>
                    <div class="okay_list_heading okay_list_close"></div>
                </div>
                <div class="fn_status_list fn_sort_list okay_list_body sortable">
                    {if $payment_methods}
                        {foreach $payment_methods as $payment_method}
                            <div class="fn_row okay_list_body_item">
                                <div class="okay_list_row fn_sort_item">
                                    <div class="okay_list_boding col-lg-6 col-md-6">
                                        <input type="hidden" name="payment[id][]" value="{$payment_method->id}">
                                        <input type="text" class="form-control" name="payment[name][]" readonly
                                               value="{$payment_method->name|escape}">
                                    </div>
                                    <div class="okay_list_boding col-lg-6 col-md-6">
                                        <select name="payment[is_close][]"
                                                class="selectpicker form-control col-xs-12 px-0"
                                                data-live-search="true">
                                            <option value="0">Dropdown list</option>
                                            {foreach $paymentCRM as $paymentCRMSourse}
                                                <option value="{$paymentCRMSourse->id}" {if $paymentCRMSourse->id == $paymentCRMChoice[$payment_method->id]->value2 } selected {/if}>{$paymentCRMSourse->name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                            </div>
                        {/foreach}
                    {/if}
                </div>
            </div>

            <div class="okay_list col-lg-6 col-md-6 boxed">
                <div class="alert__title">{$btr->keycrm_key_description_order_statuses}</div>
                {* Order Status*}
                <div class="okay_list_head">
                    <div class="okay_list_heading col-lg-4 col-md-4">{$btr->general_name|escape}</div>
                    <div class="okay_list_heading col-lg-4 col-md-4">{$btr->general_select_action|escape}</div>
                    <div class="okay_list_heading col-lg-4 col-md-4">{$btr->keycrm__slect_assotiation|escape}
                        <i class="fn_tooltips" title="{$btr->keycrm_status_info|escape}">
                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                        </i>
                    </div>
                </div>
                <div class="fn_status_list fn_sort_list okay_list_body sortable">
                    {if $orders_statuses}
                        {foreach $orders_statuses as $order_status}
                            <div class="fn_row okay_list_body_item">
                            <div class="okay_list_row fn_sort_item">
                                <div class="okay_list_boding" style="min-width: 40px;">{$order_status->id}</div>
                                <div class="okay_list_boding col-lg-4 col-md-4">
                                    <input type="hidden" name="statuses[{$order_status->id}][id]" value="{$order_status->id}">
                                    <input type="text" class="form-control" name="statuses[{$order_status->id}][name]" readonly
                                           value="{$order_status->name|escape}">
                                </div>
                                {* <div class="okay_list_boding okay_list_order_stg_sts_status2">*}
                                {*     <select name="statuses[is_close][]"*}
                                {*             class="selectpicker form-control col-xs-12 px-0">*}
                                {*         <option value="0">Dropdown list</option>*}
                                {*         {foreach $statusCRM as $status}*}
                                {*             <option value="{$status->id}" {if $status->id == $statusCRMChoice[$order_status->id]->value2 } selected {/if}>{$status->name}</option>*}
                                {*         {/foreach}*}
                                {*     </select>*}
                                {* </div>*}

                                <div class="okay_list_boding okay_list_order_stg_sts_status2 col-lg-4 col-md-4">
                                    <select name="statuses[{$order_status->id}][is_send]"
                                            class="selectpicker form-control col-xs-12 px-0"
                                            data-live-search="true">
                                        <option value="1"{if $order_status->sendStatusCRM == 1} selected=""{/if}>{$btr->keycrm_send_on|escape}</option>
                                        <option value="0"{if !$order_status->sendStatusCRM} selected=""{/if}>{$btr->keycrm_send_off|escape}</option>
                                    </select>
                                </div>

                                <div class="okay_list_boding col-lg-4 col-md-4">
                                    <select name="statuses[{$order_status->id}][crm_statuses]"
                                            class="selectpicker form-control col-xs-12 px-0" data-live-search="true"
                                            data-size="10">
                                        <option value="0" {if !$order_status->idCRM || $order_status->idCRM == 0}selected=""{/if}>Nothing selected</option>
                                        {foreach $crm_orders_statuses as $key => $crm_status}
                                            <option value="{$crm_status->id}"
                                                    {if $order_status->idCRM == $crm_status->id}selected=""{/if}>{$crm_status->name|escape}</option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>
                        </div>
                        {/foreach}
                        <span>{include file='svg_icon.tpl' svgId='icon_tooltips'}{$btr->keycrm_status_info|escape}</span>
                    {/if}
                </div>
            </div>

            <div class="okay_list col-lg-6 col-md-6 boxed">

                <div class="alert__title">{$btr->keycrm_key_description_delivery_title}</div>
                {*                Delivery methods*}
                <div class="okay_list_head">
                    <div class="okay_list_heading col-lg-5 col-md-5">{$btr->general_name|escape}</div>
                    <div class="okay_list_heading col-lg-5 col-md-5">{$btr->general_select_action|escape}</div>
                </div>
                <div class="fn_status_list fn_sort_list okay_list_body sortable">
                    {if $deliveries}
                        {foreach $deliveries as $deliverie}
                            <div class="fn_row okay_list_body_item">
                                <div class="okay_list_row fn_sort_item">
                                    <div class="okay_list_boding col-lg-6 col-md-6">
                                        <input type="hidden" name="deliverie[id][]" value="{$deliverie->id}">
                                        <input type="text" class="form-control" name="deliverie[name][]" readonly
                                               value="{$deliverie->name|escape}">
                                    </div>
                                    <div class="okay_list_boding col-lg-6 col-md-6">
                                        <select name="deliverie[is_close][]"
                                                class="selectpicker form-control col-xs-12 px-0"
                                                data-live-search="true">
                                            <option value="0">Dropdown list</option>
                                            {foreach $deliveryCRM as $deliveryCRMSourse}
                                                <option value="{$deliveryCRMSourse->id}" {if $deliveryCRMSourse->id == $deliveriCRMChoice[$deliverie->id]->value2 } selected {/if}>{$deliveryCRMSourse->name}</option>
                                            {/foreach}
                                        </select>
                                    </div>
                                </div>
                            </div>
                        {/foreach}
                    {/if}
                </div>
            </div>

            <div class="row" style="margin-bottom: 50px;">
                <div class="col-lg-12 col-md-12 ">
                    <button type="submit" class="btn btn_small btn_blue float-md-right">
                        <span>{$btr->general_apply|escape}</span>
                    </button>
                </div>
            </div>
    </form>
</div>

<link rel="stylesheet"
      href="{$rootUrl}/Okay/Modules/SimplaMarket/KeyCRM/Backend/design/css/jquery.datetimepicker.css">
<script src="{$rootUrl}/Okay/Modules/SimplaMarket/KeyCRM/Backend/design/js/jquery.datetimepicker.js"></script>

<script>
    $(document).ready(function () {
        let timePickerInput = $(document).find('#datetimepicker_date');
        let value = '{if $settings->add_timer_block__date_before}{$settings->add_timer_block__date_before}{else}{$smarty.now|date_format:'%d.%m.%Y'}{/if}';

        timePickerInput.datetimepicker({
            dayOfWeekStart: 1,
            step: 10,
            timepicker: false,
            format: 'd.m.Y',
            value: value,
            lang: 'ru',
            ignoreReadonly: false,
        });
    });
</script>
