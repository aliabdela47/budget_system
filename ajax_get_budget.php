<?php
require_once 'includes/init.php';

header('Content-Type: application/json');

$owner_id = $_GET['owner_id'] ?? null;
$code_id = $_GET['code_id'] ?? null;
$budget_type = $_GET['budget_type'] ?? 'governmental';
$et_month = $_GET['et_month'] ?? null;
$year = date('Y') - 8; // Assumes Ethiopian calendar conversion

if ($budget_type === 'program') {
    $table = 'p_budgets';
    $sql = "SELECT SUM(remaining_yearly) AS remaining FROM $table WHERE owner_id = ? AND year = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$owner_id, $year]);
} else {
    // Original logic for governmental budget
    $table = 'budgets';
    if ($et_month) {
        $sql = "SELECT remaining_monthly FROM $table WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$owner_id, $code_id, $year, $et_month]);
    } else {
        $sql = "SELECT remaining_yearly FROM $table WHERE owner_id = ? AND code_id = ? AND year = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$owner_id, $code_id, $year]);
    }
}

$remaining = $stmt->fetchColumn() ?: 0;

echo json_encode(['remaining' => $remaining]);
?>
