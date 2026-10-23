{if $m->login}
    {$meta_title = $m->login scope=global}
{else}
    {$meta_title = $btr->manager_new scope=global}
{/if}

{* Поля логіна й email без вікна підтвердження — лише для читання, із замком (ManagerReauth) *}
{$credentials_locked = !$reauth_fresh || !$can_manage_target}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {if $is_new}{$btr->manager_add|escape}{elseif $not_found}{$btr->manager_not_found|escape}{else}{$m->login|escape}{/if}
            </div>
            {if !$is_new && !$not_found}
                <div class="box_btn_heading">
                    {if $is_self}<span class="tag tag-info">{$btr->manager_this_is_you|escape}</span>{/if}
                    {if $m->comment}<span class="text_grey ml-h">{$m->comment|escape}</span>{/if}
                </div>
            {/if}
        </div>
    </div>
</div>

{if $message_success}
    <div class="alert alert--icon alert--success">
        <div class="alert__content">
            <div class="alert__title">
                {if $message_success == 'added'}{$btr->manager_added|escape}
                {elseif $message_success == 'password_changed'}{$btr->manager_password_changed|escape}
                {elseif $message_success == 'permissions_saved'}{$btr->manager_permissions_saved|escape}
                {elseif $message_success == 'session_revoked'}{$btr->manager_sessions_revoked|escape}
                {elseif $message_success == 'sessions_revoked'}{$btr->manager_sessions_revoked_many|escape}
                {elseif $message_success == 'totp_enabled'}{$btr->manager_totp_enabled_msg|escape}
                {elseif $message_success == 'totp_disabled'}{$btr->manager_totp_disabled_msg|escape}
                {elseif $message_success == 'totp_codes_new'}{$btr->manager_totp_codes_new_msg|escape}
                {else}{$btr->manager_updated|escape}{/if}
            </div>
        </div>
    </div>
{/if}

{* Пароль підтвердження й коди 2FA показують помилку біля свого поля *}
{if $message_error && !in_array($message_error, ['password_wrong', 'limit_try', 'totp_wrong'])}
    <div class="alert alert--icon alert--error">
        <div class="alert__content">
            <div class="alert__title">
                {if $message_error == 'login_exists'}{$btr->manager_exists|escape}
                {elseif $message_error == 'empty_login'}{$btr->manager_enter_login|escape}
                {elseif $message_error == 'email_invalid'}{$btr->manager_email_invalid|escape}
                {elseif $message_error == 'password_mismatch'}{$btr->manager_pass_not_match|escape}
                {elseif $message_error == 'password_empty' || $message_error == 'password_short'}{$btr->manager_password_short|escape} {$password_min_length|escape}
                {elseif $message_error == 'password_wrong'}{$btr->manager_reauth_wrong|escape}
                {elseif $message_error == 'reauth_required'}{$btr->manager_reauth_required|escape}
                {elseif $message_error == 'no_access'}{$btr->manager_no_access|escape}
                {elseif $message_error == 'last_full_admin'}{$btr->manager_last_full_admin|escape}
                {elseif $message_error == 'totp_wrong'}{$btr->auth_2fa_wrong|escape}
                {elseif $message_error == 'totp_key_missing'}{$btr->manager_totp_key_missing|escape}
                {elseif $message_error == 'limit_try'}{$btr->auth_limit_try|escape}
                {else}{$btr->manager_save_failed|escape}{/if}
            </div>
        </div>
    </div>
{/if}

{if $not_found}
    {* менеджера з таким id немає *}
{else}

{include file='manager_reauth_panel.tpl'}

{if !$is_new && !$can_manage_target}
    <div class="alert alert--icon alert--warning">
        <div class="alert__content">
            <div class="alert__title">{$btr->manager_higher_title|escape}</div>
            <p>{$btr->manager_higher_text|escape}</p>
        </div>
    </div>
{/if}

