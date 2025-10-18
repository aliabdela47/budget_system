<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

try {
    $budget_type = $_GET['budget_type'] ?? 'governmental';
    $owner_id = !empty($_GET['owner_id']) ? (int)$_GET['owner_id'] : null;
    $code_id = !empty($_GET['code_id']) ? (int)$_GET['code_id'] : null;
    $year = (int)($_GET['year'] ?? (date('Y') - 8));

    $response = ['ok' => true];

    if ($budget_type === 'program') {
        // Program: totals by code_id and year from budgets (monthly_amount=0, month IS NULL)
        if ($code_id) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(remaining_yearly), 0) 
                FROM budgets 
                WHERE budget_type='program' 
                  AND code_id = ?
                  AND monthly_amount = 0 
                  AND month IS NULL
                  AND year = ?
            ");
            $stmt->execute([$code_id, $year]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(remaining_yearly), 0) 
                FROM budgets 
                WHERE budget_type='program' 
                  AND monthly_amount = 0 
                  AND month IS NULL
                  AND year = ?
            ");
            $stmt->execute([$year]);
        }
        $response['programsTotalYearly'] = (float)$stmt->fetchColumn();
    } else {
        // Governmental: owner/year "yearly remaining" values are informational
        // These do NOT imply fallback for monthly allocations
        if ($owner_id) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(remaining_yearly), 0) 
                FROM budgets 
                WHERE budget_type='governmental' 
                  AND monthly_amount = 0 
                  AND month IS NULL
                  AND year = ? 
                  AND owner_id = ?
            ");
            $stmt->execute([$year, $owner_id]);
            $response['govtOwnerRemainingYearly'] = (float)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(remaining_yearly), 0) 
                FROM budgets 
                WHERE budget_type='governmental' 
                  AND monthly_amount = 0 
                  AND month IS NULL
                  AND year = ?
            ");
            $stmt->execute([$year]);
            $response['govtBureauRemainingYearly'] = (float)$stmt->fetchColumn();
        }
    }
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>