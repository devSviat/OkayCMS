<?php

namespace Core\UserReferer;

use Okay\Core\Request;
use Okay\Core\UserReferer\UserReferer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Snowplow\RefererParser\Config\JsonConfigReader;
use Snowplow\RefererParser\Medium;
use Snowplow\RefererParser\Parser;

/**
 * ok_orders.referer_channel - це ENUM із п'яти значень, а довідник парсера знає
 * більше каналів, ніж їх розбирає parse(). Канал поза списком лишав $userReferer
 * порожнім, і saveUserReferer(array) отримував null - тобто 500 на вітрині.
 * Фікстура навмисно своя: наш довідник каналів paid і chatbot не має.
 */
class UserRefererChannelTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/data/referers-mediums.json';

    private const ORDER_CHANNELS = [
        UserReferer::CHANNEL_EMAIL,
        UserReferer::CHANNEL_SEARCH,
        UserReferer::CHANNEL_SOCIAL,
        UserReferer::CHANNEL_REFERRAL,
        UserReferer::CHANNEL_UNKNOWN,
    ];

    private $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;

        $_SERVER['HTTP_HOST']   = 'okaycms.loc';
        $_SERVER['REQUEST_URI'] = '/products/divan-redking';

        Request::setDomain('okaycms.loc');
        Request::setProtocol('http');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;

        Request::setDomain('');
        Request::setProtocol('');
        (new \ReflectionProperty(UserReferer::class, 'userReferer'))->setValue(null, null);
    }

    #[DataProvider('unmappedChannelProvider')]
    public function testUnmappedChannelBecomesReferral($url, $source): void
    {
        $this->assertSame(
            ['medium' => UserReferer::CHANNEL_REFERRAL, 'source' => $source],
            $this->parse($url)
        );
    }

    public static function unmappedChannelProvider()
    {
        return [
            'chatbot' => ['https://chatgpt.com/c/68a1f0', 'ChatGPT'],
            'paid'    => ['https://googleads.g.doubleclick.net/pcs/click?x=1', 'Google Ads'],
        ];
    }

    #[DataProvider('knownChannelProvider')]
    public function testKnownChannelsKeepTheirMedium($url, $medium, $source): void
    {
        $this->assertSame(['medium' => $medium, 'source' => $source], $this->parse($url));
    }

    public static function knownChannelProvider()
    {
        return [
            'search' => ['https://www.google.com/search?q=divan', UserReferer::CHANNEL_SEARCH, 'Google'],
            'social' => ['https://www.facebook.com/',             UserReferer::CHANNEL_SOCIAL, 'Facebook'],
            'email'  => ['https://mail.google.com/mail/u/0/',      UserReferer::CHANNEL_EMAIL,  'Gmail'],
        ];
    }

    /**
     * Коли пакет додасть шостий канал, тест впаде тут, а не мовчки лишить діру:
     * фікстура має покривати всі канали, які parse() взагалі може побачити.
     */
    public function testFixtureCoversEveryKnownMediumOfThePackage(): void
    {
        $known = array_values(array_map(
            fn (Medium $medium) => $medium->value,
            array_filter(
                Medium::cases(),
                fn (Medium $medium) => !in_array($medium, [Medium::INVALID, Medium::UNKNOWN, Medium::INTERNAL], true)
            )
        ));
        sort($known);

        $fixture = array_keys($this->fixture());
        sort($fixture);

        $this->assertSame($known, $fixture, 'Канали пакета розійшлись із фікстурою');
    }

    public function testEveryChannelFitsTheOrdersEnum(): void
    {
        foreach ($this->fixture() as $medium => $sources) {
            foreach ($sources as $source => $referer) {
                $result = $this->parse('https://' . $referer['domains'][0] . '/');

                $this->assertContains($result['medium'], self::ORDER_CHANNELS, $medium);
                $this->assertSame($source, $result['source'], $medium);
            }
        }
    }

    private function parse($url)
    {
        $_SERVER['HTTP_REFERER'] = $url;

        $userReferer = new UserReferer(new Parser(new JsonConfigReader(self::FIXTURE)));
        $userReferer->parse();

        return UserReferer::getUserReferer();
    }

    private function fixture()
    {
        return json_decode(file_get_contents(self::FIXTURE), true);
    }
}
