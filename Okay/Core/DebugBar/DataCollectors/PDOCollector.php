<?php

namespace Okay\Core\DebugBar\DataCollectors;

use DebugBar\DataFormatter\QueryFormatter as LibQueryFormatter;
use Okay\Core\DebugBar\DataFormatter\QueryFormatter;

/**
 * Підставляє наш QueryFormatter — сеттера для нього в бібліотеці немає.
 */
class PDOCollector extends \DebugBar\DataCollector\PDO\PDOCollector
{
    public function getQueryFormatter(): LibQueryFormatter
    {
        if ($this->queryFormatter === null) {
            $this->queryFormatter = new QueryFormatter();
        }

        return $this->queryFormatter;
    }
}
