{* Sorting as one segmented control: the four options are mutually exclusive.
   fn_ajax_buttons + fn_is_ajax keep okay.js's ajax pagination contract, and
   the two-arrow sprite says which direction the next tap will sort in.

   Below 576px the same markup is the theme's bottom sheet, opened by
   .vs-sort__trigger. It is the .vs-sort__sheet wrapper and not
   .vs-sort__group that carries .vs-sheet: vibeSheet writes role="dialog"
   onto the sheet element when it opens and REMOVES the attribute when it
   closes, so a role="group" living on that element would be destroyed by
   the first open. The group keeps its own element, and its role with it.

   The sheet is nested here rather than moved to a body-level element on
   purpose. .fn_products_sort is re-rendered on every ajax sort, so a copy
   outside it would keep a tick that no longer matches the grid. Measured
   before this was written: no ancestor between .vs-sort__group and <body>
   creates a stacking context or clips, so the fixed sheet is not clamped
   by one - see the warning at the .vs-sheet primitive. *}
{if $products|count > 0}
    {if $sort == 'price' || $sort == 'price_desc'}
        {$vs_sort_active = $lang->products_by_price}
    {elseif $sort == 'name' || $sort == 'name_desc'}
        {$vs_sort_active = $lang->products_by_name}
    {elseif $sort == 'rating' || $sort == 'rating_desc'}
        {$vs_sort_active = $lang->products_by_rating}
    {else}
        {$vs_sort_active = $lang->products_by_default}
    {/if}

    <span class="vs-sort__label hidden-sm-down" data-language="products_sort_by">{$lang->products_sort_by}:</span>

    <button type="button" class="vs-btn vs-btn--secondary vs-sort__trigger" data-vs-sheet-open="vs_sort" aria-controls="vs_sort" aria-expanded="false">
        {include file="svg.tpl" svgId="sort_icon"}
        <span class="vs-sort__trigger_label">{$vs_sort_active|escape}</span>
        {include file="svg.tpl" svgId="chevron"}
    </button>

    <div id="vs_sort" class="vs-sort__sheet vs-sheet">
        <div class="vs-sort__sheet_head">
            <span class="vs-sort__sheet_title" data-language="products_sort_by">{$lang->products_sort_by}</span>
            <button type="button" class="vs-btn vs-btn--ghost vs-btn--icon vs-sort__sheet_close" data-vs-sheet-close aria-label="{$lang->mobile_filter_close|escape}">
                {include file="svg.tpl" svgId="close"}
            </button>
        </div>

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
    </div>
{/if}
