{* Кнопки шарингу. Мережі — з налаштувань теми, адреси будує Okay\Core\SocialShare,
   гліфи — з social_icon.tpl. Раніше це малював у браузері jssocials, а іконки брав
   із FontAwesome, де немає ні X, ні Viber, ні LINE.

   label=false у пості: там підпис прибраний ще стоковою темою. *}
{share_links var=okShareLinks url=$url title=$title}
{if $okShareLinks}
    <div class="share">
        {if $label|default:true}
            <div class="share__text">
                <span data-language="product_share">{$lang->product_share}:</span>
            </div>
        {/if}
        <div class="share__icons">
            {foreach $okShareLinks as $okLink}
                <a class="share__item" href="{$okLink.url|escape}"{if $okLink.blank} target="_blank" rel="noopener nofollow"{/if}>
                    <span class="share__glyph">{include file="social_icon.tpl" domain=$okLink.key}</span>
                    <span class="sr-only">{$okLink.label|escape}</span>
                </a>
            {/foreach}
        </div>
    </div>
{/if}
