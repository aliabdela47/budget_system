<?php
include 'includes/db.php';

$owner_id = $_GET['owner_id'] ?? null;
$code_id = $_GET['code_id'] ?? null;
$month = $_GET['month'] ?? null;
$year = date('Y') - 8; // Ethiopian year adjustment

$quarterMap = [
    'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
    'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሳስ' => 2,
    'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
    'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4,
];
$quarter = $quarterMap[$month] ?? 0;

if ($owner_id && $code_id && $month) {
    // Fetch monthly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?");
    $stmt->execute([$owner_id, $code_id, $year, $month]);
    $budget = $stmt->fetch();

    // Fetch yearly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0");
    $stmt->execute([$owner_id, $code_id, $year]);
    $budget_yearly = $stmt->fetch();

    if ($budget_yearly || $budget) {
        $yearly_amount = $budget_yearly ? $budget_yearly['yearly_amount'] : 0;
        $monthly_amount = $budget ? $budget['monthly_amount'] : 0;

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND YEAR(date) = ?");
        $stmt->execute([$owner_id, $code_id, date('Y')]);
        $trans_year = $stmt->fetchColumn() ?: 0;
        $calculated_remaining_year = $yearly_amount - $trans_year;

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND et_month = ? AND YEAR(date) = ?");
        $stmt->execute([$owner_id, $code_id, $month, date('Y')]);
        $trans_month = $stmt->fetchColumn() ?: 0;
        $calculated_remaining_month = $monthly_amount - $trans_month;

        $stmt = $pdo->prepare("SELECT SUM(monthly_amount) FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND quarter = ?");
        $stmt->execute([$owner_id, $code_id, $year, $quarter]);
        $quarterly_alloc = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND quarter = ? AND YEAR(date) = ?");
        $stmt->execute([$owner_id, $code_id, $quarter, date('Y')]);
        $trans_quarter = $stmt->fetchColumn() ?: 0;
        $calculated_remaining_quarter = $quarterly_alloc - $trans_quarter;

        if (!$budget) {
            $calculated_remaining_month = $calculated_remaining_year;
        }

        if ($quarterly_alloc == 0) {
            $calculated_remaining_quarter = $calculated_remaining_year;
        }

        echo json_encode([
            'remaining_month' => $calculated_remaining_month,
            'remaining_quarter' => $calculated_remaining_quarter,
            'remaining_year' => $calculated_remaining_year
        ]);
    } else {
        echo json_encode(['remaining_month' => 0, 'remaining_quarter' => 0, 'remaining_year' => 0]);
    }
}