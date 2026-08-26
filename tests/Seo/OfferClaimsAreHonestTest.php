<?php

namespace Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Вузол `offers` — це обіцянка покупцеві, і Google показує її у видачі. Тут
 * стережемо дві обіцянки, які легко дати випадково й не помітити.
 *
 * @see PriceValidUntilIsNeverStaleTest — там протилежний бік тієї ж дати.
 */
class OfferClaimsAreHonestTest extends TestCase
{
    /**
     * Нуль у `shippingRate` означає безкоштовну доставку. У магазині її рахує
     * перевізник за вагою й габаритами, тож жодного сталого числа тут бути не
     * може. Google цього вузла не вимагає, а от сніпет «безкоштовна доставка»
     * покупець побачить і прийде з іншим очікуванням.
     */
    #[DataProvider('productTemplateProvider')]
    public function testShippingRateIsNeverAHardcodedNumber(string $path): void
    {
        $source = $this->withoutComments(file_get_contents($path));

        if (!preg_match('~itemprop\s*=\s*"shippingRate"~', $source)) {
            $this->addToAssertionCount(1);

            return;
        }

        $block = $this->nodeAfter($source, 'shippingRate');

        $this->assertDoesNotMatchRegularExpression(
            '~content\s*=\s*"\d+(\.\d+)?"~',
            $block,
            sprintf(
                '%s: shippingRate заявляє стале число — вартість доставки рахує перевізник, '
                . 'а нуль обіцяє безкоштовну',
                $this->themeName($path)
            )
        );
    }

    /**
     * `validFrom` мусить стояти поруч із `priceValidUntil`: перше каже, відколи
     * ціна діє, друге — доки. Одне без одного лишає в Search Console попередження
     * про неповний `offers`.
     */
    #[DataProvider('productTemplateProvider')]
    public function testValidFromAccompaniesPriceValidUntil(string $path): void
    {
        $source = $this->withoutComments(file_get_contents($path));

        if (!str_contains($source, 'itemprop="priceValidUntil"')) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertStringContainsString(
            'itemprop="validFrom"',
            $source,
            sprintf('%s: є priceValidUntil, але немає validFrom', $this->themeName($path))
        );
    }

    /**
     * Дата початку дії ціни в майбутньому — це пропозиція, яка ще не почалась.
     */
    #[DataProvider('productTemplateProvider')]
    public function testValidFromIsNotComputedForward(string $path): void
    {
        $source = $this->withoutComments(file_get_contents($path));

        if (!preg_match('~itemprop\s*=\s*"validFrom".*~', $source, $found)) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertStringNotContainsString(
            'now +',
            $found[0],
            sprintf('%s: validFrom рахується вперед — ціна ще не почала діяти', $this->themeName($path))
        );
    }

    /**
     * Від `itemprop` до закриття його елемента. Потрібне, щоб перевіряти саме
     * вміст вузла, а не весь шаблон.
     */
    private function nodeAfter(string $source, string $prop): string
    {
        $start = (int)strpos($source, sprintf('itemprop="%s"', $prop));
        $end   = strpos($source, '</div>', $start);

        return substr($source, $start, $end === false ? 400 : $end - $start);
    }

    /**
     * Пояснення до правил цілком законно згадують і нулі, і назви полів.
     */
    private function withoutComments(string $source): string
    {
        return preg_replace('~\{\*.*?\*\}~s', '', $source);
    }

    private function themeName(string $path): string
    {
        return basename(dirname(dirname($path)));
    }

    public static function productTemplateProvider(): array
    {
        $found = [];
        foreach (glob(dirname(__DIR__, 2) . '/design/*/html/product.tpl') ?: [] as $path) {
            $found[basename(dirname(dirname($path)))] = [$path];
        }

        return $found;
    }
}
