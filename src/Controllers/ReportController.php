<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TaskLogModel;
use App\Models\TaskModel;

class ReportController
{
    public function __construct(
        private readonly TaskModel $taskModel,
        private readonly TaskLogModel $taskLogModel,
    ) {
    }

    // ดึงรายงานพร้อม Logs
    public function getReport($filters)
    {
        $tasks = $this->taskModel->filterTasks($filters);

        $taskIds = array_column($tasks, 'id');
        $logs    = $this->taskLogModel->getLogsForTasks($taskIds);

        // รวม log ตาม Task
        $logGrouped = [];
        foreach ($logs as $l) {
            $logGrouped[$l['task_id']][] = $l;
        }

        return [$tasks, $logGrouped];
    }
}
