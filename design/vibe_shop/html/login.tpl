{* Login page *}

{* The page title *}
{if empty($meta_title)}{$meta_title = $lang->login_title scope=global}{/if}

<div class="vs-page vs-auth">
    <div class="vs-page__masthead">
        <h1 class="vs-page__title"><span data-language="login_enter">{$lang->login_enter}</span></h1>
    </div>

    <div class="vs-auth__layout">
        <form method="post" class="fn_validate_login vs-form vs-auth__form">
            <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
            <h2 class="vs-form__title" data-language="login_title_form">{$lang->login_title_form}</h2>

            {* Form error messages *}
            {if $error}
                <p class="vs-field__error vs-form__alert" role="alert">
                    {if $error == 'login_incorrect'}
                        <span data-language="login_error_pass">{$lang->login_error_pass}</span>
                    {else}
                        {$error|escape}
                    {/if}
                </p>
            {/if}

            <div class="vs-fields">
                {* User's email *}
                <div class="vs-form__row">
                    <label class="vs-form__label" for="vs_login_email">{$lang->form_email}*</label>
                    <input id="vs_login_email" class="vs-field vs-form__input" type="email" name="email" value="{$request_data.email|escape}" autocomplete="email" />
                </div>
                {* User's password *}
                <div class="vs-form__row">
                    <label class="vs-form__label" for="vs_login_password">{$lang->form_password}*</label>
                    <input id="vs_login_password" class="vs-field vs-form__input" type="password" name="password" value="" autocomplete="current-password" />
                </div>
            </div>

            {* Submit button *}
            <button type="submit" class="vs-btn vs-btn--primary vs-form__submit" name="login" value="{$lang->login_sign_in}">
                <span data-language="login_sign_in">{$lang->login_sign_in}</span>
            </button>

            {* Remind password link *}
            <a class="password_remind vs-form__link" href="{url_generator route="password_remind"}">
                <span data-language="login_remind">{$lang->login_remind}</span>
                {include file="svg.tpl" svgId="arrow_right2"}
            </a>
        </form>

        <div class="vs-auth__aside">
            <h2 class="vs-auth__aside_title" data-language="login_text">{$lang->login_text}</h2>
            <div class="block__description vs-prose vs-auth__aside_body">
                {$description}
            </div>
            {* Link to registration *}
            <a href="{url_generator route="register"}" class="vs-btn vs-btn--secondary vs-auth__aside_cta" data-language="login_registration">{$lang->login_registration}</a>
        </div>
    </div>
</div>
