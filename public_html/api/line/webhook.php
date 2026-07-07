<?php
// public/api/line/webhook.php

date_default_timezone_set('Asia/Bangkok');

$root = dirname(__DIR__, 3); // public/api/line -> project root
require_once $root . '/app/config.php';
require_once $root . '/app/db.php';               // ต้องมี $pdo
require_once $root . '/app/LineBotService.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK (GET)\n";
    exit;
}

$channelSecret = getenv('LINE_CHANNEL_SECRET') ?: '';
$token         = getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '';

if ($channelSecret === '' || $token === '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing LINE config\n";
    exit;
}

$body = file_get_contents('php://input');

$signature =
    $_SERVER['HTTP_X_LINE_SIGNATURE']
    ?? $_SERVER['REDIRECT_HTTP_X_LINE_SIGNATURE']
    ?? '';

// ถ้ายิงเองไม่มี signature ก็ยังตอบ 200 (กัน healthcheck/ทดสอบ)
if ($signature === '') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK (no signature)\n";
    exit;
}

// Verify signature: base64(HMAC-SHA256(body, channelSecret))
$hash = base64_encode(hash_hmac('sha256', $body, $channelSecret, true));
if (!hash_equals($hash, $signature)) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid signature\n";
    exit;
}

$payload = json_decode($body, true);
$events = $payload['events'] ?? [];

$line = new LineBotService($token);

$normalize = function (string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/', ' ', $s);
    return $s ?? '';
};

// ✅ โค้ด 6 ตัวเท่านั้น (ตัวอักษร/ตัวเลข)
$isCode6 = function (string $text): bool {
    return (bool)preg_match('/^[A-Za-z0-9]{6}$/', $text);
};

// ✅ ปิดบางส่วนอีเมลเพื่อความเป็นส่วนตัว
$maskEmail = function (?string $email): string {
    $email = trim((string)$email);
    if ($email === '' || strpos($email, '@') === false) return '';
    [$name, $domain] = explode('@', $email, 2);
    $n = strlen($name);
    if ($n <= 2) $masked = str_repeat('*', $n);
    else $masked = substr($name, 0, 1) . str_repeat('*', max(1, $n - 2)) . substr($name, -1);
    return $masked . '@' . $domain;
};

$replyHelp = function (string $replyToken) use ($line) {
    $msg =
        "วิธีผูก LINE เพื่อรับแจ้งเตือนงาน\n"
        . "1) ไปที่ระบบ → เมนูผูก LINE → รับโค้ด 6 ตัว\n"
        . "2) ส่งโค้ด 6 ตัวมาที่แชทนี้ เช่น ABC123\n"
        . "หรือพิมพ์: link ABC123\n\n"
        . "คำสั่ง:\n"
        . "• status = ดูสถานะการผูก\n"
        . "• unlink = ยกเลิกการผูก";
    $line->replyText($replyToken, $msg);
};

