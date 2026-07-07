<?php
// public/index.php
require_once '../app/db.php';
require_once '../app/auth.php';
require_once '../app/helpers.php';

// อ่าน route จาก query string ที่ .htaccess ส่งมา เช่น /login, /tasks, /tasks/add
$route = $_GET['route'] ?? '';
$route = trim($route, '/');

// API routes: /api/...
if (str_starts_with($route, 'api')) {
    require 'api.php';
    exit;
}


// ถ้า route ว่าง → ถ้า login แล้วไป dashboard, ถ้ายังให้ไป login
if ($route === '') {
    if (isset($_SESSION['user'])) {
        $route = 'dashboard';
    } else {
        $route = 'login';
    }
}

// แตก path เป็นส่วน ๆ เช่น 'tasks/add' → ['tasks', 'add']
$parts = explode('/', $route);
$controller = $parts[0] ?? '';
$action     = $parts[1] ?? '';

// simple router
switch ($controller) {

    case 'login':
        // GET /login , POST /login
        require 'login.php';
        break;

    case 'logout':
        require 'logout.php';
        break;

    case 'dashboard':
        require 'dashboard.php';
        break;

    case 'tasks':
        // /tasks           → รายการงาน
        // /tasks/add       → เพิ่มงาน
        // /tasks/edit      → แก้ไขงาน (ใช้ ?id=xx เพิ่มเติม)
        // /tasks/delete    → ลบงาน (ใช้ ?id=xx)
        if ($action === '') {
            require 'tasks.php';
        } elseif ($action === 'add') {
            require 'task_add.php';
        } elseif ($action === 'edit') {
            require 'task_edit.php';
        } elseif ($action === 'delete') {
            require 'task_delete.php';
        } else {
            http_response_code(404);
            echo "404 Not Found (tasks)";
        }
        break;

    case 'tasks-mine':
        require 'tasks-mine.php';
        break;
    case 'myjob':
        require 'myjob.php';
        break;
    case 'reports':
        if (is_boss()) {
            require 'reports.php';
        } else {
            require 'reports-mine.php';
        }
        break;

    case 'reports-mine':
        require 'reports-mine.php';
        break;
    case 'users':
        // /users        → รายการพนักงาน
        // /users/add    → เพิ่มพนักงาน
        if ($action === '' || $action === 'list') {
            require 'user_list.php';
        } elseif ($action === 'add') {
            require 'user_add.php';
        } else {
            http_response_code(404);
            echo "404 Not Found (users)";
        }
        break;

    case 'line-link':
        require 'line-link.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found: " . htmlspecialchars($route);
        break;
}
