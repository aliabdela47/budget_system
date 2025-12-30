<?php
// includes/index.php

require_once 'includes/init.php';

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https://via.placeholder.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// Redirect if already logged in
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Rate limiting constants
$LOCK_SECONDS = 300; // 5 minutes
$MAX_FAILS = 5;

// Function to check if login is locked
function login_locked() {
    $lockUntil = $_SESSION['login_lock_until'] ?? 0;
    return $lockUntil && time() < $lockUntil;
}

// Function to register a failed login attempt
function register_fail() {
    $fails = (int)($_SESSION['login_fail_count'] ?? 0) + 1;
    $_SESSION['login_fail_count'] = $fails;
    if ($fails >= $MAX_FAILS) {
        $_SESSION['login_lock_until'] = time() + $LOCK_SECONDS;
    }
}

// Function to clear failed login attempts
function clear_fail() {
    unset($_SESSION['login_fail_count'], $_SESSION['login_lock_until']);
}

$error = null;
$lockedRemaining = 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (login_locked()) {
        $lockedRemaining = max(0, ($_SESSION['login_lock_until'] ?? 0) - time());
        $error = 'Too many failed attempts. Try again in ' . ceil($lockedRemaining / 60) . ' minute(s).';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $remember = !empty($_POST['remember_me']);

        if ($username === '' || $password === '') {
            $error = 'Please enter username and password.';
        } else {
            $stmt = $pdo->prepare("SELECT id, username, name, password_hash, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_name'] = $user['name'] ?? $user['username'];
                clear_fail();

                if ($remember) {
                    $selector = bin2hex(random_bytes(9));
                    $validator = bin2hex(random_bytes(32));
                    $hash = hash('sha256', $validator);
                    $ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                    $expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

                    $ins = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, user_agent_hash, expires_at) VALUES (?,?,?,?,?)");
                    try {
                        $ins->execute([(int)$user['id'], $selector, $hash, $ua_hash, $expires]);
                    } catch (Throwable $e) {
                        // Fail silently if table doesn't exist.
                    }

                    $cookieVal = $selector . ':' . $validator;
                    setcookie('remember', $cookieVal, [
                        'expires' => time() + 60*60*24*30,
                        'path' => '/',
                        'domain' => $_SERVER['HTTP_HOST'],
                        'secure' => !empty($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
                header('Location: dashboard.php');
                exit;
            } else {
                register_fail();
                $lockedRemaining = login_locked() ? (($_SESSION['login_lock_until'] ?? 0) - time()) : 0;
                $error = 'Invalid credentials.';
            }
        }
    }
}
?>
