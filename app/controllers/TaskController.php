<?php
// app/controllers/TaskController.php

class TaskController
{
    private $taskModel;
    private $taskLogModel;

    public function __construct($taskModel, $taskLogModel)
    {
        $this->taskModel    = $taskModel;
        $this->taskLogModel = $taskLogModel;
    }

    /**
     * ✅ LIST งาน (สำหรับ GET /tasks)
     * - boss: filter ได้ตาม params ที่ส่งมา
     * - employee: บังคับเป็นของตัวเองเท่านั้น
     *   - scope=assigned_by => งานที่ฉันมอบหมาย (assigned_by = me)
     *   - default/scope=assigned_to => งานที่มอบหมายให้ฉัน (assigned_to / task_assignees มีฉัน)
     */
    public function listTasks(array $query, int $currentUserId, bool $isBoss): array
    {
        $params = [];

        $keyword = trim($query['keyword'] ?? '');
        $status  = trim($query['status'] ?? '');
        $dept    = trim($query['department'] ?? '');
        $scope   = trim($query['scope'] ?? '');

        if ($keyword !== '') $params['keyword'] = $keyword;
        if ($status !== '')  $params['status'] = $status;
        if ($dept !== '')    $params['department'] = $dept;

        if ($isBoss) {
            // boss จะระบุ assigned_by/assigned_to มาก็ได้
            if (!empty($query['assigned_by'])) $params['assigned_by'] = (int)$query['assigned_by'];
            if (!empty($query['assigned_to'])) $params['assigned_to'] = (int)$query['assigned_to'];
        } else {
            // employee: override ให้เป็นของตัวเองเสมอ
            if ($scope === 'assigned_by') {
                $params['assigned_by'] = $currentUserId;
            } else {
                $params['assigned_to'] = $currentUserId;
            }
        }

        return $this->taskModel->filterTasks($params);
    }

    // เพิ่มงาน
    public function createTask($data, $userId)
    {
        $id = $this->taskModel->create($data);

        // บันทึก log
        $this->taskLogModel->addLog(
            $id,
            $userId,
            null,
            $data['status'],
            'สร้างงานใหม่'
        );

        return $id;
    }

    // อัปเดตงาน
    public function updateTask($taskId, $oldStatus, $data, $userId)
    {
        $this->taskModel->update($taskId, $data);

        if (isset($data['status']) && $oldStatus !== $data['status']) {
            $this->taskLogModel->addLog(
                $taskId,
                $userId,
                $oldStatus,
                $data['status'],
                'เปลี่ยนสถานะงาน'
            );
        }
    }

    // ลบงาน
    public function deleteTask($taskId)
    {
        return $this->taskModel->delete($taskId);
    }
}
