<?php

namespace Worker;

use Okay\Core\OkayContainer\OkayContainer;
use Okay\Core\OkayContainer\Reference\ParameterReference;
use Okay\Core\OkayContainer\Reference\ServiceReference;
use PHPUnit\Framework\TestCase;

/**
 * Worker mode лишає сервіси в пам'яті між запитами, тож сервіс зі скоупом
 * worker не має права тримати посилання на request-scoped сусіда: інакше
 * покупець отримав би Request, Design або кошик попереднього відвідувача.
 *
 * Зворотний напрямок дозволений: request-scoped сервіс може залежати від
 * теплого. Перевірка тримає це замикання, тож витік через DI неможливий за
 * побудовою, а не за уважністю.
 */
class ServiceScopeGraphTest extends TestCase
{
    public function testWorkerScopedServicesDoNotDependOnRequestScopedOnes(): void
    {
        $services = $this->services();
        $offenders = [];

        foreach ($services as $id => $definition) {
            if ($this->scopeOf($services, $id) !== OkayContainer::SCOPE_WORKER) {
                continue;
            }

            foreach ($this->serviceReferences($definition) as $dependency) {
                if ($this->scopeOf($services, $dependency) !== OkayContainer::SCOPE_WORKER) {
                    $offenders[] = $id . ' -> ' . $dependency;
                }
            }
        }

        $this->assertSame([], $offenders, 'теплий сервіс залежить від request-scoped');
    }

    /**
     * Значення з {%...%} розгортаються через Settings уже під час створення
     * сервіса, тобто теплий сервіс зафіксував би налаштування мовою першого
     * відвідувача. Такий параметр - та сама залежність, лише непомітна в графі.
     */
    public function testWorkerScopedServicesTakeNoSettingsDerivedParameters(): void
    {
        $services   = $this->services();
        $parameters = $this->parameters();
        $offenders  = [];

        foreach ($services as $id => $definition) {
            if ($this->scopeOf($services, $id) !== OkayContainer::SCOPE_WORKER) {
                continue;
            }

            foreach ($this->parameterReferences($definition) as $name) {
                if (!$this->parameterExists($parameters, $name)) {
                    // Нерозпізнане ім'я мовчки вважалося б безпечним, тож воно
                    // теж порушення: або друкарська помилка, або структура,
                    // якої розбір не бачить.
                    $offenders[] = $id . ' -> ' . $name . ' (немає такого параметра)';
                    continue;
                }

                if ($this->holdsSettingsPlaceholder($this->parameterValue($parameters, $name))) {
                    $offenders[] = $id . ' -> ' . $name;
                }
            }
        }

        $this->assertSame([], $offenders, 'теплий сервіс бере параметр із Settings');
    }

    /** Модуль не має права зробити свій сервіс теплим повз ревʼю ядра. */
    public function testModulesDoNotDeclareScope(): void
    {
        $offenders = [];

        foreach (glob(dirname(__DIR__, 2) . '/Okay/Modules/*/*/Init/services.php') as $file) {
            if (str_contains((string) file_get_contents($file), "'scope'")) {
                $offenders[] = str_replace(dirname(__DIR__, 2) . '/', '', $file);
            }
        }

        $this->assertSame([], $offenders);
    }

    /** Розбір мусить бачити і теплі сервіси, і request-scoped - інакше він мовчить. */
    public function testTheParserSeesBothScopes(): void
    {
        $services = $this->services();
        $worker   = 0;

        foreach (array_keys($services) as $id) {
            if ($this->scopeOf($services, $id) === OkayContainer::SCOPE_WORKER) {
                $worker++;
            }
        }

        $this->assertGreaterThan(150, count($services), 'визначень DI підозріло мало');
        $this->assertArrayHasKey(\Okay\Helpers\MainHelper::class, $services, 'helpers.php не змерджено');
        $this->assertArrayHasKey(\Okay\Requests\CartRequest::class, $services, 'requests.php не змерджено');
        $this->assertGreaterThan(0, $worker, 'жоден сервіс не оголошений теплим');
        $this->assertLessThan(count($services), $worker);
    }

    private function scopeOf(array $services, string $id): string
    {
        if (!isset($services[$id])) {
            // Сервіси модулів прив'язуються в рантаймі й теплими не бувають.
            return OkayContainer::SCOPE_REQUEST;
        }

        $scope = $services[$id]['scope'] ?? OkayContainer::SCOPE_REQUEST;

        return $scope === OkayContainer::SCOPE_WORKER
            ? OkayContainer::SCOPE_WORKER
            : OkayContainer::SCOPE_REQUEST;
    }

    /** @return string[] */
    private function serviceReferences(array $definition): array
    {
        $names = [];

        foreach ($this->argumentLists($definition) as $arguments) {
            foreach ($arguments as $argument) {
                if ($argument instanceof ServiceReference) {
                    $names[] = $argument->getName();
                }
            }
        }

        return $names;
    }

    /** @return string[] */
    private function parameterReferences(array $definition): array
    {
        $names = [];

        foreach ($this->argumentLists($definition) as $arguments) {
            foreach ($arguments as $argument) {
                if ($argument instanceof ParameterReference) {
                    $names[] = $argument->getName();
                }
            }
        }

        return $names;
    }

    /** @return array<int, array> аргументи конструктора і всіх calls */
    private function argumentLists(array $definition): array
    {
        $lists = [];

        if (isset($definition['arguments']) && is_array($definition['arguments'])) {
            $lists[] = $definition['arguments'];
        }

        foreach ($definition['calls'] ?? [] as $call) {
            if (isset($call['arguments']) && is_array($call['arguments'])) {
                $lists[] = $call['arguments'];
            }
        }

        return $lists;
    }

    private function parameterValue(array $parameters, string $name)
    {
        $context = $parameters;

        foreach (explode('.', $name) as $token) {
            if (!is_array($context) || !array_key_exists($token, $context)) {
                return null;
            }

            $context = $context[$token];
        }

        return $context;
    }

    private function parameterExists(array $parameters, string $name): bool
    {
        $context = $parameters;

        foreach (explode('.', $name) as $token) {
            if (!is_array($context) || !array_key_exists($token, $context)) {
                return false;
            }

            $context = $context[$token];
        }

        return true;
    }

    /** Значення параметра буває масивом, і {%...%} ховається всередині нього. */
    private function holdsSettingsPlaceholder($value): bool
    {
        if (is_string($value)) {
            return str_contains($value, '{%');
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->holdsSettingsPlaceholder($item)) {
                return true;
            }
        }

        return false;
    }

    private function services(): array
    {
        return require dirname(__DIR__, 2) . '/Okay/Core/config/services.php';
    }

    private function parameters(): array
    {
        return require dirname(__DIR__, 2) . '/Okay/Core/config/parameters.php';
    }
}
