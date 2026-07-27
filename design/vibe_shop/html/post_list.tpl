{* One article card. Self-contained: the grid that hosts it (.vs-posts) only
   supplies the track, so the same card works on the homepage, the blog index
   and an author page without a per-page column wrapper. *}
<article class="vs-post-card">
    <div class="vs-post-card__media">
        <a class="vs-post-card__media_link" href="{url_generator route='post' url=$post->url}" tabindex="-1" aria-hidden="true">
            {if $post->image}
                <picture>
                    {if $settings->support_webp}
                        <source class="lazy" type="image/webp" data-srcset="{$post->image|resize:340:240:false:$config->resized_blog_dir:center:center|webp}" media="(max-width: 440px)" srcset="{$rootUrl}/design/{get_theme}/images/xloading.svg">
                        <source class="lazy" type="image/webp" data-srcset="{$post->image|resize:380:240:false:$config->resized_blog_dir:center:center|webp}" srcset="{$rootUrl}/design/{get_theme}/images/xloading.svg">
                    {/if}
                    <source class="lazy" data-srcset="{$post->image|resize:340:240:false:$config->resized_blog_dir:center:center}" media="(max-width: 440px)" srcset="{$rootUrl}/design/{get_theme}/images/xloading.svg">
                    <source class="lazy" data-srcset="{$post->image|resize:380:240:false:$config->resized_blog_dir:center:center}" srcset="{$rootUrl}/design/{get_theme}/images/xloading.svg">
                    <img class="lazy" data-src="{$post->image|resize:380:240:false:$config->resized_blog_dir:center:center}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt="{$post->name|escape}"/>
                </picture>
            {else}
                <span class="vs-post-card__no_image">
                    {include file="svg.tpl" svgId="no_image"}
                </span>
            {/if}
        </a>
        {if !empty($post->categories)}
            <div class="vs-post-card__labels">
                {foreach $post->categories as $c}
                    {if $c->visible}
                        <a class="vs-post-card__label" href="{url_generator route='blog_category' url=$c->url}">{$c->name|escape}</a>
                    {/if}
                {/foreach}
            </div>
        {/if}
    </div>

    <div class="vs-post-card__body">
        {* The card is included from four places at two different depths: under a
           section <h2> on the home page, the author page and the product page,
           and directly under the page <h1> on the blog list, where a fixed <h3>
           skipped a level. The caller passes the level it sits at; <h3> stays
           the default because three of the four callers want it. *}
        {$cardHeading = $cardHeading|default:'h3'}
        <{$cardHeading} class="vs-post-card__title">
            <a class="vs-post-card__link" href="{url_generator route='post' url=$post->url}" data-post="{$post->id}">{$post->name|escape}</a>
        </{$cardHeading}>

        <div class="vs-post-card__meta">
            <span class="vs-post-card__meta_item">
                {include file="svg.tpl" svgId="calendar_icon"}
                <span>{$post->date|date:"d m Y"}</span>
            </span>
            <span class="vs-post-card__meta_item" title="{$lang->blog_count_comments}">
                {include file="svg.tpl" svgId="chat_icon"}
                <span>{if $post->comments_count}{$post->comments_count}{else}0{/if}</span>
            </span>
            {if $post->read_time > 0}
                <span class="vs-post-card__meta_item" title="{$lang->blog_time_read} {$post->read_time} {$post->read_time|plural:$lang->blog_time_read_minute_1:$lang->blog_time_read_minute_2:$lang->blog_time_read_minute_3}">
                    {include file="svg.tpl" svgId="time_read_icon"}
                    <span>{$post->read_time} {$post->read_time|plural:$lang->blog_time_read_minute_1:$lang->blog_time_read_minute_2:$lang->blog_time_read_minute_3}</span>
                </span>
            {/if}
        </div>

        {if $post->annotation}
            <p class="vs-post-card__annotation">{$post->annotation|strip_tags|escape:'html':'UTF-8':false}</p>
        {/if}
    </div>

    {if !empty($post->author)}
        <div class="vs-post-card__foot">
            <span class="vs-post-card__avatar">
                {if $post->author->image}
                    <picture>
                        {if $settings->support_webp}
                            <source type="image/webp" data-srcset="{$post->author->image|resize:24:24:false:$config->resized_authors_dir:center:center|webp}">
                        {/if}
                        <source data-srcset="{$post->author->image|resize:24:24:false:$config->resized_authors_dir:center:center}">
                        <img class="lazy" data-src="{$post->author->image|resize:24:24:false:$config->resized_authors_dir:center:center}" src="{$rootUrl}/design/{get_theme}/images/xloading.svg" alt=""/>
                    </picture>
                {else}
                    {include file="svg.tpl" svgId="avatar_icon"}
                {/if}
            </span>
            {if $post->author->visible}
                <a class="vs-post-card__author" href="{url_generator route='author' url=$post->author->url}">{$post->author->name|escape}</a>
            {else}
                <span class="vs-post-card__author">{$post->author->name|escape}</span>
            {/if}
        </div>
    {/if}
</article>
