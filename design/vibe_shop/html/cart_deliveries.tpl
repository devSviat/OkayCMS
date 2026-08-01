{if $deliveries}
    {* Delivery *}
    <section class="vs-panel">
        <h2 class="vs-panel__title">
            {include file="svg.tpl" svgId="delivery_icon"}
            <span data-language="cart_delivery">{$lang->cart_delivery}</span>
        </h2>
        <div class="vs-options">
            {foreach $deliveries as $delivery}
                {* .delivery__item is a JS contract, not decoration: okay.js's
                   update_delivery_module_data() and the Nova Poshta module both
                   reach the module fields with .closest('.delivery__item'). *}
                <div class="delivery__item fn_delivery_item vs-option">
                    <label class="vs-option-card{if $active_delivery->id == $delivery->id} active{/if}" for="deliveries_{$delivery->id}">
                        {*NOTICE: Зверніть увагу, data-total_price зберігається в основній валюті сайту*}
                        <input class="vs-option-card__radio"
                               id="deliveries_{$delivery->id}"
                               onchange="okay.change_payment_method(); update_delivery_module_data();"
                               data-module_id="{$delivery->module_id}"
                               data-payment_method_ids="{join($delivery->payment_methods_ids, ',')}"
                               data-total_price="{$delivery->total_price_with_delivery}"
                               data-delivery_price="{$delivery->price}"
                               data-is_free_delivery="{$delivery->is_free_delivery|intval}"
                               data-separate_payment="{$delivery->separate_payment|intval}"
                               data-hide_front_delivery_price="{$delivery->hide_front_delivery_price|intval}"
                               type="radio"
                               name="delivery_id"
                               value="{$delivery->id}"
                                {if $active_delivery->id == $delivery->id} checked{/if} />
                        <span class="vs-option-card__body">
                            <span class="vs-option-card__name">{$delivery->name|escape}</span>
                            <span class="vs-option-card__price{if $delivery->hide_front_delivery_price} hidden{/if}"><span class="fn_delivery_price">{$delivery->delivery_price_text}</span></span>
                        </span>
                        {if $delivery->image}
                            <span class="vs-option-card__logo">
                                <picture>
                                    {if $settings->support_webp}
                                        <source type="image/webp" data-srcset="{$delivery->image|resize:80:30:false:$config->resized_deliveries_dir|webp}">
                                    {/if}
                                    <source data-srcset="{$delivery->image|resize:80:30:false:$config->resized_deliveries_dir}">
                                    <img class="lazy" data-src="{$delivery->image|resize:80:30:false:$config->resized_deliveries_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="" title="{$delivery->name|escape}"/>
                                </picture>
                            </span>
                        {/if}
                    </label>

                    {$block = {get_design_block block='front_cart_delivery' vars=['delivery' => $delivery]}}
                    {if $delivery->description || $block}
                        {* Revealed only for the picked option. okay.js disables the
                           module inputs of every other delivery, so showing them
                           all would offer fields that cannot be filled in. *}
                        <div class="vs-option__extra">
                            {$delivery->description}
                            {if $block}
                                <div class="fn_delivery_module_html">
                                    {$block}
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            {/foreach}
        </div>
    </section>

    {* Payment methods *}
    {if $payment_methods}
        <div class="fn_payments_block"{if !$active_delivery->payment_methods_ids} style="display: none;"{/if}>
            <section class="vs-panel">
                <h2 class="vs-panel__title">
                    {include file="svg.tpl" svgId="money_icon"}
                    <span data-language="cart_payment">{$lang->cart_payment}</span>
                </h2>
                <div class="vs-options">
                    {foreach $payment_methods as $payment_method}
                        <div class="payment_method__item fn_payment_method__item fn_payment_method__item_{$payment_method->id} vs-option"{if !in_array($payment_method->id, $active_delivery->payment_methods_ids)} style="display: none;"{/if}>
                            <label class="vs-option-card{if $active_payment->id==$payment_method->id} active{/if}" for="payment_{$payment_method->id}">
                                <input class="vs-option-card__radio" id="payment_{$payment_method->id}" type="radio" name="payment_method_id" data-currency_id="{$payment_method->currency_id}" data-auto_submit="{$payment_method->auto_submit}" value="{$payment_method->id}"{if $active_payment->id==$payment_method->id} checked{/if} />
                                <span class="vs-option-card__body">
                                    <span class="vs-option-card__name">{$payment_method->name|escape}{$lang->cart_deliveries_to_pay}</span>
                                    <span class="vs-option-card__price vs-tabular"><span class="fn_payment_price">{$active_delivery->total_price_with_delivery|convert:$payment_method->currency_id}</span> {$all_currencies[$payment_method->currency_id]->sign|escape}</span>
                                </span>
                                {if $payment_method->image}
                                    <span class="vs-option-card__logo">
                                        <picture>
                                            {if $settings->support_webp}
                                                <source type="image/webp" data-srcset="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir|webp}">
                                            {/if}
                                            <source data-srcset="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir}">
                                            <img class="lazy" data-src="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="" title="{$payment_method->name|escape}"/>
                                        </picture>
                                    </span>
                                {/if}
                            </label>

                            {$block = {get_design_block block='front_cart_payment' vars=['payment_method' => $payment_method]}}
                            {if $payment_method->description || $block}
                                <div class="vs-option__extra">
                                    {$payment_method->description}
                                    {if $block}
                                        <div class="fn_payment_module_html">
                                            {$block}
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/foreach}
                </div>
            </section>
        </div>
    {/if}
{/if}
