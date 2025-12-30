<?php
include 'includes/db.php';
header('Content-Type: application/json');

$owner_id = !empty($_GET['owner_id']) ? (int)$_GET['owner_id'] : null;
$code_id = !empty($_GET['code_id']) ? (int)$_GET['code_id'] : null;
$month = $_GET['month'] ?? null;
$year = (int)($_GET['year'] ?? (date('Y')-8));
$budget_type = $_GET['budget_type'] ?? 'governmental';

if (!$owner_id || !$year) {
    echo json_encode(['remaining_monthly' => 0, 'remaining_yearly' => 0]);
    exit;
}

$response = ['remaining_monthly' => 0, 'remaining_yearly' => 0];

if ($budget_type === 'program') {
    // For programs, only yearly matters. It's the sum of remaining for that owner.
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly), 0) FROM budgets WHERE budget_type='program' AND owner_id=? AND year=? AND monthly_amount=0");
    $stmt->execute([$owner_id, $year]);
    $response['remaining_yearly'] = (float)$stmt->fetchColumn();
} else {
    // For governmental, check specific perdium code
    if ($month && $code_id) {
        $stmt_month = $pdo->prepare("SELECT remaining_monthly FROM budgets WHERE owner_id=? AND code_id=? AND year=? AND month=?");
        $stmt_month->execute([$owner_id, $code_id, $year, $month]);
        $response['remaining_monthly'] = (float)($stmt_month->fetchColumn() ?: 0);
    }
    if ($code_id) {
        $stmt_year = $pdo->prepare("SELECT remaining_yearly FROM budgets WHERE owner_id=? AND code_id=? AND year=? AND monthly_amount=0");
        $stmt_year->execute([$owner_id, $code_id, $year]);
        $response['remaining_yearly'] = (float)($stmt_year->fetchColumn() ?: 0);
    }
}

echo json_encode($response);
?>