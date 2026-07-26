{if $menu_items}
    {function name=menu_items_tree}
    {if $menu_items}
    <ul class="fn_menu_list vs-menu vs-menu--{$level}">
        {foreach $menu_items as $item}
        {if $item->visible == 1}
        {$hasSub = ($item->submenus && $item->count_children_visible > 0)}
        <li class="vs-menu__item{if $hasSub} menu_eventer vs-has-children{/if}">
            <a class="vs-menu__link" {if $item->url} href="{if preg_match('~^https?://~', {$item->url})}{$item->url}{else}{url_generator route='page' url=$item->url}{/if}"{/if} {if !$item->submenus && $item->is_target_blank}target="_blank"{/if}>
                <span>{$item->name|escape}</span>
                {if $hasSub}<span class="vs-menu__chevron">{include file="svg.tpl" svgId="chevron"}</span>{/if}
            </a>
            {menu_items_tree menu_items=$item->submenus level=$level + 1}
        </li>
        {/if}
        {/foreach}
    </ul>
    {/if}
    {/function}
    {menu_items_tree menu_items=$menu_items level=1}
{/if}
