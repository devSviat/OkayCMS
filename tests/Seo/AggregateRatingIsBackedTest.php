<?php

namespace Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `aggregateRating` у розмітці стверджує, що товар оцінювали. Надрукований без
 * оцінки, він робить сторінку недостовірною для пошуковика - це вже питання
 * політики Google щодо оманливих структурованих даних, а не косметика.
 *
 * Умову легко загубити: розмітка вписана прямо в тег, і `{if}` там виглядає
 * випадковим форматуванням.
 */
class AggregateRatingIsBackedTest extends TestCase
{
    private const MARKUP = 'aggregateRating';

    /**
     * Кожна згадка `aggregateRating` мусить стояти під умовою, яка перевіряє
     * оцінку: і мікророзмітка в тезі, і JSON-LD нижче. Годиться і `rating`,
     * і `votes` - друге навіть точніше, бо `reviewCount` береться саме звідти.
     */
    #[DataProvider('productTemplateProvider')]
    public function testAggregateRatingIsAlwaysGuardedByARating(string $path): void
    {
        $source = file_get_contents($path);

        if (!preg_match_all('~' . self::MARKUP . '~', $source, $found, PREG_OFFSET_CAPTURE)) {
            $this->addToAssertionCount(1);

            return;
        }

        $guarded = $this->guardedRanges($source);

        foreach ($found[0] as [$match, $offset]) {
            $this->assertTrue(
                $this->isInside($offset, $guarded),
                sprintf(
                    '%s: %s поза умовою про оцінку — сторінка заявить оцінку, якої немає',
                    basename(dirname(dirname($path))) . '/' . basename($path),
                    self::MARKUP
                )
            );
        }
    }

    public static function productTemplateProvider(): array
    {
        $found = [];
        foreach (glob(dirname(__DIR__, 2) . '/design/*/html/product.tpl') ?: [] as $path) {
            $found[basename(dirname(dirname($path)))] = [$path];
        }

        return $found;
    }

    /**
     * Межі блоків `{if ...rating|votes...}` ... `{/if}` з урахуванням вкладеності.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private function guardedRanges(string $source): array
    {
        preg_match_all('~\{if\b~', $source, $opens, PREG_OFFSET_CAPTURE);
        preg_match_all('~\{/if\}~', $source, $closes, PREG_OFFSET_CAPTURE);

        $tokens = [];
        foreach ($opens[0] as [$text, $offset]) {
            $tokens[$offset] = 'open';
        }
        foreach ($closes[0] as [$text, $offset]) {
            $tokens[$offset] = 'close';
        }
        ksort($tokens);

        $ranges = [];
        $stack  = [];
        foreach ($tokens as $offset => $type) {
            if ($type === 'open') {
                $tag = substr($source, $offset, (int)strpos($source, '}', $offset) - $offset + 1);
                $stack[] = [$offset, (bool)preg_match('~rating|votes~i', $tag)];
                continue;
            }

            $opened = array_pop($stack);
            if ($opened !== null && $opened[1]) {
                $ranges[] = [$opened[0], $offset];
            }
        }

        return $ranges;
    }

    /**
     * @param array<int, array{0: int, 1: int}> $ranges
     */
    private function isInside(int $offset, array $ranges): bool
    {
        foreach ($ranges as [$from, $to]) {
            if ($offset > $from && $offset < $to) {
                return true;
            }
        }

        return false;
    }
}
