{* Sorting as one segmented control: the four options are mutually exclusive.
   fn_ajax_buttons + fn_is_ajax keep okay.js's ajax pagination contract, and
   the two-arrow sprite says which direction the next tap will sort in. *}
{if $products|count > 0}
    <span class="vs-sort__label hidden-sm-down" data-language="products_sort_by">{$lang->products_sort_by}:</span>

    <div class="fn_ajax_buttons vs-sort__group" role="group" aria-label="{$lang->products_sort_by|escape}">
        <form class="vs-sort__item" method="post">
            <button type="submit" name="prg_seo_hide" class="vs-sort__btn{if $sort=='position'} active_up{/if}" value="{furl sort=position page=null absolute=1}">
                <span data-language="products_by_default">{$lang->products_by_default}</span>
            </button>
        </form>

        <form class="vs-sort__item" method="post">
            <button type="submit" name="prg_seo_hide" class="vs-sort__btn{if $sort=='price'} active_up{elseif $sort=='price_desc'} active_down{/if}" value="{if $sort=='price'}{furl sort=price_desc page=null absolute=1}{else}{furl sort=price page=null absolute=1}{/if}">
                <span data-language="products_by_price">{$lang->products_by_price}</span>
                {include file="svg.tpl" svgId="sort_icon"}
            </button>
        </form>

        <form class="vs-sort__item" method="post">
            <button type="submit" name="prg_seo_hide" class="vs-sort__btn{if $sort=='name'} active_up{elseif $sort=='name_desc'} active_down{/if}" value="{if $sort=='name'}{furl sort=name_desc page=null absolute=1}{else}{furl sort=name page=null absolute=1}{/if}">
                <span data-language="products_by_name">{$lang->products_by_name}</span>
                {include file="svg.tpl" svgId="sort_icon"}
            </button>
        </form>

        <form class="vs-sort__item" method="post">
            <button type="submit" name="prg_seo_hide" class="vs-sort__btn{if $sort=='rating'} active_up{elseif $sort=='rating_desc'} active_down{/if}" value="{if $sort=='rating'}{furl sort=rating_desc page=null absolute=1}{else}{furl sort=rating page=null absolute=1}{/if}">
                <span data-language="products_by_rating">{$lang->products_by_rating}</span>
                {include file="svg.tpl" svgId="sort_icon"}
            </button>
        </form>
    </div>
{/if}
