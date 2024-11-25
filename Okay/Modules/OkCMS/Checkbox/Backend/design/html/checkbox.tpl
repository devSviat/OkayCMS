{$meta_title = $btr->okcms__checkbox_title scope=global}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="heading_page">{$btr->okcms__checkbox_title|escape}</div>
    </div>
</div>

{*Вывод успешных сообщений*}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'saved'}
                            {$btr->okcms__checkbox_settings_saved|escape}
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

{*Главная форма страницы*}
<form method="post" enctype="multipart/form-data">
    <input type=hidden name="session_id" value="{$smarty.session.id}">
    {*if !$settings->okcms__checkbox_cashier_per_manager*}
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="boxed fn_toggle_wrap">
                    <div class="heading_box">
                        {$btr->okcms__checkbox_title|escape}
                        <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                            <a class="btn-minimize" href="javascript:;" ><i class="fa fn_icon_arrow fa-angle-down"></i></a>
                        </div>
                    </div>
                    {*Параметры элемента*}
                    <div class="toggle_body_wrap on fn_card">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="heading_label">{$btr->okcms__checkbox_login|escape}</div>
                                <div class="mb-1">
                                    <input name="okcms__checkbox_login" class="form-control" type="text" value="{$settings->okcms__checkbox_login|escape}" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="heading_label">{$btr->okcms__checkbox_password|escape}</div>
                                <div class="mb-1">
                                    <input name="okcms__checkbox_password" class="form-control" type="text" value="{$settings->okcms__checkbox_password|escape}" />
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="heading_label">{$btr->okcms__checkbox_licenseKey|escape}</div>
                                <div class="mb-1">
                                    <input name="okcms__checkbox_licenseKey" class="form-control" type="text" value="{$settings->okcms__checkbox_licenseKey|escape}" />
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="heading_label">{$btr->okcms__checkbox_receiptText|escape}</div>
                                <div class="mb-1">
                                    {*<input name="okcms__checkbox_licenseKey" class="form-control" type="text" value="{$settings->okcms__checkbox_licenseKey|escape}" />*}
                                    <textarea name="okcms__checkbox_receiptText" class="form-control">{$settings->okcms__checkbox_receiptText|escape}</textarea>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="heading_label">{$btr->okcms__checkbox_orderAvailbaleStatus|escape}</div>
                                <div class="mb-1">
                                    <select class="form-control selectpicker" name="okcms__checkbox_orderStatusId">
                                        <option value="0">{$btr->okcms__checkbox_noStatus}</option>
                                        {foreach $orders_statuses as $orders_status}
                                            <option value="{$orders_status->id|escape}" {if $settings->okcms__checkbox_orderStatusId == $orders_status->id} selected{/if}>{$orders_status->name|escape}</option>
                                        {/foreach}
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="heading_label">{$btr->okcms__checkbox_message_howSend|escape}</div>
                                <div class="mb-1">
                                    <select class="form-control selectpicker" name="okcms__checkbox_sendMessage">
                                        <option value="0"{if !$settings->okcms__checkbox_sendMessage} selected{/if}>{$btr->okcms__checkbox_message_notSend}</option>
                                        <option value="1"{if $settings->okcms__checkbox_sendMessage == 1} selected{/if}>{$btr->okcms__checkbox_message_email}</option>
{*                                        <option value="2"{if $settings->okcms__checkbox_sendMessage == 2} selected{/if}>{$btr->okcms__checkbox_message_sms}</option>*}
{*                                        <option value="3"{if $settings->okcms__checkbox_sendMessage == 3} selected{/if}>{$btr->okcms__checkbox_message_email_sms}</option>*}
{*                                        <option value="4"{if $settings->okcms__checkbox_sendMessage == 4} selected{/if}>{$btr->okcms__checkbox_message_sms_if_not_email}</option>*}
{*                                        <option value="5"{if $settings->okcms__checkbox_sendMessage == 5} selected{/if}>{$btr->okcms__checkbox_message_email_if_not_sms}</option>*}
                                    </select>
                                </div>
                            </div>

