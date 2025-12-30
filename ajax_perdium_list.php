<?php
require_once 'includes/init.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Simple debug version without Ethiopian calendar
try {
    // Build the base query
    $sql = "SELECT 
                pt.*,
                e.name as employee_name,
                e.name_am as employee_name_am,
                bo.name as owner_name,
                bo.code as owner_code,
                bo.p_koox as owner_p_koox,
                c.name_amharic as city_name,
                c.name_english as city_name_english
            FROM perdium_transactions pt
            LEFT JOIN emp_list e ON pt.employee_id = e.id
            LEFT JOIN budget_owners bo ON pt.budget_owner_id = bo.id
            LEFT JOIN cities c ON pt.city_id = c.id
            WHERE 1=1";
    
    $params = [];
    
    // Get filter parameters
    $budget_type = $_GET['budget_type'] ?? '';
    $owner_id = $_GET['owner_id'] ?? '';
    $et_month = $_GET['et_month'] ?? '';
    $employee_id = $_GET['employee_id'] ?? '';
    
    // Add budget type filter
    if (!empty($budget_type)) {
        $sql .= " AND pt.budget_type = ?";
        $params[] = $budget_type;
    }
    
    // Add owner filter
    if (!empty($owner_id)) {
        $sql .= " AND pt.budget_owner_id = ?";
        $params[] = $owner_id;
    }
    
    // Add month filter (only for governmental budget type)
    if (!empty($et_month) && $budget_type === 'governmental') {
        $sql .= " AND pt.et_month = ?";
        $params[] = $et_month;
    }
    
    // Add employee filter
    if (!empty($employee_id)) {
        $sql .= " AND pt.employee_id = ?";
        $params[] = $employee_id;
    }
    
    $sql .= " ORDER BY pt.created_at DESC, pt.id DESC";
    
    // Debug: Log the actual SQL and parameters
    error_log("SQL Query: " . $sql);
    error_log("SQL Parameters: " . implode(', ', $params));
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Simple date formatting
    foreach ($rows as &$row) {
        $row['ethiopian_date'] = date('d/m/Y', strtotime($row['created_at']));
        // Create a display name for employee
        $row['employee_display_name'] = $row['employee_name'];
        if (!empty($row['employee_name_am'])) {
            $row['employee_display_name'] .= ' | ' . $row['employee_name_am'];
        }
        // Create a display name for owner based on budget type
        if ($row['budget_type'] === 'program') {
            $row['owner_display_name'] = $row['owner_name']; // Show program name like UNDAF
        } else {
            $row['owner_display_name'] = $row['owner_p_koox'] ?: $row['owner_code']; // Show koox for governmental
        }
        // Use Amharic city name if available, otherwise English
        $row['city_display_name'] = !empty($row['city_name']) ? $row['city_name'] : $row['city_name_english'];
    }
    
    echo json_encode([
        'success' => true, 
        'rows' => $rows,
        'count' => count($rows),
        'debug' => [
            'query' => $sql,
            'params' => $params,
            'rows_found' => count($rows)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in ajax_perdium_list.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug' => [
            'query' => $sql ?? 'No query',
            'params' => $params ?? []
        ]
    ]);
}
?>