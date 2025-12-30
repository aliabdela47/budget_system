<?php

// includes/init.php

// This file sets up the database connection, session, and other essential helpers.
// It should be included at the very top of every page and must not output anything.

// --- Database Connection ---
// The db.php file should establish a connection and store it in the $pdo variable.
require_once __DIR__ . '/db.php';

// --- Secure Session Cookies ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'],
        'secure'   => !empty($_SERVER['HTTPS']), // Enforces HTTPS
        'httponly' => true,                       // Prevents JavaScript access
        'samesite' => 'Lax'                       // Prevents CSRF attacks
    ]);
    session_start();
}

// --- Basic Helper Functions ---

/**
 * Generates and returns a CSRF token.
 *
 * @return string The CSRF token.
 */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Checks if a given token matches the session's CSRF token.
 *
 * @param string $t The token to check.
 * @return bool True if tokens match, false otherwise.
 */
function csrf_check($t) {
    return hash_equals($_SESSION['csrf'] ?? '', $t ?? '');
}

/**
 * Redirects the user to the login page if they are not authenticated.
 */
function require_login() {
    if (empty($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Redirects the user if they are not an administrator.
 */
function require_admin() {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: index.php');
        exit;
    }
}

// --- Auto-login via "remember me" cookie ---
// This block handles auto-logging in a user if their session has expired
// but a valid and secure "remember me" cookie is present.
if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember'])) {
    try {
        $cookie = $_COOKIE['remember'];
        if (strpos($cookie, ':') !== false) {
            list($selector, $validator) = explode(':', $cookie, 2);

            // Validate that the selector and validator are in the expected format.
            if (ctype_xdigit($selector) && ctype_xdigit($validator) && strlen($validator) === 64) {
                // Fetch the token from the database using the selector.
                $stmt = $pdo->prepare("SELECT rt.user_id, rt.validator_hash, rt.expires_at, rt.user_agent_hash, u.username, u.name, u.role 
                                       FROM remember_tokens rt 
                                       JOIN users u ON u.id = rt.user_id 
                                       WHERE rt.selector = ? LIMIT 1");
                $stmt->execute([$selector]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    // Check for token expiration and timing-safe hash comparison.
                    if (new DateTime() < new DateTime($row['expires_at'])) {
                        $calc    = hash('sha256', $validator);
                        $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

                        if (hash_equals($row['validator_hash'], $calc) && hash_equals($row['user_agent_hash'], $ua_hash)) {
                            // Success: Set session variables and rotate the validator for enhanced security.
                            session_regenerate_id(true);
                            $_SESSION['user_id']   = (int)$row['user_id'];
                            $_SESSION['username']  = $row['username'];
                            $_SESSION['role']      = $row['role'];
                            $_SESSION['user_name'] = $row['name'] ?? $row['username'];

                            // Rotate the validator.
                            $newValidator = bin2hex(random_bytes(32));
                            $newHash      = hash('sha256', $newValidator);
                            $upd          = $pdo->prepare("UPDATE remember_tokens SET validator_hash=?, last_used_at=NOW() WHERE selector=?");
                            $upd->execute([$newHash, $selector]);
                            $cookieVal = $selector . ':' . $newValidator;
                            
                            // Update the cookie with the new validator.
                            setcookie('remember', $cookieVal, [
                                'expires'  => time() + 60 * 60 * 24 * 30,
                                'path'     => '/',
                                'domain'   => $_SERVER['HTTP_HOST'],
                                'secure'   => !empty($_SERVER['HTTPS']),
                                'httponly' => true,
                                'samesite' => 'Lax'
                            ]);
                        } else {
                            // Invalid validator or user agent: a potential token theft attempt.
                            // Delete the token from the database and clear the cookie.
                            $del = $pdo->prepare("DELETE FROM remember_tokens WHERE selector=?");
                            $del->execute([$selector]);
                            setcookie('remember', '', time() - 3600, '/');
                        }
                    } else {
                        // Expired token: delete it from the database and clear the cookie.
                        $del = $pdo->prepare("DELETE FROM remember_tokens WHERE selector=?");
                        $del->execute([$selector]);
                        setcookie('remember', '', time() - 3600, '/');
                    }
                } else {
                    // No token found in the database for the given selector: clear the cookie.
                    setcookie('remember', '', time() - 3600, '/');
                }
            }
        }
    } catch (Throwable $e) {
        // Fail closed: do nothing if an unexpected error occurs.
    }
}
