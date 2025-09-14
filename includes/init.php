<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'functions.php';
require_once 'auth.php'; // add this

// Block unauthenticated except login
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'index.php') {
    header('Location: index.php');
    exit;
}

// Optional: flash message helper (if not in functions.php)
if (!function_exists('flash')) {
    function flash() {
        if (!empty($_SESSION['flash_error'])) {
            echo '<div class="mb-4 p-3 rounded bg-red-50 text-red-700">'.htmlspecialchars($_SESSION['flash_error']).'</div>';
            unset($_SESSION['flash_error']);
        }
    }
}