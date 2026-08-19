{$meta_title = $btr->settings_general_design scope=global}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="heading_page">
            {$btr->settings_general_design|escape}
            {*<div class="tooltip_box hint-bottom-middle-t-info-s-small-mobile hint-anim hidden-sm-down" data-hint="Описание tooltips">
                {include file='svg_icon.tpl' svgId='info_icon'}
            </div>*}
        </div>
    </div>
</div>

{if $message_error}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--error">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_error == 'css_write_error'}
                            {$btr->settings_theme_css_write_error|escape}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

{*Вывод успешных сообщений*}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'saved'}
                        {$btr->general_settings_saved|escape}
                        {/if}
                    </div>
                </div>
                {if $smarty.get.return}
                <a class="alert__button" href="{$smarty.get.return}">
                    {include file='svg_icon.tpl' svgId='return'}
                    <span>{$btr->general_back|escape}</span>
                </a>
                {/if}
            </div>
        </div>
    </div>
{/if}


{*Главная форма страницы*}
<form class="fn_form_list" method="post" enctype="multipart/form-data">
    <input type=hidden name="session_id" value="{$smarty.session.id}">

    {*Логотип сайта*}
    <div class="row">
        <div class="col-lg-6 col-md-6">
            <div class="boxed fn_toggle_wrap ">
                <div class="row">
                    <div class="col-lg-12 col-md-12">
                        <div class="main_header pt-0">
                            <div class="main_header__item">
                                <div class="heading_box mb-1">
                                {$btr->settings_theme_site_logo|escape}
                                <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_site_logo|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_site_logo|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                                </div>
                            </div>
                            <div class="main_header__item">
                                <div class="activity_of_switch mb-1">
                                    <div class="activity_of_switch_item"> {* row block *}
                                        <div class="okay_switch clearfix">
                                            <label class="switch_label">{$btr->settings_theme_multilang_logo|escape}
                                                <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_multilang_logo|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_multilang_logo|escape}">
                                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                                </i>
                                            </label>
                                            <label class="switch switch-default">
                                                <input class="switch-input" name="multilang_logo" value='1' type="checkbox" {if $settings->multilang_logo}checked=""{/if}/>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                        {get_design_block block="settings_theme_logo_checkboxes"}
                                    </div>
                                </div>
                            </div>
                            <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                                <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                            </div>
                        </div>
                        <div>
                            {$btr->settings_theme_allow_ext|escape}
                            {if $allow_ext}
                                {foreach $allow_ext as $img_ext}
                                    <span class="tag tag-info">{$img_ext|escape}</span>
                                {/foreach}
                            {/if}
                        </div>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-lg-12 col-md-12">
                            <div class="boxed fn_image_block site_logo_wrap">
                                {if $settings->site_logo}
                                    <div class="fn_parent_image txt_center">
                                        <div class="image_wrapper fn_image_wrapper fn_new_image text-xs-center">
                                            <a href="javascript:;" class="fn_delete_item delete_image remove_image"></a>
                                            <input type="hidden" name="site_logo" value="{$settings->site_logo|escape}">
                                            <img class="watermark_image" src="{$rootUrl}/{$config->design_images|escape}{$settings->site_logo|escape}?v={$settings->site_logo_version|escape}" alt="" />
                                        </div>
                                    </div>
                                {else}
                                    <div class="fn_parent_image"></div>
                                {/if}

                                <div class="fn_upload_image dropzone_block_image text-xs-center {if $settings->site_logo} hidden{/if}">
                                    {include file='svg_icon.tpl' svgId='upload'}
                                    <input class="dropzone_image" name="site_logo" type="file" accept="image/*" />
                                </div>
                                <div class="image_wrapper fn_image_wrapper fn_new_image text-xs-center hidden">
                                    <a href="javascript:;" class="fn_delete_item delete_image remove_image"></a>
                                    <input type="hidden" name="site_logo" value="{$settings->site_logo|escape}" disabled="">
                                    <img src="" alt="" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {get_design_block block="settings_theme_site_logo"}
            </div>
        </div>
        <div class="col-lg-6 col-md-6">
            <div class="boxed fn_toggle_wrap ">
                <div class="row">
                    <div class="col-lg-12 col-md-12">
                        <div class="heading_box row mb-0">
                            <div class="col-lg-12 col-md-12 mb-1">
                                {$btr->settings_theme_site_favicon|escape}
                                <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_site_favicon|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_site_favicon|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                                <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                            </div>
                        </div>
                        <div>
                            {$btr->settings_theme_allow_ext|escape}
                            {if $allow_ext}
                                {foreach $allow_ext as $img_ext}
                                    <span class="tag tag-info">{$img_ext|escape}</span>
                                {/foreach}
                            {/if}
                        </div>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-lg-12 col-md-12">
                            <div class="boxed fn_image_block site_logo_wrap">
                                {if $settings->site_favicon}
                                    <div class="fn_parent_image txt_center">
                                        <div class="image_wrapper fn_image_wrapper fn_new_image text-xs-center">
                                            <a href="javascript:;" class="fn_delete_item delete_image remove_image"></a>
                                            <input type="hidden" name="site_favicon" value="{$settings->site_favicon|escape}">
                                            <img class="watermark_image" src="{$rootUrl}/{$config->design_images|escape}{$settings->site_favicon|escape}?v={$settings->site_favicon_version|escape}" alt="" />
                                        </div>
                                    </div>
                                {else}
                                    <div class="fn_parent_image"></div>
                                {/if}

                                <div class="fn_upload_image dropzone_block_image text-xs-center {if $settings->site_favicon} hidden{/if}">
                                    {include file='svg_icon.tpl' svgId='upload'}
                                    <input class="dropzone_image" name="site_favicon" type="file" accept="image/*" />
                                </div>
                                <div class="image_wrapper fn_image_wrapper fn_new_image text-xs-center hidden">
                                    <a href="javascript:;" class="fn_delete_item delete_image remove_image"></a>
                                    <input type="hidden" name="site_favicon" value="{$settings->site_favicon|escape}" disabled="">
                                    <img src="" alt="" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {get_design_block block="settings_theme_favicon"}
            </div>
        </div>
    </div>

    {$block = {get_design_block block="settings_theme_custom_block"}}
    {if !empty($block)}
        <div class="row fn_toggle_wrap custom_block">
            {$block}
        </div>
    {/if}

    <div class="row">
        <div class="col-lg-6 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->settings_theme_deliveries|escape}
                    <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_deliveries|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_deliveries|escape}">
                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                    </i>
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                {*Параметры элемента*}
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="mb-1">
                                <textarea name="product_deliveries" class="form-control okay_textarea editor_small">{$settings->product_deliveries}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                {get_design_block block="settings_theme_deliveries"}
            </div>
        </div>
        <div class="col-lg-6 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->settings_theme_payments|escape}
                    <i class="fn_tooltips hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_payments|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_payments|escape}">
                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                    </i>
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                {*Параметры элемента*}
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="mb-1">
                                <textarea name="product_payments" class="form-control okay_textarea editor_small">{$settings->product_payments}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                {get_design_block block="settings_theme_payments"}
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->settings_theme_contact|escape}
                    <i class="fn_tooltips fn_tip_wide hint-bottom-left-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_contact|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_contact|escape}">
                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                    </i>
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                {*Параметры элемента*}
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="heading_label">{$btr->settings_theme_email|escape}</div>
                            <div class="mb-1">
                                <input name="site_email" class="form-control" type="text" value="{$settings->site_email|escape}" />
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="heading_label">{$btr->settings_theme_phones|escape}</div>
                            <div class="mb-1">
                                <input name="site_phones" class="form-control" type="text" value="{$site_phones|escape}" />
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="heading_label">{$btr->settings_theme_working_hours|escape}</div>
                            <div class="mb-1">
                                <textarea name="site_working_hours" class="form-control okay_textarea editor_small">{$settings->site_working_hours}</textarea>
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="heading_label">{$btr->settings_theme_social|escape}</div>
                            <div class="mb-1">
                                <textarea name="site_social_links" class="form-control okay_textarea">{$site_social_links}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                {get_design_block block="settings_theme_contacts"}
            </div>
        </div>
        <div class="col-lg-6 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->settings_theme_general_settings|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                {*Параметры элемента*}
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="heading_label">{$btr->settings_theme_iframe_map|escape}
                                <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_iframe_map|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_iframe_map|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <div class="mb-1">
                                <textarea name="iframe_map_code" class="form-control okay_textarea">{$settings->iframe_map_code}</textarea>
                            </div>
                        </div>
                        <div class="col-xs-12">
                            <div class="heading_label">{$btr->settings_theme_social_share|escape}</div>
                            <div class="mb-1">
                                <div class="share_networks">
                                    {foreach $share_networks as $network => $label}
                                        <div class="okay_type_checkbox_wrap">
                                            <input id="fn_share_{$network|escape}" class="hidden_check" type="checkbox" name="sj_shares[]" value="{$network|escape}"{if is_array($settings->sj_shares) && in_array($network, $settings->sj_shares)} checked{/if} />
                                            <label for="fn_share_{$network|escape}" class="okay_type_checkbox">
                                                <span>{$label|escape}</span>
                                            </label>
                                        </div>
                                    {/foreach}
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-12 col-md-12 ">
                            <button type="submit" class="btn btn_small btn_blue float-md-right">
                                {include file='svg_icon.tpl' svgId='checked'}
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                    </div>
                </div>
                {get_design_block block="settings_theme_general"}
            </div>
        </div>
    </div>

    {if !empty($css_variables)}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->settings_theme_color|escape}{if $settings->admin_theme} {$settings->admin_theme|escape}{/if}
                    <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_settings_theme_color|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_settings_theme_color|escape}">
                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                    </i>
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                {*Параметры элемента*}
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        {foreach $css_variables as $name => $value}
                            {$translation_name = str_replace('--', '', $name)}
                            {$translation_name = str_replace('-', '_', $translation_name)}
                            {if !empty($btr->getTranslation('settings_theme_'|cat:$translation_name))}
                                <div class="col-md-6 col-xs-12">
                                    <div class="variables_box">
                                        <div class="variables_box__left">
                                            <div class="heading_label">{$btr->getTranslation('settings_theme_'|cat:$translation_name)}</div>
                                        </div>
                                        <div class="variables_box__right">
                                            <div class="">
                                                <input type="color" class="fn_color theme_color" aria-label="{$btr->getTranslation('settings_theme_'|cat:$translation_name)|escape}">
                                                <input name="css_colors[{$name|escape}]" class="form-control" type="hidden" value="{$value|escape}" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        {/foreach}

                        <div class="col-lg-12 col-md-12 ">
                            <button type="submit" class="btn btn_small btn_blue float-md-right">
                                {include file='svg_icon.tpl' svgId='checked'}
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                        
                    </div>
                </div>
                {get_design_block block="settings_theme_css_colors"}
            </div>
        </div>
    </div>
    {/if}