{*                            <div class="col-md-12">*}
{*                                <div class="heading_label">{$btr->okcms__checkbox_message_text|escape}</div>*}
{*                                <div class="mb-1">*}
{*                                    *}{*<input name="okcms__checkbox_licenseKey" class="form-control" type="text" value="{$settings->okcms__checkbox_licenseKey|escape}" />*}
{*                                    <textarea name="okcms__checkbox_messageText" class="form-control okay_textarea">{$settings->okcms__checkbox_messageText|escape}</textarea>*}
{*                                </div>*}
{*                            </div>*}


                        </div>
                        <div class="row">
                            <div class="col-lg-12 col-md-12 ">
                                <button type="submit" class="btn btn_small btn_blue float-md-right">
                                    {include file='svg_icon.tpl' svgId='checked'}
                                    <span>{$btr->general_apply|escape}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {*/if*}


    {*<div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed fn_toggle_wrap min_height_210px">
                <div class="heading_box">
                    {$btr->okcms__checkbox_devMode|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><i class="fa fn_icon_arrow fa-angle-down"></i></a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="permission_block">
                        <div class="permission_boxes row">
                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <div class="permission_box ">
                                    <span title="{$btr->okcms__checkbox_cashier_per_manager|escape}">{$btr->okcms__checkbox_cashier_per_manager|escape}</span>
                                    <label class="switch switch-default">
                                        <input class="switch-input" name="okcms__checkbox_cashier_per_manager" value='1' type="checkbox" {if $settings->okcms__checkbox_cashier_per_manager}checked=""{/if}/>
                                        <span class="switch-label"></span>
                                        <span class="switch-handle"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="col-xl-6 col-lg-6 col-md-6">
                                <div class="permission_box ">
                                    <span title="{$btr->okcms__checkbox_devMode|escape}">{$btr->okcms__checkbox_devMode|escape}</span>
                                    <label class="switch switch-default">
                                        <input class="switch-input" name="okcms__checkbox_devMode" value='1' type="checkbox" {if $settings->okcms__checkbox_devMode}checked=""{/if}/>
                                        <span class="switch-label"></span>
                                        <span class="switch-handle"></span>
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="col-xs-12 clearfix"></div>
                </div>
                <div class="row">
                    <div class="col-lg-12 col-md-12 ">
                        <button type="submit" class="btn btn_small btn_blue float-md-right">
                            {include file='svg_icon.tpl' svgId='checked'}
                            <span>{$btr->general_apply|escape}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>*}
</form>


<form method="post">
    <input type=hidden name="session_id" value="{$smarty.session.id}">
    {*if !$settings->okcms__checkbox_cashier_per_manager*}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->okcms__checkbox_receipts_service|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><i class="fa fn_icon_arrow fa-angle-down"></i></a>
                    </div>
                </div>
                {*Параметры элемента*}
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-lg-6 col-md-6">
                            <div class="heading_label">{$btr->okcms__checkbox_receipts_service_value|escape}</div>
                            <div class="mb-1">
                                <input name="okcms__checkbox_receipt_value" class="form-control" type="text" value="" />
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 ">
                            <button type="submit" class="btn btn_small btn_blue float-md-right" name="receipt_service" value="1">
                                {include file='svg_icon.tpl' svgId='checked'}
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {*/if*}

</form>

{*if $serviceReceipts}
    <div class="boxed fn_toggle_wrap">

        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="pages_wrap okay_list">

                    <div class="okay_list_head">
                        <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_receiptServiceCreatedAt|escape}</div>
                        <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_receiptServiceOperation|escape}</div>
                        <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_receiptServiceSum|escape}</div>
                        <div class="okay_list_heading okay_list_setting okcms__checkbox_list_cell">{$btr->general_activities|escape}</div>
                        <div class="okay_list_heading okay_list_close"></div>
                    </div>


                    <div class="okay_list_body">
                        {foreach $serviceReceipts as $serviceReceipt}
                            <div class="okay_list_row">

                                <div class="okay_list_boding okcms__checkbox_list_cell">{$serviceReceipt->created_at|date_format:"H:i:s d.m.Y"}</div>
                                <div class="okay_list_boding okcms__checkbox_list_cell">{if $serviceReceipt->full_response.type == 'SERVICE_OUT'}{$btr->okcms__checkbox_receiptServiceOperationOut|escape}{elseif $serviceReceipt->full_response.type == 'SERVICE_IN'}{$btr->okcms__checkbox_receiptServiceOperationIn|escape}{/if}</div>
                                <div class="okay_list_boding okcms__checkbox_list_cell">{$serviceReceipt->full_response.sum/100}</div>

                                <div class="okay_list_boding okay_list_setting okcms__checkbox_list_cell">
                                    <a href="{url_generator route="OkCMS_Checkbox_getReceiptPdf" receiptId=$serviceReceipt->receipt_id absolute=1}" target="_blank" class="setting_icon setting_icon_open hint-bottom-middle-t-info-s-small-mobile  hint-anim">
                                        {include file='svg_icon.tpl' svgId='print'}
                                    </a>
                                </div>
                                <div class="okay_list_boding okay_list_close"></div>

                            </div>
                        {/foreach}
                    </div>

                </div>
            </div>
        </div>
    </div>
{/if*}


