<?php

declare(strict_types=1);

// Application config. Values come from .env (see .env.example).
return [
    'base_url' => env('APP_BASE_URL', ''),
    'timezone' => env('APP_TIMEZONE', 'Asia/Bangkok'),

    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME', ''),
        'user' => env('DB_USER', ''),
        'pass' => env('DB_PASS', ''),
    ],

    'mail' => [
        'transport' => env('MAIL_TRANSPORT', 'mail'),
        'from'      => env('MAIL_FROM', ''),
        'from_name' => env('MAIL_FROM_NAME', 'Task System'),
        'host'      => env('SMTP_HOST', ''),
        'port'      => (int) env('SMTP_PORT', '587'),
        'user'      => env('SMTP_USER', ''),
        'pass'      => env('SMTP_PASS', ''),
        'secure'    => env('SMTP_SECURE', 'tls'),
    ],

    'line' => [
        'access_token'   => env('LINE_CHANNEL_ACCESS_TOKEN', ''),
        'channel_secret' => env('LINE_CHANNEL_SECRET', ''),
    ],

    'uploads_path' => dirname(__DIR__) . '/storage/uploads',
];
