<!-- The main page template -->

{* Featured products.
   Shape 1 of 5 on this page: a rail on the bare canvas with the "see all"
   affordance in the section head. The slide machinery (fn_products_slide,
   products_list, product_item, swiper-*) is okay.js's contract and is kept
   exactly as it was - only the chrome around it changed. *}
{get_featured_products var=featured_products limit=5}
{if $featured_products}
    <section class="vs-home__section container">
        <div class="vs-home__head">
            <h2 class="vs-home__title">
                <span data-language="main_recommended_products">{$lang->main_recommended_products}</span>
            </h2>
            <a class="vs-home__more" href="{url_generator route='products' filtersUrl=['filter' => ['featured']]}">
                <span data-language="main_look_all">{$lang->main_look_all}</span>{include file="svg.tpl" svgId="arrow_right2"}
            </a>
        </div>
        <div class="fn_products_slide products_list row no_gutters vs-home__rail swiper-container">
            <div class="swiper-wrapper">
                {foreach $featured_products as $product}
                    <div class="item product_item swiper-slide no_hover">{include "product_list.tpl"}</div>
                {/foreach}
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>
{/if}

{* Shape 2: the category tile strip. *}
{include "top_categories.tpl"}

{* Shape 3: a second rail, deliberately without a "see all" - there is no
   "all new products" destination, and inventing one would only make this
   block a copy of the first. *}
{get_new_products var=new_products limit=5}
{if $new_products}
    <section class="vs-home__section container">
        <div class="vs-home__head">
            <h2 class="vs-home__title">
                <span data-language="main_new_products">{$lang->main_new_products}</span>
            </h2>
        </div>
        <div class="fn_products_slide products_list row no_gutters vs-home__rail swiper-container">
            <div class="swiper-wrapper">
                {foreach $new_products as $product}
                    <div class="product_item swiper-slide no_hover">{include "product_list.tpl"}</div>
                {/foreach}
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>
{/if}

{* Shape 4: the same rail on a tinted full-bleed band. The band is a value
   step, not a fourth hue - the rose discount badges inside it are still the
   only colour event on the row. *}
{get_discounted_products var=discounted_products limit=5}
{if $discounted_products}
    <section class="vs-home__band">
        <div class="vs-home__section container">
            <div class="vs-home__head">
                <h2 class="vs-home__title">
                    <span data-language="main_discount_products">{$lang->main_discount_products}</span>
                </h2>
                <a class="vs-home__more" href="{url_generator route='products' filtersUrl=['filter' => ['discounted']]}">
                    <span data-language="main_look_all">{$lang->main_look_all}</span>{include file="svg.tpl" svgId="arrow_right2"}
                </a>
            </div>
            <div class="fn_products_slide products_list row no_gutters vs-home__rail swiper-container">
                <div class="swiper-wrapper">
                    {foreach $discounted_products as $product}
                        <div class="product_item swiper-slide no_hover">{include "product_list.tpl"}</div>
                    {/foreach}
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
    </section>
{/if}

{* Shape 5: an editorial split - the shop's own copy beside the brand wall. *}
{get_brands var=all_brands visible_brand=1 limit=9}
{if $description || $all_brands}
    <section class="vs-home__section container">
        <div class="vs-home__split{if !$description || !$all_brands} vs-home__split--single{/if}">
            {if $description}
                <div class="vs-home__about">
                    <h1 class="vs-home__title">{$h1|escape}</h1>
                    <div class="fn_readmore">
                        <div class="vs-prose vs-home__about_body">{$description}</div>
                    </div>
                </div>
            {/if}
            {* Brand list *}
            {if $all_brands}
                <div class="vs-home__brands">
                    <div class="vs-home__head">
                        <h2 class="vs-home__title vs-home__title--sub">
                            <span data-language="main_brands">{$lang->main_brands}</span>
                        </h2>
                        <a class="vs-home__more" href="{url_generator route='brands'}">
                            <span data-language="main_look_all">{$lang->main_look_all}</span>{include file="svg.tpl" svgId="arrow_right2"}
                        </a>
                    </div>
                    <ul class="vs-brands vs-brands--compact">
                        {foreach $all_brands as $b}
                            <li class="vs-brands__item">
                                <a class="vs-brands__link" href="{url_generator route='brand' url=$b->url}" data-brand="{$b->id}" title="{$b->name|escape}">
                                    {if $b->image}
                                        <picture>
                                            {if $settings->support_webp}
                                                <source type="image/webp" data-srcset="{$b->image|resize:100:50:false:$config->resized_brands_dir|webp}">
                                            {/if}
                                            <source data-srcset="{$b->image|resize:100:50:false:$config->resized_brands_dir}">
                                            <img class="main_brands_img lazy" data-src="{$b->image|resize:100:50:false:$config->resized_brands_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="{$b->name|escape}"/>
                                        </picture>
                                    {else}
                                        <span class="vs-brands__name">{$b->name|escape}</span>
                                    {/if}
                                </a>
                            </li>
                        {/foreach}
                    </ul>
                </div>
            {/if}
        </div>
    </section>
{/if}

{* Shape 6: article cards - a different card entirely, not a sixth product row. *}
{get_posts var=last_posts limit=4 category_id=1}
{if $last_posts}
    <section class="vs-home__section container">
        <div class="vs-home__head">
            <h2 class="vs-home__title">
                <span data-language="main_news">{$lang->main_news}</span>
            </h2>
            {if !empty($blog_categories[1])}
                <a class="vs-home__more" href="{url_generator route='blog_category' url=$blog_categories[1]->url}">
                    <span data-language="main_all_news">{$lang->main_all_news}</span>{include file="svg.tpl" svgId="arrow_right2"}
                </a>
            {/if}
        </div>
        <div class="fn_articles_slide vs-posts">
            {foreach $last_posts as $post}
                {include 'post_list.tpl'}
            {/foreach}
        </div>
    </section>
{/if}
