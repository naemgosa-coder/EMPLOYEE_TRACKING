<?php

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

require_login();
$user = current_user();
$uid = (int)($user['id'] ?? 0);
$isBoss = is_boss();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'BAD_REQUEST']); exit; }

// task
$stmt = $pdo->prepare("
  SELECT
    t.*,
    ub.full_name AS assigned_by_name,
    um.full_name AS assigned_to_name
  FROM tasks t
  LEFT JOIN users ub ON ub.id = t.assigned_by
  LEFT JOIN users um ON um.id = t.assigned_to
  WHERE t.id = :id
  LIMIT 1
");
$stmt->execute(['id'=>$id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'NOT_FOUND']); exit; }

// permission: boss หรือเกี่ยวข้องกับงาน
if (!$isBoss) {
  $chk = $pdo->prepare("
    SELECT 1
    FROM tasks t
    WHERE t.id = :id
      AND (
        t.assigned_by = :uid OR
        t.assigned_to = :uid OR
        EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = :uid)
      )
    LIMIT 1
  ");
  $chk->execute(['id'=>$id,'uid'=>$uid]);
  if (!$chk->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'FORBIDDEN']);
    exit;
  }
}

// assignees
$assStmt = $pdo->prepare("
  SELECT u.id, u.username, u.full_name, u.role, ta.created_at
  FROM task_assignees ta
  JOIN users u ON u.id = ta.user_id
  WHERE ta.task_id = :id
  ORDER BY ta.created_at ASC
");
$assStmt->execute(['id'=>$id]);
$assignees = $assStmt->fetchAll(PDO::FETCH_ASSOC);

// files
$fileStmt = $pdo->prepare("
  SELECT id, original_name, file_size, created_at
  FROM task_files
  WHERE task_id = :id
  ORDER BY created_at DESC, id DESC
");
$fileStmt->execute(['id'=>$id]);
$files = $fileStmt->fetchAll(PDO::FETCH_ASSOC);

// logs
$logStmt = $pdo->prepare("
  SELECT l.*, u.full_name AS user_name
  FROM task_status_logs l
  JOIN users u ON u.id = l.changed_by
  WHERE l.task_id = :id
  ORDER BY l.created_at DESC, l.id DESC
");
$logStmt->execute(['id'=>$id]);
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'ok' => true,
  'data' => [
    'task' => $task,
    'assignees' => $assignees,
    'files' => $files,
    'logs' => $logs,
  ]
], JSON_UNESCAPED_UNICODE);
