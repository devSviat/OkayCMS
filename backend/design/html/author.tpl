{if $author->id}
    {$meta_title = $author->name scope=global}
{else}
    {$meta_title = $btr->author_new scope=global}
{/if}

{*Название страницы*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {if !$author->id}
                    {$btr->author_add|escape}
                {else}
                    {$author->name|escape}
                {/if}
            </div>
            {if $author->id}
                <div class="box_btn_heading">
                    <a class="btn btn_small btn-info add" target="_blank" href="{url_generator route="author" url=$author->url absolute=1}">
                    {include file='svg_icon.tpl' svgId='icon_desktop'}
                    <span>{$btr->general_open|escape}</span>
                    </a>
                </div>
            {/if}
        </div>
    </div>
</div>

{*Вывод успешных сообщений*}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success=='added'}
                            {$btr->author_added|escape}
                        {elseif $message_success=='updated'}
                            {$btr->author_updated|escape}
                        {else}
                            {$message_success|escape}
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

{*Вывод ошибок*}
{if $message_error}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--error">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_error=='url_exists'}
                            {$btr->author_exists|escape}
                        {elseif $message_error=='global_url_exists'}
                            {$btr->global_url_exists|escape}
                        {elseif $message_error=='empty_name'}
                            {$btr->general_enter_title|escape}
                        {elseif $message_error == 'empty_url'}
                            {$btr->general_enter_url|escape}
                        {else}
                            {$message_error|escape}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

