<!-- The main page template -->

{* Featured products *}
{get_featured_products var=featured_products limit=4}
{if $featured_products}
    <div class="main-products main-products__featured container">
        <div class="block block--border">
            <div class="block__header block__header--promo">
                <div class="block__title">
                    <span data-language="main_recommended_products">{$lang->main_recommended_products}</span>
                </div>
                <div class="block__header_button">
                    <a class="block__more d-flex align-items-center" href="{url_generator route='products' filtersUrl=['filter' => ['featured']]}">
                        <span data-language="main_look_all">{$lang->main_look_all}</span>{include file="svg.tpl" svgId="arrow_right2"}
                    </a>
                </div>
            </div>
            <div class="block__body">
                <div class="fn_products_slide products_list row no_gutters swiper-container">
                    <div class="swiper-wrapper">
                        {foreach $featured_products as $product}
                            <div class="item product_item swiper-slide no_hover">{include "product_list.tpl"}</div>
                        {/foreach}
                    </div> 
                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </div>
    </div>
{/if}

{* New products *}
{get_new_products var=new_products limit=4}
{if $new_products}
    <div class="main-products main-products__new container">
        <div class="block block--border">
            <div class="block__header">
                <div class="block__title">
                    <span data-language="main_new_products">{$lang->main_new_products}</span>
                </div>
            </div>
            <div class="block__body">
                <div class="fn_products_slide products_list row no_gutters swiper-container">
                    <div class="swiper-wrapper">
                        {foreach $new_products as $product}
                            <div class="product_item swiper-slide no_hover">{include "product_list.tpl"}</div>
                        {/foreach}
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
         </div>
    </div>
{/if}

{* Discount products *}
{get_discounted_products var=discounted_products limit=4}
{if $discounted_products}
    <div class="main-products main-products__sale container">
        <div class="block block--border">
            <div class="block__header block__header--promo">
                <div class="block__title">
                    <span data-language="main_discount_products">{$lang->main_discount_products}</span>
                </div>
                <div class="block__header_button">
                    <a class="block__more d-flex align-items-center" href="{url_generator route='products' filtersUrl=['filter' => ['discounted']]}">
                        <span data-language="main_look_all">{$lang->main_look_all} </span>{include file="svg.tpl" svgId="arrow_right2"}
                    </a>
                </div>
            </div>
            <div class="block__body">
                <div class="fn_products_slide products_list row no_gutters swiper-container">
                    <div class="swiper-wrapper">
                        {foreach $discounted_products as $product}
                            <div class="product_item swiper-slide no_hover">{include "product_list.tpl"}</div>
                        {/foreach}
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </div>
    </div>
{/if}

{if $description}
    <div class="container section_about_&_brands">
        <div class="block block--boxed block--border">
            <div class="f_row">
                {if $description}
                    <div class="d-lg-flex align-items-lg-stretch">
                        <div class="block__abouts_us">
                            <div class="block__header">
                                <h1 class="block__title"><span>{$h1|escape}</span></h1>
                            </div>
                            <div class="block__body">
                                <div class="fn_readmore">
                                    <div class="block__description">{$description}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}

{* Last_posts *}
{get_posts var=last_posts limit=4 category_id=1}
{if $last_posts}
    <div class="main-articles container">
        <div class="block block--boxed block--border">
            <div class="block__header block__header--promo">
                <div class="block__title">
                    <span data-language="main_news">{$lang->main_news}</span>
                </div>
                <div class="block__header_button">
                    <a class="block__more d-flex align-items-center" href="{url_generator route='blog_category' url=$blog_categories[1]->url}">
                        <span data-language="main_all_news">{$lang->main_all_news} </span>{include file="svg.tpl" svgId="arrow_right2"}
                    </a>
                </div>
            </div>
            <div class="block__body">
                <div class="fn_articles_slide article_list f_row no_gutters">
                    {foreach $last_posts as $post}
                        <div class="article_item no_hover f_col-sm-6 f_col-lg-3">{include 'post_list.tpl'}</div>
                    {/foreach}
                </div>
            </div>   
        </div>
    </div>
{/if}