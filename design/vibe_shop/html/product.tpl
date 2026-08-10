{* Product page.

   Contracts that must not be touched (C5/C6):
   - every fn_* class, the <option> data-price/data-stock/data-cprice/
     data-discount/data-sku/data-units attributes, name="variant",
     name="amount", data-id, data-result-text and the hidden / hidden-xs-up
     classes okay.js toggles for the stock and pre-order states.
   - okay.js resolves everything it rewrites with
     selected.closest(".fn_product").find(...), so every element it updates -
     including the second price in the sticky bar - has to stay inside
     .fn_product.

   Two mechanisms are deliberately dropped from THIS template:
   - .tabs / .tabs__navigation. okay.js's tab script writes an inline
     display:none on every panel it is not showing, at every width, and an
     inline style can only be beaten with !important - which this branch bans.
     The panels are driven by fn_accordion instead, which is already an
     exclusive disclosure: one panel open, clicking the open one is a no-op.
     Desktop lays the same markup out as a tab set with CSS grid, mobile as a
     stacked accordion.
     Every panel ships OPEN: nothing on this page may depend on a script having
     run to be readable. vibe.js collapses all but one and marks the block
     .is-enhanced, which is also what unlocks the desktop tab grid - three
     panels sharing one grid cell only works while one of them is displayed.
     vibe.js also owns .fn_anchor_comments: okay.js triggers a click on
     #fn_tab_comments and then measures #comments in the same tick, before
     slideDown has made it visible, so its scroll destination is always -110.
   - .fn_switch / .mobile_tab_navigation, the second (mobile-only) disclosure
     the old markup layered on top of the tabs. One mechanism, both widths.

   Chips are a wider bet here than on the card: the buy box is 380px and this
   is the page where the variant choice is actually made, so up to six named
   variants of up to twenty characters get one-tap chips. Anything longer or
   more numerous stays on the select2 dropdown. *}
{* $product->images and $product->variants are NULL, not [], when the product has
   none - counting NULL is a fatal in PHP 8, so both are counted defensively. *}
{assign var="vsVariantCount" value=0}
{if !empty($product->variants)}{assign var="vsVariantCount" value=$product->variants|count}{/if}
{assign var="vsImageCount" value=0}
{if !empty($product->images)}{assign var="vsImageCount" value=$product->images|count}{/if}
{assign var="vsCommentCount" value=0}
{if !empty($comments)}{assign var="vsCommentCount" value=$comments|count}{/if}
{assign var="vsChipLen" value=0}
{assign var="vsChipNamed" value=true}
{foreach $product->variants as $v}
    {if !$v->name}{assign var="vsChipNamed" value=false}
    {elseif $v->name|count_characters > $vsChipLen}{assign var="vsChipLen" value=$v->name|count_characters}{/if}
{/foreach}
{* `le`, not `<=`: inside an {assign} a `<` reads as the start of an HTML tag to
   Okay\Core\TplMod, which then truncates the template from here on. Only an opening
   {if} survives a `<`, which is why the tests further down keep theirs. *}
{assign var="vsChips" value=($vsVariantCount > 1 && $vsVariantCount le 6 && $vsChipNamed && $vsChipLen le 20)}
{assign var="vsLowAt" value=5}
{assign var="vsHasDiscount" value=($product->variant->price > 0 && $product->variant->compare_price > 0 && $product->variant->compare_price > $product->variant->price)}

