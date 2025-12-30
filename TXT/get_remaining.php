<?php
include 'includes/db.php';

// Get parameters
$owner_id = $_GET['owner_id'] ?? null;
$code_id  = $_GET['code_id'] ?? null;
$month    = $_GET['month'] ?? null;
$year     = date('Y') - 8; // Ethiopian fiscal year

// Ethiopian month to quarter mapping
$quarterMap = [
    'ሐምሌ'     => 1, 'ነሐሴ'   => 1, 'መስከረም' => 1,
    'ጥቅምት'   => 2, 'ህዳር'   => 2, 'ታኅሳስ'   => 2,
    'ጥር'       => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
    'ሚያዝያ'   => 4, 'ግንቦቷ' => 4, 'ሰኔ'     => 4,
];
$quarter = $quarterMap[$month] ?? 0;

// Initialize response
$response = [
    'remaining_month'   => 0,
    'remaining_quarter' => 0,
    'remaining_year'    => 0
];

// Proceed only if all required parameters are present
if ($owner_id && $code_id && $month) {
    // Fetch monthly budget row
    $stmt = $pdo->prepare("
        SELECT remaining_monthly, remaining_yearly
          FROM budgets
         WHERE owner_id = ?
           AND code_id  = ?
           AND year     = ?
           AND month    = ?
         ORDER BY id DESC
         LIMIT 1
    ");
    $stmt->execute([$owner_id, $code_id, $year, $month]);
    $monthly_budget = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($monthly_budget) {
        $response['remaining_month'] = (float)$monthly_budget['remaining_monthly'];
        $response['remaining_year']  = (float)$monthly_budget['remaining_yearly'];
    }

    // Fetch quarterly budget by summing actual monthly allocations
    if ($quarter) {
        $stmt = $pdo->prepare("
            SELECT SUM(remaining_monthly) AS total_quarter
              FROM budgets
             WHERE owner_id = ?
               AND code_id  = ?
               AND year     = ?
               AND quarter  = ?
               AND monthly_amount > 0
        ");
        $stmt->execute([$owner_id, $code_id, $year, $quarter]);
        $quarter_sum = $stmt->fetchColumn();
        $response['remaining_quarter'] = (float)$quarter_sum;
    }
}

// Return JSON response
echo json_encode($response);