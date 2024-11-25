<div class="col-xs-12">
    <div class="boxed">
        <div class="row">
            <div class="col-lg-12 toggle_body_wrap on fn_card">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="form-group clearfix">
                            <div class="heading_label" >{$btr->okcms__checkbox_type|escape}</div>
                            <select name="okcms__checkbox_type" class="selectpicker form-control">
                                <option value="CASH"{if $payment_method->okcms__checkbox_type == 'CASH'} selected{/if}>{$btr->okcms__checkbox_type_cash|escape}</option>
                                <option value="CASHLESS"{if $payment_method->okcms__checkbox_type == 'CASHLESS'} selected{/if}>{$btr->okcms__checkbox_type_cashless|escape}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>