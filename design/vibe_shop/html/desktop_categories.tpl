<!-- Desktop categories template -->
{function name=categories_tree3}
    {if $categories}
        {if $level == 1}
            <ul class="fn_category_scroll vs-catalog__list">
        {elseif $level == 2}
            <ul class="vs-catalog__sub">
        {else}
            <ul class="vs-catalog__leaf">
        {/if}
        {foreach $categories as $c}
            {if $c->visible && ($c->has_products || $settings->show_empty_categories)}
                {$hasChild = ($c->subcategories && $c->count_children_visible && $level < 3)}
                <li class="vs-catalog__item vs-catalog__item--{$level}">
                    <a class="vs-catalog__link vs-catalog__link--{$level}{if $category->id == $c->id} is-current{/if}" href="{url_generator route='category' url=$c->url}" data-category="{$c->id}">
                        {if $level == 1 && $c->image}
                            {if strtolower(pathinfo($c->image, $smarty.const.PATHINFO_EXTENSION)) == 'svg'}
                                <span class="vs-catalog__icon">{$c->image|read_svg:$config->original_categories_dir}</span>
                            {else}
                                <span class="vs-catalog__icon lazy" data-bg="url({$c->image|resize:22:22:false:$config->resized_categories_dir})"></span>
                            {/if}
                        {/if}
                        <span class="vs-catalog__name">{$c->name|escape}</span>
                    </a>
                    {if $hasChild}
                        {categories_tree3 categories=$c->subcategories level=$level + 1}
                    {/if}
                </li>
            {/if}
        {/foreach}
        </ul>
    {/if}
{/function}
{categories_tree3 categories=$categories level=1}
