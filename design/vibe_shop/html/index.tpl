<!DOCTYPE html>
<html {if $language->href_lang} lang="{$language->href_lang|escape}"{/if} prefix="og: http://ogp.me/ns#">
<head>
    {* Meta data *}
    {get_design_block block="front_before_head_content"}
    {include "head.tpl"}
    {get_design_block block="front_after_head_content"}
</head>

<body class="d-flex flex-column {if $controller == 'MainController'}main_page{elseif $controller == 'CartController'}cart_page{else}other_page{/if}">
    {if !empty($counters['body_top'])}
        {foreach $counters['body_top'] as $counter}
        {$counter->code}
        {/foreach}
    {/if}

    {* Skip link. The first focusable node in the document - the counters above
       are <script>, and the header below repeats 30+ tab stops on every page.
       Points at .main, which carries the id and a -1 tabindex so the jump moves
       focus and not only the scroll position. *}
    <a class="vs-skip" href="#vs_main">
        <span data-language="index_skip_to_content">{$lang->index_skip_to_content}</span>
    </a>

    {if $block = {get_design_block block="front_start_body_content"} }
    <div>
        {$block}
    </div>
    {/if}
    {if $controller !== 'CartController'}
    <header class="vs-header">
        {if $is_mobile == false || $is_tablet == true}
        {* Utility bar: site pages, contacts, locale, account *}
        <div class="vs-header__utility hidden-md-down">
            <div class="container">
                <div class="vs-utility">
                    <nav class="vs-nav">
                        {$menu_header}
                    </nav>
                    <div class="vs-utility__aside">
                        {if $settings->site_phones}
                            {foreach $settings->site_phones as $phone}
                                <a class="vs-utility__link vs-utility__phone" href="tel:{preg_replace('~[^0-9\+]~', '', $phone)}">
                                    {include file="svg.tpl" svgId="phone"}
                                    <span>{$phone|escape}</span>
                                </a>
                            {/foreach}
                        {/if}
                        <a class="fn_callback vs-utility__link" href="#fn_callback" data-language="index_back_call">
                            {include file="svg.tpl" svgId="headset"}
                            <span>{$lang->index_back_call}</span>
                        </a>
                        <div class="vs-switcher">
                            {include file="switcher.tpl"}
                        </div>
                        <div id="account" class="vs-utility__account">
                            {include file="user_informer.tpl"}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {/if}
        {* Main bar: logo, catalogue, search, informers *}
        {* data-sticky-wrap: sticky.min.js takes the bar out of the flow with
           position:fixed and, without this, leaves nothing behind it - measured
           at 1440, the document lost 87px and everything below the header
           jumped up by exactly that the instant the page began to scroll. The
           attribute makes the library wrap the bar in a placeholder span it
           then sizes to the bar's own rectangle, so the space is kept. It is a
           documented option of the library, not a patch to it. *}
        <div class="vs-header__main {if $controller != 'MainController'}fn_header__sticky {/if}" data-margin-top="0" data-sticky-for="991" data-sticky-class="is-sticky" data-sticky-wrap>
            <div class="container">
                <div class="vs-header__bar">
                    {* Mobile menu button *}
                    <button type="button" class="fn_menu_switch vs-btn vs-btn--ghost vs-btn--icon vs-header__burger hidden-lg-up" aria-label="{$lang->index_mobile_menu|escape}">
                        {include file="svg.tpl" svgId="burger"}
                    </button>
                    {* Logo *}
                    <div class="vs-header__logo">
                        {if !empty({$settings->site_logo})}
                        <a class="vs-logo" href="{if $controller=='MainController'}javascript:;{else}{url_generator route='main'}{/if}">
                            {if strtolower(pathinfo($settings->site_logo, $smarty.const.PATHINFO_EXTENSION)) == 'svg'}
                                {$settings->site_logo|read_svg:$config->design_images}
                            {else}
                                <img src="{$rootUrl}/{$config->design_images|escape}{$settings->site_logo|escape}?v={$settings->site_logo_version|escape}" alt="{$settings->site_name|escape}"/>
                            {/if}
                        </a>
                        {/if}
                    </div>
                    {* Catalogue button *}
                    <button type="button" class="fn_catalog_switch vs-btn vs-btn--primary vs-catalog-btn hidden-md-down" aria-expanded="false">
                        {include file="svg.tpl" svgId="burger"}
                        <span data-language="index_categories">{$lang->index_categories}</span>
                        <span class="vs-catalog-btn__chevron">{include file="svg.tpl" svgId="chevron"}</span>
                    </button>
                    {* Search form *}
                    <form id="fn_search" class="fn_search_mob vs-search d-md-flex" action="{url_generator route='products'}">
                        <input class="fn_search vs-search__input" type="text" name="keyword" value="{$keyword|escape}" aria-label="search" data-language="index_search" placeholder="{$lang->index_search}"/>
                        <button class="vs-search__submit" aria-label="search" type="submit">{include file="svg.tpl" svgId="search"}</button>
                    </form>
                    <div class="vs-informers">
                        {* Mobile search toggle *}
                        <button type="button" class="fn_search_toggle vs-informer hidden-md-up" aria-label="{$lang->index_search|escape}">{include file="svg.tpl" svgId="search"}</button>
                        {* Wishlist informer *}
                        <div id="wishlist" class="vs-informers__slot vs-informers__slot--secondary">{include file="wishlist_informer.tpl"}</div>
                        {* Comparison informer *}
                        <div id="comparison" class="vs-informers__slot vs-informers__slot--secondary">{include "comparison_informer.tpl"}</div>
                        {* Cart informer*}
                        <div id="cart_informer" class="vs-informers__slot">{include file='cart_informer.tpl'}</div>
                    </div>
                </div>
                {* Categories menu *}
                {if $is_mobile == false || $is_tablet == true}
                    <nav class="fn_catalog_menu vs-catalog hidden-md-down">
                        {include file="desktop_categories.tpl"}
                    </nav>
                {/if}
            </div>
        </div>
    </header>
    {/if}

    {* Тіло сайту.
       <main> rather than <div>: the class is untouched, so every .main rule and
       every descendant selector still matches, and the page gains the landmark
       the skip link lands on. tabindex="-1" makes the landing programmatically
       focusable without adding a tab stop. *}
    <main id="vs_main" class="main" tabindex="-1">
        {* Include module banner *}
        {if !empty($global_banners)}
            <div class="container">
                <div class="{if $controller == 'MainController'}d-flex main_banner{/if}">
                    {$global_banners}
                </div>
            </div>
        {/if}

        {* Контент сайту *}
        {if $controller == "MainController" || $controller == "CartController" || (!empty($page) && $page->url == '404')}
            <div class="fn_ajax_content">
                {$content}
            </div>
        {else}
            <div class="container">
                {include file='breadcrumb.tpl'}
                <div class="fn_ajax_content">
                    {$content}
                </div>
            </div>
        {/if}

        {* Переваги магазину.
           The markup inside is the Banners module's, not ours - this wrapper is
           the only hook the theme has for restyling it, so it carries the class. *}
        {if !empty($banner_shortcode_advantage)}
            <div class="container vs-advantages">
                {$banner_shortcode_advantage}
            </div>
        {/if}
    </main>

    {* Кнопка вгору *}
    <div class="fn_to_top to_top"></div>

    <div>
        {get_design_block block="front_before_footer_content"}
    </div>

    {* Footer *}
    {if $controller != 'CartController'}
    <footer class="vs-footer">
        <div class="container">
            <div class="vs-footer__grid">
                {* Footer contacts *}
                <section class="vs-footer__col">
                    <h2 class="vs-footer__title">
                        <span data-language="index_contacts">{$lang->index_contacts}</span>
                        <button type="button" class="fn_switch_parent vs-footer__toggle hidden-lg-up down" aria-label="{$lang->index_contacts|escape}">{include file="svg.tpl" svgId="chevron"}</button>
                    </h2>
                    <div class="vs-footer__body">
                        {if $settings->site_phones}
                            {foreach $settings->site_phones as $phone}
                                <a class="vs-footer__contact" href="tel:{preg_replace('~[^0-9\+]~', '', $phone)}">
                                    {include file="svg.tpl" svgId="phone"}
                                    <span>{$phone|escape}</span>
                                </a>
                            {/foreach}
                        {/if}
                        {if $settings->site_email}
                            <a class="vs-footer__contact" href="mailto:{$settings->site_email|escape}">
                                {include file="svg.tpl" svgId="mail"}
                                <span>{$settings->site_email|escape}</span>
                            </a>
                        {/if}
                        {if $settings->site_working_hours}
                            <div class="vs-footer__contact vs-footer__contact--static">
                                {include file="svg.tpl" svgId="clock"}
                                {* A <div>, not a <span>: site_working_hours is a
                                   rich-text setting and ships as "<div>Режим
                                   роботи...<br><strong>...</strong></div>" in this
                                   install, so the span wrapped block-level content.
                                   The parent is display:flex, which blockifies
                                   either tag, so nothing moves - but an admin who
                                   writes a <p> or a second <div> in that field now
                                   gets valid markup and can style it as a block. *}
                                <div>{$settings->site_working_hours}</div>
                            </div>
                        {/if}
                        <a class="fn_callback vs-btn vs-btn--secondary vs-footer__callback" href="#fn_callback" data-language="index_back_call">
                            {include file="svg.tpl" svgId="headset"}
                            <span>{$lang->index_back_call}</span>
                        </a>
                    </div>
                </section>
                {* Main menu *}
                <section class="vs-footer__col">
                    <h2 class="vs-footer__title">
                        <span data-language="index_about_store">{$lang->index_about_store}</span>
                        <button type="button" class="fn_switch_parent vs-footer__toggle hidden-lg-up down" aria-label="{$lang->index_about_store|escape}">{include file="svg.tpl" svgId="chevron"}</button>
                    </h2>
                    <div class="vs-footer__body vs-footer__menu">
                        {$menu_footer}
                    </div>
                </section>
                {* Categories menu *}
                <section class="vs-footer__col">
                    <h2 class="vs-footer__title">
                        <span data-language="index_categories">{$lang->index_categories}</span>
                        <button type="button" class="fn_switch_parent vs-footer__toggle hidden-lg-up down" aria-label="{$lang->index_categories|escape}">{include file="svg.tpl" svgId="chevron"}</button>
                    </h2>
                    <div class="fn_view_content vs-footer__body vs-footer__menu">
                        {$c_count = 0}
                        {foreach $categories as $c}
                            {if $c->visible && ($c->has_products || $settings->show_empty_categories)}
                                {$c_count = $c_count+1}
                                <div class="vs-footer__menu_item {if $c_count > 5}closed{else}opened{/if}">
                                    <a href="{url_generator route='category' url=$c->url}">{$c->name|escape}</a>
                                </div>
                            {/if}
                        {/foreach}
                        {if $c_count > 5}
                            <a class="fn_view_all vs-footer__more" href="">{$lang->filter_view_show|escape}</a>
                        {/if}
                    </div>
                </section>
                {* Subscribing *}
                <section class="vs-footer__col">
                    <h2 class="vs-footer__title">
                        <span data-language="subscribe_heading">{$lang->subscribe_heading}</span>
                        <button type="button" class="fn_switch_parent vs-footer__toggle hidden-lg-up down" aria-label="{$lang->subscribe_heading|escape}">{include file="svg.tpl" svgId="chevron"}</button>
                    </h2>
                    <div id="subscribe_container" class="vs-footer__body">
                        <p class="vs-footer__promo">
                            <span data-language="subscribe_promotext">{$lang->subscribe_promotext}</span>
                        </p>
                        <form class="fn_subscribe_form fn_validate_subscribe vs-subscribe" method="post">
                            <div class="vs-subscribe__row">
                                <input type="hidden" name="subscribe" value="1"/>
                                <input class="vs-field vs-subscribe__input" aria-label="subscribe" type="email" name="subscribe_email" value="" data-format="email" placeholder="{$lang->form_email}"/>
                                <button class="vs-btn vs-btn--primary" type="submit"><span data-language="subscribe_button">{$lang->subscribe_button}</span></button>
                            </div>
                            <div class="fn_subscribe_success vs-note vs-note--ok hidden">
                                <span data-language="subscribe_sent">{$lang->index_subscribe_sent}</span>
                            </div>
                            <div class="fn_subscribe_error vs-note vs-note--error hidden">
                                <span class="fn_error_text"></span>
                            </div>
                        </form>
                        {* Social buttons *}
                        {if $site_social}
                            <div class="vs-social">
                                <span class="vs-social__label" data-language="index_in_networks">{$lang->index_in_networks}</span>
                                <div class="vs-social__list">
                                    {* Glyph plus a clipped name, not the name as the label: the
                                       title attribute states the network on hover and .vs-sr-only
                                       states it to a screen reader, so the button keeps an
                                       accessible name that a bare icon link would not have. *}
                                    {foreach $site_social as $social}
                                        <a class="vs-social__link" rel="noreferrer" href="{if !preg_match('~^https?://.*$~', $social.url)}https://{/if}{$social.url|escape}" target="_blank" title="{$social.domain|escape}">
                                            {include file="social_icon.tpl" domain=$social.domain}
                                            <span class="vs-sr-only">{$social.domain|escape}</span>
                                        </a>
                                    {/foreach}
                                </div>
                            </div>
                        {/if}
                    </div>
                </section>
            </div>
        </div>
        <div class="vs-footer__base">
            <div class="container">
                <div class="vs-footer__baseline">
                    {* Copyright *}
                    <div class="vs-copyright">
                        <span>© {$smarty.now|date_format:"%Y"}</span>
                        <span data-language="index_copyright">{$lang->index_copyright}</span>
                        <a class="vs-copyright__mark" href="https://okay-cms.com" rel="noreferrer" target="_blank" title="OkayCms">{include file="svg.tpl" svgId="okaycms"}</a>
                    </div>
                    {* Payments *}
                    <ul class="vs-payments">
                        {foreach $payment_methods as $payment_method}
                            {if !$payment_method->image}{continue}{/if}
                            <li class="vs-payments__item" title="{$payment_method->name|escape}">
                                <picture>
                                    {if $settings->support_webp}
                                        <source type="image/webp" data-srcset="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir|webp}">
                                    {/if}
                                    <source data-srcset="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir}">
                                    <img class="lazy" data-src="{$payment_method->image|resize:80:30:false:$config->resized_payments_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="{$payment_method->name|escape}" title="{$payment_method->name|escape}"/>
                                </picture>
                            </li>
                        {/foreach}
                    </ul>
                </div>
            </div>
        </div>
    </footer>
    {/if}

    <div class="fn_mobile_menu hidden">
        {include file="mobile_menu.tpl"}
    </div>

    {* Glyphs for the share pills. jsSocials builds those buttons in JS and its own
       logo is a Font Awesome <i>, which this theme has no font for - so scripts.tpl
       lifts the matching glyph out of here instead. A bank rather than data URIs in
       the stylesheet: svg.tpl stays the one place the art lives, and the list is
       built from the same setting the buttons are, so it can never fall behind it. *}
    {if $settings->sj_shares}
        <div id="fn_share_icons" class="hidden" aria-hidden="true">
            {foreach $settings->sj_shares as $vsShare}
                <span data-vs-share-icon="{$vsShare|escape}">{include file="social_icon.tpl" domain=$vsShare}</span>
            {/foreach}
        </div>
    {/if}

    {* Форма зворотного дзвінка *}
    {include file='callback.tpl'}
    
    {* Спливний кошик *}
    {if $route_name != 'cart'}
    <div id="fn_pop_up_cart_wrap" class="popup_animated" style="display: none;">
        <div id="fn_pop_up_cart" class="popup_animated">
            {include file='pop_up_cart.tpl'}
        </div>
    </div>
    {/if}

    {* Повідомлення про додавання до порівняння *}
    <div id="fn_compare_confirm" class="popup_bg popup_animated"  style="display: none;">
        <div class="popup_confirm__title">
            {include file="svg.tpl" svgId="success_icon"}
            <span data-language="popup_add_to_compare">{$lang->popup_add_to_compare}</span>
        </div>
    </div>

    {* Повідомлення про додавання до обраного *}
    <div id="fn_wishlist_confirm" class="popup_bg popup_animated" style="display: none;">
        <div class="popup_confirm__title">
            {include file="svg.tpl" svgId="success_icon"}
            <span data-language="popup_add_to_wishlist">{$lang->popup_add_to_wishlist}</span>
        </div>
    </div>
    
    <script>ut_tracker.start('parsing:body_bottom:scripts');</script>

    {if $controller == 'ProductController' || $controller == "BlogController"}
        {js file="jssocials.min.js" dir='js_libraries/js_socials/js' defer=true}
    {/if}

    <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js" integrity="sha512-uURl+ZXMBrF4AwGaWmEetzrd+J5/8NRkWAvJx5sbPSSuOb0bZLqf+tOzniObO00BjHa/dD7gub9oCGMLPQHtQA==" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js" integrity="sha512-0QDLUJ0ILnknsQdYYjG7v2j8wERkKufvjBNmng/EdR/s/SE7X8cQ9y0+wMzuQT0lfXQ/NhG+zhmHNOWTUS3kMA==" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.devbridge-autocomplete/1.4.11/jquery.autocomplete.min.js" integrity="sha512-uxCwHf1pRwBJvURAMD/Gg0Kz2F2BymQyXDlTqnayuRyBFE7cisFCh2dSb1HIumZCRHuZikgeqXm8ruUoaxk5tA==" crossorigin="anonymous"></script>

    {$ok_footer}

    {if $controller == 'ProductController' || $controller == "BlogController"}
        {css file='jssocials.css' dir='js_libraries/js_socials/css'}
        {if $settings->social_share_theme}
            {css file="jssocials-theme-{$settings->social_share_theme|escape}.css" dir='js_libraries/js_socials/css'}
        {/if}
    {/if}
    <script>ut_tracker.end('parsing:body_bottom:scripts');</script>

    {if !empty($counters['body_bottom'])}
        <script>ut_tracker.start('parsing:body_bottom:counters');</script>
        {foreach $counters['body_bottom'] as $counter}
            {$counter->code}
        {/foreach}
        <script>ut_tracker.end('parsing:body_bottom:counters');</script>
    {/if}

    <script>ut_tracker.end('parsing:page');</script>

    <div>
        {get_design_block block="front_after_footer_content"}
    </div>

    {if $debug_bar_renderer}
        {$debug_bar_inline_assets}
        {$debug_bar_renderer->render()}
    {/if}
</body>
</html>
