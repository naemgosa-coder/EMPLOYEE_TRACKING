<?php
// routes/api.php

use App\Models\TaskModel;
use App\Models\TaskLogModel;
use App\Models\UserModel;
use App\Services\NotificationService;

header('Content-Type: application/json; charset=utf-8');

function json_ok($data = null)
{
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error($message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function get_json_body()
{
    $raw = file_get_contents('php://input');
    if ($raw) {
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
    }
    return is_array($_POST) ? $_POST : [];
}

/**
 * ✅ เช็คสิทธิ์เข้าถึงงาน
 * - boss: ได้หมด
 * - employee: ได้ถ้า (assigned_to == ฉัน) หรือ (assigned_by == ฉัน) หรือ (อยู่ใน task_assignees)
 */
function can_access_task($pdo, $role, $userId, $taskId, $taskRow)
{
    if ($role === 'boss') return true;
    if (!$taskRow) return false;

    if ((int)($taskRow['assigned_to'] ?? 0) === (int)$userId) return true;
    if ((int)($taskRow['assigned_by'] ?? 0) === (int)$userId) return true;

    $stmt = $pdo->prepare("SELECT 1 FROM task_assignees WHERE task_id = :tid AND user_id = :uid LIMIT 1");
    $stmt->execute(['tid' => (int)$taskId, 'uid' => (int)$userId]);
    return (bool)$stmt->fetchColumn();
}

$route = $_GET['route'] ?? '';
$route = trim($route, '/');

if (!str_starts_with($route, 'api')) {
    json_error('INVALID_API_ROUTE', 404);
}

$path = trim(substr($route, 3), '/'); // remove "api"
$segments = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$taskModel = new TaskModel($pdo);
$taskLogModel = new TaskLogModel($pdo);
$userModel = new UserModel($pdo);
$noti = new NotificationService($pdo);

$user = current_user();
$userId = $user['id'] ?? null;
$role = $user['role'] ?? null;

if (($segments[0] ?? '') === 'ping') {
    json_ok(['pong' => true, 'time' => date('c')]);
}

require_login_api();

if (($segments[0] ?? '') === 'me') {
    json_ok([
        'id' => $user['id'],
        'username' => $user['username'] ?? null,
        'full_name' => $user['full_name'] ?? null,
        'role' => $user['role'] ?? null,
    ]);
}

if (($segments[0] ?? '') === 'dashboard') {
    $params = [
        'department' => $_GET['department'] ?? '',
        'year'       => $_GET['year'] ?? '',
    ];

    if ($role !== 'boss') {
        $params['assigned_to'] = (int)$userId;
        unset($params['department']);
    }

    $rows = $taskModel->filterTasks($params);

    $counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];
    $display = ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0];

    $today = new DateTime('today');

    foreach ($rows as $r) {
        $raw = (string)($r['status'] ?? 'pending');
        $base = in_array($raw, ['pending', 'in_progress', 'completed'], true) ? $raw : 'pending';
        $counts[$base]++;

        $isOverdue = false;
        if ($base !== 'completed') {
            $dueStr = (string)($r['due_date'] ?? '');
            if ($dueStr !== '') {
                $due = DateTime::createFromFormat('Y-m-d', $dueStr);
                if ($due && $due < $today) $isOverdue = true;
            }
        }

        if ($isOverdue) {
            $counts['overdue']++;
            $display['overdue']++;
        } else {
            $display[$base]++;
        }
    }

    $latest = array_slice($rows, 0, 10);

    json_ok([
        'counts' => $counts,
        'display_counts' => $display,
        'latest_tasks' => $latest
    ]);
}

