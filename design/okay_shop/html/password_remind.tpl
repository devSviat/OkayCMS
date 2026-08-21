<!-- Password remind page -->

{* The page title *}
{if empty($meta_title)}{$meta_title = $lang->password_remind_title scope=global}{/if}

<div class="block">
    {* The page heading *}
    <div class="block__header block__header--boxed block__header--border">
        <h1 class="block__heading"><span data-language="password_remind_header">{$lang->password_remind_header}</span></h1>
    </div>

    <div class="block block--boxed block--border">
        {if $recovery_expired}
            <div class="message_error" role="alert">
                <span data-language="password_remind_expired">{$lang->password_remind_expired}</span>
            </div>
        {elseif $recovery_mode}
            {* Введення нового пароля за підтвердженим посиланням відновлення *}
            <div class="f_row flex-lg-row align-items-md-start">

            </div>
            <div class="form_wrap f_col-lg-6">
                <form method="post" class="form form--boxed">
                    <input type="hidden" name="reset_password" value="1">
                    <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
                    <div class="form__header">
                        <div class="form__title">
                            <span class="label_block" data-language="password_remind_new_password">{$lang->password_remind_new_password}</span>
                        </div>
                    </div>
                    <div class="form__body">
                        {* Form error messages *}
                        {if $error}
                            <div class="message_error" role="alert">
                                {if $error == 'password_empty'}
                                    <span data-language="password_remind_password_empty">{$lang->password_remind_password_empty}</span>
                                {elseif $error == 'password_wrong'}
                                    <span data-language="password_remind_password_wrong">{$lang->password_remind_password_wrong}</span>
                                {else}
                                    {$error|escape}
                                {/if}
                            </div>
                        {/if}
                        <div class="form__group">
                            <input id="new_password" class="form__input form__placeholder--focus" type="password" name="new_password" autocomplete="new-password" required>
                            <span class="form__placeholder">{$lang->password_remind_new_password}*</span>
                        </div>
                        <div class="form__group">
                            <input id="new_password_check" class="form__input form__placeholder--focus" type="password" name="new_password_check" autocomplete="new-password" required>
                            <span class="form__placeholder">{$lang->password_remind_new_password}*</span>
                        </div>
                    </div>
                    <div class="form__footer">
                        {* Submit button *}
                        <button type="submit" class="form__button button--blick" value="{$lang->password_remind_save}">
                            <span data-language="password_remind_save">{$lang->password_remind_save}</span>
                        </button>
                    </div>
                </form>
            </div>
        {elseif $email_sent}
            {* Відповідь не залежить від того, чи існує акаунт *}
            <div>
                <span data-language="password_remind_letter_sent_generic">{$lang->password_remind_letter_sent_generic}</span>
            </div>
        {else}
        <div class="f_row flex-lg-row align-items-md-start">

        </div>
            <div class="form_wrap f_col-lg-6">
                <form method="post" class="form form--boxed">
                    <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
                    <div class="form__header">
                        <div class="form__title">
                            <span class="label_block" data-language="password_remind_enter_your_email">{$lang->password_remind_enter_your_email}</span>
                        </div>
                    </div>
                    <div class="form__body">
                        {* Form error messages *}
                        {if $error}
                            <div class="message_error" role="alert">
                                {$error|escape}
                            </div>
                        {/if}
                        <div class="form__group">
                            <input id="password_remind" class="form__input form__placeholder--focus" type="text" name="email" value="{$request_data.email|escape}" data-language="form_email" required>
                            <span class="form__placeholder">{$lang->form_email}*</span>
                        </div>
                    </div>
                    <div class="form__footer">
                        {* Submit button *}
                        <button type="submit" class="form__button button--blick" value="{$lang->password_remind_remember}">
                            <span data-language="password_remind_remember">{$lang->password_remind_remember}</span>
                        </button>
                    </div>
                </form>
            </div>
        {/if}
    </div>
</div>
