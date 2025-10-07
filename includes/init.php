<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);

require_once 'db.php';
require_once 'functions.php';
require_once 'auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Helper Functions ---

/**
 * A shortcut for htmlspecialchars to prevent XSS attacks.
 * @param string|null $s The string to escape.
 * @return string The escaped string.
 */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a number as a currency string.
 * @param float|int $n The number to format.
 * @return string The formatted number string.
 */
function fmt($n) {
    return number_format((float)$n, 2);
}

// --- Budget Access Control Functions ---

/**
 * Get user's assigned budget owners
 */
function getUserAssignedBudgets($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT uba.budget_type, uba.budget_owner_id, 
               CASE 
                   WHEN uba.budget_type = 'governmental' THEN bo.name 
                   WHEN uba.budget_type = 'program' THEN pbo.name 
               END as owner_name,
               CASE 
                   WHEN uba.budget_type = 'governmental' THEN bo.code 
                   WHEN uba.budget_type = 'program' THEN pbo.code 
               END as owner_code
        FROM user_budget_assignments uba
        LEFT JOIN budget_owners bo ON uba.budget_type = 'governmental' AND uba.budget_owner_id = bo.id
        LEFT JOIN p_budget_owners pbo ON uba.budget_type = 'program' AND uba.budget_owner_id = pbo.id
        WHERE uba.user_id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if user has access to specific budget type and owner
 */
function hasBudgetAccess($pdo, $user_id, $budget_type, $budget_owner_id) {
    // Admin users have full access
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['role'] === 'admin') {
        return true;
    }
    
    // Check specific assignments for officers
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_budget_assignments 
        WHERE user_id = ? AND budget_type = ? AND budget_owner_id = ?
    ");
    $stmt->execute([$user_id, $budget_type, $budget_owner_id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get filtered budget owners based on user role and assignments
 */
function getFilteredBudgetOwners($pdo, $user_id, $user_role, $budget_type) {
    if ($user_role === 'admin') {
        // Admin gets all budget owners
        if ($budget_type === 'governmental') {
            return $pdo->query("SELECT * FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return $pdo->query("SELECT * FROM p_budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Officer gets only assigned budget owners
        $stmt = $pdo->prepare("
            SELECT uba.budget_owner_id as id, 
                   CASE 
                       WHEN uba.budget_type = 'governmental' THEN bo.code 
                       WHEN uba.budget_type = 'program' THEN pbo.code 
                   END as code,
                   CASE 
                       WHEN uba.budget_type = 'governmental' THEN bo.name 
                       WHEN uba.budget_type = 'program' THEN pbo.name 
                   END as name,
                   bo.p_koox
            FROM user_budget_assignments uba
            LEFT JOIN budget_owners bo ON uba.budget_type = 'governmental' AND uba.budget_owner_id = bo.id
            LEFT JOIN p_budget_owners pbo ON uba.budget_type = 'program' AND uba.budget_owner_id = pbo.id
            WHERE uba.user_id = ? AND uba.budget_type = ?
            ORDER BY code
        ");
        $stmt->execute([$user_id, $budget_type]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Check if current page requires budget access control
 */
function requiresBudgetAccessControl() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $controlled_pages = ['perdium.php', 'fuel_management.php', 'transactions.php'];
    return in_array($current_page, $controlled_pages);
}

// --- Track Online Users ---
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE users SET last_seen = NOW(), ip_address = ? WHERE id = ?"
        );
        // Get the real IP address, even behind a proxy like Cloudflare
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        $stmt->execute([$ip, $_SESSION['user_id']]);
    } catch (PDOException $e) {
        // Silently fail if DB connection is lost, don't break the page
    }
}
// --- End Track Online Users ---

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