<?php

namespace Seo;

use PHPUnit\Framework\TestCase;

/**
 * Роут `blog` має слаг-плейсхолдер `{$url}`, а його префікс живе в patterns,
 * які застосовуються лише при матчингу. Router::generateUrl() зрізає незаповнені
 * плейсхолдери, тож виклик без параметрів віддає корінь сайту — і canonical
 * розділу блога вказував на головну.
 *
 * Помилка мовчазна: сторінка віддає 200 і виглядає нормально, видно її лише в
 * HTML. Тест тримає інваріант на рівні джерел, бо ядро підтягується з форку
 * цілими файлами і правку може затерти непомітно.
 *
 * Поведінку перевірено руками через curl; що саме — у повідомленні коміта.
 */
class BlogCanonicalTest extends TestCase
{
    private const CONTROLLER = 'Okay/Controllers/BlogController.php';

    private function source(): string
    {
        $path = __DIR__ . '/../../' . self::CONTROLLER;
        $this->assertFileExists($path, self::CONTROLLER . ' переїхав — тест треба оновити');

        return file_get_contents($path);
    }

    public function testBlogCanonicalPassesUrlExplicitly(): void
    {
        $this->assertMatchesRegularExpression(
            "~generateUrl\(\s*'blog'\s*,\s*\[\s*'url'\s*=>~",
            $this->source(),
            'canonical розділу блога має отримувати url явно'
        );
    }

    public function testBlogCanonicalNeverCalledWithEmptyParams(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            "~generateUrl\(\s*'blog'\s*,\s*\[\s*\]~",
            $this->source(),
            "generateUrl('blog', []) віддає корінь сайту — canonical блога стане головною"
        );
    }

    /**
     * Фолбек префікса роута дивився в неоголошену змінну: перевірка завжди
     * істинна, налаштування ігнорується, а на PHP 8.5 це ще й warning.
     */
    public function testRoutePrefixFallbackChecksTheVariableItAssigns(): void
    {
        $source = $this->source();

        // Через регексп, а не точний рядок: інакше тест падав би від переносу
        // дужки чи зайвого пробілу, а перевпроваджений баг у такому вигляді
        // навпаки лишився б непоміченим.
        $this->assertMatchesRegularExpression(
            '~empty\(\s*\$prefixRoute\s*\)~',
            $source,
            'фолбек префікса роута має перевіряти змінну, якій щойно присвоїв значення'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~empty\(\s*\$prefix\s*\)~',
            $source,
            '$prefix у BlogController не оголошується — перевірка завжди істинна'
        );
    }
}
