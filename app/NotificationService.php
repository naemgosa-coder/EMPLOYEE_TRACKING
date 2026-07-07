<?php
// app/NotificationService.php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/LineBotService.php';

date_default_timezone_set('Asia/Bangkok');

class NotificationService
{
    private PDO $pdo;
    private ?LineBotService $line = null;
    private const TASK_LOGS_TABLE = 'task_status_logs';

    private const STATUS_LABELS = [
        'pending'     => 'รอดำเนินการ',
        'in_progress' => 'กำลังดำเนินการ',
        'completed'   => 'เสร็จแล้ว',
        'overdue'     => 'เกินกำหนด',
    ];

    // ✅ สำหรับ “ทุกการกระทำ” (อิง task_logs.action)
    private const ACTION_LABELS = [
        'CREATE'         => 'สร้างงาน',
        'UPDATE'         => 'แก้ไขรายละเอียด',
        'DUE_DATE'       => 'เปลี่ยนวันครบกำหนด',
        'ASSIGNEE_ADD'   => 'เพิ่มผู้รับมอบหมาย',
        'ASSIGNEE_REMOVE' => 'ลบผู้รับมอบหมาย',
        'COMMENT'        => 'เพิ่มหมายเหตุ',
        'FILE_ADD'       => 'แนบไฟล์',
        'DELETE'         => 'ลบงาน',
        'STATUS'         => 'อัปเดตสถานะงาน',
        'ASSIGNED'       => 'มอบหมายงาน',
        'DUE_SOON'       => 'ใกล้ถึงกำหนด',
        'OVERDUE'        => 'เกินกำหนด',
    ];

