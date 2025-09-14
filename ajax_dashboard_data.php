<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
    // Get filter parameters
    $start_date = $_GET['startDate'] ?? null;
    $end_date = $_GET['endDate'] ?? null;
    $owner_id = !empty($_GET['ownerFilter']) ? (int)$_GET['ownerFilter'] : null;
    $code_id = !empty($_GET['typeFilter']) ? (int)$_GET['typeFilter'] : null;

    $params = [];
    $where_clauses = " WHERE 1=1 ";

    // Build dynamic WHERE clauses safely
    if ($owner_id) {
        $where_clauses .= " AND o.id = ? ";
        $params[] = $owner_id;
    }
    if ($code_id) {
        $where_clauses .= " AND b.code_id = ? ";
        $params[] = $code_id;
    }

    // Date filtering for transactions
    $tx_where_clauses = " WHERE 1=1 ";
    $tx_params = [];
    if ($owner_id) { $tx_where_clauses .= " AND t.owner_id = ? "; $tx_params[] = $owner_id; }
    if ($code_id)  { $tx_where_clauses .= " AND t.code_id = ? ";  $tx_params[] = $code_id; }
    if ($start_date) { $tx_where_clauses .= " AND t.date >= ? "; $tx_params[] = $start_date . ' 00:00:00'; }
    if ($end_date)   { $tx_where_clauses .= " AND t.date <= ? "; $tx_params[] = $end_date . ' 23:59:59'; }

    // 1. Fetch data for directorate chart and stats cards
    $directorate_data = [];
    $sql = "
        SELECT
            o.id, o.name,
            COALESCE(SUM(b.yearly_amount + b.monthly_amount), 0) AS allocated,
            COALESCE((SELECT SUM(t.amount) FROM transactions t " . $tx_where_clauses . " AND t.owner_id = o.id), 0) AS used
        FROM budget_owners o
        LEFT JOIN budgets b ON o.id = b.owner_id
        " . str_replace('b.code_id', 'budgets.code_id', str_replace('o.id', 'b.owner_id', $where_clauses)) . "
        GROUP BY o.id, o.name
        ORDER BY allocated DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($tx_params, $params));
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['remaining'] = $row['allocated'] - $row['used'];
        $directorate_data[] = $row;
    }

    // 2. Fetch recent transactions
    $tx_sql = "
        SELECT t.*, o.name AS owner_name, c.name AS code_name
        FROM transactions t
        LEFT JOIN budget_owners o ON t.owner_id = o.id
        LEFT JOIN budget_codes c ON t.code_id = c.id
        " . $tx_where_clauses . "
        ORDER BY t.date DESC, t.id DESC
        LIMIT 5
    ";
    $tx_stmt = $pdo->prepare($tx_sql);
    $tx_stmt->execute($tx_params);
    $recent_transactions = $tx_stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Calculate overall totals
    $total_allocated = array_sum(array_column($directorate_data, 'allocated'));
    $total_used = array_sum(array_column($directorate_data, 'used'));
    $total_remaining = $total_allocated - $total_used;

    // Response object
    $response = [
        'ok' => true,
        'stats' => [
            'total_allocated' => $total_allocated,
            'total_used' => $total_used,
            'total_remaining' => $total_remaining,
            'utilization_percentage' => ($total_allocated > 0) ? ($total_used / $total_allocated) * 100 : 0,
        ],
        'charts' => [
            'allocation_labels' => array_column($directorate_data, 'name'),
            'allocation_data' => array_column($directorate_data, 'allocated'),
        ],
        'recent_transactions' => $recent_transactions
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>