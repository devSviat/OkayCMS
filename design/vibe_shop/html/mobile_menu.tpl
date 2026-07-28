<ul class="top-nav">
    <li>
        <div class="">
            {if !empty({$settings->site_logo})}
            <a class="mobile__link " href="{if $controller=='MainController'}javascript:;{else}{url_generator route="main"}{/if}">
                <img src="{$rootUrl}/{$config->design_images}{$settings->site_logo}?v={$settings->site_logo_version}" alt="{$settings->site_name|escape}"/>
            </a>
            {/if}
        </div>
        <div class="d-flex align-items-center f_col">
            {if $user}
                <a class="account__link" href="{url_generator route="user"}">
                    {include file="svg.tpl" svgId="user"}
                    <span>{$user->name|escape}</span>
                </a>
            {else}
                <a class="account__link" rel="nofollow" href="{url_generator route='login'}"  title="{$lang->index_login}">
                    {include file="svg.tpl" svgId="user"}
                    <span class="account__login" data-language="index_login">{$lang->index_login}</span>
                </a>
            {/if}
        </div>
    </li>
</ul>
<ul class="second-nav">
    {if $controller != 'MainController'}
        <li>
            <a href="{url_generator route='main'}">
                {include file="svg.tpl" svgId="home_icon"}
                <span data-language="mobile_menu_home">{$lang->mobile_menu_home}</span>
            </a>
        </li>
    {/if}
    <li>
        <a>
            {include file="svg.tpl" svgId="catalog_icon"}
            <span data-language="mobile_menu_category">{$lang->mobile_menu_category}</span>
        </a>
        {function name=categories_tree4}
        {if $categories}
            <ul class="">
                {foreach $categories as $c}
                    {if $c->visible && ($c->has_products || $settings->show_empty_categories)}
                        {if $c->subcategories && $c->count_children_visible}
                            <li class="">
                                <a class="{if $category->id == $c->id} selected{/if}" href="{url_generator route='category' url=$c->url}" data-category="{$c->id}">
                                    {if $c->image}
                                        <span class="nav-icon">
                                            <img src="{$c->image|resize:20:20:false:$config->resized_categories_dir}" alt="{$c->name|escape}" />
                                        </span>
                                    {/if}
                                    <span>{$c->name|escape}</span>
                                </a>
                                {categories_tree4 categories=$c->subcategories level=$level + 1}
                            </li>
                        {else}
                            <li class="">
                                <a class="{if $category->id == $c->id} selected{/if}" href="{url_generator route='category' url=$c->url}" data-category="{$c->id}">
                                    {if $c->image}
                                    <span class="nav-icon">
                                            <img src="{$c->image|resize:20:20:false:$config->resized_categories_dir}" alt="{$c->name|escape}" />
                                        </span>
                                    {/if}
                                     <span>{$c->name|escape}</span>
                                </a>
                            </li>
                        {/if}
                    {/if}
                {/foreach}
            </ul>
        {/if}
        {/function}
        {categories_tree4 categories=$categories level=1}
    </li>
    <li>
        <a href="{url_generator route='wishlist'}">
            {include file="svg.tpl" svgId="heart"}
            <span data-language="wishlist_header">{$lang->wishlist_header}</span>
        </a>
    </li>
    <li>
        <a href="{url_generator route='comparison'}">
            {include file="svg.tpl" svgId="compare"}
            <span data-language="comparison_header">{$lang->comparison_header}</span>
        </a>
    </li>
</ul>

{$menu_mobile}

{* Currencies *}
{if $currencies|count > 1}
<ul class="currencies-nav">
    <li>
        <span>
            {include file="svg.tpl" svgId="coins"}
            <span class="vs-sr-only">{$lang->mobile_menu_currency|escape}: </span>
            <span>{$currency->name|escape}</span>
        </span>
        <ul class="">
            {foreach $currencies as $c}
            {if $c->enabled}
            <li>
                <a class="{if $currency->id== $c->id} active{/if}" href="#" onclick="document.location.href = '{url currency_id=$c->id}'">
                    <span class="">{$c->name} </span> <span class=""> ({$c->sign})</span>
                </a>
            </li>
            {/if}
            {/foreach}
        </ul>
    </li>
</ul>
{/if}

{if $languages|count > 1}
{$cnt = 0}
{foreach $languages as $ln}
{if $ln->enabled}
{$cnt = $cnt+1}
{/if}
{/foreach}
{if $cnt>1}
    <ul class="language-nav">
        <li>
            <span>
                {include file="svg.tpl" svgId="language"}
                <span>{$language->name|escape}</span>
            </span>
            <ul class="">
                {foreach $languages as $l}
                {if $l->enabled}
                <li>
                    <a class=" {if $language->id == $l->id} active{/if}"
                       href="{preg_replace('/^(.+)\/$/', '$1', $l->url)}">
                        {if is_file("{$config->lang_images_dir}{$l->label}.png")}
                        <img alt="{$l->current_name}" src="{("{$l->label}.png")|resize:20:20:false:$config->lang_resized_dir}" />
                        {/if}
                        <span class="">{$l->name}</span>
                        {*<span class="">{$l->label}</span>*}
                    </a>
                </li>
                {/if}
                {/foreach}
            </ul>
        </li>
    </ul>
{/if}
{/if}

{if $settings->site_phones || $settings->site_email}
<ul class="contact-nav">
    {if $settings->site_phones}
    {foreach $settings->site_phones as $phone}
    <li>
        <a class="phone" href="tel:{preg_replace('~[^0-9\+]~', '', $phone)}">
            {include file="svg.tpl" svgId="phone"}
            <span>{$phone|escape}</span>
        </a>
    </li>
    {/foreach}
    {/if}
    {if $settings->site_email}
    <li>
        <a class="email" href="mailto:{$settings->site_email|escape}">
            {include file="svg.tpl" svgId="mail"}
            <span>{$settings->site_email|escape}</span>
        </a>
    </li>
    {/if}
</ul>
{/if}



<ul class="bottom-nav">
    {foreach $settings->site_social_links as $social_link}
    {$social_domain = preg_replace('~(https?://)?(www\.)?([^\.]+)?\..*~', '$3', $social_link)}
    {if $social_domain}
    <li>
        <a href="{if !preg_match('~^https?://.*$~', $social_link)}https://{/if}{$social_link|escape}" target="_blank" rel="noreferrer" title="{$social_domain|escape}">
            <span>{$social_domain|escape}</span>
        </a>
    </li>
    {/if}
    {/foreach}
</ul>
