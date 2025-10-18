<?php
include 'includes/db.php';

header('Content-Type: application/json');

try {
    if (!isset($_GET['owner_id'], $_GET['code_id'], $_GET['month'], $_GET['year'], $_GET['budget_type'])) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    $owner_id = (int)$_GET['owner_id'];
    $code_id = (int)$_GET['code_id'];
    $month = $_GET['month'];
    $year = (int)$_GET['year'];
    $budget_type = $_GET['budget_type'] === 'program' ? 'program' : 'governmental';
    
    if ($budget_type === 'program') {
        // Program: yearly-only fetch (month IS NULL, monthly_amount=0)
        $stmt = $pdo->prepare("
            SELECT remaining_yearly, 0 as remaining_monthly, 0 as remaining_quarterly 
            FROM budgets 
            WHERE budget_type='program'
              AND owner_id = ? 
              AND code_id = ? 
              AND year = ? 
              AND monthly_amount = 0
              AND month IS NULL
        ");
        $stmt->execute([$owner_id, $code_id, $year]);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$budget) {
            echo json_encode([
                'error' => 'No program yearly budget found',
                'remaining_monthly' => 0,
                'remaining_quarterly' => 0,
                'remaining_yearly' => 0
            ]);
            exit;
        }
        
        // Check for duplicates
        $dupStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM budgets 
            WHERE budget_type='program'
              AND owner_id = ? 
              AND code_id = ? 
              AND year = ? 
              AND monthly_amount = 0
              AND month IS NULL
        ");
        $dupStmt->execute([$owner_id, $code_id, $year]);
        if ((int)$dupStmt->fetchColumn() > 1) {
            echo json_encode([
                'error' => 'Duplicate program budgets detected - please run diagnostic',
                'remaining_monthly' => 0,
                'remaining_quarterly' => 0,
                'remaining_yearly' => 0
            ]);
            exit;
        }
        
    } else {
        // Governmental: strict monthly fetch (no fallback)
        if (empty($month)) {
            echo json_encode([
                'error' => 'Month is required for governmental budget',
                'remaining_monthly' => 0,
                'remaining_quarterly' => 0,
                'remaining_yearly' => 0
            ]);
            exit;
        }
        
        $stmt = $pdo->prepare("
            SELECT remaining_monthly, remaining_quarterly, remaining_yearly 
            FROM budgets 
            WHERE budget_type='governmental'
              AND owner_id = ? 
              AND code_id = ? 
              AND year = ? 
              AND month = ?
        ");
        $stmt->execute([$owner_id, $code_id, $year, $month]);
        $budget = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$budget) {
            echo json_encode([
                'error' => 'No monthly budget found for this month',
                'remaining_monthly' => 0,
                'remaining_quarterly' => 0,
                'remaining_yearly' => 0
            ]);
            exit;
        }
        
        // Check for duplicates
        $dupStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM budgets 
            WHERE budget_type='governmental'
              AND owner_id = ? 
              AND code_id = ? 
              AND year = ? 
              AND month = ?
        ");
        $dupStmt->execute([$owner_id, $code_id, $year, $month]);
        if ((int)$dupStmt->fetchColumn() > 1) {
            echo json_encode([
                'error' => 'Duplicate monthly budgets detected - please run diagnostic',
                'remaining_monthly' => 0,
                'remaining_quarterly' => 0,
                'remaining_yearly' => 0
            ]);
            exit;
        }
    }
    
    echo json_encode($budget ?: [
        'remaining_monthly' => 0,
        'remaining_quarterly' => 0,
        'remaining_yearly' => 0
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>