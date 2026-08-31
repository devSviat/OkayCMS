<?php

namespace Okay\Modules\Sviat\CoreUpdater\Helpers;

use Okay\Core\Config;

/**
 * Завантаження й розпакування пакета оновлення ядра. Тонкий клас: уся
 * мережа (cURL) і файлова система (ZipArchive) — жодної бізнес-логіки
 * перевірки контенту (та лежить в UpdatePackage).
 */
class UpdateDownloader
{
    private const ASSET_KEYS = ['zip', 'checksums'];

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Качає zip і checksums.txt у files/tmp/updates/{version}/.
     *
     * @param array<string, string> $assets мапа з ReleaseFeed::mapAssets() —
     *     'zip'/'checksums' (і, можливо, 'versionJson', тут не потрібен).
     * @return array{zip: string, checksums: string} локальні шляхи
     */
    public function download(array $assets, string $version): array
    {
        $targetDir = rtrim((string) $this->config->get('root_dir'), '/')
            . '/files/tmp/updates/' . $version;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true) && !is_dir($targetDir)) {
            throw new \RuntimeException("Не вдалося створити каталог для завантаження: {$targetDir}");
        }

        $localPaths = [];
        foreach (self::ASSET_KEYS as $key) {
            $url = $assets[$key] ?? null;
            if (!is_string($url) || $url === '') {
                throw new \RuntimeException("У переліку asset-ів відсутнє посилання \"{$key}\".");
            }

            // Паранойя-перевірка (defense-in-depth): навіть якщо снапшот
            // у Settings колись підмінили, споживання (тут) перевіряє
            // префікс наново, а не довіряє тому, що збережено раніше.
            if (!str_starts_with($url, ReleaseFeed::TRUSTED_ASSET_URL_PREFIX)) {
                throw new \RuntimeException("Посилання на \"{$key}\" не з довіреного джерела: {$url}");
            }

            $localPaths[$key] = $this->downloadOne($url, $targetDir);
        }

        return $localPaths;
    }

    /**
     * @throws \RuntimeException
     */
    private function downloadOne(string $url, string $targetDir): string
    {
        $fileName = basename(parse_url($url, PHP_URL_PATH) ?: '');
        if ($fileName === '' || $fileName === '.' || $fileName === '/') {
            throw new \RuntimeException("Не вдалося визначити ім'я файлу з посилання: {$url}");
        }

        $localPath = $targetDir . '/' . $fileName;

        $fileHandle = fopen($localPath, 'wb');
        if ($fileHandle === false) {
            throw new \RuntimeException("Не вдалося відкрити файл для запису: {$localPath}");
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fileHandle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_USERAGENT => 'OkayCMS-Fork-Updater',
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fileHandle);

        if ($ok !== true || $errno !== 0) {
            unlink($localPath);
            throw new \RuntimeException("Не вдалося завантажити {$url}: cURL error #{$errno}: {$error}");
        }

        if ($httpCode !== 200) {
            unlink($localPath);
            throw new \RuntimeException("Не вдалося завантажити {$url}: сервер відповів кодом {$httpCode}.");
        }

        return $localPath;
    }

    public function extract(string $zipPath, string $targetDir): void
    {
        if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true) && !is_dir($targetDir)) {
            throw new \RuntimeException("Не вдалося створити каталог для розпакування: {$targetDir}");
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            throw new \RuntimeException("Не вдалося відкрити архів {$zipPath} (код {$openResult}).");
        }

        // Перевірка ДО extractTo(): manifest-звірка шляхів (assertSafePaths)
        // стоїть пізніше й захищає лише застосування, а не сам запис на диск.
        // Тут відкидаємо traversal, абсолютні шляхи й symlink-записи ще до
        // матеріалізації — extractTo() інакше створив би їх на диску.
        try {
            $this->assertZipEntriesSafe($zip);
        } catch (\Throwable $e) {
            $zip->close();
            throw $e;
        }

        if (!$zip->extractTo($targetDir)) {
            $zip->close();
            throw new \RuntimeException("Не вдалося розпакувати архів {$zipPath} у {$targetDir}.");
        }

        if ($zip->close() !== true) {
            throw new \RuntimeException("Помилка при закритті архіву {$zipPath}.");
        }
    }

    /**
     * Symlink-запис у zip на POSIX матеріалізується справжнім симлінком, а
     * `..`/абсолютний шлях виводить запис за межі каталогу розпакування.
     * Перевіряється по кожному запису архіву перед extractTo().
     *
     * @throws \RuntimeException на першому небезпечному записі
     */
    private function assertZipEntriesSafe(\ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                throw new \RuntimeException("Не вдалося прочитати запис архіву №{$i}.");
            }

            self::assertEntryNameSafe((string) $stat['name']);

            // unix-права у старших 16 бітах external_attr; S_IFLNK = 0xA000.
            $unixMode = ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === \ZipArchive::OPSYS_UNIX)
                ? ($attr >> 16)
                : 0;
            if (($unixMode & 0xF000) === 0xA000) {
                throw new \RuntimeException("Архів містить symlink-запис (заборонено): {$stat['name']}");
            }
        }
    }

    public static function assertEntryNameSafe(string $name): void
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new \RuntimeException('Архів містить запис із порожнім чи нуль-байтовим ім\'ям.');
        }

        $normalized = str_replace('\\', '/', $name);
        if ($normalized[0] === '/' || preg_match('#^[A-Za-z]:/#', $normalized)) {
            throw new \RuntimeException("Архів містить абсолютний шлях (заборонено): {$name}");
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                throw new \RuntimeException("Архів містить перехід за межі каталогу (заборонено): {$name}");
            }
        }
    }
}
