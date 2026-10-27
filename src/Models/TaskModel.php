<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use PDO;

class TaskModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * ดึงงานตาม ID
     * - assigned_to_name = ผู้รับผิดชอบหลัก (tasks.assigned_to)
     * - assigned_to_names = ผู้รับมอบหมายทั้งหมด (จาก task_assignees)
     */
    public function find($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT
              t.*,
              u.full_name AS assigned_to_name,
              (
                SELECT GROUP_CONCAT(u2.full_name ORDER BY u2.full_name SEPARATOR ', ')
                FROM task_assignees ta2
                JOIN users u2 ON u2.id = ta2.user_id
                WHERE ta2.task_id = t.id
              ) AS assigned_to_names
            FROM tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    /**
     * ดึงงานทั้งหมดตาม filter
     * รองรับเพิ่ม:
     * - keyword
     * - status
     * - assigned_to (หางานที่ user นั้นอยู่ใน task_assignees)
     * - department
     */
    public function filterTasks($params = [])
    {
        $where = [];
        $binds = [];

        $sql = "
            SELECT
                t.*,
                ub.full_name AS assigned_by_name,
                um.full_name AS assigned_to_name,
                GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') AS assigned_to_names,
                COUNT(DISTINCT tf.id) AS files_count
            FROM tasks t
            LEFT JOIN users ub ON ub.id = t.assigned_by
            LEFT JOIN users um ON um.id = t.assigned_to
            LEFT JOIN task_assignees ta ON ta.task_id = t.id
            LEFT JOIN users u ON u.id = ta.user_id
            LEFT JOIN task_files tf ON tf.task_id = t.id
        ";

        if (!empty($params['keyword'])) {
            $where[] = "t.title LIKE :kw";
            $binds['kw'] = "%{$params['keyword']}%";
        }
        if (!empty($params['status'])) {
            $where[] = "t.status = :status";
            $binds['status'] = $params['status'];
        }
        if (!empty($params['department'])) {
            $where[] = "t.department = :dept";
            $binds['dept'] = $params['department'];
        }
        // ✅ year filter (รองรับทั้ง ค.ศ. และ พ.ศ.)
        if (!empty($params['year'])) {
            $yy = (int)$params['year'];
            if ($yy >= 2400) $yy -= 543; // ถ้าเผลอส่ง พ.ศ. มา

            // แนะนำใช้ช่วงเวลา (เร็วกว่า YEAR(created_at) ถ้ามี index)
            $from = sprintf('%04d-01-01 00:00:00', $yy);
            $to   = sprintf('%04d-01-01 00:00:00', $yy + 1);

            $where[] = "(t.created_at >= :from AND t.created_at < :to)";
            $binds['from'] = $from;
            $binds['to']   = $to;
        }
        // app/models/TaskModel.php (เฉพาะส่วนนี้)
        if (!empty($params['assigned_to'])) {
            $where[] = "(t.assigned_to = :uid OR EXISTS (
        SELECT 1 FROM task_assignees ta2
        WHERE ta2.task_id = t.id AND ta2.user_id = :uid
        ))";
            $binds['uid'] = (int)$params['assigned_to'];
        }
        if (!empty($params['assigned_by'])) {
            $where[] = "t.assigned_by = :assigned_by";
            $binds['assigned_by'] = (int)$params['assigned_by'];
        }

        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        $sql .= " GROUP BY t.id ORDER BY t.created_at DESC, t.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);
        return $stmt->fetchAll();
    }

    public function create($data)
    {
        $stmt = $this->pdo->prepare("
        INSERT INTO tasks
            (task_code, title, description, department, assigned_to, assigned_by, status, due_date, created_at, updated_at)
        VALUES
            (:task_code, :title, :description, :department, :assigned_to, :assigned_by, :status, :due_date, NOW(), NOW())
    ");

        $stmt->execute([
            'task_code'    => $data['task_code'],
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'department'   => $data['department'] ?? null,
            'assigned_to'  => $data['assigned_to'],
            'assigned_by'  => $data['assigned_by'],
            'status'       => $data['status'],
            'due_date'     => $data['due_date'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function createAuto(array $data, array $assigneeIds = [])
    {
        $assigneeIds = array_values(array_unique(array_filter(array_map('intval', $assigneeIds), fn($v) => $v > 0)));
        if (empty($assigneeIds)) {
            throw new Exception('กรุณาเลือกผู้รับมอบหมายอย่างน้อย 1 คน');
        }

        $mainAssignee = (int)$assigneeIds[0];

        $this->pdo->beginTransaction();
        try {
            $tmp = 'TMP-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

            $stmt = $this->pdo->prepare("
            INSERT INTO tasks
              (task_code, title, description, department, assigned_to, assigned_by, status, due_date, created_at, updated_at)
            VALUES
              (:task_code, :title, :description, :department, :assigned_to, :assigned_by, :status, :due_date, NOW(), NOW())
        ");
            $stmt->execute([
                'task_code'   => $tmp,
                'title'       => $data['title'],
                'description' => $data['description'] ?? null,
                'department'  => $data['department'] ?? null,
                'assigned_to' => $mainAssignee,
                'assigned_by' => (int)$data['assigned_by'],
                'status'      => $data['status'],
                'due_date'    => $data['due_date'],
            ]);

            $taskId = (int)$this->pdo->lastInsertId();

            $code = make_task_code($taskId);
            $this->pdo->prepare("UPDATE tasks SET task_code = :code WHERE id = :id")
                ->execute(['code' => $code, 'id' => $taskId]);

            $ins = $this->pdo->prepare("
            INSERT IGNORE INTO task_assignees (task_id, user_id, created_at)
            VALUES (:task_id, :user_id, NOW())
        ");
            foreach ($assigneeIds as $uid) {
                $ins->execute(['task_id' => $taskId, 'user_id' => (int)$uid]);
            }

            $this->pdo->commit();
            return ['id' => $taskId, 'task_code' => $code];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function update($id, $data, ?array $assigneeIds = null)
    {
        if (is_array($data) && array_key_exists('remark', $data)) {
            unset($data['remark']);
        }

        $this->pdo->beginTransaction();
        try {
            if (!empty($data)) {
                $fields = [];
                foreach ($data as $k => $v) {
                    $fields[] = "$k = :$k";
                }

                $sql = "UPDATE tasks SET " . implode(",", $fields) . ", updated_at = NOW() WHERE id = :id";
                $data['id'] = $id;

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($data);
            }

            // ... ส่วน replace assignees ของคุณเหมือนเดิม ...
            if (is_array($assigneeIds)) {
                $assigneeIds = array_values(array_unique(array_filter(array_map('intval', $assigneeIds), fn($v) => $v > 0)));

                $del = $this->pdo->prepare("DELETE FROM task_assignees WHERE task_id = :id");
                $del->execute(['id' => $id]);

                if (!empty($assigneeIds)) {
                    $ins = $this->pdo->prepare("
                    INSERT IGNORE INTO task_assignees (task_id, user_id, created_at)
                    VALUES (:task_id, :user_id, NOW())
                ");
                    foreach ($assigneeIds as $uid) {
                        $ins->execute(['task_id' => $id, 'user_id' => (int)$uid]);
                    }

                    $main = (int)$assigneeIds[0];
                    $updMain = $this->pdo->prepare("UPDATE tasks SET assigned_to = :uid, updated_at = NOW() WHERE id = :id");
                    $updMain->execute(['uid' => $main, 'id' => $id]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ลบงาน (ลบ assignees ก่อน)
    public function delete($id)
    {
        $this->pdo->beginTransaction();
        try {
            // 1) ลบ logs ก่อน (FK task_status_logs -> tasks)
            $stmt0 = $this->pdo->prepare("DELETE FROM task_status_logs WHERE task_id = :id");
            $stmt0->execute(['id' => $id]);

            // 2) ลบ assignees ก่อน (ถ้ามีตารางนี้)
            $stmt1 = $this->pdo->prepare("DELETE FROM task_assignees WHERE task_id = :id");
            $stmt1->execute(['id' => $id]);

            // 3) ค่อยลบ tasks
            $stmt2 = $this->pdo->prepare("DELETE FROM tasks WHERE id = :id");
            $stmt2->execute(['id' => $id]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
