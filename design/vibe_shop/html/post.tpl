<!-- Post page -->

<div class="vs-blog vs-post">
    <div class="vs-blog__layout">
        {* Content with post *}
        <article class="vs-blog__main vs-post__main">
            {* Below 992px the rail is a bottom sheet with no other way in - this
               is its only trigger, and without it the blog categories and the
               subscribe form are unreachable on a phone. Same contract as
               blog.tpl and brands.tpl: data-vs-sheet-open names the sheet by id
               and vibeSheet owns every open/close path, so it deliberately does
               NOT carry fn_switch_mobile_filter. *}
            <div class="vs-blog__toolbar">
                <button type="button" class="vs-btn vs-btn--secondary vs-filters__open hidden-lg-up" data-vs-sheet-open="vs_blog_rail" aria-controls="vs_blog_rail" aria-expanded="false">
                    {include file="svg.tpl" svgId="catalog_icon"}
                    <span data-language="blog_catalog">{$lang->blog_catalog}</span>
                </button>
            </div>

            <header class="vs-post__masthead">
                {if !empty($post->categories)}
                    <div class="vs-post__labels">
                        {foreach $post->categories as $c}
                            {if $c->visible}
                                <a class="vs-post__label" href="{url_generator route='blog_category' url=$c->url}">{$c->name|escape}</a>
                            {/if}
                        {/foreach}
                    </div>
                {/if}

                <h1 class="vs-post__title">
                    <span data-post="{$post->id}">{$h1|escape}</span>
                </h1>

                <div class="vs-post__meta">
                    {* Article author *}
                    {if $post->author}
                        <span class="vs-post__meta_item" title="{$lang->blog_author}">
                            <span class="vs-post__avatar">
                                {if $post->author->image}
                                    <img src="{$post->author->image|resize:30:30:false:$config->resized_authors_dir:center:center}" alt="">
                                {else}
                                    {include file="svg.tpl" svgId="avatar_icon"}
                                {/if}
                            </span>
                            {if $post->author->visible}
                                <a href="{url_generator route='author' url=$post->author->url}">{$post->author->name|escape}</a>
                            {else}
                                <span>{$post->author->name|escape}</span>
                            {/if}
                        </span>
                    {/if}
                    {* Post date *}
                    {if $post->date}
                        <span class="vs-post__meta_item" title="{$lang->blog_date_public}">
                            {include file="svg.tpl" svgId="calendar_icon"}
                            <span>{$post->date|date:"d m Y"}</span>
                        </span>
                    {/if}
                    {* Reading time *}
                    {if $post->read_time > 0}
                        <span class="vs-post__meta_item" title="{$lang->blog_time_read}">
                            {include file="svg.tpl" svgId="time_read_icon"}
                            <span>{$post->read_time} {$post->read_time|plural:$lang->blog_time_read_minute_1:$lang->blog_time_read_minute_2:$lang->blog_time_read_minute_3}</span>
                        </span>
                    {/if}
                    {* Counts of comments. count() on null is a fatal in PHP 8. *}
                    <a class="vs-post__meta_item" href="#comments" title="{$lang->blog_count_comments}">
                        {include file="svg.tpl" svgId="chat_icon"}
                        <span>{if !empty($comments)}{$comments|count}{else}0{/if}</span>
                    </a>
                    {* Update date *}
                    {if $post->updated_date > 0}
                        <span class="vs-post__meta_item">
                            {include file="svg.tpl" svgId="update_date_icon"}
                            <span class="">{$lang->blog_update_date} {$post->updated_date|date:"d m Y"}</span>
                        </span>
                    {/if}
                </div>

                {if $post->image}
                    <div class="vs-post__cover">
                        <img src="{$post->image|resize:1100:600:false:$config->resized_blog_dir}" alt="{$post->name|escape}"/>
                    </div>
                {/if}
            </header>

            {* Table contents *}
            {if !empty($table_of_content)}
                <nav class="vs-toc vs-post__toc" aria-label="{$lang->blog_table_contents|escape}">
                    <div class="vs-toc__title">{$lang->blog_table_contents}</div>
                    <ol class="vs-post__toc_list">
                        {foreach $table_of_content as $content_item}
                            <li style="margin-left: {$content_item.header_level*15-15}px">
                                <a class="fn_ancor_post" href="{$content_item.url|escape}">{$content_item.anchor_text|escape}</a>
                            </li>
                        {/foreach}
                    </ol>
                </nav>
            {/if}

            {* Post content. Admin-authored WYSIWYG: styled defensively. *}
            <div class="block__description vs-prose vs-post__body">
                {$description}
            </div>

            <footer class="vs-post__foot">
                {* Post tags *}
                {if !empty($post->categories)}
                    <div class="vs-post__tags">
                        {include file="svg.tpl" svgId="tag_icon"}
                        {foreach $post->categories as $c}
                            {if $c->visible}
                                <a class="vs-post__tag" href="{url_generator route='blog_category' url=$c->url}">{$c->name|escape}</a>
                            {/if}
                        {/foreach}
                    </div>
                {/if}

                <div class="vs-post__rate">
                    <div id="post_{$post->id}" class="product__rating fn_rating vs-rating" data-rating_post_url="{url_generator route='ajax_post_rating'}">
                        <span class="vs-rating__label" data-language="post_rating_title">{$lang->post_rating_title}</span>
                        <span class="rating_starOff">
                            <span class="rating_starOn" style="width:{$post->rating*90/5|string_format:'%.0f'}px;"></span>
                        </span>
                        {if $post->rating > 0}
                            <span class="rating_text">( <span>{$post->votes|string_format:"%.0f"}</span> )</span>
                            <span class="rating_text hidden">( <span>{$post->rating|string_format:"%.1f"}</span> )</span>
                            <span class="rating_text hidden" style="display:none;">5</span>
                        {else}
                            <span class="rating_text hidden">({$post->rating|string_format:"%.1f"})</span>
                        {/if}
                    </div>

                    {* Share buttons *}
                    {include file="share.tpl" url=$canonical title=$post->name class="vs-post__share"}
                </div>

                {* Article author *}
                {if $post->author}
                    <div class="vs-author-card">
                        <span class="vs-author-card__avatar">
                            {if $post->author->image}
                                <img src="{$post->author->image|resize:100:100:false:$config->resized_authors_dir:center:center}" alt="">
                            {else}
                                {include file="svg.tpl" svgId="avatar_icon"}
                            {/if}
                        </span>
                        <div class="vs-author-card__body">
                            <div class="vs-author-card__name">
                                {if $post->author->visible}
                                    <a href="{url_generator route='author' url=$post->author->url}">{$post->author->name|escape}</a>
                                {else}
                                    <span>{$post->author->name|escape}</span>
                                {/if}
                            </div>
                            {if $post->author->position_name}
                                <div class="vs-author-card__position">{$post->author->position_name|escape}</div>
                            {/if}
                            {if is_array($post->author->socials)}
                                <div class="vs-socials">
                                    {foreach $post->author->socials as $social}
                                        <a class="fn_social_image vs-socials__link" rel="noreferrer" href="{if !preg_match('~^https?://.*$~', $social.url)}https://{/if}{$social.url|escape}" target="_blank" title="{$social.domain|escape}">
                                            <span>{$social.domain|escape}</span>
                                        </a>
                                    {/foreach}
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}

                {* Previous/Next posts *}
                {if $prev_post || $next_post}
                    <nav class="vs-pager" aria-label="{$lang->blog_catalog|escape}">
                        {if $prev_post}
                            <a class="vs-pager__link vs-pager__link--prev" href="{url_generator route='post' url=$prev_post->url}">
                                {include file="svg.tpl" svgId="arrow_up_icon"}
                                <span>{$prev_post->name|escape}</span>
                            </a>
                        {/if}
                        {if $next_post}
                            <a class="vs-pager__link vs-pager__link--next" href="{url_generator route='post' url=$next_post->url}">
                                <span>{$next_post->name|escape}</span>
                                {include file="svg.tpl" svgId="arrow_up_icon"}
                            </a>
                        {/if}
                    </nav>
                {/if}
            </footer>

            {* Related products *}
            {if $related_products}
                <section class="vs-section vs-post__related">
                    <h2 class="vs-section__title">
                        <span data-language="product_recommended_products">{$lang->product_recommended_products}</span>
                    </h2>
                    <div class="vs-related__grid">
                        {foreach $related_products as $p}
                            {include "product_list.tpl" product = $p}
                        {/foreach}
                    </div>
                </section>
            {/if}

            <section id="comments" class="vs-section">
                <h2 class="vs-section__title">
                    <span data-language="post_comments">{$lang->post_comments}</span>
                </h2>

                <div class="vs-reviews">
                <div class="vs-reviews__list">
                    {if $comments}
                        {function name=comments_tree level=0}
                            {foreach $comments as $comment}
                                <div class="comment__item {if $level > 0} admin_note{/if}">
                                    {* Comment anchor *}
                                    <a name="comment_{$comment->id}"></a>
                                    <div class="comment__inner">
                                        <div class="comment__icon">
                                            {if $level > 0}
                                                {include file="svg.tpl" svgId="headset"}
                                            {else}
                                                {include file="svg.tpl" svgId="user"}
                                            {/if}
                                        </div>
                                        <div class="comment__boxed">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between comment__header">
                                                <div class="d-flex flex-wrap align-items-center comment__author">
                                                    <span class="comment__name">{$comment->name|escape}</span>
                                                    {if !$comment->approved}
                                                        <span class="comment__status" data-language="post_comment_status">({$lang->post_comment_status})</span>
                                                    {/if}
                                                </div>
                                                <div class="comment__date">
                                                    <span>{$comment->date|date} {$comment->date|time}</span>
                                                </div>
                                            </div>
                                            <div class="comment__body">
                                                {$comment->text|escape|nl2br}
                                            </div>
                                        </div>
                                    </div>
                                    {if !empty($comment->children)}
                                        {comments_tree comments=$comment->children level=$level+1}
                                    {/if}
                                </div>
                            {/foreach}
                        {/function}
                        {comments_tree comments=$comments}
                    {else}
                        <div class="vs-empty vs-empty--inline">
                            <span class="vs-empty__icon">{include file="svg.tpl" svgId="comment_icon"}</span>
                            <p class="vs-empty__note" data-language="post_no_comments">{$lang->post_no_comments}</p>
                        </div>
                    {/if}
                </div>

                {* Comment form *}
                <form id="fn_blog_comment" class="fn_validate_post vs-form vs-reviews__form" method="post" action="">
                    {if $settings->captcha_type == "v3"}
                        <input type="hidden" class="fn_recaptcha_token fn_recaptchav3" name="recaptcha_token" />
                    {/if}

                    <h3 class="vs-form__title">
                        <span data-language="post_write_comment">{$lang->post_write_comment}</span>
                    </h3>

                    {* Form error messages *}
                    {if $error}
                        <p class="vs-field__error vs-form__alert" role="alert">
                            {if $error=='captcha'}
                                <span data-language="form_error_captcha">{$lang->form_error_captcha}</span>
                            {elseif $error=='empty_name'}
                                <span data-language="form_enter_name">{$lang->form_enter_name}</span>
                            {elseif $error=='empty_comment'}
                                <span data-language="form_enter_comment">{$lang->form_enter_comment}</span>
                            {elseif $error=='empty_email'}
                                <span data-language="form_enter_email">{$lang->form_enter_email}</span>
                            {else}
                                {$error|escape}
                            {/if}
                        </p>
                    {/if}

                    <div class="vs-fields">
                        <div class="vs-form__row">
                            <label class="vs-form__label" for="vs_post_name">{$lang->form_name}*</label>
                            <input id="vs_post_name" class="vs-field vs-form__input" type="text" name="name" value="{if $request_data.name}{$request_data.name|escape}{elseif $user->name}{$user->name|escape}{/if}" />
                        </div>
                        <div class="vs-form__row">
                            <label class="vs-form__label" for="vs_post_email">{$lang->form_email}</label>
                            <input id="vs_post_email" class="vs-field vs-form__input" type="email" name="email" value="{if $request_data.email}{$request_data.email|escape}{elseif $user->email}{$user->email|escape}{/if}" />
                        </div>
                        <div class="vs-form__row">
                            <label class="vs-form__label" for="vs_post_text">{$lang->form_enter_comment}*</label>
                            <textarea id="vs_post_text" class="vs-field vs-form__textarea" rows="4" name="text">{$request_data.text|escape}</textarea>
                        </div>

                        {* Captcha *}
                        {if $settings->captcha_comment}
                            {if $settings->captcha_type == "v2"}
                                <div class="vs-form__row">
                                    <div id="recaptcha1"></div>
                                </div>
                            {elseif $settings->captcha_type == "default"}
                                {get_captcha var="captcha_comment"}
                                <div class="vs-form__row">
                                    <label class="vs-form__label" for="vs_post_captcha">{$captcha_comment[0]|escape} + ? = {$captcha_comment[1]|escape}</label>
                                    <input id="vs_post_captcha" class="vs-field vs-form__input" type="text" name="captcha_code" value="" />
                                </div>
                            {/if}
                        {/if}
                    </div>

                    <input type="hidden" name="comment" value="1">
                    <button class="vs-btn vs-btn--primary vs-form__submit g-recaptcha" type="submit" name="comment" {if $settings->captcha_type == "invisible"}data-sitekey="{$settings->public_recaptcha_invisible}" data-badge='bottomleft' data-callback="onSubmit"{/if} value="{$lang->form_send}">
                        <span data-language="form_send">{$lang->form_send}</span>
                    </button>
                </form>
                </div>
            </section>
        </article>

        {* Sidebar with post *}
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

