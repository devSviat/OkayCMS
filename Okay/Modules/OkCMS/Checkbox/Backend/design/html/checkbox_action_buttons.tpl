<span class="okcms__checkbox_buttons_block">
    {if $checkboxActiveShift}
        {if $checkboxActiveShift->status == 'OPENED'}
            <a class="okcms__checkbox_button_action js-okcms-checkbox-action-shift" href="{url_generator route="OkCMS_Checkbox_closeShift" absolute=1}">
                {include file='svg_icon.tpl' svgId='delete'}
                <span class="">{$btr->okcms__checkbox_closeShift|escape} ({$btr->okcms__checkbox_openedShift|escape} {$checkboxActiveShift->opened_at|date_format:"H:i d.m.Y"})</span>
            </a>
        {elseif $checkboxActiveShift->status == 'CREATED'}
            <span class="okcms__checkbox_button_action">
                {*include file='svg_icon.tpl' svgId='icon_desktop'*}
                <span class="">{$btr->okcms__checkbox_justCreatedShift|escape}</span>
            </span>
        {/if}
    {else}
        <a class="okcms__checkbox_button_action js-okcms-checkbox-action-shift" href="{url_generator route="OkCMS_Checkbox_createShift" absolute=1}">
            {include file='svg_icon.tpl' svgId='plus'}
            <span class="">{$btr->okcms__checkbox_createShift|escape}</span>
        </a>
    {/if}
</span>