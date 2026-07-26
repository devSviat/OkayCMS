<!-- Cart informer (given by Ajax) -->
{if $cart->isEmpty === false}
    <a href="{url_generator route='cart'}" class="vs-informer vs-informer--cart" title="{$lang->cart_header|escape}" aria-label="{$lang->cart_header|escape}">
        {include file="svg.tpl" svgId="cart"}
        <span class="vs-informer__count cart_counter vs-tabular">{$cart->total_products}</span>
    </a>
{else}
    <div class="vs-informer vs-informer--cart vs-informer--idle" title="{$lang->cart_header|escape}">
        {include file="svg.tpl" svgId="cart"}
    </div>
{/if}
