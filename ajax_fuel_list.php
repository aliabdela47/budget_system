<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
  $budget_type = $_GET['budget_type'] ?? 'governmental';
  $owner_id    = $_GET['owner_id'] ?? '';
  $et_month    = $_GET['et_month'] ?? '';
  $plate       = $_GET['plate'] ?? '';

  $q = "SELECT f.*, o.code AS owner_code
        FROM fuel_transactions f
        LEFT JOIN budget_owners o ON f.owner_id = o.id
        WHERE f.budget_type = ?";
  $p = [$budget_type];

  if ($owner_id !== '') { $q .= " AND f.owner_id = ?"; $p[] = $owner_id; }
  if ($budget_type !== 'program' && $et_month !== '') { $q .= " AND f.et_month = ?"; $p[] = $et_month; }
  if ($plate !== '') { $q .= " AND f.plate_number = ?"; $p[] = $plate; }

  $q .= " ORDER BY f.date DESC, f.id DESC LIMIT 500";
  $stmt = $pdo->prepare($q); $stmt->execute($p);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'rows'=>$rows]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}