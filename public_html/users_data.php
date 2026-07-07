<?php
require_once '../app/db.php';
require_once '../app/auth.php';

require_login();
$me = current_user();

header('Content-Type: application/json; charset=utf-8');

try {
  // ถ้าต้องการให้เฉพาะ boss เข้าถึง
  if (($me['role'] ?? '') !== 'boss') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'ไม่มีสิทธิ์เข้าถึง']);
    exit;
  }

  $stmt = $pdo->query("SELECT id, username, full_name, role, is_active FROM users ORDER BY id DESC");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'message' => 'เกิดข้อผิดพลาดในการโหลดผู้ใช้']);
}
