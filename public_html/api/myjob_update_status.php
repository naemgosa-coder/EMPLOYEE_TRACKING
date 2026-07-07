<?php
// public/api/myjob_update_status.php
require_once '../../app/db.php';
require_once '../../app/auth.php';
require_once '../../app/helpers.php';
require_once '../../app/models/TaskLogModel.php';
require_once '../../app/NotificationService.php';
require_once '../../app/models/TaskFileModel.php';

require_login_api();
$user = current_user();
$uid = (int)($user['id'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = [];

$taskId = (int)($_POST['task_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? ''));
$note   = trim((string)($_POST['note'] ?? ''));

// fallback ถ้ายังมี client เก่าส่ง JSON
if ($taskId <= 0 && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $payload = json_decode($raw, true);
  if (!is_array($payload)) $payload = [];
  $taskId = (int)($payload['task_id'] ?? 0);
  $status = trim((string)($payload['status'] ?? ''));
  $note   = trim((string)($payload['note'] ?? ''));
}

// ห้าม overdue (ระบบคำนวณเองจาก due_date)
$allowed = ['pending', 'in_progress', 'completed'];

if ($taskId <= 0 || !in_array($status, $allowed, true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'BAD_REQUEST'], JSON_UNESCAPED_UNICODE);
  exit;
}

// บังคับ note เมื่อ completed
if ($status === 'completed' && $note === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'NOTE_REQUIRED_FOR_COMPLETED'], JSON_UNESCAPED_UNICODE);
  exit;
}

// ตรวจสิทธิ์: ต้องเป็นคนที่อยู่ใน task_assignees หรือ assigned_to
$stmt = $pdo->prepare("
  SELECT t.*
  FROM tasks t
  WHERE t.id = :id
    AND (
      t.assigned_to = :uid
      OR EXISTS (
        SELECT 1 FROM task_assignees ta
        WHERE ta.task_id = t.id AND ta.user_id = :uid
      )
    )
  LIMIT 1
");
$stmt->execute(['id' => $taskId, 'uid' => $uid]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'FORBIDDEN'], JSON_UNESCAPED_UNICODE);
  exit;
}

$allowedOrder = ['pending' => 0, 'in_progress' => 1, 'completed' => 2];

$oldStatus = (string)($task['status'] ?? 'pending');
$oldKey = $allowedOrder[$oldStatus] ?? 0;
$newKey = $allowedOrder[$status] ?? -1;

if ($newKey < $oldKey) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'STATUS_CANNOT_ROLLBACK'], JSON_UNESCAPED_UNICODE);
  exit;
}

$oldStatus = $task['status'] ?? null;
if ($oldStatus === $status) {
  echo json_encode(['ok' => true, 'changed' => false], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo->beginTransaction();

  $upd = $pdo->prepare("UPDATE tasks SET status = :st, updated_at = NOW() WHERE id = :id");
  $upd->execute(['st' => $status, 'id' => $taskId]);

  $logModel = new TaskLogModel($pdo);

  $logNote = $note !== ''
    ? $note
    : 'อัปเดตสถานะจากหน้า งานของฉัน';

  $logIdRaw = $logModel->addLog($taskId, $uid, $oldStatus, $status, $logNote);
  $logId = is_numeric($logIdRaw) ? (int)$logIdRaw : (int)$pdo->lastInsertId();

  $savedFiles = [];
  try {
    if (!empty($_FILES['files'])) {
      $filesModel = new TaskFileModel($pdo);
      $savedFiles = $filesModel->saveUploads($taskId, $logId, $uid, $_FILES['files']);
    }
  } catch (Throwable $e) {
    // ถ้าอยากให้ fail ทั้ง request เมื่ออัปโหลดพัง -> throw $e;
    // ตอนนี้เลือกไม่ให้พังทั้งการเปลี่ยนสถานะ
  }

  $pdo->commit();

  // ส่งอีเมลแจ้งเตือนสถานะ (หลัง commit)
  try {
    if ($logId > 0) {
      $noti = new NotificationService($pdo);
      $noti->sendTaskStatusChangedByLogId($logId);
    }
  } catch (Throwable $e) {
    error_log('[NOTI][MYJOB_STATUS] ' . $e->getMessage());
  }

  echo json_encode([
    'ok' => true,
    'changed' => true,
    'files' => array_map(fn($x) => ['id' => $x['id'], 'name' => $x['name'], 'path' => $x['path']], $savedFiles),
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
