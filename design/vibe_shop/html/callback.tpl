<!-- Callback form -->
<div class="hidden">
    <form id="fn_callback" class="popup_animated fn_validate_callback vs-form vs-modal-form" method="post">

        {* The callback writes a row and mails the shop, so it is a mutation and
           carries the token like every other one. It also posts to whatever page
           it sits on - including /cart, where the guard would otherwise reject
           it after CommonHelper had already saved the row. *}
        <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">

        {* Одноразовий: гасить другу заявку від подвійного кліку чи F5. *}
        {form_token name="callback"}

        {if $settings->captcha_type == "v3"}
            <input type="hidden" class="fn_recaptcha_token fn_recaptchav3" name="recaptcha_token" />
        {/if}

        {* The form heading *}
        <h2 class="vs-form__title">
            {include file="svg.tpl" svgId="support_icon"}
            <span data-language="callback_header">{$lang->callback_header}</span>
        </h2>

        {if $call_error}
            <p class="vs-field__error vs-form__alert" role="alert">
                {if $call_error=='captcha'}
                    <span data-language="form_error_captcha">{$lang->form_error_captcha}</span>
                {elseif $call_error=='empty_name'}
                    <span data-language="form_enter_name">{$lang->form_enter_name}</span>
                {elseif $call_error=='empty_phone'}
                    <span data-language="form_enter_phone">{$lang->form_enter_phone}: {$phone_example}</span>
                {elseif $call_error=='empty_comment'}
                    <span data-language="form_enter_comment">{$lang->form_enter_comment}</span>
                {else}
                    <span>{$call_error|escape}</span>
                {/if}
            </p>
        {/if}

        <div class="vs-fields">
            {* User's name *}
            <div class="vs-form__row">
                <label class="vs-form__label" for="vs_cb_name">{$lang->form_name}*</label>
                <input id="vs_cb_name" class="vs-field vs-form__input" type="text" name="callback_name" value="{if $request_data.callback_name}{$request_data.callback_name|escape}{elseif $user->name}{$user->name|escape}{/if}" autocomplete="name">
            </div>

            {* User's phone. The |phone modifier is Phone::format() ->
               PhoneNumberUtil::parse(), which THROWS on a number it cannot
               parse when phone_default_region is unset - a 500 on every page,
               because this form is included from index.tpl. The stored value
               is printed raw: it is the customer's own number, going straight
               back into an editable field, so formatting it buys nothing. *}
            <div class="vs-form__row">
                <label class="vs-form__label" for="vs_cb_phone">{$lang->form_phone}*</label>
                <input id="vs_cb_phone" class="vs-field vs-form__input" type="tel" name="callback_phone" value="{if $request_data.callback_phone}{$request_data.callback_phone|escape}{elseif $user->phone}{$user->phone|escape}{/if}" autocomplete="tel">
            </div>

            {* User's message *}
            <div class="vs-form__row">
                <label class="vs-form__label" for="vs_cb_message">{$lang->form_enter_message}</label>
                <textarea id="vs_cb_message" class="vs-field vs-form__textarea" rows="3" name="callback_message">{$request_data.callback_message|escape}</textarea>
            </div>

            {* Captcha *}
            {if $settings->captcha_callback}
                {if $settings->captcha_type == "v2"}
                    <div class="vs-form__row">
                        <div id="recaptcha2"></div>
                    </div>
                {elseif $settings->captcha_type == "default"}
                    {get_captcha var="captcha_callback"}
                    <div class="vs-form__row">
                        <label class="vs-form__label" for="vs_cb_captcha">{$captcha_callback[0]|escape} + ? = {$captcha_callback[1]|escape}</label>
                        <input id="vs_cb_captcha" class="vs-field vs-form__input" type="text" name="captcha_code" value="">
                    </div>
                {/if}
            {/if}
        </div>

        <input name="callback" type="hidden" value="1">
        {* Submit button *}
        <button class="vs-btn vs-btn--primary vs-form__submit g-recaptcha" type="submit" name="callback" {if $settings->captcha_type == "invisible"}data-sitekey="{$settings->public_recaptcha_invisible}" data-badge='bottomleft' data-callback="onSubmitCallback"{/if} value="{$lang->callback_order}">
            <span data-language="callback_order">{$lang->callback_order}</span>
        </button>
    </form>
</div>

{* The modal window after submitting *}
{if $call_sent}
    <div class="hidden">
        <div id="fn_callback_sent" class="popup_animated vs-modal-note">
            <div class="vs-modal-note__title">
                {include file="svg.tpl" svgId="success_icon"}
                <span data-language="callback_sent_header">{$lang->callback_sent_header}</span>
            </div>
            <p class="vs-modal-note__text">
                <span data-language="callback_sent_text">{$lang->callback_sent_text}</span>
            </p>
        </div>
    </div>
{/if}
