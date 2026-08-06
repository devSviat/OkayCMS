<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Корінь сайту — весь репозиторій, тож новий файл там за замовчуванням
 * опинявся б у мережі. Тест не питає nginx (у CI його немає), а перевіряє
 * два інваріанти: кожен запис кореня класифікований явно, і всі три конфіги
 * (dev-шаблон, приклад у docs/, .htaccess) тримають білий список.
 *
 * Поведінку перевіряють dev/bin/smoke.sh, smoke-prod.sh і smoke-apache.sh.
 */
class PublicSurfaceTest extends TestCase
{
    /** Єдині записи кореня, які віддаються назовні. */
    private const PUBLIC_ROOT_ENTRIES = [
        'robots.txt',
        // index.php — точка входу, а не файл: сам він віддає 301 на /.
        'index.php',
    ];

    /** Решта кореня. Перелік потрібен тесту, не конфігу. */
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
     * Крапкові записи не перелічуються: після інверсії під них немає жодного
     * дозволу, як і під усе інше.
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

    /** Серце інверсії: `try_files $uri` тут повернув би віддачу з диска. */
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

    /** Приклад із docs/ копіюють на власний хостинг — він не має відставати. */
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
     * На звичайному хостингу .htaccess — єдиний важіль. Ключове тут —
     * переписування на index.php без умови `!-f`: доки воно стосувалось лише
     * неіснуючих шляхів, усе з дерева віддавалось як є.
     */
    public function testHtaccessRoutesEverythingUnknownToTheFrontController(): void
    {
        $source = $this->config('.htaccess');

        $this->assertMatchesRegularExpression(
            '#RewriteCond \$1 !\^\(robots\\\\\.txt\|index\\\\\.php\|favicon#',
            $source,
            'кореневий .htaccess мусить перелічувати дозволене, а не заборонене'
        );
        $this->assertStringContainsString('RewriteRule ^(.*)$ index.php [L,QSA]', $source);
        $this->assertStringContainsString('RewriteRule ^files/originals/ index.php', $source);
    }

    /** Умови мусять іти по $1: з %{REQUEST_URI} ламається установка в підкаталог. */
    public function testHtaccessRulesSurviveASubdirectoryInstall(): void
    {
        $source = $this->config('.htaccess');

        // Саме блок білого списку: інші правила файлу (згортання слешів)
        // працюють з %{REQUEST_URI} правомірно.
        $lines = explode("\n", $source);
        $ruleIndex = null;
        foreach ($lines as $i => $line) {
            if (trim($line) === 'RewriteRule ^(.*)$ index.php [L,QSA]') {
                $ruleIndex = $i;
                break;
            }
        }
        $this->assertNotNull($ruleIndex, 'фінального переписування на index.php не знайдено');

        $conditions = 0;
        for ($i = $ruleIndex - 1; $i >= 0 && str_starts_with(trim($lines[$i]), 'RewriteCond'); $i--) {
            $conditions++;
            $this->assertStringStartsWith(
                'RewriteCond $1',
                trim($lines[$i]),
                'умова білого списку мусить зіставлятися з $1, а не з %{REQUEST_URI}'
            );
        }

        $this->assertGreaterThan(5, $conditions, 'білий список мусить перелічувати дозволені дерева');
    }

    /** З /application/public конфіг не працює взагалі, і це помічають не одразу. */
    public function testDocsExamplePointsAtTheRealRoot(): void
    {
        $source = $this->config('docs/nginx/nginx.conf');

        $this->assertStringNotContainsString('/application/public', $source);
        $this->assertStringContainsString('/var/www/html', $source);
    }
}
