{* Title *}
{$meta_title=$btr->managers_managers scope=global}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->managers_managers|escape} - {$managers_count}
            </div>
            <div class="box_btn_heading">
                <a class="btn btn_small btn-info" href="{url controller=ManagerAdmin}">
                    {include file='svg_icon.tpl' svgId='plus'}
                    <span>{$btr->managers_add|escape}</span>
                </a>
            </div>
        </div>
    </div>
</div>

{if $message_error}
    <div class="alert alert--icon alert--error">
        <div class="alert__content">
            <div class="alert__title">
                {if $message_error == 'reauth_required'}{$btr->manager_reauth_required|escape}
                {elseif $message_error == 'password_wrong'}{$btr->manager_reauth_wrong|escape}
                {elseif $message_error == 'limit_try'}{$btr->auth_limit_try|escape}
                {else}{$btr->manager_delete_skipped|escape}{/if}
            </div>
        </div>
    </div>
{/if}

{* Видалення менеджера — у вікні підтвердження паролем (ManagersAdmin) *}
{include file='manager_reauth_panel.tpl'}

{*Главная форма страницы*}
<div class="boxed fn_toggle_wrap">
    {if $managers}
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <form method="post" class="fn_form_list" data-reauth="1">
                    <input type="hidden" name="session_id" value="{$smarty.session.id}">
                    <div class="managers_wrap okay_list">
                        {*Шапка таблицы*}
                        <div class="okay_list_head">
                            <div class="okay_list_heading okay_list_check">
                                <input class="hidden_check fn_check_all" type="checkbox" id="check_all_1" name="" value=""/>
                                <label class="okay_ckeckbox" for="check_all_1"></label>
                            </div>
                            <div class="okay_list_heading okay_list_manager_name">{$btr->general_name|escape}</div>
                            <div class="okay_list_heading okay_list_close"></div>
                        </div>

                        {*Параметры элемента*}
                        <div class="okay_list_body">
                            {foreach $managers as $manager_admin}
                                <div class="fn_row okay_list_body_item">
                                    <div class="okay_list_row">
                                        <div class="okay_list_boding okay_list_check">
                                            {if $manager_admin->can_delete}
                                                <input class="hidden_check" type="checkbox" id="id_{$manager_admin->id}" name="check[]" value="{$manager_admin->id}"/>
                                                <label class="okay_ckeckbox" for="id_{$manager_admin->id}"></label>
                                            {/if}
                                        </div>

                                        <div class="okay_list_boding okay_list_manager_name">
                                            <a class="link" href="{url controller=ManagerAdmin id=$manager_admin->id}">
                                                {$manager_admin->login|escape}
                                            </a>
                                            {if $manager_admin->comment}
                                                <span class="text_grey">{$manager_admin->comment|escape}</span>
                                            {/if}
                                        </div>

                                        {if $manager_admin->can_delete}
                                            <div class="okay_list_boding okay_list_close">
                                                {*delete*}
                                                <button data-hint="{$btr->managers_delete|escape}" type="button" class="btn_close fn_remove hint-bottom-right-t-info-s-small-mobile  hint-anim" data-toggle="modal" data-target="#fn_action_modal" onclick="success_action($(this));">
                                                    {include file='svg_icon.tpl' svgId='delete'}
                                                </button>
                                            </div>
                                        {/if}
                                    </div>
                                </div>
                            {/foreach}
                        </div>

                        {*Блок массовых действий*}
                        <div class="okay_list_footer fn_action_block">
                            <div class="okay_list_foot_left">
                                <div class="okay_list_heading okay_list_check">
                                    <input class="hidden_check fn_check_all" type="checkbox" id="check_all_2" name="" value=""/>
                                    <label class="okay_ckeckbox" for="check_all_2"></label>
                                </div>
                                <div class="okay_list_option">
                                    <select name="action" class="selectpicker">
                                        <option value="delete">{$btr->general_delete|escape}</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn_small btn_blue">
                                {include file='svg_icon.tpl' svgId='checked'}
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->managers_no|escape}</div>
        </div>
    {/if}
</div>
