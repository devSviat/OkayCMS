<?php


namespace Okay\Modules\OkayCMS\LiqPay;


/**
 * Дротовий протокол LiqPay v3: payload форми, підпис і розбір order_id.
 *
 * Винесено з PaymentForm і CallbackController, де підпис був продубльований
 * інлайном, а кожна гілка контролера завершується exit; — тобто без цього
 * класу підпис не накривався тестом.
 */
class LiqPayProtocol
{
    private const VERSION = 3;

    /**
     * Payload, який іде в браузер покупця.
     *
     * private_key викидається незалежно від того, що передав викликач: саме
     * так дефект і виглядав — одна пара ключ-значення в масиві, звідки ключ
     * мерчанта доїжджав до сторінки оплати у вигляді base64.
     *
     * JSON_UNESCAPED_UNICODE — щоб опис замовлення лишався читабельним у
     * кабінеті мерчанта.
     */
    public function payload(string $publicKey, array $fields): string
    {
        unset($fields['private_key']);

        return base64_encode(json_encode(
            array_merge($fields, ['version' => self::VERSION, 'public_key' => $publicKey]),
            JSON_UNESCAPED_UNICODE
        ));
    }

    public function sign(string $privateKey, string $data): string
    {
        return base64_encode(sha1($privateKey . $data . $privateKey, true));
    }

    public function matches(string $privateKey, string $data, string $signature): bool
    {
        return hash_equals($this->sign($privateKey, $data), $signature);
    }

    /**
     * order_id має вигляд "<id замовлення>-<випадкове число>". Усе, що не має
     * цієї форми, дає 0: на 0 замовлення не знаходиться й колбек відхиляється.
     */
    public function extractOrderId(string $liqPayOrderId): int
    {
        $separator = strpos($liqPayOrderId, '-');
        if ($separator === false) {
            return 0;
        }

        return (int)substr($liqPayOrderId, 0, $separator);
    }
}
