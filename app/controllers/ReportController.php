<?php
// app/controllers/ReportController.php

class ReportController
{
    private $taskModel;
    private $taskLogModel;

    public function __construct($taskModel, $taskLogModel)
    {
        $this->taskModel    = $taskModel;
        $this->taskLogModel = $taskLogModel;
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
