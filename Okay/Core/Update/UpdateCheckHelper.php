<?php

namespace Okay\Core\Update;

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
     * @return array{checkedAt: ?int, etag: ?string, installed: string, latest: ?array, updateAvailable: bool, lastError?: string, lastErrorAt?: int}
     */
    public function check(bool $force = false): array
    {
        $snapshot = $this->getSnapshot();

        // config.local.php може перевизначити частоту перевірки ключем core_updater_check_ttl
        $ttl = ((int) $this->config->get('core_updater_check_ttl')) ?: self::TTL;

        if (!$force && $snapshot !== null && ReleaseFeed::isSnapshotFresh($snapshot, time(), $ttl)) {
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

        if ($errno !== 0 || !is_string($response)) {
            return $this->withError($snapshot, "cURL error #{$errno}: {$error}");
        }

        if ($httpCode === 304) {
            // Знімок міг лишитись від давньої версії формату — добиваємо
            // до повного контракту тим самим кістяком, що й помилки.
            $merged = array_merge(self::emptySnapshot($this->config->forkVersion), $snapshot ?? []);
            $merged = array_diff_key($merged, ['lastError' => true, 'lastErrorAt' => true]);
            $merged['checkedAt'] = time();
            $merged = ReleaseFeed::refreshInstalled($merged, $this->config->forkVersion);

            // 304 лишає latest як був; якщо попередній side-fetch по
            // version.json не вдався, meta так і висіла б порожньою.
            if (is_array($merged['latest'] ?? null) && ($merged['latest']['meta'] ?? null) === null) {
                $metaResult = $this->fetchVersionMeta($merged['latest']['assets']['versionJson']);
                if ($metaResult['meta'] !== null) {
                    $merged['latest']['meta'] = $metaResult['meta'];
                }
            }

            $this->settings->set(self::SETTING_SNAPSHOT, $merged);

            return $merged;
        }

        if ($httpCode !== 200) {
            return $this->withError($snapshot, "HTTP {$httpCode}");
        }

        $responseHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $latest = ReleaseFeed::parseLatest($body);
        $metaError = null;

        if ($latest !== null) {
            $metaResult = $this->fetchVersionMeta($latest['assets']['versionJson']);
            $latest['meta'] = $metaResult['meta'];
            $metaError = $metaResult['error'];
        }

        $newSnapshot = [
            'checkedAt' => time(),
            'etag' => self::extractEtag($responseHeaders) ?? $etag,
            'installed' => $this->config->forkVersion,
            'latest' => $latest,
            'updateAvailable' => $latest !== null
                && ReleaseFeed::isNewerThanInstalled($latest['forkVersion'], $this->config->forkVersion),
        ];

        // Список релізів дістався успішно — знімок вартий збереження,
        // навіть якщо цей додатковий запит по version.json не вдався;
        // meta повторно спробуємо наступної перевірки.
        if ($metaError !== null) {
            $newSnapshot['lastError'] = $metaError;
            $newSnapshot['lastErrorAt'] = time();
        }

        $this->settings->set(self::SETTING_SNAPSHOT, $newSnapshot);

        return $newSnapshot;
    }

    /**
     * Окремий легкий GET по version.json з того ж релізу — без ETag,
     * тим самим стилем cURL, що й основний запит.
     *
     * @return array{meta: ?array, error: ?string}
     */
    private function fetchVersionMeta(string $url): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: OkayCMS-Fork-Updater',
            'Accept: application/vnd.github+json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        // Ассети GitHub-релізу віддаються 302-редиректом на CDN.
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($errno !== 0 || !is_string($response)) {
            return ['meta' => null, 'error' => "cURL error #{$errno}: {$error}"];
        }

        if ($httpCode !== 200) {
            return ['meta' => null, 'error' => "HTTP {$httpCode}"];
        }

        return ['meta' => ReleaseFeed::parseVersionMeta($response), 'error' => null];
    }

    /**
     * Читання без мережі. installed/updateAvailable перераховуються з
     * поточної версії форку — інакше щойно застосоване оновлення й далі
     * показувало б застиглу картину зі старого знімка.
     *
     * @return array{checkedAt: ?int, etag: ?string, installed: string, latest: ?array, updateAvailable: bool}|null
     */
    public function getSnapshot(): ?array
    {
        $snapshot = $this->settings->get(self::SETTING_SNAPSHOT);
        if (!is_array($snapshot)) {
            return null;
        }

        return ReleaseFeed::refreshInstalled($snapshot, $this->config->forkVersion);
    }

    /**
     * Помилка перевірки не має права зіпсувати робочий стан кешу: старий
     * снапшот зберігається як є (добитий до повного контракту check()), з
     * доданими lastError/lastErrorAt — щоб перевірка, яка не проходить
     * місяцями, було видно, а не лише мовчки поверталась з мережі.
     *
     * @param array<string, mixed>|null $snapshot
     * @return array<string, mixed>
     */
    private function withError(?array $snapshot, string $message): array
    {
        $result = self::buildErrorResult($snapshot, $this->config->forkVersion, $message);
        $this->settings->set(self::SETTING_SNAPSHOT, $result);

        return $result;
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
        $result['lastErrorAt'] = time();

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
