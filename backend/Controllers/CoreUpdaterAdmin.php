<?php

namespace Okay\Admin\Controllers;

use Okay\Core\Config;
use Okay\Core\Update\CoreUpdaterViewModel;
use Okay\Core\Update\UpdateCheckHelper;
use Okay\Core\Update\UpdateRunner;
use Okay\Core\Update\UpdateStatus;

class CoreUpdaterAdmin extends IndexAdmin
{
    public function fetch(UpdateCheckHelper $checkHelper, UpdateStatus $status, Config $config)
    {
        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time(), $config->version);

        $this->design->assign('vm', $vm);
        $this->design->assign('steps_lang_keys', self::stepsLangKeys());

        $this->response->setContent($this->design->fetch('core_updater.tpl'));
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
