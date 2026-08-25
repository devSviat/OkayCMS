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
 * Тест тримає одне: жоден фронтовий шаблон пагінації більше не лінкує page=all.
 * Він НЕ прикриває сам фатал — стеля в paginate() досі окрема задача, і
 * налаштування robots_catalog_page_all тут не рятує: MetaRobotsHelper викликають
 * лише контролери каталогу, а на фатальному URL мета-тег не встигає вийти.
 */
class PageAllNotLinkedTest extends TestCase
{

    #[DataProvider('frontPaginationProvider')]
    public function testPageAllLinkIsNeverUnconditional(string $path): void
    {
        // Регексп, а не підрядок: {url page='all'} і {furl page="all"} —
        // те саме посилання, і літеральний пошук їх би пропустив.
        $source = file_get_contents($path);
        if (!preg_match('~page\s*=\s*[\'"]?all~', $source)) {
            // Кнопки немає взагалі — саме так у темах Broken. Стерегти нічого.
            $this->assertStringNotContainsString('page=all', $source);

            return;
        }

        // Якщо кнопка є, вона мусить зникати разом із самою можливістю:
        // при вимкненому page-all адреса віддає ту саму сторінку, тож напис
        // обіцяв би всі товари й не давав нічого.
        $this->assertMatchesRegularExpression(
            '~\{if[^}]*page_all_enabled[^}]*\}~',
            $source,
            'посилання «всі» без умови: при вимкненому page-all воно веде на ту саму сторінку'
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
            'у Sviat/Promo зʼявився власний шаблон пагінації — його треба додати у FRONT_PAGINATION'
        );
    }

    /**
     * Лише `chpu_pagination.tpl` — каталог і бренди, тобто те, що вимикається
     * налаштуванням. `pagination.tpl` (блог, автори, лендинги акцій) сюди не
     * входить свідомо: там page-all лишився на константі й не вимикається, тож
     * безумовна кнопка нічого не обіцяє даремно.
     *
     * Теми шукаються на диску: той самий тест їде у форк, де вони звуться
     * інакше, і жорсткий список ловив би лише наші.
     */
    public static function frontPaginationProvider(): array
    {
        $found = [];
        foreach (glob(dirname(__DIR__, 2) . '/design/*/html/chpu_pagination.tpl') ?: [] as $path) {
            $found[basename(dirname(dirname($path)))] = [$path];
        }

        return $found;
    }
}
