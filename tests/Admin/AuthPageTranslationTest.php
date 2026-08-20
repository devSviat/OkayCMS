<?php

namespace Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Сторінка входу єдина рендериться до автентифікації, тому колись увесь її
 * текст був зашитий у розмітку російською: $btr там просто не існував.
 * Тепер він є завжди, і текст мусить лишатись у файлах перекладу.
 */
class AuthPageTranslationTest extends TestCase
{
    private const TEMPLATE = 'backend/design/html/auth.tpl';

    public function testNoVisibleTextIsHardcoded(): void
    {
        $markup = $this->read(self::TEMPLATE);

        // Смартівські коментарі не рендеряться, тож текст у них не рахуємо.
        $markup = preg_replace('~\{\*.*?\*\}~su', '', $markup);

        $this->assertSame(
            [],
            $this->cyrillicLines($markup),
            'у ' . self::TEMPLATE . ' лишився текст поза перекладами'
        );
    }

    /**
     * Присвоєння мусить стояти поза будь-якою гілкою: саме через те, що воно
     * лежало в if (!empty($manager)), сторінка входу роками не мала
     * перекладів. Глибина рахується токенізатором, бо текстовий пошук цього не
     * бачить - переміщення в else лишає рядок на місці.
     */
    public function testTranslationsAreAssignedOutsideAnyBranch(): void
    {
        $depth = $this->assignDepth($this->read('backend/index.php'));

        $this->assertNotNull($depth, "у backend/index.php немає присвоєння btr");
        $this->assertSame(0, $depth, 'btr присвоюється всередині гілки');
    }

    /** @return int|null глибина вкладеності у гілки або null, якщо не знайдено */
    private function assignDepth(string $source): ?int
    {
        $tokens = token_get_all($source);
        // Блоки try/catch/finally, тіла функцій і інтерполяція - не гілки:
        // вони не роблять присвоєння умовним, тож у глибину не рахуються.
        $neutral = [T_TRY, T_CATCH, T_FINALLY, T_FUNCTION, T_FN, T_CLASS];
        $kinds   = [];
        $depth   = 0;
        $nextIsNeutral = false;

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], $neutral, true)) {
                $nextIsNeutral = true;
                continue;
            }

            if ($token === '{') {
                $kinds[] = $nextIsNeutral ? 'neutral' : 'branch';
                $depth += $nextIsNeutral ? 0 : 1;
                $nextIsNeutral = false;
                continue;
            }

            if ($token === '}') {
                $depth -= array_pop($kinds) === 'branch' ? 1 : 0;
                continue;
            }

            if (is_array($token) && ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES)) {
                $kinds[] = 'neutral';
                continue;
            }

            if (!is_array($token) || $token[0] !== T_STRING || $token[1] !== 'assign') {
                continue;
            }

            // ->assign('btr', ...)
            $argument = $tokens[$i + 2] ?? null;
            if (is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING
                && trim($argument[1], "'\"") === 'btr') {
                return $depth;
            }
        }

        return null;
    }

    /**
     * Дубль ключа тихий: виграє останній, а перший зникає без сліду - саме так
     * загубився плейсхолдер поля логіну, бо обидва оголошення auth_form_login
     * означали різне. Поза auth_* дублі теж є, але там це те саме формулювання
     * двічі, тож вони записані в беклог, а не сюди.
     */
    #[DataProvider('languageFileProvider')]
    public function testNoAuthKeyIsDeclaredTwice(string $file): void
    {
        preg_match_all("~^\\\$lang\\['(auth_[a-zA-Z0-9_]+)'\\]~m", $this->read($file), $matches);

        $duplicates = array_keys(array_filter(array_count_values($matches[1]), static fn ($n) => $n > 1));

        $this->assertSame([], $duplicates, $file);
    }

    /** Мову панелі перемикають менеджери, тож набір ключів мусить збігатись. */
    public function testAuthKeysExistInEveryLanguage(): void
    {
        $keys = [];
        foreach (self::languageFileProvider() as $label => $row) {
            preg_match_all("~^\\\$lang\\['(auth_[a-zA-Z0-9_]+)'\\]~m", $this->read($row[0]), $matches);
            sort($matches[1]);
            $keys[$label] = $matches[1];
        }

        $this->assertSame($keys['ua'], $keys['ru']);
        $this->assertSame($keys['ua'], $keys['en']);
    }

    public static function languageFileProvider(): array
    {
        return [
            'ua' => ['backend/lang/ua.php'],
            'ru' => ['backend/lang/ru.php'],
            'en' => ['backend/lang/en.php'],
        ];
    }

    /** @return string[] */
    private function cyrillicLines(string $markup): array
    {
        $found = [];
        foreach (explode("\n", $markup) as $number => $line) {
            if (preg_match('~[\p{Cyrillic}]~u', $line)) {
                $found[] = ($number + 1) . ': ' . trim($line);
            }
        }

        return $found;
    }

    private function read(string $relativePath): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
    }
}
