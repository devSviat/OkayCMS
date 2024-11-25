{* Callback form *}
<div class="hidden">
    <form id="fn-inform_back_in_stock_form" class="form form--boxed popup fn_validate_backinstock" method="post">

        {*{if $settings->captcha_type == "v3"}*}
            {*<input type="hidden" class="fn_recaptcha_token fn_recaptchav3" name="recaptcha_token" />*}
        {*{/if}*}

        {* The form heading *}
        <div class="form__header">
            <div class="form__title">
                {*{include file="svg.tpl" svgId="support_icon"}*}
                <span data-language="callback_header">{$lang->inform_back_form_title}</span>
            </div>
        </div>

        <div class="form__body">
            <div class="fn_error"></div>

            {* User's name *}
            <div class="form__group">
                <input class="form__input form__placeholder--focus" type="text" name="name" value="{if $user->name}{$user->name|escape}{/if}" data-language="form_name">
                <span class="form__placeholder">{$lang->form_name}*</span>
            </div>

            {* User's email *}
            <div class="form__group">
                <input class="form__input form__placeholder--focus" type="text" name="email" value="{if $user->email}{$user->email|escape}{/if}" data-language="form_phone">
                <span class="form__placeholder">{$lang->form_email}*</span>
            </div>

        </div>

        <div class="form__footer">
            {* Submit button *}
            <button class="form__button button--blick g-recaptcha fn_inform_back_in_stock_submit" type="submit"  value="{$lang->order_inform_stock}">
                <span data-language="callback_order">{$lang->order_inform_stock}</span>
            </button>
        </div>
    </form>
</div>

{* The modal window after submitting *}
{*{if $call_sent}*}
    <div class="hidden">
        <div id="fn_message_sent" class="popup">
            <div class="popup__heading">
                {include file="svg.tpl" svgId="success_icon"}
                <span data-language="callback_sent_header">{$lang->inform_stock_sent}</span>
            </div>
            <div class="popup__description">
                <span data-language="callback_sent_text">{$lang->inform_stock_text}</span>
            </div>
        </div>
    </div>
{*{/if}*}









{*<div class="hidden-xs-up">*}
    {*<form id="fn-inform_back_in_stock_form" class="bg-info p-a-1 fn_validate_backinstock" method="post">*}
        {* Заголовок формы *}
        {*<div class="h3 m-b-1 text-xs-center">{$lang->inform_back_form_title}</div>*}

        {*<div id="product_name" class="h5 m-b-1 text-xs-center"></div>*}

        {*<div class="fn_error"></div>*}

        {* Имя клиента *}
        {*<div class="form_group">*}
            {*<input class="form_input" type="text" name="name"  value="" data-language="form_name" placeholder="{$lang->form_name}*"/>*}
        {*</div>*}

        {* Телефон клиента *}
        {*<div class="form_group">*}
            {*<input class="form_input" type="text" name="email"  value="" data-language="form_email" placeholder="{$lang->form_email}*"/>*}
        {*</div>*}

        {* Кнопка отправки формы *}
        {*<button type="submit" class="fn_inform_back_in_stock_submit">{$lang->order_inform_stock}</button>*}

        {*<input name="back_in_stock_variant_id" type="hidden" value="" />*}
        {*<input name="back_in_stock_product_id" type="hidden" value="" />*}

    {*</form>*}
{*</div>*}


{*<div class="hidden">*}
    {*<div id="fn_message_sent" class="popup">*}
        {*<div class="popup__heading">*}
            {*{include file="svg.tpl" svgId="success_icon"}*}
            {*<span data-language="message_sent_header">{$lang->inform_stock_sent}</span>*}
        {*</div>*}
    {*</div>*}
{*</div>*}

<div class="hidden">
    <div id="fn_error_sent_earlier" class="popup">
        <div class="popup__heading">
            {include file="svg.tpl" svgId="success_icon"}
            <span data-language="message_sent_header">{$lang->sent_earlier}</span>
        </div>
    </div>
</div>
