<?php
// app/helpers.php

function redirect($url)
{
    header("Location: $url");
    exit;
}

function set_flash($key, $message)
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash($key)
{
    if (isset($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

function e($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * สร้างรหัสงานอัตโนมัติจาก task id
 * ตัวอย่าง: TSK-202512-000123
 */
function make_task_code(int $taskId, string $prefix = 'TSK'): string
{
    $ym = date('Ym');
    return $prefix . '-' . $ym . '-' . str_pad((string)$taskId, 6, '0', STR_PAD_LEFT);
}
