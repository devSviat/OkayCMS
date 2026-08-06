<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Корінь сайту — весь репозиторій: nginx-образ копіює дерево застосунку як є.
 * Тому кожен файл, доданий у корінь, за замовчуванням опинявся б у мережі,
 * якби конфіг лишався чорним списком.
 *
 * Цей тест — запобіжник саме проти такого регресу. Він не питає nginx (у CI
 * його немає), а перевіряє два інваріанти:
 *
 *  1. кожен запис у корені репозиторію класифікований явно. Новий файл валить
 *     тест, доки його не віднесуть до публічних або приватних;
 *  2. обидва конфіги — dev-шаблон і приклад у docs/ — тримають білий список:
 *     кореневий location не пробує віддати файл з диска, vendor/ закритий,
 *     скомпільовані шаблони адмінки не є точкою входу.
 *
 * Поведінку на живому оточенні перевіряє блок «Public surface» у
 * dev/bin/smoke.sh і dev/bin/smoke-prod.sh — там реальні коди відповідей.
 */
class PublicSurfaceTest extends TestCase
{
    /**
     * Єдині записи кореня, які віддаються назовні.
     *
     * Додати сюди щось — свідоме рішення опублікувати файл. Для всього іншого
     * достатньо додати запис у PRIVATE_ROOT_ENTRIES.
     */
    private const PUBLIC_ROOT_ENTRIES = [
        'robots.txt',
        // index.php — точка входу, а не файл: сам він віддає 301 на /.
        'index.php',
    ];

    /**
     * Решта кореня. Перелік потрібен не конфігу (той закриває все, що не
     * дозволене), а цьому тесту: щоб поява нового запису була помічена.
     */
    private const PRIVATE_ROOT_ENTRIES = [
        '1DB_changes', 'backend', 'CLAUDE.md', 'compiled', 'composer.json',
        'composer.lock', 'config', 'design', 'dev', 'docs', 'files', 'js_libraries',
        'LICENSE.md', 'ok', 'Okay', 'phpcs.xml.dist', 'phpstan-baseline.neon',
        'phpstan.neon', 'phpunit.xml', 'PRODUCT.md', 'README.md', 'tests', 'vendor',
    ];

    /**
     * Створюються під час роботи, а не лежать у репозиторії, тож у свіжому
     * клоні їх може не бути — у CI саме так і сталось із cache/. Класифіковані
     * вони так само, як приватні; відрізняються лише тим, що перевірка
     * застарілих записів їх не вимагає.
     */
    private const RUNTIME_ROOT_ENTRIES = [
        'cache',
    ];

    private function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Крапкові записи (.git, .env, .github, .claude) не перелічуються: конфіг
     * закриває їх за формою шляху, а не за іменем, і перелік однаково відставав
     * би від реальності.
     */
    private function rootEntries(): array
    {
        $entries = array_values(array_filter(
            scandir($this->repoRoot()),
            static fn($entry) => $entry !== '.' && $entry !== '..' && !str_starts_with($entry, '.')
        ));

        sort($entries);

        return $entries;
    }

    private function classifiedEntries(): array
    {
        return array_merge(
            self::PUBLIC_ROOT_ENTRIES,
            self::PRIVATE_ROOT_ENTRIES,
            self::RUNTIME_ROOT_ENTRIES
        );
    }

    public function testEveryRootEntryIsClassified(): void
    {
        $unknown = array_values(array_diff($this->rootEntries(), $this->classifiedEntries()));

        $this->assertSame(
            [],
            $unknown,
            "У корені репозиторію з'явилось те, чого немає в жодному переліку: "
            . implode(', ', $unknown)
            . ". Віднеси кожен запис до PUBLIC_ROOT_ENTRIES або PRIVATE_ROOT_ENTRIES "
            . "і переконайся, що nginx віддає саме те, що задумано."
        );
    }

    /**
     * Дзеркальна перевірка: перелік не повинен обростати записами, яких у
     * дереві вже немає, — інакше він перестає щось означати.
     */
    public function testClassificationHasNoStaleEntries(): void
    {
        $expected = array_merge(self::PUBLIC_ROOT_ENTRIES, self::PRIVATE_ROOT_ENTRIES);
        $stale = array_values(array_diff($expected, $this->rootEntries()));

        $this->assertSame([], $stale, 'у переліку лишились записи, яких немає в дереві');
    }

    public static function configProvider(): array
    {
        return [
            'dev template' => ['dev/config/nginx/templates/default.conf.template'],
            'docs example' => ['docs/nginx/nginx.conf'],
        ];
    }

    private function config(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        $this->assertIsString($source, $relativePath);

        return $source;
    }

    /**
     * Серце інверсії. `try_files $uri` у кореневому location означав би, що
     * nginx знову пробує віддати з диска будь-який запитаний шлях, і білий
     * список перестав би бути білим списком.
     */
    #[DataProvider('configProvider')]
    public function testRootLocationNeverServesFilesFromDisk(string $path): void
    {
        $source = $this->config($path);

        $this->assertMatchesRegularExpression(
            '#location\s+/\s*\{\s*(\#[^\n]*\n\s*)*try_files\s+/does_not_exist\s+/index\.php#',
            $source,
            "кореневий location у {$path} мусить вести у фронт-контролер, не пробуючи \$uri"
        );
    }

    #[DataProvider('configProvider')]
    public function testVendorTreeIsClosed(string $path): void
    {
        $this->assertMatchesRegularExpression(
            '#location\s+\^~\s*/vendor/\s*\{\s*return\s+404#',
            $this->config($path),
            "{$path} мусить закривати vendor/ префіксним збігом (^~), інакше "
            . "правило за розширенням перебиває заборону"
        );
    }

    #[DataProvider('configProvider')]
    public function testCompiledTemplatesAreNotEntryPoints(string $path): void
    {
        $this->assertMatchesRegularExpression(
            '#location\s+~\s*\^/backend/design/compiled/\s*\{\s*return\s+404#',
            $this->config($path),
            "{$path} мусить закривати скомпільовані шаблони адмінки"
        );
    }

    /**
     * Приклад із docs/ — те, що копіюють до себе на власний хостинг. Якщо він
     * відстає від dev-шаблону, порада з документації відкриває магазин.
     */
    #[DataProvider('allowedTreeProvider')]
    public function testDocsExampleAllowsTheSameTreesAsTheDevTemplate(string $tree): void
    {
        $this->assertStringContainsString(
            $tree,
            $this->config('docs/nginx/nginx.conf'),
            "приклад у docs/nginx/nginx.conf мусить дозволяти те саме дерево, що й dev-шаблон"
        );
        $this->assertStringContainsString(
            $tree,
            $this->config('dev/config/nginx/templates/default.conf.template')
        );
    }

    public static function allowedTreeProvider(): array
    {
        return [
            'design'  => ['^/design/[^/]+/(css|js|images|fonts)/'],
            'cache'   => ['^/cache/(css|js)/'],
            'files'   => ['^/files/(?!originals/)'],
            'modules' => ['^/Okay/Modules/[^/]+/[^/]+/'],
            'root'    => ['location = /robots.txt'],
        ];
    }

    /**
     * Приклад не повинен указувати на шлях, якого в цьому форку немає: з
     * /application/public конфіг не працює взагалі, і це помічають не одразу.
     */
    public function testDocsExamplePointsAtTheRealRoot(): void
    {
        $source = $this->config('docs/nginx/nginx.conf');

        $this->assertStringNotContainsString('/application/public', $source);
        $this->assertStringContainsString('/var/www/html', $source);
    }
}
