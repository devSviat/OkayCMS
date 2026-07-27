<!-- Cart informer (given by Ajax) -->
{if $cart->isEmpty === false}
    <a href="{url_generator route='cart'}" class="vs-informer" title="{$lang->cart_header|escape}" aria-label="{$lang->cart_header|escape}">
        {include file="svg.tpl" svgId="cart"}
        <span class="vs-informer__count cart_counter vs-tabular">{$cart->total_products}</span>
    </a>
{else}
    {* Not a link while the cart is empty - /cart would only show the empty
       state - but it still needs a name, or a screen reader reaches an
       unlabelled graphic between the wishlist and the account controls. *}
    <div class="vs-informer vs-informer--idle" title="{$lang->cart_header|escape}" role="img" aria-label="{$lang->cart_header|escape}: {$lang->cart_empty|escape}">
        {include file="svg.tpl" svgId="cart"}
    </div>
{/if}
