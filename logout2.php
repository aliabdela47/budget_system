<?php

// includes/logout.php

// This file handles logging the user out by destroying their session and clearing the "remember me" cookie.

require_once 'includes/init.php';

// --- Clear "Remember Me" Cookie ---
// This block of code is a more robust way to handle the logout logic for "remember me" tokens.
if (!empty($_COOKIE['remember'])) {
    $cookie = $_COOKIE['remember'];

    // Ensure the cookie value has a valid format (selector:validator).
    if (strpos($cookie, ':') !== false) {
        list($selector,) = explode(':', $cookie, 2);

        // Validate the selector to prevent SQL injection or other attacks.
        if (ctype_xdigit($selector)) {
            // Delete the token from the database.
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        }
    }

    // Always clear the cookie in the user's browser, regardless of its format.
    setcookie('remember', '', time() - 3600, '/');
}

// --- Destroy Session ---
// This ensures all session data is cleared, effectively logging the user out.

// Unset all session variables.
$_SESSION = [];

// If cookies are used for the session, clear the session cookie.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session.
session_destroy();

// Redirect to the login page after successful logout.
header('Location: index.php');
exit;
