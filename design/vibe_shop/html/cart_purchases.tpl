<!-- Cart purchases template -->
{*NOTICE: Обратите внимание, data-total_purchases_price хранится в основной валюте сайта*}
{* This block is re-rendered wholesale by okay.js (ajax_set_result replaces the
   innerHTML of #fn_purchases), so the thumbnails stay plain <img src>: a
   data-src/lazyload image would need lazyload re-initialised on every quantity
   change and would otherwise show the spinner placeholder for good. *}
<div class="fn_purchases_wrap vs-cart__lines vs-cart__lines--main" data-total_purchases_price="{$cart->total_price}">
{foreach $cart->purchases as $purchase}
    <article class="vs-cart__line">
        {* Product image. Decorative duplicate of the name link below it, so it
           is out of the tab order and out of the accessibility tree. *}
        <a class="vs-cart__thumb" href="{url_generator route="product" url=$purchase->product->url}" tabindex="-1" aria-hidden="true">
            {if $purchase->product->image}
                <img src="{$purchase->product->image->filename|resize:140:140}" alt=""/>
            {else}
                <span class="vs-cart__thumb-empty">{include file="svg.tpl" svgId="no_image"}</span>
            {/if}
        </a>

        <div class="vs-cart__info">
            {* Product name *}
            <a class="vs-cart__name" href="{url_generator route="product" url=$purchase->product->url}">{$purchase->product->name|escape}</a>
            {if $purchase->variant->name}<div class="vs-cart__variant">{$purchase->variant->name|escape}</div>{/if}
            {if $purchase->variant->stock == 0}<div class="vs-stock vs-stock--low">{$lang->product_pre_order}</div>{/if}

            <div class="vs-cart__unit{if $purchase->discounts} vs-cart__unit--cut{/if}">
                <span class="vs-tabular">{($purchase->price)|convert}</span>
                <span class="currency">{$currency->sign}</span>
                {if $purchase->variant->units}<span>/ {$purchase->variant->units|escape}</span>{/if}
                {if $purchase->discounts}
                    <a href="javascript:;" class="discount_tooltip vs-cart__discount-link" title="{$lang->purchase_discount__tooltip}" data-src="#fn_purchase_discount_detail_{$purchase->variant->id}" data-fancybox="hello_{$purchase->variant->id}" aria-label="{$lang->purchase_discount__tooltip|escape}">{include file="svg.tpl" svgId="sale_icon"}</a>
                {/if}
            </div>
        </div>

        {* Quantity. The input is the value carrier okay.js posts and re-reads,
           so the stepper wraps it and never replaces it. fn_is_preorder tells
           okay.js's amount_change to clamp against max_order_amount instead of
           the variant stock, and vibe.js honours the same flag. *}
        <div class="fn_product_amount vs-stepper vs-cart__qty{if $settings->is_preorder} fn_is_preorder{/if}">
            <button type="button" class="fn_minus vs-stepper__btn" data-vs-step="-1" aria-label="{$lang->cart_head_amoun|escape} -1">&minus;</button>
            <input class="amount__input vs-stepper__input" type="text" inputmode="numeric" data-id="{$purchase->variant->id}" name="amounts[{$purchase->variant->id}]" value="{$purchase->amount}" onblur="ajax_change_amount(this, {$purchase->variant->id});" data-max="{$purchase->variant->stock}" aria-label="{$lang->cart_head_amoun|escape}">
            <button type="button" class="fn_plus vs-stepper__btn" data-vs-step="1" aria-label="{$lang->cart_head_amoun|escape} +1">&plus;</button>
        </div>

        <div class="vs-cart__linetotal vs-tabular">{$purchase->meta->total_price|convert} <span class="currency">{$currency->sign}</span></div>

        {* Remove button. Deliberately NOT its own <form>: this block renders
           inside the checkout form, and a nested <form> start tag is dropped by
           the HTML parser while its </form> pops the outer form off the open
           element stack - everything declared after it, up to and including the
           submit button, loses its form owner. formaction on a submit button
           posts the checkout form to the remove route instead, which needs only
           the CSRF token and ignores the rest, so no-JS removal still works.
           With scripting on the inline handler returns false and nothing is
           submitted at all. *}
        <button type="submit" class="vs-btn vs-btn--ghost vs-btn--icon vs-cart__remove" formmethod="post" formnovalidate formaction="{url_generator route="cart_remove_item" variantId=$purchase->variant->id}" onclick="ajax_remove({$purchase->variant->id});return false;" title="{$lang->cart_remove}" aria-label="{$lang->cart_remove|escape}">
            {include file='svg.tpl' svgId='remove_icon'}
        </button>

        {if $purchase->discounts}
        <div class="hidden">
            {* .popup is deliberately not carried over: okay.css forced its
               padding, width and text-align with !important, which this layer
               could not answer, and the generic .popup components.css restates
               is sized for the FastOrder form. .popup_animated is only the
               fancybox entrance, which no sheet draws any more. *}
            <div id="fn_purchase_discount_detail_{$purchase->variant->id}" class="vs-discounts popup_animated">
                <div class="vs-discounts__title">
                    {include file="svg.tpl" svgId="sale_icon"}
                    <span data-language="purchase_discount__popup_title">{$lang->purchase_discount__popup_title}</span>
                </div>
                {foreach $purchase->discounts as $discount}
                    <div class="vs-discounts__item">
                        <div class="vs-discounts__name">{$discount->name}</div>
                        <div class="vs-discounts__row">
                            <span class="vs-discounts__label" data-language="purchase_discount__price">{$lang->purchase_discount__price}</span>
                            <span class="vs-tabular">{$discount->priceBeforeDiscount} <span class="currency">{$currency->sign|escape}</span></span>
                        </div>
                        <div class="vs-discounts__row vs-discounts__row--cut">
                            <span class="vs-discounts__label" data-language="purchase_discount__discount">{$lang->purchase_discount__discount}</span>
                            <span class="vs-tabular">{$discount->percentDiscount|string_format:"%.2f"} % &minus; {$discount->absoluteDiscount|convert} <span class="currency">{$currency->sign|escape}</span></span>
                        </div>
                        <div class="vs-discounts__row vs-discounts__row--total">
                            <span class="vs-discounts__label" data-language="purchase_discount__total">{$lang->purchase_discount__total}</span>
                            <span class="vs-tabular">{$discount->priceAfterDiscount} <span class="currency">{$currency->sign|escape}</span></span>
                        </div>
                    </div>
                {/foreach}
            </div>
        </div>
        {/if}
    </article>
{/foreach}
</div>
