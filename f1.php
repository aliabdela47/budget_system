<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';
// if you guard by role, keep it; otherwise allow logged-in users as needed

// Make PDO throw exceptions (safer)
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

const FUEL_CODE_ID = 5; // Sansii kee Sukutih in budget_codes
$BUDGET_TYPE_PROGRAM = 'program'; // if your enum is 'programs', change this

// Ethiopian months + quarter mapping
if (!isset($etMonths) || !is_array($etMonths)) {
    $etMonths = ['Meskerem','Tikimt','Hidar','Tahsas','Tir','Yekatit','Megabit','Miazia','Ginbot','Sene','Hamle','Nehase'];
}
$quarterMap = [
    'Meskerem'=>1,'Tikimt'=>1,'Hidar'=>1,
    'Tahsas'=>2,'Tir'=>2,'Yekatit'=>2,
    'Megabit'=>3,'Miazia'=>3,'Ginbot'=>3,
    'Sene'=>4,'Hamle'=>4,'Nehase'=>4,
    'Pagume'=>4
];

// Helper: EC year from Gregorian date
function ecYearFromDate($gregDate) {
    if (function_exists('getEtMonthAndQuarter')) {
        $info = getEtMonthAndQuarter($gregDate);
        if (!empty($info['year'])) return (int)$info['year'];
    }
    return (int)date('Y', strtotime($gregDate)) - 8; // fallback approx
}

// Load form dropdown data
$owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll();
$plates = $pdo->query("SELECT DISTINCT plate_number FROM fuel_transactions WHERE plate_number IS NOT NULL AND plate_number<>'' ORDER BY plate_number")->fetchAll(PDO::FETCH_COLUMN);

// ================== JSON endpoints ==================

