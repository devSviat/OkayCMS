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

    public function testTranslationsAreAssignedForEveryRequest(): void
    {
        $source = $this->read('backend/index.php');

        $assign = strpos($source, "\$design->assign('btr', \$backendTranslations);");
        $this->assertIsInt($assign, "btr має присвоюватись і без менеджера");

        // Присвоєння мусить бути поза гілкою про менеджера, інакше сторінка
        // входу знову лишиться без перекладів.
        $branch = strpos($source, 'if (!empty($manager)) {');
        $this->assertIsInt($branch);
        $this->assertGreaterThan($branch, $assign);
        $this->assertStringNotContainsString(
            "initTranslations(\$manager->lang);\n    \$design->assign('btr'",
            $source
        );
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
