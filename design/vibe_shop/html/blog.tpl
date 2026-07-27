<!-- Blog page -->

{* The category rail reuses Task 4's .vs-filters skin verbatim: bottom sheet
   below 992px, static rail from 992px, one element, no second copy. The open
   and close controls deliberately do NOT carry fn_switch_mobile_filter - that
   okay.js handler is a second, independent open/close mechanism that
   desynchronises from vibeSheet. See products.tpl for the full note. *}

<div class="vs-blog">
    <div class="vs-blog__masthead">
        <h1 class="vs-blog__title">
            <span {if $page->id}data-page="{$page->id}"{elseif $category->id}data-blog_category="{$category->id}"{/if}>{$h1|escape}</span>
        </h1>
        {if $description}
            <div class="fn_readmore vs-blog__intro">
                <div class="block__description vs-prose">{$description}</div>
            </div>
        {/if}
    </div>

    <div class="vs-blog__layout">
        <div class="vs-blog__main">
            <div class="vs-blog__toolbar">
                <button type="button" class="vs-btn vs-btn--secondary vs-filters__open hidden-lg-up" data-vs-sheet-open="vs_blog_rail" aria-controls="vs_blog_rail" aria-expanded="false">
                    {include file="svg.tpl" svgId="catalog_icon"}
                    <span data-language="blog_catalog">{$lang->blog_catalog}</span>
                </button>
            </div>

            {if !empty($posts)}
                <div class="vs-posts">
                    {foreach $posts as $post}
                        {include 'post_list.tpl' cardHeading='h2'}
                    {/foreach}
                </div>
                {* Pagination *}
                <div class="products_pagination">
                    {include file='pagination.tpl'}
                </div>
            {else}
                <div class="vs-empty vs-empty--center">
                    <span class="vs-empty__icon">{include file="svg.tpl" svgId="description_icon"}</span>
                    <p class="vs-empty__title" data-language="products_not_found">{$lang->products_not_found}</p>
                </div>
            {/if}
        </div>

        {* Sidebar with blog *}
        <aside id="vs_blog_rail" class="fn_mobile_toogle vs-filters vs-sheet vs-blog__aside" aria-label="{$lang->blog_catalog|escape}">
            <div class="vs-filters__bar">
                <span class="vs-filters__heading" data-language="blog_catalog">{$lang->blog_catalog}</span>
                <button type="button" class="vs-btn vs-btn--ghost vs-btn--icon vs-filters__close" data-vs-sheet-close aria-label="{$lang->mobile_filter_close|escape}">
                    {include file="svg.tpl" svgId="close"}
                </button>
            </div>
            <div class="vs-filters__scroll">
                {include 'blog_sidebar.tpl'}
            </div>
        </aside>
    </div>
</div>

<div class="vs-sheet__backdrop"></div>
