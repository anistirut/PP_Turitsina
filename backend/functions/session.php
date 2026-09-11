<?php

require_once __DIR__ . '/../models/User.php';

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function loginUser(User $user): void
{
    $_SESSION['user'] = [
        'id'    => $user->id,
        'login' => $user->login,
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}
