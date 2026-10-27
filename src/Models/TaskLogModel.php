<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class TaskLogModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * เพิ่ม log การเปลี่ยนสถานะ (ตารางจริง: task_status_logs)
     * return: int $logId (0 ถ้าไม่สำเร็จ)
     */
    public function addLog($taskId, $changedBy, $oldStatus, $newStatus, $note = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_status_logs
                (task_id, changed_by, old_status, new_status, note, created_at)
            VALUES
                (:task_id, :changed_by, :old_status, :new_status, :note, NOW())
        ");

        $ok = $stmt->execute([
            'task_id'    => (int)$taskId,
            'changed_by' => (int)$changedBy,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note'       => $note,
        ]);

        return $ok ? (int)$this->pdo->lastInsertId() : 0;
    }

    // ดึง log ของงาน
    public function getLogsByTask($taskId)
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, u.full_name
            FROM task_status_logs l
            JOIN users u ON u.id = l.changed_by
            WHERE l.task_id = :id
            ORDER BY l.created_at ASC
        ");

        $stmt->execute(['id' => (int)$taskId]);
        return $stmt->fetchAll();
    }

    // ดึง log หลายงาน
    public function getLogsForTasks($taskIds)
    {
        if (empty($taskIds)) return [];

        $in = implode(",", array_fill(0, count($taskIds), '?'));
        $sql = "
            SELECT l.*, u.full_name
            FROM task_status_logs l
            JOIN users u ON u.id = l.changed_by
            WHERE l.task_id IN ($in)
            ORDER BY l.created_at ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($taskIds));
        return $stmt->fetchAll();
    }
}