{*<div class="boxed fn_toggle_wrap">
    {if $closedShifts}
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="pages_wrap okay_list">
                        <div class="okay_list_head">
                            <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_shiftOpenedAt|escape}</div>
                            <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_shiftClosedAt|escape}</div>
                            <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_shiftStatus|escape}</div>
                            <div class="okay_list_heading okay_list_setting okcms__checkbox_list_cell">{$btr->general_activities|escape}</div>
                            <div class="okay_list_heading okay_list_close"></div>
                        </div>


                        <div class="okay_list_body">
                            {foreach $closedShifts as $closedShift}
                                <div class="okay_list_body_item js-okcms-checkbox-shift-replace">
                                    {include "checkbox_shift_list.tpl"}
                                </div>
                            {/foreach}
                        </div>

                    </div>
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->okcms__checkbox_shifts_no|escape}</div>
        </div>
    {/if}
</div>*}
<div class="boxed fn_toggle_wrap">
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--icon alert--warning mt-2 mb-0">
                <div class="alert__content" style="line-height: 1.4">
                    <div class="alert__title">Совет</div>
                    {$btr->okcms__checkbox_cron_check_shifts_1}
                    <div>
                        <a href=""  class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile" data-hint="Click to copy" data-hint-copied="✔ Copied to clipboard">php {$config->root_dir}Okay{DIRECTORY_SEPARATOR}Modules{DIRECTORY_SEPARATOR}OkCMS{DIRECTORY_SEPARATOR}Checkbox{DIRECTORY_SEPARATOR}cron{DIRECTORY_SEPARATOR}check_shifts.php</a>
                    </div>
                    {$btr->okcms__checkbox_cron_check_shifts_2}
                    <div>
                        <a href=""  class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile" data-hint="Click to copy" data-hint-copied="✔ Copied to clipboard">wget -q -O- {url_generator route="OkCMS_Checkbox_cronShiftsCheck" absolute=1}</a>
                    </div>
                    {$btr->okcms__checkbox_cron_check_shifts_3}
                </div>
            </div>
        </div>

        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--icon alert--error mt-2 mb-0">
                <div class="alert__content" style="line-height: 1.4">
                    <div class="alert__title">Важно</div>
                    {$btr->okcms__checkbox_cron_check_receipts_empty_1}
                    <div>
                        <a href=""  class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile" data-hint="Click to copy" data-hint-copied="✔ Copied to clipboard">php {$config->root_dir}Okay{DIRECTORY_SEPARATOR}Modules{DIRECTORY_SEPARATOR}OkCMS{DIRECTORY_SEPARATOR}Checkbox{DIRECTORY_SEPARATOR}cron{DIRECTORY_SEPARATOR}check_empty_receipts.php</a>
                    </div>
                    {$btr->okcms__checkbox_cron_check_receipts_empty_2}
                    <div>
                        <a href=""  class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile" data-hint="Click to copy" data-hint-copied="✔ Copied to clipboard">wget -q -O- {url_generator route="OkCMS_Checkbox_cronReceiptsCheckEmpty" absolute=1}</a>
                    </div>
                    {$btr->okcms__checkbox_cron_check_receipts_empty_3}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    sclipboard();
</script>