{*Главная форма страницы*}
<form method="post" enctype="multipart/form-data" class="fn_fast_button">
    <input type=hidden name="session_id" value="{$smarty.session.id}">
    <input type="hidden" name="lang_id" value="{$lang_id}" />

    <div class="row">
        <div class="col-xs-12">
            <div class="boxed">
                {*Название элемента сайта*}
                <div class="row d_flex">
                    <div class="col-lg-9 col-md-9 col-sm-12">
                        <div class="fn_step-1">
                            <div class="heading_label heading_label--required">
                                <span>{$btr->index_name|escape}</span>
                            </div>
                            <div class="form-group">
                                <input class="form-control" name="name" type="text" value="{$author->name|escape}"/>
                                <input name="id" type="hidden" value="{$author->id|escape}"/>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-12 col-lg-6 col-md-10">
                                <div class="fn_step-2 form-group">
                                    <div class="input-group input-group--dabbl">
                                        <span class="input-group-addon input-group-addon--left">URL</span>
                                        <input name="url" class="fn_meta_field form-control fn_url {if $author->id}fn_disabled{/if}" {if $author->id}readonly=""{/if} type="text" value="{$author->url|escape}" />
                                        <input type="checkbox" id="block_translit" class="hidden" value="1" {if $author->id}checked=""{/if}>
                                        <span class="input-group-addon fn_disable_url">
                                            {if $author->id}
                                                {include file='svg_icon.tpl' svgId='lock_closed'}
                                            {else}
                                                {include file='svg_icon.tpl' svgId='lock_open'}
                                            {/if}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-12 col-lg-6 col-md-10">
                                <div class="form-group">
                                    <input class="form-control" name="position_name" type="text" value="{$author->position_name|escape}" placeholder="{$btr->author_position_name}"/>
                                </div>
                            </div>
                        </div>
                        {get_design_block block="author_general"}
                    </div>
                    <div class="col-lg-3 col-md-3 col-sm-12">
                        <div class="fn_step-3 activity_of_switch">
                            <div class="activity_of_switch_item">
                                <div class="okay_switch clearfix">
                                    <label class="switch_label">
                                        {$btr->author_enable|escape}
                                        <i class="fn_tooltips fn_tip_wide hint-bottom-right-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_general_enable_author|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_general_enable_author|escape}">
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </label>
                                    <label class="switch switch-default">
                                        <input class="switch-input" name="visible" value='1' type="checkbox" {if $author->visible}checked=""{/if}/>
                                        <span class="switch-label"></span>
                                        <span class="switch-handle"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {*Дополнительные настройки*}
    {$switch_checkboxes = {get_design_block block="author_switch_checkboxes"}}
    {if !empty($switch_checkboxes)}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed fn_toggle_wrap ">
                <div class="heading_box">
                    {$btr->general_additional_settings|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="activity_of_switch activity_of_switch--box_settings">
                        {$switch_checkboxes}
                    </div>
                </div>
            </div>
        </div>
    </div>
    {/if}

    {*Параметры элемента*}
    <div class="row">
        <div class="col-lg-4 col-md-12 pr-0">
            <div class="fn_step-4 boxed fn_toggle_wrap min_height_270px">
                <div class="heading_box">
                    {$btr->general_image|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <ul class="author_images_list">
                        <li class="author_image_item fn_image_block">
                            {if $author->image}
                            <input type="hidden" class="fn_accept_delete" name="delete_image" value="">
                                <div class="fn_parent_image">
                                    <div class="author_image image_wrapper fn_image_wrapper text-xs-center">
                                        <a href="javascript:;" class="fn_delete_item remove_image"></a>
                                        <img src="{$author->image|resize:300:120:false:$config->resized_authors_dir}" alt="" />
                                    </div>
                                </div>
                            {else}
                                <div class="fn_parent_image"></div>
                            {/if}
                            <div class="fn_upload_image dropzone_block_image {if $author->image} hidden{/if}">

                                {include file='svg_icon.tpl' svgId='upload'}
                                <input class="dropzone_image" name="image" type="file" />
                            </div>
                            <div class="author_image image_wrapper fn_image_wrapper fn_new_image text-xs-center hidden">
                                <a href="javascript:;" class="fn_delete_item remove_image"></a>
                                <img src="" alt="" />
                            </div>
                        </li>
                    </ul>
                </div>
                {get_design_block block="author_image"}
            </div>
        </div>
        <div class="col-lg-8 col-md-12 pr-0 ">
            <div class="fn_step-11 boxed fn_toggle_wrap min_height_270px">
                <div class="heading_box">
                    {$btr->author_socials|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    {* Іконка соцмережі обирається за доменом, який ввів користувач,
                       тож потрібна і мапа, і фолбек на невідомий домен. *}
                    {$social_icons = [
                        'facebook'  => 'brand_facebook',
                        'instagram' => 'brand_instagram',
                        'twitter'   => 'brand_twitter',
                        'x'         => 'brand_twitter',
                        'youtube'   => 'brand_youtube',
                        'telegram'  => 'brand_telegram',
                        't'         => 'brand_telegram',
                        'linkedin'  => 'brand_linkedin',
                        'tiktok'    => 'brand_tiktok',
                        'pinterest' => 'brand_pinterest'
                    ] scope=global}
                    {* Джерело для клонування з JS: та сама розмітка, що й на сервері,
                       аби SVG не довелося дублювати ще й у скрипті. *}
                    <div class="fn_social_icon_source hidden">
                        {foreach $social_icons as $domain => $icon_id}
                            <span data-domain="{$domain|escape}">{include file='svg_icon.tpl' svgId=$icon_id}</span>
                        {/foreach}
                        <span data-domain="">{include file='svg_icon.tpl' svgId='brand_unknown'}</span>
                    </div>
                    <div class="features_wrap fn_socials_wrap">
                        {foreach $socials as $social}
                        <div class="fn_row form-group">
                            <div class="input-group input-group--dabbl">
                                    <span class="fn_social_image input-group-addon input-group-addon--left">
                                        {include file='svg_icon.tpl' svgId=$social_icons[$social.domain|default:'']|default:'brand_unknown'}
                                    </span>
                                <input class="fn_social_input form-control" type="text" name="socials[][url]" value="{$social.url|escape}"/>
                                <button type="button" class="input-group-addon fn_delete_social">
                                    {include file='svg_icon.tpl' svgId='trash'}
                                </button>
                            </div>
                        </div>
                        {/foreach}
                        <div class="fn_new_social fn_row form-group">
                            <div class="input-group input-group--dabbl">
                                <span class="fn_social_image input-group-addon input-group-addon--left">{include file='svg_icon.tpl' svgId='brand_unknown'}</span>
                                <input class="fn_social_input form-control" type="text" name="socials[][url]" value=""/>
                                <button type="button" class="input-group-addon fn_delete_social">
                                    {include file='svg_icon.tpl' svgId='trash'}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="box_btn_heading mt-q">
                        <button type="button" class="btn btn_mini btn-secondary fn_add_social">
                            {include file='svg_icon.tpl' svgId='plus'}
                            <span>{$btr->author_add_codial|escape}</span>
                        </button>
                    </div>
                </div>
                {get_design_block block="product_features"}
            </div>
        </div>

    </div>

    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="fn_step-5 boxed fn_toggle_wrap min_height_230px">
                <div class="heading_box">
                    {$btr->general_metatags|escape}
                    <i class="fn_tooltips fn_tip_wide hint-bottom-left-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_general_metatags|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_general_metatags|escape}">
                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                    </i>
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card row">
                    <div class="col-lg-6 col-md-6">
                        <div class="heading_label" >Meta-title <span id="fn_meta_title_counter"></span>
                            <i class="fn_tooltips fn_tip_wide hint-bottom-left-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_meta_title|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_meta_title|escape}">
                                {include file='svg_icon.tpl' svgId='icon_tooltips'}
                            </i>
                        </div>
                        <input name="meta_title" class="form-control fn_meta_field mb-h" type="text" value="{$author->meta_title|escape}" />
                        <div class="heading_label" >Meta-keywords
                            <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_meta_keywords|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_meta_keywords|escape}">
                                {include file='svg_icon.tpl' svgId='icon_tooltips'}
                            </i>
                        </div>
                        <input name="meta_keywords" class="form-control fn_meta_field mb-h" type="text" value="{$author->meta_keywords|escape}" />
                    </div>
                    <div class="col-lg-6 col-md-6 pl-0">
                        <div class="heading_label" >Meta-description <span id="fn_meta_description_counter"></span>
                            <i class="fn_tooltips fn_tip_wide hint-bottom-middle-t-white-s-small-mobile hint-anim" data-hint="{$btr->tooltip_meta_description|escape}" tabindex="0" role="img" aria-label="{$btr->tooltip_meta_description|escape}">
                                {include file='svg_icon.tpl' svgId='icon_tooltips'}
                            </i>
                        </div>
                        <textarea name="meta_description" class="form-control okay_textarea fn_meta_field">{$author->meta_description|escape}</textarea>
                    </div>
                </div>
                {get_design_block block="author_meta"}
            </div>
        </div>
    </div>
    
    {$block = {get_design_block block="author_custom_block"}}
    {if !empty($block)}
        <div class="row custom_block">
            {$block}
        </div>
    {/if}

    {*Описание элемента*}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="fn_step-6 boxed match fn_toggle_wrap tabs">
                <div class="heading_tabs">
                    <div class="tab_navigation">
                        <a href="#tab1" class="tab_navigation_link">{$btr->general_full_description|escape}</a>
                    </div>
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;" ><span class="fn_icon_arrow">{include file='svg_icon.tpl' svgId='chevron_down'}</span></a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="tab_container">
                        <div id="tab1" class="tab">
                            <textarea name="description" id="fn_editor" class="editor_large fn_editor_class">{$author->description|escape}</textarea>
                        </div>
                    </div>
                </div>
                <div class="row">
                   <div class="col-lg-12 col-md-12 mt-1">
                        <button type="submit" class="fn_step-7 btn btn_small btn_blue float-md-right">
                            {include file='svg_icon.tpl' svgId='checked'}
                            <span>{$btr->general_apply|escape}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

{* Подключаем Tiny MCE *}
{include file='tinymce_init.tpl'}
{* On document load *}

<script>
    $(window).on("load", function() {
        // Добавление соц. сети
        var new_social = $(".fn_new_social").clone(false);
        $(".fn_new_social").remove();
        
        $(document).on("click", ".fn_delete_social", function () {
            $(this).closest(".fn_row ").fadeOut(200, function () {
                $(this).remove();
            });
        });
        
        $(document).on("click", ".fn_add_social", function () {
            var new_social_clone = new_social.clone(true);
            new_social_clone.removeClass("hidden").removeClass("fn_new_social");
            $(".fn_socials_wrap").append(new_social_clone);
            return false;
        });
    });

    $(document).on("change keyup", ".fn_social_input", function () {
        let domain = $(this).val().replace(/^(https?:\/\/)?(www\.)?([^.]+)?\..*$/, '$3'),
            social_image_wrap = $(this).closest('.fn_row').find('.fn_social_image');

        var source = $(".fn_social_icon_source [data-domain='" + domain + "']");
        if (!source.length) {
            source = $(".fn_social_icon_source [data-domain='']");
        }
        social_image_wrap.html(source.html());
    });
    
</script>
