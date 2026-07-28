{* Product card. fn_* hooks and every <option> data-* are a JavaScript contract (okay.js) *}
{* The card carries NO visible variant control. Choosing a variant is the product
   page's job - the catalogue's job is to get the shopper there. The <select> below
   stays in the DOM because it is the value this form posts and because okay.js's
   .fn_variant handler is what recalculates price, old price, SKU and stock; it is
   simply never shown and never handed to select2. Consequence, accepted
   deliberately: "В кошик" from a card adds the FIRST variant of a multi-variant
   product. *}
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
                            <img class="fn_img lazy" data-src="{$product->image->filename|resize:600:800}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="{$product->name|escape}" title="{$product->name|escape}"/>
                        {else}
                            {if $settings->support_webp}
                                <source type="image/webp" data-srcset="{$product->image->filename|resize:180:150|webp}" media="(max-width: 440px)" >
                                <source type="image/webp" data-srcset="{$product->image->filename|resize:300:150|webp}" >
                            {/if}
                            <source data-srcset="{$product->image->filename|resize:180:150}" media="(max-width: 440px)">
                            <source data-srcset="{$product->image->filename|resize:300:150}">

                            <img class="fn_img lazy" data-src="{$product->image->filename|resize:300:150}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="{$product->name|escape}" title="{$product->name|escape}"/>
                        {/if}
                    </picture>
                {else}
                    <span class="fn_img vs-card__no_image" title="{$product->name|escape}">
                        {include file="svg.tpl" svgId="no_image"}
                    </span>
                {/if}
            </a>

            {* The short annotation is a hover reveal over the foot of the plate, not a
               line in the card body: in the body it cost every card two permanent lines
               of small grey type for a fragment of a sentence, and revealing it in flow
               would resize the whole grid row under the pointer. It is aria-hidden and
               display:none under (hover: none) on purpose - it is a truncated copy of
               text the product page states in full, so nothing lives only here.
               strip_tags leaves entities behind ("Цвет:&nbsp;Белый"), so escape must not
               double-encode them - it still escapes a bare < or & *}
            {if $product->annotation && $controller != "MainController"}
                <div class="vs-card__annotation" aria-hidden="true">{$product->annotation|strip_tags|escape:'html':'UTF-8':false}</div>
            {/if}
        </div>

        {* Badges. The discount badge is always in the DOM: okay.js reveals it by
           removing hidden-xs-up when a discounted variant is chosen.

           Badges and tools sit here, a level above .vs-card__media, on purpose.
           Inside the plate they were clipped by its 12px radius and overflow,
           which is why each carried its own inset (8px and 4px) - two arbitrary
           offsets that put them out of line with the title, price and buttons
           below. As children of .vs-card__inner they pin to inset 0 and share
           the card's single padding step with everything else. The annotation
           stays inside the plate: it slides up from the foot and needs the clip. *}
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

            {* "Add to comparison" is switched off on the card by the shop owner's
               decision. Delete the comment wrapper below to bring it back - nothing
               else has to change: the control still exists on the product page, the
               header informer still counts, and the comparison page still works.
               fn_comparison and vs-card__compare are the hooks okay.js and
               components.css bind to, so keep them intact. *}
            {*
            {if $controller != "ComparisonController"}
                {if is_array($comparison->ids) && in_array($product->id, $comparison->ids)}
                    <a class="fn_comparison vs-btn vs-btn--icon vs-card__tool vs-card__compare selected" href="#" data-id="{$product->id}" title="{$lang->remove_comparison}" data-result-text="{$lang->add_comparison}">{include file="svg.tpl" svgId="compare"}</a>
                {else}
                    <a class="fn_comparison vs-btn vs-btn--icon vs-card__tool vs-card__compare" href="#" data-id="{$product->id}" title="{$lang->add_comparison}" data-result-text="{$lang->remove_comparison}">{include file="svg.tpl" svgId="compare"}</a>
                {/if}
            {/if}
            *}

            {* The comparison page draws the same card, and this is the only way to
               drop a product from it - so that branch stays live whatever happens
               above. *}
            {if $controller == "ComparisonController"}
                <a class="fn_comparison vs-btn vs-btn--icon vs-card__tool vs-card__compare selected" href="#" data-id="{$product->id}" title="{$lang->remove_comparison}">{include file="svg.tpl" svgId="close"}</a>
            {/if}
        </div>

        <div class="vs-card__body">
            <a class="vs-card__name" data-product="{$product->id}" href="{url_generator route="product" url=$product->url}">{$product->name|escape}</a>

            <div class="vs-card__sku{if !$product->variant->sku} hidden-xs-up{/if}">
                <span data-language="product_sku">{$lang->product_sku}:</span>
                <span class="fn_sku">{$product->variant->sku|escape}</span>
            </div>

            <div class="vs-card__price">
                <span class="vs-card__price_current{if $product->variant->compare_price} price--red{/if}"><span class="fn_price">{$product->variant->price|convert}</span>&nbsp;<span class="vs-card__currency">{$currency->sign|escape}</span></span>
                <span class="vs-card__price_old{if !$product->variant->compare_price} hidden-xs-up{/if}"><span class="fn_old_price">{$product->variant->compare_price|convert}</span>&nbsp;<span class="vs-card__currency">{$currency->sign|escape}</span></span>
            </div>

            {* Availability, stated in all three states - out of stock included, the
               same way the product page states it. This element carries NO fn_ class,
               so okay.js never selects it and hidden-xs-up on it would be ours alone;
               it is not written here at all, which is why the muted out-of-stock line
               is reachable. okay.js keeps toggling hidden-xs-up on the fn_ elements in
               the actions row below, untouched.
               vibe.js rewrites the class and the label from these data-* strings when
               a variant changes, so the copy stays localised. *}
            <p class="vs-stock{if $product->variant->stock < 1} vs-stock--out{elseif $product->variant->stock <= 5} vs-stock--low{else} vs-stock--in{/if}" data-low-at="5" data-in="{$lang->product_in_stock|escape}" data-low="{$lang->product_low_stock|escape}" data-out="{$lang->out_of_stock|escape}">
                <span class="vs-stock__label">{if $product->variant->stock < 1}{$lang->out_of_stock}{elseif $product->variant->stock <= 5}{$lang->product_low_stock}{else}{$lang->product_in_stock}{/if}</span>
            </p>

            <form class="fn_variants vs-card__form" action="{url_generator route="cart"}">
                {* Value carrier only: always hidden, never given fn_select2. okay.js
                   reads it on submit with $(this).find("select[name=variant]").val(),
                   which does not care that the element is display:none. *}
                <div class="vs-card__variants hidden">
                    <select name="variant" class="fn_variant">
                        {foreach $product->variants as $v}
                            <option value="{$v->id}" data-price="{$v->price|convert}" data-stock="{$v->stock}"{if $v->compare_price > 0} data-cprice="{$v->compare_price|convert}"{if $v->compare_price>$v->price && $v->price>0} data-discount="{round((($v->price-$v->compare_price)/$v->compare_price)*100, 2)}&nbsp;%"{/if}{/if}{if $v->sku} data-sku="{$v->sku|escape}"{/if}>{if $v->name}{$v->name|escape}{else}{$product->name|escape}{/if}</option>
                        {/foreach}
                    </select>
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
