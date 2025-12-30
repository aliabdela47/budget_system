<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_role(): string {
    return $_SESSION['role'] ?? '';
}
function is_admin(): bool {
    return (current_role() === 'admin');
}
function is_officer(): bool {
    return (current_role() === 'officer');
}

// Enforce admin-only access
function require_admin(): void {
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'You are not authorized to access that page.';
        header('Location: dashboard.php');
        exit;
    }
}

// Enforce officer-only (if you ever need it)
function require_officer(): void {
    if (!is_officer()) {
        $_SESSION['flash_error'] = 'Officers only.';
        header('Location: dashboard.php');
        exit;
    }
}