<?php
$root = dirname(__DIR__);
require_once $root . '/app/config.php';
require_once $root . '/app/db.php';
require_once $root . '/app/LineBotService.php';

header('Content-Type: text/plain; charset=utf-8');

$userId = 10;

$stmt = $pdo->prepare("SELECT line_user_id FROM users WHERE id=:id LIMIT 1");
$stmt->execute(['id' => $userId]);
$lineUserId = (string)($stmt->fetchColumn() ?: '');

if (!$lineUserId) { echo "NO line_user_id\n"; exit; }

$line = new LineBotService(getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '');

try {
  $line->pushText($lineUserId, "✅ TEST: Push ใช้งานได้แล้ว");
  echo "PUSH OK\n";
} catch (Throwable $e) {
  echo "PUSH FAILED: " . $e->getMessage() . "\n";
}