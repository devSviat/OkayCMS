<!-- Password remind page -->

{* The page title *}
{if empty($meta_title)}{$meta_title = $lang->password_remind_title scope=global}{/if}

<div class="vs-page vs-auth">
    <div class="vs-page__masthead">
        <h1 class="vs-page__title"><span data-language="password_remind_header">{$lang->password_remind_header}</span></h1>
    </div>

    <div class="vs-auth__layout vs-auth__layout--single">
        {if $recovery_expired}
            <div class="vs-form vs-auth__form">
                <p class="vs-field__error" role="alert">
                    <span data-language="password_remind_expired">{$lang->password_remind_expired}</span>
                </p>
                <a class="vs-btn vs-btn--secondary vs-form__submit" href="{url_generator route="password_remind"}">
                    <span data-language="password_remind_remember">{$lang->password_remind_remember}</span>
                </a>
            </div>
        {elseif $recovery_mode}
            {* Введення нового пароля за підтвердженим посиланням відновлення *}
            <form method="post" class="vs-form vs-auth__form">
                <input type="hidden" name="reset_password" value="1">
                <h2 class="vs-form__title" data-language="password_remind_new_password">{$lang->password_remind_new_password}</h2>

                {* Form error messages *}
                {if $error}
                    <p class="vs-field__error vs-form__alert" role="alert">
                        {if $error == 'password_empty'}
                            <span data-language="password_remind_password_empty">{$lang->password_remind_password_empty}</span>
                        {elseif $error == 'password_wrong'}
                            <span data-language="password_remind_password_wrong">{$lang->password_remind_password_wrong}</span>
                        {else}
                            {$error|escape}
                        {/if}
                    </p>
                {/if}

                <div class="vs-fields">
                    <div class="vs-form__row">
                        <label class="vs-form__label" for="new_password">{$lang->password_remind_new_password}*</label>
                        <input id="new_password" class="vs-field vs-form__input" type="password" name="new_password" autocomplete="new-password" required>
                    </div>
                    <div class="vs-form__row">
                        <label class="vs-form__label" for="new_password_check">{$lang->password_remind_new_password}*</label>
                        <input id="new_password_check" class="vs-field vs-form__input" type="password" name="new_password_check" autocomplete="new-password" required>
                    </div>
                </div>

                {* Submit button *}
                <button type="submit" class="vs-btn vs-btn--primary vs-form__submit" value="{$lang->password_remind_save}">
                    <span data-language="password_remind_save">{$lang->password_remind_save}</span>
                </button>
            </form>
        {elseif $email_sent}
            {* Відповідь не залежить від того, чи існує акаунт *}
            <div class="vs-form vs-auth__form">
                <p class="vs-note vs-note--ok" role="status">
                    <span data-language="password_remind_letter_sent_generic">{$lang->password_remind_letter_sent_generic}</span>
                </p>
                <a class="vs-btn vs-btn--secondary vs-form__submit" href="{url_generator route="login"}">
                    <span data-language="login_sign_in">{$lang->login_sign_in}</span>
                </a>
            </div>
        {else}
            <form method="post" class="vs-form vs-auth__form">
                <h2 class="vs-form__title" data-language="password_remind_enter_your_email">{$lang->password_remind_enter_your_email}</h2>

                {* Form error messages *}
                {if $error}
                    <p class="vs-field__error vs-form__alert" role="alert">{$error|escape}</p>
                {/if}

                <div class="vs-fields">
                    <div class="vs-form__row">
                        <label class="vs-form__label" for="password_remind">{$lang->form_email}*</label>
                        <input id="password_remind" class="vs-field vs-form__input" type="email" name="email" value="{$request_data.email|escape}" autocomplete="email" required>
                    </div>
                </div>

                {* Submit button *}
                <button type="submit" class="vs-btn vs-btn--primary vs-form__submit" value="{$lang->password_remind_remember}">
                    <span data-language="password_remind_remember">{$lang->password_remind_remember}</span>
                </button>
            </form>
        {/if}
    </div>
</div>
