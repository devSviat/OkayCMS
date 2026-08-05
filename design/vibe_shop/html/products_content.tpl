{* The ajax-replaced region. okay.js swaps this whole block into
   #fn_products_content on every price-slider filter and ajax page change, so
   the current result count travels with it on .vs-catalogue__state and vibe.js
   copies it into the toolbar and the sheet's apply button. The strings are
   built here rather than in JS so the plural form stays the translator's. *}
{assign var="vs_total" value=$total_products_num|default:0}
{assign var="vs_units" value=$vs_total|plural:$lang->products_count_plural1:$lang->products_count_plural2:$lang->products_count_plural3}
<div class="vs-catalogue__state" data-vs-count="{$vs_total} {$vs_units|escape}" data-vs-apply="{$lang->products_show|escape} {$vs_total} {$vs_units|escape}"></div>

{if $products}
    <div class="vs-catalogue__grid">
        {foreach $products as $product}
            {include file="product_list.tpl"}
        {/foreach}
    </div>
{else}
    <div class="vs-empty">
        {* The icon that the other eight empty states all carry. It was the only
           one without one - the left alignment here is deliberate (the rail
           column collapses on an empty result set, so a centred block would sit
           off-centre in the page), but the missing glyph was not. *}
        <span class="vs-empty__icon">{include file="svg.tpl" svgId="search"}</span>
        <p class="vs-empty__title" data-language="products_not_found">{$lang->products_not_found}</p>
        {* The note says "try changing or resetting the filters", so it belongs to
           the same branch as the reset button. On an unfiltered empty page there
           is no rail, no filter trigger and nothing to reset, and the note would
           point at an action the page does not offer. *}
        {if $is_filter_page}
            <p class="vs-empty__note" data-language="products_not_found_note">{$lang->products_not_found_note}</p>
            <form method="post">
                <button type="submit" name="prg_seo_hide" class="fn_filter_reset vs-btn vs-btn--primary" value="{if $category}{url_generator route="category" url=$category->url absolute=1}{elseif $brand}{url_generator route="brand" url=$brand->url absolute=1}{else}{url_generator route=$route_name absolute=1}{/if}">
                    {include file="svg.tpl" svgId="reset_icon"}
                    <span>{$lang->selected_features_reset}</span>
                </button>
            </form>
        {else}
            <a class="vs-btn vs-btn--secondary" href="{url_generator route='main'}">
                <span data-language="breadcrumb_home">{$lang->breadcrumb_home}</span>
            </a>
        {/if}
    </div>
{/if}
