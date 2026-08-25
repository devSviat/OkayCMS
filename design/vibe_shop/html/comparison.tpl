<!-- The products comparison page -->

<div class="vs-page">
    {* The page heading *}
    <div class="vs-page__masthead">
        <h1 class="vs-page__title">
            <span data-language="comparison_header">{$lang->comparison_header}</span>
        </h1>
    </div>

    {if !empty($comparison->products)}
        <div class="vs-compare">
            <div class="vs-compare__names">
                <div class="fn_resize vs-compare__controls">
                    {* Show all/different product features. count() on null is a
                       PHP 8 fatal, so the collection is checked, not counted. *}
                    {if $comparison->products|count > 1}
                        <div class="fn_show vs-compare__switch">
                            <a href="#show_all" class="vs-compare__switch_btn active"><span data-language="comparison_all">{$lang->comparison_all}</span></a>
                            <a href="#show_dif" class="vs-compare__switch_btn unique"><span data-language="comparison_unique">{$lang->comparison_unique}</span></a>
                        </div>
                    {/if}
                </div>
                {* Rating *}
                <div class="cprs_rating vs-compare__cell vs-compare__cell--name" data-use="cprs_rating">
                    <span data-language="product_rating">{$lang->product_rating}</span>
                </div>
                {* Feature name *}
                {if $comparison->features}
                    {foreach $comparison->features as $id=>$cf}
                        <div class="cprs_feature_{$id} cell vs-compare__cell vs-compare__cell--name{if $cf->not_unique} not_unique{/if}" data-use="cprs_feature_{$id}">
                            <span data-feature="{$cf->id}">{$cf->name|escape}</span>
                        </div>
                    {/foreach}
                {/if}
            </div>

            <div class="fn_comparison_products vs-compare__track swiper-container">
                <div class="swiper-wrapper">
                    {foreach $comparison->products as $id=>$product}
                        <div class="vs-compare__column swiper-slide">
                            <div class="fn_resize product_item no_hover">
                                {include file="product_list.tpl"}
                            </div>

                            {* Rating. data-label carries the row name into the cell
                               because the label column is hidden below 992px - a
                               bare value with nothing naming it is not a
                               comparison. *}
                            <div id="product_{$product->id}" class="cprs_rating vs-compare__cell vs-rating" data-label="{$lang->product_rating|escape}">
                                <span class="rating_starOff">
                                    <span class="rating_starOn" style="width:{((($product->rating*2)|round)/2)*18|string_format:'%.0f'}px;"></span>
                                </span>
                            </div>

                            {* Feature value *}
                            {if $product->features}
                                {foreach $product->features as $id=>$value}
                                    <div class="cprs_feature_{$id} cell vs-compare__cell{if $comparison->features.{$id}->not_unique} not_unique{/if}" data-label="{$comparison->features.{$id}->name|escape}">
                                        <span class="vs-compare__value">{$value|default:"&mdash;"}</span>
                                    </div>
                                {/foreach}
                            {/if}
                        </div>
                    {/foreach}
                </div>
                <div class="swiper-button-next vs-compare__nav"></div>
                <div class="swiper-button-prev vs-compare__nav"></div>
            </div>
        </div>
    {else}
        <div class="vs-empty vs-empty--center">
            <span class="vs-empty__icon">{include file="svg.tpl" svgId="compare"}</span>
            <p class="vs-empty__title" data-language="comparison_empty">{$lang->comparison_empty}</p>
            <a class="vs-btn vs-btn--primary" href="{url_generator route='products'}">
                <span data-language="index_categories">{$lang->index_categories}</span>
            </a>
        </div>
    {/if}
</div>
