<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

function ecMonthNoFromString($m) {
    if ($m === null) return null;
    $m = trim((string)$m);
    if ($m === '') return null;
    
    static $map = [
        'መስከረም' => 1, 'ጥቅምት' => 2, 'ህዳር' => 3, 'ታኅሳስ' => 4,
        'ጥር' => 5, 'የካቲት' => 6, 'መጋቢት' => 7, 'ሚያዝያ' => 8,
        'ግንቦት' => 9, 'ሰኔ' => 10, 'ሐምሌ' => 11, 'ነሃሴ' => 12, 'ጳጉሜ' => 13
    ];
    
    return $map[$m] ?? null;
}

$owner_id = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
$code_id  = isset($_GET['code_id'])  ? (int)$_GET['code_id']  : 0;
$month    = $_GET['month'] ?? null;
$year     = isset($_GET['year'])     ? (int)$_GET['year']     : 0;

if ($owner_id <= 0 || $code_id <= 0 || !$month || $year <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$ecm = ecMonthNoFromString($month);

// Monthly remaining
$stmt_monthly = $pdo->prepare("
    SELECT remaining_monthly
    FROM budgets
    WHERE budget_type='governmental'
        AND owner_id=?
        AND code_id=?
        AND year=?
        AND ec_month=?
    LIMIT 1
");
$stmt_monthly->execute([$owner_id, $code_id, $year, $ecm]);
$remaining_monthly = (float)($stmt_monthly->fetchColumn() ?: 0.0);

// Yearly remaining for governmental (for the same owner + code_id + budget_type)
$stmt_yearly = $pdo->prepare("
    SELECT remaining_yearly
    FROM budgets
    WHERE budget_type='governmental'
        AND owner_id=?
        AND code_id=?
        AND year=?
        AND monthly_amount = 0
    LIMIT 1
");
$stmt_yearly->execute([$owner_id, $code_id, $year]);
$remaining_yearly = (float)($stmt_yearly->fetchColumn() ?: 0.0);

echo json_encode([
    'remaining_monthly' => $remaining_monthly,
    'remaining_yearly' => $remaining_yearly
]);
?>