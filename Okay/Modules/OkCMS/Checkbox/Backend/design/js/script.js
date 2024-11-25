"use strict";

$(function(){
    $(document).on('click', '.js-okcms-checkbox-action-shift', function(e){
        e.preventDefault();
        let $button = $(this),
            link = $button.attr('href'),
            id = typeof $button.data('id') !== 'undefined' ? $button.data('id') : '',
            isPrint = typeof $button.data('print') !== 'undefined';

        $('.js-checkbox-action-loader').removeClass('hidden');

        $.ajax({
            url: link,
            dataType: 'json',
            method: 'POST',
            data: {id: id},
        }).done(function(response){
            $('.js-checkbox-action-loader').addClass('hidden');
            if(response.message) {
                alert(response.message);
            } else {
                if($button.data('replace') && response.html) {
                    $($button.data('replace')).html(response.html);
                } else {
                    if(response.link) {
                        if(isPrint) {
                            let windowPrint = window.open(response.link);
                            windowPrint.print();
                        } else {
                            $.fancybox.open({
                                src: response.link,
                                type : 'iframe'
                            });
                        }
                    } else {
                        window.location = window.location;
                    }
                }
            }
            //console.log(response);
        }).fail(function(xhr, ajaxOptions, thrownError){
            $('.js-checkbox-action-loader').addClass('hidden');
            alert('unknown error');
            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        });
    });

    $(document).on('click', '.js-checkbox-create-receipt', function(e){
        let $button = $(this),
            link = $button.data('href'),
            orderId = $button.data('order_id'),
            isReturn = $button.data('return');

        $('.js-checkbox-action-loader').removeClass('hidden');

        $.ajax({
            url: link,
            dataType: 'json',
            method: 'POST',
            data: {orderId: orderId, isReturn: isReturn},
        }).done(function(response){
            $('.js-checkbox-action-loader').addClass('hidden');
            if(response.message) {
                alert(response.message);
            } else {
                if(response.html) {
                    //$('.js-checkbox-order-receipts-list').prepend(response.html);
                    $('.js-checkbox-order-receipts-list').append(response.html);
                }
                $button.addClass('hidden').siblings().removeClass('hidden');
            }
            //console.log(response);
        }).fail(function(xhr, ajaxOptions, thrownError){
            $('.js-checkbox-action-loader').addClass('hidden');
            alert('unknown error');
            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        });

    });


});