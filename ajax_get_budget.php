<?php
require_once 'includes/init.php';

header('Content-Type: application/json');

try {
    $owner_id = $_GET['owner_id'] ?? null;
    $code_id = $_GET['code_id'] ?? null;
    $budget_type = $_GET['budget_type'] ?? 'governmental';
    $et_month = $_GET['et_month'] ?? null;
    $year = (int)($_GET['year'] ?? (date('Y') - 8)); // Ethiopian calendar conversion

    if (!$owner_id) {
        echo json_encode(['error' => 'owner_id required', 'remaining' => 0]);
        exit;
    }

    if ($budget_type === 'program') {
        // Program: budgets table only, yearly-only (month IS NULL, monthly_amount=0)
        $sql = "SELECT COALESCE(SUM(remaining_yearly), 0) AS remaining 
                FROM budgets 
                WHERE budget_type='program' 
                  AND owner_id = ? 
                  AND code_id = ?
                  AND year = ?
                  AND monthly_amount = 0
                  AND month IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$owner_id, $code_id, $year]);
    } else {
        // Governmental: strict monthly or yearly
        if ($et_month) {
            $sql = "SELECT COALESCE(remaining_monthly, 0) AS remaining 
                    FROM budgets 
                    WHERE budget_type='governmental'
                      AND owner_id = ? 
                      AND code_id = ? 
                      AND year = ? 
                      AND month = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$owner_id, $code_id, $year, $et_month]);
        } else {
            $sql = "SELECT COALESCE(remaining_yearly, 0) AS remaining 
                    FROM budgets 
                    WHERE budget_type='governmental'
                      AND owner_id = ? 
                      AND code_id = ? 
                      AND year = ?
                      AND monthly_amount = 0
                      AND month IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$owner_id, $code_id, $year]);
        }
    }

    $remaining = $stmt->fetchColumn() ?: 0;

    echo json_encode(['remaining' => (float)$remaining]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'remaining' => 0]);
}
?>
