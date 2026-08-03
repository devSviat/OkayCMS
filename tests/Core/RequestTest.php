<?php

namespace Core;

use Okay\Core\Request;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Guards Okay\Core\Request against the PHP 8.1 "Passing null to parameter of
 * type string is deprecated" for internal functions: a missing param yields
 * null internally, and preg_replace(null) would emit a deprecation — the
 * (string) cast in get()/post() must prevent it.
 *
 * The container suppresses E_DEPRECATED via error_reporting, so this test
 * installs its own E_DEPRECATED handler that throws, making any null-to-string
 * deprecation a hard failure regardless of the ambient ini settings.
 */
class RequestTest extends TestCase
{
    /**
     * @return mixed
     */
    private function failOnDeprecation(callable $fn)
    {
        set_error_handler(
            static function ($no, $str): bool {
                throw new RuntimeException($str);
            },
            E_DEPRECATED
        );

        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    public function testGetStringWithMissingParam(): void
    {
        unset($_GET['php85_missing_param']);

        /** @var Request $request */
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        $result = $this->failOnDeprecation(
            static fn () => $request->get('php85_missing_param', 'string')
        );

        $this->assertSame('', $result);
    }

    /**
     * get() згортав масив до першого елемента, post() - ні, і віддавав його
     * просто в intval(). А intval(['9999']) - це 1, мовчки. Тобто
     * variant[]=9999 клало в кошик варіант 1.
     *
     * Обидва методи мають поводитись однаково: хто просить скаляр, той не має
     * отримати результат розбору масиву.
     */
    public function testPostCollapsesArraysWhenAScalarTypeIsRequested(): void
    {
        /** @var Request $request */
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        $_POST['php85_array_param'] = ['9999', '7'];
        $_GET['php85_array_param']  = ['9999', '7'];

        try {
            $this->assertSame(9999, $request->post('php85_array_param', 'integer'));
            $this->assertSame(
                $request->get('php85_array_param', 'integer'),
                $request->post('php85_array_param', 'integer'),
                'post() і get() мають однаково зводити масив до скаляра'
            );
        } finally {
            unset($_POST['php85_array_param'], $_GET['php85_array_param']);
        }
    }

    /**
     * Без типу масиви мають проходити як були: на цьому тримається
     * post('amounts') у кошику.
     */
    public function testPostKeepsArraysWhenNoTypeIsRequested(): void
    {
        /** @var Request $request */
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();

        $_POST['php85_amounts'] = ['10' => '3', '11' => '5'];

        try {
            $this->assertSame(['10' => '3', '11' => '5'], $request->post('php85_amounts'));
        } finally {
            unset($_POST['php85_amounts']);
        }
    }
}
