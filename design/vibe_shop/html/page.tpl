<!-- Page template -->

{if $page->url == '404'}
    {include file='page_404.tpl'}
{else}
    <div class="vs-page">
        {* The page heading *}
        <div class="vs-page__masthead">
            <h1 class="vs-page__title">
                <span data-page="{$page->id}">{if $page->name_h1|escape}{$page->name_h1|escape}{else}{$page->name|escape}{/if}</span>
            </h1>
        </div>

        {* The page content. Admin-authored WYSIWYG: .vs-prose assumes nothing
           about the nesting, inline styles, table widths or image sizes. *}
        <div class="block__description vs-prose vs-page__body">{$description}</div>
    </div>
{/if}
