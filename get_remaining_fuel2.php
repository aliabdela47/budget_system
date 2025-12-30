<?php
include 'includes/db.php';

// Get parameters
$owner_id = isset($_GET['owner_id']) ? $_GET['owner_id'] : null;
$code_id = isset($_GET['code_id']) ? $_GET['code_id'] : null;
$month = isset($_GET['month']) ? $_GET['month'] : null;
$year = isset($_GET['year']) ? $_GET['year'] : (date('Y') - 8); // Ethiopian year

// Map Ethiopian months to quarters
$quarterMap = [
    'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
    'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሳስ' => 2,
    'ጥር' => 3, 'የካቲት' => 3, 'መጋቢት' => 3,
    'ሚያዝያ' => 4, 'ግንቦት' => 4, 'ሰኔ' => 4,
];

// Initialize response
$response = [
    'remaining_monthly' => 0,
    'remaining_quarterly' => 0,
    'remaining_yearly' => 0
];

if ($owner_id && $code_id && $month && $year) {
    // Determine the quarter from the month
    $quarter = isset($quarterMap[$month]) ? $quarterMap[$month] : 0;
    
    // Fetch monthly budget for the specific month
    $stmt = $pdo->prepare("
        SELECT remaining_monthly 
        FROM budgets 
        WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?
    ");
    $stmt->execute([$owner_id, $code_id, $year, $month]);
    $monthly_rem = $stmt->fetchColumn();
    
    if ($monthly_rem !== false) {
        $response['remaining_monthly'] = (float)$monthly_rem;
    }

    // Calculate quarterly total by summing monthly remainders for all months in the same quarter
    if ($quarter) {
        $stmt = $pdo->prepare("
            SELECT SUM(remaining_monthly) 
            FROM budgets 
            WHERE owner_id = ? AND code_id = ? AND year = ? AND quarter = ?
        ");
        $stmt->execute([$owner_id, $code_id, $year, $quarter]);
        $quarterly_sum = $stmt->fetchColumn();
        
        if ($quarterly_sum !== false) {
            $response['remaining_quarterly'] = (float)$quarterly_sum;
        }
    }

    // Fetch yearly budget
    $stmt = $pdo->prepare("
        SELECT remaining_yearly 
        FROM budgets 
        WHERE owner_id = ? AND code_id = ? AND year = ?
    ");
    $stmt->execute([$owner_id, $code_id, $year]);
    $yearly_rem = $stmt->fetchColumn();
    
    if ($yearly_rem !== false) {
        $response['remaining_yearly'] = (float)$yearly_rem;
    }
    
    // Fallback logic: if no monthly budget, use yearly as monthly/quarterly
    if ($monthly_rem === false) {
        $response['remaining_monthly'] = (float)$yearly_rem;
        $response['remaining_quarterly'] = (float)$yearly_rem;
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>