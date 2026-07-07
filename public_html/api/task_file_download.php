<?php
require_once '../../app/db.php';
require_once '../../app/auth.php';
require_once '../../app/helpers.php';

require_login();
$user = current_user();
$uid = (int)($user['id'] ?? 0);
$isBoss = is_boss();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('BAD_REQUEST'); }

$stmt = $pdo->prepare("
  SELECT f.*, t.assigned_by, t.assigned_to
  FROM task_files f
  JOIN tasks t ON t.id = f.task_id
  WHERE f.id = :id
  LIMIT 1
");
$stmt->execute(['id' => $id]);
$f = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$f) { http_response_code(404); exit('NOT_FOUND'); }

// ===== permission =====
if (!$isBoss) {
  $taskId = (int)$f['task_id'];
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
  $chk->execute(['id' => $taskId, 'uid' => $uid]);
  if (!$chk->fetchColumn()) { http_response_code(403); exit('FORBIDDEN'); }
}

// ===== build candidates =====
$publicRoot = realpath(__DIR__ . '/..'); // /public
if (!$publicRoot) { http_response_code(500); exit('SERVER_PATH_ERROR'); }

$rel = (string)($f['path'] ?? ''); // เช่น uploads/task_files/12/abc.pdf
$rel = str_replace('\\', '/', trim($rel));
$rel = preg_replace('#^public/#', '', $rel);
$rel = preg_replace('#^\./+#', '', $rel);
$rel = ltrim($rel, '/');

$storedName = (string)($f['stored_name'] ?? '');
$taskId = (int)($f['task_id'] ?? 0);

$candidates = [];

// 1) ถ้า DB เก็บ path แบบ relative ใต้ public/ เช่น uploads/...
if ($rel !== '') {
  $candidates[] = $publicRoot . '/' . $rel;

  // ถ้า rel เป็นแค่ชื่อไฟล์ (ไม่มี /) ให้ลองประกอบด้วย taskId
  if (strpos($rel, '/') === false && $taskId > 0) {
    $candidates[] = $publicRoot . '/uploads/task_files/' . $taskId . '/' . $rel;
    $candidates[] = $publicRoot . '/uploads/task/' . $taskId . '/' . $rel;
  }
}

// 2) fallback จาก stored_name + taskId
if ($storedName !== '' && $taskId > 0) {
  $candidates[] = $publicRoot . '/uploads/task_files/' . $taskId . '/' . $storedName;
  $candidates[] = $publicRoot . '/uploads/task/' . $taskId . '/' . $storedName;
}

$path = null;
foreach ($candidates as $cand) {
  $rp = realpath($cand);
  if ($rp && is_file($rp)) {
    $path = $rp;
    break;
  }
}
if (!$path) { http_response_code(404); exit('FILE_NOT_FOUND'); }

// ===== security: allow only under /public/uploads (ครอบทั้ง task และ task_files) =====
$baseUploads = realpath($publicRoot . '/uploads');
if (!$baseUploads) { http_response_code(500); exit('UPLOADS_BASE_NOT_FOUND'); }

$norm = function ($p) {
  return rtrim(strtolower(str_replace('\\', '/', $p)), '/');
};
$baseN = $norm($baseUploads);
$pathN = $norm($path);

if (!($pathN === $baseN || str_starts_with($pathN, $baseN . '/'))) {
  http_response_code(403);
  exit('FORBIDDEN_PATH');
}

// ===== output =====
$downloadName = (string)($f['original_name'] ?? '');
if ($downloadName === '') $downloadName = basename($path);
$downloadName = str_replace(["\r", "\n", '"'], '', $downloadName);

$mime = (string)($f['mime_type'] ?? '');
if ($mime === '') $mime = 'application/octet-stream';

// ถ้าต้องการ "เปิดดู" pdf/รูป ในแท็บใหม่ ให้เป็น inline
$disposition = ($mime === 'application/pdf' || str_starts_with($mime, 'image/'))
  ? 'inline'
  : 'attachment';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
// รองรับชื่อไฟล์ภาษาไทยด้วย filename*
header("Content-Disposition: {$disposition}; filename=\"{$downloadName}\"; filename*=UTF-8''" . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
