{* Product card. fn_* hooks and every <option> data-* are a JavaScript contract (okay.js) *}
{* Chips are only worth it for short labels - "Червоний", "XL", "128 Gb". A shop
   whose variants read "16 дюймов - 155-166 см" would get four stacked 44px rows
   and a card twice as tall as its neighbours, so those fall back to the dropdown. *}
{* $product->variants is NULL, not [], for a product with no variant: attachVariants
   only ever assigns the key, it never initialises it. Counting NULL is a fatal in
   PHP 8, and this card is rendered for every product in the catalogue, on the home
   page swipers and in the wishlist - one variant-less product takes the whole
   listing down with a 500. (The related-products block on the product page cannot
   reach it: RelatedProductsHelper hard-codes in_stock => true, which requires at
   least one variant. The catalogue does not.) *}
{assign var="vsVariantCount" value=0}
{if !empty($product->variants)}{assign var="vsVariantCount" value=$product->variants|count}{/if}
{assign var="vsChipLen" value=0}
{assign var="vsChipNamed" value=true}
{foreach $product->variants as $v}
    {if !$v->name}{assign var="vsChipNamed" value=false}
    {elseif $v->name|count_characters > $vsChipLen}{assign var="vsChipLen" value=$v->name|count_characters}{/if}
{/foreach}
{assign var="vsChips" value=($vsVariantCount > 1 && $vsVariantCount <= 4 && $vsChipNamed && $vsChipLen <= 12)}
<article class="vs-card fn_product">
    <div class="fn_transfer vs-card__inner">
        <div class="vs-card__media">
            <a class="vs-card__media-link" aria-label="{$product->name|escape}" href="{if $controller=='Comparison'}{$product->image->filename|resize:800:600:w}{else}{url_generator route='product' url=$product->url}{/if}" {if $controller=='Comparison'}data-fancybox="group" data-caption="{$product->name|escape}"{/if}>
                {if $product->image->filename}
                    <picture>
                        {if $settings->increased_image_size}
                            {if $settings->support_webp}
                                <source type="image/webp" data-srcset="{$product->image->filename|resize:600:800|webp}" >
                            {/if}
                            <source data-srcset="{$product->image->filename|resize:600:800}">
                            <img class="fn_img lazy" data-src="{$product->image->filename|resize:600:800}" src="{$rootUrl}/design/{get_theme}/images/xloading.gif" alt="{$product->name|escape}" title="{$product->name|escape}"/>
                        {else}
                            {if $settings->support_webp}
                                <source type="image/webp" data-srcset="{$product->image->filename|resize:180:150|webp}" media="(max-width: 440px)" >
                                <source type="image/webp" data-srcset="{$product->image->filename|resize:300:150|webp}" >
                            {/if}
                            <source data-srcset="{$product->image->filename|resize:180:150}" media="(max-width: 440px)">
                            <source data-srcset="{$product->image->filename|resize:300:150}">

                            <img class="fn_img lazy" data-src="{$product->image->filename|resize:300:150}" src="{$rootUrl}/design/{get_theme}/images/xloading.gif" alt="{$product->name|escape}" title="{$product->name|escape}"/>
                        {/if}
                    </picture>
                {else}
                    <span class="fn_img vs-card__no_image" title="{$product->name|escape}">
                        {include file="svg.tpl" svgId="no_image"}
                    </span>
                {/if}
            </a>

            {* Badges. The discount badge is always in the DOM: okay.js reveals it by
               removing hidden-xs-up when a discounted variant is chosen. *}
            <div class="vs-card__badges">
                {if $product->featured}
                    <span class="vs-badge vs-badge--hit" data-language="product_sticker_hit">{$lang->product_sticker_hit}</span>
                {/if}
                <span class="fn_discount_label vs-badge vs-badge--sale{if !($product->variant->price>0 && $product->variant->compare_price>0 && $product->variant->compare_price>$product->variant->price)} hidden-xs-up{/if}">{if $product->variant->price>0 && $product->variant->compare_price>0 && $product->variant->compare_price>$product->variant->price}{round((($product->variant->price-$product->variant->compare_price)/$product->variant->compare_price)*100)}&nbsp;%{/if}</span>
                {if $product->special}
                    <span class="vs-badge vs-badge--special">
                        <img src='files/special/{$product->special}' alt='{$product->special|escape}' title="{$product->special|escape}"/>
                    </span>
                {/if}
            </div>

            {* Wishlist and comparison. Revealed on hover, permanently visible under
               (hover: none) - see components.css *}
            <div class="vs-card__tools">
                {if $controller != "WishListController"}
                    {if is_array($wishlist->ids) && in_array($product->id, $wishlist->ids)}
                        <a href="#" data-id="{$product->id}" class="fn_wishlist vs-btn vs-btn--icon vs-card__tool vs-card__wish selected" title="{$lang->remove_favorite}" data-result-text="{$lang->add_favorite}">
                            <span class="vs-card__wish_off">{include file="svg.tpl" svgId="heart"}</span>
                            <span class="vs-card__wish_on">{include file="svg.tpl" svgId="heart_filled"}</span>
                        </a>
                    {else}
                        <a href="#" data-id="{$product->id}" class="fn_wishlist vs-btn vs-btn--icon vs-card__tool vs-card__wish" title="{$lang->add_favorite}" data-result-text="{$lang->remove_favorite}">
                            <span class="vs-card__wish_off">{include file="svg.tpl" svgId="heart"}</span>
                            <span class="vs-card__wish_on">{include file="svg.tpl" svgId="heart_filled"}</span>
                        </a>
                    {/if}
                {else}
                    <a href="#" data-id="{$product->id}" class="fn_wishlist vs-btn vs-btn--icon vs-card__tool vs-card__wish vs-card__wish--remove selected" title="{$lang->remove_favorite}">
                        {include file="svg.tpl" svgId="close"}
                    </a>
                {/if}

                {if $controller != "ComparisonController"}
                    {if is_array($comparison->ids) && in_array($product->id, $comparison->ids)}
                        <a class="fn_comparison vs-btn vs-btn--icon vs-card__tool vs-card__compare selected" href="#" data-id="{$product->id}" title="{$lang->remove_comparison}" data-result-text="{$lang->add_comparison}">{include file="svg.tpl" svgId="compare"}</a>
                    {else}
                        <a class="fn_comparison vs-btn vs-btn--icon vs-card__tool vs-card__compare" href="#" data-id="{$product->id}" title="{$lang->add_comparison}" data-result-text="{$lang->remove_comparison}">{include file="svg.tpl" svgId="compare"}</a>
                    {/if}
                {else}
                    <a class="fn_comparison vs-btn vs-btn--icon vs-card__tool vs-card__compare selected" href="#" data-id="{$product->id}" title="{$lang->remove_comparison}">{include file="svg.tpl" svgId="close"}</a>
                {/if}
            </div>
        </div>

        <div class="vs-card__body">
            <a class="vs-card__name" data-product="{$product->id}" href="{url_generator route="product" url=$product->url}">{$product->name|escape}</a>

            <div class="vs-card__sku{if !$product->variant->sku} hidden-xs-up{/if}">
                <span data-language="product_sku">{$lang->product_sku}:</span>
                <span class="fn_sku">{$product->variant->sku|escape}</span>
            </div>

            {if $product->annotation && $controller != "MainController"}
                {* strip_tags leaves entities behind ("Цвет:&nbsp;Белый"), so escape must not
                   double-encode them - it still escapes a bare < or & *}
                <div class="vs-card__annotation">{$product->annotation|strip_tags|escape:'html':'UTF-8':false}</div>
            {/if}

            <div class="vs-card__price">
                <span class="vs-card__price_current{if $product->variant->compare_price} price--red{/if}"><span class="fn_price">{$product->variant->price|convert}</span>&nbsp;<span class="vs-card__currency">{$currency->sign|escape}</span></span>
                <span class="vs-card__price_old{if !$product->variant->compare_price} hidden-xs-up{/if}"><span class="fn_old_price">{$product->variant->compare_price|convert}</span>&nbsp;<span class="vs-card__currency">{$currency->sign|escape}</span></span>
            </div>

            {* Availability. vibe.js rewrites the class and the label from these
               data-* strings when a variant changes, so the copy stays localised. *}
            <p class="vs-stock{if $product->variant->stock < 1} vs-stock--out hidden-xs-up{elseif $product->variant->stock <= 5} vs-stock--low{else} vs-stock--in{/if}" data-low-at="5" data-in="{$lang->product_in_stock|escape}" data-low="{$lang->product_low_stock|escape}" data-out="{$lang->out_of_stock|escape}">
                <span class="vs-stock__label">{if $product->variant->stock < 1}{$lang->out_of_stock}{elseif $product->variant->stock <= 5}{$lang->product_low_stock}{else}{$lang->product_in_stock}{/if}</span>
            </p>

            <form class="fn_variants vs-card__form" action="{url_generator route="cart"}">
                {* Variants: chips up to four, select2 beyond. The <select> stays in the
                   DOM either way - it is the value the form submits. *}
                {if $vsChips}
                    <div class="vs-chips" role="group" aria-label="{$lang->product_variant|escape}">
                        {foreach $product->variants as $v}
                            <button type="button" class="vs-chip{if $v@first} vs-chip--selected{/if}" aria-pressed="{if $v@first}true{else}false{/if}" data-variant-id="{$v->id}">{$v->name|escape}</button>
                        {/foreach}
                    </div>
                {/if}
                <div class="vs-card__variants{if $vsChips || $vsVariantCount <= 1} hidden{/if}">
                    <select name="variant" class="fn_variant vs-card__select{if $vsVariantCount > 1 && !$vsChips} fn_select2{/if}">
                        {foreach $product->variants as $v}
                            <option value="{$v->id}" data-price="{$v->price|convert}" data-stock="{$v->stock}"{if $v->compare_price > 0} data-cprice="{$v->compare_price|convert}"{if $v->compare_price>$v->price && $v->price>0} data-discount="{round((($v->price-$v->compare_price)/$v->compare_price)*100, 2)}&nbsp;%"{/if}{/if}{if $v->sku} data-sku="{$v->sku|escape}"{/if}>{if $v->name}{$v->name|escape}{else}{$product->name|escape}{/if}</option>
                        {/foreach}
                    </select>
                    <div class="dropDownSelect2"></div>
                </div>

                <div class="vs-card__actions">
                    {if !$settings->is_preorder}
                        {* Out of stock *}
                        <p class="fn_not_preorder vs-card__unavailable{if $product->variant->stock > 0} hidden-xs-up{/if}">
                            <span data-language="out_of_stock">{$lang->out_of_stock}</span>
                        </p>
                    {else}
                        {* Pre-order *}
                        <button class="fn_is_preorder vs-btn vs-btn--secondary vs-card__cta{if $product->variant->stock > 0} hidden-xs-up{/if}" type="submit" data-language="pre_order">{$lang->pre_order}</button>
                    {/if}

                    {* Submit cart button *}
                    <button class="fn_is_stock vs-btn vs-btn--primary vs-card__cta{if $product->variant->stock < 1} hidden-xs-up{/if}" type="submit" data-language="add_to_cart">{$lang->add_to_cart}</button>

                    {fast_order_btn product=$product}
                </div>
            </form>
        </div>
    </div>
</article>
