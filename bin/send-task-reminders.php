<?php
// bin/send-task-reminders.php

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Services\NotificationService;

$noti = new NotificationService($pdo);

// ใกล้ถึงกำหนด: พรุ่งนี้ (1 วันก่อน)
$noti->sendDueSoonReminders(1);

// เกินกำหนด: ส่งวันละครั้ง
$noti->sendOverdueReminders();

echo "OK\n";
