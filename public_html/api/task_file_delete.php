<?php
// public_html/api/task_file_delete.php

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$user = current_user();
$uid = (int)($user['id'] ?? 0);
$isBoss = is_boss();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'BAD_REQUEST']); exit; }

// หาไฟล์ + task
$stmt = $pdo->prepare("
  SELECT f.*, t.id AS task_id, t.assigned_by
  FROM task_files f
  JOIN tasks t ON t.id = f.task_id
  WHERE f.id = :id
  LIMIT 1
");
$stmt->execute(['id' => $id]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$f) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'NOT_FOUND']); exit; }

if (!$isBoss && (int)($f['assigned_by'] ?? 0) !== $uid) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'error'=>'FORBIDDEN']);
  exit;
}

$taskId = (int)($f['task_id'] ?? 0);

// หา path ไฟล์ (รองรับหลายคอลัมน์ เผื่อ schema ไม่เหมือนกัน)
// TaskFileModel เก็บ path = uploads/task_files/{taskId}/{stored_name} (relative to storage/)
$storedPath = trim((string)($f['path'] ?? ($f['stored_path'] ?? '')));
$storedName = trim((string)($f['stored_name'] ?? ($f['stored_filename'] ?? '')));

$rel = '';
if ($storedPath !== '') $rel = $storedPath;
else if ($storedName !== '') $rel = "uploads/task_files/{$taskId}/{$storedName}";

// ลบไฟล์จาก disk (ถ้าเจอ และอยู่ใต้ storage/uploads เท่านั้น)
if ($rel !== '') {
  $uploadsRoot = realpath(BASE_PATH . '/storage/uploads');
  $abs = realpath(BASE_PATH . '/storage/' . ltrim(str_replace('\\', '/', $rel), '/'));
  if ($uploadsRoot && $abs && str_starts_with($abs, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($abs)) {
    @unlink($abs);
  }
}

// ลบ record
$del = $pdo->prepare("DELETE FROM task_files WHERE id = :id LIMIT 1");
$del->execute(['id' => $id]);

echo json_encode(['ok'=>true, 'data'=>['deleted'=>true]], JSON_UNESCAPED_UNICODE);
