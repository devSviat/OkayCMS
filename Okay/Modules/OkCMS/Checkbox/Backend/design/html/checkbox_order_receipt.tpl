<div class="row okay_list_row">
    <div class="okay_list_boding col-md-4">{$orderReceipt->created_at|date_format:"d.m.Y H:i:s"|default:"Не создан"}</div>
    <div class="okay_list_boding col-md-4">{$orderReceipt->receipt_id}</div>
    <div class="okay_list_boding col-md-2 txt_center">{if $orderReceipt->is_return}+{else}-{/if}</div>
    <div class="okay_list_boding col-md-2 txt_center">
        {if $orderReceipt->receipt_id}
            <a href="../files/checkbox/receipts/{$orderReceipt->receipt_id}.pdf" target="_blank" style="display: inline-block;">{include file='svg_icon.tpl' svgId='eye'}</a>
        {/if}

        {if $orderReceipt->receipt_id}
            <a href="{url_generator route="OkCMS_Checkbox_getReceiptPdf" receiptId=$orderReceipt->receipt_id absolute=1}" target="_blank" style="display: inline-block;">{include file='svg_icon.tpl' svgId='print'}</a>
        {/if}
    </div>
</div>