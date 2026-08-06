<?php

namespace Okay\Core\Modules;

use Okay\Core\Modules\DTO\LicenseDTO;
use Okay\Core\Request;

class LicenseStorage
{
    private string $compileCodeDir;

    public function __construct(string $compileCodeDir)
    {
        $this->compileCodeDir = $compileCodeDir;

        // Провал тут ні на що не впливає: файл просто не запишеться. Попередження
        // придушуємо навмисно - інакше воно повторюється на кожному запиті.
        if (!is_dir($this->compileCodeDir)) {
            @mkdir($this->compileCodeDir, 0777, true);
        }
    }

    public function saveLicense(LicenseDTO $licenseDTO)
    {
        file_put_contents(
            $this->getLicenseFilename(),
            serialize($licenseDTO),
            LOCK_EX
        );
    }

    public function getLicense(): ?LicenseDTO
    {
        $licenseFilename = $this->getLicenseFilename();
        if (!is_file($licenseFilename)) {
            return null;
        }
        $licenseContent = file_get_contents($licenseFilename);
        if (empty($licenseContent)
            || strpos($licenseContent, "\n") !== false
            || strpos($licenseContent, "\r") !== false
        ) {
            return null;
        }

        $licenseDTO = @unserialize($licenseContent, ['allowed_classes' => [LicenseDTO::class]]);

        if (!is_object($licenseDTO)) {
            return null;
        }

        if (!$licenseDTO instanceof LicenseDTO) {
            return null;
        }
        return $licenseDTO;
    }

    /**
     * Чи дозволено зараз ходити за ліцензією.
     *
     * Ця пауза свідомо спільна для всіх відвідувачів, а не сесійна: при
     * недоступному маркетплейсі невдалий запит не зберігає файл ліцензії, тож
     * без спільної паузи кожен наступний запит вітрини знову чекав на curl.
     * Анонімні відвідувачі й боти сесію не переносять, тож сесійна пауза для
     * них не працює взагалі.
     */
    public function isRequestAllowed(): bool
    {
        $filename = $this->getRetryFilename();
        if (!is_file($filename)) {
            return true;
        }

        return time() >= (int)file_get_contents($filename);
    }

    public function suppressRequestsFor(int $seconds): void
    {
        file_put_contents($this->getRetryFilename(), (string)(time() + $seconds), LOCK_EX);
    }

    public function allowRequests(): void
    {
        $filename = $this->getRetryFilename();
        if (is_file($filename)) {
            unlink($filename);
        }
    }

    private function getLicenseFilename(): string
    {
        return sprintf('%s%s.license',
            $this->compileCodeDir,
            md5(Request::getDomain())
        );
    }

    private function getRetryFilename(): string
    {
        return $this->getLicenseFilename() . '-retry';
    }
}