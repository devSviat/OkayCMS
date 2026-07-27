<!-- The cart page template -->
<div class="vs-checkout">
    {* Minimal branded bar. index.tpl renders no site header at all for
       CartController - a deliberate distraction-free checkout - so this is the
       only chrome on the page. It is deliberately NOT sticky: on a phone the
       total and the submit button are the only things worth the vertical
       space. The logo and the back link are the way out of the funnel; without
       them the shopper is stranded here. *}
    <div class="vs-checkout__bar">
        <div class="container">
            <div class="vs-checkout__bar-inner">
                {* Logo *}
                {if !empty({$settings->site_logo})}
                <a class="vs-checkout__logo" href="{url_generator route='main'}">
                    {if strtolower(pathinfo($settings->site_logo, $smarty.const.PATHINFO_EXTENSION)) == 'svg'}
                        {$settings->site_logo|read_svg:$config->design_images}
                    {else}
                        <img src="{$rootUrl}/{$config->design_images|escape}{$settings->site_logo|escape}?v={$settings->site_logo_version|escape}" alt="{$settings->site_name|escape}"/>
                    {/if}
                </a>
                {/if}
                <a class="vs-checkout__back" href="{url_generator route='products'}">
                    {include file="svg.tpl" svgId="chevron"}
                    <span data-language="cart_continue_shopping">{$lang->cart_continue_shopping}</span>
                </a>
                {if $settings->site_phones}
                    {foreach $settings->site_phones as $phone}
                        {if $phone@first}
                        <a class="vs-checkout__phone" href="tel:{preg_replace('~[^0-9\+]~', '', $phone)}" title="{$phone|escape}">
                            {include file="svg.tpl" svgId="phone"}
                            <span class="vs-checkout__phone-text">{$phone|escape}</span>
                        </a>
                        {/if}
                    {/foreach}
                {/if}
            </div>
        </div>
    </div>

    {* The cart content *}
    <div class="vs-checkout__body">
        <div class="container">
            {if $cart->isEmpty === false}
                <h1 class="vs-checkout__title">
                    <span data-language="cart_header">{$lang->cart_header}</span>
                </h1>

                {if $description}
                    <div class="vs-checkout__intro">{$description}</div>
                {/if}

                <form id="captcha_id" method="post" name="cart" class="fn_validate_cart">
                    {if $settings->captcha_type == "v3"}
                        <input type="hidden" class="fn_recaptcha_token fn_recaptchav3" name="recaptcha_token" />
                    {/if}
                    {* Shared by the checkout POST and by the per-line remove
                       buttons, which post this same form to the remove route
                       through formaction rather than nesting a form of their
                       own. *}
                    <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">

                    {* Implicit submission (Enter in any text field, the phone
                       keyboard's Go key) activates the form's DEFAULT button -
                       the first submit button in tree order. The per-line remove
                       controls below are submit buttons with formaction, so
                       without this one the Enter key would silently delete the
                       first cart line. This button claims that role for
                       "place the order" instead, and gives a keyboard user a way
                       to submit at all. It is clipped rather than display:none
                       or hidden, because a non-rendered button is not the
                       default button. Out of the tab order and out of the
                       accessibility tree: the real CTA at the end of the form is
                       the one to reach. *}
                    <button class="vs-checkout__default-submit" type="submit" name="checkout" value="1" tabindex="-1" aria-hidden="true"></button>

                    <div class="vs-cart">
                    <div class="vs-cart__main">
                        {* The list of products in the cart *}
                        <section class="vs-panel">
                            <h2 class="vs-panel__title">
                                <span data-language="cart_purchase_title">{$lang->cart_purchase_title}</span>
                            </h2>
                            <div id="fn_purchases">
                                {include file='cart_purchases.tpl'}
                            </div>
                        </section>

                        {* Contact details *}
                        <section class="vs-panel">
                            <h2 class="vs-panel__title">
                                {include file="svg.tpl" svgId="comment_icon"}
                                <span data-language="cart_form_header">{$lang->cart_form_header}</span>
                            </h2>

                            {* Form error messages *}
                            {if $error}
                                <p class="vs-note vs-note--error vs-panel__note">
                                    {if $error == 'empty_name'}
                                        <span data-language="form_enter_name">{$lang->form_enter_name}</span>
                                    {elseif $error == 'empty_email'}
                                        <span data-language="form_enter_email">{$lang->form_enter_email}</span>
                                    {elseif $error == 'captcha'}
                                        <span data-language="form_error_captcha">{$lang->form_error_captcha}</span>
                                    {elseif $error == 'empty_phone'}
                                        <span data-language="form_error_phone">{$lang->form_error_phone} {$lang->form_error_phone_example} {$phone_example}</span>
                                    {else}
                                        <span>{$error|escape}</span>
                                    {/if}
                                </p>
                            {/if}

                            <div class="vs-fields">
                                {* User's name *}
                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_cart_name">{$lang->form_name}*</label>
                                    <input id="vs_cart_name" class="vs-field vs-form__input" name="name" type="text" autocomplete="given-name" value="{$request_data.name|escape}" data-language="form_name">
                                </div>

                                {* User's last name *}
                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_cart_last_name">{$lang->form_last_name}</label>
                                    <input id="vs_cart_last_name" class="vs-field vs-form__input" name="last_name" type="text" autocomplete="family-name" value="{$request_data.last_name|escape}">
                                </div>

                                {* User's phone *}
                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_cart_phone">{$lang->form_phone}</label>
                                    <input id="vs_cart_phone" class="vs-field vs-form__input" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{$request_data.phone|escape}" data-language="form_phone">
                                </div>

                                {* User's email *}
                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_cart_email">{$lang->form_email}*</label>
                                    <input id="vs_cart_email" class="vs-field vs-form__input" name="email" type="email" inputmode="email" autocomplete="email" value="{$request_data.email|escape}" data-language="form_email">
                                </div>

                                {* User's message *}
                                <div class="vs-form__row vs-form__row--wide">
                                    <label class="vs-form__label" for="vs_cart_comment">{$lang->cart_order_comment}</label>
                                    <textarea id="vs_cart_comment" class="vs-field vs-form__textarea" rows="3" name="comment" data-language="cart_order_comment">{$request_data.comment|escape}</textarea>
                                </div>
                            </div>
                        </section>

                        {* Delivery and Payment *}
                        <div id="fn_ajax_deliveries">
                            {include file='cart_deliveries.tpl'}
                        </div>
                    </div>

                    {* Order summary. Sticky from 992px so the total and the one
                       action that matters never leave the screen. *}
                    <aside class="vs-summary">
                        <h2 class="vs-summary__title">
                            <span data-language="cart_title">{$lang->cart_title}</span>
                        </h2>

                        <div id="fn_cart_coupon">
                            {include file="cart_coupon.tpl"}
                        </div>

                        <div class="vs-summary__row">
                            <span class="vs-summary__label" data-language="cart_order_price">{$lang->cart_order_price}</span>
                            <span id="fn_total_purchases_price" class="vs-summary__value vs-tabular">{$cart->total_price|convert} {$currency->sign|escape}</span>
                        </div>

                        <div id="fn_total_delivery_price_block" class="vs-summary__row">
                            <span class="vs-summary__label" data-language="cart_discount">
                                <span data-language="cart_delivery_order_price">{$lang->cart_delivery_order_price}</span>
                                <span id="fn_total_separate_delivery"{if !$active_delivery->separate_payment || $active_delivery->is_free_delivery === true} style="display: none;"{/if}> ({$lang->cart_paid_separate})</span>
                            </span>
                            <span class="vs-summary__value vs-tabular">
                                <span id="fn_total_delivery_price"{if $active_delivery->is_free_delivery === true} style="display: none;"{/if}>{$active_delivery->price|convert} {$currency->sign|escape}</span>
                                <span id="fn_total_free_delivery" class="vs-summary__free" data-language="cart_free"{if $active_delivery->is_free_delivery === false} style="display: none;"{/if}>{$lang->cart_free}</span>
                            </span>
                        </div>

                        <div class="vs-summary__row vs-summary__row--total">
                            <span class="vs-summary__label" data-language="cart_total_price">{$lang->cart_total_price}</span>
                            {*Итоговую стоимость выводим с активной доставки*}
                            <span class="vs-summary__grand vs-tabular"><span id="fn_cart_total_price">{$active_delivery->total_price_with_delivery|convert}</span> <span class="currency">{$currency->sign|escape}</span></span>
                        </div>

                        {* Captcha *}
                        {if $settings->captcha_cart}
                            {if $settings->captcha_type == "v2"}
                                <div class="vs-summary__captcha">
                                    <div id="recaptcha1"></div>
                                </div>
                            {elseif $settings->captcha_type == "default"}
                                {get_captcha var="captcha_cart"}
                                <div class="vs-summary__captcha">
                                    <label class="vs-form__label" for="vs_cart_captcha">{$captcha_cart[0]|escape} + ? = {$captcha_cart[1]|escape}</label>
                                    <input id="vs_cart_captcha" class="vs-field vs-form__input form__input_captcha" type="text" inputmode="numeric" name="captcha_code" value="" />
                                </div>
                            {/if}
                        {/if}

                        <input type="hidden" name="checkout" value="1">
                        {* Submit button *}
                        <button class="vs-btn vs-btn--primary vs-summary__submit g-recaptcha" type="submit" name="checkout" {if $settings->captcha_type == "invisible"}data-sitekey="{$settings->public_recaptcha_invisible}" data-badge='bottomleft' data-callback="onSubmit"{/if} value="{$lang->cart_checkout}">
                            <span data-language="cart_button">{$lang->cart_button}</span>
                        </button>
                    </aside>
                    </div>
                </form>
            {else}
                <div class="vs-empty vs-empty--center">
                    <span class="vs-empty__icon">{include file="svg.tpl" svgId="cart"}</span>
                    <h1 class="vs-empty__title">
                        <span data-language="cart_empty">{$lang->cart_empty}</span>
                    </h1>
                    <p class="vs-empty__note" data-language="cart_empty_note">{$lang->cart_empty_note}</p>
                    <a class="vs-btn vs-btn--primary" href="{url_generator route='products'}">
                        <span data-language="cart_continue_shopping">{$lang->cart_continue_shopping}</span>
                    </a>
                </div>
            {/if}
        </div>
    </div>

    {* The cart footer *}
    <div class="vs-checkout__foot">
        <div class="container">
            <div class="vs-copyright">
                <span>© {$smarty.now|date_format:"%Y"}</span>
                <span data-language="index_copyright">{$lang->index_copyright}</span>
                <a class="vs-copyright__mark" href="https://okay-cms.com" rel="noreferrer" target="_blank" title="OkayCms">{include file="svg.tpl" svgId="okaycms"}</a>
            </div>
        </div>
    </div>
</div>
