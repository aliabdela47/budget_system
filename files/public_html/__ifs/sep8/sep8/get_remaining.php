<?php
include 'includes/db.php';

// Get parameters (Compatible with PHP 5.x and later)
$owner_id = isset($_GET['owner_id']) ? $_GET['owner_id'] : null;
$code_id  = isset($_GET['code_id']) ? $_GET['code_id'] : null;
$month    = isset($_GET['month']) ? $_GET['month'] : null;
$year     = date('Y') - 8; // This line is fine and does not need to be changed

// ... rest of your code


$quarterMap = [
    'ሐምሌ'     => 1, 'ነሐሴ'   => 1, 'መስከረም' => 1,
    'ጥቅምት'   => 2, 'ህዳር'   => 2, 'ታኅሳስ'   => 2,
    'ጥር'       => 3, 'የካቲት' => 3, 'መጋቢት' => 3,
    'ሚያዝያ'   => 4, 'ግንቦት' => 4, 'ሰኔ'     => 4,
];
$quarter = $quarterMap[$month] ?? [span_13](start_span)0;[span_13](end_span)

// Initialize response
$response = [
    'remaining_month'   => 0,
    'remaining_quarter' => 0,
    'remaining_year'    => 0
];

if ($owner_id && $code_id && $month) {
    // Fetch monthly budget
    $stmt = $pdo->prepare("SELECT remaining_monthly FROM budgets WHERE owner_id = ? AND code_id  = ? AND year = ? AND month = ?");
    $stmt->execute([$owner_id, $code_id, $year, $month]);
    $monthly_rem = $stmt->fetchColumn();
    if ($monthly_rem !== false) {
        $response['remaining_month'] = (float)$monthly_rem;
    }

    // THIS IS THE CORRECT QUARTERLY LOGIC - IT SUMS THE MONTHLY REMAINDERS
    if ($quarter) {
        [span_14](start_span)$stmt = $pdo->prepare("SELECT SUM(remaining_monthly) AS total_quarter FROM budgets WHERE owner_id = ? AND code_id  = ? AND year = ? AND quarter  = ?");[span_14](end_span)
        [span_15](start_span)$stmt->execute([$owner_id, $code_id, $year, $quarter]);[span_15](end_span)
        $quarter_sum = $stmt->fetchColumn();
        $response['remaining_quarter'] = (float)$quarter_sum;
    }

    // Fetch yearly budget
    $stmt = $pdo->prepare("SELECT remaining_yearly FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0");
    $stmt->execute([$owner_id, $code_id, $year]);
    $yearly_rem = $stmt->fetchColumn();
     if ($yearly_rem !== false) {
        $response['remaining_year'] = (float)$yearly_rem;
    }
    
    // Fallback logic: if no monthly budget, the effective monthly/quarterly remainder is the yearly one.
    if($monthly_rem === false) {
        $response['remaining_month'] = (float)$yearly_rem;
        // If there's no monthly budget, the quarterly calculation should reflect the yearly as the limit
        if ($response['remaining_quarter'] == 0) {
             $response['remaining_quarter'] = (float)$yearly_rem;
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);