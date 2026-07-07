<?php
// public/api/line/create-link-code.php
date_default_timezone_set('Asia/Bangkok');

$root = dirname(__DIR__, 3);
require_once $root . '/app/config.php';
require_once $root . '/app/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED']);
  exit;
}

// สุ่มโค้ด 6 ตัว (อ่านง่าย ไม่สับสน I,O,1,0)
function genCode6(): string {
  $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  $out = '';
  for ($i = 0; $i < 6; $i++) {
    $out .= $chars[random_int(0, strlen($chars) - 1)];
  }
  return $out;
}

try {
  // ปิดโค้ดเก่าที่ยัง active (ให้ 1 คนมี code ที่ใช้ได้แค่ 1 อัน)
  $pdo->prepare("
    UPDATE user_line_link_codes
    SET used_at = NOW()
    WHERE user_id = :uid
      AND used_at IS NULL
      AND expires_at > NOW()
  ")->execute(['uid' => $userId]);

  $expiresAt = (new DateTimeImmutable())->modify('+10 minutes')->format('Y-m-d H:i:s');

  // กันชน code ซ้ำ
  $code = '';
  for ($try = 0; $try < 20; $try++) {
    $code = genCode6();
    try {
      $stmt = $pdo->prepare("
        INSERT INTO user_line_link_codes (user_id, code, expires_at)
        VALUES (:uid, :c, :exp)
      ");
      $stmt->execute(['uid' => $userId, 'c' => $code, 'exp' => $expiresAt]);
      break;
    } catch (Throwable $e) {
      $code = '';
    }
  }

  if ($code === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CANNOT_GENERATE_CODE']);
    exit;
  }

  echo json_encode([
    'ok' => true,
    'code' => $code,
    'expires_at' => $expiresAt,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}