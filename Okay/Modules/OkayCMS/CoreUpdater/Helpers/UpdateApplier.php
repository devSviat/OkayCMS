<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Helpers;

use Okay\Core\Design;
use Symfony\Component\Process\Process;

/**
 * Застосування файлів оновлення ядра — spool-and-swap (спек §8 кроки 6-7,
 * §11): копія поруч із ціллю + atomic rename(), нічого не видаляється.
 *
 * Довіра до шляхів: викликач (UpdateRunner) прогонив
 * UpdatePackage::assertSafePaths() над тим самим manifest-переліком ДО
 * виклику applyFiles() — тут шляхи вважаються вже безпечними, повторної
 * перевірки нема (defense-in-depth зроблено один раз вище по стеку).
 */
class UpdateApplier
{
    private const TMP_SUFFIX = '.core-update.tmp';

    /**
     * Для кожного шляху з $manifestFiles: mkdir цільової директорії,
     * copy() джерела у `{$target}.core-update.tmp`, rename() поверх цілі
     * (той самий FS — atomic swap). Файли, відсутні в пакеті, не
     * зачіпаються: apply лише додає/замінює, ніколи не видаляє.
     *
     * @param array<string, mixed> $manifestFiles відносний шлях => sha256;
     *     значення тут не використовується — контент уже звірений
     *     UpdatePackage::verifyExtractedFiles() до виклику
     * @param ?callable $onProgress викликається (string $relativePath) після
     *     кожного успішно застосованого файлу
     * @return list<string> застосовані відносні шляхи, у порядку manifest
     * @throws UpdateApplyException якщо файл не вдалось скопіювати чи
     *     перейменувати — несе перелік уже застосованих файлів; недописаний
     *     `.core-update.tmp` цього файлу прибирається перед кидком
     */
    public function applyFiles(
        string $extractedPayloadDir,
        string $rootDir,
        array $manifestFiles,
        ?callable $onProgress = null
    ): array {
        $extractedPayloadDir = rtrim($extractedPayloadDir, '/');
        $rootDir = rtrim($rootDir, '/');
        $applied = [];

        foreach (array_keys($manifestFiles) as $relativePath) {
            $sourcePath = $extractedPayloadDir . '/' . $relativePath;
            $targetPath = $rootDir . '/' . $relativePath;
            $tmpPath = $targetPath . self::TMP_SUFFIX;

            try {
                $this->spoolAndSwapFile($sourcePath, $targetPath, $tmpPath);
            } catch (\Throwable $e) {
                @unlink($tmpPath);
                throw new UpdateApplyException(
                    "Не вдалося застосувати файл {$relativePath}: {$e->getMessage()}",
                    $applied,
                    $e
                );
            }

            $applied[] = $relativePath;
            if ($onProgress !== null) {
                $onProgress($relativePath);
            }
        }

        return $applied;
    }

