<?php
include 'includes/db.php';

if (isset($_GET['owner_id'], $_GET['code_id'], $_GET['month'], $_GET['year'])) {
    $owner_id = $_GET['owner_id'];
    $code_id = $_GET['code_id'];
    $month = $_GET['month'];
    $year = $_GET['year'];
    
    // Strict monthly lookup for governmental budgets - no fallback
    $stmt = $pdo->prepare("
        SELECT remaining_monthly, remaining_yearly 
        FROM budgets 
        WHERE budget_type='governmental' AND owner_id = ? AND code_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$owner_id, $code_id, $year, $month]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($budget) {
        // Monthly row found
        echo json_encode([
            'remaining_monthly' => (float)$budget['remaining_monthly'],
            'remaining_yearly' => (float)$budget['remaining_yearly'],
            'error' => false
        ]);
    } else {
        // No monthly row found - return 0 with error flag
        echo json_encode([
            'remaining_monthly' => 0,
            'remaining_yearly' => 0,
            'error' => true,
            'message' => 'No monthly budget allocated for the selected month.'
        ]);
    }
}
?>