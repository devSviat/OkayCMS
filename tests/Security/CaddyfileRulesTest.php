<?php

namespace Security;

use PHPUnit\Framework\TestCase;

/**
 * Розбирає dev/config/caddy/Caddyfile у впорядкований список правил і перевіряє,
 * що кожне з них РОБИТЬ, а не що потрібний рядок десь трапляється.
 *
 * Причина існування: підрядкові перевірки в PublicSurfaceTest пропускали мутації,
 * які відкривали дерево. `respond @denied 404` → `file_server @denied` публікує
 * vendor/ і скомпільовані шаблони, і жодна голка цього не бачить — шлях у рядку
 * матчера лишається на місці. Тут натомість звіряються директива, її матчер і
 * позиція в ланцюжку.
 *
 * У Caddy виграє не найточніше правило, як у nginx, а перше, що збіглося, тож
 * позиція — частина політики, а не оформлення.
 */
class CaddyfileRulesTest extends TestCase
{
    private const CADDYFILE = 'dev/config/caddy/Caddyfile';

    /**
     * Дерева, які file_server має право віддавати. Рівно ті самі, що дозволяє
     * docs/nginx/nginx.conf. Нове дерево тут з'явиться лише свідомо — інакше
     * тест впаде, і це його головна робота.
     */
    private const SERVING_MATCHERS = [
        '@robots' => '/robots.txt',
        '@wellknown' => '/.well-known/',
        '@favicon' => '/favicon.',
        '@theme_static' => '/design/',
        '@theme_preview' => '/design/',
        '@cache_bundles' => '/cache/',
        '@js_libraries' => '/js_libraries/',
        '@module_static' => '/Okay/Modules/',
        '@module_preview' => '/Okay/Modules/',
        '@files' => '/files/',
        '@backend_static' => '/backend/design/',
    ];

    /**
     * Матчери віддачі, яким не потрібен перелік розширень: точний файл і
     * дерево ACME-валідації. Так само в nginx.
     */
    private const WITHOUT_EXTENSION_LIST = ['@robots', '@wellknown'];