if (($segments[0] ?? '') === 'tasks') {

    // -------------------------
    // /api/tasks
    // -------------------------
    if (count($segments) === 1) {

        // ✅ LIST
        if ($method === 'GET') {
            $params = [
                'keyword' => $_GET['keyword'] ?? '',
                'status' => $_GET['status'] ?? '',
                'department' => $_GET['department'] ?? '',
                'year' => $_GET['year'] ?? '',
            ];

            // legacy mine=1 (เดิม)
            if (($_GET['mine'] ?? '') === '1') {
                $params['assigned_to'] = $userId;
            }

            // employee: บังคับเป็นของตัวเองเสมอ (กันแก้ query หลอก)
            if ($role !== 'boss') {
                unset($params['assigned_by'], $params['assigned_to']);

                $scope = trim($_GET['scope'] ?? '');
                if ($scope === 'assigned_by') {
                    $params['assigned_by'] = (int)$userId;
                } else {
                    $params['assigned_to'] = (int)$userId;
                }

                unset($params['department']);
            } else {
                if (!empty($_GET['assigned_by'])) $params['assigned_by'] = (int)$_GET['assigned_by'];
                if (!empty($_GET['assigned_to'])) $params['assigned_to'] = (int)$_GET['assigned_to'];
            }

            $rows = $taskModel->filterTasks($params);

            $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : null;
            if ($limit) $rows = array_slice($rows, 0, $limit);

            json_ok($rows);
        }

        // ✅ CREATE (ของเดิม ยังบังคับ boss)
        if ($method === 'POST') {
            require_boss_api();
            $body = get_json_body();

            foreach (['task_code', 'title', 'assigned_to', 'status'] as $k) {
                if (empty($body[$k])) json_error("MISSING_$k", 422);
            }

            $data = [
                'task_code' => trim($body['task_code']),
                'title' => trim($body['title']),
                'description' => trim((string)($body['description'] ?? '')),
                'assigned_to' => (int)$body['assigned_to'],
                'assigned_by' => (int)$userId,
                'status' => $body['status'],
                'due_date' => $body['due_date'] ?? null,
                'remark' => trim((string)($body['remark'] ?? '')),
            ];

            $id = (int)$taskModel->create($data);

            // log
            $logIdRaw = $taskLogModel->addLog($id, (int)$userId, null, $data['status'], 'สร้างงานใหม่');
            $logId = is_numeric($logIdRaw) ? (int)$logIdRaw : (int)$pdo->lastInsertId();

            // ✅ แจ้งเตือนงานใหม่ (Email + Line)
            try {
                $noti->sendTaskAssigned($id);
            } catch (Throwable $e) {
                error_log('[NOTI][ASSIGNED] ' . $e->getMessage());
            }

            json_ok(['id' => $id, 'log_id' => $logId]);
        }

        json_error('METHOD_NOT_ALLOWED', 405);
    }

    // -------------------------
    // /api/tasks/{id}
    // -------------------------
    $taskId = (int)($segments[1] ?? 0);
    if ($taskId <= 0) json_error('INVALID_ID', 400);

    if (count($segments) === 2) {

        // ✅ DETAIL
        if ($method === 'GET') {
            $t = $taskModel->find($taskId);
            if (!$t) json_error('NOT_FOUND', 404);

            if (!can_access_task($pdo, $role, $userId, $taskId, $t)) {
                json_error('FORBIDDEN', 403);
            }

            json_ok($t);
        }

        // ✅ UPDATE (ให้แก้ได้ถ้าเป็น boss หรือ "ผู้มอบหมาย")
        if ($method === 'PATCH') {
            $t = $taskModel->find($taskId);
            if (!$t) json_error('NOT_FOUND', 404);

            $isAssigner = ((int)($t['assigned_by'] ?? 0) === (int)$userId);

            if ($role !== 'boss' && !$isAssigner) {
                json_error('FORBIDDEN', 403);
            }

            $body = get_json_body();
            $allowed = ['title', 'description', 'assigned_to', 'status', 'due_date', 'remark'];
            $data = [];
            foreach ($allowed as $k) {
                if (array_key_exists($k, $body)) $data[$k] = $body[$k];
            }
            if (!$data) json_error('NO_FIELDS', 422);

            $oldStatus = (string)($t['status'] ?? '');
            $oldTitle  = (string)($t['title'] ?? '');
            $oldDue    = (string)($t['due_date'] ?? '');

            $taskModel->update($taskId, $data);

            // ✅ ถ้าเปลี่ยนสถานะ -> log + แจ้งเตือนสถานะ (รวม completed)
            if (isset($data['status']) && (string)$data['status'] !== $oldStatus) {
                $logIdRaw = $taskLogModel->addLog($taskId, (int)$userId, $oldStatus, (string)$data['status'], 'เปลี่ยนสถานะงาน');
                $logId = is_numeric($logIdRaw) ? (int)$logIdRaw : (int)$pdo->lastInsertId();

                try {
                    $noti->sendTaskStatusChangedByLogId($logId);
                } catch (Throwable $e) {
                    error_log('[NOTI][STATUS] ' . $e->getMessage());
                }

                json_ok(['updated' => true, 'log_id' => $logId]);
            }

            // ✅ อัปเดตงาน (ไม่ใช่ status) -> ทำ log แบบ “ทั่วไป” แล้วเรียก sendTaskActionByLogId
            $changes = [];
            if (isset($data['title']) && (string)$data['title'] !== $oldTitle) $changes[] = 'แก้ไขหัวข้อ';
            if (array_key_exists('description', $data)) $changes[] = 'แก้ไขรายละเอียด';
            if (isset($data['due_date']) && (string)$data['due_date'] !== $oldDue) $changes[] = 'เปลี่ยนกำหนดส่ง';
            if (isset($data['assigned_to'])) $changes[] = 'เปลี่ยนผู้รับมอบหมายหลัก';
            if (isset($data['remark'])) $changes[] = 'แก้ไขหมายเหตุ';

            $note = $changes ? ('อัปเดตงาน: ' . implode(', ', $changes)) : 'อัปเดตงาน';

            $logIdRaw = $taskLogModel->addLog($taskId, (int)$userId, $oldStatus, $oldStatus, $note);
            $logId = is_numeric($logIdRaw) ? (int)$logIdRaw : (int)$pdo->lastInsertId();

            try {
                // ✅ ตัวนี้จะส่ง Email + Line ถึงผู้รับมอบหมาย + ผู้มอบหมาย
                $noti->sendTaskActionByLogId($logId);
            } catch (Throwable $e) {
                error_log('[NOTI][ACTION] ' . $e->getMessage());
            }

            json_ok(['updated' => true, 'log_id' => $logId]);
        }

        // ✅ DELETE (boss หรือ "ผู้มอบหมาย" เท่านั้น)
        if ($method === 'DELETE') {
            $t = $taskModel->find($taskId);
            if (!$t) json_error('NOT_FOUND', 404);

            $isAssigner = ((int)($t['assigned_by'] ?? 0) === (int)$userId);
            if ($role !== 'boss' && !$isAssigner) json_error('FORBIDDEN', 403);

            $taskModel->delete($taskId);
            json_ok(['deleted' => true]);
        }

        json_error('METHOD_NOT_ALLOWED', 405);
    }

    // -------------------------
    // /api/tasks/{id}/logs
    // -------------------------
    if (($segments[2] ?? '') === 'logs' && $method === 'GET') {
        $t = $taskModel->find($taskId);
        if (!$t) json_error('NOT_FOUND', 404);

        if (!can_access_task($pdo, $role, $userId, $taskId, $t)) {
            json_error('FORBIDDEN', 403);
        }

        $logs = $taskLogModel->getLogsByTask($taskId);
        json_ok($logs);
    }

    // -------------------------
    // /api/tasks/{id}/status
    // -------------------------
    if (($segments[2] ?? '') === 'status' && $method === 'PATCH') {
        $t = $taskModel->find($taskId);
        if (!$t) json_error('NOT_FOUND', 404);

        if (!can_access_task($pdo, $role, $userId, $taskId, $t)) {
            json_error('FORBIDDEN', 403);
        }

        $body = get_json_body();
        $new = $body['status'] ?? '';
        $note = trim((string)($body['note'] ?? ''));

        if (!in_array($new, ['pending', 'in_progress', 'completed', 'overdue'], true)) json_error('INVALID_STATUS', 422);

        $old = $t['status'] ?? null;
        $taskModel->update($taskId, ['status' => $new]);

        $logIdRaw = $taskLogModel->addLog($taskId, (int)$userId, $old, $new, $note ?: 'อัปเดตสถานะ');
        $logId = is_numeric($logIdRaw) ? (int)$logIdRaw : (int)$pdo->lastInsertId();

        try {
            $noti->sendTaskStatusChangedByLogId($logId);
        } catch (Throwable $e) {
            error_log('[NOTI][STATUS] ' . $e->getMessage());
        }

        json_ok(['updated' => true, 'log_id' => $logId]);
    }

    json_error('NOT_FOUND', 404);
}

