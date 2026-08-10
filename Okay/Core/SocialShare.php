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
        'email'         => ['label' => 'Email',     'url' => 'mailto:?subject={title}&body={url}'],
    ];

    /**
     * Мережі, додані модулями. Сервіс у контейнері один, тож виклик з
     * Init::init() бачать і адмінка, і вітрина - інакше галочка зʼявлялась би
     * в налаштуваннях, а кнопка на сторінці ні.
     *
     * @var array<string, array{label: string, url: string}>
     */
    private $extraNetworks = [];

    /**
     * $url - шаблон адреси з {url} і {title}; підставляються закодованими.
     */
    public function addNetwork(string $key, string $label, string $url): void
    {
        $this->extraNetworks[$key] = ['label' => $label, 'url' => $url];
    }

    /**
     * @return array<string, array{label: string, url: string}>
     */
    private function networks(): array
    {
        return self::NETWORKS + $this->extraNetworks;
    }

    /**
     * @return string[]
     */
    public function getNetworks(): array
    {
        return array_keys($this->networks());
    }

    /**
     * @return string[] ключ => підпис
     */
    public function getNetworkLabels(): array
    {
        return array_map(static fn (array $network): string => $network['label'], $this->networks());
    }

    /**
     * Порядок кнопок береться з NETWORKS, а не з $enabled: інакше він залежав
     * би від того, в якому порядку адмін тикав галочки.
     *
     * blank каже шаблону, чи вішати target="_blank". Тільки для http(s):
     * mailto: віддається поштовому клієнту, viber:// - застосунку, і нова
     * вкладка в обох випадках лишається висіти порожньою.
     *
     * @param string[] $enabled
     * @return array<int, array{key: string, label: string, url: string, blank: bool}>
     */
    public function buildLinks(array $enabled, string $url, string $title): array
    {
        $links = [];
        foreach ($this->networks() as $key => $network) {
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
                'blank' => str_starts_with($network['url'], 'http'),
            ];
        }

        return $links;
    }

    /**
     * Домен посилання з site_social_links => назва мережі для іконки.
     */
    public static function getSocialDomain($link)
    {
        return preg_replace('~^(https?://)?(www\.)?([^.]+)?\..*$~', '$3', $link);
    }
}
