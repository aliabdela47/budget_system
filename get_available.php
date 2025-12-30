<?php
include 'includes/db.php';
include 'includes/functions.php';

$owner_id = $_GET['owner_id'] ?? null;
$code_id = $_GET['code_id'] ?? null;
$et_month = $_GET['et_month'] ?? null;
if (!$owner_id || !$code_id || !$et_month) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$quarterMap = [
    'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
    'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሳስ' => 2,
    'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
    'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4,
];
$quarter = $quarterMap[$et_month] ?? 0;
$year = date('Y') - 8;

$stmt = $pdo->prepare("SELECT yearly_amount FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0");
$stmt->execute([$owner_id, $code_id, $year]);
$yearly_amount = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND YEAR(date) = ?");
$stmt->execute([$owner_id, $code_id, date('Y')]);
$trans_year = $stmt->fetchColumn() ?: 0;
$remaining_year = $yearly_amount - $trans_year;

$months_in_quarter = array_keys(array_filter($quarterMap, fn($q) => $q == $quarter));
$stmt = $pdo->prepare("SELECT SUM(monthly_amount) FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND quarter = ?");
$stmt->execute([$owner_id, $code_id, $year, $quarter]);
$quarterly_alloc = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND quarter = ?");
$stmt->execute([$owner_id, $code_id, $quarter]);
$trans_quarter = $stmt->fetchColumn() ?: 0;
$remaining_quarter = $quarterly_alloc - $trans_quarter;

$stmt = $pdo->prepare("SELECT monthly_amount FROM budgets WHERE owner_id = ? AND code_id = ? AND month = ? AND year = ?");
$stmt->execute([$owner_id, $code_id, $et_month, $year]);
$monthly_amount = $stmt->fetchColumn() ?: 0;
$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND et_month = ?");
$stmt->execute([$owner_id, $code_id, $et_month]);
$trans_month = $stmt->fetchColumn() ?: 0;
$remaining_month = $monthly_amount - $trans_month;

echo json_encode([
    'monthly' => number_format($remaining_month, 2),
    'quarterly' => number_format($remaining_quarter, 2),
    'yearly' => number_format($remaining_year, 2)
]);
?>