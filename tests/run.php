<?php

declare(strict_types=1);

// Minimal self-check (no DB needed): composer test
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

ini_set('zend.assertions', '1');
ini_set('assert.exception', '1');

// load_env: quotes stripped, '=' inside value kept, real env wins
$tmp = tempnam(sys_get_temp_dir(), 'env');
file_put_contents($tmp, "# comment\nT_A=plain\nT_B=\"two words\"\nT_C=abc=/+x=\nT_D=from_file\n");
putenv('T_D=from_env');
load_env($tmp);
unlink($tmp);
assert(env('T_A') === 'plain');
assert(env('T_B') === 'two words');
assert(env('T_C') === 'abc=/+x=');
assert(env('T_D') === 'from_env');
assert(env('T_MISSING', 'dflt') === 'dflt');

// config(): dot notation + default
assert(is_array(config('db')));
assert(config('mail.port') === (int) env('SMTP_PORT', '587'));
assert(config('nope.nothing', 'x') === 'x');

assert(e('<a href="x">') === '&lt;a href=&quot;x&quot;&gt;');
assert(e(null) === '');
assert(preg_match('/^TSK-\d{6}-000042$/', make_task_code(42)) === 1);

foreach ([
    App\Models\TaskModel::class, App\Models\TaskLogModel::class, App\Models\TaskFileModel::class,
    App\Models\UserModel::class, App\Controllers\TaskController::class, App\Controllers\ReportController::class,
    App\Services\NotificationService::class, App\Services\LineBotService::class, App\Services\Mailer::class,
] as $class) {
    assert(class_exists($class), "autoload failed: $class");
}

echo "OK\n";
