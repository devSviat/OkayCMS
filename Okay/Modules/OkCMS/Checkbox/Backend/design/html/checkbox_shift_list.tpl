<div class="okay_list_row">

    <div class="okay_list_boding okcms__checkbox_list_cell">{$closedShift->opened_at|date_format:"H:i:s d.m.Y"}</div>
    <div class="okay_list_boding okcms__checkbox_list_cell">{$closedShift->closed_at|date_format:"H:i:s d.m.Y"}</div>
    <div class="okay_list_boding okcms__checkbox_list_cell">{$closedShift->status|escape}</div>

    <div class="okay_list_setting okcms__checkbox_list_cell">
        {if $closedShift->z_report_id}
{*            <a data-hint="{$btr->okcms__checkbox_showReport|escape}" class="setting_icon setting_icon_open hint-bottom-middle-t-info-s-small-mobile  hint-anim js-okcms-checkbox-action-shift" href="{url_generator route="OkCMS_Checkbox_getShiftReport" absolute=1}" data-id="{$closedShift->z_report_id|escape}">*}
{*                {include file='svg_icon.tpl' svgId='eye'}*}
{*            </a>*}
{*            <a data-hint="{$btr->okcms__checkbox_showReport|escape}" class="setting_icon setting_icon_open hint-bottom-middle-t-info-s-small-mobile  hint-anim js-okcms-checkbox-action-shift" href="{url_generator route="OkCMS_Checkbox_getShiftReport" absolute=1}" data-id="{$closedShift->z_report_id|escape}" data-print="1">*}
{*                {include file='svg_icon.tpl' svgId='print'}*}
{*            </a>*}
        {/if}
        <a data-hint="{$btr->okcms__checkbox_refresh|escape}" class="setting_icon setting_icon_open hint-bottom-middle-t-info-s-small-mobile  hint-anim js-okcms-checkbox-action-shift" href="{url_generator route="OkCMS_Checkbox_updateShift" absolute=1}" data-id="{$closedShift->shift_id|escape}" data-replace=".js-okcms-checkbox-shift-replace">
            {include file='svg_icon.tpl' svgId='refresh_icon'}
        </a>
    </div>
    <div class="okay_list_heading okay_list_close"></div>

</div>