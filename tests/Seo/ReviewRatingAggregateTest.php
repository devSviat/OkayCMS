<?php

namespace Seo;

use Okay\Helpers\RatingHelper;
use PHPUnit\Framework\TestCase;

/**
 * Середній бал товару — похідна від відгуків, і саме він друкується в
 * `aggregateRating`. Тому в підрахунок має входити рівно те, що видно на
 * сторінці: схвалені відгуки з оцінкою.
 */
class ReviewRatingAggregateTest extends TestCase
{
    /**
     * Відгук без зірок — це коментар, а не оцінка. Порахований як нуль, він
     * тягнув би середнє вниз; порахований як голос — роздував би `reviewCount`.
     */
    public function testCommentsWithoutARatingDoNotCount(): void
    {
        $entity = $this->objectEntity();

        $this->helper([
            (object)['rating' => 5],
            (object)['rating' => null],
            (object)['rating' => 3],
        ])->recalculateFromReviews($entity, 7);

        $this->assertSame(['rating' => 4.0, 'votes' => 2], $entity->updated);
    }

    /**
     * Коли оцінок не лишилось, бал обнуляється: шаблон друкує розмітку лише
     * при `rating > 0`, тож товар просто лишається без заявленої оцінки.
     */
    public function testWithoutAnyRatingsTheAggregateIsCleared(): void
    {
        $entity = $this->objectEntity();

        $this->helper([(object)['rating' => null]])->recalculateFromReviews($entity, 7);

        $this->assertSame(['rating' => 0.0, 'votes' => 0], $entity->updated);
    }

    /**
     * У вибірку мусять іти лише схвалені відгуки цього обʼєкта: інакше бал
     * зʼявився б до модерації, і накрутити його міг би будь-хто.
     *
     * `has_parent => false` відсікає відповіді адміна — вони лежать у тій самій
     * таблиці з тим самим `object_id` і відгуками не є.
     */
    public function testOnlyApprovedReviewsOfThisObjectAreAsked(): void
    {
        $helper = $this->helper([], $captured);

        $helper->recalculateFromReviews($this->objectEntity(), 7, 'post');

        $this->assertSame(
            ['object_id' => 7, 'type' => 'post', 'approved' => 1, 'has_parent' => false],
            $captured,
            'фільтр вибірки змінився — у середнє можуть потрапити чужі або несхвалені відгуки'
        );
    }

    /**
     * @param array<int, object> $comments
     */
    private function helper(array $comments, &$captured = null): RatingHelper
    {
        $factory = new class ($comments, $captured) extends \Okay\Core\EntityFactory {
            public function __construct(private array $comments, public &$captured)
            {
            }

            public function get($entityClassName = null, ...$args)
            {
                return new class ($this->comments, $this->captured) {
                    public function __construct(private array $comments, public &$captured)
                    {
                    }

                    public function noLimit()
                    {
                        return $this;
                    }

                    public function find($filter = [])
                    {
                        $this->captured = $filter;

                        return $this->comments;
                    }
                };
            }
        };

        return new RatingHelper($factory);
    }

    private function objectEntity(): object
    {
        return new class extends \Okay\Entities\ProductsEntity {
            public $updated = null;

            public function __construct()
            {
            }

            public function update($id, $object)
            {
                $this->updated = $object;

                return true;
            }
        };
    }
}
