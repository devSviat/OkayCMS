<?php

namespace Okay\Modules\Sviat\WorkingHours\Backend\Controllers;

use Okay\Core\EntityFactory;
use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\Sviat\WorkingHours\Entities\WorkingHoursEntity;

class AdminWorkingHours extends IndexAdmin
{
    public function fetch(EntityFactory $entityFactory)
    {
        /** @var WorkingHoursEntity $workingHoursEntity */
        $workingHoursEntity = $entityFactory->get(WorkingHoursEntity::class);

        if ($this->request->method('post')) {
            $hours = $this->request->post('hours', []);

            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                $data = $hours[$day] ?? [];

                $data['closed'] = isset($data['closed']) ? 1 : 0;

                $workingHoursEntity->updateOrCreate(
                    ['day' => $day],
                    [
                        'closed'       => $data['closed'],
                        'opening_time' => $data['opening_time'] ?? null,
                        'closing_time' => $data['closing_time'] ?? null,
                    ]
                );
            }

            $this->design->assign('message_success', 'saved');
        }

        $workingHours = $workingHoursEntity->find();
        $hours = [];
        foreach ($workingHours as $row) {
            $hours[$row->day] = [
                'closed'       => $row->closed,
                'opening_time' => $row->opening_time,
                'closing_time' => $row->closing_time,
            ];
        }

        $this->design->assign('hours', $hours);
        $this->response->setContent($this->design->fetch('admin_working_hours.tpl'));
    }
}
