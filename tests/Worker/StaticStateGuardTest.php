<?php

namespace Worker;

use Okay\Core\Worker\RequestScopedState;
use PHPUnit\Framework\TestCase;

/**
 * Статика переживає запит, тож кожна статична властивість форку мусить бути
 * класифікована: або її скидає RequestScopedState, або вона свідомо лишається.
 *
 * Нова статика без класифікації валить цей тест. Саме це перетворює
 * одноразовий аудит на межу, яку не перейти непомітно.
 */
class StaticStateGuardTest extends TestCase
{
    /** Декларації схеми Entity: метадані таблиці, а не стан запиту. */
    private const SCHEMA_PROPERTIES = [
        'fields', 'langFields', 'additionalFields', 'searchFields', 'table',
        'tableAlias', 'langTable', 'langObject', 'alternativeIdField',
        'defaultOrderFields',
    ];

    /** Шляхи до шаблонів пресетів Feeds: рядкові літерали, у рантаймі не пишуться. */
    private const PRESET_TEMPLATE_PROPERTIES = ['settingsTemplate', 'headerTemplate', 'footerTemplate'];

    /**
     * Статика, яка свідомо переживає запит, і чому це безпечно.
     *
     * @var array<string, string>
     */
    private const ALLOWED = [
        'Okay\Core\DebugBar\DebugBar::$bufferedConfigValues' => 'панель відладки; вимагає debug_mode і в worker mode не підтримується',
        'Okay\Core\DebugBar\DebugBar::$debugBar' => 'те саме',
        'Okay\Core\DebugBar\DebugBar::$serviceLocator' => 'те саме',

        'Okay\Core\Modules\Extender\AbstractExtender::$triggers' => 'реєстр розширень; модулі піднімаються раз на процес',
        'Okay\Core\Modules\Extender\ChainExtender::$triggers' => 'те саме',
        'Okay\Core\Modules\Extender\QueueExtender::$triggers' => 'те саме',
        'Okay\Core\Modules\UpdateObject::$objects' => 'реєстр об\'єктів оновлення, заповнюється при бутстрапі модулів',
        'Okay\Core\Modules\Module::$modulesIds' => 'ідентифікатори модулів із БД; змінюються лише перевстановленням',

        'Okay\Core\OkayContainer\OkayContainer::$instance' => 'контейнер живе стільки, скільки процес',
        'Okay\Core\ServiceLocator::$instance' => 'локатор живе стільки, скільки процес',
        'Okay\Core\ServiceLocator::$isSingleton' => 'те саме',

        'Okay\Core\Router::$routes' => 'перелік роутів із конфіга і модулів, не з запиту',
        'Okay\Core\Router::$modulesRoutes' => 'те саме',
        'Okay\Core\Router::$entityFactory' => 'сервіс; конструктор Router перезаписує його на кожному запиті',
        'Okay\Core\Router::$languages' => 'те саме',
        'Okay\Core\Router::$routeFactory' => 'те саме',

        'Okay\Core\Scheduler\Task::$tasksCounter' => 'нумерація задач планувальника; лише CLI',

        'Okay\Core\Security\BackendFileDownloadPolicy::$map' => 'літерал',
        'Okay\Core\Security\SvgSanitizer::$allowedAttributes' => 'літерал',
        'Okay\Core\Security\SvgSanitizer::$allowedElements' => 'літерал',
        'Okay\Core\Translit::$specPairs' => 'літерал',
        'Okay\Core\Translit::$translitPairs' => 'літерал',
    ];

    public function testEveryStaticPropertyIsClassified(): void
    {
        $unclassified = [];

        foreach ($this->staticProperties() as $entry => $file) {
            if ($this->isClassified($entry, $file)) {
                continue;
            }

            $unclassified[] = $entry . ' (' . $file . ')';
        }

        $this->assertSame(
            [],
            $unclassified,
            'нова статика: або скидати її в RequestScopedState, або внести у ALLOWED з причиною'
        );
    }

    /** Перелік дозволених мусить старіти разом із кодом, інакше він перетворюється на сміття. */
    public function testTheAllowListHasNoStaleEntries(): void
    {
        $existing = array_keys($this->staticProperties());
        $stale    = array_values(array_diff(array_keys(self::ALLOWED), $existing));

        $this->assertSame([], $stale, 'у ALLOWED лишились властивості, яких у коді вже немає');
    }

    public function testTheResetRegistryPointsAtRealProperties(): void
    {
        foreach (RequestScopedState::RESET as $class => $properties) {
            $this->assertTrue(class_exists($class), $class);

            foreach ($properties as $property) {
                $this->assertTrue(
                    (new \ReflectionClass($class))->hasProperty($property),
                    $class . '::$' . $property
                );
            }
        }
    }

