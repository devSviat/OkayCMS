$(document).on('change', '.fn_variant', function() {
    let selected = $(this).children(':selected'),
    // let selected = $('input[name="variant"]:checked'),
        stock = parseInt(selected.data('stock'));

    if (stock == 0) {
        $('#fn_inform_stock_id_btn').removeClass('hidden');
    } else {
        $('#fn_inform_stock_id_btn').addClass('hidden');
    }
});

$('.fn-inform_back_in_stock').on('click', function(e) {
    e.preventDefault();

    $.fancybox.open( {
        src: '#fn-inform_back_in_stock_form',
        type : 'inline',
    } );

});


$('.fn_inform_back_in_stock_submit').on('click', function (e) {

    e.preventDefault();

    let $form = $('#fn-inform_back_in_stock_form');
    if ($form.valid() === false) {
        return false;
    }

    let variant_id = $('select[name=variant]').val(),
        name       = $form.find('input[name="name"]').val(),
        email      = $form.find('input[name="email"]').val();

    $.ajax({
        url: okay.router['SimplaMarket.InformBackInStock'],
        type: 'POST',
        dataType: 'json',
        data:
            {
                variant_id: variant_id,
                email: email,
                name: name,
            },
            // $form.serialize(),
        success: function(data) {
        if (data.success){

                $.fancybox.open( {
                    src: '#fn_message_sent',
                    type : 'inline',
                } );
                function reload(){
                    document.location.reload();
                }
                setTimeout(reload, 2000);
            }
            else if (data.error) {
                $.fancybox.open( {
                    src: '#fn_error_sent_earlier',
                    type : 'inline',
                } );
                function reload(){
                    document.location.reload();
                }
                setTimeout(reload, 2000);
            }
            else if (data.error_empty){
                $form.find('.fn_error').text(data.error_empty).show();
            }

        }
    });
});