    private const SKIP_SELF_NOTIFY = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        $token = getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '';
        $this->line = $token !== '' ? new LineBotService($token) : null;
    }

    /** ========== PUBLIC APIs ========== */


    public function sendTaskActionByLogId(int $logId): void
    {
        $log = $this->getLogWithTask($logId);
        if (!$log) return;

        $action = strtoupper((string)($log['action'] ?? ''));

        $oldSt = $log['old_status'] ?? null;
        $newSt = $log['new_status'] ?? null;

        if ($action === '' && $oldSt !== null && $newSt !== null && (string)$oldSt !== (string)$newSt) {
            $action = 'STATUS';
        }

        if ($action === '') {
            $action = 'UPDATE';
        }

        if ($action === 'STATUS') {
            $this->sendTaskStatusChangedByLogId($logId);
            return;
        }

        $taskId = (int)($log['task_id'] ?? 0);
        if ($taskId <= 0) return;

        $task = $this->getTask($taskId);
        $meta = $this->parseMeta($log);

        // ✅ กรณีลบงานแล้วดึง task ไม่ได้: ให้เก็บ snapshot ใน meta_json['task']
        if (!$task && $action === 'DELETE' && !empty($meta['task']) && is_array($meta['task'])) {
            $task = $meta['task'];
            $task['id'] = $taskId;
        }
        if (!$task) return;

        $label = self::ACTION_LABELS[$action] ?? $action;

        // ผู้รับหลัก: assignees + assigned_by
        $recipients = $this->getAssignees($taskId, $task);
        $recipients[] = [
            'id'          => (int)($task['assigned_by'] ?? 0),
            'email'       => (string)($task['assigned_by_email'] ?? ''),
            'full_name'   => (string)($task['assigned_by_name'] ?? ''),
            'line_user_id' => (string)($task['assigned_by_line_user_id'] ?? ''),
        ];

        // ✅ ถ้า action มี “ผู้ถูกกระทำ” (เช่น remove assignee) แม้ตอนนี้ไม่อยู่ใน assignees แล้ว
        if (!empty($meta['target_user_id'])) {
            $tu = $this->getUserMini((int)$meta['target_user_id']);
            if ($tu) $recipients[] = $tu;
        }

        $recipients = $this->uniqueRecipients($recipients);
        if (!$recipients) return;

        $actorName = (string)($log['changed_by_name'] ?? '-');
        $field     = (string)($log['field'] ?? '');
        $oldV      = (string)($log['old_value'] ?? '');
        $newV      = (string)($log['new_value'] ?? '');
        $note      = (string)($log['note'] ?? '');

        $changeLine = '';
        if ($field !== '' && ($oldV !== '' || $newV !== '')) {
            if ($field === 'due_date') {
                $changeLine = "ครบกำหนด: " . $this->fmtDate($oldV) . " → " . $this->fmtDate($newV);
            } else {
                $changeLine = "{$field}: {$oldV} → {$newV}";
            }
        } elseif (!empty($meta['summary'])) {
            $changeLine = (string)$meta['summary'];
        }

        $subject = "[{$label}] {$task['task_code']} {$task['title']}";

        $html = $this->wrapEmail($label, '
          <div style="font-size:14px;color:#0f172a;">
            มีการ' . $this->h($label) . ' ในงาน
          </div>
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;">
            <div><b>รหัสงาน:</b> ' . $this->h($task['task_code'] ?? '-') . '</div>
            <div><b>หัวข้อ:</b> ' . $this->h($task['title'] ?? '-') . '</div>
            <div><b>โดย:</b> ' . $this->h($actorName) . '</div>
            ' . ($changeLine !== '' ? '<div><b>รายละเอียด:</b> ' . $this->h($changeLine) . '</div>' : '') . '
            ' . ($note !== '' ? '<div style="margin-top:8px;"><b>หมายเหตุ:</b><br>' . $this->h($note) . '</div>' : '') . '
          </div>
        ');

        $lineMsg = $this->lineMsgGenericAction($task, $label, $actorName, $changeLine, $note);

        $eventBase = "action:{$action}:log{$logId}";

        foreach ($recipients as $r) {
            $uid = (int)($r['id'] ?? 0);

            if (self::SKIP_SELF_NOTIFY && !empty($log['changed_by']) && (int)$log['changed_by'] === $uid) continue;

            $eventKey = $eventBase . ":u" . $uid;

            // EMAIL
            $this->sendOnce($eventKey, $taskId, $uid ?: null, (string)($r['email'] ?? ''), $action, $subject, $html);

            // LINE
            $lineUserId = trim((string)($r['line_user_id'] ?? ''));
            if ($lineUserId !== '') {
                $this->sendLineOnce($eventKey, $taskId, $uid ?: null, $lineUserId, $action, $lineMsg);
            }
        }
    }

    // 1) งานใหม่ถูกมอบหมาย -> ส่งให้ผู้รับมอบหมายทั้งหมด
    public function sendTaskAssigned(int $taskId): void
    {
        $task = $this->getTask($taskId);
        if (!$task) return;

        $recipients = $this->getAssignees($taskId, $task); // assignees only
        if (!$recipients) return;

        $subject = "[งานใหม่] {$task['task_code']} {$task['title']}";
        $html    = $this->tplTaskAssigned($task);

        $eventBase = "assigned:{$taskId}:" . ($task['created_at'] ?? date('Y-m-d H:i:s'));

        foreach ($recipients as $r) {
            $uid = (int)($r['id'] ?? 0);
            $eventKey = $eventBase . ":u" . $uid;

            // EMAIL
            $this->sendOnce($eventKey, $taskId, $uid ?: null, (string)($r['email'] ?? ''), 'ASSIGNED', $subject, $html);

            // LINE
            $lineUserId = trim((string)($r['line_user_id'] ?? ''));
            if ($lineUserId !== '') {
                $msg = $this->lineMsgAssigned($task);
                $this->sendLineOnce($eventKey, $taskId, $uid ?: null, $lineUserId, 'ASSIGNED', $msg);
            }
        }
    }

    // 2) เปลี่ยนสถานะ -> ส่งให้ "ผู้มอบหมาย + ผู้รับมอบหมายทั้งหมด"
    public function sendTaskStatusChangedByLogId(int $logId): void
    {
        $row = $this->getLogWithTask($logId);
        if (!$row) return;

        $taskId = (int)$row['task_id'];
        $task = $this->getTask($taskId);
        if (!$task) return;

        $newLabel = self::STATUS_LABELS[$row['new_status']] ?? $row['new_status'];

        $subject = "[อัปเดตสถานะ] {$task['task_code']} {$task['title']} → {$newLabel}";
        $html    = $this->tplStatusChanged($task, $row);

        $recipients = $this->getAssignees($taskId, $task);

        // รวมผู้มอบหมายด้วย
        $recipients[] = [
            'id' => (int)($task['assigned_by'] ?? 0),
            'email' => (string)($task['assigned_by_email'] ?? ''),
            'full_name' => (string)($task['assigned_by_name'] ?? ''),
            'line_user_id' => (string)($task['assigned_by_line_user_id'] ?? ''),
        ];

        $recipients = $this->uniqueRecipients($recipients);

        $eventBase = "status:log{$logId}";

        foreach ($recipients as $r) {
            $uid = (int)($r['id'] ?? 0);

            if (self::SKIP_SELF_NOTIFY && !empty($row['changed_by']) && (int)$row['changed_by'] === $uid) continue;

            $eventKey = $eventBase . ":u" . $uid;

            // EMAIL
            $this->sendOnce($eventKey, $taskId, $uid ?: null, (string)($r['email'] ?? ''), 'STATUS', $subject, $html);

            // LINE
            $lineUserId = trim((string)($r['line_user_id'] ?? ''));
            if ($lineUserId !== '') {
                $msg = $this->lineMsgStatusChanged($task, $row);
                $this->sendLineOnce($eventKey, $taskId, $uid ?: null, $lineUserId, 'STATUS', $msg);
            }
        }
    }

    // 3) ใกล้ถึงกำหนด (เช่น 1 วันก่อน) -> ส่งให้ผู้รับมอบหมาย + ผู้มอบหมาย
    public function sendDueSoonReminders(int $daysAhead = 1): void
    {
        $target = (new DateTimeImmutable('today'))->modify("+{$daysAhead} day")->format('Y-m-d');

        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   ub.full_name AS assigned_by_name,
                   ub.email AS assigned_by_email,
                   ub.line_user_id AS assigned_by_line_user_id
            FROM tasks t
            LEFT JOIN users ub ON ub.id = t.assigned_by
            WHERE t.status IN ('pending','in_progress')
              AND t.due_date = :d
        ");
        $stmt->execute(['d' => $target]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $task) {
            $taskId = (int)$task['id'];

            $recipients = $this->getAssignees($taskId, $task);

            // รวมผู้มอบหมาย
            $recipients[] = [
                'id' => (int)($task['assigned_by'] ?? 0),
                'email' => (string)($task['assigned_by_email'] ?? ''),
                'full_name' => (string)($task['assigned_by_name'] ?? ''),
                'line_user_id' => (string)($task['assigned_by_line_user_id'] ?? ''),
            ];

            $recipients = $this->uniqueRecipients($recipients);
            if (!$recipients) continue;

            $subject = "[ใกล้ถึงกำหนด] {$task['task_code']} {$task['title']} (ครบกำหนด {$this->fmtDate($task['due_date'])})";
            $html    = $this->tplDueSoon($task, $daysAhead);

            $eventBase = "dueSoon:{$taskId}:{$task['due_date']}:" . date('Y-m-d'); // ส่งวันละครั้ง

            foreach ($recipients as $r) {
                $uid = (int)($r['id'] ?? 0);
                $eventKey = $eventBase . ":u" . $uid;

                // EMAIL
                $this->sendOnce($eventKey, $taskId, $uid ?: null, (string)($r['email'] ?? ''), 'DUE_SOON', $subject, $html);

                // LINE
                $lineUserId = trim((string)($r['line_user_id'] ?? ''));
                if ($lineUserId !== '') {
                    $msg = $this->lineMsgDueSoon($task, $daysAhead);
                    $this->sendLineOnce($eventKey, $taskId, $uid ?: null, $lineUserId, 'DUE_SOON', $msg);
                }
            }
        }
    }

    // 4) เกินกำหนด -> ส่งให้ผู้รับมอบหมาย + ผู้มอบหมาย (ส่งวันละครั้งจนกว่าจะ completed)
    public function sendOverdueReminders(): void
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        $stmt = $this->pdo->prepare("
            SELECT t.*,
                   ub.full_name AS assigned_by_name,
                   ub.email AS assigned_by_email,
                   ub.line_user_id AS assigned_by_line_user_id
            FROM tasks t
            LEFT JOIN users ub ON ub.id = t.assigned_by
            WHERE t.status <> 'completed'
              AND t.due_date < :today
        ");
        $stmt->execute(['today' => $today]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as $task) {
            $taskId = (int)$task['id'];

            $recipients = $this->getAssignees($taskId, $task);

            // รวมผู้มอบหมาย
            $recipients[] = [
                'id' => (int)($task['assigned_by'] ?? 0),
                'email' => (string)($task['assigned_by_email'] ?? ''),
                'full_name' => (string)($task['assigned_by_name'] ?? ''),
                'line_user_id' => (string)($task['assigned_by_line_user_id'] ?? ''),
            ];

            $recipients = $this->uniqueRecipients($recipients);
            if (!$recipients) continue;

            $subject = "[เกินกำหนด] {$task['task_code']} {$task['title']} (ครบกำหนด {$this->fmtDate($task['due_date'])})";
            $html    = $this->tplOverdue($task);

            $eventBase = "overdue:{$taskId}:" . date('Y-m-d'); // วันละครั้ง

            foreach ($recipients as $r) {
                $uid = (int)($r['id'] ?? 0);
                $eventKey = $eventBase . ":u" . $uid;

                // EMAIL
                $this->sendOnce($eventKey, $taskId, $uid ?: null, (string)($r['email'] ?? ''), 'OVERDUE', $subject, $html);

                // LINE
                $lineUserId = trim((string)($r['line_user_id'] ?? ''));
                if ($lineUserId !== '') {
                    $msg = $this->lineMsgOverdue($task);
                    $this->sendLineOnce($eventKey, $taskId, $uid ?: null, $lineUserId, 'OVERDUE', $msg);
                }
            }
        }
    }

    /** ========== INTERNAL HELPERS ========== */

    // ====== task_logs column resolver (กัน schema ไม่ตรง) ======
    private static ?array $taskLogsCols = null;

    private function getTaskLogsCols(): array
    {
        if (self::$taskLogsCols !== null) return self::$taskLogsCols;

        try {
            $rows = $this->pdo->query("SHOW COLUMNS FROM " . self::TASK_LOGS_TABLE)->fetchAll(PDO::FETCH_ASSOC);
            $cols = [];
            foreach ($rows as $r) {
                $f = (string)($r['Field'] ?? '');
                if ($f !== '') $cols[$f] = true;
            }
            self::$taskLogsCols = $cols;
            return self::$taskLogsCols;
       } catch (Throwable $e) {
            self::$taskLogsCols = array_fill_keys([
                'id','log_id','logId',
                'task_id','taskId','taskID',
                'changed_by','user_id','created_by','actor_id',
                'old_status','from_status','oldStatus',
                'new_status','to_status','newStatus',
                'note','message','remark','detail',
                'created_at','createdAt','log_time','logged_at',
                'action','field','old_value','new_value','meta_json','meta'
            ], true);
            return self::$taskLogsCols;
        }
    }

    private function pickCol(array $colsMap, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if (isset($colsMap[$c])) return $c;
        }
        return null;
    }

    private function parseMeta(array $log): array
    {
        $raw = (string)($log['meta_json'] ?? '');
        if ($raw === '') return [];
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    private function getUserMini(int $userId): ?array
    {
        if ($userId <= 0) return null;
        $st = $this->pdo->prepare("SELECT id, full_name, email, line_user_id FROM users WHERE id=:id LIMIT 1");
        $st->execute(['id' => $userId]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        return $u ?: null;
    }

    private function getTask(int $taskId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
              t.*,
              ub.full_name AS assigned_by_name,
              ub.email AS assigned_by_email,
              ub.line_user_id AS assigned_by_line_user_id
            FROM tasks t
            LEFT JOIN users ub ON ub.id = t.assigned_by
            WHERE t.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getAssignees(int $taskId, array $taskRow): array
    {
        // ดึงจาก task_assignees
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.full_name, u.email, u.line_user_id
                FROM task_assignees ta
                JOIN users u ON u.id = ta.user_id
                WHERE ta.task_id = :id AND u.is_active = 1
                ORDER BY u.full_name
            ");
            $stmt->execute(['id' => $taskId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // fallback ถ้าไม่มีใน task_assignees
            if (!$rows && !empty($taskRow['assigned_to'])) {
                $stmt2 = $this->pdo->prepare("SELECT id, full_name, email, line_user_id FROM users WHERE id = :id AND is_active=1 LIMIT 1");
                $stmt2->execute(['id' => (int)$taskRow['assigned_to']]);
                $u = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($u) $rows = [$u];
            }

            return $this->uniqueRecipients($rows);
        } catch (Throwable $e) {
            // fallback สุดท้าย
            if (!empty($taskRow['assigned_to'])) {
                $stmt2 = $this->pdo->prepare("SELECT id, full_name, email, line_user_id FROM users WHERE id = :id AND is_active=1 LIMIT 1");
                $stmt2->execute(['id' => (int)$taskRow['assigned_to']]);
                $u = $stmt2->fetch(PDO::FETCH_ASSOC);
                return $u ? [$u] : [];
            }
            return [];
        }
    }

    private function getLogWithTask(int $logId): ?array
    {
        $cols = $this->getTaskLogsCols();

        $idCol        = $this->pickCol($cols, ['id', 'log_id']);
        $taskIdCol    = $this->pickCol($cols, ['task_id', 'taskId']);
        $changedByCol = $this->pickCol($cols, ['changed_by', 'user_id', 'created_by', 'actor_id']);

        $oldStatusCol = $this->pickCol($cols, ['old_status', 'from_status']);
        $newStatusCol = $this->pickCol($cols, ['new_status', 'to_status']);

        $noteCol      = $this->pickCol($cols, ['note', 'message', 'remark', 'detail']);
        $createdAtCol = $this->pickCol($cols, ['created_at', 'createdAt', 'log_time', 'logged_at']);

        // action-mode fields (ถ้ามี)
        $actionCol    = $this->pickCol($cols, ['action']);
        $fieldCol     = $this->pickCol($cols, ['field']);
        $oldValueCol  = $this->pickCol($cols, ['old_value', 'oldValue']);
        $newValueCol  = $this->pickCol($cols, ['new_value', 'newValue']);
        $metaCol      = $this->pickCol($cols, ['meta_json', 'meta']);

        if (!$idCol || !$taskIdCol) {
            error_log("[NOTI] task_logs schema missing id/task_id column");
            return null;
        }

        $select = [];
        $select[] = "l.{$idCol} AS id";
        $select[] = "l.{$taskIdCol} AS task_id";
        $select[] = $changedByCol ? "l.{$changedByCol} AS changed_by" : "NULL AS changed_by";
        $select[] = $oldStatusCol ? "l.{$oldStatusCol} AS old_status" : "NULL AS old_status";
        $select[] = $newStatusCol ? "l.{$newStatusCol} AS new_status" : "NULL AS new_status";
        $select[] = $noteCol ? "l.{$noteCol} AS note" : "NULL AS note";
        $select[] = $createdAtCol ? "l.{$createdAtCol} AS created_at" : "NULL AS created_at";

        $select[] = $actionCol ? "l.{$actionCol} AS action" : "NULL AS action";
        $select[] = $fieldCol ? "l.{$fieldCol} AS field" : "NULL AS field";
        $select[] = $oldValueCol ? "l.{$oldValueCol} AS old_value" : "NULL AS old_value";
        $select[] = $newValueCol ? "l.{$newValueCol} AS new_value" : "NULL AS new_value";
        $select[] = $metaCol ? "l.{$metaCol} AS meta_json" : "NULL AS meta_json";

        // join ผู้ใช้งานแบบปลอดภัย
        $join = "LEFT JOIN users u ON 1=0";
        if ($changedByCol) {
            $join = "LEFT JOIN users u ON u.id = l.{$changedByCol}";
        }

        $sql = "
            SELECT " . implode(",\n               ", $select) . ",
            u.full_name AS changed_by_name
            FROM " . self::TASK_LOGS_TABLE . " l
            {$join}
            WHERE l.{$idCol} = :id
            LIMIT 1
    ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $logId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                error_log("[NOTI] log not found: {$logId}");
                return null;
            }
            return $row;
        } catch (Throwable $e) {
            error_log("[NOTI] getLogWithTask error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ unique แบบ “user_id ก่อน” และ fallback ด้วย email/line_user_id
     * (กันซ้ำระหว่าง assignees + assigned_by + target_user)
     */
    private function uniqueRecipients(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $uid  = (int)($r['id'] ?? 0);
            $email = strtolower(trim((string)($r['email'] ?? '')));
            $lid   = trim((string)($r['line_user_id'] ?? ''));

            $key = null;
            if ($uid > 0) $key = "u{$uid}";
            elseif ($email !== '') $key = "e{$email}";
            elseif ($lid !== '') $key = "l{$lid}";
            else $key = null;

            if ($key === null) continue;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $out[] = $r;
        }
        return $out;
    }

    private function isValidEmail(string $email): bool
    {
        $email = trim($email);
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    private function isValidLineUserId(string $lineUserId): bool
    {
        $s = trim($lineUserId);
        return $s !== '' && strlen($s) <= 64;
    }

    private function sendOnce(string $eventKey, int $taskId, ?int $userId, string $email, string $type, string $subject, string $html): void
    {
        if (!$this->isValidEmail($email)) return;

        // reserve กันส่งซ้ำ
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO task_email_logs
              (event_key, task_id, user_id, email, type, subject, send_status, created_at)
            VALUES
              (:k, :tid, :uid, :em, :tp, :sj, 'PENDING', NOW())
        ");
        $stmt->execute([
            'k' => $eventKey,
            'tid' => $taskId,
            'uid' => $userId,
            'em' => $email,
            'tp' => $type,
            'sj' => $subject,
        ]);

        if ($stmt->rowCount() === 0) return; // เคยส่งแล้ว

        $logId = (int)$this->pdo->lastInsertId();
        [$ok, $err] = Mailer::sendWithError($email, $subject, $html);

        if ($ok) {
            $this->pdo->prepare("UPDATE task_email_logs SET send_status='SENT', error=NULL WHERE id=:id")
                ->execute(['id' => $logId]);
        } else {
            $this->pdo->prepare("UPDATE task_email_logs SET send_status='FAILED', error=:e WHERE id=:id")
                ->execute(['id' => $logId, 'e' => $err ?: 'send failed']);
        }
    }

    private function sendLineOnce(string $eventKey, int $taskId, ?int $userId, string $lineUserId, string $type, string $message): void
    {
        if (!$this->line) return;
        if (!$this->isValidLineUserId($lineUserId)) return;

        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO task_line_logs
              (event_key, task_id, user_id, line_user_id, type, message, send_status, created_at)
            VALUES
              (:k, :tid, :uid, :lid, :tp, :msg, 'PENDING', NOW())
        ");
        $stmt->execute([
            'k' => $eventKey,
            'tid' => $taskId,
            'uid' => $userId,
            'lid' => $lineUserId,
            'tp' => $type,
            'msg' => $message,
        ]);

        if ($stmt->rowCount() === 0) return; // เคยส่งแล้ว

        $logId = (int)$this->pdo->lastInsertId();

        try {
            $this->line->pushText($lineUserId, $message);
            $this->pdo->prepare("UPDATE task_line_logs SET send_status='SENT', error=NULL WHERE id=:id")
                ->execute(['id' => $logId]);
        } catch (Throwable $e) {
            $this->pdo->prepare("UPDATE task_line_logs SET send_status='FAILED', error=:e WHERE id=:id")
                ->execute(['id' => $logId, 'e' => $e->getMessage()]);
        }
    }

    private function fmtDate($d): string
    {
        if (!$d) return '-';
        $dt = DateTime::createFromFormat('Y-m-d', (string)$d);
        return $dt ? $dt->format('d/m/Y') : (string)$d;
    }

    private function h($s): string
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    private function taskLink(array $task): string
    {
        global $APP_BASE_URL;

        if (empty($APP_BASE_URL)) return '';

        $tid = (int)($task['id'] ?? 0);
        if ($tid <= 0) return '';

        return rtrim($APP_BASE_URL, '/') . '/myjob?open=' . $tid . '&from=line';
    }

    /** ========== LINE MESSAGE TEMPLATES ========== */

    private function lineMsgGenericAction(array $task, string $label, string $actorName, string $changeLine, string $note): string
    {
        $link = $this->taskLink($task);
        $msg = "🔔 {$label}\n"
            . "{$task['task_code']} {$task['title']}\n"
            . "โดย: {$actorName}\n";
        if ($changeLine !== '') $msg .= "{$changeLine}\n";
        if ($note !== '') $msg .= "หมายเหตุ: {$note}\n";
        if ($link) $msg .= "เปิดดูงาน: {$link}";
        return trim($msg);
    }

    private function lineMsgAssigned(array $task): string
    {
        $link = $this->taskLink($task);
        return "📌 งานใหม่ถูกมอบหมาย\n"
            . "{$task['task_code']} {$task['title']}\n"
            . "แผนก: " . ($task['department'] ?? '-') . "\n"
            . "ครบกำหนด: " . $this->fmtDate($task['due_date'] ?? null) . "\n"
            . "ผู้มอบหมาย: " . ($task['assigned_by_name'] ?? '-') . "\n"
            . ($link ? "เปิดดูงาน: {$link}" : "");
    }

    private function lineMsgStatusChanged(array $task, array $log): string
    {
        $old = self::STATUS_LABELS[$log['old_status']] ?? ($log['old_status'] ?? '-');
        $new = self::STATUS_LABELS[$log['new_status']] ?? ($log['new_status'] ?? '-');
        $link = $this->taskLink($task);

        return "🔔 อัปเดตสถานะงาน\n"
            . "{$task['task_code']} {$task['title']}\n"
            . "สถานะ: {$old} → {$new}\n"
            . "โดย: " . ($log['changed_by_name'] ?? '-') . "\n"
            . ($link ? "เปิดดูงาน: {$link}" : "");
    }

    private function lineMsgDueSoon(array $task, int $daysAhead): string
    {
        $link = $this->taskLink($task);
        return "⏰ ใกล้ถึงกำหนด (อีก {$daysAhead} วัน)\n"
            . "{$task['task_code']} {$task['title']}\n"
            . "ครบกำหนด: " . $this->fmtDate($task['due_date'] ?? null) . "\n"
            . "ผู้มอบหมาย: " . ($task['assigned_by_name'] ?? '-') . "\n"
            . ($link ? "เปิดดูงาน: {$link}" : "");
    }

    private function lineMsgOverdue(array $task): string
    {
        $link = $this->taskLink($task);
        return "⚠️ งานเกินกำหนด\n"
            . "{$task['task_code']} {$task['title']}\n"
            . "ครบกำหนด: " . $this->fmtDate($task['due_date'] ?? null) . "\n"
            . "ผู้มอบหมาย: " . ($task['assigned_by_name'] ?? '-') . "\n"
            . ($link ? "เปิดดูงาน: {$link}" : "");
    }

    /** ========== EMAIL TEMPLATES (ของเดิม) ========== */

    private function wrapEmail(string $title, string $bodyHtml): string
    {
        return '
        <div style="font-family:Arial,TH Sarabun New,sans-serif; background:#f5f7fb; padding:24px;">
          <div style="max-width:720px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
            <div style="padding:16px 18px;border-bottom:1px solid #e5e7eb;">
              <div style="font-weight:700;font-size:16px;color:#0f172a;">' . $this->h($title) . '</div>
              <div style="font-size:12px;color:#64748b;">อีเมลแจ้งเตือนจากระบบงาน</div>
            </div>
            <div style="padding:18px;">
              ' . $bodyHtml . '
            </div>
            <div style="padding:14px 18px;border-top:1px solid #e5e7eb;font-size:12px;color:#94a3b8;">
              * ข้อความนี้ส่งอัตโนมัติ กรุณาอย่าตอบกลับ
            </div>
          </div>
        </div>';
    }

    private function tplTaskAssigned(array $task): string
    {
        $link = $this->taskLink($task);
        $btn = $link ? '<a href="' . $this->h($link) . '" style="display:inline-block;padding:10px 14px;border-radius:10px;background:#1e78d6;color:#fff;text-decoration:none;font-weight:700;">เปิดดูงาน</a>' : '';

        $body = '
          <div style="font-size:14px;color:#0f172a;">
            มีงานใหม่ถูกมอบหมายให้คุณ
          </div>
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;">
            <div><b>รหัสงาน:</b> ' . $this->h($task['task_code']) . '</div>
            <div><b>หัวข้อ:</b> ' . $this->h($task['title']) . '</div>
            <div><b>แผนก:</b> ' . $this->h($task['department'] ?? '-') . '</div>
            <div><b>ครบกำหนด:</b> ' . $this->h($this->fmtDate($task['due_date'] ?? null)) . '</div>
            <div><b>ผู้มอบหมาย:</b> ' . $this->h($task['assigned_by_name'] ?? '-') . '</div>
          </div>
          ' . (!empty($task['description']) ? '<div style="margin-top:12px;"><b>รายละเอียด:</b><br>' . $this->h($task['description']) . '</div>' : '') . '
          <div style="margin-top:14px;">' . $btn . '</div>
        ';

        return $this->wrapEmail('งานใหม่ถูกมอบหมาย', $body);
    }

    private function tplStatusChanged(array $task, array $log): string
    {
        $link = $this->taskLink($task);
        $btn = $link ? '<a href="' . $this->h($link) . '" style="display:inline-block;padding:10px 14px;border-radius:10px;background:#1e78d6;color:#fff;text-decoration:none;font-weight:700;">เปิดดูงาน</a>' : '';

        $old = self::STATUS_LABELS[$log['old_status']] ?? ($log['old_status'] ?? '-');
        $new = self::STATUS_LABELS[$log['new_status']] ?? ($log['new_status'] ?? '-');

        $body = '
          <div style="font-size:14px;color:#0f172a;">
            มีการเปลี่ยนสถานะงาน
          </div>
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;">
            <div><b>รหัสงาน:</b> ' . $this->h($task['task_code']) . '</div>
            <div><b>หัวข้อ:</b> ' . $this->h($task['title']) . '</div>
            <div><b>สถานะ:</b> ' . $this->h($old) . ' → <b>' . $this->h($new) . '</b></div>
            <div><b>โดย:</b> ' . $this->h($log['changed_by_name'] ?? '-') . '</div>
            <div><b>เวลา:</b> ' . $this->h($log['created_at'] ?? '') . '</div>
          </div>
          ' . (!empty($log['note']) ? '<div style="margin-top:12px;"><b>หมายเหตุ:</b><br>' . $this->h($log['note']) . '</div>' : '') . '
          <div style="margin-top:14px;">' . $btn . '</div>
        ';

        return $this->wrapEmail('อัปเดตสถานะงาน', $body);
    }

    private function tplDueSoon(array $task, int $daysAhead): string
    {
        $link = $this->taskLink($task);
        $btn = $link ? '<a href="' . $this->h($link) . '" style="display:inline-block;padding:10px 14px;border-radius:10px;background:#1e78d6;color:#fff;text-decoration:none;font-weight:700;">เปิดดูงาน</a>' : '';

        $body = '
          <div style="font-size:14px;color:#0f172a;">
            งานนี้ใกล้ถึงกำหนด (อีก ' . $this->h($daysAhead) . ' วัน)
          </div>
          <div style="margin-top:12px;padding:12px;border:1px solid #e5e7eb;border-radius:12px;">
            <div><b>รหัสงาน:</b> ' . $this->h($task['task_code']) . '</div>
            <div><b>หัวข้อ:</b> ' . $this->h($task['title']) . '</div>
            <div><b>ครบกำหนด:</b> <b>' . $this->h($this->fmtDate($task['due_date'] ?? null)) . '</b></div>
            <div><b>ผู้มอบหมาย:</b> ' . $this->h($task['assigned_by_name'] ?? '-') . '</div>
          </div>
          <div style="margin-top:14px;">' . $btn . '</div>
        ';

        return $this->wrapEmail('แจ้งเตือนใกล้ถึงกำหนด', $body);
    }

    private function tplOverdue(array $task): string
    {
        $link = $this->taskLink($task);
        $btn = $link ? '<a href="' . $this->h($link) . '" style="display:inline-block;padding:10px 14px;border-radius:10px;background:#ef4444;color:#fff;text-decoration:none;font-weight:700;">เปิดดูงาน</a>' : '';

        $body = '
          <div style="font-size:14px;color:#0f172a;">
            งานนี้ <b style="color:#ef4444;">เกินกำหนด</b> แล้ว
          </div>
          <div style="margin-top:12px;padding:12px;border:1px solid #fee2e2;border-radius:12px;">
            <div><b>รหัสงาน:</b> ' . $this->h($task['task_code']) . '</div>
            <div><b>หัวข้อ:</b> ' . $this->h($task['title']) . '</div>
            <div><b>ครบกำหนด:</b> <b style="color:#ef4444;">' . $this->h($this->fmtDate($task['due_date'] ?? null)) . '</b></div>
            <div><b>ผู้มอบหมาย:</b> ' . $this->h($task['assigned_by_name'] ?? '-') . '</div>
          </div>
          <div style="margin-top:14px;">' . $btn . '</div>
        ';

        return $this->wrapEmail('แจ้งเตือนเกินกำหนด', $body);
    }
}
