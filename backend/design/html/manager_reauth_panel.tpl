{* Режим підтвердження (ManagerReauth): одна смуга на сторінку замість поля пароля біля кожної дії.
   Форми шлють action=reauth / reauth_close на поточну адресу; обробляють ManagerAdmin, ManagersAdmin
   і AllManagerSessionsAdmin. Помилка пароля показується тут, біля поля, а не загальним алертом. *}
{$reauth_error = ($message_error == 'password_wrong' || $message_error == 'limit_try')}
{if $reauth_fresh}
    {* Відкрите вікно — підвищені повноваження, тож колір стану-попередження, а не успіху *}
    <div class="reauth_bar reauth_bar--open" role="status">
        <div class="reauth_bar__text">
            {include file='svg_icon.tpl' svgId='clock'}
            <span>{$btr->manager_reauth_open_until|escape} <span class="reauth_bar__time">{$reauth_expires_at|date_format:'%H:%M'}</span></span>
        </div>
        <form method="post" class="reauth_bar__form reauth_bar__form--end">
            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
            {if isset($m->id)}<input type="hidden" name="id" value="{$m->id|escape}">{/if}
            <button type="submit" name="action" value="reauth_close" class="btn btn-info btn_mini">{$btr->manager_reauth_close|escape}</button>
        </form>
    </div>
{else}
    <div class="reauth_bar{if $reauth_error} reauth_bar--error{/if}">
        <div class="reauth_bar__text">
            {include file='svg_icon.tpl' svgId='lock_closed'}
            <span>
                <span class="reauth_bar__title">{$btr->manager_reauth_title|escape}</span>
                {if $reauth_error}
                    <span class="reauth_bar__error" role="alert">{if $message_error == 'limit_try'}{$btr->auth_limit_try|escape}{else}{$btr->manager_reauth_wrong|escape}{/if}</span>
                {else}
                    <span class="reauth_bar__hint">{$btr->manager_reauth_hint|escape}</span>
                {/if}
            </span>
        </div>
        <form method="post" class="reauth_bar__form fn_reauth_form" autocomplete="off">
            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
            {if isset($m->id)}<input type="hidden" name="id" value="{$m->id|escape}">{/if}
            <input type="hidden" name="action" value="reauth">
            <input type="hidden" name="tab" value="{$smarty.get.active_tab|escape}" class="fn_reauth_tab">
            <input class="form-control" name="reauth_password" type="password" autocomplete="current-password" placeholder="{$btr->manager_reauth_password|escape}" aria-label="{$btr->manager_reauth_password|escape}" {if $reauth_error}aria-invalid="true" autofocus{/if} required/>
            <button type="submit" class="btn btn_blue btn_small">{$btr->manager_reauth_confirm|escape}</button>
        </form>
    </div>
{/if}
