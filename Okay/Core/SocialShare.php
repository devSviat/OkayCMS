<?php


namespace Okay\Core;


class SocialShare
{
    /**
     * Мережа => підпис і шаблон адреси. {url} і {title} підставляються вже
     * закодованими.
     *
     * Список свідомо коротший за той, що був у jssocials: googleplus,
     * stumbleupon і pocket — закриті сервіси, а messenger має тільки
     * fb-messenger:// для застосунку та веб-діалог, який вимагає app_id
     * фейсбук-застосунку магазину.
     *
     * Ключ twitter лишається twitter, хоч підпис і адреса вже X: цей ключ
     * лежить у sj_shares працюючих магазинів, і перейменування зробило б
     * їхню галочку невидимою.
     */
    private const NETWORKS = [
        'facebook'      => ['label' => 'Facebook',  'url' => 'https://www.facebook.com/sharer/sharer.php?u={url}'],
        'twitter'       => ['label' => 'X',         'url' => 'https://x.com/intent/post?url={url}&text={title}'],
        'telegram'      => ['label' => 'Telegram',  'url' => 'https://t.me/share/url?url={url}&text={title}'],
        'whatsapp'      => ['label' => 'WhatsApp',  'url' => 'https://api.whatsapp.com/send?text={title}%20{url}'],
        'viber'         => ['label' => 'Viber',     'url' => 'viber://forward?text={title}%20{url}'],
        'linkedin'      => ['label' => 'LinkedIn',  'url' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}'],
        'pinterest'     => ['label' => 'Pinterest', 'url' => 'https://pinterest.com/pin/create/bookmarklet/?url={url}&description={title}'],
        'reddit'        => ['label' => 'Reddit',    'url' => 'https://www.reddit.com/submit?url={url}&title={title}'],
        'line'          => ['label' => 'LINE',      'url' => 'https://social-plugins.line.me/lineit/share?url={url}'],
        'vkontakte'     => ['label' => 'VK',        'url' => 'https://vk.com/share.php?url={url}&title={title}'],
        'odnoklassniki' => ['label' => 'OK',        'url' => 'https://connect.ok.ru/dk?st.cmd=WidgetSharePreview&st.shareUrl={url}&title={title}'],
        'email'         => ['label' => 'Email',     'url' => 'mailto:?subject={title}&body={url}'],
    ];

    /**
     * Домен деяких соцмереж не збігається з назвою мережі.
     *
     * @var string[]
     */
    private static $socialAliases = [
        'ok' => 'odnoklassniki',
    ];

    /**
     * @return string[]
     */
    public function getNetworks(): array
    {
        return array_keys(self::NETWORKS);
    }

    /**
     * @return string[] ключ => підпис
     */
    public function getNetworkLabels(): array
    {
        return array_map(static fn (array $network): string => $network['label'], self::NETWORKS);
    }

    /**
     * Порядок кнопок береться з NETWORKS, а не з $enabled: інакше він залежав
     * би від того, в якому порядку адмін тикав галочки.
     *
     * @param string[] $enabled
     * @return array<int, array{key: string, label: string, url: string}>
     */
    public function buildLinks(array $enabled, string $url, string $title): array
    {
        $links = [];
        foreach (self::NETWORKS as $key => $network) {
            if (!in_array($key, $enabled, true)) {
                continue;
            }

            $links[] = [
                'key'   => $key,
                'label' => $network['label'],
                'url'   => strtr($network['url'], [
                    '{url}'   => rawurlencode($url),
                    '{title}' => rawurlencode($title),
                ]),
            ];
        }

        return $links;
    }

    public static function getSocialDomain($link)
    {
        $socialDomain = preg_replace('~^(https?://)?(www\.)?([^.]+)?\..*$~', '$3', $link);

        if (isset(self::$socialAliases[$socialDomain])) {
            return self::$socialAliases[$socialDomain];
        }
        return $socialDomain;
    }
}
