<!-- The template of page 404 -->

<div class="container">
    <div class="vs-404">
        {* The page had no heading of any level at all - the outline started at
           the footer's <h2>. The design is an illustration, a sentence of
           admin-authored copy and a button, and none of those is a headline the
           owner asked for, so the level-1 heading is spoken rather than drawn.
           $h1 falls back to the meta title, which this route always sets. *}
        <h1 class="vs-sr-only">{if $h1}{$h1|escape}{else}{$meta_title|escape}{/if}</h1>
        <div class="vs-404__mark">
            {include file="svg.tpl" svgId="404_icon"}
        </div>
        <div class="vs-404__note">
            {$description}
        </div>
        <div class="vs-404__actions">
            <a class="vs-btn vs-btn--primary" href="{url_generator route='products'}">
                <span data-language="index_categories">{$lang->index_categories}</span>
            </a>
        </div>
        {* The 404 menu is admin-authored: an arbitrary <ul> of links. *}
        <nav class="vs-404__menu">
            {$menu_404}
        </nav>
    </div>
</div>
