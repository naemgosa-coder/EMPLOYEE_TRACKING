<?php

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

require_login_api();
$user = current_user();
$uid = (int)($user['id'] ?? 0);
$isBoss = is_boss();

header('Content-Type: application/json; charset=utf-8');

$taskId = (int)($_GET['task_id'] ?? 0);
if ($taskId <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'BAD_REQUEST'], JSON_UNESCAPED_UNICODE);
  exit;
}

// permission: boss ดูได้หมด / staff ดูได้ถ้าเป็นผู้รับมอบหมายหรือผู้มอบหมาย
if (!$isBoss) {
  $chk = $pdo->prepare("
    SELECT 1
    FROM tasks t
    WHERE t.id = :id
      AND (
        t.assigned_by = :uid
        OR t.assigned_to = :uid
        OR EXISTS (
          SELECT 1 FROM task_assignees ta
          WHERE ta.task_id = t.id AND ta.user_id = :uid
        )
      )
    LIMIT 1
  ");
  $chk->execute(['id'=>$taskId,'uid'=>$uid]);
  if (!$chk->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'FORBIDDEN'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

$stmt = $pdo->prepare("
  SELECT id, original_name, file_size, mime_type, created_at
  FROM task_files
  WHERE task_id = :id
  ORDER BY created_at DESC, id DESC
");
$stmt->execute(['id'=>$taskId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
