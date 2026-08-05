<!-- selected filters -->
{* Rendered above the grid, not inside the filter panel: the current filter
   state has to be readable from the results without reopening a panel. Each
   chip is the same POST that unsets its own filter, so removing one is one
   tap and the ajax/PRG contract is untouched. *}
{if $is_filter_page}
    <div class="vs-applied">
        <span class="vs-applied__label" data-language="selected_features_heading">{$lang->selected_features_heading}</span>

        {if $selected_catalog_prices}
            <form class="vs-applied__item" method="post">
                <button type="submit" name="prg_seo_hide" class="fn_filter_reset vs-applied__chip" value="{furl params=[price=>null, route=>$furlRoute]}">
                    <span class="vs-applied__text">{$lang->features_price}: <i>{$selected_catalog_prices['min']|escape} &ndash; {$selected_catalog_prices['max']|escape}</i></span>
                    {include file="svg.tpl" svgId="close"}
                </button>
            </form>
        {/if}

        {* Other filters *}
        {if $catalog_other_filters && $selected_catalog_other_filters}
            {foreach $catalog_other_filters as $f}
                {if in_array($f->url, $selected_catalog_other_filters)}
                    {$furl = {furl params=[filter=>$f->url, page=>null, route=>$furlRoute]}}
                    <form class="vs-applied__item" method="post">
                        <button type="submit" name="prg_seo_hide" class="vs-applied__chip" value="{$furl|escape}">
                            <span class="vs-applied__text" data-language="{$f->translation}">{$f->name|escape}</span>
                            {include file="svg.tpl" svgId="close"}
                        </button>
                    </form>
                {/if}
            {/foreach}
        {/if}

        {* Brand filter *}
        {if $catalog_brands && $selected_catalog_brands_ids}
            {foreach $catalog_brands as $b}
                {if $brand->id == $b->id || in_array($b->id, $selected_catalog_brands_ids)}
                    {$furl = {furl params=[brand=>$b->url, page=>null, route=>$furlRoute]}}
                    <form class="vs-applied__item" method="post">
                        <button type="submit" name="prg_seo_hide" class="vs-applied__chip" value="{$furl|escape}">
                            <span class="vs-applied__text"><i>{$b->name|escape}</i></span>
                            {include file="svg.tpl" svgId="close"}
                        </button>
                    </form>
                {/if}
            {/foreach}
        {/if}

        {* Features filter *}
        {if $catalog_features}
            {foreach $catalog_features as $key=>$f}
                {if $selected_catalog_features[$f->id]}
                    {foreach $f->features_values as $fv}
                        {if isset($selected_catalog_features[$f->id][$fv->id])}
                            {$furl = {furl params=[$f->url=>$fv->translit, page=>null, route=>$furlRoute]}}
                            <form class="vs-applied__item" method="post">
                                <button type="submit" name="prg_seo_hide" class="vs-applied__chip" value="{$furl|escape}">
                                    <span class="vs-applied__text">{$f->name|escape}: <i>{$fv->value|escape}</i></span>
                                    {include file="svg.tpl" svgId="close"}
                                </button>
                            </form>
                        {/if}
                    {/foreach}
                {/if}
            {/foreach}
        {/if}

        <form class="vs-applied__reset" method="post">
            <button type="submit" name="prg_seo_hide" class="fn_filter_reset vs-applied__reset_btn" value="{if $category}{url_generator route="category" url=$category->url}{elseif $brand}{url_generator route="brand" url=$brand->url}{else}{url_generator route=$route_name}{/if}">
                {$lang->selected_features_reset}
            </button>
        </form>
    </div>
{/if}
