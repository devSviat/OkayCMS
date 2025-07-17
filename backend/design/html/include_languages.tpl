{*Список языков*}
{if $languages}
    {foreach $languages as $lang}
        <a class="flag flag_{$lang->id} {if $lang->id == $lang_id} focus{/if} hint-bottom-middle-t-info-s-small-mobile  hint-anim" data-hint="{$lang->name|escape}" href="{url lang_id=$lang->id}" data-label="{$lang->label|escape}">
            {$lang->label}
        </a>
    {/foreach}
{/if}
