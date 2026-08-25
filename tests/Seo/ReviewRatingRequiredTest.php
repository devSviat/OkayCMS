<?php

namespace Seo;

use Okay\Helpers\ValidateHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Оцінка обовʼязкова саме для товару. Відгук без зірок у середній бал не
 * входить, тож товар лишався б без `aggregateRating` попри наявні відгуки — і
 * покупець бачив би текст, а пошуковик не бачив би оцінки.
 *
 * У коментарях до статей оцінки немає взагалі, тож вимагати її там нічого.
 */
class ReviewRatingRequiredTest extends TestCase
{
    public static function casesProvider(): array
    {
        return [
            'товар без оцінки'  => ['product', null, 'empty_rating'],
            'товар з нулем'     => ['product', 0, 'empty_rating'],
            'товар з оцінкою'   => ['product', 4, null],
            'стаття без оцінки' => ['post', null, null],
            'тип не вказано'    => [null, null, null],
        ];
    }

    #[DataProvider('casesProvider')]
    public function testRatingIsRequiredForProductsOnly(?string $objectType, $rating, ?string $expected): void
    {
        $comment = (object)[
            'name'   => 'Іван',
            'text'   => 'Текст відгуку',
            'email'  => 'ivan@example.com',
            'rating' => $rating,
        ];

        $this->assertSame($expected, $this->helper()->getCommentValidateError($comment, $objectType));
    }

    private function helper(): ValidateHelper
    {
        $validator = new class extends \Okay\Core\Validator {
            public function __construct()
            {
            }

            public function isName($name = "", $is_required = false)
            {
                return !empty($name);
            }

            public function isComment($comment = "", $is_required = false)
            {
                return !empty($comment);
            }

            public function isEmail($email = "", $is_required = false)
            {
                return !empty($email);
            }
        };

        $settings = new class extends \Okay\Core\Settings {
            public function __construct()
            {
            }

            public function get($param)
            {
                return null;
            }
        };

        $request = new class extends \Okay\Core\Request {
            public function __construct()
            {
            }

            public function post($name = null, $type = null, $default = null)
            {
                return null;
            }
        };

        $translations = new class extends \Okay\Core\FrontTranslations {
            public function __construct()
            {
            }
        };

        return new ValidateHelper($validator, $settings, $request, $translations);
    }
}
