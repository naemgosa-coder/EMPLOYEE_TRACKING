<?php
// public_html/index.php

require_once dirname(__DIR__) . '/bootstrap/app.php';

// อ่าน route จาก query string ที่ .htaccess ส่งมา เช่น /login, /tasks, /tasks/add
$route = $_GET['route'] ?? '';
$route = trim($route, '/');

// API routes: /api/...
if (str_starts_with($route, 'api')) {
    require BASE_PATH . '/routes/api.php';
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
        require BASE_PATH . '/views/login.php';
        break;

    case 'logout':
        require BASE_PATH . '/views/logout.php';
        break;

    case 'dashboard':
        require BASE_PATH . '/views/dashboard.php';
        break;

    case 'tasks':
        // /tasks           → รายการงาน
        // /tasks/add       → เพิ่มงาน
        // /tasks/edit      → แก้ไขงาน (ใช้ ?id=xx เพิ่มเติม)
        // /tasks/delete    → ลบงาน (ใช้ ?id=xx)
        if ($action === '') {
            require BASE_PATH . '/views/tasks.php';
        } elseif ($action === 'add') {
            require BASE_PATH . '/views/task_add.php';
        } elseif ($action === 'edit') {
            require BASE_PATH . '/views/task_edit.php';
        } elseif ($action === 'delete') {
            require BASE_PATH . '/views/task_delete.php';
        } else {
            http_response_code(404);
            echo "404 Not Found (tasks)";
        }
        break;

    case 'tasks-mine':
        require BASE_PATH . '/views/tasks-mine.php';
        break;
    case 'myjob':
        require BASE_PATH . '/views/myjob.php';
        break;
    case 'reports':
        if (is_boss()) {
            require BASE_PATH . '/views/reports.php';
        } else {
            require BASE_PATH . '/views/reports-mine.php';
        }
        break;

    case 'reports-mine':
        require BASE_PATH . '/views/reports-mine.php';
        break;
    case 'users':
        // /users        → รายการพนักงาน
        // /users/add    → เพิ่มพนักงาน
        if ($action === '' || $action === 'list') {
            require BASE_PATH . '/views/user_list.php';
        } elseif ($action === 'add') {
            require BASE_PATH . '/views/user_add.php';
        } else {
            http_response_code(404);
            echo "404 Not Found (users)";
        }
        break;

    case 'line-link':
        require BASE_PATH . '/views/line-link.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found: " . htmlspecialchars($route);
        break;
}
