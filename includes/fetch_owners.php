<?php
session_start();
require_once 'activity_logger.php';

// Get user ID from session (you may need to query it if only username is stored)
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$username]);
$userId = $stmt->fetchColumn();

// Log unauthorized attempt
logUserActivity($pdo, $userId, 'unauthorized_access', "Tried to access $requestedSource budget owners");
require_once 'db.php';
require_once 'access.php';

$username = $_SESSION['username'] ?? '';
$role = $_SESSION['role'] ?? '';

$access = getUserBudgetAccess($username, $role);

// Determine allowed source
$requestedSource = $_GET['source'] ?? '';
$allowedSource = $access['source'][0] ?? '';
$ownersTable = $access['owners_table'][0] ?? '';
$ownersFilter = $access['owners_filter'];


// Block unauthorized source switching
if ($requestedSource === 'program' && $allowedSource !== 'Programs Budget') {
    logUserActivity($pdo, $userId, 'unauthorized_access', "Tried to access Programs Budget");
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access to Programs Budget']);
    exit;
}
if ($requestedSource === 'governmental' && $allowedSource !== 'Government Budget') {
    logUserActivity($pdo, $userId, 'unauthorized_access', "Tried to access Government Budget");
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access to Government Budget']);
    exit;
}

// Determine correct table
$table = ($requestedSource === 'program') ? 'p_budget_owners' : 'budget_owners';

// Fetch owners
$query = "SELECT id, name FROM $table";
if ($ownersFilter) {
    $placeholders = implode(',', array_fill(0, count($ownersFilter), '?'));
    $query .= " WHERE name IN ($placeholders)";
    $stmt = $pdo->prepare($query);
    $stmt->execute($ownersFilter);
} else {
    $stmt = $pdo->query($query);
}
$owners = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($owners);