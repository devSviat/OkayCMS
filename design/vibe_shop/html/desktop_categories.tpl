<!-- Desktop categories template -->
{function name=categories_tree3}
    {if $categories}
        {* The list opens and closes as one balanced tag, with the level written into
           the class - the same shape okay_shop uses. Opening a <ul> inside a branch
           and closing it after the {foreach} costs the tail of the file the moment a
           module modifies this template and Okay\Core\TplMod re-parses it. *}
        <ul class="{if $level == 1}fn_category_scroll vs-catalog__list{elseif $level == 2}vs-catalog__sub{else}vs-catalog__leaf{/if}">
        {foreach $categories as $c}
            {if $c->visible && ($c->has_products || $settings->show_empty_categories)}
                {* `lt`, not `<`: outside an opening {if} a `<` reads as an HTML tag to
                   TplMod and truncates the file. okay_shop keeps this test inline in
                   the {if} below, where `<` is safe. *}
                {$hasChild = ($c->subcategories && $c->count_children_visible && $level lt 3)}
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
