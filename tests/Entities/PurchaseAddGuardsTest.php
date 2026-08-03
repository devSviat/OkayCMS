<?php

namespace Entities;

use Okay\Core\Request;
use Okay\Requests\CartRequest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PurchasesEntity::add() має три охоронні умови: немає товару, немає
 * замовлення, не вистачає на складі. Усі три викликали ExtenderFacade без
 * return, тобто нічого не зупиняли — виконання йшло далі з $order === null
 * і наприкінці метод оновлював неіснуюче замовлення.
 *
 * Поведінково це тут не перевірити: Entity збирає залежності через
 * ServiceLocator у конструкторі. Тому перевіряється сама властивість —
 * кожен вихід із add() є виходом.
 */
class PurchaseAddGuardsTest extends TestCase
{
    public function testEveryGuardInAddActuallyReturns()
    {
        $body = $this->methodBody('Okay/Entities/PurchasesEntity.php', 'add');

        $discarded = [];
        foreach (explode("\n", $body) as $number => $line) {
            if (strpos($line, 'ExtenderFacade::execute') === false) {
                continue;
            }

            if (!preg_match('~(return|=)\s*ExtenderFacade::execute~', $line)) {
                $discarded[] = trim($line);
            }
        }

        $this->assertSame([], $discarded, 'охорона в add() не зупиняє виконання');
    }

    public function testGuardCountDidNotSilentlyDrop()
    {
        $body = $this->methodBody('Okay/Entities/PurchasesEntity.php', 'add');

        $this->assertSame(
            3,
            substr_count($body, 'ExtenderFacade::execute'),
            'кількість охорон змінилась — перевірити, чи всі досі повертають'
        );
    }

    /**
     * name, email і comment у __orders оголошені NOT NULL. Request::post()
     * на відсутнє поле дає null, і явний null перебиває значення за
     * замовчуванням — вставка падає, а замовлення не створюється.
     */
    #[DataProvider('notNullFieldProvider')]
    public function testMissingNotNullFieldBecomesEmptyString($field)
    {
        $_POST = [];
        $order = (new CartRequest($this->emptyRequest()))->postOrder();

        $this->assertSame('', $order->$field, $field);
    }

    public static function notNullFieldProvider()
    {
        return [
            'name'    => ['name'],
            'email'   => ['email'],
            'comment' => ['comment'],
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
                $values = ['name' => 'Іван', 'email' => 'ivan@example.com', 'comment' => 'подзвоніть'];

                return $values[$name] ?? null;
            }
        };

        $order = (new CartRequest($request))->postOrder();

        $this->assertSame('Іван', $order->name);
        $this->assertSame('ivan@example.com', $order->email);
        $this->assertSame('подзвоніть', $order->comment);
    }

    private function emptyRequest(): Request
    {
        return new class extends Request {
            public function __construct()
            {
            }

            public function post($name = null, $type = null, $default = null)
            {
                return null;
            }
        };
    }

    private function methodBody($file, $method)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $this->assertIsString($source, $file);

        $start = strpos($source, 'public function ' . $method . '(');
        $this->assertIsInt($start, $method . '() не знайдено');

        // До наступного оголошення методу: цього досить, щоб не зачепити
        // сусідні методи з такими самими викликами.
        $next = strpos($source, "\n    public function ", $start + 1);

        return substr($source, $start, $next === false ? null : $next - $start);
    }
}
