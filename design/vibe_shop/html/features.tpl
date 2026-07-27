{* Filter panel body. Every group is a fn_switch header immediately followed by
   its body - okay.js collapses a group with $(this).next().slideToggle(), so
   the two must stay adjacent siblings.

   Collapse state ships from here. `active` on the head is okay.js's own name for
   "collapsed"; components.css turns it into display:none on the next sibling, so
   the first click on a collapsed head expands it through exactly the handler that
   drives every later toggle. Two groups ship open - the category tree and price,
   the two a shopper reaches for first - and every other group ships collapsed
   UNLESS one of its own values is currently applied. A collapsed group hiding an
   applied filter is a trap, so an applied filter always wins.

   Rendering all groups open cost 8000px of scroll on a 29-group catalogue; the
   heads alone are a list the shopper can read at a glance. okay.js replaces this
   whole block after every ajax filter round-trip, so the "applied filters stay
   open" rule re-evaluates on each one without any client-side bookkeeping. *}

{if $catalog_categories}
    <div class="vs-filter-group vs-filter-group--cats hidden-md-down">
        <button type="button" class="fn_switch vs-filter-group__head" aria-expanded="true">
            <span data-language="features_catalog">{$lang->features_catalog}</span>
            <span class="vs-filter-group__chevron">{include file="svg.tpl" svgId="chevron"}</span>
        </button>
        <div class="vs-filter-group__body">
            {function name=categories_tree_sidebar}
                {if $categories}
                    <div class="vs-filter-cats{if $level > 1} vs-filter-cats--sub{/if}">
                        {foreach $categories as $c}
                            {if $c->visible && ($c->has_products || $settings->show_empty_categories)}
                                <div class="vs-filter-cats__item">
                                    <{if $c->id == $category->id && !$keyword}b{else}a{/if}
                                            class="vs-filter-cats__link{if $category->id == $c->id} is-current{/if}"
                                            {if $route_name === 'products'}
                                                href="{url_generator route="category" url=$c->url filtersUrl=['brand' => $brand->url] keyword=$keyword}"
                                            {else}
                                                href="{url_generator route="category" url=$c->url filtersUrl=['brand' => $brand->url]}"
                                            {/if}
                                            data-category="{$c->id}"
                                    >
                                        <span class="vs-filter-cats__icon">
                                            {if $c->image}
                                                <picture>
                                                    {if $settings->support_webp}
                                                        <source type="image/webp" data-srcset="{$c->image|resize:20:20:false:$config->resized_categories_dir}.webp">
                                                    {/if}
                                                    <source data-srcset="{$c->image|resize:20:20:false:$config->resized_categories_dir}">
                                                    <img class="lazy" data-src="{$c->image|resize:20:20:false:$config->resized_categories_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.gif" alt="{$c->name|escape}" title="{$c->name|escape}"/>
                                                </picture>
                                            {else}
                                                {include file="svg.tpl" svgId="no_image"}
                                            {/if}
                                        </span>
                                        <span class="vs-filter-cats__name">{$c->name|escape}</span>
                                        {if $c->id != $category->id}
                                            {include file="svg.tpl" svgId="arrow_right2"}
                                        {/if}
                                    </{if $c->id == $category->id && !$keyword}b{else}a{/if}>
                                </div>
                            {/if}
                        {/foreach}
                    </div>
                {/if}
            {/function}
            {categories_tree_sidebar categories=$catalog_categories level=1}
        </div>
    </div>
{/if}

{* Filters *}
{if ($catalog_brands || ($catalog_prices->min != $catalog_prices->max) || $catalog_features)}
    {* Ajax Price filter *}
    {if $catalog_prices->min != '' && $catalog_prices->max != '' && $catalog_prices->min != $catalog_prices->max}
        <div class="vs-filter-group">
            <button type="button" class="fn_switch vs-filter-group__head" aria-expanded="true">
                <span data-language="features_price">{$lang->features_price}</span>
                <span class="vs-filter-group__chevron">{include file="svg.tpl" svgId="chevron"}</span>
            </button>
            <div class="vs-filter-group__body">
                {* Price range *}
                <div class="vs-price">
                    <div class="vs-price__field">
                        <input class="vs-field vs-price__input" id="fn_slider_min" aria-label="{$lang->features_price_from|escape}" name="p[min]" value="{($selected_catalog_prices['min']|default:$catalog_prices->min)|escape}" data-price="{$catalog_prices->min}" type="text">
                    </div>
                    <span class="vs-price__sep">&ndash;</span>
                    <div class="vs-price__field">
                        <input class="vs-field vs-price__input" id="fn_slider_max" aria-label="{$lang->features_price_to|escape}" name="p[max]" value="{($selected_catalog_prices['max']|default:$catalog_prices->max)|escape}" data-price="{$catalog_prices->max}" type="text">
                    </div>
                    <span class="vs-price__currency">{$currency->sign|escape}</span>
                </div>
                {* Price slider *}
                <div id="fn_slider_price" class="vs-price__slider" data-href="{furl params=[price=>['min'=>'min', 'max'=>'max'], page=>null, sort=>null, route=>$furlRoute]}"></div>
            </div>
        </div>
    {/if}

    {* Other filters *}
    {if $catalog_other_filters}
        {$grp_open = (bool)$selected_catalog_other_filters}
        <div class="vs-filter-group">
            <button type="button" class="fn_switch vs-filter-group__head{if !$grp_open} active{/if}" aria-expanded="{if $grp_open}true{else}false{/if}">
                <span data-language="features_other_filter">{$lang->features_other_filter}</span>
                <span class="vs-filter-group__chevron">{include file="svg.tpl" svgId="chevron"}</span>
            </button>
            <div class="vs-filter-group__body">
                <div class="vs-filter__list">
                    {* Display all other filters *}
                    <div class="vs-filter__item">
                        <form method="post">
                            {$furl = {furl params=[filter=>null, page=>null, route=>$furlRoute]}}
                            <button type="submit" name="prg_seo_hide" class="vs-filter__option{if !$selected_catalog_other_filters} checked{/if}" value="{$furl|escape}">
                                <span class="vs-filter__box">
                                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                    </svg>
                                </span>
                                <span class="vs-filter__label" data-language="features_all">{$lang->features_all}</span>
                            </button>
                        </form>
                    </div>
                    {* Other filter list *}
                    {foreach $catalog_other_filters as $f}
                        <div class="vs-filter__item">
                            {$furl = {furl params=[filter=>$f->url, page=>null, route=>$furlRoute]}}
                            {if $seo_hide_filter || ($selected_catalog_other_filters && in_array($f->url, $selected_catalog_other_filters))}
                                <form method="post">
                                    <button type="submit" name="prg_seo_hide" class="vs-filter__option{if $selected_catalog_other_filters && in_array($f->url, $selected_catalog_other_filters)} checked{/if}" value="{$furl|escape}">
                                        <span class="vs-filter__box">
                                            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                            </svg>
                                        </span>
                                        <span class="vs-filter__label" data-language="{$f->translation}">{$f->name|escape}</span>
                                    </button>
                                </form>
                            {else}
                                <a class="vs-filter__option{if $selected_catalog_other_filters && in_array($f->url, $selected_catalog_other_filters)} checked{/if}" href="{$furl}">
                                    <span class="vs-filter__box">
                                        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                        </svg>
                                    </span>
                                    <span class="vs-filter__label" data-language="{$f->translation}">{$f->name|escape}</span>
                                </a>
                            {/if}
                        </div>
                    {/foreach}
                </div>
            </div>
        </div>
    {/if}

    {* Brand filter *}
    {if $catalog_brands}
        {$grp_open = (bool)($brand->id || $selected_catalog_brands_ids)}
        <div class="vs-filter-group">
            <button type="button" class="fn_switch vs-filter-group__head{if !$grp_open} active{/if}" aria-expanded="{if $grp_open}true{else}false{/if}">
                <span data-language="features_manufacturer">{$lang->features_manufacturer}</span>
                <span class="vs-filter-group__chevron">{include file="svg.tpl" svgId="chevron"}</span>
            </button>
            <div class="fn_view_content vs-filter-group__body">
                <div class="vs-filter__list">
                    {* Display all brands *}
                    <div class="vs-filter__item">
                        <form method="post">
                            {$furl = {furl params=[brand=>null, page=>null, route=>$furlRoute]}}
                            <button type="submit" name="prg_seo_hide" class="vs-filter__option{if !$brand->id && !$selected_catalog_brands_ids} checked{/if}" value="{$furl|escape}">
                                <span class="vs-filter__box">
                                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                    </svg>
                                </span>
                                <span class="vs-filter__label" data-language="features_all">{$lang->features_all}</span>
                            </button>
                        </form>
                    </div>
                    {* Brand list *}
                    {foreach $catalog_brands as $b}
                        {$b_count = $b_count+1}
                        <div class="vs-filter__item{if $b && $b_count > 4} {if $brand->id == $b->id || $selected_catalog_brands_ids && in_array($b->id,$selected_catalog_brands_ids)}opened{else}closed{/if}{/if}">
                            {$furl = {furl params=[brand=>$b->url, page=>null, route=>$furlRoute]}}
                            {if $seo_hide_filter || ($brand->id == $b->id || $selected_catalog_brands_ids && in_array($b->id,$selected_catalog_brands_ids))}
                                <form method="post">
                                    <button type="submit" name="prg_seo_hide" class="vs-filter__option{if $brand->id == $b->id || $selected_catalog_brands_ids && in_array($b->id,$selected_catalog_brands_ids)} checked{/if}" value="{$furl|escape}">
                                        <span class="vs-filter__box">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                            </svg>
                                        </span>
                                        <span class="vs-filter__label">{$b->name|escape}</span>
                                    </button>
                                </form>
                            {else}
                                <a class="vs-filter__option{if $brand->id == $b->id || $selected_catalog_brands_ids && in_array($b->id,$selected_catalog_brands_ids)} checked{/if}" href="{$furl}">
                                    <span class="vs-filter__box">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                        </svg>
                                    </span>
                                    <span class="vs-filter__label">{$b->name|escape}</span>
                                </a>
                            {/if}
                        </div>
                    {/foreach}
                    {if $b_count > 4}
                        <a class="fn_view_all vs-filter__more" href="">{$lang->filter_view_show|escape}</a>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    {* Features filter *}
    {if $catalog_features}
        {foreach $catalog_features as $key=>$f}
            {$grp_open = (bool)($selected_catalog_features[$f->id]|default:false)}
            <div class="vs-filter-group">
                <button type="button" class="fn_switch vs-filter-group__head{if !$grp_open} active{/if}" aria-expanded="{if $grp_open}true{else}false{/if}">
                    <span data-feature="{$f->id}">{$f->name|escape}</span>
                    <span class="vs-filter-group__chevron">{include file="svg.tpl" svgId="chevron"}</span>
                </button>
                <div class="fn_view_content vs-filter-group__body">
                    <div class="vs-filter__list">
                        {* Display all features *}
                        <div class="vs-filter__item">
                            <form method="post">
                                {$furl = {furl params=[$f->url=>null, page=>null, route=>$furlRoute]}}
                                <button type="submit" name="prg_seo_hide" class="vs-filter__option{if !isset($selected_catalog_features[$f->id])} checked{/if}" value="{$furl|escape}">
                                    <span class="vs-filter__box">
                                        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                        </svg>
                                    </span>
                                    <span class="vs-filter__label" data-language="features_all">{$lang->features_all}</span>
                                </button>
                            </form>
                        </div>

                        {* Feature value *}
                        {$f_count = 0}
                        {foreach $f->features_values as $fv}
                            {$f_count = $f_count+1}
                            <div class="vs-filter__item{if $fv && $f_count > 4} {if $selected_catalog_features[$f->id] && isset($selected_catalog_features[$f->id][$fv->id])}opened{else}closed{/if}{/if}">
                                {$furl = {furl params=[$f->url=>$fv->translit, page=>null, route=>$furlRoute]}}
                                {if !$fv->to_index || $seo_hide_filter || ($selected_catalog_features[$f->id] && isset($selected_catalog_features[$f->id][$fv->id]))}
                                    <form method="post">
                                        <button type="submit" name="prg_seo_hide" class="vs-filter__option{if $selected_catalog_features[$f->id] && isset($selected_catalog_features[$f->id][$fv->id])} checked{/if}" value="{$furl|escape}">
                                            <span class="vs-filter__box">
                                                <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                    <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                                </svg>
                                            </span>
                                            <span class="vs-filter__label">{$fv->value|escape}</span>
                                        </button>
                                    </form>
                                {else}
                                    <a class="vs-filter__option{if $smarty.get.{$f@key} && in_array($fv->translit,$smarty.get.{$f@key},true)} checked{/if}" href="{$furl}">
                                        <span class="vs-filter__box">
                                            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                <path class="checkmark-path" fill="none" d="M4 10 l5 4 8-8.5"></path>
                                            </svg>
                                        </span>
                                        <span class="vs-filter__label">{$fv->value|escape}</span>
                                    </a>
                                {/if}
                            </div>
                        {/foreach}
                        {if $f_count > 4}
                            <a class="fn_view_all vs-filter__more" href="">{$lang->filter_view_show|escape}</a>
                        {/if}
                    </div>
                </div>
            </div>
        {/foreach}
    {/if}
{/if}
