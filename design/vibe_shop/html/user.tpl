{* Account page *}

{* The page title *}
{$meta_title = $lang->user_title scope=global}

{* Two presentations, one markup, exactly as the stock theme had it:
   - from 768px okay.js's .tabs script shows one .tab at a time and
     .tabs__navigation is the switcher;
   - below 768px every .tab is shown and each one is a fn_switch accordion whose
     body is .mobile_tab__content (okay.js slide-toggles $(this).next(), so the
     head and the body must stay adjacent siblings).
   The class names above are that contract and are kept verbatim; presentation
   hangs off the .vs-account__* names beside them so the legacy sheets can go. *}

{* UserHelper::defaultActiveTab() returns the current route name when the route
   is not one of the four tab routes, so on /user $active_tab is the string
   'user' - never empty. The stock template tested empty() and therefore never
   marked anything selected; okay.js's tabs script papered over it by picking
   the first nav item at runtime. Normalised here so the server-rendered markup
   is right on its own, which is what the mobile accordion depends on. *}
{$vs_tab = 'info'}
{if $active_tab == 'orders' || $active_tab == 'comments' || $active_tab == 'favorites' || $active_tab == 'browsed'}
    {$vs_tab = $active_tab}
{/if}

<div class="vs-account">
    <div class="tabs tabs--user vs-account__layout">
        <aside class="vs-account__rail">
            <div class="vs-account__profile">
                <span class="vs-account__avatar">
                    {include file="svg.tpl" svgId="comment-user_icon"}
                </span>
                <span class="vs-account__who">{$user->name|escape}</span>
            </div>
            <nav class="tabs__navigation tabs__navigation--user vs-account__nav">
                <a class="tabs__link vs-account__nav_link{if $vs_tab == 'info'} selected{/if}" data-history_location="{url_generator route="user"}" href="#user_info">
                    {include file="svg.tpl" svgId="user_account_icon"}
                    <span data-language="user_personal_title">{$lang->user_personal_title}</span>
                </a>
                {if $orders}
                    <a class="tabs__link vs-account__nav_link{if $vs_tab == 'orders'} selected{/if}" data-history_location="{url_generator route="user_orders"}" href="#user_orders">
                        {include file="svg.tpl" svgId="user_orders_icon"}
                        <span data-language="user_orders_title">{$lang->user_orders_title}</span>
                    </a>
                {/if}
                <a class="tabs__link vs-account__nav_link{if $vs_tab == 'comments'} selected{/if}" data-history_location="{url_generator route="user_comments"}" href="#user_comments">
                    {include file="svg.tpl" svgId="user_comments_icon"}
                    <span data-language="user_comments_title">{$lang->user_comments_title}</span>
                </a>
                {* count() on null is a fatal in PHP 8 - never count the collection. *}
                {if !empty($wishlist->products)}
                    <a class="tabs__link vs-account__nav_link{if $vs_tab == 'favorites'} selected{/if}" data-history_location="{url_generator route="user_favorites"}" href="#user_wishlist">
                        {include file="svg.tpl" svgId="user_heart_icon"}
                        <span data-language="user_wishlist_title">{$lang->user_wishlist_title}</span>
                    </a>
                {/if}
                {get_browsed_products var=browsed_products limit=16}
                {if $browsed_products}
                    <a class="tabs__link vs-account__nav_link{if $vs_tab == 'browsed'} selected{/if}" data-history_location="{url_generator route="user_browsed"}" href="#user_browsed">
                        {include file="svg.tpl" svgId="user_broused_icon"}
                        <span data-language="user_browsed_title">{$lang->user_browsed_title}</span>
                    </a>
                {/if}
            </nav>
            {* Logout *}
            <a class="button__logout vs-account__logout" href="{url_generator route='logout'}">
                {include file="svg.tpl" svgId="exit_icon"}
                <span data-language="user_logout">{$lang->user_logout}</span>
            </a>
        </aside>

        <div class="user_container vs-account__main">
            <div class="tabs__content user_container__boxed vs-account__panels">

                <section id="user_info" class="tab vs-account__panel{if $vs_tab == 'info'} vs-account__panel--open{/if}"{if $vs_tab == 'info'} style="display: block;"{/if}>
                    <h2 class="fn_switch vs-account__panel_head{if $vs_tab == 'info'} active{/if}">
                        <span data-language="user_personal_title">{$lang->user_personal_title}</span>
                        <span class="vs-account__panel_chevron">{include file="svg.tpl" svgId="chevron"}</span>
                    </h2>
                    <div class="mobile_tab__content vs-account__panel_body">
                        <form method="post" class="fn_validate_register vs-account__form">
                            {if $user_updated}
                                <p class="vs-note vs-note--ok" role="status">
                                    {include file="svg.tpl" svgId="success_icon"}
                                    <span data-language="user_messages_success">{$lang->user_messages_success}</span>
                                </p>
                            {/if}

                            <div class="vs-account__cols">
                                <div class="vs-panel vs-account__panel_card">
                                    <h2 class="vs-panel__title">
                                        {include file="svg.tpl" svgId="comment_icon"}
                                        <span data-language="cart_form_header">{$lang->cart_form_header}</span>
                                    </h2>

                                    {if $error}
                                        <p class="vs-field__error" role="alert">
                                            {if $error == 'empty_name'}
                                                <span data-language="form_enter_name">{$lang->form_enter_name}</span>
                                            {elseif $error == 'empty_email'}
                                                <span data-language="form_enter_email">{$lang->form_enter_email}</span>
                                            {elseif $error == 'empty_password'}
                                                <span data-language="form_enter_password">{$lang->form_enter_password}</span>
                                            {elseif $error == 'user_exists'}
                                                <span data-language="register_user_registered">{$lang->register_user_registered}</span>
                                            {else}
                                                {$error|escape}
                                            {/if}
                                        </p>
                                    {/if}

                                    <div class="vs-fields">
                                        <div class="vs-form__row">
                                            <label class="vs-form__label" for="vs_user_name">{$lang->form_name}*</label>
                                            <input id="vs_user_name" class="vs-field vs-form__input" value="{$user->name|escape}" name="name" type="text" autocomplete="given-name" />
                                        </div>
                                        <div class="vs-form__row">
                                            <label class="vs-form__label" for="vs_user_last_name">{$lang->form_last_name}</label>
                                            <input id="vs_user_last_name" class="vs-field vs-form__input" value="{$user->last_name|escape}" name="last_name" type="text" autocomplete="family-name" />
                                        </div>
                                        <div class="vs-form__row">
                                            <label class="vs-form__label" for="vs_user_email">{$lang->form_email}*</label>
                                            <input id="vs_user_email" class="vs-field vs-form__input" value="{$user->email|escape}" name="email" type="email" autocomplete="email" />
                                        </div>
                                        {* The stored number is printed raw: |phone is
                                           PhoneNumberUtil::parse(), which throws when
                                           phone_default_region is unset, and it is
                                           going back into an editable field anyway. *}
                                        <div class="vs-form__row">
                                            <label class="vs-form__label" for="vs_user_phone">{$lang->form_phone}</label>
                                            <input id="vs_user_phone" class="vs-field vs-form__input" value="{$user->phone|escape}" name="phone" type="tel" autocomplete="tel" />
                                        </div>
                                        <div class="vs-form__row">
                                            <button type="button" class="change_pass vs-account__pass_toggle" onclick="$('#fn_password').toggle().prop('type', 'password').prop('name', 'password');return false;">
                                                <span data-language="user_change_password">{$lang->user_change_password}</span>
                                                {include file="svg.tpl" svgId="arrow_right2"}
                                            </button>
                                            <input class="vs-field vs-form__input" id="fn_password" value="" name="" type="" style="display:none;" autocomplete="new-password" aria-label="{$lang->user_change_password|escape}"/>
                                        </div>
                                    </div>
                                </div>

                                <div class="vs-account__col">
                                    {include 'user_deliveries.tpl'}
                                </div>
                            </div>

                            {* Submit button *}
                            <button type="submit" class="vs-btn vs-btn--primary vs-account__save" name="user_save" value="{$lang->form_save}">
                                <span data-language="form_save">{$lang->form_save}</span>
                            </button>
                        </form>
                    </div>
                </section>

                {if $orders}
                    <section id="user_orders" class="tab vs-account__panel{if $vs_tab == 'orders'} vs-account__panel--open{/if}"{if $vs_tab == 'orders'} style="display: block;"{/if}>
                        <h2 class="fn_switch vs-account__panel_head{if $vs_tab == 'orders'} active{/if}">
                            <span data-language="user_orders_title">{$lang->user_orders_title}</span>
                            <span class="vs-account__panel_chevron">{include file="svg.tpl" svgId="chevron"}</span>
                        </h2>
                        <div class="mobile_tab__content vs-account__panel_body">
                            <div class="vs-table-wrap">
                                <table class="table vs-table">
                                    <thead>
                                        <tr>
                                            <th class="vs-table__toggle_head"></th>
                                            <th><span data-language="user_number_of_order">{$lang->user_number_of_order}</span></th>
                                            <th><span data-language="user_order_date">{$lang->user_order_date}</span></th>
                                            <th><span data-language="user_order_status">{$lang->user_order_status}</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {foreach $orders as $order}
                                            <tr class="vs-table__row">
                                                <td class="vs-table__toggle_cell">
                                                    <a class="fn_user_orders_switch vs-table__toggle" href="javascript:;" aria-label="{$lang->user_order_number|escape}{$order->id}">
                                                        {include file="svg.tpl" svgId="chevron"}
                                                    </a>
                                                </td>
                                                {* Order number *}
                                                <td>
                                                    <a class="vs-table__link" href='{url_generator route="order" url=$order->url}'><span data-language="user_order_number">{$lang->user_order_number}</span>{$order->id}</a>
                                                </td>
                                                {* Order date *}
                                                <td class="vs-tabular">{$order->date|date}</td>
                                                {* Order status *}
                                                <td>
                                                    {if $order->paid == 1}
                                                        <span class="vs-stock vs-stock--in" data-language="status_paid">{$lang->status_paid}</span>
                                                    {/if}
                                                    <span>{$orders_status[$order->status_id]->name|escape}</span>
                                                </td>
                                            </tr>
                                            <tr class="user_orders_hidden vs-table__detail">
                                                <td colspan="4">
                                                    <div class="vs-order-lines">
                                                        {foreach $order->purchases as $purchase}
                                                            <div class="vs-cart__line vs-cart__line--static">
                                                                {* Product image *}
                                                                <a class="vs-cart__thumb" href="{url_generator route='product' url=$purchase->product->url}" tabindex="-1" aria-hidden="true">
                                                                    {if $purchase->product->image}
                                                                        <picture>
                                                                            {if $settings->support_webp}
                                                                                <source type="image/webp" data-srcset="{$purchase->product->image->filename|resize:70:70|webp}">
                                                                            {/if}
                                                                            <source data-srcset="{$purchase->product->image->filename|resize:70:70}">
                                                                            <img class="lazy" data-src="{$purchase->product->image->filename|resize:70:70}" src="{$rootUrl}/design/{get_theme}/images/xloading.gif" alt=""/>
                                                                        </picture>
                                                                    {else}
                                                                        <span class="vs-cart__thumb-empty">{include file="svg.tpl" svgId="no_image"}</span>
                                                                    {/if}
                                                                </a>
                                                                <div class="vs-cart__info">
                                                                    <a class="vs-cart__name" href="{url_generator route="product" url=$purchase->product->url}">{$purchase->product_name|escape}</a>
                                                                    {if $purchase->variant_name}
                                                                        <span class="vs-cart__variant">{$purchase->variant_name|escape}</span>
                                                                    {/if}
                                                                    {if !$order->closed && $purchase->variant->stock == 0}
                                                                        <span class="vs-stock vs-stock--low">{$lang->product_pre_order}</span>
                                                                    {/if}
                                                                    <span class="vs-cart__unit vs-tabular{if $purchase->discounts} vs-cart__unit--cut{/if}">
                                                                        {($purchase->price)|convert} {$currency->sign|escape}{if $purchase->variant->units} / {$purchase->variant->units|escape}{/if}
                                                                        {if $purchase->discounts}
                                                                            <a href="javascript:;" class="discount_tooltip vs-cart__discount-link" title="{$lang->purchase_discount__tooltip}" data-src="#fn_purchase_discount_detail_{$purchase->variant->id}" data-fancybox="hello_{$purchase->variant->id}" aria-label="{$lang->purchase_discount__tooltip|escape}">{include file="svg.tpl" svgId="sale_icon"}</a>
                                                                        {/if}
                                                                    </span>
                                                                </div>
                                                                <span class="vs-cart__qty-static vs-tabular">&times;{$purchase->amount|escape}</span>
                                                                <span class="vs-cart__linetotal vs-tabular">{($purchase->price*$purchase->amount)|convert} {$currency->sign|escape}</span>

                                                                {* Per-line discount breakdown, opened by fancybox from the rose tag above. *}
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
                                                                                        <span class="vs-tabular">{$discount->priceBeforeDiscount} {$currency->sign|escape}</span>
                                                                                    </div>
                                                                                    <div class="vs-discounts__row vs-discounts__row--cut">
                                                                                        <span class="vs-discounts__label" data-language="purchase_discount__discount">{$lang->purchase_discount__discount}</span>
                                                                                        <span class="vs-tabular">{$discount->percentDiscount|string_format:"%.2f"} % &minus; {$discount->absoluteDiscount|convert} {$currency->sign|escape}</span>
                                                                                    </div>
                                                                                    <div class="vs-discounts__row vs-discounts__row--total">
                                                                                        <span class="vs-discounts__label" data-language="purchase_discount__total">{$lang->purchase_discount__total}</span>
                                                                                        <span class="vs-tabular">{$discount->priceAfterDiscount} {$currency->sign|escape}</span>
                                                                                    </div>
                                                                                </div>
                                                                            {/foreach}
                                                                        </div>
                                                                    </div>
                                                                {/if}
                                                            </div>
                                                        {/foreach}
                                                    </div>

                                                    <dl class="vs-order-totals">
                                                        {* Discount *}
                                                        {if $order->discount > 0}
                                                            <dt data-language="cart_discount">{$lang->cart_discount}</dt>
                                                            <dd class="vs-tabular">{$order->discount}%</dd>
                                                        {/if}
                                                        {if $order->coupon_discount > 0}
                                                            <dt data-language="cart_coupon">{$lang->cart_coupon}</dt>
                                                            <dd class="vs-tabular">&minus;{$order->coupon_discount|convert} {$currency->sign|escape}</dd>
                                                        {/if}
                                                        {if !$delivery->hide_front_delivery_price && ($order->separate_delivery || !$order->separate_delivery && $order->delivery_price > 0)}
                                                            <dt>{$delivery->name|escape}</dt>
                                                            <dd class="vs-tabular">{$order->delivery_price|convert} {$currency->sign|escape}</dd>
                                                        {/if}
                                                        <dt class="vs-order-totals__grand" data-language="cart_total_price">{$lang->cart_total_price}</dt>
                                                        <dd class="vs-order-totals__grand vs-tabular">{$order->total_price|convert} {$currency->sign|escape}</dd>
                                                    </dl>
                                                </td>
                                            </tr>
                                        {/foreach}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                {/if}

                <section id="user_comments" class="tab vs-account__panel{if $vs_tab == 'comments'} vs-account__panel--open{/if}"{if $vs_tab == 'comments'} style="display: block;"{/if}>
                    <h2 class="fn_switch vs-account__panel_head{if $vs_tab == 'comments'} active{/if}">
                        <span data-language="user_comments_title">{$lang->user_comments_title}</span>
                        <span class="vs-account__panel_chevron">{include file="svg.tpl" svgId="chevron"}</span>
                    </h2>
                    <div class="mobile_tab__content vs-account__panel_body">
                        {include 'user_comments.tpl'}
                    </div>
                </section>

                {if !empty($wishlist->products)}
                    <section id="user_wishlist" class="tab vs-account__panel{if $vs_tab == 'favorites'} vs-account__panel--open{/if}"{if $vs_tab == 'favorites'} style="display: block;"{/if}>
                        <h2 class="fn_switch vs-account__panel_head{if $vs_tab == 'favorites'} active{/if}">
                            <span data-language="user_wishlist_title">{$lang->user_wishlist_title}</span>
                            <span class="vs-account__panel_chevron">{include file="svg.tpl" svgId="chevron"}</span>
                        </h2>
                        <div class="mobile_tab__content vs-account__panel_body">
                            <div class="fn_wishlist_page vs-catalogue__grid">
                                {foreach $wishlist->products as $product}
                                    {include "product_list.tpl"}
                                {/foreach}
                            </div>
                        </div>
                    </section>
                {/if}

                {if $browsed_products}
                    <section id="user_browsed" class="tab vs-account__panel{if $vs_tab == 'browsed'} vs-account__panel--open{/if}"{if $vs_tab == 'browsed'} style="display: block;"{/if}>
                        <h2 class="fn_switch vs-account__panel_head{if $vs_tab == 'browsed'} active{/if}">
                            <span data-language="user_browsed_title">{$lang->user_browsed_title}</span>
                            <span class="vs-account__panel_chevron">{include file="svg.tpl" svgId="chevron"}</span>
                        </h2>
                        <div class="mobile_tab__content vs-account__panel_body">
                            <div class="vs-catalogue__grid">
                                {foreach $browsed_products as $product}
                                    {include "product_list.tpl"}
                                {/foreach}
                            </div>
                        </div>
                    </section>
                {/if}
            </div>
        </div>
    </div>
</div>
