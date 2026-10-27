<?php

declare(strict_types=1);

// Session is started in bootstrap/app.php.

function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: login');
        exit;
    }
}

function require_login_api(): void
{
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'UNAUTHORIZED'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function require_boss_api(): void
{
    require_login_api();
    if (($_SESSION['user']['role'] ?? '') !== 'boss') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'FORBIDDEN'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_boss(): bool
{
    return isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'boss';
}
