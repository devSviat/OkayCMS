{$meta_title = $btr->simplamarket__personal_data__title|escape scope=global}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->simplamarket__personal_data__title|escape}
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="row">
            <div class="col-md-6">
                <div class="alert alert--icon">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->simplamarket__personal_data__info}</div>
                        <p>{$btr->simplamarket__personal_data__description_2}</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="alert alert--icon alert--warning">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->simplamarket__personal_data__warning}</div>
                        <p>{$btr->simplamarket__personal_data__description_1}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form method="post" enctype="multipart/form-data">
    <div class="permission_block">
        <div class="permission_boxes row">
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="permission_box">
                    <span>{$btr->settings_general_comment|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="pd_comment" value='1' type="checkbox"
                               {if $settings->pd_comment}checked=""{/if}/>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="permission_box">
                    <span>{$btr->settings_general_cart|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="pd_cart" value='1' type="checkbox"
                               {if $settings->pd_cart}checked=""{/if}/>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="permission_box">
                    <span>{$btr->settings_general_register|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="pd_register" value='1' type="checkbox"
                               {if $settings->pd_register}checked=""{/if}/>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="permission_box">
                    <span>{$btr->settings_general_contact_form|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="pd_feedback" value='1' type="checkbox"
                               {if $settings->pd_feedback}checked=""{/if}/>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>

            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="permission_box">
                    <span>{$btr->settings_general_callback|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="pd_callback" value='1' type="checkbox"
                               {if $settings->pd_callback}checked=""{/if}/>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="permission_box">
                    <span>{$btr->simplamarket__personal_data__comment_product|escape}</span>
                    <label class="switch switch-default">
                        <input class="switch-input" name="pd_comment_product" value='1' type="checkbox" {if $settings->pd_comment_product}checked=""{/if}/>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>
        </div>

    </div>
    <div class="row">
        <div class="col-lg-12 col-md-12 ">
            <button type="submit" class="btn btn_small btn_blue float-md-right">
                {include file='svg_icon.tpl' svgId='checked'}
                <span>{$btr->general_apply|escape}</span>
            </button>
        </div>
    </div>
</form>
