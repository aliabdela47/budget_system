<?php
include 'includes/db.php';

if (isset($_GET['owner_id'], $_GET['code_id'], $_GET['month'], $_GET['year'])) {
    $owner_id = $_GET['owner_id'];
    $code_id = $_GET['code_id'];
    $month = $_GET['month'];
    $year = $_GET['year'];
    
    $stmt = $pdo->prepare("
        SELECT remaining_monthly, remaining_quarterly, remaining_yearly 
        FROM budgets 
        WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$owner_id, $code_id, $year, $month]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($budget ? $budget : [
        'remaining_monthly' => 0,
        'remaining_quarterly' => 0,
        'remaining_yearly' => 0
    ]);
}
?>