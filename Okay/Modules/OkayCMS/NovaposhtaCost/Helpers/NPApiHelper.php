<?php


namespace Okay\Modules\OkayCMS\NovaposhtaCost\Helpers;


use Okay\Core\Settings;
use Okay\Modules\OkayCMS\NovaposhtaCost\DTO\NPCitiesCollectionDTO;
use Okay\Modules\OkayCMS\NovaposhtaCost\DTO\NPCityDTO;
use Okay\Modules\OkayCMS\NovaposhtaCost\DTO\NPWarehouseDTO;
use Okay\Modules\OkayCMS\NovaposhtaCost\DTO\NPWarehousesCollectionDTO;
use Okay\Modules\OkayCMS\NovaposhtaCost\DTO\NPWarehouseTypeDTO;
use Psr\Log\LoggerInterface;

class NPApiHelper
{
    /**
     * Скільки разів пробувати запит там, де на відповідь ніхто не чекає.
     * Клієнтські шляхи (автокомпліт адреси, розрахунок доставки) лишаються на
     * одній спробі: там ретрай тримає php-fpm воркер до півтори хвилини, а
     * «повторює» користувач — наступною літерою.
     */
    private const UNATTENDED_ATTEMPTS = 3;

    private string $lastCallError = '';
    private Settings $settings;
    private LoggerInterface $logger;

