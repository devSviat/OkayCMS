{* Кнопки шарингу. Мережі — з налаштувань теми, адреси будує Okay\Core\SocialShare.
   Гліфи — FontAwesome, як і решта іконок цієї теми; бренд-класи для X, Viber і LINE
   у 4.7 відсутні, тож там загальні. Раніше ці кнопки малював у браузері jssocials.

   label=false у пості: там підпис прибраний ще стоковою темою. *}
{$okShareIcons = [
    'facebook' => 'fa-facebook',
    'twitter' => 'fa-twitter',
    'telegram' => 'fa-telegram',
    'whatsapp' => 'fa-whatsapp',
    'viber' => 'fa-phone',
    'linkedin' => 'fa-linkedin',
    'pinterest' => 'fa-pinterest',
    'reddit' => 'fa-reddit',
    'line' => 'fa-comment',
    'email' => 'fa-envelope-o'
]}
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
                <a class="share__item" href="{$okLink.url|escape}" target="_blank" rel="noopener nofollow">
                    <i class="fa {$okShareIcons[$okLink.key]|default:'fa-share-alt'}" aria-hidden="true"></i>
                    <span class="sr-only">{$okLink.label|escape}</span>
                </a>
            {/foreach}
        </div>
    </div>
{/if}
