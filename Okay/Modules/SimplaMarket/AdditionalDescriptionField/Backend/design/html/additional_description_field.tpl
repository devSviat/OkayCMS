<div class="col-lg-12 col-md-12">
    <div class="fn_step-14 boxed match fn_toggle_wrap tabs">
        <div class="heading_tabs">
            <div class="tab_navigation">
                <a href="#tab_additional_description_field__title"
                   class="heading_box tab_navigation_link">
                    {$btr->additional_description_field__label|escape}
                </a>
            </div>
            <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                <a class="btn-minimize" href="javascript:;"><i class="icon-arrow-down"></i></a>
            </div>
        </div>
        <div class="toggle_body_wrap on fn_card">
            <div class="tab_container">
                <div id="tab_additional_description_field__title" class="tab">
                    <textarea name="simplamarket__additional_description_field__description"
                              id="simplamarket__additional_description_field__description"
                              class="editor_small">{$product->simplamarket__additional_description_field__description|escape}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>