foreach ($events as $ev) {
    if (($ev['type'] ?? '') !== 'message') continue;
    if (($ev['message']['type'] ?? '') !== 'text') continue;

    $replyToken = (string)($ev['replyToken'] ?? '');
    if ($replyToken === '') continue;

    $lineUserId = $ev['source']['userId'] ?? null;
    if (!$lineUserId) continue;

    $sourceType = (string)($ev['source']['type'] ?? 'user');
    if ($sourceType !== 'user') continue; // ✅ ตอบเฉพาะแชทส่วนตัว

    $textRaw = (string)($ev['message']['text'] ?? '');
    $textTrim = $normalize($textRaw);
    $textLower = strtolower($textTrim);

    // ===== help =====
    if (in_array($textLower, ['help', 'ช่วยเหลือ', 'วิธีใช้', 'คู่มือ', '?'], true)) {
        try { $replyHelp($replyToken); } catch (Throwable $e) {}
        continue;
    }

    // ===== status =====
    if (in_array($textLower, ['status', 'สถานะ', 'เช็คสถานะ'], true)) {
        try {
            $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE line_user_id = :lid LIMIT 1");
            $stmt->execute(['lid' => $lineUserId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($u) {
                $name = trim((string)($u['full_name'] ?? '')) ?: 'ไม่ระบุชื่อ';
                $masked = $maskEmail($u['email'] ?? '');
                $extra = $masked !== '' ? "\nอีเมล: {$masked}" : "";
                $line->replyText($replyToken, "✅ ผูกแล้ว\nผู้ใช้: {$name}{$extra}");
            } else {
                $line->replyText($replyToken, "ℹ️ ยังไม่ได้ผูกบัญชี\nส่งโค้ด 6 ตัวจากระบบมาเพื่อผูก (เช่น ABC123)");
            }
        } catch (Throwable $e) {
            try { $line->replyText($replyToken, "เกิดข้อผิดพลาด กรุณาลองใหม่"); } catch (Throwable $x) {}
        }
        continue;
    }

    // ===== unlink =====
    if (in_array($textLower, ['unlink', 'ยกเลิก', 'ยกเลิกผูก', 'ลบการผูก'], true)) {
        try {
            $pdo->prepare("UPDATE users SET line_user_id = NULL WHERE line_user_id = :lid")
                ->execute(['lid' => $lineUserId]);
            $line->replyText($replyToken, "✅ ยกเลิกการผูกเรียบร้อยแล้ว");
        } catch (Throwable $e) {
            try { $line->replyText($replyToken, "เกิดข้อผิดพลาด กรุณาลองใหม่"); } catch (Throwable $x) {}
        }
        continue;
    }

    // ===== รับโค้ด 6 ตัวแบบ: "link ABC123" หรือ "ABC123" =====
    $code = null;

    if (preg_match('/^link\s+([A-Za-z0-9]{6})$/i', $textTrim, $m)) {
        $code = strtoupper($m[1]);
    } else {
        $t = strtoupper($textTrim);
        if ($isCode6($t)) $code = $t;
    }

    if ($code !== null) {
        try {
            // หาโค้ดที่ยังไม่หมดอายุและยังไม่ถูกใช้
            $stmt = $pdo->prepare("
                SELECT id, user_id
                FROM user_line_link_codes
                WHERE code = :c
                  AND used_at IS NULL
                  AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute(['c' => $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $line->replyText($replyToken, "❌ โค้ดไม่ถูกต้องหรือหมดอายุ\nพิมพ์ help เพื่อดูวิธีใช้งาน");
                continue;
            }

            $userId = (int)$row['user_id'];

            // กัน LINE userId ไปผูกกับหลาย user (เคลียร์คนอื่นก่อน)
            $pdo->prepare("UPDATE users SET line_user_id = NULL WHERE line_user_id = :lid AND id <> :uid")
                ->execute(['lid' => $lineUserId, 'uid' => $userId]);

            // ผูก LINE userId กับ user
            $pdo->prepare("UPDATE users SET line_user_id = :lid WHERE id = :uid")
                ->execute(['lid' => $lineUserId, 'uid' => $userId]);

            // mark code used
            $pdo->prepare("UPDATE user_line_link_codes SET used_at = NOW() WHERE id = :id")
                ->execute(['id' => (int)$row['id']]);

            // ดึงชื่อ/อีเมลผู้ใช้ เพื่อยืนยันว่าผูกกับบัญชีไหน
            $stmtU = $pdo->prepare("SELECT full_name, email FROM users WHERE id=:id LIMIT 1");
            $stmtU->execute(['id' => $userId]);
            $uinfo = $stmtU->fetch(PDO::FETCH_ASSOC) ?: [];

            $fullName = trim((string)($uinfo['full_name'] ?? ''));
            $email    = trim((string)($uinfo['email'] ?? ''));

            $display = $fullName !== '' ? $fullName : ("User ID: " . $userId);
            $masked  = $maskEmail($email);
            $extra   = $masked !== '' ? "\nอีเมล: {$masked}" : "";

            $line->replyText(
                $replyToken,
                "✅ ผูก LINE สำเร็จแล้ว\nผูกกับ: {$display}{$extra}\nต่อไปคุณจะได้รับแจ้งเตือนงานผ่าน LINE"
            );
        } catch (Throwable $e) {
            try { $line->replyText($replyToken, "เกิดข้อผิดพลาด กรุณาลองใหม่"); } catch (Throwable $x) {}
        }
        continue;
    }

   // fallback: ✅ ไม่ตอบอะไร (เงียบ)
    continue;
}

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo "OK\n";