</form>


<script type="text/javascript" src="design/js/tinymce_jq/tinymce.min.js"></script>
{literal}
    <script>

        $(function() {
            $(document).on('click', '.fn_remove_new', function() {
                $(this).closest('.fn_row').remove();
            });

            /* Серед змінних теми є не лише кольори - тінь лежить цілим значенням
               box-shadow. Такі значення інпут показати не може, тому вони просто
               лишаються недоторканими, поки користувач не вибере колір. */
            $('input.fn_color').each(function () {
                var raw = $(this).next('input').val();
                if (/^#[0-9a-f]{3}$/i.test(raw)) {
                    raw = raw.replace(/^#(.)(.)(.)$/, '#$1$1$2$2$3$3');
                }
                if (/^#[0-9a-f]{6}$/i.test(raw)) {
                    this.value = raw;
                }
            });

            $(document).on('input', 'input.fn_color', function () {
                $(this).next('input').val(this.value);
            });

        });
        
        
        $(function(){
            tinyMCE.init({
                selector: "textarea.editor_small",
                height: '100',
                plugins: ["code"],
                toolbar_items_size : 'small',
                menubar:'',
                toolbar1: "fontselect fontsizeselect | bold italic underline | alignleft aligncenter alignright alignjustify | forecolor backcolor | code",
                statusbar: true,
                font_formats: "Andale Mono=andale mono,times;"+
                "Arial=arial,helvetica,sans-serif;"+
                "Arial Black=arial black,avant garde;"+
                "Book Antiqua=book antiqua,palatino;"+
                "Comic Sans MS=comic sans ms,sans-serif;"+
                "Courier New=courier new,courier;"+
                "Georgia=georgia,palatino;"+
                "Helvetica=helvetica;"+
                "Impact=impact,chicago;"+
                "Open Sans=Open Sans,sans-serif;"+
                "Symbol=symbol;"+
                "Tahoma=tahoma,arial,helvetica,sans-serif;"+
                "Terminal=terminal,monaco;"+
                "Times New Roman=times new roman,times;"+
                "Trebuchet MS=trebuchet ms,geneva;"+
                "Verdana=verdana,geneva;"+
                "Webdings=webdings;"+
                "Wingdings=wingdings,zapf dingbats",
            });
        });
    </script>
{/literal}
