<?php
// public_html/api/line/unlink.php

require_once dirname(__DIR__, 3) . '/bootstrap/app.php';

use App\Services\LineBotService;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED']);
  exit;
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED']);
  exit;
}

try {
  // 1) อ่านข้อมูลล่าสุดจาก DB (เอา line_user_id ไว้ส่งแจ้งเตือนก่อนลบ)
  $stmt = $pdo->prepare("SELECT full_name, line_user_id FROM users WHERE id=:id LIMIT 1");
  $stmt->execute(['id' => $userId]);
  $me = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

  $fullName   = trim((string)($me['full_name'] ?? ''));
  $lineUserId = trim((string)($me['line_user_id'] ?? ''));

  $notified = false;
  $warn = null;

  // 2) ถ้ามี line_user_id -> ส่งแจ้งเตือนใน LINE ก่อน
  if ($lineUserId !== '') {
    $token = getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '';
    if ($token !== '') {
      try {
        $line = new LineBotService($token);

        $name = $fullName !== '' ? $fullName : 'ผู้ใช้งาน';
        $msg =
          "🔕 ยกเลิกการผูก LINE แล้ว\n"
          . "บัญชี: {$name}\n\n"
          . "คุณจะไม่ได้รับแจ้งเตือนงานผ่าน LINE อีกต่อไป\n"
          . "หากต้องการผูกใหม่ ให้เข้าเมนู “ผูก LINE แจ้งเตือน” ในระบบ แล้วส่งโค้ด 6 ตัวให้บอท";

        // ต้องเป็น push เพราะไม่ได้มาจาก event ที่มี replyToken
        $line->pushText($lineUserId, $msg);

        $notified = true;
      } catch (Throwable $e) {
        // ส่งไม่ผ่านก็ยังยกเลิกในระบบต่อ (ไม่ค้าง)
        $warn = 'LINE_PUSH_FAILED';
      }
    } else {
      $warn = 'MISSING_LINE_TOKEN';
    }
  }

  // 3) ล้างการผูกใน DB
  $pdo->prepare("UPDATE users SET line_user_id = NULL WHERE id = :id")
      ->execute(['id' => $userId]);

  // 4) ปิดโค้ดที่ยัง active เผื่อมีค้าง
  $pdo->prepare("
    UPDATE user_line_link_codes
    SET used_at = NOW()
    WHERE user_id = :uid
      AND used_at IS NULL
      AND expires_at > NOW()
  ")->execute(['uid' => $userId]);

  // 5) sync session ให้ header badge เปลี่ยนทันที
  $_SESSION['user']['line_user_id'] = null;

  echo json_encode([
    'ok' => true,
    'notified' => $notified,
    'warn' => $warn,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'SERVER_ERROR']);
}