{literal}
<script type="application/ld+json">

    { "@context": "http://schema.org",
        "@type": "Article",
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": "{/literal}{$canonical}{literal}"
        },
        "headline": "{/literal}{$h1|escape}{literal}",
        "alternativeHeadline": "{/literal}{$h1|escape}{literal}",
        "image": "{/literal}{$post->image|resize:800:800:false:$config->resized_blog_dir}{literal}",
        "author": {
            "@type": "Person",
            "name": "{/literal}{$post->author->name|escape}{literal}"
        },
        "publisher": {
            "@type": "Organization",
            "name": "{/literal}{$settings->site_name|escape}{literal}",
            "logo": {
                "@type": "ImageObject",
                "url": "{/literal}{$rootUrl}/{$config->design_images}{$settings->site_logo}{literal}",
                "width": 230,
                "height": 40
            }
        },
        "url": "{/literal}{$canonical}{literal}",
        "datePublished": "{/literal}{$post->date|date_format:'%Y-%m-%d'}{literal}",
        "dateCreated": "{/literal}{$post->date|date_format:'%Y-%m-%d'}{literal}",
        {/literal}
        {if $post->updated_date > 0}
        {literal}
        "dateModified": "{/literal}{$post->updated_date|date_format:'%Y-%m-%d'}{literal}",
        {/literal}
        {else}
        {literal}
        "dateModified": "{/literal}{$post->date|date_format:'%Y-%m-%d'}{literal}",
        {/literal}
        {/if}
        {literal}
        "description": "{/literal}{$annotation|json_ld_text}{literal}",
        "articleBody": "{/literal}{$description|json_ld_text}{literal}"
    }

</script>
{/literal}