    /**
     * Зворотний spool-and-swap: відновлює файли з backup-архіву
     * (UpdateBackup::createFilesBackup()) поверх $rootDir — той самий
     * tmp+rename патерн, у зворотньому напрямку.
     *
     * UpdateBackup::EMPTY_BACKUP_MARKER пропускається: це технічний
     * маркер-запис, яким UpdateRunner::stepBackup() змушує libzip реально
     * записати порожній backup-архів на диск, а не частина сайту — інакше
     * rollback лишав би stray `{rootDir}/.empty`.
     *
     * @return list<string> відновлені відносні шляхи, у порядку архіву
     * @throws UpdateApplyException якщо файл не вдалось відновити — несе
     *     перелік уже відновлених файлів
     */
    public function restoreFiles(string $backupZipPath, string $rootDir): array
    {
        $rootDir = rtrim($rootDir, '/');

        $zip = new \ZipArchive();
        $openResult = $zip->open($backupZipPath);
        if ($openResult !== true) {
            throw new \RuntimeException("Не вдалося відкрити архів бекапу {$backupZipPath} (код {$openResult}).");
        }

        $restored = [];

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $relativePath = $zip->getNameIndex($i);
                if ($relativePath === false
                    || str_ends_with($relativePath, '/')
                    || $relativePath === UpdateBackup::EMPTY_BACKUP_MARKER
                ) {
                    continue;
                }

                $targetPath = $rootDir . '/' . $relativePath;
                $tmpPath = $targetPath . self::TMP_SUFFIX;

                try {
                    $content = $zip->getFromIndex($i);
                    if ($content === false) {
                        throw new \RuntimeException("Не вдалося прочитати {$relativePath} з архіву бекапу.");
                    }

                    $this->spoolAndSwapContent($content, $targetPath, $tmpPath);
                } catch (\Throwable $e) {
                    @unlink($tmpPath);
                    throw new UpdateApplyException(
                        "Не вдалося відновити файл {$relativePath}: {$e->getMessage()}",
                        $restored,
                        $e
                    );
                }

                $restored[] = $relativePath;
            }
        } finally {
            $zip->close();
        }

        return $restored;
    }

    private function spoolAndSwapFile(string $sourcePath, string $targetPath, string $tmpPath): void
    {
        if (!is_file($sourcePath)) {
            throw new \RuntimeException("У розпакованому пакеті відсутній файл: {$sourcePath}");
        }

        $this->ensureDirFor($targetPath);

        if (!copy($sourcePath, $tmpPath)) {
            throw new \RuntimeException("Не вдалося скопіювати {$sourcePath} у {$tmpPath}.");
        }

        // ZIP не переносить unix-права, тому виконуваність успадковуємо від
        // файлу, що заміняється — інакше `./ok` після оновлення втрачає +x.
        if (is_file($targetPath) && is_executable($targetPath)) {
            @chmod($tmpPath, fileperms($targetPath) & 0777);
        }

        $this->rename($tmpPath, $targetPath);
    }

    private function spoolAndSwapContent(string $content, string $targetPath, string $tmpPath): void
    {
        $this->ensureDirFor($targetPath);

        if (file_put_contents($tmpPath, $content) === false) {
            throw new \RuntimeException("Не вдалося записати тимчасовий файл {$tmpPath}.");
        }

        $this->rename($tmpPath, $targetPath);
    }

    private function ensureDirFor(string $targetPath): void
    {
        $targetDir = dirname($targetPath);
        // @: провал очікуваний і перевіряється явно (наприклад, шлях
        // зайнятий звичайним файлом) — тут це не тиха помилка, а керована.
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException("Не вдалося створити каталог {$targetDir}.");
        }
    }

    private function rename(string $tmpPath, string $targetPath): void
    {
        if (!rename($tmpPath, $targetPath)) {
            throw new \RuntimeException("Не вдалося перейменувати {$tmpPath} у {$targetPath}.");
        }
    }

    /**
     * composer install, лише якщо composer.lock у пакеті відрізняється від
     * поточного (порівняння ВМІСТУ, не mtime). Пакет без composer.lock
     * (не кожен реліз чіпає залежності) → null, кроку немає.
     *
     * ЯКЩО lock відрізняється, а composer недоступний — RuntimeException.
     * Це страховка: основний гейт — pre-flight у UpdateRunner (Task 7), що
     * перевіряє доступність composer ДО будь-яких змін під коренем; тут —
     * останній рубіж, якщо той гейт чомусь оминули.
     *
     * @return ?string stdout composer install, або null якщо крок не виконувався
     * @throws \RuntimeException composer install провалився, або composer
     *     недоступний, а lock відрізняється
     */
    public function runComposerIfNeeded(string $rootDir, string $extractedPayloadDir): ?string
    {
        $rootDir = rtrim($rootDir, '/');
        $extractedPayloadDir = rtrim($extractedPayloadDir, '/');

        $packageLockPath = $extractedPayloadDir . '/composer.lock';
        if (!is_file($packageLockPath)) {
            return null;
        }

        $currentLockPath = $rootDir . '/composer.lock';
        $packageLock = file_get_contents($packageLockPath);
        $currentLock = is_file($currentLockPath) ? file_get_contents($currentLockPath) : null;

        if ($packageLock === $currentLock) {
            return null;
        }

        $composerCommand = $this->findComposerCommand($rootDir);
        if ($composerCommand === null) {
            throw new \RuntimeException(
                'composer.lock оновлення відрізняється від поточного, а composer/composer.phar не знайдено — '
                . 'встановлення залежностей неможливе.'
            );
        }

        $process = new Process(
            [...$composerCommand, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
            $rootDir
        );
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('composer install завершився з помилкою: ' . trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }

    /**
     * Шукає composer у PATH (`composer`, потім `composer.phar`), тоді
     * `composer.phar` у корені сайту.
     *
     * @return ?list<string> команда запуску, або null якщо composer не знайдено
     */
    private function findComposerCommand(string $rootDir): ?array
    {
        foreach ([['composer'], ['composer.phar']] as $command) {
            if ($this->commandWorks($command)) {
                return $command;
            }
        }

        $rootPhar = $rootDir . '/composer.phar';
        if (is_file($rootPhar) && $this->commandWorks(['php', $rootPhar])) {
            return ['php', $rootPhar];
        }

        return null;
    }

    /** @param list<string> $command */
    private function commandWorks(array $command): bool
    {
        try {
            $process = new Process([...$command, '--version']);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function clearCaches(Design $design): void
    {
        $design->clearCompiled();

        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}
