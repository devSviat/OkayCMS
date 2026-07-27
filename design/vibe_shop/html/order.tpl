<!-- Order page -->

<div class="vs-order">
    <div class="vs-order__hero">
        <span class="vs-order__hero-icon">{include file="svg.tpl" svgId="success_icon"}</span>
        <h1 class="vs-order__hero-title">
            <span data-language="order_greeting">{$lang->order_greeting}</span>
            <span class="vs-order__number vs-tabular">№ {$order->id}</span>
            <span data-language="order_success_issued">{$lang->order_success_issued}</span>
        </h1>
        <p class="vs-order__hero-note" data-language="order_success_text">
            {* The comma sits inside <strong> on purpose: the response is
               re-indented before it is sent, and a newline between </strong>
               and the comma collapses to a visible space. *}
            <strong>{$order->name|escape},</strong> {$lang->order_success_text}
        </p>
    </div>

    <div class="vs-cart">
        <div class="vs-cart__main">
            {if !$order->paid}
                {if $payment_methods && !$payment_method && $order->total_price>0}
                    {* Payments *}
                    <section class="vs-panel">
                        <h2 class="vs-panel__title">
                            {include file="svg.tpl" svgId="money_icon"}
                            <span data-language="order_payment_details">{$lang->order_payment_details}</span>
                        </h2>
                        <form method="post">
                            <div class="vs-options">
                                {foreach $payment_methods as $payment_method}
                                    <div class="vs-option">
                                        <label class="vs-option-card{if $payment_method@first} active{/if}" for="payment_{$payment_method->id}">
                                            <input class="vs-option-card__radio" type="radio" name="payment_method_id" value="{$payment_method->id}"{if $payment_method@first} checked{/if} id="payment_{$payment_method->id}">
                                            <span class="vs-option-card__body">
                                                <span class="vs-option-card__name">{$payment_method->name|escape}{$lang->cart_deliveries_to_pay}</span>
                                                <span class="vs-option-card__price vs-tabular">{$order->total_price|convert:$payment_method->currency_id} {$all_currencies[$payment_method->currency_id]->sign}</span>
                                            </span>
                                            {if $payment_method->image}
                                                <span class="vs-option-card__logo">
                                                    <picture>
                                                        {if $settings->support_webp}
                                                            <source type="image/webp" srcset="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir|webp}">
                                                        {/if}
                                                        <source srcset="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir}">
                                                        <img src="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir}" alt="" title="{$payment_method->name|escape}"/>
                                                    </picture>
                                                </span>
                                            {/if}
                                        </label>

                                        {if $payment_method->description}
                                            <div class="vs-option__extra">
                                                {$payment_method->description}
                                            </div>
                                        {/if}
                                    </div>
                                {/foreach}
                            </div>

                            <input type="submit" data-language="cart_checkout" value="{$lang->cart_checkout}" name="checkout" class="vs-btn vs-btn--primary vs-order__pay">
                        </form>
                    </section>
                {elseif $payment_method}
                    {* Selected payment *}
                    <section class="vs-panel">
                        <h2 class="vs-panel__title">
                            {include file="svg.tpl" svgId="money_icon"}
                            <span data-language="order_payment_details">{$lang->order_payment_details}</span>
                        </h2>
                        <div class="vs-order__payment">
                            <div class="vs-order__payment-head">
                                <div>
                                    <span class="vs-order__payment-label" data-language="order_payment">{$lang->order_payment}:</span>
                                    <span class="vs-order__payment-name">{$payment_method->name|escape}</span>
                                </div>
                                <form method="post">
                                    <input class="vs-btn vs-btn--ghost vs-order__payment-change" type="submit" name="reset_payment_method" data-language="order_change_payment" value="{$lang->order_change_payment}"/>
                                </form>
                            </div>
                            {if $payment_method->description}
                                <div class="vs-order__payment-note">{$payment_method->description}</div>
                            {/if}
                            <div class="vs-order__payment-form">
                                {*Payment form is generated by payment module*}
                                {*payment's form HTML code is in the /payment/ModuleName/form.tpl*}
                                {checkout_payment_form order_id=$order->id module=$payment_method->module}
                            </div>
                        </div>
                    </section>
                {/if}
            {/if}

            {* Order details *}
            <section class="vs-panel">
                <h2 class="vs-panel__title">
                    {include file="svg.tpl" svgId="description_icon"}
                    <span data-language="order_details">{$lang->order_details}</span>
                </h2>
                <dl class="vs-order__details">
                    <dt><span data-language="user_order_status">{$lang->user_order_status}</span></dt>
                    <dd>
                        {$order_status->name|escape}
                        {if $order->paid == 1}, <span data-language="status_paid">{$lang->status_paid}</span>{/if}
                    </dd>

                    <dt><span data-language="order_date">{$lang->order_date}</span></dt>
                    <dd>{$order->date|date} <span data-language="order_time">{$lang->order_time}</span> {$order->date|time}</dd>

                    <dt><span data-language="order_number_text">{$lang->order_number_text}</span></dt>
                    <dd class="vs-tabular">№ {$order->id}</dd>

                    <dt><span data-language="order_name">{$lang->order_name}</span></dt>
                    <dd>{$order->name|escape} {$order->last_name|escape}</dd>

                    <dt><span data-language="order_email">{$lang->order_email}</span></dt>
                    <dd>{$order->email|escape}</dd>

                    {if $order->phone}
                        <dt><span data-language="order_phone">{$lang->order_phone}</span></dt>
                        {* The |phone modifier is libphonenumber's parse(), which
                           THROWS on a number it cannot read - and this shop has
                           no phone_default_region set, so every stored number
                           without a leading "+" throws "Missing or invalid
                           default region" and takes the whole confirmation page
                           down with a 500. Phone::isValid() is the same parse
                           inside a try/catch, so it is an exact guard: when it
                           says yes the modifier cannot throw, and when it says
                           no the shop owner still sees the number the customer
                           actually typed.
                           The call is a bare static one on purpose: Smarty
                           blocks call_user_func in templates outright (a
                           compile error, i.e. another 500), and registering the
                           class with Smarty::registerClass would mean editing
                           core. The cost is one compile-time deprecation notice
                           per template compile - the same one the Banners
                           module's DTO constants already emit. *}
                        <dd>{if \Okay\Core\Phone::isValid($order->phone)}{$order->phone|phone}{else}{$order->phone|escape}{/if}</dd>
                    {/if}

                    {if $order->comment}
                        <dt><span data-language="order_comment">{$lang->order_comment}</span></dt>
                        <dd>{$order->comment|escape|nl2br}</dd>
                    {/if}

                    {if $delivery}
                        <dt><span data-language="order_delivery">{$lang->order_delivery}</span></dt>
                        <dd>{$delivery->name|escape}</dd>
                    {/if}
                </dl>
            </section>
        </div>

        {* fn_cart_sticky is kept although nothing initialises sticky.min.js on
           it any more - the offset is CSS now. It stays because it is part of
           the theme's JS hook surface. *}
        <aside class="vs-summary fn_cart_sticky">
            <h2 class="vs-summary__title">
                <span data-language="cart_purchase_title">{$lang->cart_purchase_title}</span>
            </h2>

            <div class="vs-cart__lines vs-cart__lines--static">
                {foreach $purchases as $purchase}
                    <article class="vs-cart__line vs-cart__line--static">
                        {* Product image *}
                        <a class="vs-cart__thumb" href="{url_generator route='product' url=$purchase->product->url}" tabindex="-1" aria-hidden="true">
                            {if $purchase->product->image}
                                <picture>
                                    {if $settings->support_webp}
                                        <source type="image/webp" data-srcset="{$purchase->product->image->filename|resize:140:140|webp}">
                                    {/if}
                                    <source data-srcset="{$purchase->product->image->filename|resize:140:140}">
                                    <img class="lazy" data-src="{$purchase->product->image->filename|resize:140:140}" src="{$rootUrl}/design/{get_theme}/images/xloading.gif" alt=""/>
                                </picture>
                            {else}
                                <span class="vs-cart__thumb-empty">{include file="svg.tpl" svgId="no_image"}</span>
                            {/if}
                        </a>

                        <div class="vs-cart__info">
                            {* Product name *}
                            <a class="vs-cart__name" href="{url_generator route="product" url=$purchase->product->url}">{$purchase->product_name|escape}</a>
                            {if $purchase->variant_name}<div class="vs-cart__variant">{$purchase->variant_name|escape}</div>{/if}
                            {if !$order->closed && $purchase->variant->stock == 0}<div class="vs-stock vs-stock--low">{$lang->product_pre_order}</div>{/if}

                            <div class="vs-cart__unit{if $purchase->discounts} vs-cart__unit--cut{/if}">
                                <span class="vs-tabular">{($purchase->price)|convert}</span>
                                <span class="currency">{$currency->sign}</span>
                                {if $purchase->variant->units}<span>/ {$purchase->variant->units|escape}</span>{/if}
                                {if $purchase->discounts}
                                    <a href="javascript:;" class="discount_tooltip vs-cart__discount-link" title="{$lang->purchase_discount__tooltip}" data-src="#fn_purchase_discount_detail_{$purchase->variant->id}" data-fancybox="hello_{$purchase->variant->id}" aria-label="{$lang->purchase_discount__tooltip|escape}">{include file="svg.tpl" svgId="sale_icon"}</a>
                                {/if}
                            </div>
                        </div>

                        <div class="vs-cart__qty-static vs-tabular">&times;{$purchase->amount|escape}</div>

                        <div class="vs-cart__linetotal vs-tabular">{($purchase->price*$purchase->amount)|convert} <span class="currency">{$currency->sign}</span></div>

                        {if $purchase->discounts}
                        <div class="hidden">
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

            {* Discounts *}
            {if $discounts}
                {foreach $discounts as $discount}
                    <div class="vs-summary__row vs-summary__row--discount">
                        <span class="vs-summary__label">{$discount->name}</span>
                        <span class="vs-summary__value vs-tabular">{$discount->percentDiscount|string_format:"%.2f"} % &minus;{$discount->absoluteDiscount|convert} <span class="currency">{$currency->sign|escape}</span></span>
                    </div>
                {/foreach}
            {/if}

            {if !$delivery->hide_front_delivery_price && ($order->separate_delivery || !$order->separate_delivery && $order->delivery_price > 0)}
                <div class="vs-summary__row">
                    <span class="vs-summary__label">{$delivery->name|escape}</span>
                    <span class="vs-summary__value vs-tabular">{$order->delivery_price|convert} <span class="currency">{$currency->sign|escape}</span></span>
                </div>
            {/if}

            <div class="vs-summary__row vs-summary__row--total">
                <span class="vs-summary__label" data-language="cart_total_price">{$lang->cart_total_price}</span>
                <span class="vs-summary__grand vs-tabular">{$order->total_price|convert} <span class="currency">{$currency->sign|escape}</span></span>
            </div>
        </aside>
    </div>
</div>
