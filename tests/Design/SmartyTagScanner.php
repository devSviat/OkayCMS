<?php


namespace Design;


/**
 * Витягує з шаблону вміст Smarty-тегів, щоб перевірки не спрацьовували на прозі
 * в коментарях і на JS всередині <script>.
 *
 * Регулярка виду \{[^{}]*\} для цього не годиться: вона не бачить вкладених тегів
 * (`{$smarty.get.{$f@key}}`), тож усе, що всередині таких, лишалось би без нагляду.
 */
final class SmartyTagScanner
{
    /**
     * @return string[] вміст кожного тега разом із дужками
     */
    public static function tags(string $source): array
    {
        // {* коментар *} - не код, у ньому може бути будь-яка згадка.
        $source = preg_replace('~\{\*.*?\*\}~s', '', $source);

        $tags = [];
        $length = strlen($source);

        for ($i = 0; $i < $length; $i++) {
            if ($source[$i] !== '{') {
                continue;
            }
            // Smarty не вважає тегом `{` перед пробілом чи переносом - цим і
            // відрізняється Smarty-тег від блоку JS або правила CSS.
            if ($i + 1 >= $length || ctype_space($source[$i + 1])) {
                continue;
            }

            $depth = 0;
            for ($j = $i; $j < $length; $j++) {
                if ($source[$j] === '{') {
                    $depth++;
                } elseif ($source[$j] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $tags[] = substr($source, $i, $j - $i + 1);
                        $i = $j;
                        break;
                    }
                }
            }
        }

        return $tags;
    }
}
