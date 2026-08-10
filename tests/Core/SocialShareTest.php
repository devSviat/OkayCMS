<?php

namespace Core;

use Okay\Core\SocialShare;
use PHPUnit\Framework\TestCase;

class SocialShareTest extends TestCase
{
    public function testBuildLinksKeepsTheDeclaredOrderNotTheSettingsOrder(): void
    {
        $links = (new SocialShare())->buildLinks(['email', 'facebook'], 'https://shop.ua/p/1', 'Диван');

        $this->assertSame(['facebook', 'email'], array_column($links, 'key'));
    }

    /**
     * Налаштування sj_shares у працюючих магазинах досі містить googleplus і
     * pocket. Кнопка на закритий сервіс гірша за відсутню, тож невідомий ключ
     * має просто зникнути, а не впасти і не намалювати порожній гурток.
     */
    public function testBuildLinksSkipsUnknownNetworks(): void
    {
        $links = (new SocialShare())->buildLinks(['googleplus', 'pocket', 'telegram'], 'https://shop.ua/p/1', 'Диван');

        $this->assertSame(['telegram'], array_column($links, 'key'));
    }

    public function testBuildLinksEncodesUrlAndTitle(): void
    {
        $links = (new SocialShare())->buildLinks(['telegram'], 'https://shop.ua/p/1?a=b&c=d', 'Диван "Осло" & стіл');

        $this->assertSame(
            'https://t.me/share/url?url=https%3A%2F%2Fshop.ua%2Fp%2F1%3Fa%3Db%26c%3Dd'
                . '&text=%D0%94%D0%B8%D0%B2%D0%B0%D0%BD%20%22%D0%9E%D1%81%D0%BB%D0%BE%22%20%26%20%D1%81%D1%82%D1%96%D0%BB',
            $links[0]['url']
        );
    }

    public function testTwitterKeepsItsKeyButCarriesTheCurrentNameAndEndpoint(): void
    {
        $links = (new SocialShare())->buildLinks(['twitter'], 'https://shop.ua/p/1', 'Диван');

        $this->assertSame('twitter', $links[0]['key'], 'ключ лежить у sj_shares працюючих магазинів');
        $this->assertSame('X', $links[0]['label']);
        $this->assertStringStartsWith('https://x.com/intent/post', $links[0]['url']);
    }

    public function testGetNetworkLabelsIsKeyedByNetwork(): void
    {
        $labels = (new SocialShare())->getNetworkLabels();

        $this->assertSame('X', $labels['twitter']);
        $this->assertSame('Telegram', $labels['telegram']);
        $this->assertSame((new SocialShare())->getNetworks(), array_keys($labels));
    }

    /**
     * mailto: віддається поштовому клієнту, viber:// - застосунку. target="_blank"
     * на них лишає в браузері порожню вкладку, тож прапорець мають отримати
     * тільки http(s)-адреси.
     */
    public function testOnlyHttpLinksAreMarkedForANewTab(): void
    {
        $links = (new SocialShare())->buildLinks(
            ['facebook', 'viber', 'email'],
            'https://shop.ua/p/1',
            'Диван'
        );

        $this->assertSame(
            ['facebook' => true, 'viber' => false, 'email' => false],
            array_column($links, 'blank', 'key')
        );
    }

    /**
     * Модуль реєструє мережу одним викликом, і її мають побачити обидві
     * сторони: список галочок в адмінці й кнопки на вітрині. Раніше цю роль
     * грав getCustomSocials(), який годував бібліотеку.
     */
    public function testAddNetworkReachesBothTheAdminListAndTheStorefront(): void
    {
        $share = new SocialShare();
        $share->addNetwork('mastodon', 'Mastodon', 'https://example.social/share?text={title}%20{url}');

        $this->assertContains('mastodon', $share->getNetworks());
        $this->assertSame('Mastodon', $share->getNetworkLabels()['mastodon']);

        $links = $share->buildLinks(['mastodon'], 'https://shop.ua/p/1', 'Диван');
        $this->assertSame('mastodon', $links[0]['key']);
        $this->assertStringContainsString('https%3A%2F%2Fshop.ua%2Fp%2F1', $links[0]['url']);
        $this->assertTrue($links[0]['blank']);
    }

    public function testGetSocialDomainStripsSchemeAndWww(): void
    {
        $this->assertSame('facebook', SocialShare::getSocialDomain('https://www.facebook.com/shop'));
        $this->assertSame('t', SocialShare::getSocialDomain('https://t.me/shop'));
    }
}
