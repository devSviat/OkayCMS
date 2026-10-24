<?php

namespace Entities;

use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Okay\Entities\CommentsEntity;
use Okay\Helpers\CommentsHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Несхвалений коментар відкривався кожному з тим самим IP, що в автора:
 * офіс, мобільний оператор, NAT провайдера. Тепер — лише за id із сесії
 * автора і за його user_id.
 */
class CommentsApprovedFilterTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['own_comment_ids']);
    }

    public function testIpNoLongerOpensUnapproved(): void
    {
        [$sql, $bind] = self::approvedSql(['approved' => 1, 'ip' => '10.0.0.1']);

        self::assertStringNotContainsString('`ip`', $sql);
        self::assertArrayNotHasKey('ip', $bind);
    }

    public function testOwnIdsAndUserOpenOnlyThem(): void
    {
        [$sql, $bind] = self::approvedSql(['approved' => 1, 'own_ids' => [5, '7', 0, -3, 'x'], 'own_user_id' => 42]);

        self::assertStringContainsString('(`c`.`approved` = :approved OR `c`.`id` IN (5,7) OR `c`.`user_id` = :own_user_id)', $sql);
        self::assertSame(42, $bind['own_user_id']);
    }

    /** Гість без власних коментарів: лише схвалені, без «OR» узагалі. */
    public function testGuestSeesOnlyApproved(): void
    {
        [$sql] = self::approvedSql(['approved' => 1, 'own_ids' => [], 'own_user_id' => 0]);

        self::assertStringContainsString('(`c`.`approved` = :approved)', $sql);
    }

    public function testSessionKeepsLastOwnIds(): void
    {
        $helper = (new ReflectionClass(CommentsHelper::class))->newInstanceWithoutConstructor();
        $helper->rememberOwnComment(0);
        for ($id = 1; $id <= 25; $id++) {
            $helper->rememberOwnComment($id);
        }
        $helper->rememberOwnComment(25);

        self::assertSame(range(6, 25), $_SESSION['own_comment_ids']);
    }

    public function testFilterCarriesSessionIdsAndUser(): void
    {
        $_SESSION['own_comment_ids'] = ['9'];
        $reflector = new ReflectionClass(CommentsHelper::class);
        $helper = $reflector->newInstanceWithoutConstructor();
        $reflector->getProperty('user')->setValue($helper, (object)['id' => 3]);

        self::assertSame(
            ['own_ids' => [9], 'own_user_id' => 3],
            $reflector->getMethod('ownCommentsFilter')->invoke($helper)
        );

        $reflector->getProperty('user')->setValue($helper, null);
        self::assertSame(0, $reflector->getMethod('ownCommentsFilter')->invoke($helper)['own_user_id']);
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private static function approvedSql(array $filter): array
    {
        $select = (new AuraQueryFactory('mysql'))->newSelect();
        $select->from('__comments AS c')->cols(['c.id']);

        $entity = new ReflectionClass(CommentsEntity::class);
        $comments = $entity->newInstanceWithoutConstructor();
        $entity->getProperty('select')->setValue($comments, $select);
        $entity->getMethod('filter__approved')->invokeArgs($comments, [$filter['approved'], &$filter]);
        if (isset($filter['ip'])) {
            $entity->getMethod('filter__ip')->invoke($comments, $filter['ip']);
        }

        return [(string)$select, $select->getBindValues()];
    }
}
