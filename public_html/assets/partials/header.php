<?php
// public/partials/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? null;

$pageTitle = $pageTitle ?? 'TaskFlow';
$pageCss   = $pageCss ?? [];
$hideNav   = $hideNav ?? false;

// สำหรับ layout: กำหนด container ได้ต่อหน้า (เช่น dashboard อยากกว้างใช้ container-xxl)
$containerClass = $containerClass ?? 'container';

// route จาก router
$route = $_GET['route'] ?? '';
$route = trim($route, '/');

$isBoss  = ($user && (($user['role'] ?? '') === 'boss'));
$isAdmin = ($user && (($user['role'] ?? '') === 'admin'));

// helper แบบไม่ต้องประกาศ function (กันชนชื่อซ้ำ)
$isActive = function (string $needle) use ($route): bool {
    if ($route === $needle) return true;
    return str_starts_with($route, $needle . '/');
};

$baseHref = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';

// initials สำหรับ avatar
$fullName = $user['full_name'] ?? ($user['name'] ?? '');
$initials = trim($fullName) !== '' ? mb_substr(trim($fullName), 0, 1, 'UTF-8') : 'U';
?>
<!doctype html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | TaskFlow</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <base href="<?= e($baseHref) ?>">
    <meta name="api-base" content="api">

    <!-- Bootstrap (ก่อน) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <!-- App theme (หลัง) -->
    <link rel="stylesheet" href="assets/css/app.css">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <?php foreach ($pageCss as $css): ?>
        <link rel="stylesheet" href="<?= e($css) ?>">
    <?php endforeach; ?>
</head>

<body>

<?php if (!$hideNav): ?>
    <nav class="navbar navbar-expand-lg app-navbar">
        <div class="container-fluid px-3 px-lg-4">

            <a class="navbar-brand d-flex align-items-center gap-2"
               href="<?= ($user && ($user['role'] ?? '') === 'admin') ? 'myjob' : 'dashboard' ?>">
                <img src="assets/img/logo.png" alt="Logo" class="app-brand__logo">
                <div class="d-none d-sm-block">
                    <div class="app-brand__name">TaskFlow</div>
                    <div class="app-brand__sub">จัดการงาน • ติดตามสถานะ</div>
                </div>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#mainNavbar" aria-controls="mainNavbar"
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <?php if ($user): ?>
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 app-nav">
                        <?php if ($isBoss): ?>
                            <li class="nav-item">
                                <a href="dashboard" class="nav-link <?= $isActive('dashboard') ? 'active' : '' ?>">
                                    <i class="bi bi-speedometer2 me-1"></i>แดชบอร์ด
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="tasks" class="nav-link <?= $isActive('tasks') ? 'active' : '' ?>">
                                    <i class="bi bi-list-check me-1"></i>จัดการงาน
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="myjob" class="nav-link <?= $isActive('myjob') ? 'active' : '' ?>">
                                    <i class="bi bi-briefcase me-1"></i>งานของฉัน
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="users" class="nav-link <?= $isActive('users') ? 'active' : '' ?>">
                                    <i class="bi bi-people me-1"></i>พนักงาน
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="reports" class="nav-link <?= $isActive('reports') ? 'active' : '' ?>">
                                    <i class="bi bi-graph-up-arrow me-1"></i>รายงาน
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a href="dashboard" class="nav-link <?= $isActive('dashboard') ? 'active' : '' ?>">
                                    <i class="bi bi-speedometer2 me-1"></i>แดชบอร์ด
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="tasks" class="nav-link <?= $isActive('tasks') ? 'active' : '' ?>">
                                    <i class="bi bi-list-check me-1"></i>จัดการงาน
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="myjob" class="nav-link <?= $isActive('myjob') ? 'active' : '' ?>">
                                    <i class="bi bi-briefcase me-1"></i>งานของฉัน
                                </a>
                            </li>

                            <li class="nav-item">
                                <a href="reports" class="nav-link <?= $isActive('reports') ? 'active' : '' ?>">
                                    <i class="bi bi-graph-up-arrow me-1"></i>รายงาน
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <div class="d-flex align-items-center gap-2">
                        <div class="dropdown">
                            <button class="btn btn-light app-userbtn dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown"
                                    data-bs-boundary="viewport"
                                    aria-expanded="false">
                                <span class="app-avatar me-2"><?= htmlspecialchars($initials) ?></span>
                                <span class="d-none d-md-inline">
                                    <?= htmlspecialchars($fullName ?: 'ผู้ใช้งาน') ?>
                                </span>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end shadow-sm app-dropdown">
                                <li class="px-3 py-2">
                                    <div class="small text-muted">เข้าสู่ระบบเป็น</div>
                                    <div class="fw-semibold"><?= htmlspecialchars($fullName ?: '-') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($user['role'] ?? '-') ?></div>
                                </li>

                                <li><hr class="dropdown-divider"></li>

                                <li>
                                    <a class="dropdown-item" href="dashboard">
                                        <i class="bi bi-house me-2"></i>หน้าแรก
                                    </a>
                                </li>

                                <!-- ✅ เพิ่มเมนูผูก LINE -->
                                <li>
                                    <a class="dropdown-item d-flex align-items-center justify-content-between" href="line-link">
                                        <span>
                                            <i class="bi bi-link-45deg me-2"></i>ผูก LINE แจ้งเตือน
                                        </span>
                                    </a>
                                </li>

                                <li><hr class="dropdown-divider"></li>

                                <li>
                                    <a class="dropdown-item text-danger" href="logout">
                                        <i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="ms-auto">
                        <a href="login" class="btn btn-primary btn-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i>เข้าสู่ระบบ
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
<?php endif; ?>

<div class="page-wrapper">
    <div class="<?= htmlspecialchars($containerClass) ?> py-4">
        <main class="app-main">