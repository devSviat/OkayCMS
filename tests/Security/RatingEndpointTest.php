<?php

namespace Security;

use Okay\Helpers\RatingHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Бал із цього ендпоінта потрапляє в `aggregateRating` на сторінці товару,
 * тобто в структуровані дані для пошуковика. Значення поза шкалою робить із
 * розмітки оманливу - це вже не косметика, а порушення правил Google.
 *
 * Ендпоінт відкритий і анонімний, тож перевірка мусить бути на боці сервера.
 */
class RatingEndpointTest extends TestCase
{
    private const CONTROLLERS = [
        'Okay/Controllers/ProductController.php',
        'Okay/Controllers/BlogController.php',
    ];

    public static function ratingsOutsideTheScale(): array
    {
        return [
            'захмарне'   => ['999999'],
            'відʼємне'   => ['-5'],
            'нуль'       => ['0'],
            'ледь вище'  => ['5.1'],
            'нечислове'  => ['abc'],
            'порожнє'    => [''],
            'масив'      => [[5]],
        ];
    }

    #[DataProvider('ratingsOutsideTheScale')]
    public function testVoteOutsideTheScaleIsRejected($posted): void
    {
        $helper = new RatingHelper($this->requestWith('product_1', $posted));
        $entity = $this->entityThatMustNotBeTouched();

        $this->assertSame(
            (float)RatingHelper::REJECTED,
            $helper->vote($entity, 'product_', 'test_rating_ids'),
            'голос поза шкалою прийнято — у aggregateRating піде вигадана цифра'
        );
    }

    public static function ratingsWithinTheScale(): array
    {
        return [
            'нижня межа'  => ['1', 1.0],
            'верхня межа' => ['5', 5.0],
            'пів зірки'   => ['4.5', 4.5],
        ];
    }

    /**
     * Дзеркало: звуження не мало зачепити звичайний голос, зокрема дробовий -
     * тема з півзірками шле саме такий.
     */
    #[DataProvider('ratingsWithinTheScale')]
    public function testVoteWithinTheScaleIsAccepted($posted, float $expected): void
    {
        $helper = new RatingHelper($this->requestWith('product_7', $posted));
        $entity = $this->entityWith(7, 0.0, 0);

        $this->assertSame($expected, $helper->vote($entity, 'product_', 'test_rating_ids_' . $posted));
        $this->assertSame(
            ['rating' => $expected, 'votes' => 1],
            $entity->updated,
            'збережено не те, що проголосували'
        );
    }

    /**
     * Межа має бути саме на сервері: JS у темі легко обійти, а тема взагалі
     * може бути чужою.
     */
    #[DataProvider('controllerProvider')]
    public function testRatingEndpointRefusesForeignForms(string $path): void
    {
        $source = $this->source($path);
        $method = $this->ratingMethod($source, $path);

        $this->assertStringContainsString(
            'requireSameOrigin',
            $method,
            sprintf('%s: голос приймається з будь-якої сторінки в інтернеті', basename($path))
        );
    }

    /**
     * `$_POST` повз `Request` - це ще й обхід усього, що на `Request` навісили
     * модулі та розширювачі.
     */
    #[DataProvider('controllerProvider')]
    public function testRatingEndpointDoesNotReadRawPost(string $path): void
    {
        $this->assertStringNotContainsString(
            '$_POST',
            $this->ratingMethod($this->source($path), $path),
            sprintf('%s: сирий $_POST замість Request', basename($path))
        );
    }

    /**
     * Дубль був причиною того, що діра жила у двох місцях одразу: полагоджена
     * в товарі, вона лишалась би в блозі.
     */
    #[DataProvider('controllerProvider')]
    public function testRatingMathLivesInOnePlaceOnly(string $path): void
    {
        $this->assertStringNotContainsString(
            '->votes + 1',
            $this->ratingMethod($this->source($path), $path),
            sprintf('%s: підрахунок середнього повернувся в контролер', basename($path))
        );
    }

    public static function controllerProvider(): array
    {
        $cases = [];
        foreach (self::CONTROLLERS as $path) {
            $cases[basename($path)] = [$path];
        }

        return $cases;
    }

    private function source(string $path): string
    {
        $full = dirname(__DIR__, 2) . '/' . $path;
        $this->assertFileExists($full);

        return (string)file_get_contents($full);
    }

    /** Тіло методу rating(), щоб перевірки не чіплялись за решту контролера. */
    private function ratingMethod(string $source, string $path): string
    {
        $start = strpos($source, 'public function rating(');
        $this->assertNotFalse($start, sprintf('%s: метод rating() зник', basename($path)));

        $end = strpos($source, "\n    }\n", $start);
        $this->assertNotFalse($end, sprintf('%s: не видно кінця методу rating()', basename($path)));

        return substr($source, $start, $end - $start);
    }

    private function requestWith($id, $rating): object
    {
        return new class ($id, $rating) extends \Okay\Core\Request {
            public function __construct(private $id, private $rating)
            {
            }

            public function post($name = null, $type = null, $default = null)
            {
                return $name === 'id' ? $this->id : $this->rating;
            }
        };
    }

    private function entityWith(int $id, float $rating, int $votes): object
    {
        return new class ($id, $rating, $votes) extends \Okay\Entities\ProductsEntity {
            public $updated = null;

            public function __construct(private $id, private $rating, private $votes)
            {
            }

            public function cols($cols = []): self
            {
                return $this;
            }

            public function get($id)
            {
                return (object)['id' => $this->id, 'rating' => $this->rating, 'votes' => $this->votes];
            }

            public function update($id, $object)
            {
                $this->updated = $object;

                return true;
            }
        };
    }

    private function entityThatMustNotBeTouched(): object
    {
        $entity = $this->entityWith(1, 5.0, 88);
        $entity->updated = null;

        return $entity;
    }
}
