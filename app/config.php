<?php
// app/config.php
$DB_HOST = 'localhost';
$DB_NAME = '';
$DB_USER = '';
$DB_PASS = '';

// === Mail Config ===
$APP_BASE_URL = '';

$MAIL_TRANSPORT = 'smtp';

$MAIL_FROM = '';
$MAIL_FROM_NAME = 'Task System';

$SMTP_HOST = 'smtp.gmail.com';
$SMTP_PORT = 587;
$SMTP_USER = '';
$SMTP_PASS = ''; // App Password 16 ตัว
$SMTP_SECURE = 'tls'; // STARTTLS

// === LINE Bot Config ===
putenv('LINE_CHANNEL_ACCESS_TOKEN=');
putenv('LINE_CHANNEL_SECRET=');
putenv('APP_BASE_URL=' . $APP_BASE_URL);

$_ENV['LINE_CHANNEL_ACCESS_TOKEN'] = getenv('LINE_CHANNEL_ACCESS_TOKEN');
$_ENV['LINE_CHANNEL_SECRET'] = getenv('LINE_CHANNEL_SECRET');
$_ENV['APP_BASE_URL'] = getenv('APP_BASE_URL');

$GLOBALS['APP_BASE_URL'] = $_ENV['APP_BASE_URL'];