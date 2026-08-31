<?php

namespace Okay\Modules\OkayCMS\CoreUpdater\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\CoreUpdaterViewModel;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateCheckHelper;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateRunner;
use Okay\Modules\OkayCMS\CoreUpdater\Helpers\UpdateStatus;

class CoreUpdaterAdmin extends IndexAdmin
{
    public function fetch(UpdateCheckHelper $checkHelper, UpdateStatus $status)
    {
        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time());

        $this->design->assign('vm', $vm);
        $this->design->assign('steps_lang_keys', self::stepsLangKeys());

        $this->response->setContent($this->design->fetch('core_updater.tpl'));
    }

    public function checkNow(UpdateCheckHelper $checkHelper)
    {
        if (!$this->assertValidPost()) {
            return;
        }

        $checkHelper->check(true);

        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), null, time());
        $this->response->setContent(json_encode($vm), RESPONSE_JSON);
    }

    public function startUpdate(UpdateRunner $runner, UpdateCheckHelper $checkHelper, UpdateStatus $status)
    {
        if (!$this->assertValidPost()) {
            return;
        }

        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time());
        if (!$vm['canStartUpdate']) {
            $this->response->setContent(json_encode(['error' => 'cannot_start']), RESPONSE_JSON);
            return;
        }

        // run() сам виставляє ignore_user_abort(true)/set_time_limit(0) і
        // виконується синхронно в цьому запиті (спек §8) — паралельний GET
        // на status() лишається єдиним джерелом прогресу для клієнта.
        try {
            $runner->run(null);
        } catch (\Throwable $e) {
            $this->response->setContent(json_encode(['error' => 'start_failed', 'message' => $e->getMessage()]), RESPONSE_JSON);
            return;
        }

        $finalVm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time());
        $this->response->setContent(json_encode($finalVm), RESPONSE_JSON);
    }

    public function status(UpdateCheckHelper $checkHelper, UpdateStatus $status)
    {
        $vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(), $status->load(), time());
        $this->response->setContent(json_encode($vm), RESPONSE_JSON);
    }

    /**
     * Невалідний/протухлий CSRF-токен лишає $_POST порожнім без винятку
     * (Request::checkSession()) — тут це єдина ознака, за якою відрізнити
     * підроблений чи протермінований POST від справжнього.
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
