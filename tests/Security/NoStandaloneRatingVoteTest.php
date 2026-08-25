<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Бал товару має рівно одне джерело — схвалені відгуки.
 *
 * Доти поруч жило анонімне голосування: відкритий ендпоінт писав рейтинг
 * напряму в товар, повз відгуки й повз модерацію. Він приймав будь-яке число
 * з будь-якого сайту, а після переходу на відгуки ще й затирав порахований
 * бал — один запит міняв 4.0/1 на 2.5/2.
 *
 * Друге джерело - це друга правда в `aggregateRating`, тобто вигадана оцінка
 * у структурованих даних для пошуковика.
 */
class NoStandaloneRatingVoteTest extends TestCase
{
    private const GONE_ROUTES = ['ajax_product_rating', 'ajax_post_rating'];
    private const GONE_SLUGS  = ['/ajax/rating', '/ajax/post_rating'];

    #[DataProvider('goneRouteProvider')]
    public function testVoteRoutesStayRemoved(string $needle): void
    {
        $this->assertStringNotContainsString(
            $needle,
            $this->source('Okay/Core/config/routes.php'),
            sprintf('роут %s повернувся — бал знову можна писати повз відгуки', $needle)
        );
    }

    public static function goneRouteProvider(): array
    {
        $cases = [];
        foreach (array_merge(self::GONE_ROUTES, self::GONE_SLUGS) as $needle) {
            $cases[$needle] = [$needle];
        }

        return $cases;
    }

    /**
     * Контролери каталогу й блога більше не мають методу голосування: сам
     * роут прибрати замало, бо метод лишався б досяжним із будь-якого нового
     * маршруту, який хтось додасть.
     */
    #[DataProvider('controllerProvider')]
    public function testControllersHaveNoVoteAction(string $path): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '~function\s+rating\s*\(~',
            $this->source($path),
            sprintf('%s: метод голосування повернувся', basename($path))
        );
    }

    public static function controllerProvider(): array
    {
        return [
            'товар' => ['Okay/Controllers/ProductController.php'],
            'блог'  => ['Okay/Controllers/BlogController.php'],
        ];
    }

    /**
     * Теми не повинні малювати клікабельний віджет: він або вів би в нікуди,
     * або, якщо ендпоінт колись повернуть, знову писав би бал повз відгуки.
     */
    #[DataProvider('themeTemplateProvider')]
    public function testThemesDoNotOfferAStandaloneVote(string $path): void
    {
        $source = $this->source($path);

        $this->assertStringNotContainsString(
            'rating_post_url',
            $source,
            sprintf('%s: віджет усе ще шле голос', $this->label($path))
        );
    }

    public static function themeTemplateProvider(): array
    {
        $found = [];
        foreach (['product.tpl', 'post.tpl'] as $name) {
            foreach (glob(dirname(__DIR__, 2) . '/design/*/html/' . $name) ?: [] as $path) {
                $found[basename(dirname(dirname($path))) . '/' . $name] = [$path];
            }
        }

        return $found;
    }

    private function label(string $path): string
    {
        return basename(dirname(dirname($path))) . '/' . basename($path);
    }

    private function source(string $path): string
    {
        $full = str_starts_with($path, '/') ? $path : dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileExists($full);

        return (string)file_get_contents($full);
    }
}