{if $is_new}
    {* Новий менеджер: одна форма — обліковий запис, пароль і права разом *}
    <form method="post" autocomplete="off">
        <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
        <input type="hidden" name="action" value="create">
        <div class="boxed">
            <div class="row">
                <div class="col-lg-6 col-md-12">
                    <div class="heading_box mb-1">{$btr->manager_account_heading|escape}</div>
                    <div class="form-group">
                        <div class="heading_label heading_label--required"><span>{$btr->manager_login|escape}</span></div>
                        <input class="form-control" name="login" type="text" autocomplete="off" value="" required/>
                    </div>
                    <div class="form-group">
                        <div class="heading_label">{$btr->manager_email|escape}</div>
                        <div class="input-group">
                            <input class="form-control" name="email" type="email" autocomplete="off" value=""/>
                            <span class="input-group-addon">{include file='svg_icon.tpl' svgId='email'}</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="heading_label">{$btr->manager_language|escape}</div>
                        <select name="manager_lang" class="selectpicker form-control">
                            {foreach $btr_languages as $name => $label}
                                <option value="{$label|escape}">{$name|escape}</option>
                            {/foreach}
                        </select>
                    </div>
                    <div class="form-group">
                        <div class="heading_label">{$btr->general_comment|escape}</div>
                        <input class="form-control" name="comment" type="text" value="" placeholder="{$btr->manager_example|escape}"/>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12">
                    <div class="heading_box mb-1">{$btr->manager_password_heading|escape}</div>
                    <div class="form-group">
                        <div class="heading_label heading_label--required"><span>{$btr->manager_pass|escape}</span></div>
                        <input class="form-control" name="new_password" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required/>
                        <div class="text_grey font_12 mt-q">{$btr->manager_password_short|escape} {$password_min_length|escape}</div>
                    </div>
                    <div class="form-group">
                        <div class="heading_label heading_label--required"><span>{$btr->manager_pass_repeat|escape}</span></div>
                        <input class="form-control" name="new_password_check" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required/>
                    </div>
                </div>
            </div>
        </div>
        <div class="boxed">
            {include file='manager_permissions.tpl'}
            <div class="sticky_actions">
                {if !$reauth_fresh}<span class="settings_locked mb-0">{include file='svg_icon.tpl' svgId='lock_closed'}<span>{$btr->manager_reauth_locked|escape}</span></span>{/if}
                <button type="submit" class="btn btn_blue btn_small" {if !$reauth_fresh}disabled{/if}>
                    {include file='svg_icon.tpl' svgId='checked'}
                    <span>{$btr->manager_create|escape}</span>
                </button>
            </div>
        </div>
    </form>
{else}
    <div class="boxed">
        <div class="tabs">
            <div class="heading_tabs">
                <div class="tab_navigation">
                    <a href="#profile" class="heading_box tab_navigation_link">{$btr->manager_tab_profile|escape}</a>
                    <a href="#security" class="heading_box tab_navigation_link">{$btr->manager_tab_security|escape}</a>
                    {if $can_edit_permissions}
                        <a href="#permissions" class="heading_box tab_navigation_link">{$btr->manager_rights|escape}</a>
                    {/if}
                </div>
            </div>
            <div class="tab_container">

                {* Профіль. Логін і email — вхід і відновлення пароля, тож лише у вікні підтвердження *}
                <div id="profile" class="tab">
                    <form method="post" autocomplete="off">
                        <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                        <input type="hidden" name="id" value="{$m->id|escape}">
                        <input type="hidden" name="action" value="profile">
                        <div class="row">
                            <div class="col-lg-6 col-md-12">
                                <div class="heading_box mb-1">{$btr->manager_account_heading|escape}</div>
                                <div class="form-group">
                                    <div class="heading_label heading_label--required"><span>{$btr->manager_login|escape}</span></div>
                                    <div class="input-group">
                                        <input class="form-control" name="login" type="text" autocomplete="off" value="{$m->login|escape}" required {if $credentials_locked}readonly{/if}/>
                                        <span class="input-group-addon" title="{if $credentials_locked}{$btr->manager_reauth_to_edit|escape}{/if}">{if $credentials_locked}{include file='svg_icon.tpl' svgId='lock_closed'}{else}{include file='svg_icon.tpl' svgId='user2_icon'}{/if}</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="heading_label">{$btr->manager_email|escape}</div>
                                    <div class="input-group">
                                        <input class="form-control" name="email" type="email" autocomplete="off" value="{$m->email|escape}" {if $credentials_locked}readonly{/if}/>
                                        <span class="input-group-addon" title="{if $credentials_locked}{$btr->manager_reauth_to_edit|escape}{/if}">{if $credentials_locked}{include file='svg_icon.tpl' svgId='lock_closed'}{else}{include file='svg_icon.tpl' svgId='email'}{/if}</span>
                                    </div>
                                    <div class="text_grey font_12 mt-q">{$btr->manager_email_hint|escape}</div>
                                </div>
                                <div class="form-group">
                                    <div class="heading_label">{$btr->last_activity|escape}</div>
                                    <div class="text_dark">{$m->last_activity|date} {$m->last_activity|time}</div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-12">
                                <div class="heading_box mb-1">{$btr->manager_panel_heading|escape}</div>
                                <div class="form-group">
                                    <div class="heading_label">{$btr->manager_language|escape}</div>
                                    <select name="manager_lang" class="selectpicker form-control" {if !$can_manage_target}disabled{/if}>
                                        {foreach $btr_languages as $name => $label}
                                            <option value="{$label|escape}" {if $m->lang == $label}selected{/if}>{$name|escape}</option>
                                        {/foreach}
                                    </select>
                                </div>
                                <div class="form-group">
                                    <div class="heading_label">{$btr->general_comment|escape}</div>
                                    <input class="form-control" name="comment" type="text" value="{$m->comment|escape}" placeholder="{$btr->manager_example|escape}" {if !$can_manage_target}readonly{/if}/>
                                </div>
                                <div class="form-group">
                                    <div class="heading_label">{$btr->manager_sidebar|escape}</div>
                                    <select name="menu_status" class="selectpicker form-control" {if !$can_manage_target}disabled{/if}>
                                        <option value="1" {if $m->menu_status == 1}selected{/if}>{$btr->manager_open|escape}</option>
                                        <option value="0" {if $m->menu_status == 0}selected{/if}>{$btr->manager_closed|escape}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        {if $can_manage_target}
                            <div class="row">
                                <div class="col-lg-12 col-md-12">
                                    {* Свої налаштування — без підтвердження, чужі — лише у вікні *}
                                    {$profile_locked = !$is_self && !$reauth_fresh}
                                    <button type="submit" class="btn btn_blue btn_small float-md-right" {if $profile_locked}disabled title="{$btr->manager_reauth_required|escape}"{/if}>
                                        {include file='svg_icon.tpl' svgId='checked'}
                                        <span>{$btr->general_apply|escape}</span>
                                    </button>
                                    <button type="submit" form="fn_reset_menu" class="btn btn-info btn_small float-md-right mr-h" {if $profile_locked}disabled title="{$btr->manager_reauth_required|escape}"{/if}>
                                        {include file='svg_icon.tpl' svgId='refresh_icon'}
                                        <span>{$btr->manager_reset_menu|escape}</span>
                                    </button>
                                </div>
                            </div>
                        {/if}
                    </form>
                    {if $can_manage_target}
                        <form method="post" id="fn_reset_menu">
                            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                            <input type="hidden" name="id" value="{$m->id|escape}">
                            <input type="hidden" name="action" value="reset_menu">
                        </form>
                    {/if}
                </div>

                {* Безпека: пароль і сесії. Усе тут, крім зміни власного пароля, — у вікні підтвердження *}
                {* Безпека: секції налаштувань — ліворуч що це й навіщо, праворуч керування *}
                <div id="security" class="tab">
                    {* Одне пояснення на вкладку: дії нижче вимкнені, доки не підтверджено пароль *}
                    {if !$reauth_fresh && ($can_manage_target || $can_revoke_sessions)}
                        <p class="settings_locked settings_locked--tab">{include file='svg_icon.tpl' svgId='lock_closed'}<span>{$btr->manager_reauth_locked|escape}</span></p>
                    {/if}

                    <section class="settings_section{if $totp_setup} settings_section--wide{/if}">
                        <div class="settings_section__about">
                            <h2 class="settings_section__title">{$btr->manager_totp_heading|escape}</h2>
                            {if $totp_enabled}
                                <span class="tag tag-success">{$btr->manager_totp_state_on|escape}</span>
                            {else}
                                <span class="tag tag-default">{$btr->manager_totp_state_off|escape}</span>
                            {/if}
                            <p class="settings_section__hint">{$btr->manager_totp_about|escape}</p>
                        </div>
                        <div class="settings_section__body">
                            {include file='manager_two_factor.tpl'}
                        </div>
                    </section>

                    {if $totp_setup}
                        <p class="settings_locked">{$btr->manager_totp_setup_focus|escape}</p>
                    {else}
                    <section class="settings_section">
                        <div class="settings_section__about">
                            <h2 class="settings_section__title">{$btr->manager_password_heading|escape}</h2>
                            <p class="settings_section__hint">{if $is_self}{$btr->manager_password_ends_others|escape}{else}{$btr->manager_password_ends_all|escape}{/if}</p>
                        </div>
                        <div class="settings_section__body">
                            {$password_locked = !$is_self && !$reauth_fresh}
                            {if $can_manage_target}
                                <form method="post" class="settings_form" autocomplete="off">
                                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                                    <input type="hidden" name="id" value="{$m->id|escape}">
                                    <input type="hidden" name="action" value="password">
                                    {if $is_self}
                                        {* Свій пароль — завжди з поточним, навіть у вікні підтвердження *}
                                        <div class="form-group">
                                            <label class="heading_label heading_label--required" for="fn_current_password"><span>{$btr->manager_current_password|escape}</span></label>
                                            <input class="form-control" id="fn_current_password" name="current_password" type="password" autocomplete="current-password" required/>
                                        </div>
                                    {/if}
                                    <div class="form-group">
                                        <label class="heading_label heading_label--required" for="fn_new_password"><span>{$btr->manager_new_password|escape}</span></label>
                                        <input class="form-control" id="fn_new_password" name="new_password" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required aria-describedby="fn_password_rule" {if $password_locked}disabled{/if}/>
                                        <div class="text_grey font_12 mt-q" id="fn_password_rule">{$btr->manager_password_short|escape} {$password_min_length|escape}</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="heading_label heading_label--required" for="fn_new_password_check"><span>{$btr->manager_pass_repeat|escape}</span></label>
                                        <input class="form-control" id="fn_new_password_check" name="new_password_check" type="password" autocomplete="new-password" minlength="{$password_min_length|escape}" required {if $password_locked}disabled{/if}/>
                                    </div>
                                    <button type="submit" class="btn btn_blue btn_small" {if $password_locked}disabled{/if}>{$btr->manager_change_password|escape}</button>
                                </form>
                            {else}
                                <p class="text_grey">{$btr->manager_higher_text|escape}</p>
                            {/if}
                        </div>
                    </section>

                    <section class="settings_section">
                        <div class="settings_section__about">
                            <h2 class="settings_section__title">{$btr->manager_sessions_heading|escape}</h2>
                            <p class="settings_section__hint">{$btr->manager_sessions_hint|escape}</p>
                        </div>
                        <div class="settings_section__body">
                            {if !$can_see_sessions}
                                <p class="text_grey">{$btr->manager_sessions_no_access|escape}</p>
                            {elseif !$sessions}
                                <p class="text_grey">{$btr->manager_sessions_none|escape}</p>
                            {else}
                                {$locked = !$can_revoke_sessions || !$reauth_fresh}
                                <ul class="session_list">
                                    {foreach $sessions as $s}
                                        <li class="session_list__item">
                                            <div class="session_list__main">
                                                <div class="session_list__device" title="{$s->user_agent|escape}">
                                                    {if $s->is_mobile}{include file='svg_icon.tpl' svgId='phone'}{/if}
                                                    <span>{$s->device|escape}</span>
                                                    {if $s->id == $current_session_id}<span class="tag tag-success">{$btr->manager_sessions_current|escape}</span>{/if}
                                                </div>
                                                <div class="session_list__meta">
                                                    {$s->ip|escape} · {$btr->manager_sessions_started|escape} {$s->created_at|date} {$s->created_at|time} · {$btr->manager_sessions_last_seen|escape} {$s->last_seen_at|date} {$s->last_seen_at|time}
                                                </div>
                                            </div>
                                            {if $s->id != $current_session_id && $can_revoke_sessions}
                                                <form method="post" class="session_list__action">
                                                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                                                    <input type="hidden" name="id" value="{$m->id|escape}">
                                                    <input type="hidden" name="action" value="revoke_session">
                                                    <input type="hidden" name="session" value="{$s->id|escape}">
                                                    <button type="submit" class="btn btn-info btn_mini" {if $locked}disabled{/if}>{$btr->manager_sessions_revoke|escape}</button>
                                                </form>
                                            {/if}
                                        </li>
                                    {/foreach}
                                </ul>
                                {if $can_revoke_sessions && (!$is_self || count($sessions) > 1)}
                                    <form method="post" class="settings_form__actions">
                                        <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                                        <input type="hidden" name="id" value="{$m->id|escape}">
                                        <input type="hidden" name="action" value="revoke_other_sessions">
                                        <button type="submit" class="btn btn-outline-danger btn_small" {if $locked}disabled{/if}>
                                            {if $is_self}{$btr->manager_sessions_revoke_others|escape}{else}{$btr->manager_sessions_revoke_all|escape}{/if}
                                        </button>
                                    </form>
                                {/if}
                            {/if}
                        </div>
                    </section>
                    {/if}
                </div>

                {* Права доступу — лише іншого менеджера, лише з правом managers і лише нижчого чи рівного *}
                {if $can_edit_permissions}
                    <div id="permissions" class="tab">
                        <form method="post" autocomplete="off">
                            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                            <input type="hidden" name="id" value="{$m->id|escape}">
                            <input type="hidden" name="action" value="permissions">
                            {include file='manager_permissions.tpl'}
                            <div class="sticky_actions">
                                {if !$reauth_fresh}<span class="settings_locked mb-0">{include file='svg_icon.tpl' svgId='lock_closed'}<span>{$btr->manager_reauth_locked|escape}</span></span>{/if}
                                <button type="submit" class="btn btn_blue btn_small" {if !$reauth_fresh}disabled{/if}>
                                    {include file='svg_icon.tpl' svgId='checked'}
                                    <span>{$btr->general_apply|escape}</span>
                                </button>
                            </div>
                        </form>
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <script>
        {* Підтвердження повертає на ту вкладку, з якої його відкрили *}
        $(document).on('click', '.tab_navigation_link', function () {
            $('.fn_reauth_tab').val(($(this).attr('href') || '').replace('#', ''));
        });
    </script>
{/if}
{/if}
