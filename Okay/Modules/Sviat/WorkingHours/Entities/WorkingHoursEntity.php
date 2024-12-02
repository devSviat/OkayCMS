<?php

namespace Okay\Modules\Sviat\WorkingHours\Entities;

use Okay\Core\Entity\Entity;

class WorkingHoursEntity extends Entity
{
    protected static $fields = [
        'id',
        'day',
        'closed',
        'opening_time',
        'closing_time',
    ];

    protected static $defaultOrderFields = [
        'id',
    ];

    protected static $table = 'sviat__working_hours';
    protected static $tableAlias = 'wh';


    /**
     * Створює або оновлює запис на основі умов.
     *
     * @param array $conditions Умови для пошуку запису.
     * @param array $data Дані для оновлення або створення.
     */
    public function updateOrCreate(array $conditions, array $data)
    {
        $existingRecord = $this->findOne($conditions);

        $existingRecord ? $this->update($existingRecord->id, $data) : $this->add(array_merge($conditions, $data));
    }

    /**
     * Отримати всі записи робочих годин.
     *
     * @return array|null
     */
    public function getAllWorkingHours()
    {
        return $this->find([]);
    }


    /**
     * Отримати графік роботи на сьогодні.
     *
     * @param string $day Поточний день
     * @return object|null
     */
    public function getTodayWorkingHours(string $day)
    {
        return $this->findOne([
            'day' => $day,
            'closed' => 0,
        ]);
    }

    /**
     * Знайти наступний робочий день після поточного.
     *
     * @param string $currentDay Поточний день тижня
     * @return object|null
     */
    public function getNextWorkingDay(string $currentDay)
    {
        // Список днів тижня
        $weekDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $currentIndex = array_search($currentDay, $weekDays);

        // Перебираємо наступні дні, поки не знайдемо робочий
        for ($i = 1; $i <= 7; $i++) {
            $nextDayIndex = ($currentIndex + $i) % 7;
            $nextDay = $weekDays[$nextDayIndex];
            if ($this->findOne(['day' => $nextDay, 'closed' => 0])) {
                return $this->findOne(['day' => $nextDay, 'closed' => 0]);
            }
        }

        return null;
    }
}