    /** Реєстр перелічує - reset() мусить дійсно чіпати кожен рядок переліку. */
    public function testResetActuallyClearsEveryRegisteredProperty(): void
    {
        $untouched = [];

        foreach (RequestScopedState::RESET as $class => $properties) {
            foreach ($properties as $name) {
                $property = new \ReflectionProperty($class, $name);

                $sentinel = $this->sentinelFor($property);
                $property->setValue(null, $sentinel);

                RequestScopedState::reset();

                if ($property->getValue() === $sentinel) {
                    $untouched[] = $class . '::$' . $name;
                }
            }
        }

        $this->assertSame([], $untouched, 'RequestScopedState::reset() не прибирає властивість зі свого ж переліку');
    }

    /** Розбір мусить бачити і схему Entity, і решту - інакше він мовчки нічого не перевіряє. */
    public function testTheScannerSeesTheWholeTree(): void
    {
        $properties = $this->staticProperties();

        $this->assertGreaterThan(300, count($properties), 'сканер бачить підозріло мало статики');
        $this->assertArrayHasKey('Okay\Core\Security\SessionNames::$adminLogin', $properties);
        $this->assertArrayHasKey('Okay\Entities\CurrenciesEntity::$table', $properties);
    }

    private function sentinelFor(\ReflectionProperty $property)
    {
        $type = $property->getType();

        if ($type instanceof \ReflectionNamedType && $type->getName() === 'bool') {
            return true;
        }

        return '__sentinel__';
    }

    private function isClassified(string $entry, string $file): bool
    {
        if (isset(self::ALLOWED[$entry])) {
            return true;
        }

        [$class, $property] = explode('::$', $entry);

        foreach (RequestScopedState::RESET as $resetClass => $properties) {
            if ($class === $resetClass && in_array($property, $properties, true)) {
                return true;
            }
        }

        if (in_array($property, self::SCHEMA_PROPERTIES, true)
            && (str_contains($file, '/Entities/') || $file === 'Okay/Core/Entity/Entity.php')) {
            return true;
        }

        if (in_array($property, self::PRESET_TEMPLATE_PROPERTIES, true)
            && str_contains($file, '/Feeds/') && str_contains($file, '/Presets/')) {
            return true;
        }

        return false;
    }

    /** @return array<string, string> "Клас::$властивість" => шлях від кореня репозиторію */
    private function staticProperties(): array
    {
        $root  = dirname(__DIR__, 2);
        $found = [];

        foreach (['Okay', 'backend'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                $path = $file->getPathname();
                if (substr($path, -4) !== '.php') {
                    continue;
                }

                $relative = str_replace($root . '/', '', $path);
                if (preg_match('~(^|/)(compiled|vendor|node_modules)/~', $relative)) {
                    continue;
                }

                foreach ($this->declaredStatics((string) file_get_contents($path)) as $entry) {
                    $found[$entry] = $relative;
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * Токенізатором, а не рефлексією: рефлексія тягне автозавантаження всього
     * дерева, включно з класами модулів, чиї залежності можуть бути відсутні.
     *
     * @return string[]
     */
    private function declaredStatics(string $source): array
    {
        $tokens     = token_get_all($source);
        $namespace  = '';
        $class      = null;
        $classDepth = null;
        $depth      = 0;
        $found      = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if ($token === '{') {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;
                if ($classDepth !== null && $depth < $classDepth) {
                    $class = null;
                    $classDepth = null;
                }
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = $this->readNamespace($tokens, $i);
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $previous = $tokens[$i - 1] ?? null;
                if (is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
                    continue; // ::class
                }

                $class = $this->readClassName($tokens, $i, $namespace);
                $classDepth = $depth + 1;
                continue;
            }

            if ($class === null || $token[0] !== T_STATIC) {
                continue;
            }

            $name = $this->readStaticPropertyName($tokens, $i);
            if ($name !== null) {
                $found[] = $class . '::' . $name;
            }
        }

        return $found;
    }

    private function readNamespace(array $tokens, int $from): string
    {
        $namespace = '';

        for ($j = $from + 1; $j < count($tokens); $j++) {
            if ($tokens[$j] === ';' || $tokens[$j] === '{') {
                break;
            }

            if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                $namespace .= $tokens[$j][1];
            }
        }

        return $namespace;
    }

    private function readClassName(array $tokens, int $from, string $namespace): ?string
    {
        for ($j = $from + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                return ($namespace !== '' ? $namespace . '\\' : '') . $tokens[$j][1];
            }

            if ($tokens[$j] === '{' || $tokens[$j] === '(') {
                break; // анонімний клас
            }
        }

        return null;
    }

    private function readStaticPropertyName(array $tokens, int $from): ?string
    {
        $j = $from + 1;
        while (isset($tokens[$j]) && is_array($tokens[$j])
            && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }

        $next = $tokens[$j] ?? null;
        if (is_array($next) && in_array($next[0], [T_FUNCTION, T_FN, T_DOUBLE_COLON], true)) {
            return null; // static function, static fn, static::
        }

        // між static і змінною лишаються модифікатори видимості й тип
        while (isset($tokens[$j])) {
            $token = $tokens[$j];

            if (is_array($token) && $token[0] === T_VARIABLE) {
                return $token[1];
            }

            if ($token === ';' || $token === '=' || $token === '(') {
                return null;
            }

            $j++;
        }

        return null;
    }
}