if (($segments[0] ?? '') === 'reports') {
    if (($segments[1] ?? '') === 'export') {
        $format = $_GET['format'] ?? 'csv';
        if ($format !== 'csv') json_error('FORMAT_NOT_SUPPORTED', 422);

        require_boss_api();

        $params = [
            'keyword' => $_GET['keyword'] ?? '',
            'status'  => $_GET['status'] ?? '',
        ];
        if (!empty($_GET['assigned_to'])) $params['assigned_to'] = (int)$_GET['assigned_to'];

        $rows = $taskModel->filterTasks($params);

        // ===== หา "วันที่งานเสร็จสิ้น" จาก logs ที่มีอยู่แล้ว (ไม่เดาชื่อตาราง/คอลัมน์เอง) =====
        $completedMapTs = []; // task_id => min timestamp ที่กลายเป็น completed

        $taskIds = array_values(array_filter(array_map(
            fn($t) => (int)($t['id'] ?? 0),
            $rows
        ), fn($x) => $x > 0));

        if (!empty($taskIds)) {
            $logs = $taskLogModel->getLogsForTasks($taskIds);

            // helper: หา field เวลาใน log (รองรับหลายชื่อ)
            $pickTime = function (array $l): string {
                foreach (
                    [
                        'created_at',
                        'createdAt',
                        'log_time',
                        'logged_at',
                        'created_date',
                        'createdDate',
                        'time',
                        'at',
                        'date'
                    ] as $k
                ) {
                    if (!empty($l[$k])) return (string)$l[$k];
                }
                return '';
            };

            foreach ($logs as $l) {
                $tid = (int)($l['task_id'] ?? 0);
                if ($tid <= 0) continue;

                $newStatus = (string)($l['new_status'] ?? '');
                if ($newStatus !== 'completed') continue;

                $timeStr = $pickTime($l);
                if ($timeStr === '') continue;

                $ts = strtotime($timeStr);
                if (!$ts) continue;

                if (!isset($completedMapTs[$tid]) || $ts < $completedMapTs[$tid]) {
                    $completedMapTs[$tid] = $ts; // เอาเวลาที่ completed ครั้งแรก
                }
            }
        }

        // ===== ส่ง CSV =====
        header_remove('Content-Type');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="reports.csv"');

        $out = fopen('php://output', 'w');

        // BOM กันภาษาไทยเพี้ยนใน Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // ✅ หัวตารางเรียงตามที่คุณต้องการ
        fputcsv($out, ['Tack Code', 'Title', 'Due Date', 'Assigned To', 'Staus', 'วันที่งานเสร็จสิ้น']);

        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $completedDate = (isset($completedMapTs[$id]) ? date('Y-m-d', $completedMapTs[$id]) : '');

            fputcsv($out, [
                $r['task_code'] ?? '',
                $r['title'] ?? '',
                $r['due_date'] ?? '',
                $r['assigned_to_name'] ?? ($r['assigned_to_username'] ?? ($r['assigned_to'] ?? '')),
                $r['status'] ?? '',
                $completedDate,
            ]);
        }

        fclose($out);
        exit;
    }

    $params = [
        'keyword' => $_GET['keyword'] ?? '',
        'status' => $_GET['status'] ?? '',
    ];
    if (!empty($_GET['assigned_to'])) $params['assigned_to'] = (int)$_GET['assigned_to'];
    if ($role !== 'boss') $params['assigned_to'] = $userId;

    $tasks = $taskModel->filterTasks($params);
    $taskIds = array_map(fn($t) => (int)$t['id'], $tasks);

    $logsGrouped = [];
    if ($taskIds) {
        $logs = $taskLogModel->getLogsForTasks($taskIds);
        foreach ($logs as $l) {
            $tid = (int)$l['task_id'];
            if (!isset($logsGrouped[$tid])) $logsGrouped[$tid] = [];
            $logsGrouped[$tid][] = $l;
        }
    }

    json_ok(['tasks' => $tasks, 'logs' => $logsGrouped]);
}

