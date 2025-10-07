<?php
require_once 'includes/init.php';

header('Content-Type: application/json');

$budget_type = $_GET['budget_type'] ?? 'governmental';

if ($budget_type === 'program') {
    $table = 'p_budget_owners';
    $sql = "SELECT id, code, name, '' as p_koox FROM $table ORDER BY code";
} else {
    $table = 'budget_owners';
    $sql = "SELECT id, code, name, p_koox FROM $table ORDER BY code";
}

try {
    $owners = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'owners' => $owners]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}