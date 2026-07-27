<!-- Author page -->

<div class="vs-blog">
    <div class="vs-blog__layout">
        <div class="vs-blog__main">
            <header class="vs-author-hero">
                <div class="vs-author-hero__media">
                    {if $author->image}
                        <a data-fancybox="author_image" href="{$author->image|resize:800:800:false:$config->resized_authors_dir}">
                            <picture>
                                {if $settings->support_webp}
                                    <source type="image/webp" data-srcset="{$author->image|resize:320:500:false:$config->resized_authors_dir|webp}">
                                {/if}
                                <source data-srcset="{$author->image|resize:320:500:false:$config->resized_authors_dir}">
                                <img class="lazy" data-src="{$author->image|resize:320:500:false:$config->resized_authors_dir}" src="{$rootUrl}/design/{get_theme}/images/xloading.gif" alt="{$author->name|escape}"/>
                            </picture>
                        </a>
                    {else}
                        <span class="vs-author__no_image">
                            {include file="svg.tpl" svgId="comment-user_icon"}
                        </span>
                    {/if}
                </div>
                <div class="vs-author-hero__body">
                    <h1 class="vs-author-hero__name">
                        <span data-author="{$author->id}">{$h1|escape}</span>
                    </h1>
                    {if $author->position_name}
                        <p class="vs-author-hero__position">{$author->position_name|escape}</p>
                    {/if}
                    {if is_array($author->socials)}
                        <div class="vs-socials">
                            {foreach $author->socials as $social}
                                <a class="fn_social_image vs-socials__link" rel="noreferrer" href="{if !preg_match('~^https?://.*$~', $social.url)}https://{/if}{$social.url|escape}" target="_blank" title="{$social.domain|escape}">
                                    <span>{$social.domain|escape}</span>
                                </a>
                            {/foreach}
                        </div>
                    {/if}
                    {if $description}
                        <div class="author_card__description vs-prose vs-author-hero__about">{$description}</div>
                    {/if}
                </div>
            </header>

            <section class="vs-section">
                <h2 class="vs-section__title">
                    <span data-language="author_posts">{$lang->author_posts}</span>
                </h2>
                {if !empty($posts)}
                    <div class="vs-posts">
                        {foreach $posts as $post}
                            {include 'post_list.tpl'}
                        {/foreach}
                    </div>
                    {* Pagination *}
                    <div class="products_pagination">
                        {include file='pagination.tpl'}
                    </div>
                {else}
                    <div class="vs-empty vs-empty--center">
                        <span class="vs-empty__icon">{include file="svg.tpl" svgId="description_icon"}</span>
                        <p class="vs-empty__title" data-language="products_not_found">{$lang->products_not_found}</p>
                    </div>
                {/if}
            </section>
        </div>

        {* Sidebar with blog *}
        <aside id="vs_blog_rail" class="fn_mobile_toogle vs-filters vs-sheet vs-blog__aside" aria-label="{$lang->blog_catalog|escape}">
            <div class="vs-filters__bar">
                <span class="vs-filters__heading" data-language="blog_catalog">{$lang->blog_catalog}</span>
                <button type="button" class="vs-btn vs-btn--ghost vs-btn--icon vs-filters__close" data-vs-sheet-close aria-label="{$lang->mobile_filter_close|escape}">
                    {include file="svg.tpl" svgId="close"}
                </button>
            </div>
            <div class="vs-filters__scroll">
                {include 'blog_sidebar.tpl'}
            </div>
        </aside>
    </div>
</div>

<div class="vs-sheet__backdrop"></div>
