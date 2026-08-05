<div class="order">
    {if $delivery}
        <span class="delivery">{$delivery->name|escape}</span>
    {/if}
    <i>{$purchase->variant->name|escape}</i>
</div>
