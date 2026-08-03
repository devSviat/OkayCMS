<?php

namespace Core;

use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\Languages;
use Okay\Core\Notify;
use Okay\Core\Settings;
use Okay\Core\TemplateConfig\FrontTemplateConfig;
use Okay\Entities\CommentsEntity;
use Okay\Helpers\NotifyHelper;
use Okay\Helpers\OrdersHelper;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Умова відсіву була складена так, що при порожньому parent_id гілка з
 * присвоєнням $comment не виконувалась, а наступний доданок умови все одно
 * читав $comment->email — тобто неоголошену змінну.
 */
class NotifyCommentAnswerTest extends TestCase
{
    public function testRootCommentIsSkippedWithoutTouchingAnUndefinedVariable()
    {
        $answer = (object)['id' => 7, 'parent_id' => null, 'message' => 'відповідь'];

        $notify = $this->notifyReturning([7 => $answer]);

        $this->assertFalse($notify->emailCommentAnswerToUser(7));
    }

    public function testMissingParentIsSkipped()
    {
        $answer = (object)['id' => 7, 'parent_id' => 42, 'message' => 'відповідь'];

        $notify = $this->notifyReturning([7 => $answer, 42 => null]);

        $this->assertFalse($notify->emailCommentAnswerToUser(7));
    }

    public function testParentWithoutAnEmailIsSkipped()
    {
        $answer = (object)['id' => 7, 'parent_id' => 42, 'message' => 'відповідь'];
        $parent = (object)['id' => 42, 'email' => '', 'name' => 'Іван'];

        $notify = $this->notifyReturning([7 => $answer, 42 => $parent]);

        $this->assertFalse($notify->emailCommentAnswerToUser(7));
    }

    public function testUnknownAnswerIsSkipped()
    {
        $notify = $this->notifyReturning([7 => null]);

        $this->assertFalse($notify->emailCommentAnswerToUser(7));
    }

    /**
     * @param array<int, object|null> $commentsById
     */
    private function notifyReturning(array $commentsById): Notify
    {
        $comments = $this->createStub(CommentsEntity::class);
        $comments->method('findOne')->willReturnCallback(
            function ($filter) use ($commentsById) {
                $id = (int)($filter['id'] ?? 0);

                return $commentsById[$id] ?? null;
            }
        );

        $entityFactory = $this->createStub(EntityFactory::class);
        $entityFactory->method('get')->willReturnCallback(
            function ($class) use ($comments) {
                return $class === CommentsEntity::class ? $comments : $this->createStub($class);
            }
        );

        $notifyHelper = $this->createMock(NotifyHelper::class);
        $notifyHelper->expects($this->never())->method('needSendEmailCommentAnswerToUser');

        return new Notify(
            $this->createStub(Settings::class),
            $this->createStub(Languages::class),
            $entityFactory,
            $this->createStub(Design::class),
            $this->createStub(FrontTemplateConfig::class),
            $this->createStub(OrdersHelper::class),
            $this->createStub(BackendTranslations::class),
            $this->createStub(FrontTranslations::class),
            $this->createStub(PHPMailer::class),
            $this->createStub(LoggerInterface::class),
            $notifyHelper,
            ''
        );
    }
}
