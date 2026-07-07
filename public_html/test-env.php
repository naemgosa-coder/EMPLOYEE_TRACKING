<?php
$root = dirname(__DIR__);
require_once $root . '/app/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "APP_BASE_URL=" . ($GLOBALS['APP_BASE_URL'] ?? '') . PHP_EOL;
echo "LINE_TOKEN=" . ((getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '') !== '' ? "OK" : "MISSING") . PHP_EOL;
echo "LINE_SECRET=" . ((getenv('LINE_CHANNEL_SECRET') ?: '') !== '' ? "OK" : "MISSING") . PHP_EOL;