    /**
     * Розширення, які браузер виконує або показує як документ. Жоден білий
     * список статики не сміє їх пускати: під /files/ це stored XSS на домені
     * магазину, під /design/ і /Okay/Modules/ — віддача сирців.
     */
    private const NEVER_SERVED = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8',
        'tpl', 'htaccess', 'ini', 'yml', 'yaml', 'sh', 'md', 'sql', 'lock',
    ];

    /**
     * Єдині цілі, на які rewrite має право переписувати перед `php`. Перелік
     * явний: `rewrite * {path}` тотожний, і після нього php виконує запитаний
     * шлях, лишаючись формально «захищеною».
     */
    private const REWRITE_TARGETS = [
        '* /index.php',
        '* /backend/index.php',
        '* /backend/design/js/admintooltip/admintooltip.php',
    ];

    /** Дерева, у яких html дозволений — рівно там, де його дозволяє nginx. */
    private const HTML_ALLOWED_UNDER = ['/backend/design/'];

    private function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function source(): string
    {
        $path = $this->repoRoot() . '/' . self::CADDYFILE;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    // ───────────────────────────── розбір ─────────────────────────────

    /**
     * Тіло верхнього route{} — саме воно й є ланцюжком маршрутизації.
     *
     * @return list<array{line:int, text:string}>
     */
    private function routeBodyLines(): array
    {
        $lines = explode("\n", $this->source());

        $start = null;
        $depth = 0;
        $body = [];

        foreach ($lines as $i => $raw) {
            $text = trim($raw);

            if ($start === null) {
                // Верхній route{} — той, що починається з нульового відступу
                // всередині блока сайту, тобто рівно з одного табу.
                if (preg_match('/^\troute \{$/', rtrim($raw, "\r"))) {
                    $start = $i;
                    $depth = 1;
                }
                continue;
            }

            $depth += substr_count($text, '{') - substr_count($text, '}');
            if ($depth <= 0) {
                break;
            }

            $body[] = ['line' => $i + 1, 'text' => $text];
        }

        $this->assertNotNull($start, 'у Caddyfile немає верхнього блока route{}');
        $this->assertNotEmpty($body, 'верхній route{} порожній');

        return $body;
    }

    /**
     * Плоский список директив із глибиною вкладеності та номером рядка.
     * Коментарі й порожні рядки викидаються — вони не є правилами.
     *
     * @return list<array{line:int, depth:int, directive:string, rest:string}>
     */
    private function directives(): array
    {
        $out = [];
        $depth = 0;

        foreach ($this->routeBodyLines() as $entry) {
            $text = $entry['text'];

            if ($text === '' || str_starts_with($text, '#')) {
                continue;
            }

            if ($text === '}') {
                $depth--;
                continue;
            }

            $head = rtrim($text);
            $opens = str_ends_with($head, '{');
            if ($opens) {
                $head = rtrim(substr($head, 0, -1));
            }

            $parts = preg_split('/\s+/', $head, 2);

            $out[] = [
                'line' => $entry['line'],
                'depth' => $depth,
                'directive' => $parts[0],
                'rest' => trim($parts[1] ?? ''),
            ];

            if ($opens) {
                $depth++;
            }
        }

        return $out;
    }

    /**
     * Матчери за іменем: і однорядкові (`@n path /a`), і блокові.
     *
     * @return array<string, list<array{kind:string, pattern:string}>>
     */
    private function matchers(): array
    {
        $out = [];
        $current = null;

        foreach ($this->directives() as $d) {
            if (str_starts_with($d['directive'], '@')) {
                $name = $d['directive'];

                if ($d['rest'] === '') {
                    $current = $name;
                    $out[$name] ??= [];
                    continue;
                }

                $parts = preg_split('/\s+/', $d['rest'], 2);
                $out[$name][] = $this->matcherPart($parts[0], trim($parts[1] ?? ''));
                continue;
            }

            if ($current !== null) {
                $out[$current][] = $this->matcherPart($d['directive'], $d['rest']);
                if (!in_array($d['directive'], ['path', 'path_regexp', 'file', 'expression', 'header_regexp'], true)) {
                    $current = null;
                }
                continue;
            }
        }

        return $out;
    }

    /**
     * @return array{kind:string, pattern:string}
     */
    private function matcherPart(string $kind, string $rest): array
    {
        if ($kind === 'path_regexp') {
            $parts = preg_split('/\s+/', $rest, 2);
            // `path_regexp <name> <pattern>` — ім'я захоплення є лише тоді, коли
            // перший токен не є початком самої регулярки.
            if (count($parts) === 2 && !preg_match('/^[\^(]/', $parts[0])) {
                $rest = $parts[1];
            }
        }

        return ['kind' => $kind, 'pattern' => trim($rest)];
    }

    /** Літеральний префікс регулярки — дерево, якого вона стосується. */
    private function literalPrefix(string $regexp): string
    {
        $re = preg_replace('/^\(\?i\)/', '', $regexp);
        $re = (string) preg_replace('/^\^/', '', (string) $re);

        $out = '';
        for ($i = 0, $n = strlen($re); $i < $n; $i++) {
            $c = $re[$i];

            if ($c === '\\') {
                if (($re[$i + 1] ?? '') === '.') {
                    $out .= '.';
                    $i++;
                    continue;
                }
                break;
            }

            if (strpbrk($c, '[(.*+?|${') !== false) {
                break;
            }

            $out .= $c;
        }

        return $out;
    }

    /** @return list<string> розширення з хвостового `\.(?i:a|b|c)$` */
    private function allowedExtensions(string $regexp): array
    {
        if (!preg_match('/\\\\\.\(\?i:([^)]+)\)\$$/', $regexp, $m)) {
            return [];
        }

        $out = [];
        foreach (explode('|', $m[1]) as $ext) {
            // jpe?g -> jpeg/jpg, woff2? -> woff2, html? -> html; для перевірки
            // достатньо розгорнути необов'язковий символ в обидва боки.
            $out[] = str_replace('?', '', $ext);
            $out[] = (string) preg_replace('/.\?$/', '', $ext);
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Правила ланцюжка в порядку запису — і верхні, і вкладені у route{}.
     * Вкладені важать не менше: file_server, що віддає /files/, лежить саме
     * всередині route @files.
     *
     * @return list<array{line:int, depth:int, directive:string, rest:string, index:int, enclosing:?string}>
     */
    private function chainRules(): array
    {
        $out = [];
        $index = 0;
        $enclosing = [];

        foreach ($this->directives() as $d) {
            $enclosing = array_slice($enclosing, 0, $d['depth']);

            if (str_starts_with($d['directive'], '@')) {
                continue;
            }

            $out[] = [
                'line' => $d['line'],
                'depth' => $d['depth'],
                'directive' => $d['directive'],
                'rest' => $d['rest'],
                'index' => $index++,
                'enclosing' => $enclosing[$d['depth'] - 1] ?? null,
            ];

            if ($d['directive'] === 'route') {
                $enclosing[$d['depth']] = $d['rest'];
            }
        }

        return $out;
    }

    /** @return list<array{line:int, depth:int, directive:string, rest:string, index:int, enclosing:?string}> */
    private function topLevelRules(): array
    {
        return array_values(array_filter($this->chainRules(), static fn (array $r): bool => $r['depth'] === 0));
    }

    /**
     * Дерева, яких стосується правило: з власного матчера, а якщо його немає —
     * з матчера чи шляху оточуючого route. Правило без обох стосується всього.
     *
     * @param array{rest:string, enclosing:?string} $rule
     * @return list<string>
     */
    private function prefixesOf(array $rule): array
    {
        $scope = str_starts_with($rule['rest'], '@') || str_starts_with($rule['rest'], '/')
            ? $rule['rest']
            : ($rule['enclosing'] ?? '');

        if ($scope === '') {
            return ['/'];
        }

        $token = preg_split('/\s+/', $scope)[0];

        if (!str_starts_with($token, '@')) {
            return [rtrim($token, '*')];
        }

        $out = [];
        foreach ($this->matchers()[$token] ?? [] as $part) {
            if ($part['kind'] === 'path_regexp') {
                $out[] = $this->literalPrefix($part['pattern']);
            } elseif ($part['kind'] === 'path') {
                foreach (preg_split('/\s+/', $part['pattern']) as $pattern) {
                    $out[] = rtrim($pattern, '*');
                }
            }
        }

        return $out === [] ? ['/'] : $out;
    }

    /** Ім'я матчера правила: власне або успадковане від оточуючого route. */
    private function servingMatcherName(array $rule): ?string
    {
        foreach ([$rule['rest'], $rule['enclosing'] ?? ''] as $scope) {
            if (str_starts_with((string) $scope, '@')) {
                return preg_split('/\s+/', (string) $scope)[0];
            }
        }

        return null;
    }

    private function indexOf(string $directive, string $needle): int
    {
        foreach ($this->chainRules() as $rule) {
            if ($rule['directive'] === $directive && str_contains($rule['rest'], $needle)) {
                return $rule['index'];
            }
        }

        $this->fail("у ланцюжку немає правила `$directive` з `$needle`");
    }

    // ───────────────────────────── перевірки ─────────────────────────────

    /**
     * Адмін-API Caddy увімкнений за замовчуванням, а тут вебсервер і PHP — один
     * процес під одним UID: код застосунку дістає до 127.0.0.1:2019 звичайним
     * file_get_contents, а POST /load замінює конфігурацію в памʼяті, тобто
     * обходить увесь білий список нижче. У зв'язці nginx + php-fpm такої
     * поверхні не було взагалі.
     */
    public function testTheCaddyAdminApiIsDisabled(): void
    {
        $source = $this->source();

        // Глобальний блок — від `{` на початку рядка до парної `}` там само;
        // фіксоване вікно тут не годиться, бо довжина коментарів у ньому росте.
        $start = (int) strpos($source, "\n{\n");
        $end = (int) strpos($source, "\n}\n", $start);
        $global = substr($source, $start, $end - $start);

        $this->assertMatchesRegularExpression(
            '/^\s*admin\s+off\s*$/m',
            $global,
            'у глобальному блоці мусить бути `admin off`: інакше будь-який запис у дерево '
            . 'чи SSRF із тілом перетворюється на виконання коду через POST /load'
        );
    }

    /** Сам розбір мусить щось знаходити — інакше решта тестів беззмістовна. */
    public function testTheParserSeesTheWholeChain(): void
    {
        $rules = $this->topLevelRules();

        $this->assertGreaterThan(20, count($rules), 'розбір не побачив ланцюжка правил');
        $this->assertNotEmpty($this->matchers(), 'розбір не побачив жодного матчера');

        $directives = array_column($rules, 'directive');
        foreach (['respond', 'file_server', 'php', 'rewrite', 'redir', 'route'] as $expected) {
            $this->assertContains($expected, $directives, "розбір не побачив директиви `$expected`");
        }
    }

    /**
     * Закриті дерева мусять отримувати відмову, а не віддаватись із диска.
     * Заміна `respond` на `file_server` чи `php` — однорядкова мутація, після
     * якої шлях у матчері лишається на місці.
     */
    public function testClosedTreesAreAnsweredWithRespond(): void
    {
        $closed = ['/vendor/', '/backend/lang/', '/backend/design/compiled/'];
        $matchers = $this->matchers();

        $seen = [];
        foreach ($this->topLevelRules() as $rule) {
            if (!str_starts_with($rule['rest'], '@')) {
                continue;
            }

            $name = preg_split('/\s+/', $rule['rest'])[0];
            foreach ($matchers[$name] ?? [] as $part) {
                $patterns = $part['kind'] === 'path'
                    ? preg_split('/\s+/', $part['pattern'])
                    : [$this->literalPrefix($part['pattern'])];

                foreach ($patterns as $pattern) {
                    foreach ($closed as $tree) {
                        if (!str_starts_with(rtrim($pattern, '*'), $tree)) {
                            continue;
                        }

                        $seen[$tree] = true;
                        $this->assertSame(
                            'respond',
                            $rule['directive'],
                            "рядок {$rule['line']}: $tree мусить закриватись `respond`, "
                            . "а не `{$rule['directive']}`"
                        );
                        $this->assertStringEndsWith(
                            '404',
                            $rule['rest'],
                            "рядок {$rule['line']}: відмова для $tree мусить бути 404"
                        );
                    }
                }
            }
        }

        foreach ($closed as $tree) {
            $this->assertArrayHasKey($tree, $seen, "у ланцюжку немає правила-відмови для $tree");
        }

        // Класові дерева PSR-4 закриті однією регуляркою, тож префіксом їх не
        // знайти — перевіряється сам перелік у ній.
        $psr4 = null;
        foreach ($this->chainRules() as $rule) {
            if ($rule['directive'] !== 'respond' || !str_starts_with($rule['rest'], '@')) {
                continue;
            }
            foreach ($matchers[preg_split('/\\s+/', $rule['rest'])[0]] ?? [] as $part) {
                if (str_contains($part['pattern'], 'Controllers')) {
                    $psr4 = $part['pattern'];
                }
            }
        }

        $this->assertNotNull($psr4, 'немає правила-відмови для класових дерев backend/');
        foreach (['Controllers', 'Helpers', 'Requests', 'Entities'] as $tree) {
            $this->assertStringContainsString($tree, $psr4, "backend/$tree/ мусить бути закритий");
        }

        // Саме дерево backend/design/ віддає статику, тож перевіряється не
        // префікс, а конкретне правило: .php під ним не є точкою входу.
        $phpUnderDesign = null;
        foreach ($this->chainRules() as $rule) {
            foreach ($matchers[preg_split('/\\s+/', $rule['rest'])[0]] ?? [] as $part) {
                if ($part['kind'] === 'path_regexp' && str_contains($part['pattern'], 'backend/design/') 
                    && str_ends_with($part['pattern'], '\\.php$')) {
                    $phpUnderDesign = $rule;
                }
            }
        }

        $this->assertNotNull($phpUnderDesign, 'немає правила про .php під backend/design/');
        $this->assertSame('respond', $phpUnderDesign['directive'], '.php під backend/design/ мусить отримувати відмову');
    }

    /**
     * Фолбек вітрини мусить бути ОСТАННІМ правилом ланцюжка. Попередній тест
     * шукав пару `rewrite * /index.php` + `php` регуляркою по всьому файлу — і
     * знаходив її у вкладеному route для files/originals/, тож фолбек можна
     * було видалити цілком.
     */
    public function testTheStorefrontFallbackIsTheLastRuleInTheChain(): void
    {
        $rules = $this->topLevelRules();

        $last = array_pop($rules);
        $beforeLast = array_pop($rules);

        $this->assertNotNull($last);
        $this->assertNotNull($beforeLast);

        $this->assertSame('php', $last['directive'], 'ланцюжок мусить завершуватись голою php');
        $this->assertSame('', $last['rest'], 'фінальна php не має звужуватись матчером');

        $this->assertSame('rewrite', $beforeLast['directive'], 'перед фінальною php мусить бути rewrite');
        $this->assertSame(
            '* /index.php',
            $beforeLast['rest'],
            'фолбек мусить переписувати на фронт-контролер вітрини'
        );
    }

    /**
     * Модель білого списку: з диска віддається лише те, що описане матчером.
     * Гола `file_server` (як і `php_server`, як і `file_server { pass_thru }`)
     * публікує весь репозиторій.
     */
    public function testNothingServesTheTreeWithoutAMatcher(): void
    {
        foreach ($this->chainRules() as $rule) {
            $this->assertNotSame(
                'php_server',
                $rule['directive'],
                "рядок {$rule['line']}: php_server робить try_files і вмикає file_server — "
                . 'це протилежність білому списку'
            );
        }

        foreach ($this->topLevelRules() as $rule) {
            if ($rule['directive'] !== 'file_server') {
                continue;
            }

            $this->assertStringStartsWith(
                '@',
                $rule['rest'],
                "рядок {$rule['line']}: file_server без матчера віддає з диска будь-який шлях"
            );
        }

        foreach ($this->directives() as $d) {
            if ($d['directive'] !== 'pass_thru') {
                continue;
            }

            $this->fail("рядок {$d['line']}: pass_thru перетворює file_server на наскрізну віддачу");
        }
    }

    /**
     * Кожен матчер, за яким щось віддається з диска, мусить бути прив'язаний до
     * кореня URI. Незакріплена регулярка збігається будь-де в шляху.
     */
    public function testEveryServingMatcherIsAnchoredToTheUriRoot(): void
    {
        $matchers = $this->matchers();
        $checked = 0;

        foreach ($this->topLevelRules() as $rule) {
            if ($rule['directive'] !== 'file_server') {
                continue;
            }

            $name = preg_split('/\s+/', $rule['rest'])[0];
            $this->assertArrayHasKey($name, $matchers, "матчер $name не оголошений");

            foreach ($matchers[$name] as $part) {
                $checked++;

                if ($part['kind'] === 'path_regexp') {
                    $this->assertMatchesRegularExpression(
                        '#^(\(\?i\))?\^/#',
                        $part['pattern'],
                        "матчер $name: регулярка мусить починатись із ^/"
                    );
                    continue;
                }

                $this->assertSame('path', $part['kind'], "матчер $name: несподіваний вид {$part['kind']}");
                foreach (preg_split('/\s+/', $part['pattern']) as $pattern) {
                    $this->assertStringStartsWith('/', $pattern, "матчер $name: шлях мусить починатись зі /");
                    $this->assertNotSame('/*', $pattern, "матчер $name: /* публікує весь корінь");
                }
            }
        }

        $this->assertGreaterThan(5, $checked, 'перевірка не побачила матчерів віддачі');
    }

    /**
     * Дерева, доступні з диска, мусять збігатися з переліком, дозволеним у
     * nginx. Нове дерево (config/, 1DB_changes/, уся тема цілком) не з'явиться
     * непоміченим.
     */
    public function testFileServerReachesOnlyTheApprovedTrees(): void
    {
        $actual = [];

        foreach ($this->chainRules() as $rule) {
            if ($rule['directive'] !== 'file_server') {
                continue;
            }

            $name = $this->servingMatcherName($rule);
            $this->assertNotNull($name, "рядок {$rule['line']}: file_server без матчера");

            $prefixes = $this->prefixesOf($rule);
            $this->assertCount(1, array_unique($prefixes), "матчер $name стосується кількох дерев");

            $actual[$name] = $prefixes[0];
        }

        ksort($actual);
        $expected = self::SERVING_MATCHERS;
        ksort($expected);

        $this->assertSame(
            $expected,
            $actual,
            'перелік того, що віддається з диска, змінився — звірте його з docs/nginx/nginx.conf'
        );
    }

    /**
     * Білі списки статики не сміють пускати те, що виконується або
     * рендериться як документ.
     */
    public function testStaticWhitelistsRefuseActiveContent(): void
    {
        $matchers = $this->matchers();
        $checked = 0;

        foreach ($this->chainRules() as $rule) {
            if ($rule['directive'] !== 'file_server') {
                continue;
            }

            $name = $this->servingMatcherName($rule);
            foreach ($matchers[$name] ?? [] as $part) {
                if ($part['kind'] !== 'path_regexp') {
                    continue;
                }

                $extensions = $this->allowedExtensions($part['pattern']);

                // Білий список без хвостового переліку розширень — це вже не
                // білий список: `^/files/.+\..*$` пускає .php так само, як .png.
                $this->assertNotEmpty(
                    $extensions,
                    "матчер $name мусить закінчуватись переліком розширень"
                );

                $checked++;
                foreach (self::NEVER_SERVED as $forbidden) {
                    $this->assertNotContains(
                        $forbidden,
                        $extensions,
                        "матчер $name пускає .$forbidden — це віддача сирців або виконуваного вмісту"
                    );
                }

                if (!in_array($this->literalPrefix($part['pattern']), self::HTML_ALLOWED_UNDER, true)) {
                    foreach (['html', 'htm', 'xhtml'] as $document) {
                        $this->assertNotContains(
                            $document,
                            $extensions,
                            "матчер $name пускає .$document — документ на домені магазину"
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(6, $checked, 'перевірка не побачила білих списків із розширеннями');
    }

    /** Точний файл і дерево ACME — єдині матчери віддачі без переліку розширень. */
    public function testOnlyKnownMatchersServeWithoutAnExtensionList(): void
    {
        foreach ($this->matchers() as $name => $parts) {
            if (!array_key_exists($name, self::SERVING_MATCHERS)) {
                continue;
            }

            foreach ($parts as $part) {
                if ($part['kind'] === 'path_regexp') {
                    continue;
                }

                $this->assertContains(
                    $name,
                    self::WITHOUT_EXTENSION_LIST,
                    "матчер $name віддає за шляхом без переліку розширень"
                );
            }
        }
    }

    /**
     * PHP виконується лише там, де шлях уже переписано на фронт-контролер, або
     * де матчер звужує його до перелічених точок входу. Ціль rewrite мусить
     * бути літералом: `rewrite * {path}` тотожний, і після нього php виконує
     * запитаний шлях.
     */
    public function testPhpRunsOnlyKnownEntryPoints(): void
    {
        $matchers = $this->matchers();
        $previous = null;
        $checked = 0;

        foreach ($this->directives() as $d) {
            if ($d['directive'] === 'rewrite') {
                $this->assertDoesNotMatchRegularExpression(
                    '/\{(path|uri|orig_path|orig_uri)\}\s*$/',
                    $d['rest'],
                    "рядок {$d['line']}: rewrite мусить вести на конкретну точку входу, "
                    . 'а не повертати запитаний шлях'
                );
            }

            if ($d['directive'] !== 'php') {
                $previous = $d;
                continue;
            }

            $checked++;

            if ($d['rest'] === '') {
                $this->assertNotNull($previous, "рядок {$d['line']}: гола php без попереднього rewrite");
                $this->assertSame(
                    'rewrite',
                    $previous['directive'],
                    "рядок {$d['line']}: гола php мусить іти одразу після rewrite, "
                    . 'інакше вона виконає запитаний шлях'
                );
                $this->assertContains(
                    $previous['rest'],
                    self::REWRITE_TARGETS,
                    "рядок {$d['line']}: rewrite перед php веде на невідому точку входу"
                );
                $previous = $d;
                continue;
            }

            $name = preg_split('/\s+/', $d['rest'])[0];
            $this->assertArrayHasKey($name, $matchers, "матчер $name не оголошений");

            $kinds = array_column($matchers[$name], 'kind');
            foreach ($matchers[$name] as $part) {
                if ($part['kind'] === 'path') {
                    $this->assertMatchesRegularExpression(
                        '#^/[A-Za-z0-9_/.-]+\.php$#',
                        $part['pattern'],
                        "матчер $name: php за шляхом мусить вказувати на одну точку входу"
                    );
                    continue;
                }

                if ($part['kind'] === 'path_regexp') {
                    // Один сегмент в одній теці й перевірка наявності файла:
                    // ширший матчер повертає наскрізне виконання PHP у дереві.
                    $this->assertMatchesRegularExpression(
                        '#^\^/[A-Za-z0-9_/-]+/\[\^/\]\+\\\\\.php\$$#',
                        $part['pattern'],
                        "матчер $name: регулярка мусить обмежувати php одним сегментом у одній теці"
                    );
                    $this->assertContains(
                        'file',
                        $kinds,
                        "матчер $name: без `file` php намагається виконати відсутній скрипт і дає 500"
                    );
                }
            }

            $previous = $d;
        }

        $this->assertGreaterThan(3, $checked, 'перевірка не побачила викликів php');
    }

    /**
     * Заборони мусять стояти перед дозволами: у Caddy виграє перше правило,
     * що збіглося. Перенесений в кінець блок заборон стає мертвим кодом.
     */
    public function testDenialsComeBeforeTheAllowances(): void
    {
        $allows = [];
        $denies = [];

        foreach ($this->chainRules() as $rule) {
            if (in_array($rule['directive'], ['file_server', 'php'], true)) {
                $allows[] = $rule;
            } elseif ($rule['directive'] === 'respond' && str_ends_with($rule['rest'], '404')) {
                $denies[] = $rule;
            }
        }

        $this->assertNotEmpty($allows, 'у ланцюжку немає жодного дозволу');
        $this->assertNotEmpty($denies, 'у ланцюжку немає жодної відмови');

        foreach ($denies as $deny) {
            foreach ($this->prefixesOf($deny) as $denied) {
                foreach ($allows as $allow) {
                    if ($allow['index'] > $deny['index']) {
                        continue;
                    }

                    foreach ($this->prefixesOf($allow) as $allowed) {
                        // Вужчий дозвіл перед забороною — це виняток-за-порядком
                        // (у Go RE2 немає негативного lookahead), він законний.
                        // А ширший або рівний просто з'їдає заборону.
                        $this->assertFalse(
                            str_starts_with($denied, $allowed),
                            "рядок {$deny['line']}: заборону $denied вже перекрив дозвіл $allowed "
                            . "з рядка {$allow['line']} — вона не спрацює"
                        );
                    }
                }
            }
        }

        // Виняток-за-порядком: обидва тримаються лише на тому, що йдуть раніше
        // за загальне правило (у Go RE2 немає негативного lookahead).
        $this->assertLessThan(
            $this->indexOf('route', '@files'),
            $this->indexOf('route', '/files/originals/*'),
            'files/originals/ мусить оброблятись до загального правила files/'
        );
        $this->assertLessThan(
            $this->indexOf('respond', '@backend_design_php'),
            $this->indexOf('route', 'admintooltip'),
            'виняток для admintooltip мусить стояти до заборони .php під backend/design/'
        );
    }

    /**
     * files/originals/ віддає сторінку 404 магазину, а не голу серверну.
     * Попередня перевірка шукала шлях у 120 символах рядка заборони й реагувала
     * на позицію слова: дописаний у кінець рядка, він її не турбував.
     */
    public function testOriginalsGoToTheFrontControllerAndAreNeverDenied(): void
    {
        $matchers = $this->matchers();

        foreach ($this->topLevelRules() as $rule) {
            if ($rule['directive'] !== 'respond' || !str_starts_with($rule['rest'], '@')) {
                continue;
            }

            $name = preg_split('/\s+/', $rule['rest'])[0];
            foreach ($matchers[$name] ?? [] as $part) {
                $patterns = $part['kind'] === 'path'
                    ? preg_split('/\s+/', $part['pattern'])
                    : [$part['pattern']];

                foreach ($patterns as $pattern) {
                    $this->assertStringNotContainsString(
                        '/files/originals/',
                        $pattern,
                        "рядок {$rule['line']}: originals мусять іти у фронт-контролер, "
                        . 'а не отримувати серверну відмову'
                    );
                }
            }
        }

        $this->assertIsInt(
            $this->indexOf('route', '/files/originals/*'),
            'для files/originals/ мусить бути окремий маршрут у фронт-контролер'
        );
    }
}
