{* Назва мережі в, гліф теми з. Одне місце на два блоки: посилання магазину у
   футері й кнопки «поділитися» на товарі та в пості.

   Ключі — те, що реально приходить. site_social віддає домен, витягнутий з
   адреси, тому "youtu" і "t" стоять поруч із "youtube" і "telegram".
   SocialShare віддає власну назву мережі, звідси "email". Twitter це X:
   мережа перейменувалась, адреса шарингу — ні, тож ключ лишається twitter.

   Невідоме отримує глобус, а не порожнечу: магазин може вписати в
   site_social_links мережу, про яку тема не знає, і порожня кнопка гірша за
   просту. *}
{$okSocialIcons = [
    'facebook' => 'social_facebook',
    'instagram' => 'social_instagram',
    'youtube' => 'social_youtube',
    'youtu' => 'social_youtube',
    'tiktok' => 'social_tiktok',
    'github' => 'social_github',
    'twitter' => 'social_x',
    'x' => 'social_x',
    'telegram' => 'social_telegram',
    't' => 'social_telegram',
    'whatsapp' => 'social_whatsapp',
    'viber' => 'social_viber',
    'linkedin' => 'social_linkedin',
    'pinterest' => 'social_pinterest',
    'reddit' => 'social_reddit',
    'line' => 'social_line',
    'email' => 'email_icon'
]}
{include file="svg.tpl" svgId=$okSocialIcons[$domain|default:''|lower]|default:'social_link'}
