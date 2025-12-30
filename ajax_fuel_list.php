<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
  $budget_type = $_GET['budget_type'] ?? 'governmental';
  $owner_id    = isset($_GET['owner_id']) && $_GET['owner_id'] !== '' ? (int)$_GET['owner_id'] : null;
  $et_month    = $_GET['et_month'] ?? null;
  $plate       = $_GET['plate'] ?? null;

  $sql = "SELECT f.*,
                 COALESCE(go.code, po.code) AS owner_code
          FROM fuel_transactions f
          LEFT JOIN budget_owners go   ON (f.owner_id = go.id AND f.budget_type='governmental')
          LEFT JOIN p_budget_owners po ON (f.owner_id = po.id AND f.budget_type='program')
          WHERE f.budget_type = ?";
  $params = [$budget_type];

  if ($owner_id) {
    $sql .= " AND f.owner_id = ?";
    $params[] = $owner_id;
  }
  if ($budget_type !== 'program' && $et_month) {
    $sql .= " AND f.et_month = ?";
    $params[] = $et_month;
  }
  if ($plate) {
    $sql .= " AND f.plate_number = ?";
    $params[] = $plate;
  }

  $sql .= " ORDER BY f.date DESC, f.id DESC LIMIT 500";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'rows'=>$rows]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}