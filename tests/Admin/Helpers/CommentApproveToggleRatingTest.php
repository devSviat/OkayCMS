<?php

namespace Admin\Helpers;

use Okay\Admin\Helpers\BackendCommentsHelper;
use Okay\Entities\BlogEntity;
use Okay\Entities\CommentsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Helpers\RatingHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Перемикач «схвалено» в списку відгуків пише через backend/ajax/update_object.php,
 * повз BackendCommentsHelper::approve(). Без перерахунку бал товару розходився з
 * відгуками: схвалений відгук не входив у середнє, а знятий зі схвалення лишався.
 */
class CommentApproveToggleRatingTest extends TestCase
{
    public function testEachReviewedObjectIsRecalculatedOnce(): void
    {
        $products = $this->createStub(ProductsEntity::class);
        $blog = $this->createStub(BlogEntity::class);

        $comments = $this->createStub(CommentsEntity::class);
        $comments->method('noLimit')->willReturnSelf();
        $comments->method('find')->willReturn([
            (object) ['type' => 'product', 'object_id' => 7],
            (object) ['type' => 'product', 'object_id' => 7],
            (object) ['type' => 'post', 'object_id' => 3],
        ]);

        $calls = [];
        $rating = $this->createMock(RatingHelper::class);
        $rating->expects($this->exactly(2))->method('recalculateFromReviews')
            ->willReturnCallback(function ($entity, $id, $type) use (&$calls, $products, $blog) {
                $calls[] = [$entity === $products ? 'products' : ($entity === $blog ? 'blog' : '?'), $id, $type];
                return 0.0;
            });

        $helper = (new ReflectionClass(BackendCommentsHelper::class))->newInstanceWithoutConstructor();
        foreach (['commentsEntity' => $comments, 'productsEntity' => $products, 'blogEntity' => $blog, 'ratingHelper' => $rating] as $name => $value) {
            (new ReflectionProperty(BackendCommentsHelper::class, $name))->setValue($helper, $value);
        }

        $helper->recalculateRatingsForComments([11, 12, 13]);

        $this->assertSame([['products', 7, 'product'], ['blog', 3, 'post']], $calls);
    }

    /** Метод нічого не дає, якщо ajax-перемикач знову писатиме повз нього. */
    public function testApproveToggleEndpointRecalculates(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/backend/ajax/update_object.php');

        $this->assertMatchesRegularExpression(
            "/\\\$object === 'comment'.*approved.*recalculateRatingsForComments\\(\\[\\\$id\\]\\)/s",
            $source
        );
    }
}