// Availability (cards)
if (isset($_GET['action']) && $_GET['action']==='availability') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $et_month    = $_GET['et_month'] ?? '';
        $date        = $_GET['date'] ?? date('Y-m-d');

        $year = ecYearFromDate($date);
        $resp = ['ok'=>true,'monthly'=>0,'yearly'=>0];

        if ($budget_type === $BUDGET_TYPE_PROGRAM) {
            if ($owner_id > 0) {
                // Programs: show yearly allocation for this owner (sum of yearly_amount for program budgets)
                $st = $pdo->prepare("SELECT COALESCE(SUM(yearly_amount),0) FROM budgets
                                     WHERE budget_type=? AND year=? AND monthly_amount=0 AND owner_id=?");
                $st->execute([$BUDGET_TYPE_PROGRAM, $year, $owner_id]);
                $resp['yearly'] = (float)$st->fetchColumn();
            } else {
                $resp['yearly'] = 0;
            }
        } else {
            // Government
            if ($owner_id > 0) {
                // Yearly (allocated for Fuel code only)
                $stY = $pdo->prepare("SELECT COALESCE(SUM(yearly_amount),0) FROM budgets
                                      WHERE budget_type='governmental' AND year=? AND monthly_amount=0
                                            AND owner_id=? AND code_id=?");
                $stY->execute([$year, $owner_id, FUEL_CODE_ID]);
                $resp['yearly'] = (float)$stY->fetchColumn();

                // Monthly (remaining for Fuel code + selected month)
                if (!empty($et_month)) {
                    $stM = $pdo->prepare("SELECT COALESCE(SUM(remaining_monthly),0) FROM budgets
                                          WHERE budget_type='governmental' AND year=? AND monthly_amount>0
                                                AND owner_id=? AND code_id=? AND month=?");
                    $stM->execute([$year, $owner_id, FUEL_CODE_ID, $et_month]);
                    $resp['monthly'] = (float)$stM->fetchColumn();
                } else {
                    $resp['monthly'] = 0;
                }
            } else {
                $resp['yearly'] = 0;
                $resp['monthly'] = 0;
            }
        }
        echo json_encode($resp);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Grand totals (purple cards)
if (isset($_GET['action']) && $_GET['action']==='grandTotals') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $date        = $_GET['date'] ?? date('Y-m-d');
        $year = ecYearFromDate($date);

        $resp = ['ok'=>true];

        if ($budget_type === $BUDGET_TYPE_PROGRAM) {
            // Bureau-wide programs yearly total (sum of yearly_amount)
            $st = $pdo->prepare("SELECT COALESCE(SUM(yearly_amount),0) FROM budgets
                                 WHERE budget_type=? AND year=? AND monthly_amount=0");
            $st->execute([$BUDGET_TYPE_PROGRAM, $year]);
            $resp['programsTotalYearly'] = (float)$st->fetchColumn();
        } else {
            // Government
            if ($owner_id > 0) {
                $st = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly),0) FROM budgets
                                     WHERE budget_type='governmental' AND year=? AND monthly_amount=0 AND owner_id=?");
                $st->execute([$year, $owner_id]);
                $resp['govtOwnerRemainingYearly'] = (float)$st->fetchColumn();
            } else {
                $st = $pdo->prepare("SELECT COALESCE(SUM(remaining_yearly),0) FROM budgets
                                     WHERE budget_type='governmental' AND year=? AND monthly_amount=0");
                $st->execute([$year]);
                $resp['govtBureauRemainingYearly'] = (float)$st->fetchColumn();
            }
        }
        echo json_encode($resp);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Transactions listing (filters without reload)
if (isset($_GET['action']) && $_GET['action']==='fuel_list') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $et_month    = $_GET['et_month'] ?? '';
        $plate       = trim($_GET['plate'] ?? '');

        $q = "SELECT ft.*, bo.name AS owner_name
              FROM fuel_transactions ft
              LEFT JOIN budget_owners bo ON bo.id = ft.owner_id
              WHERE ft.budget_type = ?";
        $p = [$budget_type];

        if ($owner_id > 0) { $q .= " AND ft.owner_id = ?"; $p[] = $owner_id; }
        if ($budget_type !== $BUDGET_TYPE_PROGRAM && !empty($et_month)) { $q .= " AND ft.et_month = ?"; $p[] = $et_month; }
        if (!empty($plate)) { $q .= " AND ft.plate_number = ?"; $p[] = $plate; }

        // Sort: plate filter → newest first
        $q .= " ORDER BY ft.date DESC, ft.id DESC LIMIT 500";
        $st = $pdo->prepare($q); $st->execute($p);
        $rows = $st->fetchAll();

        echo json_encode(['ok'=>true,'rows'=>$rows]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// ================== Add Transaction (POST) ==================
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_fuel_tx'])) {
    $budget_type     = ($_POST['budget_type'] ?? 'governmental') === $BUDGET_TYPE_PROGRAM ? $BUDGET_TYPE_PROGRAM : 'governmental';
    $owner_id        = (int)($_POST['owner_id'] ?? 0);
    $driver_name     = trim($_POST['driver_name'] ?? '');
    $plate_number    = trim($_POST['plate_number'] ?? '');
    $et_month        = $budget_type === $BUDGET_TYPE_PROGRAM ? '' : ($_POST['et_month'] ?? '');
    $previous_gauge  = (float)($_POST['previous_gauge'] ?? 0);
    $current_gauge   = (float)($_POST['current_gauge'] ?? 0);
    $journey_km      = (float)($_POST['journey_distance'] ?? 0);
    $fuel_price      = (float)($_POST['fuel_price'] ?? 0);
    $date            = $_POST['date'] ?? date('Y-m-d');

    $refuel_liters = $journey_km > 0 ? $journey_km / 5.0 : 0;
    $total_amount  = round($refuel_liters * $fuel_price, 2);
    $new_gauge     = $current_gauge + $journey_km;

    // Basic validations
    if ($owner_id<=0 || empty($plate_number) || $journey_km<=0 || $fuel_price<=0) {
        $message = 'Please fill all required fields (Owner, Plate, Journey, Price).';
    } else {
        $pdo->beginTransaction();
        try {
            // Gauge continuity: compare with last tx for that plate
            $stPrev = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number=? ORDER BY date DESC, id DESC LIMIT 1");
            $stPrev->execute([$plate_number]);
            $last_new = $stPrev->fetchColumn();
            if ($last_new !== false && $current_gauge < (float)$last_new) {
                throw new Exception("Gauge continuity error: current gauge {$current_gauge} is below last expected ".(float)$last_new);
            }

            $year = ecYearFromDate($date);

            if ($budget_type === $BUDGET_TYPE_PROGRAM) {
                // Programs: deduct from program yearly remaining (owner-level, not by code)
                // Check total remaining
                $stSum = $pdo->prepare("SELECT id, remaining_yearly FROM budgets
                                        WHERE budget_type=? AND year=? AND monthly_amount=0 AND owner_id=?
                                        ORDER BY id ASC FOR UPDATE");
                $stSum->execute([$BUDGET_TYPE_PROGRAM, $year, $owner_id]);
                $rows = $stSum->fetchAll();
                $totalRem = array_sum(array_map(fn($r)=> (float)$r['remaining_yearly'], $rows));
                if ($total_amount > $totalRem) {
                    throw new Exception("Insufficient yearly program budget. Available ".number_format($totalRem,2)." ብር");
                }
                // Deduct across rows
                $left = $total_amount;
                foreach ($rows as $r) {
                    if ($left <= 0) break;
                    $avail = (float)$r['remaining_yearly'];
                    $use = min($avail, $left);
                    $newRem = $avail - $use;
                    $upd = $pdo->prepare("UPDATE budgets SET remaining_yearly=? WHERE id=?");
                    $upd->execute([$newRem, (int)$r['id']]);
                    $left -= $use;
                }
                // Insert fuel tx
                $ins = $pdo->prepare("INSERT INTO fuel_transactions
                    (budget_type, owner_id, driver_name, plate_number, et_month, previous_gauge, current_gauge, journey_distance,
                     fuel_price, refuelable_amount, total_amount, new_gauge, gauge_gap, date)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $ins->execute([
                    $BUDGET_TYPE_PROGRAM, $owner_id, $driver_name, $plate_number, '', $previous_gauge, $current_gauge, $journey_km,
                    $fuel_price, $refuel_liters, $total_amount, $new_gauge, 0, $date
                ]);

                $pdo->commit();
                $message = 'Program fuel transaction added successfully.';
            } else {
                // Government: check monthly and yearly; deduct from monthly (remaining_monthly) and yearly (remaining_yearly) for Fuel code
                if (empty($et_month)) throw new Exception('Please select Ethiopian Month for Government transaction.');

                // Yearly remaining (gov + fuel)
                $stY = $pdo->prepare("SELECT id, remaining_yearly FROM budgets
                                      WHERE budget_type='governmental' AND year=? AND monthly_amount=0
                                            AND owner_id=? AND code_id=? FOR UPDATE");
                $stY->execute([$year, $owner_id, FUEL_CODE_ID]);
                $yr = $stY->fetch();
                if (!$yr) throw new Exception('No Yearly Fuel budget found for this owner.');
                if ($total_amount > (float)$yr['remaining_yearly']) {
                    throw new Exception('Insufficient yearly fuel budget. Available '.number_format((float)$yr['remaining_yearly'],2).' ብር');
                }

                // Monthly remaining (gov + fuel + month)
                $stM = $pdo->prepare("SELECT id, remaining_monthly FROM budgets
                                      WHERE budget_type='governmental' AND year=? AND monthly_amount>0
                                            AND owner_id=? AND code_id=? AND month=? ORDER BY id ASC FOR UPDATE");
                $stM->execute([$year, $owner_id, FUEL_CODE_ID, $et_month]);
                $monthlyRows = $stM->fetchAll();
                $monthlyTotal = array_sum(array_map(fn($r)=> (float)$r['remaining_monthly'], $monthlyRows));
                if ($total_amount > $monthlyTotal) {
                    throw new Exception('Insufficient monthly fuel budget. Available '.number_format($monthlyTotal,2).' ብር');
                }

                // Deduct monthly
                $left = $total_amount;
                foreach ($monthlyRows as $r) {
                    if ($left <= 0) break;
                    $avail = (float)$r['remaining_monthly'];
                    $use = min($avail, $left);
                    $newRem = $avail - $use;
                    $upd = $pdo->prepare("UPDATE budgets SET remaining_monthly=? WHERE id=?");
                    $upd->execute([$newRem, (int)$r['id']]);
                    $left -= $use;
                }

                // Deduct yearly
                $updY = $pdo->prepare("UPDATE budgets SET remaining_yearly=? WHERE id=?");
                $updY->execute([(float)$yr['remaining_yearly'] - $total_amount, (int)$yr['id']]);

                // Insert fuel tx
                $ins = $pdo->prepare("INSERT INTO fuel_transactions
                    (budget_type, owner_id, driver_name, plate_number, et_month, previous_gauge, current_gauge, journey_distance,
                     fuel_price, refuelable_amount, total_amount, new_gauge, gauge_gap, date)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $ins->execute([
                    'governmental', $owner_id, $driver_name, $plate_number, $et_month, $previous_gauge, $current_gauge, $journey_km,
                    $fuel_price, $refuel_liters, $total_amount, $new_gauge, 0, $date
                ]);

                $pdo->commit();
                $message = 'Government fuel transaction added successfully.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: '.$e->getMessage();
        }
    }
}

// ================== HTML starts ==================
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Fuel Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#4f46e5',secondary:'#7c3aed',accent:'#06b6d4'}}}};</script>
  <link rel="stylesheet" href="css/all.min.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f1f5f9 0%,#e2e8f0 100%);min-height:100vh;color:#334155;}
    .card-hover{transition:all .3s ease; box-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);}
    .card-hover:hover{transform:translateY(-3px); box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);}
    .input-group{transition:all .3s ease;border:1px solid #d1d5db;border-radius:.5rem;padding:.5rem .75rem;display:flex;align-items:center;background:#fff}
    .input-group:focus-within{transform:translateY(-2px);border-color:#4f46e5;box-shadow:0 0 0 1px #4f46e5;}
    input,select{outline:none;width:100%;background:transparent;}
    .btn{padding:.6rem 1rem;border-radius:.5rem;font-weight:500;border:none;cursor:pointer}
    .btn-primary{background:#4f46e5;color:#fff}.btn-primary:hover{background:#4338ca}
    .btn-secondary{background:#6b7280;color:#fff}.btn-secondary:hover{background:#4b5563}
    .btn-danger{background:#ef4444;color:#fff}.btn-danger:hover{background:#dc2626}
    .btn-info{background:#06b6d4;color:#fff}.btn-info:hover{background:#0891b2}
  </style>
</head>
<body class="text-slate-700 flex">
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="p-5">
      <div class="flex items-center justify-center mb-8">
        <i class="fas fa-gas-pump text-amber-300 text-3xl mr-3"></i>
        <h2 class="text-xl font-bold text-white">Fuel Management</h2>
      </div>
      <ul class="space-y-2">
        <li><a href="dashboard.php" class="flex items-center p-3 text-base rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-tachometer-alt w-5"></i><span class="ml-3">Dashboard</span></a></li>
        <li><a href="budget_adding.php" class="flex items-center p-3 text-base rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-wallet w-5"></i><span class="ml-3">Budget Adding</span></a></li>
        <li><a href="fuel_management.php" class="flex items-center p-3 text-base rounded-lg text-white bg-white/20"><i class="fas fa-gas-pump w-5"></i><span class="ml-3">Fuel</span></a></li>
        <li><a href="transaction.php" class="flex items-center p-3 text-base rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-exchange-alt w-5"></i><span class="ml-3">Transactions</span></a></li>
        <li><a href="settings_owners.php" class="flex items-center p-3 text-base rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-cog w-5"></i><span class="ml-3">Settings</span></a></li>
        <li><a href="users_management.php" class="flex items-center p-3 text-base rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-users w-5"></i><span class="ml-3">Users</span></a></li>
      </ul>
    </div>
  </div>

  <!-- Main -->
  <div class="main-content" id="mainContent">
    <div class="p-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 p-6 bg-white rounded-xl shadow-sm">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Fuel Management</h1>
          <p class="text-slate-600 mt-2">Process fuel transactions for Government or Programs budgets</p>
        </div>
        <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle"><i class="fas fa-bars"></i></button>
      </div>

      <?php if (isset($message)): ?>
        <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6"><i class="fas fa-info-circle mr-2"></i><?php echo htmlspecialchars($message); ?></div>
      <?php endif; ?>

      <!-- Fuel Form -->
      <div class="bg-white rounded-xl p-6 card-hover mb-8">
        <h2 class="text-lg font-semibold text-slate-800 mb-4">Add Fuel Transaction</h2>
        <form method="post" class="space-y-4" id="fuelForm">
          <input type="hidden" name="add_fuel_tx" value="1">

          <div class="grid md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Budget Source Type</label>
              <select name="budget_type" id="budget_type" class="input-group" onchange="toggleType(); refreshAvailability(); refreshGrandTotals(); fetchFuelList();">
                <option value="governmental">Government Budget</option>
                <option value="<?php echo $BUDGET_TYPE_PROGRAM; ?>">Programs Budget</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Budget Owner</label>
              <select name="owner_id" id="owner_id" class="input-group" required onchange="refreshAvailability(); refreshGrandTotals(); fetchFuelList();">
                <option value="">-- Select Owner --</option>
                <?php foreach ($owners as $o): ?>
                  <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Date</label>
              <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" class="input-group" onchange="refreshAvailability(); refreshGrandTotals();">
            </div>
          </div>

          <div class="grid md:grid-cols-3 gap-4" id="month_row">
            <div>
              <label class="block text-sm font-medium mb-1">Ethiopian Month (EC)</label>
              <select name="et_month" id="et_month" class="input-group" onchange="refreshAvailability(); fetchFuelList();">
                <option value="">-- Select Month --</option>
                <?php foreach ($etMonths as $m): ?>
                  <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Plate Number</label>
              <input name="plate_number" id="plate_number" class="input-group" placeholder="e.g., A12345">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Driver Name</label>
              <input name="driver_name" id="driver_name" class="input-group" placeholder="Driver full name">
            </div>
          </div>

          <div class="grid md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Previous Gauge</label>
              <input type="number" step="0.01" name="previous_gauge" id="previous_gauge" class="input-group" value="0">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Current Gauge</label>
              <input type="number" step="0.01" name="current_gauge" id="current_gauge" class="input-group" value="0">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Journey Distance (Km)</label>
              <input type="number" step="0.01" name="journey_distance" id="journey_distance" class="input-group" value="0" oninput="calcFuel();">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Fuel Price / L</label>
              <input type="number" step="0.01" name="fuel_price" id="fuel_price" class="input-group" value="0" oninput="calcFuel();">
            </div>
          </div>

          <div class="grid md:grid-cols-4 gap-4">
            <div class="rounded-xl p-4 bg-gradient-to-r from-sky-100 to-sky-50 border border-sky-200 shadow-sm">
              <div class="text-sm text-sky-700 font-medium">Refuelable Amount (L)</div>
              <div id="liters" class="text-2xl font-extrabold text-sky-900 mt-1">0.000</div>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-emerald-100 to-emerald-50 border border-emerald-200 shadow-sm">
              <div class="text-sm text-emerald-700 font-medium">Total Amount</div>
              <div id="total_amount" class="text-2xl font-extrabold text-emerald-900 mt-1">0.00 ብር</div>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="text-sm text-amber-700 font-medium">New Gauge</div>
              <div id="new_gauge" class="text-2xl font-extrabold text-amber-900 mt-1">0.00</div>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-rose-100 to-rose-50 border border-rose-200 shadow-sm">
              <div class="text-sm text-rose-700 font-medium">Gauge Gap</div>
              <div id="gauge_gap" class="text-2xl font-extrabold text-rose-900 mt-1">0.00</div>
            </div>
          </div>

          <!-- Availability + Grand totals -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Monthly (Gov only) -->
            <div id="avail_monthly_card" class="rounded-xl p-5 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-amber-200 text-amber-800"><i class="fas fa-calendar-alt"></i></div>
                <div>
                  <div class="text-sm text-amber-700 font-medium">Monthly Fuel Budget</div>
                  <div id="avail_monthly" class="text-2xl font-extrabold text-amber-900 mt-1">—</div>
                </div>
              </div>
            </div>
            <!-- Yearly -->
            <div class="rounded-xl p-5 bg-gradient-to-r from-emerald-100 to-emerald-50 border border-emerald-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-emerald-200 text-emerald-800"><i class="fas fa-coins"></i></div>
                <div>
                  <div class="text-sm text-emerald-700 font-medium" id="yearly_label">Available Yearly Budget</div>
                  <div id="avail_yearly" class="text-2xl font-extrabold text-emerald-900 mt-1">—</div>
                </div>
              </div>
            </div>
            <!-- Programs/Bureau Grand (right, only when Programs + owner not selected) -->
            <div id="programs_total_card" class="rounded-xl p-5 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-layer-group"></i></div>
                <div>
                  <div class="text-sm text-purple-700 font-medium">Bureau’s Programs Total Budget</div>
                  <div id="programs_total_amount" class="text-2xl font-extrabold text-purple-900 mt-1">—</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Government Grand (below) -->
          <div id="government_grand_card" class="rounded-xl p-5 mt-4 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
            <div class="flex items-center gap-3">
              <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-building"></i></div>
              <div>
                <div id="gov_grand_label" class="text-sm text-purple-700 font-medium">Bureau’s Yearly Government Budget</div>
                <div id="gov_grand_amount" class="text-2xl font-extrabold text-purple-900 mt-1">—</div>
              </div>
            </div>
          </div>

          <div class="flex gap-3 mt-4">
            <button type="submit" class="btn btn-primary">Add Transaction</button>
            <button type="button" class="btn btn-info" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
          </div>
        </form>
      </div>

      <!-- Filters for list -->
      <div class="bg-white rounded-xl p-4 mb-3">
        <div class="grid md:grid-cols-4 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Filter: Budget Source</label>
            <select id="f_type" class="input-group" onchange="syncFilterType(); fetchFuelList();">
              <option value="governmental">Government</option>
              <option value="<?php echo $BUDGET_TYPE_PROGRAM; ?>">Programs</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Filter: Owner</label>
            <select id="f_owner" class="input-group" onchange="fetchFuelList();">
              <option value="">-- Any Owner --</option>
              <?php foreach ($owners as $o): ?>
                <option value="<?php echo (int)$o['id']; ?>"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="f_month_box">
            <label class="block text-sm font-medium mb-1">Filter: Month (Gov only)</label>
            <select id="f_month" class="input-group" onchange="fetchFuelList();">
              <option value="">-- Any Month --</option>
              <?php foreach ($etMonths as $m): ?>
                <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Filter: Plate Number</label>
            <select id="f_plate" class="input-group" onchange="fetchFuelList();">
              <option value="">-- Any Plate --</option>
              <?php foreach ($plates as $p): ?>
                <option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Transactions List -->
      <div class="bg-white rounded-xl p-6 card-hover">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-xl font-bold text-slate-800">Fuel Transactions</h2>
            <p class="text-slate-500 text-sm">Live filtered by Budget Source, Owner, Month, and Plate</p>
          </div>
          <div class="text-sm text-slate-500" id="fuel_count">Loading…</div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs uppercase bg-slate-100 text-slate-700">
              <tr>
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Owner</th>
                <th class="px-4 py-3">Plate</th>
                <th class="px-4 py-3">Driver</th>
                <th class="px-4 py-3">Month</th>
                <th class="px-4 py-3 text-right">Liters</th>
                <th class="px-4 py-3 text-right">Price/L</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody id="fuel_tbody">
              <tr><td class="px-4 py-3" colspan="11">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script>
    const fmt = n => (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2});
    const birr = n => `${fmt(n)} ብር`;

    function calcFuel(){
      const km  = parseFloat(document.getElementById('journey_distance').value||'0');
      const ppl = parseFloat(document.getElementById('fuel_price').value||'0');
      const liters = km>0 ? km/5 : 0;
      const total  = liters * ppl;
      const curr   = parseFloat(document.getElementById('current_gauge').value||'0');
      const prev   = parseFloat(document.getElementById('previous_gauge').value||'0');
      const newG   = curr + km;
      const gap    = newG - curr;

      document.getElementById('liters').textContent = liters.toFixed(3);
      document.getElementById('total_amount').textContent = birr(total);
      document.getElementById('new_gauge').textContent = newG.toFixed(2);
      document.getElementById('gauge_gap').textContent = gap.toFixed(2);
    }

    function toggleType(){
      const type = document.getElementById('budget_type').value;
      const monthRow = document.getElementById('month_row');
      const availMonthlyCard = document.getElementById('avail_monthly_card');
      const yearlyLabel = document.getElementById('yearly_label');
      const fMonthBox = document.getElementById('f_month_box');

      if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
        if (monthRow) monthRow.style.display = 'none';
        if (availMonthlyCard) availMonthlyCard.style.display = 'none';
        if (fMonthBox) fMonthBox.style.display = 'none';
        yearlyLabel.textContent = 'Available Yearly Budget';
      } else {
        if (monthRow) monthRow.style.display = 'grid';
        if (availMonthlyCard) availMonthlyCard.style.display = 'block';
        if (fMonthBox) fMonthBox.style.display = 'block';
        yearlyLabel.textContent = 'Available Yearly Fuel Budget';
      }
    }

    function syncFilterType(){
      const v = document.getElementById('f_type').value;
      document.getElementById('budget_type').value = v;
      toggleType();
      refreshAvailability();
      refreshGrandTotals();
    }

    async function refreshAvailability(){
      const type    = document.getElementById('budget_type').value;
      const ownerId = document.getElementById('owner_id').value;
      const etMonth = document.getElementById('et_month')?.value || '';
      const date    = document.getElementById('date').value;

      if (!ownerId) {
        document.getElementById('avail_monthly').textContent = '—';
        document.getElementById('avail_yearly').textContent = '—';
        return;
      }

      const url = new URL(window.location.href);
      url.searchParams.set('action','availability');
      url.searchParams.set('budget_type',type);
      url.searchParams.set('owner_id',ownerId);
      url.searchParams.set('et_month',etMonth);
      url.searchParams.set('date',date);

      try{
        const r = await fetch(url.toString(), {cache:'no-store'});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error||'fetch failed');

        if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
          document.getElementById('avail_yearly').textContent = birr(j.yearly||0);
        } else {
          document.getElementById('avail_yearly').textContent = birr(j.yearly||0); // yearly allocated for fuel
          document.getElementById('avail_monthly').textContent = birr(j.monthly||0);
        }
      }catch(e){
        document.getElementById('avail_monthly').textContent = '—';
        document.getElementById('avail_yearly').textContent = '—';
      }
    }

    async function refreshGrandTotals(){
      const type    = document.getElementById('budget_type').value;
      const ownerId = document.getElementById('owner_id').value;
      const date    = document.getElementById('date').value;

      const url = new URL(window.location.href);
      url.searchParams.set('action','grandTotals');
      url.searchParams.set('budget_type',type);
      url.searchParams.set('owner_id',ownerId);
      url.searchParams.set('date',date);

      const progCard = document.getElementById('programs_total_card');
      const progAmt  = document.getElementById('programs_total_amount');
      const govCard  = document.getElementById('government_grand_card');
      const govAmt   = document.getElementById('gov_grand_amount');
      const govLbl   = document.getElementById('gov_grand_label');

      try{
        const r = await fetch(url.toString(), {cache:'no-store'});
        const j = await r.json();

        if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
          // Show Bureau Programs total only when owner not selected
          if (!ownerId) {
            progCard.style.display='block';
            progAmt.textContent = birr(j.programsTotalYearly||0);
          } else {
            progCard.style.display='none';
          }
          govCard.style.display='none';
        } else {
          progCard.style.display='none';
          if (!ownerId) {
            govCard.style.display='block';
            govLbl.textContent = "Bureau’s Yearly Government Budget";
            govAmt.textContent = birr(j.govtBureauRemainingYearly||0);
          } else if (ownerId) {
            govCard.style.display='block';
            // Find owner name for label
            const ownerSel = document.getElementById('owner_id');
            const ownerName = ownerSel.selectedIndex>0 ? ownerSel.options[ownerSel.selectedIndex].text.split(' - ').slice(1).join(' - ') : 'Selected Owner';
            govLbl.textContent = `${ownerName}'s Total Yearly Budget (Grand Yearly Budget)`;
            govAmt.textContent = birr(j.govtOwnerRemainingYearly||0);
          } else {
            govCard.style.display='none';
          }
        }
      }catch(e){
        progCard.style.display='none';
        govCard.style.display='none';
      }
    }

    async function fetchFuelList(){
      const type  = document.getElementById('budget_type').value;
      const owner = document.getElementById('owner_id').value;
      const month = document.getElementById('et_month')?.value || '';
      const plate = document.getElementById('f_plate')?.value || '';

      const url = new URL(window.location.href);
      url.searchParams.set('action','fuel_list');
      url.searchParams.set('budget_type',type);
      if (owner) url.searchParams.set('owner_id',owner); else url.searchParams.delete('owner_id');
      if (month && type!=='<?php echo $BUDGET_TYPE_PROGRAM; ?>') url.searchParams.set('et_month',month); else url.searchParams.delete('et_month');
      if (plate) url.searchParams.set('plate',plate); else url.searchParams.delete('plate');

      const tbody = document.getElementById('fuel_tbody');
      const count = document.getElementById('fuel_count');
      tbody.innerHTML = `<tr><td class="px-4 py-3" colspan="11">Loading…</td></tr>`;
      count.textContent = 'Loading…';

      try{
        const r = await fetch(url.toString(), {cache:'no-store'});
        const j = await r.json();
        if (!j.ok) throw new Error(j.error||'fetch failed');

        const rows = j.rows||[];
        count.textContent = rows.length + ' row(s)';

        if (rows.length===0) {
          tbody.innerHTML = `<tr><td class="px-4 py-3" colspan="11">No data for current filter.</td></tr>`;
          return;
        }

        let html='';
        rows.forEach(row=>{
          const report = row.budget_type==='governmental' ? 'fuel_transaction_report.php' : 'fuel_transaction_report2.php';
          html += `
            <tr class="border-b border-slate-200 hover:bg-slate-50">
              <td class="px-4 py-2 font-medium">${row.id}</td>
              <td class="px-4 py-2">${row.budget_type}</td>
              <td class="px-4 py-2">${row.owner_name || ''}</td>
              <td class="px-4 py-2">${row.plate_number || ''}</td>
              <td class="px-4 py-2">${row.driver_name || ''}</td>
              <td class="px-4 py-2">${row.et_month || ''}</td>
              <td class="px-4 py-2 text-right">${fmt(row.refuelable_amount||0)}</td>
              <td class="px-4 py-2 text-right">${fmt(row.fuel_price||0)}</td>
              <td class="px-4 py-2 text-right">${fmt(row.total_amount||0)} ብር</td>
              <td class="px-4 py-2">${(row.date||'').slice(0,19).replace('T',' ')}</td>
              <td class="px-4 py-2">
                <div class="flex gap-2">
                  <a href="${report}?id=${row.id}" class="btn btn-info btn-sm">Print</a>
                  <!-- keep your existing edit/delete if any -->
                </div>
              </td>
            </tr>
          `;
        });
        tbody.innerHTML = html;
      }catch(e){
        tbody.innerHTML = `<tr><td class="px-4 py-3" colspan="11">Failed to load.</td></tr>`;
        count.textContent = 'Error';
      }
    }

    // Initial hooks
    document.addEventListener('DOMContentLoaded', ()=>{
      document.getElementById('f_type').value = document.getElementById('budget_type').value;
      toggleType();
      calcFuel();
      refreshAvailability();
      refreshGrandTotals();
      fetchFuelList();
    });

    // UI utility
    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });
  </script>
</body>
</html>