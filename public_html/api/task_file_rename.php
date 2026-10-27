<?php
// public_html/api/task_file_rename.php

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

header('Content-Type: application/json; charset=utf-8');

require_login();
$user = current_user();
$uid = (int)($user['id'] ?? 0);
$isBoss = is_boss();

$raw = file_get_contents('php://input');
$body = $raw ? json_decode($raw, true) : null;
if (!is_array($body)) $body = $_POST;

$id = (int)($body['id'] ?? 0);
$name = trim((string)($body['original_name'] ?? ''));

if ($id <= 0 || $name === '') {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'BAD_REQUEST']);
  exit;
}

// โหลดไฟล์ + task
$stmt = $pdo->prepare("
  SELECT f.id, f.original_name, f.task_id, t.assigned_by
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

// update
$upd = $pdo->prepare("UPDATE task_files SET original_name = :n WHERE id = :id LIMIT 1");
$upd->execute(['n' => $name, 'id' => $id]);

echo json_encode(['ok'=>true, 'data'=>['renamed'=>true]], JSON_UNESCAPED_UNICODE);
