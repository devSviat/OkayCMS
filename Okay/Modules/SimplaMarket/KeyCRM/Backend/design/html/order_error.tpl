{if $order->error_crm}
    <div class="alert alert--icon alert--error">
        <div class="alert__content">
            <div class="heading_label">{$btr->keycrm__error_crm|escape}</div>
            <textarea name="note" class="form-control short_textarea">{$order->error_crm|escape}</textarea>
        </div>
    </div>
{/if}