<!-- Сomparison informer (given by Ajax) -->
{if $comparison->products|count > 0}
    <a class="vs-informer" href="{url_generator route="comparison"}" title="{$lang->comparison_header|escape}" aria-label="{$lang->comparison_header|escape}">
        {include file="svg.tpl" svgId="compare"}
        <span class="vs-informer__count compare_counter vs-tabular">{$comparison->products|count}</span>
    </a>
{else}
    <div class="vs-informer vs-informer--idle" title="{$lang->comparison_header|escape}">
        {include file="svg.tpl" svgId="compare"}
    </div>
{/if}
