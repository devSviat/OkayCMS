{if $cart->isEmpty === false}
    <div class="vs-popcart">
        <div class="vs-popcart__head">
            <span class="vs-popcart__title" data-language="cart_purchase_title">{$lang->cart_purchase_title}</span>
        </div>

        <div class="vs-popcart__lines vs-cart__lines">
            {foreach $cart->purchases as $purchase}
                <article class="fn_purchase_row vs-cart__line vs-cart__line--compact">
                    {* Product image *}
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
                        <div class="vs-cart__unit">
                            <span class="vs-tabular">{($purchase->price)|convert}</span>
                            <span class="currency">{$currency->sign}</span>
                            {if $purchase->variant->units}<span>/ {$purchase->variant->units|escape}</span>{/if}
                        </div>
                    </div>

                    <div class="fn_product_amount vs-stepper vs-cart__qty{if $settings->is_preorder} fn_is_preorder{/if}">
                        <button type="button" class="fn_minus vs-stepper__btn" data-vs-step="-1" aria-label="{$lang->cart_head_amoun|escape} -1">&minus;</button>
                        <input class="amount__input vs-stepper__input" type="text" inputmode="numeric" data-id="{$purchase->variant->id}" name="amounts[{$purchase->variant->id}]" value="{$purchase->amount}" onblur="ajax_change_amount(this, {$purchase->variant->id});" data-max="{$purchase->variant->stock}" aria-label="{$lang->cart_head_amoun|escape}">
                        <button type="button" class="fn_plus vs-stepper__btn" data-vs-step="1" aria-label="{$lang->cart_head_amoun|escape} +1">&plus;</button>
                    </div>

                    <div class="vs-cart__linetotal vs-tabular">{$purchase->meta->total_price|convert} <span class="currency">{$currency->sign}</span></div>

                    {* Remove button *}
                    <form class="vs-cart__remove-form" method="post" action="{url_generator route="cart_remove_item" variantId=$purchase->variant->id}">
                        <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
                        <button type="submit" class="vs-btn vs-btn--ghost vs-btn--icon vs-cart__remove" onclick="ajax_remove({$purchase->variant->id});return false;" title="{$lang->cart_remove}" aria-label="{$lang->cart_remove|escape}">
                            {include file='svg.tpl' svgId='remove_icon'}
                        </button>
                    </form>
                </article>
            {/foreach}
        </div>

        <div class="vs-popcart__foot">
            <div class="vs-popcart__total">
                <span class="vs-popcart__total-label" data-language="cart_total_price">{$lang->cart_total_price}</span>
                <span class="vs-popcart__total-value vs-tabular"><span id="fn_cart_total_price">{$cart->total_price|convert}</span> <span class="currency">{$currency->sign|escape}</span></span>
            </div>
            <div class="vs-popcart__actions">
                <a class="vs-btn vs-btn--secondary" href="#" onclick="$.fancybox.close(); return false;">{$lang->cart_continue_shopping}</a>
                <a class="vs-btn vs-btn--primary" href="{url_generator route='cart'}">{$lang->cart_go_to_cart}</a>
            </div>
        </div>
    </div>
{else}
    <div class="vs-popcart">
        <div class="vs-empty vs-empty--center">
            <span class="vs-empty__icon">{include file="svg.tpl" svgId="cart"}</span>
            <div class="vs-empty__title">
                <span data-language="cart_empty">{$lang->cart_empty}</span>
            </div>
            <p class="vs-empty__note" data-language="cart_empty_note">{$lang->cart_empty_note}</p>
            <a class="vs-btn vs-btn--primary" href="{url_generator route='products'}">
                <span data-language="cart_continue_shopping">{$lang->cart_continue_shopping}</span>
            </a>
        </div>
    </div>
{/if}
