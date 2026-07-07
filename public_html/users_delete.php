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

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
    exit;
  }

  // กันลบตัวเอง
  if ($id === (int)($me['id'] ?? 0)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ไม่สามารถลบบัญชีของตัวเองได้']);
    exit;
  }

  // อ่านผู้ใช้เป้าหมาย
  $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = :id");
  $stmt->execute(['id' => $id]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'ไม่พบผู้ใช้']);
    exit;
  }

  // กันลบ boss
  if (($u['role'] ?? '') === 'boss') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ไม่สามารถลบบัญชี boss ได้']);
    exit;
  }

  // ลบจริง (อาจติด FK ถ้ามีตารางอ้างอิง)
  $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
  $stmt->execute(['id' => $id]);

  echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
  // FK constraint
  if (($e->errorInfo[1] ?? null) == 1451) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => 'ลบไม่ได้: มีข้อมูลอื่นอ้างอิงผู้ใช้นี้ (แนะนำให้ “ปิดสถานะ” แทน)'], JSON_UNESCAPED_UNICODE);
    exit;
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'ลบบัญชีไม่สำเร็จ']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'ลบบัญชีไม่สำเร็จ']);
}
