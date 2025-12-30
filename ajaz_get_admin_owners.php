<?php
require_once 'includes/init.php';

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$budget_type = $_GET['budget_type'] ?? 'governmental';
$user_id = $_GET['user_id'] ?? null;

try {
    if ($budget_type === 'governmental') {
        $owners = $pdo->query("SELECT * FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $owners = $pdo->query("SELECT * FROM p_budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Mark which owners are already assigned to the user
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT budget_owner_id FROM user_budget_assignments WHERE user_id = ? AND budget_type = ?");
        $stmt->execute([$user_id, $budget_type]);
        $assigned_owners = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($owners as &$owner) {
            $owner['assigned'] = in_array($owner['id'], $assigned_owners);
        }
    }

    echo json_encode(['success' => true, 'owners' => $owners]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}