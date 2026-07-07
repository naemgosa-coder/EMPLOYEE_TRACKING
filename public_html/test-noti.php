<?php
$root = dirname(__DIR__);
require_once $root . '/app/config.php';
require_once $root . '/app/db.php';
require_once $root . '/app/NotificationService.php';

header('Content-Type: text/plain; charset=utf-8');

$taskId = 6; // เปลี่ยนเป็นงานจริงที่มี assignee เป็น user ที่ผูก line แล้ว

$ns = new NotificationService($pdo);
$ns->sendTaskAssigned($taskId);

echo "DONE\n";