<?php

namespace Okay\Core\Import;

/**
 * Приводить завантажений CSV до того, що очікує решта імпорту: UTF-8 без BOM
 * і роздільник ";".
 *
 * Один раз на вході, бо інакше визначений роздільник довелось би тягнути
 * крізь сесію в backend/ajax/import.php і Import::initColumns().
 */
class CsvNormalizer
{
    const BOM = "\xEF\xBB\xBF";

    const TARGET_DELIMITER = ';';

    /** Порядок визначає переможця при однаковій кількості колонок. @var string[] */
    const DELIMITERS = [';', ',', "\t", '|'];

    /**
     * Переписує файл на місці.
     *
     * @return string|false роздільник джерела, або false якщо файл не читається
     */
    public function normalizeFile($path)
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return false;
        }

        $firstLine = $this->stripBom($firstLine);

        // Excel кладе "sep=," окремим рядком перед заголовком.
        $declared = $this->declaredDelimiter($firstLine);
        if ($declared !== null) {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                fclose($handle);
                return false;
            }
        }

        $delimiter = $declared !== null ? $declared : $this->detectDelimiter($firstLine);

        if ($delimiter === self::TARGET_DELIMITER && $declared === null && !$this->hasBom($path)) {
            fclose($handle);
            return $delimiter;
        }

        $rows = [$this->parseLine($firstLine, $delimiter)];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        $out = @fopen($path, 'wb');
        if ($out === false) {
            return false;
        }

        foreach ($rows as $row) {
            fputcsv($out, $row, self::TARGET_DELIMITER, '"', '\\');
        }
        fclose($out);

        return $delimiter;
    }

    public function stripBom($value)
    {
        $value = (string)$value;

        if (strncmp($value, self::BOM, 3) === 0) {
            return substr($value, 3);
        }

        return $value;
    }

    /**
     * За кількістю колонок у заголовку: там рідше трапляються значення з
     * комами всередині, ніж у рядку даних.
     */
    public function detectDelimiter($headerLine)
    {
        $headerLine = $this->stripBom($headerLine);

        $best = self::TARGET_DELIMITER;
        $bestCount = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $count = count($this->parseLine($headerLine, $delimiter));
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * @return string|null символ із підказки Excel "sep=X", якщо вона є
     */
    public function declaredDelimiter($line)
    {
        if (preg_match('~^sep=(.)\s*$~i', trim($this->stripBom((string)$line)), $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function parseLine($line, $delimiter)
    {
        return str_getcsv(rtrim((string)$line, "\r\n"), $delimiter, '"', '\\');
    }

    private function hasBom($path)
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 3);
        fclose($handle);

        return $head === self::BOM;
    }
}
