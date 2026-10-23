{if $m->login}
    {$meta_title = $m->login scope=global}
{else}
    {$meta_title = $btr->manager_new scope=global}
{/if}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {if $is_new}{$btr->manager_add|escape}{elseif $not_found}{$btr->manager_not_found|escape}{else}{$m->login|escape}{/if}
            </div>
            {if $m->comment}
                <div class="text_grey">{$m->comment|escape}</div>
            {/if}
        </div>
    </div>
</div>

{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'added'}{$btr->manager_added|escape}
                        {elseif $message_success == 'password_changed'}{$btr->manager_password_changed|escape}
                        {elseif $message_success == 'permissions_saved'}{$btr->manager_permissions_saved|escape}
                        {elseif $message_success == 'session_revoked'}{$btr->manager_sessions_revoked|escape}
                        {elseif $message_success == 'sessions_revoked'}{$btr->manager_sessions_revoked_many|escape}
                        {else}{$btr->manager_updated|escape}{/if}
                    </div>
                </div>
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
                        {if $message_error == 'login_exists'}{$btr->manager_exists|escape}
                        {elseif $message_error == 'empty_login'}{$btr->manager_enter_login|escape}
                        {elseif $message_error == 'email_invalid'}{$btr->manager_email_invalid|escape}
                        {elseif $message_error == 'password_mismatch'}{$btr->manager_pass_not_match|escape}
                        {elseif $message_error == 'password_empty' || $message_error == 'password_short'}{$btr->manager_password_short|escape} {$password_min_length|escape}
                        {elseif $message_error == 'password_wrong'}{$btr->manager_reauth_wrong|escape}
                        {elseif $message_error == 'reauth_required'}{$btr->manager_reauth_required|escape}
                        {elseif $message_error == 'limit_try'}{$btr->auth_limit_try|escape}
                        {else}{$btr->manager_save_failed|escape}{/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

{if !$not_found}
    {* Режим підтвердження: одна панель на сторінку замість поля пароля біля кожної дії (ManagerReauth) *}
    <div class="boxed">
        {if $reauth_fresh}
            <div class="text_success">
                {include file='svg_icon.tpl' svgId='checked'}
                {$btr->manager_reauth_open_until|escape} {$reauth_expires_at|date_format:'%H:%M'}
            </div>
        {else}
            <form method="post" class="fn_reauth_form" autocomplete="off">
                <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                <input type="hidden" name="id" value="{$m->id|escape}">
                <input type="hidden" name="action" value="reauth">
                <input type="hidden" name="tab" value="{$smarty.get.active_tab|escape}" class="fn_reauth_tab">
                <div class="heading_label">{$btr->manager_reauth_title|escape}</div>
                <div class="text_grey font_12 mb-h">{$btr->manager_reauth_hint|escape}</div>
                <div class="row">
                    <div class="col-lg-4 col-md-6 mb-h">
                        <input class="form-control" name="reauth_password" type="password" autocomplete="current-password" placeholder="{$btr->manager_reauth_password|escape}" required/>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <button type="submit" class="btn btn_small btn_blue">{$btr->manager_reauth_confirm|escape}</button>
                    </div>
                </div>
            </form>
        {/if}
    </div>
{/if}

{if $not_found}
    {* менеджера з таким id немає *}
{elseif $is_new}
    {* Новий менеджер: одна форма — дані, пароль і права разом *}
    <form method="post" autocomplete="off">
        <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
        <input type="hidden" name="action" value="create">
        <div class="row">
            <div class="col-lg-6 col-md-12 pr-0">
                <div class="boxed">
                    <div class="heading_box">{$btr->manager_basic|escape}</div>
                    <div class="mb-1">
                        <div class="heading_label">{$btr->manager_login|escape}</div>
                        <input class="form-control" name="login" type="text" autocomplete="off" value="" required/>
                    </div>
                    <div class="mb-1">
                        <div class="heading_label">{$btr->manager_email|escape}</div>
                        <input class="form-control" name="email" type="email" autocomplete="off" value=""/>
                    </div>
                    <div class="mb-1">
                        <div class="heading_label">{$btr->manager_language|escape}</div>
                        <select name="manager_lang" class="selectpicker form-control">
                            {foreach $btr_languages as $name => $label}
                                <option value="{$label|escape}">{$name|escape}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="mb-1">
                        <div class="heading_label">{$btr->general_comment|escape}</div>
                        <input class="form-control" name="comment" type="text" value="" placeholder="{$btr->manager_example|escape}"/>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="boxed">
                    <div class="heading_box">{$btr->manager_password_heading|escape}</div>
                    <div class="mb-1">
                        <div class="heading_label">{$btr->manager_pass|escape}</div>
                        <input class="form-control" name="new_password" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required/>
                        <div class="text_grey font_12 mt-q">{$btr->manager_password_short|escape} {$password_min_length|escape}</div>
                    </div>
                    <div class="mb-1">
                        <div class="heading_label">{$btr->manager_pass_repeat|escape}</div>
                        <input class="form-control" name="new_password_check" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required/>
                    </div>
                </div>
            </div>
        </div>
        {include file='manager_permissions.tpl'}
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <button type="submit" class="btn btn_small btn_blue float-md-right" {if !$reauth_fresh}disabled title="{$btr->manager_reauth_required|escape}"{/if}>
                    {include file='svg_icon.tpl' svgId='checked'}
                    <span>{$btr->manager_create|escape}</span>
                </button>
            </div>
        </div>
    </form>
{else}
    <div class="boxed tabs">
        <div class="heading_tabs">
            <div class="tab_navigation">
                <a href="#profile" class="tab_navigation_link">{$btr->manager_tab_profile|escape}</a>
                <a href="#security" class="tab_navigation_link">{$btr->manager_tab_security|escape}</a>
                {if $can_edit_permissions}
                    <a href="#permissions" class="tab_navigation_link">{$btr->manager_rights|escape}</a>
                {/if}
            </div>
        </div>
        <div class="tab_container">

            {* Профіль. Логін і email — чутливі (вхід і відновлення пароля), решта — ні *}
            <div id="profile" class="tab">
                <form method="post" autocomplete="off">
                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                    <input type="hidden" name="id" value="{$m->id|escape}">
                    <input type="hidden" name="action" value="profile">
                    <div class="row">
                        <div class="col-lg-6 col-md-12">
                            <div class="mb-1">
                                <div class="heading_label">{$btr->manager_login|escape}</div>
                                <input class="form-control" name="login" type="text" autocomplete="off" value="{$m->login|escape}" required {if !$reauth_fresh}readonly title="{$btr->manager_reauth_required|escape}"{/if}/>
                            </div>
                            <div class="mb-1">
                                <div class="heading_label">{$btr->manager_email|escape}</div>
                                <input class="form-control" name="email" type="email" autocomplete="off" value="{$m->email|escape}" {if !$reauth_fresh}readonly title="{$btr->manager_reauth_required|escape}"{/if}/>
                                <div class="text_grey font_12 mt-q">{$btr->manager_email_hint|escape}{if !$reauth_fresh} {$btr->manager_reauth_to_edit|escape}{/if}</div>
                            </div>
                            <div class="mb-1">
                                <div class="heading_label">{$btr->last_activity|escape}</div>
                                <input class="form-control" type="text" disabled value="{$m->last_activity|date}|{$m->last_activity|time}"/>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <div class="mb-1">
                                <div class="heading_label">{$btr->manager_language|escape}</div>
                                <select name="manager_lang" class="selectpicker form-control">
                                    {foreach $btr_languages as $name => $label}
                                        <option value="{$label|escape}" {if $m->lang == $label}selected{/if}>{$name|escape}</option>
                                    {/foreach}
                                </select>
                            </div>
                            <div class="mb-1">
                                <div class="heading_label">{$btr->general_comment|escape}</div>
                                <input class="form-control" name="comment" type="text" value="{$m->comment|escape}" placeholder="{$btr->manager_example|escape}"/>
                            </div>
                            <div class="mb-1">
                                <div class="heading_label">{$btr->manager_sidebar|escape}</div>
                                <select name="menu_status" class="selectpicker form-control">
                                    <option value="1" {if $m->menu_status == 1}selected{/if}>{$btr->manager_open|escape}</option>
                                    <option value="0" {if $m->menu_status == 0}selected{/if}>{$btr->manager_closed|escape}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12 col-md-12">
                            <button type="submit" class="btn btn_small btn_blue float-md-right">
                                {include file='svg_icon.tpl' svgId='checked'}
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                    </div>
                </form>
                <form method="post" class="mt-1">
                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                    <input type="hidden" name="id" value="{$m->id|escape}">
                    <button type="submit" class="btn btn_small btn_blue" name="action" value="reset_menu">
                        {include file='svg_icon.tpl' svgId='refresh_icon'}
                        <span>{$btr->manager_reset_menu|escape}</span>
                    </button>
                </form>
            </div>

            {* Безпека: пароль і сесії *}
            <div id="security" class="tab">
                <div class="row">
                    <div class="col-lg-5 col-md-12 mb-1">
                        <div class="heading_box">{$btr->manager_password_heading|escape}</div>
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                            <input type="hidden" name="id" value="{$m->id|escape}">
                            <input type="hidden" name="action" value="password">
                            {if $is_self}
                                {* Свій пароль — завжди з поточним, незалежно від вікна підтвердження *}
                                <div class="mb-1">
                                    <div class="heading_label">{$btr->manager_current_password|escape}</div>
                                    <input class="form-control" name="current_password" type="password" autocomplete="current-password" required/>
                                </div>
                            {/if}
                            <div class="mb-1">
                                <div class="heading_label">{$btr->manager_new_password|escape}</div>
                                <input class="form-control" name="new_password" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required/>
                                <div class="text_grey font_12 mt-q">{$btr->manager_password_short|escape} {$password_min_length|escape}</div>
                            </div>
                            <div class="mb-1">
                                <div class="heading_label">{$btr->manager_pass_repeat|escape}</div>
                                <input class="form-control" name="new_password_check" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required/>
                            </div>
                            <div class="text_grey font_12 mb-1">
                                {if $is_self}{$btr->manager_password_ends_others|escape}{else}{$btr->manager_password_ends_all|escape}{/if}
                            </div>
                            <button type="submit" class="btn btn_small btn_blue" {if !$is_self && !$reauth_fresh}disabled title="{$btr->manager_reauth_required|escape}"{/if}>{$btr->manager_change_password|escape}</button>
                        </form>
                    </div>

                    <div class="col-lg-7 col-md-12">
                        <div class="heading_box">{$btr->manager_sessions_heading|escape}</div>
                        {if !$can_see_sessions}
                            <div class="text_grey">{$btr->manager_sessions_no_access|escape}</div>
                        {elseif !$sessions}
                            <div class="text_grey">{$btr->manager_sessions_none|escape}</div>
                        {else}
                            <div class="text_grey font_12 mb-1">{$btr->manager_sessions_hint|escape}</div>
                            {* Свої сесії завершуються без пароля, чужі — у вікні підтвердження *}
                            {$locked = !$is_self && !$reauth_fresh}
                            <div class="okay_list">
                                <div class="okay_list_body">
                                    {foreach $sessions as $s}
                                        <div class="okay_list_body_item">
                                            <div class="okay_list_row">
                                                <div class="okay_list_boding" style="flex: 1 1 auto; min-width: 0; text-align: left">
                                                    <div class="text_600" title="{$s->user_agent|escape}">
                                                        {$s->device|escape}
                                                        {if $s->id == $current_session_id}<span class="tag tag-success ml-h">{$btr->manager_sessions_current|escape}</span>{/if}
                                                    </div>
                                                    <div class="text_grey font_12">
                                                        {$s->ip|escape} · {$btr->manager_sessions_started|escape} {$s->created_at|escape} · {$btr->manager_sessions_last_seen|escape} {$s->last_seen_at|escape}
                                                    </div>
                                                </div>
                                                <div class="okay_list_boding" style="flex: 0 0 auto">
                                                    {if $s->id != $current_session_id}
                                                        <form method="post">
                                                            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                                                            <input type="hidden" name="id" value="{$m->id|escape}">
                                                            <input type="hidden" name="action" value="revoke_session">
                                                            <input type="hidden" name="session" value="{$s->id|escape}">
                                                            <button type="submit" class="btn btn_small btn-outline-danger" {if $locked}disabled title="{$btr->manager_reauth_required|escape}"{/if}>{$btr->manager_sessions_revoke|escape}</button>
                                                        </form>
                                                    {/if}
                                                </div>
                                            </div>
                                        </div>
                                    {/foreach}
                                </div>
                            </div>

                            {if !$is_self || count($sessions) > 1}
                                <form method="post" class="mt-1">
                                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                                    <input type="hidden" name="id" value="{$m->id|escape}">
                                    <input type="hidden" name="action" value="revoke_other_sessions">
                                    <button type="submit" class="btn btn_small btn-danger" {if $locked}disabled title="{$btr->manager_reauth_required|escape}"{/if}>
                                        {if $is_self}{$btr->manager_sessions_revoke_others|escape}{else}{$btr->manager_sessions_revoke_all|escape}{/if}
                                    </button>
                                </form>
                            {/if}
                        {/if}
                    </div>
                </div>
            </div>

            {* Права доступу — лише чужого менеджера й лише з правом managers *}
            {if $can_edit_permissions}
                <div id="permissions" class="tab">
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                        <input type="hidden" name="id" value="{$m->id|escape}">
                        <input type="hidden" name="action" value="permissions">
                        {include file='manager_permissions.tpl'}
                        <div class="row">
                            <div class="col-lg-12 col-md-12">
                                <button type="submit" class="btn btn_small btn_blue float-md-right" {if !$reauth_fresh}disabled title="{$btr->manager_reauth_required|escape}"{/if}>
                                    {include file='svg_icon.tpl' svgId='checked'}
                                    <span>{$btr->general_apply|escape}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            {/if}
        </div>
    </div>

    <script>
        {* Підтвердження повертає на ту вкладку, з якої його відкрили *}
        $(document).on('click', '.tab_navigation_link', function () {
            $('.fn_reauth_tab').val(($(this).attr('href') || '').replace('#', ''));
        });
    </script>
{/if}
