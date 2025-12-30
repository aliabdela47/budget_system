<?php
require_once 'includes/init.php';

header('Content-Type: application/json');

$owner_id = $_GET['owner_id'] ?? null;
$code_id = $_GET['code_id'] ?? null;
$budget_type = $_GET['budget_type'] ?? 'governmental';
$et_month = $_GET['et_month'] ?? null;
$year = date('Y') - 8;

if ($budget_type === 'program') {
    // Program budget - yearly only, no code_id filter
    $sql = "SELECT remaining_yearly AS remaining FROM budgets 
            WHERE budget_type='program' 
            AND owner_id = ? 
            AND year = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$owner_id, $year]);
} else {
    // Governmental budget - monthly only, no yearly fallback
    $ec_month = ecMonthNoFromString($et_month);
    $sql = "SELECT remaining_monthly AS remaining FROM budgets 
            WHERE budget_type='governmental' 
            AND owner_id = ? 
            AND code_id = ? 
            AND year = ? 
            AND ec_month = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$owner_id, $code_id, $year, $ec_month]);
}

$remaining = $stmt->fetchColumn() ?: 0;

echo json_encode(['remaining' => $remaining]);

// Helper function for Ethiopian month conversion
function ecMonthNoFromString($month) {
    if (empty($month)) return null;
    $month = trim($month);
    $months = [
        'መስከረም' => 1, 'ጥቅምት' => 2, 'ህዳር' => 3, 'ታኅሳስ' => 4,
        'ጥር' => 5, 'የካቲት' => 6, 'መጋቢት' => 7, 'ሚያዝያ' => 8,
        'ግንቦት' => 9, 'ሰኔ' => 10, 'ሐምሌ' => 11, 'ነሃሴ' => 12, 'ጳጉሜ' => 13
    ];
    return $months[$month] ?? null;
}
?>