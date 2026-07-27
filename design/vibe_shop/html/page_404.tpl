<!-- The template of page 404 -->

<div class="container">
    <div class="vs-404">
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
