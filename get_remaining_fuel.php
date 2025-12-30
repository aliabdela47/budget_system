<?php
require_once 'includes/init.php';
header('Content-Type: application/json');

/**
 * Normalize any EC month string (Amharic, English, or "1".."13") to its numeric 1..13 form.
 */
function ecMonthNoFromString($m) {
  if ($m === null) return null;
  $m = trim((string)$m);
  if ($m === '') return null;
  if (ctype_digit($m)) {
    $n = (int)$m;
    if ($n >= 1 && $n <= 13) return $n;
  }
  static $map = [
    // English transliterations
    'MESKEREM'=>1,'TIKIMT'=>2,'HIDAR'=>3,'TAHSAS'=>4,'TIR'=>5,'YEKATIT'=>6,'MEGABIT'=>7,'MIAZIA'=>8,'GINBOT'=>9,'SENE'=>10,'HAMLE'=>11,'NEHASE'=>12,'PAGUME'=>13,
    // Amharic
    'መስከረም'=>1,'ጥቅምት'=>2,'ህዳር'=>3,'ታኅሳስ'=>4,'ጥር'=>5,'የካቲት'=>6,'መጋቢት'=>7,'ሚያዝያ'=>8,'ግንቦት'=>9,'ሰኔ'=>10,'ሐምሌ'=>11,'ነሐሴ'=>12,'ጳጉሜ'=>13,'ጳግሜ'=>13
  ];
  $u = strtoupper(preg_replace('/\s+/', '', $m));
  return $map[$u] ?? null;
}

$owner_id = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;
$month    = $_GET['month'] ?? null;
$year     = isset($_GET['year'])     ? (int)$_GET['year']     : 0;

if ($owner_id <= 0 || !$month || $year <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing parameters']);
  exit;
}

$code_id = 5; // Fuel
$ecm = ecMonthNoFromString($month);

// Monthly (by ec_month)
$monthly = ['remaining_monthly' => 0, 'remaining_quarterly' => 0];
if ($ecm !== null) {
  $stmt = $pdo->prepare("
    SELECT remaining_monthly, remaining_quarterly
    FROM budgets
    WHERE budget_type='governmental'
      AND owner_id=?
      AND code_id=?
      AND year=?
      AND ec_month=?
      AND monthly_amount > 0
    LIMIT 1
  ");
  $stmt->execute([$owner_id, $code_id, $year, $ecm]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $monthly['remaining_monthly']   = (float)$row['remaining_monthly'];
    $monthly['remaining_quarterly'] = (float)$row['remaining_quarterly'];
  }
}

// Yearly (monthly_amount=0)
$stmt = $pdo->prepare("
  SELECT remaining_yearly
  FROM budgets
  WHERE budget_type='governmental'
    AND owner_id=?
    AND code_id=?
    AND year=?
    AND monthly_amount=0
  LIMIT 1
");
$stmt->execute([$owner_id, $code_id, $year]);
$yearly = (float)($stmt->fetchColumn() ?: 0.0);

echo json_encode([
  'remaining_monthly'   => $monthly['remaining_monthly'],
  'remaining_quarterly' => $monthly['remaining_quarterly'],
  'remaining_yearly'    => $yearly
]);