<?php
require_once '../app/db.php';
require_once '../app/auth.php';

require_login();
$me = current_user();

header('Content-Type: application/json; charset=utf-8');

try {
  if (($me['role'] ?? '') !== 'boss') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์ดำเนินการ']);
    exit;
  }

  $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
  $id = (int)($input['id'] ?? 0);
  $to = isset($input['to']) ? (int)$input['to'] : null; // optional: 1/0

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
  }

  // กันปิดตัวเอง
  if ($id === (int)($me['id'] ?? 0)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ไม่สามารถปิดสถานะบัญชีของตัวเองได้']);
    exit;
  }

  // อ่านผู้ใช้เป้าหมาย
  $stmt = $pdo->prepare("SELECT id, role, is_active FROM users WHERE id = :id");
  $stmt->execute(['id' => $id]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'ไม่พบผู้ใช้']);
    exit;
  }

  // กันปิด boss (ถ้าคุณอยากกัน)
  if (($u['role'] ?? '') === 'boss') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ไม่สามารถปิดสถานะบัญชี boss ได้']);
    exit;
  }

  $newVal = ($to === null) ? ((int)$u['is_active'] ? 0 : 1) : ($to ? 1 : 0);

  $stmt = $pdo->prepare("UPDATE users SET is_active = :v WHERE id = :id");
  $stmt->execute(['v' => $newVal, 'id' => $id]);

  echo json_encode(['ok' => true, 'is_active' => $newVal], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'เปลี่ยนสถานะไม่สำเร็จ']);
}
