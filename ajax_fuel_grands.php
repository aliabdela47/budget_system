<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
  $budget_type = $_GET['budget_type'] ?? 'governmental';
  $owner_id    = isset($_GET['owner_id']) && $_GET['owner_id'] !== '' ? (int)$_GET['owner_id'] : null;
  $year        = (int)($_GET['year'] ?? (date('Y') - 8));

  if ($budget_type === 'program') {
    $response = ['ok' => true];

    // Owner-specific (optional)
    if ($owner_id) {
      // Try p_budgets first
      $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly),0) FROM p_budgets WHERE year=? AND owner_id=?");
      $stmt->execute([$year, $owner_id]);
      $ownerRem = (float)$stmt->fetchColumn();

      // Fallback to budgets (program yearly rows)
      if ($ownerRem <= 0) {
        $stmt = $pdo->prepare("
          SELECT COALESCE(SUM(remaining_yearly),0)
          FROM budgets
          WHERE budget_type='program' AND monthly_amount=0 AND year=? AND owner_id=?
        ");
        $stmt->execute([$year, $owner_id]);
        $ownerRem = (float)$stmt->fetchColumn();
      }
      $response['programOwnerRemainingYearly'] = $ownerRem;
    }

    // Bureau total
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly), 0) FROM p_budgets WHERE year=?");
    $stmt->execute([$year]);
    $total = (float)$stmt->fetchColumn();

    if ($total <= 0) {
      $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(remaining_yearly), 0)
        FROM budgets
        WHERE budget_type='program' AND monthly_amount=0 AND year=?
      ");
      $stmt->execute([$year]);
      $total = (float)$stmt->fetchColumn();
    }

    $response['programsTotalYearly'] = $total;
    echo json_encode($response); exit;
  }

  // Governmental
  if ($owner_id) {
    $stmt = $pdo->prepare("
      SELECT COALESCE(SUM(remaining_yearly),0)
      FROM budgets
      WHERE budget_type='governmental' AND monthly_amount=0 AND year=? AND owner_id=?
    ");
    $stmt->execute([$year, $owner_id]);
    echo json_encode(['ok'=>true,'govtOwnerYearlyRemaining'=>(float)$stmt->fetchColumn()]); exit;
  } else {
    $stmt = $pdo->prepare("
      SELECT COALESCE(SUM(remaining_yearly),0)
      FROM budgets
      WHERE budget_type='governmental' AND monthly_amount=0 AND year=?
    ");
    $stmt->execute([$year]);
    echo json_encode(['ok'=>true,'govtBureauRemainingYearly'=>(float)$stmt->fetchColumn()]); exit;
  }
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}