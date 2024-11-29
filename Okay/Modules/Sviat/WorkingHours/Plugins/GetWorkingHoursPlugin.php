<?php

namespace Okay\Modules\Sviat\WorkingHours\Plugins;

use Okay\Core\Design;
use Okay\Core\Languages;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\SmartyPlugins\Func;
use Okay\Modules\Sviat\WorkingHours\Entities\WorkingHoursEntity;

class GetWorkingHoursPlugin extends Func
{
    protected $tag = 'get_working_hours';

    private $design;
    private $workingHoursEntity;
    private $languages;
    private $frontTranslations;

    public function __construct(Design $design, EntityFactory $entityFactory, Languages $languages, FrontTranslations $frontTranslations)
    {
        $this->design = $design;
        $this->workingHoursEntity = $entityFactory->get(WorkingHoursEntity::class);
        $this->languages = $languages;
        $this->frontTranslations = $frontTranslations;
    }

    public function run($params)
    {
        $currentDay = strtolower(date('l'));
        $currentTime = date('H:i');

        $todayHours = $this->workingHoursEntity->getTodayWorkingHours($currentDay);

        // Отримуємо статус для сьогодні або наступного робочого дня
        $statusData = $todayHours ? $this->getTodayStatus($todayHours, $currentTime) : $this->getNextWorkingDayStatus($currentDay);

        // Присвоюємо статуси
        $this->design->assign('status', $statusData['status']);
        $this->design->assign('message', $statusData['message']);
        $this->design->assign('showClosedMessage', $statusData['showClosedMessage']);

        error_log('Closing Soon: ' . var_export($statusData['showClosedMessage'], true));

        return $this->design->fetch('working_hours.tpl');
    }

    private function getTodayStatus($todayHours, $currentTime)
    {
        if ($todayHours->closed == 0 && $currentTime >= $todayHours->opening_time && $currentTime < $todayHours->closing_time) {
            return $this->generateOpenStatus($todayHours, $currentTime);
        }

        return array_merge($this->getNextWorkingDayStatus(strtolower(date('l'))), ['showClosedMessage' => true]);
    }

    private function getNextWorkingDayStatus($currentDay)
    {
        $nextWorkingDay = $this->workingHoursEntity->getNextWorkingDay($currentDay);

        if ($nextWorkingDay) {
            return $this->generateClosedStatus($nextWorkingDay);
        }

        return $this->generateNoWorkingDaysStatus();
    }

    private function generateOpenStatus($todayHours, $currentTime)
    {
        $showClosedMessage = $currentTime >= $todayHours->closing_time || $todayHours->closed == 1;

        return [
            'status' => $this->frontTranslations->getTranslation('working_hours__status_open'),
            'message' => $this->frontTranslations->getTranslation('working_hours__message_closes') . ' ' . date('g:i A', strtotime($todayHours->closing_time)),
            'showClosedMessage' => $showClosedMessage,
        ];
    }

    private function generateClosedStatus($nextWorkingDay)
    {
        return [
            'status' => $this->frontTranslations->getTranslation('working_hours__status_closed'),
            'message' => $this->frontTranslations->getTranslation('working_hours__message_opens') . ' ' . date('g:i A', strtotime($nextWorkingDay->opening_time)) . ' ' . $this->frontTranslations->getTranslation('working_hours__' . $nextWorkingDay->day),
            'showClosedMessage' => false,
        ];
    }

    private function generateNoWorkingDaysStatus()
    {
        return [
            'status' => $this->frontTranslations->getTranslation('working_hours__status_closed'),
            'message' => $this->frontTranslations->getTranslation('working_hours__message_no_working'),
            'showClosedMessage' => false,
        ];
    }
}
