<?php
// app/config.php
$DB_HOST = 'localhost';
$DB_NAME = 'marthag_taskflow_db';
$DB_USER = 'marthag_taskflow_db';
$DB_PASS = 'egKLyrM8sGJnpSy9pXTm';

// $BASE_URL = 'http://localhost/EMPLOYEE_TRACKING/public';

// === Mail Config ===
$APP_BASE_URL = 'http://tracking.marthagroupsales.com';

$MAIL_TRANSPORT = 'smtp';

$MAIL_FROM = 'marthagroup0907@gmail.com';
$MAIL_FROM_NAME = 'Task System';

$SMTP_HOST = 'smtp.gmail.com';
$SMTP_PORT = 587;
$SMTP_USER = 'marthagroup0907@gmail.com';
$SMTP_PASS = 'viwp hxsq rdjk nrbq'; // App Password 16 ตัว
$SMTP_SECURE = 'tls'; // STARTTLS

// === LINE Bot Config ===
putenv('LINE_CHANNEL_ACCESS_TOKEN=7lXpGe4pAvwzF4DTAyt1sHg8oZL2uexO5rZu42BlBWwz0iGjt1ik7/VqhaBgtcDZLLVyzWwhCt7PLiX9tsB7PqllTuVV2YvndDlmsvn+PY9aLlpjR5q8rX2FnPsY1JTcn2IhdbLydI75N0hngrSvrwdB04t89/1O/w1cDnyilFU=');
putenv('LINE_CHANNEL_SECRET=1fcc09f8e72a82258b982e34e8be88cf');
putenv('APP_BASE_URL=' . $APP_BASE_URL);

// ทำให้ getenv/$_ENV ใช้ได้สม่ำเสมอ
$_ENV['LINE_CHANNEL_ACCESS_TOKEN'] = getenv('LINE_CHANNEL_ACCESS_TOKEN');
$_ENV['LINE_CHANNEL_SECRET'] = getenv('LINE_CHANNEL_SECRET');
$_ENV['APP_BASE_URL'] = getenv('APP_BASE_URL');

// ถ้าคุณใช้ตัวแปร global แบบเดิม
$GLOBALS['APP_BASE_URL'] = $_ENV['APP_BASE_URL'];