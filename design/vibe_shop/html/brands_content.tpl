{* The list of the brands *}
{if $brands}
    <ul class="vs-brands">
        {foreach $brands as $b}
            <li class="vs-brands__item">
                <a class="vs-brands__link" data-brand="{$b->id}" href="{url_generator route='brand' url=$b->url keyword=$keyword}" title="{$b->name|escape}">
                    {if $b->image}
                        <picture>
                            {if $settings->support_webp}
                                <source type="image/webp" data-srcset="{$b->image|resize:120:100:false:$config->resized_brands_dir}.webp">
                            {/if}
                            <source data-srcset="{$b->image|resize:120:100:false:$config->resized_brands_dir}">
                            <img class="brand_img lazy" data-src="{$b->image|resize:120:100:false:$config->resized_brands_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="{$b->name|escape}"/>
                        </picture>
                    {else}
                        <span class="vs-brands__name">{$b->name|escape}</span>
                    {/if}
                </a>
            </li>
        {/foreach}
    </ul>
{else}
    <div class="vs-empty vs-empty--center">
        <span class="vs-empty__icon">{include file="svg.tpl" svgId="no_image"}</span>
        <p class="vs-empty__title" data-language="brands_not_found">{$lang->brands_not_found}</p>
    </div>
{/if}
