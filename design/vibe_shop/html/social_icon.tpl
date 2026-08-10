{* A social network's name in, the theme's glyph out. One place, because three
   blocks need the same answer and the map used to exist only inside
   mobile_menu.tpl: the footer's social buttons, that mobile row, and the share
   pills on a product or a post.

   The keys are what the callers actually hold. site_social hands a domain pulled
   out of the URL, so "youtu" and "t" are here beside "youtube" and "telegram".
   SocialShare hands its own network name, which is where "email" comes from.
   Twitter is X - the network renamed itself, the share endpoint did not, so the
   key stays twitter and the glyph is the X.

   Anything unmatched gets the generic globe rather than nothing: a shop can put
   a network in site_social_links or in the share list that this theme has never
   heard of, and an empty button is worse than a plain one. *}
{$vsSocialIcons = [
    'facebook' => 'social_facebook',
    'instagram' => 'social_instagram',
    'telegram' => 'social_telegram',
    't' => 'social_telegram',
    'youtube' => 'social_youtube',
    'youtu' => 'social_youtube',
    'tiktok' => 'social_tiktok',
    'twitter' => 'social_x',
    'x' => 'social_x',
    'linkedin' => 'social_linkedin',
    'whatsapp' => 'social_whatsapp',
    'viber' => 'social_viber',
    'pinterest' => 'social_pinterest',
    'reddit' => 'social_reddit',
    'line' => 'social_line',
    'email' => 'mail'
]}
{include file="svg.tpl" svgId=$vsSocialIcons[$domain|default:''|lower]|default:'social_link'}
