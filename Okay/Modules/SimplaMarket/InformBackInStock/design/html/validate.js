if($(".fn_validate_backinstock").length>0) {
    $(".fn_validate_backinstock").validate({
        rules: {
            name: "required",
            email: {
                required: true,
                email: true
            },
        },
        messages: {
            name: '{$lang->form_enter_name|escape}',
            email: '{$lang->form_enter_email|escape}',
        },
        errorLabelContainer: '#fn_inform_back__errors'
    });
}