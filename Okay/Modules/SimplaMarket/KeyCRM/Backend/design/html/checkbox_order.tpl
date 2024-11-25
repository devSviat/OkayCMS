<div>
    <div class="activity_of_switch_item mt-1">
        <div class="okay_switch okay_switch--nowrap clearfix">
            <label class="switch switch-default mr-1">
                <input class="switch-input" name="send_to_CRM" value="send_to_CRM"
                       type="checkbox"
                       {if !empty($crm_info)}checked=""{/if}/>
                <span class="switch-label"></span>
                <span class="switch-handle"></span>
            </label>
            <label class="switch_label mr-0">{$btr->keycrm__send_to_CRM}{if $crm_info->idCRM} № <strong>{$crm_info->idCRM|escape}</strong>{/if}</label>
        </div>
        {if !empty($crm_info)}<div>{$btr->keycrm__sent_already_to_CRM|escape} {$crm_info->updated_at|date_format:"d.m.Y H:i:s"}
            <i class="fn_tooltips"
               title="{$btr->keycrm__send_to_CRM_placehold|escape}">
                {include file='svg_icon.tpl' svgId='icon_tooltips'}
            </i>
        </div>{/if}
    </div>
</div>