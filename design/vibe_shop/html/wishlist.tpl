<!-- page title -->
{$meta_title = $lang->wishlist_title scope=global}

<div class="vs-page">
    {* Page heading *}
    <div class="vs-page__masthead">
        <h1 class="vs-page__title">
            <span data-language="wishlist_header">{$lang->wishlist_header}</span>
        </h1>
    </div>

    {if $description}
        <div class="block__description vs-prose vs-page__intro">{$description}</div>
    {/if}

    {* count() on null is a fatal in PHP 8 - the collection is checked first. *}
    {if !empty($wishlist->products)}
        {* The card is the grid item, exactly as in products_content.tpl - no
           .product_item wrapper and no .products_list, whose
           :not(.swiper-container) flex rule outranks .vs-catalogue__grid and
           would collapse the grid to one column. *}
        <div class="fn_wishlist_page vs-catalogue__grid">
            {* Список обраних товарів *}
            {foreach $wishlist->products as $product}
                {include "product_list.tpl"}
            {/foreach}
        </div>
    {else}
        <div class="vs-empty vs-empty--center">
            <span class="vs-empty__icon">{include file="svg.tpl" svgId="heart"}</span>
            <p class="vs-empty__title" data-language="wishlist_empty">{$lang->wishlist_empty}</p>
            <a class="vs-btn vs-btn--primary" href="{url_generator route='products'}">
                <span data-language="index_categories">{$lang->index_categories}</span>
            </a>
        </div>
    {/if}
</div>
