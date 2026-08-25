<?php

namespace Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `priceValidUntil` у минулому Google читає як протерміновану пропозицію й
 * знімає rich result товару, а в Search Console зʼявляється помилка у звіті
 * Merchant listings.
 *
 * Помилку легко повернути: дата товару (`created`, `last_modify`) під рукою й
 * виглядає доречною, але вона завжди в минулому.
 */
class PriceValidUntilIsNeverStaleTest extends TestCase
{
    /** Поля, чиє значення завжди в минулому. */
    private const PAST_FIELDS = ['created', 'last_modify', 'smarty.now'];

    #[DataProvider('productTemplateProvider')]
    public function testDateIsAlwaysInTheFuture(string $path): void
    {
        $source = file_get_contents($path);

        // Не [^>]*: у Smarty-виразі `$product->created` є `>`, і збіг обірвався б
        // на ньому - саме там і ховається дата, яку шукаємо.
        if (!preg_match_all('~itemprop\s*=\s*"priceValidUntil".*~', $source, $found)) {
            $this->addToAssertionCount(1);

            return;
        }

        foreach ($found[0] as $tag) {
            foreach (self::PAST_FIELDS as $field) {
                $this->assertStringNotContainsString(
                    $field,
                    $tag,
                    sprintf('%s: priceValidUntil бере дату з $%s — вона завжди в минулому', $this->themeName($path), $field)
                );
            }
        }
    }

    /**
     * Ціна на відсутній товар не гарантована, тож дати дійсності в неї немає -
     * і виводити її там не можна взагалі, а не «якусь».
     */
    #[DataProvider('productTemplateProvider')]
    public function testOutOfStockGetsNoDate(string $path): void
    {
        $source = file_get_contents($path);

        if (!preg_match('~\{if \$product->variant->stock > 0\}(.*?)\{else\}(.*?)\{/if\}~s', $source, $branches)) {
            $this->markTestSkipped($this->themeName($path) . ': гілки наявності не знайдено');
        }

        $this->assertStringNotContainsString(
            'priceValidUntil',
            $branches[2],
            $this->themeName($path) . ': відсутній товар отримує priceValidUntil'
        );
    }

    public static function productTemplateProvider(): array
    {
        $found = [];
        foreach (glob(dirname(__DIR__, 2) . '/design/*/html/product.tpl') ?: [] as $path) {
            $found[basename(dirname(dirname($path)))] = [$path];
        }

        return $found;
    }

    private function themeName(string $path): string
    {
        return basename(dirname(dirname($path))) . '/' . basename($path);
    }
}
