<?php

namespace Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `page-all` віддає весь лістинг одним запитом, і ліміт знімають три незалежні
 * копії однієї логіки: CatalogHelper::paginate(), BlogHelper::paginate() і
 * Sviat\Promo\CampaignLandingController. Заміряно: категорія — 13 МБ,
 * промо-лендинг — 10 МБ, /all-products — фатальне вичерпання пам'яті.
 *
 * Посилання «всі» стояло у видимій пагінації і несло з собою поточний фільтр та
 * сортування, тож робот заходив туди в кожній комбінації фасетів. У sitemap ці
 * URL не потрапляють — посилання було єдиним джерелом відкриття.
 *
 * Тест тримає контракт змінної `page_all_enabled`: там, де page-all вимикається
 * налаштуванням, кнопка мусить зникати разом із ним, а там, де не вимикається,
 * умови бути не повинно — вона була б завжди хибною й тихо ховала робочу кнопку.
 */
class PageAllNotLinkedTest extends TestCase
{
    private const GUARD = 'page_all_enabled';

    /**
     * Каталог і бренди: page-all тут вимикається з адмінки, тож кнопка мусить
     * бути під умовою.
     */
    #[DataProvider('switchablePaginationProvider')]
    public function testPageAllLinkIsNeverUnconditional(string $path): void
    {
        // Регексп, а не підрядок: {url page='all'} і {furl page="all"} —
        // те саме посилання, і літеральний пошук їх би пропустив.
        $source = file_get_contents($path);
        if (!preg_match_all('~page\s*=\s*[\'"]?all~', $source, $links, PREG_OFFSET_CAPTURE)) {
            // Кнопки немає взагалі — так буває в темах. Стерегти нічого.
            $this->assertStringNotContainsString('page=all', $source);

            return;
        }

        $guarded = $this->guardedRanges($source);

        foreach ($links[0] as [$link, $offset]) {
            $this->assertTrue(
                $this->isInside($offset, $guarded),
                sprintf(
                    '%s: посилання «всі» поза умовою {if $%s} — при вимкненому page-all '
                    . 'воно веде на ту саму сторінку',
                    basename($path),
                    self::GUARD
                )
            );
        }
    }

    /**
     * Блог, автори й лендинги акцій: page-all тут лишився на константі й не
     * вимикається, а `page_all_enabled` присвоюють лише CatalogHelper і
     * BrandsHelper. Скопійована сюди умова була б завжди хибною.
     *
     * Що кнопки в цих шаблонах може не бути взагалі — рішення конкретної
     * теми заради краулінгового бюджету, а не інваріант рушія: у штатних темах
     * вона лишається робочою, тож тест її відсутності тут не вимагає.
     */
    #[DataProvider('fixedPaginationProvider')]
    public function testFixedPaginationDoesNotUseTheSwitch(string $path): void
    {
        $this->assertStringNotContainsString(
            self::GUARD,
            file_get_contents($path),
            sprintf(
                '%s: %s тут ніхто не присвоює — умова завжди хибна, і кнопка зникає мовчки',
                basename($path),
                self::GUARD
            )
        );
    }

    /**
     * Модуль Promo рендерить пагінацію з теми, тож окремого шаблону в нього
     * бути не повинно — інакше правка в темі його не покриє.
     */
    public function testPromoModuleHasNoOwnPaginationTemplate(): void
    {
        $found = glob(__DIR__ . '/../../Okay/Modules/Sviat/Promo/design/html/*pagination*.tpl');

        $this->assertSame(
            [],
            $found ?: [],
            'у Sviat/Promo зʼявився власний шаблон пагінації — його теж треба перевіряти'
        );
    }

    public static function switchablePaginationProvider(): array
    {
        return self::themeTemplates('chpu_pagination.tpl');
    }

    public static function fixedPaginationProvider(): array
    {
        return self::themeTemplates('pagination.tpl');
    }

    /**
     * Теми шукаються на диску: той самий тест їде у форк, де вони звуться
     * інакше, і жорсткий список ловив би лише наші.
     */
    private static function themeTemplates(string $name): array
    {
        $found = [];
        foreach (glob(dirname(__DIR__, 2) . '/design/*/html/' . $name) ?: [] as $path) {
            $found[basename(dirname(dirname($path)))] = [$path];
        }

        return $found;
    }

    /**
     * Межі блоків `{if ...page_all_enabled...}` ... `{/if}` з урахуванням
     * вкладеності: без неї достатньо було б згадати змінну будь-де у файлі,
     * і безумовне посилання нижче лишилось би непоміченим.
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
                $stack[] = [$offset, str_contains($tag, self::GUARD)];
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
