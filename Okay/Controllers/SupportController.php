<?php


namespace Okay\Controllers;


use Okay\Core\Security\AttemptLimiter;
use Okay\Entities\SupportInfoEntity;
use Psr\Log\LoggerInterface;

class SupportController extends AbstractController
{
    /**
     * Ендпойнт публічний і без авторизації: за збігом ключа він перезаписує
     * private_key і public_key у базі, тобто передає канал підтримки іншому
     * власнику. Тому - тільки POST, тільки JSON-об'єкт, звірка ключів
     * constant-time і лічильник невдалих спроб.
     */
    public function checkDomain(
        SupportInfoEntity $supportInfoEntity,
        AttemptLimiter $attemptLimiter,
        LoggerInterface $logger
    ) {
        if (!$this->request->method('POST')) {
            return $this->refuse($logger, 'method_not_allowed', 405);
        }

        $client = $this->clientKey();

        if ($attemptLimiter->tooManyAttempts($client)) {
            return $this->refuse($logger, 'too_many_attempts', 429);
        }

        $info = $supportInfoEntity->getInfo();
        if (empty($info)) {
            return $this->reply(['success' => 0, 'error' => 'empty_local_info']);
        }

        $data = $this->decodeBody();

        $invalidResult = $this->preValidateData($data);
        if (!empty($invalidResult)) {
            $attemptLimiter->registerFailure($client);
            return $this->reply($invalidResult);
        }

        $result = ['success' => 0];
        switch ($data->action) {
            case 'new_keys': {
                if (empty($info->temp_key) || empty($info->temp_time) || strtotime($info->temp_time)+300 < time()) {
                    $supportInfoEntity->updateInfo(['temp_key'=>null, 'temp_time'=>null]);
                    $result['error'] = 'rule_1';
                    break;
                }
                if (!$this->keysMatch($info->temp_key, $data->temp_key ?? null)) {
                    $result['error'] = 'rule_2';
                    break;
                }

                $info->temp_time = strtotime($info->temp_time);
                $supportInfoEntity->updateInfo([
                    'private_key'  => $data->private_key,
                    'public_key'   => $data->public_key,
                    'new_messages' => intval(isset($data->new_messages) ? $data->new_messages : 0),
                    'balance'      => intval(isset($data->balance) ? $data->balance : 0),
                    'temp_key'     => null,
                    'temp_time'    => null
                ]);
                $result = ['success' => 1];
                break;
            }
            case 'receive_info': {
                if (empty($info->public_key) || !$this->keysMatch($info->public_key, $data->key ?? null)) {
                    $result['error'] = 'wrong_key';
                    break;
                }
                $supportInfoEntity->updateInfo([
                    'balance'      => intval(isset($data->balance) ? $data->balance : 0),
                    'new_messages' => $info->new_messages + intval($data->new_messages)
                ]);
                $result = ['success' => 1];
                break;
            }
        }

        if (empty($result['success'])) {
            $attemptLimiter->registerFailure($client);
            $logger->warning('Support endpoint: refused, ' . ($result['error'] ?? 'unknown') . ', client ' . $client);
        } else {
            // Успіх знімає лічильник, інакше рідкісна серія помилок у
            // легітимного викликача накопичувалась би до блокування.
            $attemptLimiter->reset($client);
        }

        return $this->reply($result);
    }

    /**
     * Порівняння сталого часу і без приведення типів: != зрівнював "0" і "0.0"
     * та зливав довжину спільного префікса через час відповіді.
     */
    private function keysMatch($known, $supplied)
    {
        if (!is_string($known) || !is_string($supplied) || $known === '' || $supplied === '') {
            return false;
        }

        return hash_equals($known, $supplied);
    }

    /**
     * @return object|null
     */
    private function decodeBody()
    {
        $raw = $this->request->post();
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw);

        // Саме об'єкт: JSON-масив теж валідний, але полів дії в нього немає.
        return is_object($decoded) ? $decoded : null;
    }

    /**
     * Лічильник ведеться за адресою викликача. Проксі-заголовкам довіри
     * немає - їх підставляє клієнт, і ліміт обходився б одним рядком.
     */
    private function clientKey()
    {
        $address = $_SERVER['REMOTE_ADDR'] ?? '';

        return $address !== '' ? $address : 'unknown';
    }

    private function refuse(LoggerInterface $logger, $error, $statusCode)
    {
        $logger->warning('Support endpoint: refused, ' . $error . ', client ' . $this->clientKey());

        return $this->reply(['success' => 0, 'error' => $error], $statusCode);
    }

    private function reply(array $result, $statusCode = 200)
    {
        if ($statusCode !== 200) {
            $this->response->setStatusCode($statusCode);
        }

        $this->response->setContent(json_encode($result), RESPONSE_JSON);
    }

    private function preValidateData($data) {
        $error = null;
        if (empty($data)) {
            $error = 'empty_data';
        } elseif (!is_object($data)) {
            $error = 'invalid_data';
        } elseif (!isset($data->action) || empty($data->action)) {
            $error = 'empty_action';
        }

        $errorMatch = !is_null($error);
        if ($errorMatch) {
            return ['success' => 0, 'error' => $error];
        }

        return null;
    }

}