<div class="fn_product vs-pdp" itemscope itemtype="http://schema.org/Product">
    {* Identity: name, article, rating, brand. Everything that answers "is this
       the right product" - the buy box below carries only the transaction. *}
    <div class="vs-pdp__masthead">
        <h1 class="vs-pdp__title">
            <span data-product="{$product->id}" itemprop="name">{$h1|escape}</span>
        </h1>

        <div class="vs-pdp__meta">
            <span class="vs-pdp__sku{if !$product->variant->sku} hidden-xs-up{/if}">
                <span data-language="product_sku">{$lang->product_sku}:</span>
                <span class="fn_sku"{if $product->variant->sku} itemprop="sku"{/if}>{$product->variant->sku|escape}</span>
            </span>

            {* Rating. The widget is a 90x18 sprite strip driven by scripts.tpl's
               rater: the inline width IS the value, so it stays inline. *}
            <span class="vs-pdp__rating">
                <span id="product_{$product->id}" class="product__rating fn_rating" data-rating_post_url="{url_generator route='ajax_product_rating'}"{if $product->rating > 0} itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating"{/if}>
                    <span class="rating_starOff">
                        <span class="rating_starOn" style="width:{$product->rating*90/5|string_format:'%.0f'}px;"></span>
                    </span>
                    {if $product->rating > 0}
                        <span class="rating_text">(<span itemprop="reviewCount">{$product->votes|string_format:"%.0f"}</span>)</span>
                        <span class="rating_text hidden">(<span itemprop="ratingValue">{$product->rating|string_format:"%.1f"}</span>)</span>
                        <span class="rating_text hidden" itemprop="bestRating">5</span>
                    {else}
                        <span class="rating_text hidden">({$product->rating|string_format:"%.1f"})</span>
                    {/if}
                </span>
            </span>

            <a href="#comments" class="fn_anchor_comments vs-pdp__reviews">
                {if $vsCommentCount}
                    <span>{$vsCommentCount} {$vsCommentCount|plural:$lang->product_anchor_comment_plural1:$lang->product_anchor_comment_plural2:$lang->product_anchor_comment_plural3}</span>
                {else}
                    <span data-language="product_anchor_comment">{$lang->product_anchor_comment}</span>
                {/if}
            </a>

            {if !empty($brand)}
                <a class="vs-pdp__brand" href="{url_generator route="brand" url=$brand->url}">
                    {if !empty($brand->image)}
                        <img class="vs-pdp__brand_img" src="{$brand->image|resize:200:64:false:$config->resized_brands_dir}" alt="{$brand->name|escape}" title="{$brand->name|escape}">
                    {else}
                        <span data-language="product_brand_name">{$lang->product_brand_name}</span>
                        <span>{$brand->name|escape}</span>
                    {/if}
                    <span class="hidden" itemprop="brand" itemtype="https://schema.org/Brand" itemscope>
                        <meta itemprop="name" content="{$brand->name|escape}" />
                    </span>
                </a>
            {/if}
        </div>
    </div>

    <div class="fn_transfer vs-pdp__layout">
        {* Gallery. gallery-top / gallery-thumbs / swiper-* are Swiper's binding
           classes from okay.js and cannot be renamed. The thumb strip is
           vertical because okay.js configures it that way, so it is a column
           beside the stage on desktop and hidden below 768px, where the stage
           is swipeable and the arrows say there is more than one image. *}
        <div class="vs-pdp__gallery">
            <div class="vs-gallery__frame">
                {if $vsImageCount > 1}
                    <div class="swiper-container gallery-thumbs vs-gallery__thumbs hidden-sm-down">
                        <div class="swiper-wrapper">
                            {foreach $product->images as $i=>$image}
                                <div class="swiper-slide vs-gallery__thumb">
                                    <picture>
                                        {if $settings->support_webp}
                                            <source type="image/webp" data-srcset="{$image->filename|resize:160:160|webp}">
                                        {/if}
                                        <source data-srcset="{$image->filename|resize:160:160}">
                                        <img class="lazy" data-src="{$image->filename|resize:160:160}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="{$product->name|escape}" title="{$product->name|escape}"/>
                                    </picture>
                                </div>
                            {/foreach}
                        </div>
                        {if $vsImageCount > 4}
                            <div class="swiper-scrollbar"></div>
                        {/if}
                    </div>
                {/if}

                <div class="vs-gallery__stage">
                    {if $vsImageCount}
                        <div class="swiper-container gallery-top vs-gallery__main">
                            <div class="swiper-wrapper">
                                {foreach $product->images as $i=>$image}
                                    <a href="{$image->filename|resize:1800:1800:w}" data-fancybox="we2" data-caption="{$product->name|escape}" class="swiper-slide vs-gallery__slide" aria-label="{$product->name|escape}">
                                        <picture>
                                            {if $settings->support_webp}
                                                <source type="image/webp" srcset="{$image->filename|resize:900:1100|webp}">
                                            {/if}
                                            <source srcset="{$image->filename|resize:900:1100}">
                                            <img{if $image@first} itemprop="image"{/if} src="{$image->filename|resize:900:1100}" alt="{$product->name|escape}" title="{$product->name|escape}"/>
                                        </picture>
                                    </a>
                                {/foreach}
                            </div>
                            {if $vsImageCount > 1}
                                <div class="swiper-button-next vs-gallery__nav"></div>
                                <div class="swiper-button-prev vs-gallery__nav"></div>
                            {/if}
                        </div>
                    {else}
                        <div class="vs-gallery__no_image" title="{$product->name|escape}">
                            {include file="svg.tpl" svgId="no_image"}
                        </div>
                    {/if}

                    {* Badges. Outside the image branch on purpose: fn_discount_label
                       has to be in the DOM even for a product with no photography,
                       because okay.js reveals it by removing hidden-xs-up when a
                       discounted variant is chosen. Rose means discount, nothing
                       else. *}
                    <div class="vs-gallery__badges">
                        {if $product->featured}
                            <span class="vs-badge vs-badge--hit" data-language="product_sticker_hit">{$lang->product_sticker_hit}</span>
                        {/if}
                        <span class="fn_discount_label vs-badge vs-badge--sale{if !$vsHasDiscount} hidden-xs-up{/if}">{if $vsHasDiscount}{round((($product->variant->price-$product->variant->compare_price)/$product->variant->compare_price)*100)}&nbsp;%{/if}</span>
                        {if $product->special}
                            <span class="vs-badge vs-badge--special">
                                <img src='files/special/{$product->special}' alt='{$product->special|escape}' title="{$product->special|escape}"/>
                            </span>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        {* Buy box. Sticky through the fold on desktop; on a phone it follows the
           gallery and the sticky bottom bar takes over once it scrolls away. *}
        <div class="vs-buybox">
            {* The sticky element is this wrapper, not .vs-buybox itself: the box
               is stretched to the full height of its grid area (rows 1-2) so the
               wrapper has the whole detail section to travel through. Sticking
               .vs-buybox directly would make it as tall as its own area, leaving
               it nothing to slide along, and the price would scroll away while
               the specification was still being read. *}
            <div class="vs-buybox__pin">
                {* POST and a token so the no-JS path is not CSRF-able. With JS,
                   okay.js intercepts the submit and builds its own ajax payload,
                   so neither attribute reaches that path. *}
                <form id="vs_buy_form" class="fn_variants vs-buybox__form" method="post" action="{url_generator route="cart"}">
                    <input type="hidden" name="customer_csrf_token" value="{$customer_csrf_token|escape}">
                    {* Price first, then the choice, then the action. The photograph
                       and the price are what the shopper came to check, and the
                       price is what the variant chooser then changes. *}
                    <div class="vs-buybox__offer" itemprop="offers" itemscope="" itemtype="http://schema.org/Offer">
                        <span class="hidden">
                            <link itemprop="url" href="{url_generator route="product" url=$product->url absolute=1}" />
                            <time itemprop="priceValidUntil" datetime="{$product->created|date:'Ymd'}"></time>
                            {if $product->variant->stock > 0}
                                <link itemprop="availability" href="https://schema.org/InStock" />
                            {else}
                                <link itemprop="availability" href="http://schema.org/OutOfStock" />
                            {/if}
                            <link itemprop="itemCondition" href="https://schema.org/NewCondition" />
                            <span itemprop="seller" itemscope itemtype="http://schema.org/Organization">
                                <span itemprop="name">{$settings->site_name}</span>
                            </span>
                        </span>

                        <div class="vs-buybox__prices">
                            <span class="vs-buybox__price{if $product->variant->compare_price} price--red{/if}">
                                <span class="fn_price" itemprop="price" content="{$product->variant->price|convert:null:false}">{$product->variant->price|convert}</span>&nbsp;<span class="vs-buybox__currency" itemprop="priceCurrency" content="{$currency->code|escape}">{$currency->sign|escape}</span>
                            </span>
                            <span class="vs-buybox__old{if !$product->variant->compare_price} hidden-xs-up{/if}">
                                <span class="fn_old_price">{$product->variant->compare_price|convert}</span>&nbsp;<span class="vs-buybox__currency">{$currency->sign|escape}</span>
                            </span>
                        </div>

                        {* Availability. okay.js swaps which of the two lines carries
                           hidden-xs-up; vibe.js only upgrades the in-stock line to
                           the amber "low stock" state, from data-* on the element so
                           the copy stays translated by the template. *}
                        <p class="fn_in_stock vs-stock{if $product->variant->stock > 0 && $product->variant->stock <= $vsLowAt} vs-stock--low{else} vs-stock--in{/if}{if $product->variant->stock < 1} hidden-xs-up{/if}" data-low-at="{$vsLowAt}" data-in="{$lang->product_in_stock|escape}" data-low="{$lang->product_low_stock|escape}">
                            <span class="vs-stock__label">{if $product->variant->stock > 0 && $product->variant->stock <= $vsLowAt}{$lang->product_low_stock}{else}{$lang->product_in_stock}{/if}</span>
                        </p>
                        <p class="fn_not_stock vs-stock vs-stock--out{if $product->variant->stock > 0} hidden-xs-up{/if}">
                            <span class="vs-stock__label" data-language="product_out_of_stock">{$lang->product_out_of_stock}</span>
                        </p>
                    </div>

                    {* Variants. The <select> is the value the form submits and stays
                       in the DOM either way; the chips are a shortcut vibe.js keeps
                       in sync with it, so okay.js still recalculates everything. *}
                    {if $vsVariantCount > 1}
                        <div class="vs-buybox__block">
                            <div class="vs-buybox__label" data-language="product_variant">{$lang->product_variant}</div>
                            {if $vsChips}
                                <div class="vs-chips" role="group" aria-label="{$lang->product_variant|escape}">
                                    {foreach $product->variants as $v}
                                        <button type="button" class="vs-chip{if $product->variant->id == $v->id} vs-chip--selected{/if}" aria-pressed="{if $product->variant->id == $v->id}true{else}false{/if}" data-variant-id="{$v->id}">{$v->name|escape}</button>
                                    {/foreach}
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <div class="vs-buybox__variants{if $vsChips || $vsVariantCount < 2} hidden{/if}">
                        <select name="variant" class="fn_variant vs-buybox__select{if $vsVariantCount > 1 && !$vsChips} fn_select2{/if}">
                            {foreach $product->variants as $v}
                                <option{if $product->variant->id == $v->id} selected{/if} value="{$v->id}" data-price="{$v->price|convert}" data-stock="{$v->stock}"{if $v->compare_price > 0} data-cprice="{$v->compare_price|convert}"{if $v->compare_price>$v->price && $v->price>0} data-discount="{round((($v->price-$v->compare_price)/$v->compare_price)*100, 2)}&nbsp;%"{/if}{/if}{if $v->sku} data-sku="{$v->sku|escape}"{/if}{if $v->units} data-units="{$v->units}"{/if}>{if $v->name}{$v->name|escape}{else}{$product->name|escape}{/if}</option>
                            {/foreach}
                        </select>
                        <div class="dropDownSelect2"></div>
                    </div>

                    <div class="vs-buybox__buy">
                        {* Quantity. The input is the form value carrier and is
                           never replaced - the buttons write to it and vibe.js
                           hands the arithmetic back to okay.js's amount_change,
                           which clamps to data-max and fires change. *}
                        <div class="fn_is_stock vs-buybox__qty{if $product->variant->stock < 1} hidden-xs-up{/if}">
                            <label class="vs-buybox__label" for="vs_amount">
                                <span data-language="product_quantity">{$lang->product_quantity}</span><span class="fn_units">{if $product->variant->units}, {$product->variant->units|escape}{/if}</span>
                            </label>
                            <div class="fn_product_amount vs-stepper">
                                <button type="button" class="vs-stepper__btn" data-vs-step="-1" aria-label="{$lang->product_quantity|escape} -1">&minus;</button>
                                <input id="vs_amount" class="amount__input vs-stepper__input" type="text" inputmode="numeric" name="amount" value="1" data-max="{$product->variant->stock}">
                                <button type="button" class="vs-stepper__btn" data-vs-step="1" aria-label="{$lang->product_quantity|escape} +1">&plus;</button>
                            </div>
                        </div>

                        {if !$settings->is_preorder}
                            <p class="fn_not_preorder vs-buybox__unavailable{if $product->variant->stock > 0} hidden-xs-up{/if}">
                                <span data-language="product_out_of_stock">{$lang->product_out_of_stock}</span>
                            </p>
                        {else}
                            <button class="fn_is_preorder vs-btn vs-btn--secondary vs-buybox__submit{if $product->variant->stock > 0} hidden-xs-up{/if}" type="submit" data-language="product_pre_order">{$lang->product_pre_order}</button>
                        {/if}

                        <button class="fn_is_stock vs-btn vs-btn--primary vs-buybox__submit{if $product->variant->stock < 1} hidden-xs-up{/if}" type="submit" data-language="product_add_cart">{$lang->product_add_cart}</button>
                    </div>

                    <div class="vs-buybox__aside">
                        {fast_order_btn product=$product}

                        {* Both tools carry a visible label beside the glyph, and it lives in the
                           SAME off/on pair the heart already used, so the words change with the
                           state on the click rather than only after a reload. okay.js swaps the
                           title attribute and data-result-text and never touches the element's
                           text, so a text node here is safe - and nothing in the frontend reads
                           data-language, checked before this was written, so no .text()-style
                           write can eat the glyph. The label hides itself on the narrowest
                           screens; see .vs-buybox__tool_label. *}
                        {if is_array($wishlist->ids) && in_array($product->id, $wishlist->ids)}
                            <a href="#" data-id="{$product->id}" class="fn_wishlist vs-btn vs-btn--icon vs-buybox__tool vs-buybox__wish selected" title="{$lang->product_remove_favorite}" data-result-text="{$lang->product_add_favorite}" data-language="product_remove_favorite">
                                <span class="vs-buybox__wish_off">{include file="svg.tpl" svgId="heart"}<span class="vs-buybox__tool_label">{$lang->product_add_favorite}</span></span>
                                <span class="vs-buybox__wish_on">{include file="svg.tpl" svgId="heart_filled"}<span class="vs-buybox__tool_label">{$lang->product_remove_favorite}</span></span>
                            </a>
                        {else}
                            <a href="#" data-id="{$product->id}" class="fn_wishlist vs-btn vs-btn--icon vs-buybox__tool vs-buybox__wish" title="{$lang->product_add_favorite}" data-result-text="{$lang->product_remove_favorite}" data-language="product_add_favorite">
                                <span class="vs-buybox__wish_off">{include file="svg.tpl" svgId="heart"}<span class="vs-buybox__tool_label">{$lang->product_add_favorite}</span></span>
                                <span class="vs-buybox__wish_on">{include file="svg.tpl" svgId="heart_filled"}<span class="vs-buybox__tool_label">{$lang->product_remove_favorite}</span></span>
                            </a>
                        {/if}

                        {if is_array($comparison->ids) && in_array($product->id, $comparison->ids)}
                            <a class="fn_comparison vs-btn vs-btn--icon vs-buybox__tool vs-buybox__compare selected" href="#" data-id="{$product->id}" title="{$lang->remove_comparison}" data-result-text="{$lang->product_add_comparison}" data-language="product_remove_comparison">
                                <span class="vs-buybox__compare_off">{include file="svg.tpl" svgId="compare"}<span class="vs-buybox__tool_label">{$lang->product_add_comparison}</span></span>
                                <span class="vs-buybox__compare_on">{include file="svg.tpl" svgId="compare"}<span class="vs-buybox__tool_label">{$lang->product_remove_comparison}</span></span>
                            </a>
                        {else}
                            <a class="fn_comparison vs-btn vs-btn--icon vs-buybox__tool vs-buybox__compare" href="#" data-id="{$product->id}" title="{$lang->product_add_comparison}" data-result-text="{$lang->remove_comparison}" data-language="product_add_comparison">
                                <span class="vs-buybox__compare_off">{include file="svg.tpl" svgId="compare"}<span class="vs-buybox__tool_label">{$lang->product_add_comparison}</span></span>
                                <span class="vs-buybox__compare_on">{include file="svg.tpl" svgId="compare"}<span class="vs-buybox__tool_label">{$lang->product_remove_comparison}</span></span>
                            </a>
                        {/if}
                    </div>
                </form>

                {* Delivery and payment. Real content, so the first one ships open. *}
                <div class="fn_accordion vs-disclosures">
                    <div class="accordion__item vs-disclosure-row visible">
                        <div class="accordion__title vs-disclosure-row__head active">
                            <button type="button" class="vs-disclosure-row__btn">
                                <span data-language="product_delivery">{$lang->product_delivery}</span>
                                <span class="vs-disclosure-row__chevron">{include file="svg.tpl" svgId="chevron"}</span>
                            </button>
                        </div>
                        <div id="vs_delivery" class="accordion__content vs-disclosure-row__body">
                            {$settings->product_deliveries}
                        </div>
                    </div>
                    <div class="accordion__item vs-disclosure-row">
                        <div class="accordion__title vs-disclosure-row__head">
                            <button type="button" class="vs-disclosure-row__btn">
                                <span data-language="product_payment">{$lang->product_payment}</span>
                                <span class="vs-disclosure-row__chevron">{include file="svg.tpl" svgId="chevron"}</span>
                            </button>
                        </div>
                        <div id="vs_payment" class="accordion__content vs-disclosure-row__body">
                            {$settings->product_payments}
                        </div>
                    </div>
                </div>

                {include file="share.tpl" url=$canonical title=$h1}
            </div>
        </div>

        {* Description, features and comments: one markup, driven by fn_accordion.
           A tab set from 992px, a stacked accordion below it - see components.css.
           It sits inside the two-column layout on purpose: on desktop it takes the
           gallery's column and the buy box, spanning both rows, stays with the
           shopper while they read the specification. *}
        {assign var="vsPanelFirst" value=true}
        <div id="fn_products_tab" class="fn_accordion vs-tabs">
            {if $description}
                <div class="accordion__item vs-tabs__item{if $vsPanelFirst} visible{/if}">
                    <h2 class="accordion__title vs-tabs__head{if $vsPanelFirst} active{/if}">
                        <button type="button" id="vs_tab_description" class="vs-tabs__btn">
                            <span data-language="product_description">{$lang->product_description}</span>
                            <span class="vs-tabs__chevron">{include file="svg.tpl" svgId="chevron"}</span>
                        </button>
                    </h2>
                    <div id="description" class="accordion__content vs-tabs__panel" itemprop="description">
                        <div class="block__description vs-prose">{$description}</div>
                    </div>
                </div>
                {assign var="vsPanelFirst" value=false}
            {/if}

            {if $product->features}
                <div class="accordion__item vs-tabs__item{if $vsPanelFirst} visible{/if}">
                    <h2 class="accordion__title vs-tabs__head{if $vsPanelFirst} active{/if}">
                        <button type="button" id="vs_tab_features" class="vs-tabs__btn">
                            <span data-language="product_features">{$lang->product_features}</span>
                            <span class="vs-tabs__chevron">{include file="svg.tpl" svgId="chevron"}</span>
                        </button>
                    </h2>
                    <div id="features" class="accordion__content vs-tabs__panel">
                        <dl class="vs-specs">
                            {foreach $product->features as $f}
                                <div class="vs-specs__row">
                                    <dt class="vs-specs__name">
                                        <span>{$f->name|escape}</span>{if $f->description}<span class="vs-specs__hint" title="{$f->description|escape}">i</span>{/if}
                                    </dt>
                                    <dd class="vs-specs__value">
                                        {foreach $f->values as $value}{if $category && $f->url_in_product && $f->in_filter && $value->to_index}<a href="{url_generator route="category" url=$category->url}{if !$settings->category_routes_template_slash_end}/{/if}{$f->url}-{$value->translit}">{$value->value|escape}</a>{else}{$value->value|escape}{/if}{if !$value@last}, {/if}{/foreach}
                                    </dd>
                                </div>
                            {/foreach}
                        </dl>
                    </div>
                </div>
                {assign var="vsPanelFirst" value=false}
            {/if}

            <div class="accordion__item vs-tabs__item{if $vsPanelFirst} visible{/if}">
                <h2 class="accordion__title vs-tabs__head{if $vsPanelFirst} active{/if}">
                    <button type="button" id="fn_tab_comments" class="vs-tabs__btn">
                        <span data-language="product_comments">{$lang->product_comments}</span>
                        <span class="vs-tabs__chevron">{include file="svg.tpl" svgId="chevron"}</span>
                    </button>
                </h2>
                <div id="comments" class="accordion__content vs-tabs__panel">
                    <div class="vs-reviews">
                        <div class="vs-reviews__list">
                            {if $comments}
                                {function name=comments_tree level=0}
                                    {foreach $comments as $comment}
                                        <div class="comment__item{if $level > 0} admin_note{/if}">
                                            <a name="comment_{$comment->id}"></a>
                                            <div class="comment__inner">
                                                <div class="comment__icon">
                                                    {if $level > 0}
                                                        {include file="svg.tpl" svgId="headset"}
                                                    {else}
                                                        {include file="svg.tpl" svgId="user"}
                                                    {/if}
                                                </div>
                                                <div class="comment__boxed">
                                                    <div class="comment__header">
                                                        <div class="comment__author">
                                                            <span class="comment__name">{$comment->name|escape}</span>
                                                            {if !$comment->approved}
                                                                <span class="comment__status" data-language="post_comment_status">({$lang->post_comment_status})</span>
                                                            {/if}
                                                        </div>
                                                        <div class="comment__date">
                                                            <span>{$comment->date|date} {$comment->date|time}</span>
                                                        </div>
                                                    </div>
                                                    <div class="comment__body">
                                                        {$comment->text|escape|nl2br}
                                                    </div>
                                                </div>
                                            </div>
                                            {if !empty($comment->children)}
                                                {comments_tree comments=$comment->children level=$level+1}
                                            {/if}
                                        </div>
                                    {/foreach}
                                {/function}
                                {comments_tree comments=$comments}
                            {else}
                                <div class="vs-empty vs-empty--inline">
                                    <p class="vs-empty__note" data-language="product_no_comments">{$lang->product_no_comments}</p>
                                </div>
                            {/if}
                        </div>

                        <div class="vs-reviews__form">
                            <form id="captcha_id" class="fn_validate_product vs-form" method="post">
                                {if $settings->captcha_type == "v3"}
                                    <input type="hidden" class="fn_recaptcha_token fn_recaptchav3" name="recaptcha_token" />
                                {/if}

                                <div class="vs-form__title" data-language="product_write_comment">{$lang->product_write_comment}</div>

                                {if $error}
                                    <p class="vs-note vs-note--error">
                                        {if $error=='captcha'}
                                            <span data-language="form_error_captcha">{$lang->form_error_captcha}</span>
                                        {elseif $error=='empty_name'}
                                            <span data-language="form_enter_name">{$lang->form_enter_name}</span>
                                        {elseif $error=='empty_comment'}
                                            <span data-language="form_enter_comment">{$lang->form_enter_comment}</span>
                                        {elseif $error=='empty_email'}
                                            <span data-language="form_enter_email">{$lang->form_enter_email}</span>
                                        {/if}
                                    </p>
                                {/if}

                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_comment_name">{$lang->form_name}*</label>
                                    <input id="vs_comment_name" class="vs-field vs-form__input" type="text" name="name" value="{if $request_data.name}{$request_data.name|escape}{elseif $user->name}{$user->name|escape}{/if}" />
                                </div>

                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_comment_email">{$lang->form_email}</label>
                                    <input id="vs_comment_email" class="vs-field vs-form__input" type="text" name="email" value="{if $request_data.email}{$request_data.email|escape}{elseif $user->email}{$user->email|escape}{/if}" data-language="form_email" />
                                </div>

                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_comment_text">{$lang->form_enter_comment}*</label>
                                    <textarea id="vs_comment_text" class="vs-field vs-form__textarea" rows="4" name="text">{$request_data.text|escape}</textarea>
                                </div>

                                {if $settings->captcha_comment}
                                    {if $settings->captcha_type == "v2"}
                                        <div class="vs-form__row">
                                            <div id="recaptcha1"></div>
                                        </div>
                                    {elseif $settings->captcha_type == "default"}
                                        {get_captcha var="captcha_comment"}
                                        <div class="vs-form__row">
                                            <label class="vs-form__label" for="vs_captcha">{$captcha_comment[0]|escape} + ? = {$captcha_comment[1]|escape}</label>
                                            <input id="vs_captcha" class="vs-field vs-form__input" type="text" name="captcha_code" value="" />
                                        </div>
                                    {/if}
                                {/if}

                                <input type="hidden" name="comment" value="1">
                                <input class="g-recaptcha vs-btn vs-btn--secondary vs-form__submit" type="submit" name="comment" data-language="form_send"{if $settings->captcha_type == "invisible"} data-sitekey="{$settings->public_recaptcha_invisible}" data-badge='bottomleft' data-callback="onSubmit"{/if} value="{$lang->form_send}"/>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* Previous / next product *}
    {if $prev_product || $next_product}
        <nav class="vs-pager">
            {if $prev_product}
                <a class="vs-pager__link vs-pager__link--prev" href="{url_generator route="product" url=$prev_product->url}">
                    {include file="svg.tpl" svgId="chevron"}
                    <span>{$prev_product->name|escape}</span>
                </a>
            {/if}
            {if $next_product}
                <a class="vs-pager__link vs-pager__link--next" href="{url_generator route="product" url=$next_product->url}">
                    <span>{$next_product->name|escape}</span>
                    {include file="svg.tpl" svgId="chevron"}
                </a>
            {/if}
        </nav>
    {/if}

    {* Sticky buy bar. Below 992px only, revealed by vibe.js once the inline buy
       row leaves the viewport. Its button carries form="vs_buy_form", so it
       submits the very same .fn_variants form - no second cart contract. The
       second .fn_price is deliberate: okay.js rewrites every .fn_price inside
       .fn_product, so the bar tracks the chosen variant for free. *}
    <div class="vs-sticky-buy">
        <div class="vs-sticky-buy__info">
            <span class="vs-sticky-buy__price{if $product->variant->compare_price} price--red{/if}">
                <span class="fn_price">{$product->variant->price|convert}</span>&nbsp;<span class="vs-buybox__currency">{$currency->sign|escape}</span>
            </span>
            <span class="vs-sticky-buy__old{if !$product->variant->compare_price} hidden-xs-up{/if}">
                <span class="fn_old_price">{$product->variant->compare_price|convert}</span>&nbsp;<span class="vs-buybox__currency">{$currency->sign|escape}</span>
            </span>
        </div>

        {if !$settings->is_preorder}
            <p class="fn_not_preorder vs-buybox__unavailable{if $product->variant->stock > 0} hidden-xs-up{/if}">
                <span data-language="product_out_of_stock">{$lang->product_out_of_stock}</span>
            </p>
        {else}
            <button class="fn_is_preorder vs-btn vs-btn--secondary vs-sticky-buy__cta{if $product->variant->stock > 0} hidden-xs-up{/if}" type="submit" form="vs_buy_form" data-language="product_pre_order">{$lang->product_pre_order}</button>
        {/if}

        <button class="fn_is_stock vs-btn vs-btn--primary vs-sticky-buy__cta vs-sticky-buy__cta--icon{if $product->variant->stock < 1} hidden-xs-up{/if}" type="submit" form="vs_buy_form">
            {include file="svg.tpl" svgId="cart"}
            <span data-language="product_add_cart">{$lang->product_add_cart}</span>
        </button>
    </div>
</div>

{* Related products *}
{if $related_products}
    <section class="vs-section">
        <h2 class="vs-section__title" data-language="product_recommended_products">{$lang->product_recommended_products}</h2>
        <div class="vs-related__grid">
            {foreach $related_products as $p}
                {include "product_list.tpl" product = $p}
            {/foreach}
        </div>
    </section>
{/if}

{if $related_posts}
    <section class="vs-section">
        <h2 class="vs-section__title" data-language="product_related_post">{$lang->product_related_post}</h2>
        {* .vs-posts, the same track the home page, the blog index and an author
           page use. This block was the one caller still on the legacy
           `f_row no_gutters` + `f_col-*` wrappers, and no_gutters is exactly what
           it says: the columns had no padding and nothing replaced it, so two
           cards sat edge to edge with no gap at any width. The wrapper divs go
           with it - the grid has to see the cards themselves for `align-items:
           stretch` and for `.vs-posts > .vs-post-card:only-child` to apply. *}
        <div class="fn_articles_slide vs-posts vs-posts--related">
            {foreach $related_posts as $r_p}
                {include 'post_list.tpl' post = $r_p}
            {/foreach}
        </div>
    </section>
{/if}
