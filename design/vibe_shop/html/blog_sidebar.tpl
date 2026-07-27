<!-- Blog sidebar page -->

{* Blog category tree. Same primitives as the catalogue rail: a fn_switch head
   with its body as the immediately following sibling (okay.js collapses with
   $(this).next().slideToggle(), so the two must stay adjacent), and the
   category rows are Task 4's .vs-filter-cats. *}
<div class="vs-filter-group">
    <button type="button" class="fn_switch vs-filter-group__head" aria-expanded="true">
        <span data-language="blog_catalog">{$lang->blog_catalog}</span>
        <span class="vs-filter-group__chevron">{include file="svg.tpl" svgId="chevron"}</span>
    </button>
    <div class="vs-filter-group__body">
        <nav class="blog_catalog">
            {function name=categories_article}
                {if $categories}
                    <div class="vs-filter-cats blog_catalog__list level_{$level}{if $level > 1} vs-filter-cats--sub{/if}">
                        {foreach $categories as $c}
                            {if $c->visible && ($c->has_posts || $settings->show_empty_categories)}
                                <div class="vs-filter-cats__item blog_catalog__item{if $c->subcategories && $c->count_children_visible && $level < 3} parent{/if}">
                                    <a class="vs-filter-cats__link blog_catalog__link{if $category->id == $c->id} is-current selected{/if}" href="{url_generator route='blog_category' url=$c->url}" data-blog_category="{$c->id}">
                                        <span class="vs-filter-cats__icon">
                                            {if $c->image}
                                                <picture>
                                                    {if $settings->support_webp}
                                                        <source type="image/webp" data-srcset="{$c->image|resize:20:20:false:$config->resized_blog_categories_dir|webp}">
                                                    {/if}
                                                    <source data-srcset="{$c->image|resize:20:20:false:$config->resized_blog_categories_dir}">
                                                    <img class="lazy" data-src="{$c->image|resize:20:20:false:$config->resized_blog_categories_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="" title="{$c->name|escape}"/>
                                                </picture>
                                            {else}
                                                {include file="svg.tpl" svgId="description_icon"}
                                            {/if}
                                        </span>
                                        <span class="vs-filter-cats__name blog_catalog__name">{$c->name|escape}</span>
                                        {if $category->id != $c->id}
                                            {include file="svg.tpl" svgId="arrow_right2"}
                                        {/if}
                                    </a>
                                    {if $c->subcategories && $c->count_children_visible && $level < 3}
                                        {categories_article categories=$c->subcategories level=$level + 1}
                                    {/if}
                                </div>
                            {/if}
                        {/foreach}
                    </div>
                {/if}
            {/function}
            {categories_article categories=$blog_categories level=1}
        </nav>
    </div>
</div>

{* Subscribing *}
<div class="vs-aside__block">
    <p class="vs-aside__promo">
        <span data-language="subscribe_promotext">{$lang->subscribe_promotext}</span>
    </p>
    <form class="fn_subscribe_form_blog fn_validate_subscribe_blog vs-subscribe" method="post">
        <div class="vs-subscribe__row">
            <input type="hidden" name="subscribe" value="1"/>
            <input class="vs-field vs-subscribe__input" aria-label="{$lang->form_email|escape}" type="email" name="subscribe_email" value="" data-format="email" placeholder="{$lang->form_email}"/>
            <button class="vs-btn vs-btn--primary" type="submit"><span data-language="subscribe_button">{$lang->subscribe_button}</span></button>
        </div>
        <div class="fn_subscribe_success_blog vs-note vs-note--ok hidden">
            <span data-language="subscribe_sent">{$lang->index_subscribe_sent}</span>
        </div>
        <div class="fn_subscribe_error_blog vs-note vs-note--error hidden">
            <span class="fn_error_text_blog"></span>
        </div>
    </form>
</div>

{if $controller != "AuthorsController" && !$post}
    {* Featured products *}
    {get_featured_products var=featured_products limit=3}
    {if $featured_products}
        <div class="vs-filter-group">
            <button type="button" class="fn_switch vs-filter-group__head" aria-expanded="true">
                <span data-language="main_recommended_products">{$lang->main_recommended_products}</span>
                <span class="vs-filter-group__chevron">{include file="svg.tpl" svgId="chevron"}</span>
            </button>
            <div class="vs-filter-group__body">
                <div class="vs-minicards">
                    {foreach $featured_products as $product}
                        <a class="vs-minicard" href="{url_generator route='product' url=$product->url}">
                            <span class="vs-minicard__media">
                                {if $product->image->filename}
                                    <picture>
                                        {if $settings->support_webp}
                                            <source type="image/webp" data-srcset="{$product->image->filename|resize:60:60|webp}">
                                        {/if}
                                        <source data-srcset="{$product->image->filename|resize:60:60}">
                                        <img class="lazy" data-src="{$product->image->filename|resize:60:60}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt=""/>
                                    </picture>
                                {else}
                                    {include file="svg.tpl" svgId="no_image"}
                                {/if}
                            </span>
                            <span class="vs-minicard__body">
                                <span class="vs-minicard__name">{$product->name|escape}</span>
                                <span class="vs-minicard__prices">
                                    <span class="old_price vs-minicard__old vs-tabular{if !$product->variant->compare_price} hidden-xs-up{/if}">
                                        <span class="fn_old_price">{$product->variant->compare_price|convert}</span>
                                    </span>
                                    <span class="price vs-minicard__price vs-tabular{if $product->variant->compare_price} price--red{/if}">
                                        <span class="fn_price">{$product->variant->price|convert}</span> <span class="currency">{$currency->sign|escape}</span>
                                    </span>
                                </span>
                            </span>
                        </a>
                    {/foreach}
                </div>
                <a class="vs-aside__more" href="{url_generator route='products' filtersUrl=['filter' => ['featured']]}">
                    <span data-language="main_look_all">{$lang->main_look_all}</span>{include file="svg.tpl" svgId="arrow_right2"}
                </a>
            </div>
        </div>
    {/if}
{/if}
