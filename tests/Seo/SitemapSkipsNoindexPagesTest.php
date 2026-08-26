<?php

namespace Seo;

use Okay\Helpers\SiteMapHelper;
use PHPUnit\Framework\TestCase;

/**
 * Сторінки авторизації мають рядок у `__pages`, але віддаються з noindex.
 * Потрапивши в мапу сайту, вони стають помилкою Search Console «Submitted URL
 * marked noindex» — і зникають з поля зору, бо помилка не ламає нічого видимого.
 */
class SitemapSkipsNoindexPagesTest extends TestCase
{
    private const AUTH_PAGES = ['user/login', 'user/register', 'user/password_remind'];

    public function testKnownAuthPagesAreExcluded(): void
    {
        foreach (self::AUTH_PAGES as $url) {
            $this->assertContains(
                $url,
                SiteMapHelper::NOINDEX_PAGES,
                sprintf('%s віддається з noindex, тож у мапі сайту йому не місце', $url)
            );
        }
    }

    /**
     * Перелік мертвіє тихо: видалили маршрут — рядок лишився й нічого не
     * пропускає, а виглядає як робочий захист.
     */
    public function testEveryListedPageStillHasARoute(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2) . '/Okay/Core/config/routes.php');

        foreach (SiteMapHelper::NOINDEX_PAGES as $url) {
            $this->assertMatchesRegularExpression(
                '~[\'"]/?' . preg_quote($url, '~') . '~',
                $routes,
                sprintf('%s немає серед маршрутів — рядок у NOINDEX_PAGES застарів', $url)
            );
        }
    }

    /**
     * Константа без застосування — найтихіша з можливих поломок: перелік на
     * місці, коментар пояснює задум, а мапа далі подає все підряд.
     */
    public function testTheProcedureActuallyConsultsTheList(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/Okay/Helpers/SiteMapHelper.php');

        $start = strpos($source, 'function writePagesProcedure');
        $this->assertNotFalse($start, 'writePagesProcedure() зник із SiteMapHelper');

        $end  = strpos($source, "\n    public function", $start);
        $body = substr($source, $start, $end === false ? null : $end - $start);

        $this->assertStringContainsString(
            'NOINDEX_PAGES',
            $body,
            'writePagesProcedure() більше не звіряється з переліком'
        );
    }
}
