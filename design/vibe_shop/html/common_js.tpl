<script>
    {*todo написати інструкцію до файла*}
    {literal}
    const okay = {};
    okay.router = {};
    {/literal}
    
    {if $common_js_vars}
        {foreach $common_js_vars as $var=>$value}
            okay.{$var|escape} = {$value};
        {/foreach}
    {/if}
    
    {if $front_routes}
        {foreach $front_routes as $name=>$route}
            okay.router['{$name|escape}'] = '{url_generator route=$name absolute=1}';
        {/foreach}
    {/if}

    okay.change_payment_method = function() {
        let delivery_input = $( 'input[name="delivery_id"]:checked' );
        let current_payment_id = $( 'input[name="payment_method_id"]:checked' ).val();
        let delivery_id = delivery_input.val();
        let payments_ids = new Array();
        
        if (String(delivery_input.data('payment_method_ids')).length > 0) {
            payments_ids = String(delivery_input.data('payment_method_ids')).split(',');
        }

        $( ".fn_payment_method__item" ).hide().find('input[name="payment_method_id"]').prop('disabled', true);
        $( ".fn_payment_method__item" )
            .find('input[name="payment_method_id"]')
            .not('[value="' + current_payment_id + '"]')
            .prop('checked', false);
        
        if (payments_ids.length > 0) {
            payments_ids.forEach(function (payment_id, i, arr) {
                let payment_block =  $( ".fn_payment_method__item_" + payment_id );
                let payment_input = payment_block.find('input[name="payment_method_id"]');
                let currency_id = payment_input.data('currency_id');

                payment_block.show();
                payment_block.find('.fn_payment_price').text(okay.convert(delivery_input.data('total_price'), currency_id));
                payment_input.prop('disabled', false);
            });

            $('.fn_payments_block').show();

            {* This pass decides visibility from the delivery's payment_method_ids
               alone, so it un-hides everything the NovaposhtaCost module had
               filtered out for the "Післяплата" checkbox. It runs again after every
               cart AJAX - ajax_set_result() ends by triggering change on the checked
               delivery - which is why applying a coupon or changing a quantity
               brought the cash-on-delivery method back. Hand control back to the
               module when it is installed; it re-picks the payment itself. *}
            if (typeof update_np_payments === 'function') {
                update_np_payments();

                if (typeof select_first_active_payment === 'function'
                    && $('input[name="payment_method_id"]:checked').closest('.fn_payment_method__item').is(':hidden')) {
                    select_first_active_payment();
                }
            }

            /*якщо спосіб оплати не активний для цієї доставки, тоді виберемо перший доступний*/
            if ($('input[name="payment_method_id"][value="' + current_payment_id + '"]').is(':disabled')) {
                $(".fn_payment_method__item:visible").first().find('input[name="payment_method_id"]').prop('checked', true).trigger('click');
            }
        } else {
            $('.fn_payments_block').hide();
        }
        
        okay.update_cart_total_price();

        $( 'input[name="delivery_id"]' ).parent().removeClass( 'active' );
        $( '#deliveries_' + delivery_id ).parent().addClass( 'active' );
    };

    okay.update_cart_total_price = function() {
        let delivery_input = $( 'input[name="delivery_id"]:checked' );
        $('#fn_cart_total_price').text(okay.convert(delivery_input.data('total_price')));
        $('#fn_total_purchases_price').text(okay.convert($('.fn_purchases_wrap').data('total_purchases_price'), null, true, true));

        /*Оновлюємо інформацію щодо вартості доставки*/
        if (delivery_input.data('is_free_delivery')) {
            $('#fn_total_free_delivery').show();
            $('#fn_total_delivery_price').hide();
        } else {
            $('#fn_total_free_delivery').hide();
            $('#fn_total_delivery_price').text(okay.convert(delivery_input.data('delivery_price'), null, true, true)).show();
        }

        /*Оновлюємо інформацію щодо оплати доставки (окремо чи ні)*/
        if (delivery_input.data('separate_payment') == true && delivery_input.data('is_free_delivery') == false) {
            $('#fn_total_separate_delivery').show();
        } else {
            $('#fn_total_separate_delivery').hide();
        }
    };

    /*Метод форматування ціни в js*/
    okay.convert = function(price, currencyId = null, format = true, withCurrency = false)
    {

        if (currencyId === null) {
            currencyId = {$currency->id};
        }

        let currencies = Object.create(null);

        {foreach $currencies as $c}
        currencies[{$c->id}] = {
            'rate_from': '{$c->rate_from|escape}',
            'rate_to': '{$c->rate_to|escape}',
            'cents': '{$c->cents|escape}',
            'sign': '{$c->sign|escape}',
        };
        {/foreach}

        let currency = currencies[currencyId];
        
        if (typeof currency == "undefined") {
            console.error('currency ID='+currencyId+' is not defined');
            return 'currency error';
        }
        
        let decimal = currency.cents;
        let dec_point = '{$settings->decimals_point|escape}';
        let separator = '{$settings->thousands_separator|escape}';
        let res = parseFloat(price*currency.rate_from/currency.rate_to);

        if (format === true) {
            res = Number(res).toFixed(decimal).toString().split('.');
            {literal}
            let b = res[0].replace(/(\d{1,3}(?=(\d{3})+(?:\.\d|\b)))/g,"\$1"+separator);
            {/literal}
            res = (res[1]?b+ dec_point +res[1]:b);

            if (withCurrency === true) {
                res += ' ' + currency.sign;
            }
        } else {
            res = Number(res).toFixed(decimal);
        }
        return res;
    };

</script>
