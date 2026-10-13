<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function current_user_name(): string
{
    return $_SESSION['user_name'] ?? 'User';
}
