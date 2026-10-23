{* Режим підтвердження (ManagerReauth): одна смуга на сторінку замість поля пароля біля кожної дії.
   Форма шле action=reauth на поточну адресу; обробляють ManagerAdmin, ManagersAdmin і AllManagerSessionsAdmin. *}
{if $reauth_fresh}
    <div class="reauth_bar reauth_bar--open" role="status">
        <div class="reauth_bar__text">
            {include file='svg_icon.tpl' svgId='lock_open'}
            <span>{$btr->manager_reauth_open_until|escape} <span class="reauth_bar__time">{$reauth_expires_at|date_format:'%H:%M'}</span></span>
        </div>
    </div>
{else}
    <div class="reauth_bar">
        <div class="reauth_bar__text">
            {include file='svg_icon.tpl' svgId='lock_closed'}
            <span>
                <span class="reauth_bar__title">{$btr->manager_reauth_title|escape}</span>
                <span class="reauth_bar__hint">{$btr->manager_reauth_hint|escape}</span>
            </span>
        </div>
        <form method="post" class="reauth_bar__form fn_reauth_form" autocomplete="off">
            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
            {if isset($m->id)}<input type="hidden" name="id" value="{$m->id|escape}">{/if}
            <input type="hidden" name="action" value="reauth">
            <input type="hidden" name="tab" value="{$smarty.get.active_tab|escape}" class="fn_reauth_tab">
            <input class="form-control" name="reauth_password" type="password" autocomplete="current-password" placeholder="{$btr->manager_reauth_password|escape}" aria-label="{$btr->manager_reauth_password|escape}" required/>
            <button type="submit" class="btn btn_blue btn_small">{$btr->manager_reauth_confirm|escape}</button>
        </form>
    </div>
{/if}
