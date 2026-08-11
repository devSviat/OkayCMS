<?php

namespace Requests;

use Okay\Core\Request;
use Okay\Requests\CommonRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Колонки name, email, text у __comments і __feedbacks оголошені NOT NULL.
 * Request::post() на відсутнє поле дає null, і явний null перебиває значення
 * за замовчуванням - MariaDB у strict-режимі відхиляє вставку, add() повертає
 * false, а покупця редиректить так, ніби все збереглось.
 *
 * Дістати це можна темою, у якої у формі відгуку немає поля email: воно
 * необов'язкове і в розмітці, і у валідаторі, тож форма виглядає робочою.
 */
class CommonRequestNotNullFieldsTest extends TestCase
{
    #[DataProvider('notNullFieldProvider')]
    public function testMissingFieldBecomesEmptyString($method, $marker, $field)
    {
        $comment = (new CommonRequest($this->requestWithOnly($marker)))->$method();

        $this->assertSame('', $comment->$field, $field);
    }

    public static function notNullFieldProvider()
    {
        return [
            'comment.name'      => ['postComment', 'comment', 'name'],
            'comment.email'     => ['postComment', 'comment', 'email'],
            'comment.text'      => ['postComment', 'comment', 'text'],
            'feedback.name'     => ['postFeedback', 'feedback', 'name'],
            'feedback.email'    => ['postFeedback', 'feedback', 'email'],
            'feedback.message'  => ['postFeedback', 'feedback', 'message'],
            'subscribe.email'   => ['postSubscribe', 'subscribe', 'email'],
        ];
    }

    public function testSubmittedValuesAreKept()
    {
        $request = new class extends Request {
            public function __construct()
            {
            }

            public function post($name = null, $type = null, $default = null)
            {
                $values = [
                    'comment' => '1',
                    'name'    => 'Іван',
                    'email'   => 'ivan@example.com',
                    'text'    => 'дуже задоволений',
                ];

                return $values[$name] ?? null;
            }
        };

        $comment = (new CommonRequest($request))->postComment();

        $this->assertSame('Іван', $comment->name);
        $this->assertSame('ivan@example.com', $comment->email);
        $this->assertSame('дуже задоволений', $comment->text);
    }

    /**
     * Масив у полі не має проїхати в колонку: text[]=… давав би Array.
     */
    public function testArrayValueBecomesEmptyString()
    {
        $request = new class extends Request {
            public function __construct()
            {
            }

            public function post($name = null, $type = null, $default = null)
            {
                return $name === 'comment' ? '1' : ['зловмисне'];
            }
        };

        $this->assertSame('', (new CommonRequest($request))->postComment()->text);
    }

    private function requestWithOnly($marker): Request
    {
        return new class($marker) extends Request {
            private $marker;

            public function __construct($marker)
            {
                $this->marker = $marker;
            }

            public function post($name = null, $type = null, $default = null)
            {
                return $name === $this->marker ? '1' : null;
            }
        };
    }
}
