<?php
require_once 'includes/db.php';

$owner_id = $_GET['owner_id'] ?? '';
$year     = $_GET['year'] ?? '';

if (!$owner_id || !$year) {
  echo json_encode(['remaining_yearly' => 0]);
  exit;
}

// Sum remaining_yearly across program yearly rows
$stmt = $pdo->prepare("
  SELECT COALESCE(SUM(remaining_yearly),0)
  FROM budgets
  WHERE budget_type='program'
    AND owner_id = ?
    AND year = ?
    AND monthly_amount = 0
");
$stmt->execute([$owner_id, $year]);
echo json_encode(['remaining_yearly' => (float)$stmt->fetchColumn()]);