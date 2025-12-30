<?php
require_once 'db.php'; // Your PDO connection

// Get parameters
$owner_id = $_GET['owner_id'] ?? null;
$code_id  = $_GET['code_id']  ?? null;
$year     = $_GET['year']     ?? null;
$month    = $_GET['month']    ?? null;

// Validate input
if (!$owner_id || !$code_id || !$year || !$month) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Get quarter from month (Ethiopian calendar logic)
function getQuarterFromMonth($month) {
    $quarters = [
        'ሐምሌ'     => 1, 'ነሐሴ'   => 1, 'መስከረም' => 1,
        'ጥቅምት'   => 2, 'ኅዳር'   => 2, 'ታህሳስ' => 2,
        'ጥር'      => 3, 'የካቲት' => 3, 'መጋቢት' => 3,
        'ሚያዝያ'   => 4, 'ግንቦት' => 4, 'ሰኔ'    => 4
    ];
    return $quarters[$month] ?? null;
}

$quarter = getQuarterFromMonth($month);

// Fetch monthly budget
$stmt = $pdo->prepare("
    SELECT remaining_monthly, remaining_yearly
      FROM budgets
     WHERE owner_id = ?
       AND code_id  = ?
       AND year     = ?
       AND month    = ?
     LIMIT 1
");
$stmt->execute([$owner_id, $code_id, $year, $month]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

// Monthly fallback
$remaining_month = $budget['remaining_monthly'] ?? 0;
$remaining_year  = $budget['remaining_yearly']  ?? 0;

// Fetch quarterly budget (sum of actual allocations)
$remaining_quarter = 0;
if ($quarter) {
    $stmt = $pdo->prepare("
        SELECT SUM(remaining_monthly)
          FROM budgets
         WHERE owner_id = ?
           AND code_id  = ?
           AND year     = ?
           AND quarter  = ?
           AND monthly_amount > 0
    ");
    $stmt->execute([$owner_id, $code_id, $year, $quarter]);
    $remaining_quarter = $stmt->fetchColumn() ?: 0;
}

// Return response
echo json_encode([
    'remaining_month'   => $remaining_month,
    'remaining_quarter' => $remaining_quarter,
    'remaining_year'    => $remaining_year
]);