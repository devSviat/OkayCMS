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
        <p class="vs-empty__title" data-language="products_not_found">{$lang->products_not_found}</p>
        <p class="vs-empty__note" data-language="products_not_found_note">{$lang->products_not_found_note}</p>
        {if $is_filter_page}
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