    public function __construct(
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Метод достает типы отделений из API Новой Почты
     * @return NPWarehouseTypeDTO[]
     */
    public function getWarehouseTypes(): array
    {
        $request = [
            "modelName" => "Address",
            "calledMethod" => "getWarehouseTypes",
        ];

        $response = $this->request($request, false, self::UNATTENDED_ATTEMPTS);
        if (!empty($response->success)) {
            $result = [];
            foreach ($response->data as $warehouseTypeData) {
                $name = $nameRu = htmlspecialchars($warehouseTypeData->Description);
                if (!empty($warehouseTypeData->DescriptionRu)) {
                    $nameRu = htmlspecialchars($warehouseTypeData->DescriptionRu);
                }
                $result[] = new NPWarehouseTypeDTO(
                    $name,
                    $nameRu,
                    $warehouseTypeData->Ref
                );
            }
            return $result;
        }
        return [];
    }

    public function checkApiKey(): string
    {
        $request = [
            "modelName" => "Address",
            "calledMethod" => "getWarehouseTypes",
        ];

        $this->request($request);
        return $this->getLastCallError();
    }

    public function getWarehouses(string $warehouseType, int $page, int $limit): ?NPWarehousesCollectionDTO
    {
        $request = [
            "modelName" => "Address",
            "calledMethod" => "getWarehouses",
            "methodProperties" => [
                "TypeOfWarehouseRef" => $warehouseType,
                "Page" => (string)$page,
                "Limit" => (string)$limit,
            ]
        ];

        $response = $this->request($request, true, self::UNATTENDED_ATTEMPTS);
        if (!empty($response->success)) {
            $warehousesDTO = new NPWarehousesCollectionDTO();
            foreach ($response->data as $warehouseData) {
                // Перевіряємо тип, оскільки НП може повернути відділення не того типу і вони задублюються на сайті
                if ($warehouseData->TypeOfWarehouse != $warehouseType) {
                    continue;
                }
                $name = htmlspecialchars($warehouseData->Description);
                $name = preg_replace('~(?:(№\d+)\S*)~', '$1', $name);
                $warehouseDTO = new NPWarehouseDTO(
                    $name,
                    $warehouseData->Ref,
                    $warehouseData->CityRef,
                    $warehouseData->TypeOfWarehouse,
                    (int)$warehouseData->Number
                );
                if (!empty($warehouseData->DescriptionRu)) {
                    $nameRu = htmlspecialchars($warehouseData->DescriptionRu);
                    $nameRu = preg_replace('~(?:(№\d+)\S*)~', '$1', $nameRu);
                    $warehouseDTO->setNameRu($nameRu);
                }
                $warehousesDTO->setWarehouse($warehouseDTO);
            }
            if (!empty($response->info->totalCount)) {
                $warehousesDTO->setTotalCount($response->info->totalCount);
            }
            return $warehousesDTO;
        } else {
            return null;
        }
    }

    public function getCities(int $page, int $limit): ?NPCitiesCollectionDTO
    {
        $request = [
            "modelName" => "Address",
            "calledMethod" => "getCities",
            "methodProperties" => [
                // getCities відхиляє числовий Page з "Page is invalid format",
                // тож пагінацію скрізь передаємо рядками.
                "Page" => (string)$page,
                "Limit" => (string)$limit,
            ],
        ];

        $response = $this->request($request, true, self::UNATTENDED_ATTEMPTS);
        if (!empty($response->success)) {
            $citiesDTO = new NPCitiesCollectionDTO();
            foreach ($response->data as $cityData) {
                $cityDTO = new NPCityDTO(
                    htmlspecialchars($cityData->Description),
                    $cityData->Ref
                );
                if (!empty($cityData->DescriptionRu)) {
                    $cityDTO->setNameRu(htmlspecialchars($cityData->DescriptionRu));
                }
                $citiesDTO->setCity($cityDTO);
            }
            if (!empty($response->info->totalCount)) {
                $citiesDTO->setTotalCount($response->info->totalCount);
            }
            return $citiesDTO;
        } else {
            return null;
        }
    }

    public function getLastCallError(): string
    {
        return $this->lastCallError;
    }

    public function request(array $requestParams, bool $isUseApiKey = true, int $maxAttempts = 1)
    {
        if (empty($requestParams)) {
            return false;
        }
        if ($isUseApiKey) {
            $requestParams["apiKey"] = $this->settings->get('newpost_key');
        }

        $maxAttempts = max(1, $maxAttempts);
        $retryDelay = 1;
        $retryErrno = [6, 7, 35, 28, 52, 56]; // curl network errors

        $attempt = 0;

        do {
            $attempt++;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.novaposhta.ua/v2.0/json/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Connection: close"
            ]);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST , 'POST');

            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestParams));

            // Без FOLLOWLOCATION навмисно: ендпоінт один і незмінний, а на
            // 301/302 curl перетворює POST на GET — тіло з apiKey або
            // губиться, або їде за чужим Location.
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

            $response = curl_exec($ch);
            $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno    = curl_errno($ch);
            $error    = curl_error($ch);


            $tooManyRequests = false;
            if ($response !== false) {
                $responseJson = json_decode($response);

                if (!empty($responseJson->errors)
                    && in_array('To many requests', $responseJson->errors, true)
                ) {
                    // Лише warning: помилкою це стане внизу, якщо спроби
                    // скінчаться. Інакше кожна літера в автокомпліті писала б
                    // два записи error на той самий ліміт.
                    $this->lastCallError = "To many requests";
                    $this->logger->warning('Novaposhta cost warning: "' . $this->lastCallError . '"');
                    $tooManyRequests = true;
                } else {
                    break;
                }
            }

            if (!$tooManyRequests && !in_array($errno, $retryErrno, true)) {
                $this->lastCallError = "CURL response code:$status error #{$errno}: {$error}";
                $this->logger->error('Novaposhta cost error: "' . $this->lastCallError . '"');
                return false;
            }

            if ($attempt < $maxAttempts) {
                $this->logger->warning(sprintf(
                    'Novaposhta cost warning retry %d/%d: CURL #%d %s status http:%d',
                    $attempt,
                    $maxAttempts,
                    $errno,
                    $error,
                    $status
                ));

                sleep($retryDelay);
            }
        } while ($attempt < $maxAttempts);

        if ($response === false) {
            $this->lastCallError = "CURL failed after {$attempt} attempt(s). Last error #{$errno}: {$error}";
            $this->logger->error('Novaposhta cost error: "' . $this->lastCallError . '"');
            return false;
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastCallError = 'Invalid JSON response';
            $this->logger->error('Novaposhta cost error: "' . $this->lastCallError . '"');
            return false;
        }

        if (!empty($responseJson->errors)) {
            $this->lastCallError = implode('<br>', (array) $responseJson->errors);

            if (strpos($this->lastCallError, 'API key') !== false) {
                $this->settings->set('np_api_key_error', $this->lastCallError);
            }

            $this->logger->error('Novaposhta cost error: "' . $this->lastCallError . '"');
            return false;
        }
        if (!empty($responseJson->success)) {
            if (!isset($responseJson->data)) {
                $this->lastCallError = 'Response data is empty';
                $this->logger->error('Novaposhta cost error: "' . $this->lastCallError . '"');
                return false;
            }
            return $responseJson;
        }

        return false;
    }
}