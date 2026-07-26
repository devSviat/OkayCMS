<!-- The Categories page -->

{* The filter panel is rendered ONCE. Below 992px it is the .vs-sheet bottom
   sheet, from 992px the same element is the static left rail - the switch is
   pure CSS, so okay.js binds fn_features a single time and the ajax filter
   path is unaffected. The sheet is a descendant of .main, never of .vs-header:
   the header opens a stacking context at z-index 200 that would cap it.

   The open and close buttons deliberately do NOT carry fn_switch_mobile_filter.
   okay.js binds that class non-delegated at ready and toggles .opened on every
   .fn_mobile_toogle, which is a second, independent open/close mechanism: it
   desynchronises from vibeSheet's .is-open on the Escape and apply paths and
   leaves an inverted .opened flag behind. vibeSheet owns every path here, and
   the class is still emitted by blog.tpl, brands.tpl, post.tpl and
   blog_sidebar.tpl for the legacy sidebars that media.css actually styles. *}

<div class="vs-catalogue">
    <div class="vs-catalogue__masthead">
        <h1 class="vs-catalogue__title"{if $category} data-category="{$category->id}"{/if}{if $brand} data-brand="{$brand->id}"{/if}>{$h1|escape}</h1>

        {* Result count. Server-rendered so it is right without JavaScript;
           vibe.js only refreshes it after an okay.js ajax filter round-trip. *}
        <p class="vs-results">
            <span class="vs-results__value">{$total_products_num|default:0} {$total_products_num|default:0|plural:$lang->products_count_plural1:$lang->products_count_plural2:$lang->products_count_plural3}</span>
        </p>

        {if !empty($annotation)}
            <div class="fn_readmore vs-catalogue__intro">
                <div class="block__description">{$annotation}</div>
            </div>
        {/if}
    </div>

    <div class="vs-catalogue__layout">
        {* Filters: bottom sheet on phones, left rail from 992px *}
        <aside id="vs_filters" class="fn_mobile_toogle vs-filters vs-sheet" aria-label="{$lang->filters|escape}">
            <div class="vs-filters__bar">
                <span class="vs-filters__heading" data-language="filters">{$lang->filters}</span>
                <button type="button" class="vs-btn vs-btn--ghost vs-btn--icon vs-filters__close" data-vs-sheet-close aria-label="{$lang->mobile_filter_close|escape}">
                    {include file="svg.tpl" svgId="close"}
                </button>
            </div>

            <div class="vs-filters__scroll">
                <div class="fn_features">
                    {if !$settings->deferred_load_features}
                        {include file='features.tpl'}
                    {else}
                        {* Deferred load features *}
                        <div class='fn_skeleton_load'>
                            {section name=foo start=1 loop=7 step=1}
                                <div class='vs-skeleton vs-skeleton--filter'></div>
                            {/section}
                        </div>
                    {/if}
                </div>

                {* Browsed products *}
                <div class="vs-filters__browsed">
                    {include file='browsed_products.tpl'}
                </div>
            </div>

            <div class="vs-filters__foot">
                {if $category}
                    <form method="post">
                        <button type="submit" name="prg_seo_hide" class="fn_filter_reset vs-btn vs-btn--secondary vs-filters__reset" value="{url_generator route="category" url=$category->url absolute=1}">
                            {include file="svg.tpl" svgId="reset_icon"}
                            <span>{$lang->mobile_filter_reset}</span>
                        </button>
                    </form>
                {elseif $brand}
                    <form method="post">
                        <button type="submit" name="prg_seo_hide" class="fn_filter_reset vs-btn vs-btn--secondary vs-filters__reset" value="{url_generator route="brand" url=$brand->url absolute=1}">
                            {include file="svg.tpl" svgId="reset_icon"}
                            <span>{$lang->mobile_filter_reset}</span>
                        </button>
                    </form>
                {/if}
                <button type="button" class="vs-btn vs-btn--primary vs-filters__apply" data-vs-sheet-close>
                    <span class="vs-filters__apply_label">{$lang->products_show} {$total_products_num|default:0} {$total_products_num|default:0|plural:$lang->products_count_plural1:$lang->products_count_plural2:$lang->products_count_plural3}</span>
                </button>
            </div>
        </aside>

        <div class="vs-catalogue__main">
            <div class="vs-catalogue__toolbar">
                {* Product Sorting *}
                <div class="fn_products_sort vs-sort">
                    {include file="products_sort.tpl"}
                </div>
                {* Mobile button filters *}
                <button type="button" class="vs-btn vs-btn--secondary vs-filters__open hidden-lg-up" aria-controls="vs_filters" aria-expanded="false">
                    {include file="svg.tpl" svgId="filter_icon"}
                    <span data-language="filters">{$lang->filters}</span>
                </button>
            </div>

            {* Applied filters, as removable chips above the grid *}
            <div class="fn_selected_features">
                {if !$settings->deferred_load_features}
                    {include file='selected_features.tpl'}
                {/if}
            </div>

            <div class="vs-catalogue__region">
                {* Product list *}
                <div id="fn_products_content" class="fn_categories vs-catalogue__results">
                    {include file="products_content.tpl"}
                </div>
                {* Loading state. Never hides the list on its own - vibe.js adds
                   .is-loading to the region while an ajax filter is in flight. *}
                <div class="vs-catalogue__skeleton" aria-hidden="true">
                    {section name=skel start=1 loop=9 step=1}
                        <div class="vs-skeleton"></div>
                    {/section}
                </div>
            </div>

            {if $products}
                {* Friendly URLs Pagination *}
                <div class="fn_pagination">
                    {include file='chpu_pagination.tpl'}
                </div>
            {/if}

            {if $description}
                <div class="vs-catalogue__outro">
                    {* Table contents *}
                    {if !empty($table_of_content)}
                        <div class="vs-toc">
                            <div class="vs-toc__title">{$lang->blog_table_contents}</div>
                            <ol>
                                {foreach $table_of_content as $content_item}
                                    <li style="margin-left: {$content_item.header_level*15-15}px">
                                        <a class="fn_ancor_post" href="{$content_item.url|escape}">{$content_item.anchor_text|escape}</a>
                                    </li>
                                {/foreach}
                            </ol>
                        </div>
                    {/if}
                    <div class="block__description">{$description}</div>
                </div>
            {/if}
        </div>
    </div>
</div>

<div class="vs-sheet__backdrop"></div>
