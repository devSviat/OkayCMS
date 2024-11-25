{*inform_back_in_stock*}
<div id="fn_inform_stock_id_btn" class="form-group{if $product->variant->stock > 0} hidden{/if}"><br>
    <button class="fn-inform_back_in_stock button" data-product_name="{$product->name|escape}" data-product_id="{$product->id}" type="button">{$lang->inform_stock_btn}</button>
</div>
{*/inform_back_in_stock*}