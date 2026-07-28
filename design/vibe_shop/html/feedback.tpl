<!-- Feedback page -->

<div class="vs-page vs-auth">
    <div class="vs-page__masthead">
        <h1 class="vs-page__title">
            <span>{if $page->name_h1|escape}{$page->name_h1|escape}{else}{$page->name|escape}{/if}</span>
        </h1>
    </div>

    <div class="vs-auth__layout vs-auth__layout--reverse">
        {* Feedback form *}
        {if $message_sent}
            <div class="vs-form vs-auth__form">
                <h2 class="vs-form__title" data-language="feedback_feedback">{$lang->feedback_feedback}</h2>
                <p class="vs-note vs-note--ok" role="status">
                    <b>{$request_data.name|escape},</b> <span data-language="feedback_message_sent">{$lang->feedback_message_sent}.</span>
                </p>
            </div>
        {else}
            <form id="captcha_id" method="post" class="fn_validate_feedback vs-form vs-auth__form">
                <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
                {if $settings->captcha_type == "v3"}
                    <input type="hidden" class="fn_recaptcha_token fn_recaptchav3" name="recaptcha_token" />
                {/if}

                <h2 class="vs-form__title" data-language="feedback_feedback">{$lang->feedback_feedback}</h2>

                {* Form error messages *}
                {if $error}
                    <p class="vs-field__error vs-form__alert" role="alert">
                        {if $error=='captcha'}
                            <span data-language="form_error_captcha">{$lang->form_error_captcha}</span>
                        {elseif $error=='empty_name'}
                            <span data-language="form_enter_name">{$lang->form_enter_name}</span>
                        {elseif $error=='empty_email'}
                            <span data-language="form_enter_email">{$lang->form_enter_email}</span>
                        {elseif $error=='empty_text'}
                            <span data-language="form_enter_message">{$lang->form_enter_message}</span>
                        {else}
                            {$error|escape}
                        {/if}
                    </p>
                {/if}

                <div class="vs-fields">
                    {* User's name *}
                    <div class="vs-form__row">
                        <label class="vs-form__label" for="vs_fb_name">{$lang->form_name}*</label>
                        <input id="vs_fb_name" class="vs-field vs-form__input" value="{if $request_data.name}{$request_data.name|escape}{elseif $user->name}{$user->name|escape}{/if}" name="name" type="text" autocomplete="name"/>
                    </div>

                    {* User's email *}
                    <div class="vs-form__row">
                        <label class="vs-form__label" for="vs_fb_email">{$lang->form_email}</label>
                        <input id="vs_fb_email" class="vs-field vs-form__input" value="{if $request_data.email}{$request_data.email|escape}{elseif $user->email}{$user->email|escape}{/if}" name="email" type="email" autocomplete="email"/>
                    </div>

                    {* User's comment *}
                    <div class="vs-form__row">
                        <label class="vs-form__label" for="vs_fb_message">{$lang->form_enter_message}*</label>
                        <textarea id="vs_fb_message" class="vs-field vs-form__textarea" rows="4" name="message">{$request_data.message|escape}</textarea>
                    </div>

                    {* Captcha *}
                    {if $settings->captcha_feedback}
                        {if $settings->captcha_type == "v2"}
                            <div class="vs-form__row">
                                <div id="recaptcha1"></div>
                            </div>
                        {elseif $settings->captcha_type == "default"}
                            {get_captcha var="captcha_feedback"}
                            <div class="vs-form__row">
                                <label class="vs-form__label" for="vs_fb_captcha">{$captcha_feedback[0]|escape} + ? = {$captcha_feedback[1]|escape}</label>
                                <input id="vs_fb_captcha" class="vs-field vs-form__input" type="text" name="captcha_code" value=""/>
                            </div>
                        {/if}
                    {/if}
                </div>

                <input type="hidden" name="feedback" value="1">

                {* Submit button *}
                <button class="vs-btn vs-btn--primary vs-form__submit g-recaptcha" type="submit" name="feedback" {if $settings->captcha_type == "invisible"}data-sitekey="{$settings->public_recaptcha_invisible}" data-badge='bottomleft' data-callback="onSubmit"{/if} value="{$lang->form_send}">
                    <span data-language="form_send">{$lang->form_send}</span>
                </button>
            </form>
        {/if}

        {if $description}
            <div class="vs-auth__aside">
                <div class="block__description vs-prose vs-auth__aside_body">{$description}</div>
            </div>
        {/if}
    </div>

    {* Map *}
    {if $settings->iframe_map_code}
        <div class="ya_map vs-map">
            {$settings->iframe_map_code}
        </div>
    {/if}
</div>
