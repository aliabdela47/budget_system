<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
  $budget_type = $_GET['budget_type'] ?? 'governmental';
  $owner_id    = (int)($_GET['owner_id'] ?? 0);
  $year        = (int)($_GET['year'] ?? (date('Y')-8));

  if ($budget_type === 'program') {
    // Bureau programs total (sum of yearly_amount) — you can switch to remaining_yearly if you prefer
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(yearly_amount),0) FROM budgets WHERE budget_type='program' AND monthly_amount=0 AND year=?");
    $stmt->execute([$year]);
    $programsTotalYearly = (float)$stmt->fetchColumn();
    echo json_encode(['ok'=>true,'programsTotalYearly'=>$programsTotalYearly]); exit;
  }

  // Government grand
  if ($owner_id > 0) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly),0) FROM budgets WHERE budget_type='governmental' AND monthly_amount=0 AND year=? AND owner_id=?");
    $stmt->execute([$year, $owner_id]);
    $govtOwnerYearlyRemaining = (float)$stmt->fetchColumn();
    echo json_encode(['ok'=>true,'govtOwnerYearlyRemaining'=>$govtOwnerYearlyRemaining]); exit;
  } else {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly),0) FROM budgets WHERE budget_type='governmental' AND monthly_amount=0 AND year=?");
    $stmt->execute([$year]);
    $govtBureauRemainingYearly = (float)$stmt->fetchColumn();
    echo json_encode(['ok'=>true,'govtBureauRemainingYearly'=>$govtBureauRemainingYearly]); exit;
  }
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}