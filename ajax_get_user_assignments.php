<?php
require_once 'includes/init.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_GET['user_id'] ?? null;
$budget_type = $_GET['budget_type'] ?? 'governmental';

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    $assignments = getUserAssignedBudgets($pdo, $user_id);
    
    // Filter by budget type if specified
    if ($budget_type) {
        $assignments = array_filter($assignments, function($assignment) use ($budget_type) {
            return $assignment['budget_type'] === $budget_type;
        });
    }
    
    echo json_encode(['success' => true, 'assignments' => array_values($assignments)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}