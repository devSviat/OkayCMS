<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Helpers;

use Okay\Core\Config;
use Okay\Core\Settings;

/**
 * Перевірка релізів форку на GitHub: кешує снапшот у Settings і оновлює
 * його не частіше TTL, з ETag-кондиційним запитом.
 */
class UpdateCheckHelper
{
    public const SETTING_SNAPSHOT = 'core_updater__snapshot';
    public const TTL = 21600;
    public const REPO = 'devSviat/OkayCMS';

    private const RELEASES_URL = 'https://api.github.com/repos/' . self::REPO . '/releases?per_page=15';

    private Settings $settings;
    private Config $config;

    public function __construct(Settings $settings, Config $config)
    {
        $this->settings = $settings;
        $this->config = $config;
    }

    /**
     * @return array{checkedAt: ?int, etag: ?string, installed: string, latest: ?array, updateAvailable: bool, lastError?: string}
     */
    public function check(bool $force = false): array
    {
        $snapshot = $this->getSnapshot();

        if (!$force && $snapshot !== null && ReleaseFeed::isSnapshotFresh($snapshot, time(), self::TTL)) {
            return $snapshot;
        }

        $etag = is_string($snapshot['etag'] ?? null) ? $snapshot['etag'] : null;

        $headers = [
            'User-Agent: OkayCMS-Fork-Updater',
            'Accept: application/vnd.github+json',
        ];
        if ($etag !== null && $etag !== '') {
            $headers[] = 'If-None-Match: ' . $etag;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::RELEASES_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Заголовки відповіді потрібні лише для ETag, тому читаємо їх з
        // тіла (CURLOPT_HEADER) замість CURLOPT_HEADERFUNCTION — один
        // запит, без стану колбека між викликами.
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($errno !== 0 || !is_string($response)) {
            return $this->withError($snapshot, "cURL error #{$errno}: {$error}");
        }

        if ($httpCode === 304) {
            $snapshot ??= [];
            $snapshot['checkedAt'] = time();
            $this->settings->set(self::SETTING_SNAPSHOT, $snapshot);

            return $snapshot;
        }

        if ($httpCode !== 200) {
            return $this->withError($snapshot, "HTTP {$httpCode}");
        }

        $responseHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $latest = ReleaseFeed::parseLatest($body);
        $newSnapshot = [
            'checkedAt' => time(),
            'etag' => self::extractEtag($responseHeaders) ?? $etag,
            'installed' => $this->config->forkVersion,
            'latest' => $latest,
            'updateAvailable' => $latest !== null
                && ReleaseFeed::isNewerThanInstalled($latest['forkVersion'], $this->config->forkVersion),
        ];

        $this->settings->set(self::SETTING_SNAPSHOT, $newSnapshot);

        return $newSnapshot;
    }

    /**
     * Читання без мережі.
     *
     * @return array{checkedAt: ?int, etag: ?string, installed: string, latest: ?array, updateAvailable: bool}|null
     */
    public function getSnapshot(): ?array
    {
        $snapshot = $this->settings->get(self::SETTING_SNAPSHOT);

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Помилка перевірки не має права зіпсувати робочий стан кешу: старий
     * снапшот повертається як є (добитий до повного контракту check()), з
     * доданим lastError, і в Settings не пишеться.
     *
     * @param array<string, mixed>|null $snapshot
     * @return array<string, mixed>
     */
    private function withError(?array $snapshot, string $message): array
    {
        return self::buildErrorResult($snapshot, $this->config->forkVersion, $message);
    }

    /**
     * Чиста функція без залежностей — окремо, щоб покрити юніт-тестом без
     * мережі й без Settings/DB.
     *
     * @param array<string, mixed>|null $snapshot
     * @return array<string, mixed>
     */
    public static function buildErrorResult(?array $snapshot, string $installed, string $message): array
    {
        $result = array_merge(self::emptySnapshot($installed), $snapshot ?? []);
        $result['lastError'] = $message;

        return $result;
    }

    /**
     * @return array{checkedAt: ?int, etag: ?string, installed: string, latest: ?array, updateAvailable: bool}
     */
    private static function emptySnapshot(string $installed): array
    {
        return [
            'checkedAt' => null,
            'etag' => null,
            'installed' => $installed,
            'latest' => null,
            'updateAvailable' => false,
        ];
    }

    private static function extractEtag(string $headers): ?string
    {
        if (preg_match('/^ETag:\s*(.+)$/mi', $headers, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
