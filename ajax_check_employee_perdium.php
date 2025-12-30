<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
    $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    if ($employee_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid employee_id']);
        exit;
    }

    $mode  = $_GET['mode'] ?? null;
    $start = $_GET['start'] ?? null;
    $end   = $_GET['end'] ?? null;
    $exclude_id = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;

    if ($mode === 'active') {
        $sql = "SELECT MAX(arrival_date) AS block_until
                FROM perdium_transactions
                WHERE employee_id = ?
                  AND CURDATE() BETWEEN departure_date AND arrival_date";
        $st = $pdo->prepare($sql);
        $st->execute([$employee_id]);
        $block_until = $st->fetchColumn();
        echo json_encode([
          'active' => (bool)$block_until,
          'block_until' => $block_until ?: null
        ]);
        exit;
    }

    if ($start && $end) {
        $sql = "SELECT COUNT(*)
                FROM perdium_transactions
                WHERE employee_id = ?
                  AND NOT (arrival_date < ? OR departure_date > ?)";
        $params = [$employee_id, $start, $end];
        if ($exclude_id) {
            $sql .= " AND id <> ?";
            $params[] = $exclude_id;
        }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $cnt = (int)$st->fetchColumn();
        echo json_encode(['overlap' => $cnt > 0]);
        exit;
    }

    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}