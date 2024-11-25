<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="boxed fn_toggle_wrap">
            <div class="heading_box">
                {$btr->okcms__checkbox_title|escape}
                <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                    <a class="btn-minimize" href="javascript:;" ><i class="fa fn_icon_arrow fa-angle-down"></i></a>
                </div>
            </div>

            {*Параметры элемента*}
            <div class="toggle_body_wrap on fn_card">
                <div class="row">
                    <div class="col-md-4">
                        <div class="heading_label">{$btr->okcms__checkbox_login|escape}</div>
                        <div class="mb-1">
                            <input name="okcms__checkbox_login" class="form-control" type="text" value="{$manager->okcms__checkbox_login|escape}" />
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="heading_label">{$btr->okcms__checkbox_password|escape}</div>
                        <div class="mb-1">
                            <input name="okcms__checkbox_password" class="form-control" type="text" value="{$manager->okcms__checkbox_password|escape}" />
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="heading_label">{$btr->okcms__checkbox_licenseKey|escape}</div>
                        <div class="mb-1">
                            <input name="okcms__checkbox_licenseKey" class="form-control" type="text" value="{$manager->okcms__checkbox_licenseKey|escape}" />
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>