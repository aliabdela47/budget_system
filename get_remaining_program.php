<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

$owner_id = $_GET['owner_id'] ?? '';
$year     = $_GET['year'] ?? '';

if (!$owner_id || !$year) {
  echo json_encode(['remaining_yearly' => 0]);
  exit;
}

// Look for program budget without code_id filter
$stmt = $pdo->prepare("
  SELECT COALESCE(SUM(remaining_yearly),0)
  FROM budgets
  WHERE budget_type='program'
    AND owner_id = ?
    AND year = ?
");
$stmt->execute([(int)$owner_id, (int)$year]);
$rem = (float)$stmt->fetchColumn();

echo json_encode(['remaining_yearly' => $rem]);
?>