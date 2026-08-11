<?php

namespace Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Entity::add() на невдалій вставці повертає false, а не кидає виняток —
 * Database::query() ловить його, пише в Okay/log і віддає false. Обидва
 * місця, що пишуть з вітрини, це ігнорували: коментар вів на «#comment_»
 * без id, зворотний зв'язок показував «повідомлення надіслано».
 *
 * Перевіряється порядок: результат add() перевіряється до всього, що
 * повідомляє про успіх.
 */
class FailedWriteIsNotSuccessTest extends TestCase
{
    #[DataProvider('writeSiteProvider')]
    public function testResultOfAddIsCheckedBeforeSuccessIsAnnounced($file, $add, $success)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);

        $addAt = strpos($source, $add);
        $guardAt = strpos($source, 'if (empty($');
        $successAt = strpos($source, $success);

        $this->assertNotFalse($addAt, $add);
        $this->assertNotFalse($guardAt, 'немає перевірки результату add()');
        $this->assertNotFalse($successAt, $success);

        $this->assertGreaterThan($addAt, $guardAt, 'перевірка стоїть перед add()');
        $this->assertLessThan($successAt, $guardAt, 'успіх оголошується раніше за перевірку');
    }

    public static function writeSiteProvider()
    {
        return [
            'коментар' => [
                'Okay/Helpers/CommentsHelper.php',
                '$commentId = $commentsEntity->add($comment);',
                'Response::redirectTo($this->backUrl(\'#comment_\'',
            ],
            'зворотний зв\'язок' => [
                'Okay/Controllers/FeedbackController.php',
                '$feedbackId = $feedbacksEntity->add($feedback);',
                '$frontPostRedirectGet->flash(',
            ],
        ];
    }

    /**
     * Код помилки без рядка перекладу дав би порожню плашку — видно, що
     * щось не так, і незрозуміло що.
     */
    #[DataProvider('themeProvider')]
    public function testBothThemesRenderTheError($theme)
    {
        $root = dirname(__DIR__, 2) . '/design/' . $theme;

        foreach (['html/product.tpl', 'html/post.tpl', 'html/feedback.tpl'] as $tpl) {
            $this->assertStringContainsString(
                "\$error=='not_saved'",
                file_get_contents($root . '/' . $tpl),
                $theme . '/' . $tpl
            );
        }

        foreach (['ua', 'ru', 'en'] as $lang) {
            $this->assertStringContainsString(
                "\$lang['form_error_not_saved']",
                file_get_contents($root . '/lang/' . $lang . '.php'),
                $theme . '/lang/' . $lang
            );
        }
    }

    public static function themeProvider()
    {
        return ['vibe_shop' => ['vibe_shop'], 'okay_shop' => ['okay_shop']];
    }
}
