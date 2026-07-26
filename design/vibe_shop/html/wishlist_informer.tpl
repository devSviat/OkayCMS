<!-- Информер избранного (отдаётся аяксом) -->
{if $wishlist->products|count > 0}
    <a class="vs-informer" href="{url_generator route="wishlist"}" title="{$lang->wishlist_header|escape}" aria-label="{$lang->wishlist_header|escape}">
        {include file="svg.tpl" svgId="heart"}
        <span class="vs-informer__count wishlist_counter vs-tabular">{$wishlist->products|count}</span>
    </a>
{else}
    <span class="vs-informer vs-informer--idle" title="{$lang->wishlist_header|escape}">
        {include file="svg.tpl" svgId="heart"}
    </span>
{/if}
