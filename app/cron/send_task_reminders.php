<?php
// cron/send_task_reminders.php
date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/NotificationService.php';

$noti = new NotificationService($pdo);

// ใกล้ถึงกำหนด: พรุ่งนี้ (1 วันก่อน)
$noti->sendDueSoonReminders(1);

// เกินกำหนด: ส่งวันละครั้ง
$noti->sendOverdueReminders();

echo "OK\n";
