{* Кнопки шарингу. Мережі — з налаштувань теми, адреси будує Okay\Core\SocialShare,
   гліфи — з social_icon.tpl. Раніше це малював jssocials у браузері; тепер розмітка
   приходить із сервера, тож кнопки є і без JS, і в них немає чужих класів, які
   доводилось перебивати трьома селекторами.

   Підпис лишається в розмітці й обрізається візуально: він і є доступною назвою
   кнопки, а посилання з одного гліфа не має жодної.

   class — необовʼязковий модифікатор на самому .vs-share: у пості блок сидить
   між двома лінійками і скидає власні відступи. *}
{share_links var=vsShareLinks url=$url title=$title}
{if $vsShareLinks}
    <div class="vs-share{if $class} {$class|escape}{/if}">
        <span class="vs-share__label" data-language="product_share">{$lang->product_share}</span>
        <div class="vs-share__list">
            {foreach $vsShareLinks as $vsLink}
                <a class="vs-share__item" href="{$vsLink.url|escape}" title="{$vsLink.label|escape}"{if $vsLink.blank} target="_blank" rel="noopener nofollow"{/if}>
                    <span class="vs-share__glyph">{include file="social_icon.tpl" domain=$vsLink.key}</span>
                    <span class="vs-share__name">{$vsLink.label|escape}</span>
                </a>
            {/foreach}
        </div>
    </div>
{/if}
