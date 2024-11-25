{$meta_title = $btr->okcms__checkbox_title scope=global}

{*Название страницы*}
<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">
                {$btr->okcms__checkbox_receipts_title|escape}
            </div>
        </div>
    </div>
    <div class="main_header__item main_header__item--sort_date">
        <form class="box_date_filter fn_date_filter main_header__inner" method="get">
            <input type="hidden" name="controller" value="OkCMS.Checkbox.CheckboxReceiptsAdmin">
            <ul class="filter_date__list form-control">
                <li class="filter_date__item">
                    <button type="button" class="fn_last_week filter_date__button ">{$btr->orders_date_filter_last_week}</button>
                </li>
                <li class="filter_date__item">
                    <button type="button" class="fn_30_days filter_date__button">{$btr->orders_date_filter_last_30_days}</button>
                </li>
                <li class="filter_date__item">
                    <button type="button" class="fn_7_days filter_date__button">{$btr->orders_date_filter_last_7_days}</button>
                </li>
                <li class="filter_date__item">
                    <button type="button" class="fn_yesterday filter_date__button">{$btr->orders_date_filter_last_yesterday}</button>
                </li>
                <li class="filter_date__item filter_date__item--date hidden-xs-down">
                    <button class="fn_calendar filter_date__button" title="{$btr->orders_date_filter_calendar}" type="button">
                        {include file='svg_icon.tpl' svgId='date'}
                        <span class="hidden-xs-down">{$btr->orders_date_filter_calendar}</span>
                    </button>
                    <button class="btn btn_blue" type="submit">
                        <span class="hidden-sm-up">{include file='svg_icon.tpl' svgId='checked'}</span>
                        <span class="hidden-xs-down">{$btr->general_apply|escape}</span>
                    </button>
                    {*не убирает инпут из дома, просто делаем его невидимым*}
                    <input type="text" class="fn_calendar_pixel" name="" autocomplete="off" >
                </li>
            </ul>
            <input type="hidden" class="fn_from_date" name="from_date" value="{$from_date}" autocomplete="off" >
            <input type="hidden" class="fn_to_date" name="to_date" value="{$to_date}" autocomplete="off" >
        </form>
    </div>
</div>

{if $from_date || $to_date}
    <div class="boxed wrap_view_info">
        <div class="view_info_dates">
            <div class="view_info_dates__text">
                {$btr->orders_date_filter_list_orders_from|escape}
                {if $from_date}
                    {$from_date|date}
                {else}
                    {$orders_from_date|date}
                {/if}
                {$btr->orders_date_filter_list_orders_to|escape}
                {if $to_date}
                    {$to_date|date}
                {else}
                    {$orders_to_date|date}
                {/if}
            </div>
            {if $from_date || $to_date}
                <button class="fn_reset_date_filter btn btn-secondary" type="button">{$btr->orders_date_filter_list_orders_reset|escape}</button>
            {/if}
        </div>
    </div>
{/if}

<div class="boxed fn_toggle_wrap">
    {if $serviceReceipts}
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="pages_wrap okay_list">
                    {*Шапка таблицы*}
                    <div class="okay_list_head">
                        <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_receiptServiceCreatedAt|escape}</div>
                        <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_receiptServiceOperation|escape}</div>
                        <div class="okay_list_heading okcms__checkbox_list_cell">{$btr->okcms__checkbox_receiptServiceSum|escape}</div>
                        <div class="okay_list_heading okay_list_setting okcms__checkbox_list_cell">{$btr->general_activities|escape}</div>
                        <div class="okay_list_heading okay_list_close"></div>
                    </div>

                    {*Параметры элемента*}
                    <div class="okay_list_body">
                        {foreach $serviceReceipts as $serviceReceipt}
                            <div class="okay_list_row">

                                <div class="okay_list_boding okcms__checkbox_list_cell">{$serviceReceipt->created_at|date_format:"H:i:s d.m.Y"}</div>
                                <div class="okay_list_boding okcms__checkbox_list_cell">{if $serviceReceipt->full_response.type == 'SERVICE_OUT'}{$btr->okcms__checkbox_receiptServiceOperationOut|escape}{elseif $serviceReceipt->full_response.type == 'SERVICE_IN'}{$btr->okcms__checkbox_receiptServiceOperationIn|escape}{/if}</div>
                                <div class="okay_list_boding okcms__checkbox_list_cell">{if $serviceReceipt->full_response.type == 'SERVICE_OUT'}-{/if}{$serviceReceipt->full_response.sum/100}</div>

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
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm 12 txt_center">
                {include file='pagination.tpl'}
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->okcms__checkbox_receipts_no|escape}</div>
        </div>
    {/if}
</div>

<script src="design/js/jquery/datepicker/jquery.ui.datepicker-{$manager->lang}.js"></script>
<script src="design/js/jquery/datepicker/jquery.datepicker.extension.range.min.js"></script>
{literal}
<script>
    $(function () {
        function compileDateString(date) {
            let day   = String(date.getDate());
            if (day.length === 1) {
                day = `0${day}`;
            }
            let month = String(Number(date.getMonth()) + 1);
            if (month.length === 1) {
                month = `0${month}`;
            }
            const year  = String(date.getFullYear());
            return `${day}-${month}-${year}`;
        }

        if($(window).width() >= 1199 ){
            $('.fn_last_week').on('click', function() {
                const date   = new Date();
                const dateTo = compileDateString(date);
                $('.fn_to_date').val(dateTo);

                date.setDate(date.getDate() - date.getDay() + 1);
                const dateFrom = compileDateString(date);
                $('.fn_from_date').val(dateFrom);

                $('.fn_date_filter').submit();
            });

            $('.fn_30_days').on('click', function() {
                const date   = new Date();
                const dateTo = compileDateString(date);
                $('.fn_to_date').val(dateTo);

                date.setDate(date.getDate() - 30);
                const dateFrom = compileDateString(date);
                $('.fn_from_date').val(dateFrom);

                $('.fn_date_filter').submit();
            });

            $('.fn_7_days').on('click', function() {
                const date   = new Date();
                const dateTo = compileDateString(date);
                $('.fn_to_date').val(dateTo);

                date.setDate(date.getDate() - 7);
                const dateFrom = compileDateString(date);
                $('.fn_from_date').val(dateFrom);

                $('.fn_date_filter').submit();
            });

            $('.fn_yesterday').on('click', function() {
                const date   = new Date();
                date.setDate(date.getDate() - 1);

                const dateTo = compileDateString(date);
                $('.fn_to_date').val(dateTo);

                const dateFrom = compileDateString(date);
                $('.fn_from_date').val(dateFrom);

                $('.fn_date_filter').submit();
            });

            $('.fn_calendar').on('click', function() {
                $(".fn_calendar_pixel").focus();
            });

            $(".fn_calendar_pixel").datepicker({
                dateFormat: 'dd-mm-yy',
                range_multiple_max: 2,
                range: 'period',
                onSelect: function(_, __, range){
                    $('.fn_from_date').val(range.startDateText);
                    $('.fn_to_date').val(range.endDateText);
                }
            });

            $('.fn_reset_date_filter').on('click', function() {
                $('.fn_to_date').val('');
                $('.fn_from_date').val('');
                $('.fn_date_filter').submit();
            });
        }
    });
</script>
{/literal}