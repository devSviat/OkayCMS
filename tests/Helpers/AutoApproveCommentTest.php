<?php

namespace Helpers;

use Okay\Entities\BlogEntity;
use Okay\Entities\ProductsEntity;
use Okay\Helpers\CommentsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Автосхвалення оминає адмінку, а середній бал зʼявляється саме там
 * (BackendCommentsHelper::approve). Тож відгук, схвалений одразу, мусить
 * перерахувати бал сам - інакше він видимий, але в `aggregateRating` його немає.
 */
class AutoApproveCommentTest extends TestCase
{
    /**
     * Бал товару й бал статті лежать у різних таблицях, тож тип обʼєкта
     * вирішує, яку сутність оновлювати.
     */
    #[DataProvider('objectTypeProvider')]
    public function testRatingIsRecalculatedForTheRightEntity(string $objectType, string $expectedEntity): void
    {
        $requested = null;
        $entityFactory = new class($requested) {
            public $requested;
            public function __construct(&$requested) { $this->requested = &$requested; }
            public function get($class) { $this->requested = $class; return (object)['stub' => true]; }
        };

        $captured = null;
        $ratingHelper = new class($captured) {
            public $captured;
            public function __construct(&$captured) { $this->captured = &$captured; }
            public function recalculateFromReviews($entity, $objectId, $objectType = 'product')
            {
                $this->captured = [$objectId, $objectType];
            }
        };

        $reflector = new ReflectionClass(CommentsHelper::class);
        $helper = $reflector->newInstanceWithoutConstructor();
        $reflector->getProperty('entityFactory')->setValue($helper, $entityFactory);
        $reflector->getProperty('ratingHelper')->setValue($helper, $ratingHelper);

        $reflector->getMethod('recalculateRating')->invokeArgs($helper, [$objectType, 7]);

        $this->assertSame($expectedEntity, $requested);
        $this->assertSame([7, $objectType], $captured);
    }

    public static function objectTypeProvider(): array
    {
        return [
            'товар'  => ['product', ProductsEntity::class],
            'стаття' => ['post', BlogEntity::class],
        ];
    }

    /**
     * Налаштування читається з сервісу, а не з параметра контейнера:
     * settingsParameters() фільтрує значення через empty(), тож вимкнене
     * автосхвалення (0) просто не доїхало б до хелпера.
     */
    public function testSettingIsReadFromTheService(): void
    {
        $source = file_get_contents('Okay/Helpers/CommentsHelper.php');

        $this->assertStringContainsString(
            "\$this->settings->get('auto_approved')",
            $source,
            'автосхвалення має читатись через Settings'
        );
        $this->assertStringNotContainsString('{%auto_approved%}', $source);
    }

    /**
     * Найлегша помилка тут - схвалити відгук і забути про бал. Тоді відгук уже
     * видно, а середнє лишається старим, поки хтось не відкриє адмінку.
     */
    public function testApprovalAndRecalculationStayTogether(): void
    {
        $source = file_get_contents('Okay/Helpers/CommentsHelper.php');

        $approveAt = strpos($source, '$comment->approved = 1;');
        $addAt     = strpos($source, '$commentId = $commentsEntity->add($comment);');
        $recalcAt  = strpos($source, '$this->recalculateRating($objectType, $objectId);');

        $this->assertNotFalse($approveAt, 'автосхвалення не виставляє approved');
        $this->assertNotFalse($recalcAt, 'схвалений відгук не перераховує бал');
        $this->assertGreaterThan($approveAt, $addAt, 'approved виставляється після add()');
        $this->assertGreaterThan($addAt, $recalcAt, 'бал рахується до того, як відгук записано');
    }
}
