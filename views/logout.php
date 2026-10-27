<?php
// views/logout.php

/**
 * ถ้าคุณใช้ Remember Me แบบยืดอายุ session cookie
 * (เรา set ไว้ใน login.php) ก็ควรลบ cookie ด้วย
 */
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,                // หมดอายุย้อนหลัง
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ลบข้อมูลทั้งหมดใน session
$_SESSION = [];

// ทำลาย session
session_destroy();

// จากนั้น redirect กลับไปหน้า login
header('Location: login');
exit;
