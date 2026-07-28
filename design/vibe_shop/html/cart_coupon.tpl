<!-- Coupon -->
{if $coupon_request}
    {* No disclosure here on purpose: the stock theme hung .fn_switch on the
       title, which slide-toggles whatever element happens to follow it - the
       error message when there was one, the field when there was not. One
       fewer tap on the money path, and the field is always where it was. *}
    <div class="vs-coupon">
        <label class="vs-coupon__label" for="vs_coupon_code" data-language="cart_coupon">{$lang->cart_coupon}</label>

        {* Coupon error messages *}
        {if $coupon_error}
            <p class="vs-note vs-note--error">
                {if $coupon_error == 'invalid'}
                    {$lang->cart_coupon_error}
                {elseif $coupon_error == 'empty'}
                    {$lang->cart_empty_coupon_error}
                {/if}
            </p>
        {/if}

        {if $cart->coupon->min_order_price > 0}
            <p class="vs-note vs-note--ok">
                {$lang->cart_coupon} {$cart->coupon->code|escape} {$lang->cart_coupon_min} {$cart->coupon->min_order_price|convert} {$currency->sign|escape}
            </p>
        {/if}

        <div class="vs-coupon__row">
            <input id="vs_coupon_code" class="fn_coupon vs-field vs-coupon__input" type="text" name="coupon_code" autocomplete="off" value="{$cart->coupon->code|escape}">
            <button class="fn_sub_coupon vs-btn vs-btn--secondary vs-coupon__submit" type="button">{$lang->cart_purchases_coupon_apply}</button>
        </div>
    </div>

    {if !empty($cart->discounts)}
        {foreach $cart->discounts as $discount}
            <div class="vs-summary__row vs-summary__row--discount">
                <span class="vs-summary__label">{$discount->name}</span>
                <span class="vs-summary__value vs-tabular">{$discount->percentDiscount|string_format:"%.2f"} % &minus;{$discount->absoluteDiscount|convert} <span class="currency">{$currency->sign|escape}</span></span>
            </div>
        {/foreach}
    {/if}

    {if !empty($cart->total_purchases_discounts)}
        {foreach $cart->total_purchases_discounts as $purchase_discount}
            <div class="vs-summary__row vs-summary__row--discount total_purchases_discount__item">
                <span class="vs-summary__label">{$purchase_discount->name}</span>
                <span class="vs-summary__value vs-tabular">{$purchase_discount->percentDiscount|string_format:"%.2f"} % &minus;{$purchase_discount->absoluteDiscount|convert} <span class="currency">{$currency->sign|escape}</span></span>
            </div>
        {/foreach}
    {/if}
{/if}
