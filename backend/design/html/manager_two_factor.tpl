{* Двофакторна автентифікація: тіло секції на вкладці «Безпека» (ManagerAdmin, ManagerTwoFactor).
   Назву й стан показує сама секція. *}
{if $totp_new_codes}
    {* Коди відновлення показуються один раз: у базі лише їхні хеші *}
    <div class="two_factor_codes" role="status">
        <div class="two_factor_codes__title">{$btr->manager_totp_codes_title|escape}</div>
        <p class="two_factor_codes__hint">{$btr->manager_totp_codes_hint|escape}</p>
        <ol class="two_factor_codes__list">
            {foreach $totp_new_codes as $code}
                <li>{$code|escape}</li>
            {/foreach}
        </ol>
    </div>
{/if}

{if $totp_enabled}
    <dl class="settings_facts">
        <dt>{$btr->manager_totp_since|escape}</dt>
        <dd>{$totp_enabled_at|date}</dd>
        <dt>{$btr->manager_totp_codes_left|escape}</dt>
        <dd>{$totp_codes_left|escape} / 10</dd>
    </dl>
    {if $recovery_code_used}
        <p class="settings_note">{$btr->manager_totp_recovery_used|escape}</p>
    {/if}
    {if !$totp_key_ok}
        <p class="settings_note settings_note--warning">{$btr->manager_totp_key_broken|escape}</p>
    {/if}

    {if $is_self}
        <div class="settings_form__actions">
            <form method="post" data-reauth="1">
                <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                <input type="hidden" name="id" value="{$m->id|escape}">
                <button type="submit" name="action" value="totp_codes" class="btn btn-info btn_small">
                    {$btr->manager_totp_new_codes|escape}
                </button>
            </form>
        </div>
        <form method="post" class="settings_inline" autocomplete="off" data-reauth="1">
            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
            <input type="hidden" name="id" value="{$m->id|escape}">
            <input type="hidden" name="action" value="totp_disable">
            <label class="heading_label" for="fn_totp_disable">{$btr->manager_totp_disable_label|escape}</label>
            <div class="settings_inline__row">
                <input class="form-control" id="fn_totp_disable" name="totp_code" type="text" autocomplete="one-time-code" placeholder="{$btr->manager_totp_code_or_recovery|escape}" required/>
                <button type="submit" class="btn btn-outline-danger btn_small">{$btr->manager_totp_disable|escape}</button>
            </div>
            {if $message_error == 'totp_wrong'}<p class="settings_error" role="alert">{$btr->auth_2fa_wrong|escape}</p>
            {elseif $message_error == 'totp_limit'}<p class="settings_error" role="alert">{$btr->auth_2fa_limit|escape}</p>{/if}
        </form>
    {elseif $can_manage_target}
        <p class="settings_note">{$btr->manager_totp_reset_hint|escape}</p>
        <form method="post" class="settings_form__actions" data-reauth="1">
            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
            <input type="hidden" name="id" value="{$m->id|escape}">
            <input type="hidden" name="action" value="totp_disable">
            <button type="submit" class="btn btn-outline-danger btn_small">{$btr->manager_totp_reset|escape}</button>
        </form>
    {/if}

{elseif $is_self && $totp_setup}
    {* Підключення: секрет поки лише в сесії, у базу — після першого коду *}
    <div class="two_factor_setup">
        {if $totp_qr}
            <img class="two_factor_setup__qr" src="{$totp_qr|escape}" width="184" height="184" alt="{$btr->manager_totp_qr_alt|escape}">
        {else}
            <p class="settings_note settings_note--warning two_factor_setup__qr">{$btr->manager_totp_qr_missing|escape}</p>
        {/if}
        <ol class="two_factor_setup__steps">
            <li>{$btr->manager_totp_step_app|escape}</li>
            <li>
                {$btr->manager_totp_step_scan|escape}
                <span class="two_factor_setup__manual">{$btr->manager_totp_manual|escape}</span>
                <code class="two_factor_setup__secret">{$totp_setup.secret_grouped|escape}</code>
            </li>
            <li>
                {$btr->manager_totp_step_code|escape}
                <form method="post" class="two_factor_setup__form" autocomplete="off">
                    <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
                    <input type="hidden" name="id" value="{$m->id|escape}">
                    <input class="form-control two_factor_code" name="totp_code" type="text" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" placeholder="000000" aria-label="{$btr->manager_totp_first_code|escape}" required autofocus/>
                    <button type="submit" name="action" value="totp_confirm" class="btn btn_blue btn_small">{$btr->manager_totp_confirm|escape}</button>
                    <button type="submit" name="action" value="totp_cancel" formnovalidate class="btn btn-info btn_small">{$btr->manager_totp_cancel|escape}</button>
                </form>
                {if $message_error == 'totp_wrong'}<p class="settings_error" role="alert">{$btr->auth_2fa_wrong|escape}</p>{/if}
            </li>
        </ol>
    </div>

{elseif $is_self}
    {if $totp_key_ok}
        <form method="post" class="settings_form__actions" data-reauth="1">
            <input type="hidden" name="session_id" value="{$smarty.session.id|escape}">
            <input type="hidden" name="id" value="{$m->id|escape}">
            <button type="submit" name="action" value="totp_start" class="btn btn_blue btn_small">{$btr->manager_totp_enable|escape}</button>
        </form>
    {else}
        {* Без ключа 2FA не вмикається: секрет не було б чим зашифрувати *}
        <p class="settings_note settings_note--warning">
            {if $totp_key_status == 'invalid'}{$btr->manager_totp_key_invalid|escape}
            {elseif $totp_key_status == 'no_sodium'}{$btr->manager_totp_no_sodium|escape}
            {else}{$btr->manager_totp_key_missing|escape}{/if}
        </p>
        {if $totp_key_status != 'no_sodium'}<code class="two_factor_setup__secret">openssl rand -base64 32</code>{/if}
    {/if}
{else}
    <p class="settings_note">{$btr->manager_totp_other_off|escape}</p>
{/if}
