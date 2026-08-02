<?php

namespace Okay\Core\DebugBar\DataFormatter;

/**
 * Обхід бага php-debugbar 3.8.0: булеве значення прив'язки долітає до
 * quoteBinding(string $binding) і кладе сторінку через TypeError.
 *
 * Апстрім це вже полагодив (php-debugbar/php-debugbar#1072), але у 3.8.0 фікс не встиг.
 * Тут відтворено його поведінку — TRUE/FALSE без лапок. Видалити разом із цим класом,
 * коли вийде 3.8.1.
 */
class QueryFormatter extends \DebugBar\DataFormatter\QueryFormatter
{
    protected function quoteBinding(mixed $binding, ?\PDO $pdo = null): string
    {
        if (is_bool($binding)) {
            return $binding ? 'TRUE' : 'FALSE';
        }

        return parent::quoteBinding($binding, $pdo);
    }
}
