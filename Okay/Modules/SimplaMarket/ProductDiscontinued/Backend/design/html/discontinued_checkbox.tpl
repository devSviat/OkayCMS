<div class="activity_of_switch activity_of_switch--left">
    <div class="activity_of_switch_item">
        <div class="okay_switch clearfix">
            <label class="switch_label">{$btr->simplamarket__product_discontinued__discontinued_checkbox|escape}</label>
            <label class="switch switch-default">
                <input class="switch-input" name="discontinued" value="1" type="checkbox" id="example_checkbox"{if $product->discontinued}checked=""{/if}>
                <span class="switch-label"></span>
                <span class="switch-handle"></span>
            </label>
        </div>
    </div>
</div>