<?php

namespace Okay\Core\DebugBar\DataCollectors;

use DebugBar\DataFormatter\DataFormatter;

/**
 * Показує підсумкове значення кожного параметра конфіга і те, звідки воно прийшло.
 *
 * Конфіг збирається з кількох файлів (config.php, config.local.php), тому колектор тримає
 * історію присвоєнь на ключ і сплющує її у рядок для рідного VariableListWidget.
 */
class ConfigCollector extends \DebugBar\DataCollector\ConfigCollector
{
    /** @var array<string, list<array{value: mixed, source: string}>> новіше присвоєння першим */
    private array $history = [];

    public function __construct(array $data = [], string $name = 'config')
    {
        parent::__construct($data, $name);

        // Значення ми складаємо в рядок самі, а віджет виводить його через textContent —
        // sf-dump розмітка від дефолтного HtmlDataFormatter тут показалась би як текст.
        $this->setDataFormatter(new DataFormatter());
    }

    public function set(string $name, mixed $value, string $source = ''): void
    {
        if (!isset($this->history[$name])) {
            $this->history[$name] = [];
        }

        array_unshift($this->history[$name], [
            'value'  => $value,
            'source' => $source,
        ]);
    }

    public function reset(): void
    {
        $this->history = [];
    }

    public function collect(): array
    {
        $data = [];
        foreach ($this->history as $name => $changes) {
            $parts = [];
            foreach ($changes as $change) {
                $value = $this->hideMaskedValues([$name => $change['value']])[$name];
                $value = $this->getDataFormatter()->formatVar($value);

                $parts[] = $change['source'] === '' ? $value : "$value ← {$change['source']}";
            }

            $data[$name] = array_shift($parts);
            if ($parts !== []) {
                $data[$name] .= ' (перекрито: ' . implode(', ', $parts) . ')';
            }
        }
        ksort($data);

        return $data;
    }
}
