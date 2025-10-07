<?php
require_once 'includes/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$budget_type = $_GET['budget_type'] ?? 'governmental';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'officer';

try {
    $owners = getFilteredBudgetOwners($pdo, $user_id, $user_role, $budget_type);
    echo json_encode(['success' => true, 'owners' => $owners]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}