<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
    $budget_type = $_GET['budget_type'] ?? 'governmental';
    $owner_id = !empty($_GET['owner_id']) ? (int)$_GET['owner_id'] : null;
    $et_month = $_GET['et_month'] ?? null;

    $sql = "SELECT p.*, o.name AS owner_name, o.code AS owner_code, e.name_am AS employee_name, c.name_amharic AS city_name
            FROM perdium_transactions p
            LEFT JOIN budget_owners o ON p.budget_owner_id = o.id
            LEFT JOIN emp_list e ON p.employee_id = e.id
            LEFT JOIN cities c ON p.city_id = c.id
            WHERE p.budget_type = ?";
    $params = [$budget_type];

    if ($owner_id) {
        $sql .= " AND p.budget_owner_id = ?";
        $params[] = $owner_id;
    }
    if ($budget_type === 'governmental' && $et_month) {
        $sql .= " AND p.et_month = ?";
        $params[] = $et_month;
    }
    $sql .= " ORDER BY p.created_at DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'rows' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>