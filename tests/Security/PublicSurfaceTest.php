<?php

namespace Security;

use Okay\Core\Modules\Module;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Корінь сайту — весь репозиторій, тож новий файл там за замовчуванням
 * опинявся б у мережі. Тест не питає сервер (у CI його немає), а перевіряє
 * два інваріанти: кожен запис кореня класифікований явно, і всі три конфіги
 * (dev-шаблон, приклад у docs/, .htaccess) тримають білий список.
 *
 * Поведінку nginx перевіряють dev/bin/smoke.sh і smoke-prod.sh. Для .htaccess
 * автоматичної поведінкової перевірки немає — лише інваріанти нижче; змінюючи
 * його, перевіряй руками на Apache з mod_php.
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
        'composer.lock', 'config', 'design', 'dev', 'docs', 'files',
        'LICENSE.md', 'ok', 'Okay', 'phpcs.xml.dist', 'phpstan-baseline.neon',
        'phpstan.neon', 'phpunit.xml', 'PRODUCT.md', 'README.md', 'tests', 'vendor',
    ];

    /**
     * Можуть бути відсутні у свіжому клоні — у CI саме так і сталось із cache/.
     * Класифіковані вони так само, як приватні; відрізняються лише тим, що
     * перевірка застарілих записів їх не вимагає.
     *
     * cache/ створюється під час роботи. js_libraries/ у репозиторії немає
     * взагалі: його маршрут лишається в .htaccess і в конфігах nginx як місце
     * для сторонніх бібліотек магазину (docs/assets.md), тож каталог може
     * зʼявитись і мусить лишатись класифікованим.
     */
    private const RUNTIME_ROOT_ENTRIES = [
        'cache',
        'js_libraries',
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

    /** Конфіг, за яким працює стек із репозиторію. */
    private const CADDYFILE = 'dev/config/caddy/Caddyfile';

    /**
     * Лишився один nginx-конфіг: приклад у docs/ для тих, хто ставить магазин
     * на власний nginx. Стек із репозиторію обслуговує FrankenPHP, і його
     * конфіг перевіряють окремі тести нижче — синтаксис інший, інваріанти ті самі.
     */
    public static function configProvider(): array
    {
        return [
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

    /**
     * Паритет двох конфігів, які лишились: приклад у docs/ копіюють на власний
     * nginx, а Caddyfile обслуговує стек із репозиторію. Дозволене дерево має
     * бути тим самим — інакше магазин поводиться по-різному залежно від того,
     * як його підняли.
     *
     * Голки різні там, де синтаксис не дозволяє однакових: Go RE2 не підтримує
     * негативний lookahead, тож виняток для files/originals/ у Caddyfile —
     * окреме правило-заборона, а не (?!originals/) у самій регулярці.
     */
    #[DataProvider('allowedTreeProvider')]
    public function testBothConfigsAllowTheSameTrees(string $nginxNeedle, string $caddyNeedle): void
    {
        $this->assertStringContainsString(
            $nginxNeedle,
            $this->config('docs/nginx/nginx.conf'),
            'приклад у docs/nginx/nginx.conf мусить дозволяти те саме дерево, що й Caddyfile'
        );
        $this->assertStringContainsString(
            $caddyNeedle,
            $this->config(self::CADDYFILE),
            'Caddyfile мусить дозволяти те саме дерево, що й приклад у docs/nginx/nginx.conf'
        );
    }

    public static function allowedTreeProvider(): array
    {
        return [
            'design'  => ['^/design/[^/]+/(css|js|images|fonts)/', '^/design/[^/]+/(css|js|images|fonts)/'],
            'cache'   => ['^/cache/(css|js)/', '^/cache/(css|js)/'],
            'files'   => ['^/files/(?!originals/)', '^/files/.+\\.'],
            'modules' => ['^/Okay/Modules/[^/]+/[^/]+/', '^/Okay/Modules/[^/]+/[^/]+/'],
            'root'    => ['location = /robots.txt', 'path /robots.txt'],
        ];
    }

    /**
     * Серце інверсії в термінах Caddy. php_server робить try_files {path} і
     * вмикає file_server — тобто віддає з диска будь-який наявний файл, і весь
     * білий список стає декорацією. Фолбек мусить бути переписуванням на
     * фронт-контролер, без перевірки шляху на диску.
     */
    public function testCaddyfileNeverServesFilesFromDisk(): void
    {
        $source = $this->config(self::CADDYFILE);

        $this->assertDoesNotMatchRegularExpression(
            '#^\s*php_server\b#m',
            $source,
            'php_server вмикає file_server і try_files {path} — це відкриває все дерево'
        );
        $this->assertMatchesRegularExpression(
            '#rewrite\s+\*\s+/index\.php\s*\n\s*php\s*$#m',
            $source,
            'фолбек мусить переписувати на фронт-контролер вітрини, а не пробувати шлях на диску'
        );
    }

    /**
     * Гола директива php виконує запитаний шлях, тож кожен .php у дереві став
     * би точкою входу. Вона допустима лише після rewrite або зі звуженням до
     * однієї точки входу матчером.
     */
    public function testCaddyfilePhpDirectiveIsAlwaysGuarded(): void
    {
        $lines = explode("\n", $this->config(self::CADDYFILE));

        foreach ($lines as $i => $line) {
            if (!preg_match('#^\s*php\s*$#', $line)) {
                continue;
            }

            $previous = '';
            for ($j = $i - 1; $j >= 0; $j--) {
                $candidate = trim($lines[$j]);
                if ($candidate === '' || str_starts_with($candidate, '#')) {
                    continue;
                }
                $previous = $candidate;
                break;
            }

            $this->assertMatchesRegularExpression(
                '#^rewrite\s#',
                $previous,
                'рядок ' . ($i + 1) . ": гола php мусить іти одразу після rewrite, "
                . "інакше вона виконає запитаний шлях"
            );
        }

        $source = implode("\n", $lines);

        // Форма з матчером звужує виконання — теж дозволена, але сам матчер
        // мусить бути прив'язаний до кореня URI: без ^ він ловить збіг будь-де
        // в шляху, і /files/x/backend/ajax/evil.php став би точкою входу.
        preg_match_all('#php\s+@(\w+)#', $source, $uses);
        $this->assertNotEmpty($uses[1], 'звуження php матчером тримає backend/files/ і backend/ajax/');

        foreach (array_unique($uses[1]) as $name) {
            $this->assertMatchesRegularExpression(
                '#@' . $name . '\s+path(_regexp)?\s+(\^|/)#',
                $source,
                "матчер @$name мусить описувати шлях від кореня URI"
            );
        }
    }

    /**
     * У nginx умова зіставлялась із $request_uri, а він містить рядок запиту,
     * тож /backend/index.php?controller=AuthAdmin під `…index\.php$` не
     * підпадав. path_regexp у Caddy бачить лише шлях, тож без окремої умови
     * редірект з'їдає параметри — і вхід в адмінку ламається.
     */
    public function testCaddyfileIndexPhpRedirectKeepsQueryStrings(): void
    {
        $source = $this->config(self::CADDYFILE);

        // Не `[^}]*`: тіло матчера саме містить `}` у плейсхолдері
        // {http.request.uri.query}, тож блок береться за фіксованим вікном.
        $start = strpos($source, '@index_php {');
        $this->assertIsInt($start, 'у Caddyfile не знайдено матчера канонізації index.php');

        $this->assertStringContainsString(
            'uri.query} == ""',
            substr($source, $start, 300),
            'канонізація index.php мусить спрацьовувати лише за порожнього рядка запиту'
        );
    }

    /**
     * Оригінали не віддаються, але це не серверна 404: і nginx (шлях просто не
     * підпадає під білий список files/), і .htaccess (`RewriteRule
     * ^files/originals/ index.php`) шлють їх у фронт-контролер, щоб магазин
     * намалював власну сторінку 404. `respond 404` тут дав би порожню.
     */
    public function testCaddyfileSendsOriginalsToTheFrontController(): void
    {
        $source = $this->config(self::CADDYFILE);

        $start = strpos($source, 'route /files/originals/*');
        $this->assertIsInt($start, 'у Caddyfile немає правила для files/originals/');
        $this->assertStringContainsString(
            'rewrite * /index.php',
            substr($source, $start, 200),
            'files/originals/ мусить іти у фронт-контролер, а не віддавати серверну 404'
        );

        $this->assertStringNotContainsString(
            '/files/originals/* ',
            substr($source, (int) strpos($source, '@denied path'), 120),
            'files/originals/ не має стояти серед шляхів, на які відповідають 404'
        );
    }

    /**
     * Шаблони адмінки б'ють у backend/ajax/*.php напряму. У nginx їх виконує
     * `location ~ \.ph(p\d*|tml)$` під /backend/; без власного правила
     * Caddyfile переписує їх на backend/index.php і замість JSON віддає
     * HTML-сторінку адмінки — з кодом 200, тож на статусі це непомітно.
     */
    public function testCaddyfileKeepsBackendAjaxEntryPoints(): void
    {
        $source = $this->config(self::CADDYFILE);

        $start = strpos($source, '@backend_ajax');
        $this->assertIsInt($start, 'у Caddyfile немає правила для backend/ajax/');
        $this->assertStringContainsString(
            'php @backend_ajax',
            substr($source, $start, 200),
            'матчер backend/ajax/ мусить вести на php, інакше точки входу мовчки зникають'
        );
    }

    /**
     * Матчер описує рівно один сегмент під backend/ajax/. Ендпоінт, покладений
     * глибше, під нього не підпаде — а помітно це стане лише в браузері.
     */
    public function testEveryAjaxEndpointTemplatesAskForIsCoveredByTheMatcher(): void
    {
        $referenced = [];
        $templates = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->repoRoot() . '/backend/design/html')
        );

        foreach ($templates as $file) {
            if ($file->getExtension() !== 'tpl') {
                continue;
            }
            if (preg_match_all('#ajax/(\S+?\.php)#', (string) file_get_contents($file->getPathname()), $m)) {
                $referenced = array_merge($referenced, $m[1]);
            }
        }

        $referenced = array_unique($referenced);
        $this->assertNotEmpty($referenced, 'шаблони адмінки мусять посилатись на ajax-ендпоінти');

        foreach ($referenced as $endpoint) {
            $this->assertFileExists($this->repoRoot() . '/backend/ajax/' . $endpoint);
            $this->assertStringNotContainsString(
                '/',
                $endpoint,
                "backend/ajax/$endpoint лежить глибше за один сегмент і не підпаде під матчер Caddyfile"
            );
        }
    }

    /**
     * У Caddy header — обробник у ланцюжку route, тож усе, записане до
     * термінальної php, лягає й на фолбек. А фолбек тут — HTML-сторінка 404
     * магазину з Set-Cookie: `public, max-age=31536000` на ній конфліктував із
     * `no-store` від PHP, а `default-src 'none'` знімав із неї стилі.
     */
    public function testCaddyfileFilesHeadersDoNotReachTheFrontController(): void
    {
        $source = $this->config(self::CADDYFILE);

        $start = strpos($source, 'route @files {');
        $this->assertIsInt($start, 'у Caddyfile немає маршруту для files/');
        $block = substr($source, $start, 600);

        $php = strpos($block, 'php @files_front');
        $header = strpos($block, 'header {');
        $this->assertIsInt($php, 'у route @files немає виклику php');
        $this->assertIsInt($header, 'у route @files немає блоку header');

        $this->assertGreaterThan(
            $php,
            $header,
            'header мусить стояти після php: інакше Cache-Control і CSP лягають на HTML-фолбек'
        );
    }

    /**
     * Помилку file_server Caddy пише повз обробник header сайту, тож без
     * handle_errors на кожній 404 статики лишався Server: FrankenPHP Caddy.
     */
    public function testCaddyfileStripsTheServerHeaderFromErrorResponses(): void
    {
        $source = $this->config(self::CADDYFILE);

        $start = strpos($source, 'handle_errors {');
        $this->assertIsInt($start, 'без handle_errors 404 від file_server лишає заголовок Server');
        $this->assertStringContainsString(
            '-Server',
            substr($source, $start, 200),
            'handle_errors мусить прибирати Server, інакше -Server на рівні сайту неповний'
        );
    }

    /**
     * У nginx умова канонізації стоїть під `~*`, тобто без урахування
     * регістру: /INDEX.PHP теж мусить згортатись, інакше це другий URL на ту
     * саму сторінку. path_regexp у Caddy регістр враховує, тож потрібен (?i).
     */
    public function testCaddyfileIndexCanonicalisationIgnoresCase(): void
    {
        $source = $this->config(self::CADDYFILE);

        $this->assertStringContainsString('(?i)^(.*/)index\.php$', $source);
        $this->assertStringContainsString('(?i)^(.*/)index\.html', $source);
    }

    public function testCaddyfileClosesTheVendorTree(): void
    {
        $this->assertMatchesRegularExpression(
            '#path[^\n]*\s/vendor/\*#',
            $this->config(self::CADDYFILE),
            'Caddyfile мусить закривати vendor/ окремим правилом-забороною'
        );
    }

    public function testCaddyfileCompiledTemplatesAreNotEntryPoints(): void
    {
        $this->assertMatchesRegularExpression(
            '#path[^\n]*\s/backend/design/compiled/\*#',
            $this->config(self::CADDYFILE),
            'Caddyfile мусить закривати скомпільовані шаблони адмінки'
        );
    }

    /**
     * Заборони мусять стояти перед дозволами: у Caddy виграє не «найточніше»
     * правило, як у nginx, а перше, що збіглося в route.
     */
    public function testCaddyfileDeniesBeforeItAllows(): void
    {
        $source = $this->config(self::CADDYFILE);

        $denyOriginals = strpos($source, '/files/originals/*');
        $allowFiles    = strpos($source, '^/files/.+\\.');

        $this->assertIsInt($denyOriginals, 'заборони на files/originals/ не знайдено');
        $this->assertIsInt($allowFiles, 'дозволу на files/ не знайдено');
        $this->assertLessThan(
            $allowFiles,
            $denyOriginals,
            'files/originals/ мусить закриватись до того, як відкриється files/: '
            . 'Go RE2 не має негативного lookahead, тож порядок — єдиний важіль'
        );

        $allowTooltip = strpos($source, 'admintooltip.js');
        $denyPhp      = strpos($source, '^/backend/design/.*\\.php$');

        $this->assertIsInt($allowTooltip, 'дозволу на admintooltip не знайдено');
        $this->assertIsInt($denyPhp, 'заборони на .php під backend/design/ не знайдено');
        $this->assertLessThan(
            $denyPhp,
            $allowTooltip,
            'єдина дозволена точка входу під backend/design/ мусить оброблятись '
            . 'до загальної заборони .php'
        );
    }

    /**
     * У nginx трійка повторена в кожному локейшені, бо add_header у локейшені
     * замінює успадковані. У Caddy такої семантики немає, тож трійка стоїть
     * один раз на сайті — і мусить там бути.
     */
    public function testCaddyfileSendsBaselineHeadersSiteWide(): void
    {
        $source = $this->config(self::CADDYFILE);

        foreach ([
            'X-Content-Type-Options nosniff',
            'X-Frame-Options SAMEORIGIN',
            'Referrer-Policy strict-origin-when-cross-origin',
        ] as $header) {
            $this->assertStringContainsString(
                '?' . $header,
                $source,
                "Caddyfile мусить ставити «{$header}» на рівні сайту, і саме через `?` — "
                . 'інакше він перетер би заголовок, який уже виставив SecurityHeaders'
            );
        }
    }

    public function testCaddyfileModulePreviewSendsCspForSvg(): void
    {
        $source = $this->config(self::CADDYFILE);

        $found = preg_match(
            '#@module_preview\s+path_regexp[^\n]*\n\s*header\s+@module_preview\s*\{([^}]*)\}#',
            $source,
            $matches
        );
        $this->assertSame(1, $found, "у Caddyfile не знайдено правила прев'ю модуля");
        $this->assertStringContainsString("default-src 'none'; sandbox", $matches[1]);
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

    /**
     * Перелік тримають три конфіги. Розширення, додане в один, решту лишає з
     * битою картинкою в списку модулів.
     */
    #[DataProvider('previewWhitelistProvider')]
    public function testModulePreviewWhitelistMatchesTheCore(string $path): void
    {
        $this->assertSame(
            $this->coreModulePreviewExtensions(),
            $this->configModulePreviewExtensions($path),
            "перелік розширень прев'ю модуля в {$path} розійшовся з "
            . 'Module::fileHasAllowImageExtension()'
        );
    }

    public static function previewWhitelistProvider(): array
    {
        return [
            'caddyfile'    => [self::CADDYFILE],
            'docs example' => ['docs/nginx/nginx.conf'],
            'htaccess'     => ['.htaccess'],
        ];
    }

    /**
     * Ядро питається поведінкою, а не читанням його регулярки. Перелік
     * кандидатів навмисно ширший за дозволене.
     */
    private function coreModulePreviewExtensions(): array
    {
        $candidates = ['avif', 'bmp', 'gif', 'ico', 'jpeg', 'jpg', 'png', 'svg', 'tiff', 'webp'];

        $module = (new ReflectionClass(Module::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(Module::class, 'fileHasAllowImageExtension');

        return array_values(array_filter(
            $candidates,
            static fn(string $ext) => (bool) $method->invoke($module, "preview.{$ext}")
        ));
    }

    /** `jpe?g` розгортається у два розширення — інакше переліки не порівняти. */
    private function configModulePreviewExtensions(string $path): array
    {
        $found = preg_match(
            '#Okay/Modules/\[\^/\]\+/\[\^/\]\+/preview\\\\\.\((?:\?i:)?([^)]+)\)\$#',
            $this->config($path),
            $matches
        );
        $this->assertSame(1, $found, "у {$path} не знайдено дозволу на прев'ю модуля");

        $extensions = [];
        foreach (explode('|', $matches[1]) as $alternative) {
            $extensions[] = str_replace('?', '', $alternative);
            if (str_contains($alternative, '?')) {
                $extensions[] = preg_replace('/.\?/', '', $alternative);
            }
        }

        $extensions = array_unique($extensions);
        sort($extensions);

        return $extensions;
    }

    /**
     * Прев'ю кладе в репозиторій автор модуля, а svg прямою навігацією
     * рендериться як документ і виконує <script>.
     */
    #[DataProvider('configProvider')]
    public function testModulePreviewSendsCspForSvg(string $path): void
    {
        $found = preg_match(
            '#location\s+~\s+\^/Okay/Modules/\[\^/\]\+/\[\^/\]\+/preview\\\\\.[^{]*\{([^}]*)\}#',
            $this->config($path),
            $matches
        );
        $this->assertSame(1, $found, "у {$path} не знайдено локейшена прев'ю модуля");

        $this->assertStringContainsString("default-src 'none'; sandbox", $matches[1], $path);
        $this->assertStringContainsString('X-Content-Type-Options nosniff', $matches[1], $path);
    }

    /**
     * Файл, який сервер віддає з диска, до PHP не доходить, а серед дозволених
     * розширень є html і svg — тобто документи теж.
     */
    private const BASELINE_HEADERS = [
        'add_header X-Content-Type-Options nosniff always;',
        'add_header X-Frame-Options SAMEORIGIN always;',
        'add_header Referrer-Policy strict-origin-when-cross-origin always;',
    ];

    #[DataProvider('configProvider')]
    public function testEveryLocationServingFilesSendsBaselineHeaders(string $path): void
    {
        $serving = array_filter(
            $this->locationBlocks($this->config($path)),
            static fn(array $block) => $block['servesFiles']
        );

        $this->assertNotEmpty($serving, "{$path}: локейшенів, що віддають файли, не знайдено");

        foreach ($serving as $block) {
            foreach (self::BASELINE_HEADERS as $header) {
                // Саме рівно один: другий такий рядок nginx віддає окремим
                // заголовком, а не перезаписом.
                $this->assertSame(
                    1,
                    substr_count($block['body'], $header),
                    "{$path}: {$block['head']} мусить надсилати «{$header}» рівно один раз"
                );
            }
        }
    }

    /** Серверний add_header лишив би без заголовків саме ті локейшени, що мають власні. */
    #[DataProvider('configProvider')]
    public function testBaselineHeadersAreNotSetServerWide(string $path): void
    {
        foreach (self::BASELINE_HEADERS as $header) {
            $this->assertDoesNotMatchRegularExpression(
                '#^    ' . preg_quote($header, '#') . '$#m',
                $this->config($path),
                "{$path}: «{$header}» на рівні server дублює заголовок застосунку"
            );
        }
    }

    /**
     * Розбирає конфіг по глибині дужок. Беруться лише листкові блоки: у
     * контейнера на кшталт `^~ /backend/` власного тіла немає.
     *
     * @return list<array{head: string, body: string, servesFiles: bool}>
     */
    private function locationBlocks(string $source): array
    {
        $blocks = [];
        $lines = explode("\n", $source);
        $total = count($lines);

        foreach ($lines as $index => $line) {
            if (!preg_match('#^\s*location\s+(\S.*?)\s*\{\s*$#', $line, $head)) {
                continue;
            }

            $depth = 1;
            $end = null;
            for ($i = $index + 1; $i < $total; $i++) {
                $depth += substr_count($lines[$i], '{') - substr_count($lines[$i], '}');
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
            $this->assertNotNull($end, "незакритий location: {$head[1]}");

            $body = implode("\n", array_slice($lines, $index + 1, $end - $index - 1));
            if (str_contains($body, 'location')) {
                continue;
            }

            // Віддає з диска все, що не завершується поверненням коду,
            // переписуванням або переходом у фронт-контролер.
            $servesFiles = !preg_match(
                '#\breturn\s|fastcgi_pass|\brewrite\s|try_files\s+/does_not_exist#',
                $body
            );

            $blocks[] = ['head' => $head[1], 'body' => $body, 'servesFiles' => $servesFiles];
        }

        return $blocks;
    }

    /** З /application/public конфіг не працює взагалі, і це помічають не одразу. */
    public function testDocsExamplePointsAtTheRealRoot(): void
    {
        $source = $this->config('docs/nginx/nginx.conf');

        $this->assertStringNotContainsString('/application/public', $source);
        $this->assertStringContainsString('/var/www/html', $source);
    }
}
