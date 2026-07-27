<!-- Registration page -->

{* The page title *}
{$meta_title = $lang->register_title scope=global}

<div class="vs-page vs-auth">
    <div class="vs-page__masthead">
        <h1 class="vs-page__title"><span data-language="register_header">{$lang->register_header}</span></h1>
    </div>

    <div class="vs-auth__layout">
        <form id="captcha_id" method="post" class="fn_validate_register vs-form vs-auth__form">
            {if $settings->captcha_type == "v3"}
                <input type="hidden" class="fn_recaptcha_token fn_recaptchav3" name="recaptcha_token" />
            {/if}

            <h2 class="vs-form__title" data-language="register_write_comment">{$lang->register_write_comment}</h2>

            {* Form error messages *}
            {if $error}
                <p class="vs-field__error vs-form__alert" role="alert">
                    {if $error == 'empty_name'}
                        <span data-language="form_enter_name">{$lang->form_enter_name}</span>
                    {elseif $error == 'empty_email'}
                        <span data-language="form_enter_email">{$lang->form_enter_email}</span>
                    {elseif $error == 'empty_password'}
                        <span data-language="form_enter_password">{$lang->form_enter_password}</span>
                    {elseif $error == 'user_exists'}
                        <span data-language="register_user_registered">{$lang->register_user_registered}</span>
                    {elseif $error == 'captcha'}
                        <span data-language="form_error_captcha">{$lang->form_error_captcha}</span>
                    {else}
                        {$error|escape}
                    {/if}
                </p>
            {/if}

            <div class="vs-fields">
                {* User's name *}
                <div class="vs-form__row">
                    <label class="vs-form__label" for="vs_reg_name">{$lang->form_name}*</label>
                    <input id="vs_reg_name" class="vs-field vs-form__input" type="text" name="name" value="{$request_data.name|escape}" autocomplete="given-name" />
                </div>

                {* User's last name *}
                <div class="vs-form__row">
                    <label class="vs-form__label" for="vs_reg_last_name">{$lang->form_last_name}</label>
                    <input id="vs_reg_last_name" class="vs-field vs-form__input" type="text" name="last_name" value="{$request_data.last_name|escape}" autocomplete="family-name" />
                </div>

                {* User's email *}
                <div class="vs-form__row">
                    <label class="vs-form__label" for="vs_reg_email">{$lang->form_email}*</label>
                    <input id="vs_reg_email" class="vs-field vs-form__input" type="email" name="email" value="{$request_data.email|escape}" autocomplete="email" />
                </div>

                {* User's phone *}
                <div class="vs-form__row">
                    <label class="vs-form__label" for="vs_reg_phone">{$lang->form_phone}</label>
                    <input id="vs_reg_phone" class="vs-field vs-form__input" type="tel" name="phone" value="{$request_data.phone|escape}" autocomplete="tel" />
                </div>

                {* User's password *}
                <div class="vs-form__row">
                    <label class="vs-form__label" for="vs_reg_password">{$lang->form_enter_password}*</label>
                    <input id="vs_reg_password" class="vs-field vs-form__input" type="password" name="password" value="" autocomplete="new-password" />
                </div>

                {if $settings->captcha_register}
                    {if $settings->captcha_type == "v2"}
                        <div class="vs-form__row">
                            <div id="recaptcha1"></div>
                        </div>
                    {elseif $settings->captcha_type == "default"}
                        {get_captcha var="captcha_register"}
                        <div class="vs-form__row">
                            <label class="vs-form__label" for="vs_reg_captcha">{$captcha_register[0]|escape} + ? = {$captcha_register[1]|escape}</label>
                            <input id="vs_reg_captcha" class="vs-field vs-form__input" type="text" name="captcha_code" value="" />
                        </div>
                    {/if}
                {/if}
            </div>

            <input name="register" type="hidden" value="1">
            {* Submit button *}
            <button type="submit" value="{$lang->register_create_account}" class="vs-btn vs-btn--primary vs-form__submit g-recaptcha" name="register" {if $settings->captcha_type == "invisible"}data-sitekey="{$settings->public_recaptcha_invisible}" data-badge='bottomleft' data-callback="onSubmit"{/if}>
                <span data-language="register_create_account">{$lang->register_create_account}</span>
            </button>
        </form>

        <div class="vs-auth__aside">
            <div class="block__description vs-prose vs-auth__aside_body">
                {$description}
            </div>
            <a href="{url_generator route="login"}" class="vs-btn vs-btn--secondary vs-auth__aside_cta" data-language="login_enter">{$lang->login_enter}</a>
        </div>
    </div>
</div>
