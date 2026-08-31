<?php

namespace Okay\Admin\Controllers;

use Okay\Core\Config;
use Okay\Core\Settings;
use Okay\Core\Update\CoreUpdaterViewModel;
use Okay\Core\Update\UpdateCheckHelper;
use Okay\Core\Update\UpdateRunner;
use Okay\Core\Update\UpdateStatus;

class CoreUpdaterAdmin extends IndexAdmin
{
    /** Скільки останніх резервних копій показувати списком. */
    private const BACKUPS_SHOWN = 10;

    public function fetch(UpdateCheckHelper $checkHelper, UpdateStatus $status, Config $config, Settings $settings)
    {
        $runState = $status->load();
        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $runState, time(), $config->version);

        // Результат успішного прогону — новина одного перегляду. Позначаємо
        // саме тут, а не в status(): поллінг ходить туди ще під час прогону й
        // з'їв би позначку до того, як сторінка покаже результат.
        if ($runState !== null && ($vm['mode'] === UpdateStatus::STEP_DONE || $vm['previousRunDone'])) {
            $status->markResultSeen($runState);
        }

        $this->design->assign('vm', $vm);
        $this->design->assign('steps_lang_keys', self::stepsLangKeys());
        $this->design->assign('updater_settings', [
            'rootUrl' => (string) $settings->get(UpdateRunner::SETTING_ROOT_URL),
            'autoCheck' => $settings->get(UpdateCheckHelper::SETTING_AUTO_CHECK) !== '0',
            'detectedRootUrl' => $this->request->getRootUrl(),
        ]);
        $this->design->assign('backups', self::collectBackups((string) $config->get('root_dir')));

        $this->response->setContent($this->design->fetch('core_updater.tpl'));
    }

    public function saveSettings(Settings $settings)
    {
        if (!$this->assertValidPost()) {
            return;
        }

        // Порожнє поле означає «визначати автоматично з поточного запиту» —
        // саме так поводиться UpdateRunner::resolveRootUrl() без цього ключа.
        $rootUrl = trim((string) $this->request->post('core_updater_root_url'));
        $settings->set(UpdateRunner::SETTING_ROOT_URL, rtrim($rootUrl, '/'));
        $settings->set(UpdateCheckHelper::SETTING_AUTO_CHECK, $this->request->post('core_updater_auto_check') ? '1' : '0');

        $this->design->assign('message_success', 'core_updater_settings_saved');

        $this->response->redirectTo($this->request->getRootUrl() . '/backend/index.php?controller=CoreUpdaterAdmin');
    }

    /**
     * Резервні копії показуємо переліком, щоб не лізти на сервер по SSH заради
     * питання «а копія взагалі є?». Самі файли назовні не віддаються
     * (`files/backups/.htaccess` + правило nginx), тож тут лише метадані.
     *
     * @return list<array{name: string, size: int, date: int}> найновіші перші
     */
    private static function collectBackups(string $rootDir): array
    {
        $dir = rtrim($rootDir, '/') . '/files/backups';
        $backups = [];

        foreach (glob($dir . '/*') ?: [] as $path) {
            if (!is_file($path) || in_array(basename($path), ['.htaccess', '.keep_folder'], true)) {
                continue;
            }

            $backups[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'date' => (int) filemtime($path),
            ];
        }

        usort($backups, static fn(array $a, array $b): int => $b['date'] <=> $a['date']);

        return array_slice($backups, 0, self::BACKUPS_SHOWN);
    }

    public function checkNow(UpdateCheckHelper $checkHelper, Config $config)
    {
        if (!$this->assertValidPost()) {
            return;
        }

        $checkHelper->check(true);

        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), null, time(), $config->version);
        $this->response->setContent(json_encode($vm), RESPONSE_JSON);
    }

    public function startUpdate(UpdateRunner $runner, UpdateCheckHelper $checkHelper, UpdateStatus $status, Config $config)
    {
        if (!$this->assertValidPost()) {
            return;
        }

        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time(), $config->version);
        if (!$vm['canStartUpdate']) {
            $this->response->setContent(json_encode(['error' => 'cannot_start']), RESPONSE_JSON);
            return;
        }

        // Диспетчер бекенду тримає сесію відкритою до кінця запиту, тож без
        // явного закриття лока паралельний GET на status() (та ж сесія)
        // блокується на файловому локі сесії аж до завершення багатохвилинного
        // run() — поллінг прогресу інакше "зависає" на весь час оновлення.
        session_write_close();

        // run() сам виставляє ignore_user_abort(true)/set_time_limit(0) і
        // виконується синхронно в цьому запиті (спек §8) — паралельний GET
        // на status() лишається єдиним джерелом прогресу для клієнта.
        try {
            $runner->run(null);
        } catch (\Throwable $e) {
            $this->response->setContent(json_encode(['error' => 'start_failed', 'message' => $e->getMessage()]), RESPONSE_JSON);
            return;
        }

        $finalVm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time(), $config->version);
        $this->response->setContent(json_encode($finalVm), RESPONSE_JSON);
    }

    public function status(UpdateCheckHelper $checkHelper, UpdateStatus $status, Config $config)
    {
        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time(), $config->version);
        $this->response->setContent(json_encode($vm), RESPONSE_JSON);
    }

    /**
     * Порожній $_POST тут відсікає лише GET-виклик мутуючих екшенів через цей
     * URL (диспетчер бекенду сам перевіряє CSRF-токен ще до виклику методу) —
     * назва історична, а не повторна перевірка токена.
     */
    private function assertValidPost(): bool
    {
        if (empty($_POST)) {
            $this->response->setContent(json_encode(['error' => 'csrf']), RESPONSE_JSON);
            return false;
        }

        return true;
    }

    /** @return array<string, string> крок → ленг-ключ його статусу, для .tpl */
    private static function stepsLangKeys(): array
    {
        $keys = [];
        foreach ([...UpdateStatus::STEPS, ...UpdateStatus::TERMINAL_STEPS] as $step) {
            $keys[$step] = 'core_updater_step_' . $step;
        }

        return $keys;
    }
}
