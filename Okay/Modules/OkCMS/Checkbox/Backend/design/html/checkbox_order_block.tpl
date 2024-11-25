{if $order->id}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="heading_box">
                {$btr->okcms__checkbox_order_receipts|escape}
                <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                    <a class="btn-minimize" href="javascript:;" ><i class="fa fn_icon_arrow fa-angle-down"></i></a>
                </div>
            </div>
            <div class="toggle_body_wrap on fn_card">
                <div class="okay_list mb-1">
                    {$createReceipt = true}
                    <div class="okay_list_head">
                        <div class="okay_list_heading col-md-4">{$btr->okcms__checkbox_order_receipt_date|escape}</div>
                        <div class="okay_list_heading col-md-4">ID</div>
                        <div class="okay_list_heading col-md-2 txt_center">{$btr->okcms__checkbox_order_receipt_return|escape}</div>
                        <div class="okay_list_heading col-md-2 txt_center">{$btr->okcms__checkbox_order_receipt_print|escape}</div>
                    </div>
                    <div class="okay_list_body js-checkbox-order-receipts-list">
                        {foreach $orderReceipts as $orderReceipt}
                            {if $orderReceipt@last && !$orderReceipt->is_return}
                                {$createReceipt = false}
                            {/if}
                            {include "`$config->root_dir`/Okay/Modules/OkCMS/Checkbox/Backend/design/html/checkbox_order_receipt.tpl"}
                        {/foreach}
                    </div>
                </div>
                {if !$checkboxActiveShift}
                    {$btr->okcms__checkbox_errors_no_shift}
                {elseif $emptyOrderReceiptsCount}
                    {$btr->okcms__checkbox_errors_empty_receipts_isset}
                {else}
{*                    {if !$settings->okcms__checkbox_orderStatusId || $settings->okcms__checkbox_orderStatusId == $order->status_id}*}
                        <div class="row">
                            <div class="col-lg-12 col-md-12 mb-2">
                                {*if $createReceipt*}
                                    <button type="button" class="btn btn_small btn_blue float-sm-right js-checkbox-create-receipt{if !$createReceipt} hidden{/if}" data-order_id="{$order->id}" data-return="0" data-href="{url_generator route="OkCMS_Checkbox_createReceipt" absolute=1}">{$btr->okcms__checkbox_order_receipt_create|escape}</button>
                                {*else*}
                                    <button type="button" class="btn btn_small btn_blue float-sm-right js-checkbox-create-receipt{if $createReceipt} hidden{/if}" data-order_id="{$order->id}" data-return="1" data-href="{url_generator route="OkCMS_Checkbox_createReceipt" absolute=1}">{$btr->okcms__checkbox_order_receipt_create_return|escape}</button>
                                {*/if*}
                            </div>
                        </div>
{*                    {/if}*}
                {/if}
            </div>
        </div>
    </div>
{/if}