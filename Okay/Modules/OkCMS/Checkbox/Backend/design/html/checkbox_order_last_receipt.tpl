{if $order->receipt}
    <a href="{url_generator route="OkCMS_Checkbox_getReceiptPdf" receiptId=$order->receipt->receipt_id absolute=1}" target="_blank">
        {if $order->receipt->is_return}{$btr->okcms__checkbox_orders_receipt_return|escape}{else}{$btr->okcms__checkbox_orders_receipt_pay|escape}{/if}
        ({$order->receipt->created_at|date_format:"H:i d.m.Y"})
        {include file='svg_icon.tpl' svgId='print'}
    </a>
{/if}