if (($segments[0] ?? '') === 'users') {
    require_boss_api();

    $sub = $segments[1] ?? '';

    // ✅ POST /api/users/update  (แก้ได้เฉพาะชื่อ-นามสกุล)
    if ($sub === 'update') {
        if ($method !== 'POST') json_error('METHOD_NOT_ALLOWED', 405);

        $body = get_json_body();
        $id = (int)($body['id'] ?? 0);
        $fullName = trim((string)($body['full_name'] ?? ''));

        if ($id <= 0) json_error('MISSING_ID', 422);
        if ($fullName === '') json_error('MISSING_FULL_NAME', 422);

        // กันแก้ตัวเอง (ถ้ายังต้องการกฎนี้เหมือนเดิม)
        if ($id === (int)($userId ?? 0)) json_error('SELF_ACTION', 400);

        // เช็คผู้ใช้
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) json_error('NOT_FOUND', 404);

        // กันแก้บัญชีผู้บริหาร
        if (strtolower((string)($u['role'] ?? '')) === 'boss') json_error('PROTECTED', 400);

        // ✅ อัปเดตเฉพาะ full_name (ไม่แตะ role)
        $stmt = $pdo->prepare("UPDATE users SET full_name = :f WHERE id = :id");
        $stmt->execute(['f' => $fullName, 'id' => $id]);

        json_ok(['updated' => true]);
    }

    // ✅ POST /api/users/password
    if ($sub === 'password') {
        if ($method !== 'POST') json_error('METHOD_NOT_ALLOWED', 405);

        $body = get_json_body();
        $id = (int)($body['id'] ?? 0);
        $password = (string)($body['password'] ?? '');

        if ($id <= 0 || $password === '') json_error('MISSING_FIELDS', 422);
        if (strlen($password) < 8) json_error('WEAK_PASSWORD', 422);

        // กันเปลี่ยนรหัสตัวเอง (ถ้าต้องการเหมือน canManage)
        if ($id === (int)($userId ?? 0)) json_error('SELF_ACTION', 400);

        // กันแก้ boss
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) json_error('NOT_FOUND', 404);
        if (($u['role'] ?? '') === 'boss') json_error('PROTECTED', 400);

        $hash = password_hash($password, PASSWORD_BCRYPT);

        // สำคัญ: ชื่อคอลัมน์ต้องตรงกับ DB คุณ
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
        $stmt->execute(['h' => $hash, 'id' => $id]);

        json_ok(['changed' => true]);
    }


    // ✅ POST /api/users/toggle
    if ($sub === 'toggle') {
        if ($method !== 'POST') json_error('METHOD_NOT_ALLOWED', 405);

        $body = get_json_body(); // (ด้านล่างผมให้แก้ get_json_body ให้รองรับทั้ง JSON + POST)
        $id = (int)($body['id'] ?? 0);
        $to = isset($body['to']) ? (int)$body['to'] : null; // optional 1/0

        if ($id <= 0) json_error('MISSING_ID', 422);

        // กันปิดตัวเอง
        if ($id === (int)($userId ?? 0)) json_error('SELF_ACTION', 400);

        $stmt = $pdo->prepare("SELECT id, role, is_active FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) json_error('NOT_FOUND', 404);

        // กันปิด boss
        if (($u['role'] ?? '') === 'boss') json_error('PROTECTED', 400);

        $newVal = ($to === null) ? ((int)$u['is_active'] ? 0 : 1) : ($to ? 1 : 0);

        $stmt = $pdo->prepare("UPDATE users SET is_active = :v WHERE id = :id");
        $stmt->execute(['v' => $newVal, 'id' => $id]);

        json_ok(['is_active' => $newVal]);
    }

    // ✅ POST /api/users/delete
    if ($sub === 'delete') {
        if ($method !== 'POST') json_error('METHOD_NOT_ALLOWED', 405);

        $body = get_json_body();
        $id = (int)($body['id'] ?? 0);

        if ($id <= 0) json_error('MISSING_ID', 422);

        // กันลบตัวเอง
        if ($id === (int)($userId ?? 0)) json_error('SELF_ACTION', 400);

        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) json_error('NOT_FOUND', 404);

        // กันลบ boss
        if (($u['role'] ?? '') === 'boss') json_error('PROTECTED', 400);

        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            // FK constraint
            if (($e->errorInfo[1] ?? null) == 1451) json_error('FK_CONSTRAINT', 409);
            throw $e;
        }

        json_ok(['deleted' => true]);
    }

    // ✅ /api/users (list/create) ต้องเป็นเส้นหลักเท่านั้น
    if ($sub !== '') json_error('NOT_FOUND', 404);

    if ($method === 'GET') {
        $rows = $userModel->getAll();
        json_ok($rows);
    }

    if ($method === 'POST') {
        $body = get_json_body();
        $username = trim((string)($body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $full_name = trim((string)($body['full_name'] ?? ''));
        $roleNew = trim((string)($body['role'] ?? 'admin'));

        if ($username === '' || $password === '' || $full_name === '') json_error('MISSING_FIELDS', 422);
        if (!in_array($roleNew, ['boss', 'admin'], true)) json_error('INVALID_ROLE', 422);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username,password_hash,full_name,role,is_active) VALUES (:u,:p,:f,:r,1)");
        $stmt->execute(['u' => $username, 'p' => $hash, 'f' => $full_name, 'r' => $roleNew]);

        json_ok(['created' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    json_error('METHOD_NOT_ALLOWED', 405);
}

json_error('NOT_FOUND', 404);
