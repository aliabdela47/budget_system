<?php
require_once 'includes/init.php';

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_check($t) { return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// Roles
$is_admin   = (($_SESSION['role'] ?? '') === 'admin');
$is_officer = (($_SESSION['role'] ?? '') === 'officer');

// Helpers
function ecYear(): int { return (int)date('Y') - 8; }
function monthsEC(): array { return ['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ']; }

// Flash
function set_flash($msg, $type='info'){ $_SESSION['message']=$msg; $_SESSION['message_type']=$type; }

// User info
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: login.php'); exit; }
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? ($_SESSION['username'] ?? 'User');

// Data for selects
$gov_owners  = $pdo->query("SELECT * FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$prog_owners = $pdo->query("SELECT * FROM p_budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$vehicles    = $pdo->query("SELECT * FROM vehicles ORDER BY plate_no")->fetchAll(PDO::FETCH_ASSOC);
$months      = monthsEC();
$last_price  = (float)($pdo->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC, id DESC LIMIT 1")->fetchColumn() ?: 0);

// Edit mode
$fuel = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    if (!$is_admin) { http_response_code(403); exit('Forbidden'); }
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $fuel = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Reverse (best-effort) for fuel transactions without ledger
function reverseLegacyFuel(PDO $pdo, array $tx): void {
    $amount = (float)$tx['total_amount'];
    if ($amount <= 0) return;
    $year = ecYear();
    $fuel_code_id = 5;

    if ($tx['budget_type'] === 'program') {
        // Try p_budgets first
        $r = $pdo->prepare("SELECT id FROM p_budgets WHERE owner_id=? AND year=? FOR UPDATE");
        $r->execute([(int)$tx['owner_id'], $year]);
        $prog = $r->fetch(PDO::FETCH_ASSOC);
        if ($prog) {
            $pdo->prepare("UPDATE p_budgets SET remaining_yearly = remaining_yearly + ? WHERE id=?")
                ->execute([$amount, (int)$prog['id']]);
            return;
        }
        // Fallback to budgets (program yearly rows)
        $r = $pdo->prepare("SELECT id FROM budgets WHERE budget_type='program' AND owner_id=? AND year=? AND monthly_amount=0 FOR UPDATE");
        $r->execute([(int)$tx['owner_id'], $year]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $pdo->prepare("UPDATE budgets SET remaining_yearly = remaining_yearly + ? WHERE id=?")
                ->execute([$amount, (int)$row['id']]);
        }
    } else {
        // Governmental: monthly first, else yearly
        $r = $pdo->prepare("SELECT id FROM budgets WHERE budget_type='governmental' AND owner_id=? AND code_id=? AND year=? AND month=? FOR UPDATE");
        $r->execute([(int)$tx['owner_id'], $fuel_code_id, $year, $tx['et_month']]);
        $b = $r->fetch(PDO::FETCH_ASSOC);
        if ($b) {
            $pdo->prepare("UPDATE budgets SET remaining_monthly = remaining_monthly + ? WHERE id=?")
                ->execute([$amount, (int)$b['id']]);
        } else {
            $r = $pdo->prepare("SELECT id FROM budgets WHERE budget_type='governmental' AND owner_id=? AND code_id=? AND year=? AND monthly_amount=0 FOR UPDATE");
            $r->execute([(int)$tx['owner_id'], $fuel_code_id, $year]);
            $y = $r->fetch(PDO::FETCH_ASSOC);
            if ($y) {
                $pdo->prepare("UPDATE budgets SET remaining_yearly = remaining_yearly + ? WHERE id=?")
                    ->execute([$amount, (int)$y['id']]);
            }
        }
    }
}

// Allocation helpers
function allocateProgramFuel(PDO $pdo, int $owner_id, int $year, float $amount): void {
    // Try p_budgets first
    $s = $pdo->prepare("SELECT id, remaining_yearly FROM p_budgets WHERE owner_id=? AND year=? FOR UPDATE");
    $s->execute([$owner_id, $year]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $newRem = (float)$row['remaining_yearly'] - $amount;
        if ($newRem < 0) throw new Exception('Insufficient program yearly budget.');
        $pdo->prepare("UPDATE p_budgets SET remaining_yearly=? WHERE id=?")
            ->execute([$newRem, (int)$row['id']]);
        return;
    }

    // Fallback: budgets table (program yearly rows; may be split across multiple rows)
    $s = $pdo->prepare("
        SELECT id, remaining_yearly
        FROM budgets
        WHERE budget_type='program' AND owner_id=? AND year=? AND monthly_amount=0
        ORDER BY id ASC
        FOR UPDATE
    ");
    $s->execute([$owner_id, $year]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) throw new Exception('No program budget allocated or registered for this owner/year.');

    $total_remaining = array_reduce($rows, fn($c,$r)=>$c+(float)$r['remaining_yearly'], 0.0);
    if ($amount > $total_remaining) throw new Exception('Insufficient program yearly budget.');

    $left = $amount;
    foreach ($rows as $r) {
        if ($left <= 0) break;
        $avail = (float)$r['remaining_yearly'];
        $use = min($avail, $left);
        $pdo->prepare("UPDATE budgets SET remaining_yearly=? WHERE id=?")
            ->execute([$avail - $use, (int)$r['id']]);
        $left -= $use;
    }
}

function allocateGovernmentFuel(PDO $pdo, int $owner_id, int $year, string $et_month, float $amount, int $code_id=5): void {
    // Monthly first
    $s = $pdo->prepare("
        SELECT id, remaining_monthly FROM budgets
        WHERE budget_type='governmental' AND owner_id=? AND code_id=? AND year=? AND month=? FOR UPDATE
    ");
    $s->execute([$owner_id, $code_id, $year, $et_month]);
    $b = $s->fetch(PDO::FETCH_ASSOC);
    if ($b) {
        $newRem = (float)$b['remaining_monthly'] - $amount;
        if ($newRem < 0) throw new Exception('Insufficient remaining monthly budget for fuel.');
        $pdo->prepare("UPDATE budgets SET remaining_monthly=? WHERE id=?")
            ->execute([$newRem, (int)$b['id']]);
        return;
    }
    // Yearly fallback
    $s = $pdo->prepare("
        SELECT id, remaining_yearly FROM budgets
        WHERE budget_type='governmental' AND owner_id=? AND code_id=? AND year=? AND monthly_amount=0 FOR UPDATE
    ");
    $s->execute([$owner_id, $code_id, $year]);
    $y = $s->fetch(PDO::FETCH_ASSOC);
    if (!$y) throw new Exception('No fuel budget allocated.');
    $newRemY = (float)$y['remaining_yearly'] - $amount;
    if ($newRemY < 0) throw new Exception('Insufficient remaining yearly budget for fuel.');
    $pdo->prepare("UPDATE budgets SET remaining_yearly=? WHERE id=?")
        ->execute([$newRemY, (int)$y['id']]);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // DELETE via POST (admin only)
    if ($action === 'delete') {
        if (!$is_admin) { http_response_code(403); exit('Forbidden'); }
        if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }
        $del_id = (int)($_POST['id'] ?? 0);
        if ($del_id <= 0) { set_flash('Invalid delete request', 'error'); header('Location: fuel_management.php'); exit; }
        try {
            $pdo->beginTransaction();
            $s = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id=? FOR UPDATE");
            $s->execute([$del_id]);
            $tx = $s->fetch(PDO::FETCH_ASSOC);
            if ($tx) reverseLegacyFuel($pdo, $tx);
            $pdo->prepare("DELETE FROM fuel_transactions WHERE id=?")->execute([$del_id]);
            $pdo->commit();
            set_flash('Fuel transaction deleted', 'success');
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('Error: ' . $e->getMessage(), 'error');
        }
        header('Location: fuel_management.php'); exit;
    }

    // ADD/UPDATE
    if (!in_array($_SESSION['role'] ?? '', ['admin','officer'], true)) {
        http_response_code(403); exit('Forbidden');
    }
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }

    $id           = isset($_POST['id']) ? (int)$_POST['id'] : null;
    $is_update    = (($action === 'update') && $id);
    if ($is_update && !$is_admin) { http_response_code(403); exit('Forbidden'); }

    $owner_id     = (int)($_POST['owner_id'] ?? 0);
    $budget_type  = (($_POST['budget_type'] ?? 'governmental') === 'program') ? 'program' : 'governmental';
    $driver_name  = trim($_POST['driver_name'] ?? '');
    $plate_number = trim($_POST['plate_number'] ?? '');
    $et_month     = $budget_type === 'program' ? '' : trim($_POST['et_month'] ?? '');

    $current_gauge    = (float)($_POST['current_gauge'] ?? 0);
    $journey_distance = (float)($_POST['journey_distance'] ?? 0);
    $fuel_price       = (float)($_POST['fuel_price'] ?? 0);

    // Server-side compute to prevent tampering
    $refuelable_amount = $journey_distance > 0 ? ($journey_distance / 5) : 0.0;
    $total_amount      = $refuelable_amount * $fuel_price;
    $new_gauge         = $current_gauge + $journey_distance;
    $gauge_gap         = $journey_distance;

    // Basic validation
    if ($owner_id <= 0) { set_flash('Invalid budget owner', 'error'); }
    elseif ($driver_name === '') { set_flash('Driver name is required', 'error'); }
    elseif ($plate_number === '') { set_flash('Plate number is required', 'error'); }
    elseif ($budget_type === 'governmental' && ($et_month === '' || !in_array($et_month, monthsEC(), true))) { set_flash('Valid Ethiopian month is required (governmental).', 'error'); }
    elseif ($current_gauge < 0 || $journey_distance < 0 || $fuel_price <= 0) { set_flash('Invalid numeric values for current gauge, journey, or price.', 'error'); }
    else {
        // Gauge continuity
        $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number=? ORDER BY date DESC, id DESC LIMIT 1");
        $stmt->execute([$plate_number]);
        $last_new_gauge = (float)($stmt->fetchColumn() ?: 0);
        if ($last_new_gauge && $current_gauge < $last_new_gauge) {
            set_flash('Gauge error: Current gauge is less than last recorded new gauge.', 'error');
        } else {
            try {
                $pdo->beginTransaction();
                $year = ecYear();
                $fuel_code_id = 5;

                // p_koox only for governmental owners
                $p_koox = null;
                if ($budget_type === 'governmental') {
                    $st = $pdo->prepare("SELECT p_koox FROM budget_owners WHERE id=?");
                    $st->execute([$owner_id]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    $p_koox = $row['p_koox'] ?? null;
                }

                // If updating, reverse old deduction first (best-effort)
                if ($is_update) {
                    $st = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id=? FOR UPDATE");
                    $st->execute([$id]);
                    $old = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$old) throw new Exception('Transaction not found for update.');
                    reverseLegacyFuel($pdo, $old);
                }

                // Allocate new
                if ($budget_type === 'program') {
                    allocateProgramFuel($pdo, $owner_id, $year, $total_amount);
                    if ($is_update) {
                        $u = $pdo->prepare("
                            UPDATE fuel_transactions
                            SET budget_type='program',
                                owner_id=?, p_koox=?, driver_name=?, plate_number=?, et_month=?,
                                previous_gauge=?, current_gauge=?, journey_distance=?, fuel_price=?,
                                refuelable_amount=?, total_amount=?, new_gauge=?, gauge_gap=?
                            WHERE id=?
                        ");
                        $u->execute([
                            $owner_id, null, $driver_name, $plate_number, '',
                            $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                            $refuelable_amount, $total_amount, $new_gauge, $gauge_gap, $id
                        ]);
                        set_flash('Program fuel transaction updated', 'success');
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO fuel_transactions (
                                budget_type, owner_id, p_koox, driver_name, plate_number, et_month,
                                previous_gauge, current_gauge, journey_distance, fuel_price,
                                refuelable_amount, total_amount, new_gauge, gauge_gap
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            'program', $owner_id, null, $driver_name, $plate_number, '',
                            $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                            $refuelable_amount, $total_amount, $new_gauge, $gauge_gap
                        ]);
                        set_flash('Program fuel transaction added', 'success');
                    }
                } else {
                    allocateGovernmentFuel($pdo, $owner_id, $year, $et_month, $total_amount, $fuel_code_id);
                    if ($is_update) {
                        $u = $pdo->prepare("
                            UPDATE fuel_transactions
                            SET budget_type='governmental',
                                owner_id=?, p_koox=?, driver_name=?, plate_number=?, et_month=?,
                                previous_gauge=?, current_gauge=?, journey_distance=?, fuel_price=?,
                                refuelable_amount=?, total_amount=?, new_gauge=?, gauge_gap=?
                            WHERE id=?
                        ");
                        $u->execute([
                            $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
                            $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                            $refuelable_amount, $total_amount, $new_gauge, $gauge_gap, $id
                        ]);
                        set_flash('Fuel transaction updated', 'success');
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO fuel_transactions (
                                budget_type, owner_id, p_koox, driver_name, plate_number, et_month,
                                previous_gauge, current_gauge, journey_distance, fuel_price,
                                refuelable_amount, total_amount, new_gauge, gauge_gap
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            'governmental', $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
                            $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                            $refuelable_amount, $total_amount, $new_gauge, $gauge_gap
                        ]);
                        set_flash('Fuel transaction added', 'success');
                    }
                }

                $pdo->commit();
                header('Location: fuel_management.php'); exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('Error: ' . $e->getMessage(), 'error');
            }
        }
    }
}

// Flash clear
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fuel Management - Budget System</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="../assets/css/materialize.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; }
    .ethio-font { font-family: 'Noto Sans Ethiopic', sans-serif; }
    .fade-out{opacity:1;transition:opacity .5s ease-out}.fade-out.hide{opacity:0}
    .main-content { width: 100%; }
    .select2-container--default .select2-selection--single{height:42px;border:1px solid #d1d5db;border-radius:.375rem}
    .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:40px;padding-left:12px}
    .select2-container--default .select2-selection--single .select2-selection__arrow{height:40px}
    .select2-container--default .select2-results__option--highlighted[aria-selected]{background-color:#4f46e5}
    .info-card{background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);border-left:4px solid #3b82f6}
    .program-card{background:linear-gradient(135deg,#f0f4ff 0%,#e0e7ff 100%);border-left:4px solid #6366f1}
    .vehicle-card{background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border-left:4px solid #22c55e}
    .row-click { cursor:pointer; }
    .hidden { display: none; }
  </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
  <?php require_once 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <div class="p-6">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Fuel Management</h1>
          <p class="text-slate-600 mt-2">Manage fuel transactions and consumption</p>
          <div class="mt-3 bg-indigo-100 rounded-lg p-3 max-w-md info-card">
            <i class="fas fa-user-circle text-indigo-600 mr-2"></i>
            <span class="text-indigo-800 font-semibold">
              Welcome, <?php echo htmlspecialchars($user_name); ?>! (<?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? '')); ?>)
            </span>
          </div>
        </div>
        <div class="flex items-center space-x-4 mt-4 md:mt-0">
          <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <?php if ($message): ?>
        <div id="message" class="fade-out mb-6 p-4 rounded-lg <?php
          echo $message_type == 'error' ? 'bg-red-100 text-red-700' :
          ($message_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700');
        ?>">
          <div class="flex justify-between items-center">
            <p><?php echo htmlspecialchars($message); ?></p>
            <button onclick="document.getElementById('message').classList.add('hide')" class="text-lg">&times;</button>
          </div>
        </div>
        <script>setTimeout(()=>{const m=document.getElementById('message');if(m){m.classList.add('hide');setTimeout(()=>m.remove(),500);}},5000);</script>
      <?php endif; ?>

      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6"><?php echo isset($fuel) ? 'Edit Fuel Transaction' : 'Add New Fuel Transaction'; ?></h2>
        <form id="fuelForm" method="POST" class="space-y-4" onsubmit="return validateBeforeSubmit();">
          <?php if (isset($fuel)): ?>
            <input type="hidden" name="id" value="<?php echo (int)$fuel['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Source Type *</label>
              <select name="budget_type" id="budget_type" class="w-full select2">
                <option value="governmental" <?php echo isset($fuel) ? ($fuel['budget_type']==='governmental' ? 'selected' : '') : 'selected'; ?>>Government Budget</option>
                <option value="program" <?php echo isset($fuel) && $fuel['budget_type']==='program' ? 'selected' : ''; ?>>Programs Budget</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
              <select name="owner_id" id="owner_id" required class="w-full select2">
                <option value="">Select a Budget Source First</option>
              </select>
            </div>

            <div class="program-card p-4 rounded-lg">
              <h3 class="text-sm font-medium text-indigo-800 mb-2">Source Details</h3>
              <div class="flex items-center">
                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-project-diagram text-indigo-600"></i>
                </div>
                <div>
                  <p id="program_p_koox_display" class="text-sm font-medium text-gray-900">-</p>
                  <p id="program_name_display" class="text-xs text-gray-600">Owner: -</p>
                </div>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number *</label>
              <select name="plate_number" id="plate_number" required class="w-full select2">
                <option value="">Select Vehicle</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?php echo htmlspecialchars($v['plate_no']); ?>"
                          data-model="<?php echo htmlspecialchars($v['model']); ?>"
                          <?php
                          $sel = false;
                          if (isset($fuel) && $fuel['plate_number'] === $v['plate_no']) $sel = true;
                          if (isset($_POST['plate_number']) && $_POST['plate_number'] === $v['plate_no']) $sel = true;
                          echo $sel ? 'selected' : '';
                          ?>>
                    <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['model']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Driver Name *</label>
              <input type="text" name="driver_name" id="driver_name" value="<?php
                echo isset($fuel) ? htmlspecialchars($fuel['driver_name']) :
                     (isset($_POST['driver_name']) ? htmlspecialchars($_POST['driver_name']) : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div id="et_month_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full select2">
                <option value="">Select Month</option>
                <?php foreach ($months as $m): ?>
                  <option value="<?php echo htmlspecialchars($m); ?>"
                    <?php
                      $sel = false;
                      if (isset($fuel) && $fuel['et_month'] === $m && $fuel['budget_type']!=='program') $sel = true;
                      if (isset($_POST['et_month']) && $_POST['et_month'] === $m) $sel = true;
                      echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($m); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="vehicle-card p-4 rounded-lg">
            <h3 class="text-sm font-medium text-green-800 mb-2">Vehicle Details</h3>
            <div class="flex items-center">
              <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-car text-green-600"></i>
              </div>
              <div>
                <p id="vehicle_model_display" class="text-sm font-medium text-gray-900">-</p>
                <p id="previous_gauge_display" class="text-xs text-gray-600">Previous Gauge: -</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Current Gauge *</label>
              <input type="number" step="0.01" name="current_gauge" id="current" value="<?php
                echo isset($fuel) ? htmlspecialchars($fuel['current_gauge']) : (isset($_POST['current_gauge']) ? htmlspecialchars($_POST['current_gauge']) : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (km) *</label>
              <input type="number" step="0.01" name="journey_distance" id="journey" value="<?php
                echo isset($fuel) ? htmlspecialchars($fuel['journey_distance']) : (isset($_POST['journey_distance']) ? htmlspecialchars($_POST['journey_distance']) : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price (per liter) *</label>
              <input type="number" step="0.01" name="fuel_price" id="price" value="<?php
                echo isset($fuel) ? htmlspecialchars($fuel['fuel_price']) : (isset($_POST['fuel_price']) ? htmlspecialchars($_POST['fuel_price']) : $last_price);
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable Amount (liters)</label>
              <input type="number" step="0.01" name="refuelable_amount" id="refuelable" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-xl p-4 bg-gradient-to-r from-sky-100 to-sky-50 border border-sky-200 shadow-sm">
              <div class="text-sm text-sky-700 font-medium">Total Amount</div>
              <div id="total_amount_card" class="text-2xl font-extrabold text-sky-900 mt-1">0.00 ብር</div>
              <input type="hidden" name="total_amount" id="total" value="0">
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="text-sm text-amber-700 font-medium">New Gauge</div>
              <div id="new_gauge_card" class="text-2xl font-extrabold text-amber-900 mt-1">0.00</div>
              <input type="hidden" name="new_gauge" id="new_gauge" value="0">
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-rose-100 to-rose-50 border border-rose-200 shadow-sm">
              <div class="text-sm text-rose-700 font-medium">Gauge Gap</div>
              <div id="gap_card" class="text-2xl font-extrabold text-rose-900 mt-1">0.00</div>
              <input type="hidden" name="gauge_gap" id="gap" value="0">
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div id="rem_monthly_card" class="rounded-xl p-5 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-amber-200 text-amber-800"><i class="fas fa-calendar-alt"></i></div>
                <div>
                  <div class="text-sm text-amber-700 font-medium">Monthly Fuel Budget</div>
                  <div id="rem_monthly" class="text-2xl font-extrabold text-amber-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <div class="rounded-xl p-5 bg-gradient-to-r from-emerald-100 to-emerald-50 border border-emerald-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-emerald-200 text-emerald-800"><i class="fas fa-coins"></i></div>
                <div>
                  <div class="text-sm text-emerald-700 font-medium" id="yearly_label">Available Yearly Fuel Budget</div>
                  <div id="rem_yearly" class="text-2xl font-extrabold text-emerald-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <div id="programs_total_card" class="rounded-xl p-5 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-layer-group"></i></div>
                <div>
                  <div class="text-sm text-purple-700 font-medium">Bureau’s Programs Total Budget</div>
                  <div id="programs_total_amount" class="text-2xl font-extrabold text-purple-900 mt-1">0.00 ብር</div>
                </div>
              </div>
            </div>
          </div>

          <div id="government_grand_card" class="rounded-xl p-5 mt-4 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
            <div class="flex items-center gap-3">
              <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-building"></i></div>
              <div>
                <div id="gov_grand_label" class="text-sm text-purple-700 font-medium">Bureau’s Yearly Government Budget</div>
                <div id="gov_grand_amount" class="text-2xl font-extrabold text-purple-900 mt-1">0.00 ብር</div>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-4 pt-2">
            <?php if (isset($fuel)): ?>
              <a href="fuel_management.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">Cancel</a>
            <?php endif; ?>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <?php echo isset($fuel) ? 'Update Transaction' : 'Add Transaction'; ?>
            </button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
        <div class="grid md:grid-cols-4 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Budget Source</label>
            <select id="flt_type" class="w-full select2">
              <option value="governmental">Government</option>
              <option value="program">Programs</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Owner</label>
            <select id="flt_owner" class="w-full select2">
              <option value="">Any Owner</option>
              <?php foreach ($gov_owners as $o): ?>
                <option value="<?php echo (int)$o['id']; ?>" data-budget-type="governmental"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
              <?php endforeach; ?>
              <?php foreach ($prog_owners as $o): ?>
                <option value="<?php echo (int)$o['id']; ?>" data-budget-type="program"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="flt_month_box">
            <label class="block text-sm font-medium mb-1">Month (Gov only)</label>
            <select id="flt_month" class="w-full select2">
              <option value="">Any Month</option>
              <?php foreach ($months as $m): ?>
                <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Plate</label>
            <select id="flt_plate" class="w-full select2">
              <option value="">Any Plate</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?php echo htmlspecialchars($v['plate_no']); ?>"><?php echo htmlspecialchars($v['plate_no'].' - '.$v['model']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="mb-4">
        <input type="text" id="searchInput" placeholder="Search transactions..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" onkeyup="filterTransactions()">
      </div>

      <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Fuel Transactions</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Driver</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plate No</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="transactionsTable">
              <tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    const defaultFuelPrice = <?php echo json_encode((float)$last_price); ?>;
    let filling = false;
    const isEdit = <?php echo isset($fuel) ? 'true' : 'false'; ?>;
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const csrfToken = <?php echo json_encode($_SESSION['csrf']); ?>;

    function fmt(n){return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
    function birr(n){return fmt(n)+' ብር';}
    function esc(s){return String(s ?? '').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}

    function calculateFuel() {
      const current = parseFloat($('#current').val()) || 0;
      const journey = parseFloat($('#journey').val()) || 0;
      const price = parseFloat($('#price').val()) || 0;

      const refuel = journey > 0 ? (journey / 5.0) : 0.0; // simple rule
      const total = refuel * price;
      const newG = current + journey;
      const gap = journey;

      $('#refuelable').val(refuel.toFixed(2));
      $('#total').val(total.toFixed(2));
      $('#total_amount_card').text(birr(total));

      $('#new_gauge').val(newG.toFixed(2));
      $('#new_gauge_card').text(newG.toFixed(2));

      $('#gap').val(gap.toFixed(2));
      $('#gap_card').text(gap.toFixed(2));
    }

    function setBudgetTypeUI(type) {
      if (type === 'program') {
        $('#et_month_box').hide();
        $('#et_month').prop('required', false);
        $('#rem_monthly_card').hide();
        $('#yearly_label').text('Available Yearly Budget');
        $('#flt_month_box').hide();
        $('#program_p_koox_display').hide();
      } else {
        $('#et_month_box').show();
        $('#et_month').prop('required', true);
        $('#rem_monthly_card').show();
        $('#yearly_label').text('Available Yearly Fuel Budget');
        $('#flt_month_box').show();
        $('#program_p_koox_display').show();
      }
    }

    function clearPlateDependentFields() {
      $('#vehicle_model_display').text('-');
      $('#previous_gauge_display').text('Previous Gauge: -');

      $('#current').val('');
      $('#journey').val('');
      $('#refuelable').val('');
      $('#total').val('');
      $('#new_gauge').val('');
      $('#gap').val('');

      $('#total_amount_card').text('0.00 ብር');
      $('#new_gauge_card').text('0.00');
      $('#gap_card').text('0.00');

      $('#flt_plate').val('').trigger('change.select2');
    }

    function resetFuelFormOnTypeSwitch(){
      filling = true;
      $('#owner_id').val('').trigger('change.select2');
      $('#et_month').val('').trigger('change.select2');
      $('#plate_number').val('').trigger('change.select2');
      filling = false;

      $('#driver_name').val('');
      $('#current').val('');
      $('#journey').val('');
      $('#price').val(defaultFuelPrice);
      $('#refuelable').val('');
      $('#total').val('');
      $('#new_gauge').val('');
      $('#gap').val('');

      $('#vehicle_model_display').text('-');
      $('#previous_gauge_display').text('Previous Gauge: -');
      $('#program_p_koox_display').text('-').hide();
      $('#program_name_display').text('Owner: -');

      $('#rem_monthly').text('0.00');
      $('#rem_yearly').text('0.00');
      $('#programs_total_card').hide();
      $('#government_grand_card').hide();
      $('#total_amount_card').text('0.00 ብር');
      $('#new_gauge_card').text('0.00');
      $('#gap_card').text('0.00');

      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val('').trigger('change.select2');
      $('#flt_month').val('').trigger('change.select2');
      $('#flt_plate').val('').trigger('change.select2');

      fetchFuelList();
    }

    function onBudgetTypeChange(){
      setBudgetTypeUI($('#budget_type').val());
      resetFuelFormOnTypeSwitch();
      fetchAndPopulateOwners();
    }

    function fetchAndPopulateOwners() {
        const budgetType = $('#budget_type').val();
        const ownerSelect = $('#owner_id');

        const fuelOwnerId = '<?php echo isset($fuel) ? (int)$fuel['owner_id'] : ''; ?>';
        const fuelBudgetType = '<?php echo isset($fuel) ? $fuel['budget_type'] : ''; ?>';

        ownerSelect.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');

        $.ajax({
            url: 'ajax_get_owners.php',
            type: 'GET',
            data: { budget_type: budgetType },
            dataType: 'json',
            success: function(response) {
                if (response.success && Array.isArray(response.owners)) {
                    ownerSelect.html('<option value="">Select Owner</option>');
                    response.owners.forEach(function(owner) {
                        const option = new Option(`${owner.code} - ${owner.name}`, owner.id);
                        if (budgetType === 'governmental' && owner.p_koox) {
                            $(option).data('p_koox', owner.p_koox);
                        }
                        ownerSelect.append(option);
                    });
                    if (isEdit && fuelOwnerId && fuelBudgetType === budgetType) {
                        ownerSelect.val(String(fuelOwnerId));
                    }
                } else {
                    ownerSelect.html('<option value="">Error loading owners</option>');
                }
            },
            error: function() {
                ownerSelect.html('<option value="">Error loading owners</option>');
            },
            complete: function() {
                ownerSelect.prop('disabled', false).trigger('change.select2');
                if (ownerSelect.val()) ownerSelect.trigger('change');
            }
        });
    }

    function updateFilterOwnerOptions() {
        const budgetType = $('#flt_type').val();
        const fltOwnerSelect = $('#flt_owner');
        fltOwnerSelect.find('option').each(function() {
            const option = $(this);
            const optionBudgetType = option.data('budget-type');
            if (!optionBudgetType || optionBudgetType === budgetType) {
                option.prop('disabled', false);
            } else {
                option.prop('disabled', true);
                if (option.is(':selected')) fltOwnerSelect.val('');
            }
        });
        fltOwnerSelect.trigger('change.select2');
    }

    function onOwnerChange(){
      const type = $('#budget_type').val();
      const selectedOption = $('#owner_id').find('option:selected');
      const p_koox = selectedOption.data('p_koox') || '-';
      const ownerName = selectedOption.text().split(' - ')[1] || selectedOption.text();
      $('#program_name_display').text('Owner: ' + ownerName);
      if (type === 'governmental') {
        $('#program_p_koox_display').text('P/Koox: ' + (p_koox || '-')).show();
      } else {
        $('#program_p_koox_display').text('-').hide();
      }

      clearPlateDependentFields();

      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      fetchFuelList();
      loadFuelRemaining();
      refreshGrandTotals();
    }

    function fetchVehicleDetails(plateNumber) {
      if (!plateNumber) {
        $('#vehicle_model_display').text('-');
        $('#previous_gauge_display').text('Previous Gauge: -');
        $('#current').val('');
        $('#journey').val('');
        $('#refuelable').val('');
        $('#total').val('');
        $('#new_gauge').val('');
        $('#gap').val('');
        $('#total_amount_card').text('0.00 ብር');
        $('#new_gauge_card').text('0.00');
        $('#gap_card').text('0.00');
        return;
      }

      const opt = $('#plate_number').find('option:selected');
      const model = opt.data('model') || '-';
      $('#vehicle_model_display').text(model);

      $('#flt_plate').val(plateNumber).trigger('change.select2');

      $.get('get_last_gauge.php', { plate_number: plateNumber }, function(resp) {
        try {
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          const last = Number(j.last_gauge || 0);
          $('#previous_gauge_display').text('Previous Gauge: ' + last.toFixed(2));

          $('#current').val(last.toFixed(2));
          $('#journey').val('');
          $('#refuelable').val('');
          $('#total').val('');
          $('#new_gauge').val('');
          $('#gap').val('');

          $('#total_amount_card').text('0.00 ብር');
          $('#new_gauge_card').text('0.00');
          $('#gap_card').text('0.00');

          calculateFuel();
        } catch (e) {
          console.error('parse error', e);
          $('#previous_gauge_display').text('Previous Gauge: -');
        }
      }).fail(function() {
        console.error('get_last_gauge failed');
        $('#previous_gauge_display').text('Previous Gauge: -');
      });
    }

    function loadFuelRemaining(){
      const ownerId = $('#owner_id').val();
      const etMonth = $('#et_month').val();
      const year = new Date().getFullYear() - 8;
      const type = $('#budget_type').val();

      if (!ownerId) { $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); return; }

      if (type === 'program') {
        $.get('get_remaining_program.php', { owner_id: ownerId, year: year }, function(resp){
          try {
            const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_yearly').text(fmt(j.remaining_yearly || 0));
            $('#rem_monthly').text('0.00');
          } catch (e) { $('#rem_yearly').text('0.00'); }
        }).fail(()=>$('#rem_yearly').text('0.00'));
      } else {
        if (!etMonth) { $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); return; }
        // Reuse per diem endpoint for fuel (code_id=5)
        $.get('get_remaining_perdium.php', { owner_id: ownerId, code_id: 5, month: etMonth, year: year }, function(resp){
          try {
            const rem = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_monthly').text(fmt(rem.remaining_monthly || 0));
            $('#rem_yearly').text(fmt(rem.remaining_yearly || 0));
          } catch (e) {
            $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00');
          }
        }).fail(()=>{ $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); });
      }
    }

    function refreshGrandTotals(){
      const type    = $('#budget_type').val();
      const ownerId = $('#owner_id').val();
      const year    = new Date().getFullYear() - 8;

      $.get('ajax_fuel_grands.php',{ budget_type:type, owner_id:ownerId, year:year }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          if (type === 'program') {
            if (!ownerId) {
              $('#programs_total_card').show();
              $('#programs_total_amount').text(birr(j.programsTotalYearly || 0));
            } else {
              $('#programs_total_card').hide();
            }
            $('#government_grand_card').hide();
          } else {
            $('#programs_total_card').hide();
            if (!ownerId) {
              $('#government_grand_card').show();
              $('#gov_grand_label').text("Bureau’s Yearly Government Budget");
              $('#gov_grand_amount').text(birr(j.govtBureauRemainingYearly || 0));
            } else {
              $('#government_grand_card').show();
              const ownerName = $('#owner_id option:selected').text().split(' - ')[1] || 'Selected Owner';
              $('#gov_grand_label').text(`${ownerName}'s Total Yearly Budget (Grand Yearly Budget)`);
              $('#gov_grand_amount').text(birr(j.govtOwnerYearlyRemaining || 0));
            }
          }
        }catch(e){
          $('#programs_total_card').hide();
          $('#government_grand_card').hide();
        }
      }).fail(function(){
        $('#programs_total_card').hide();
        $('#government_grand_card').hide();
      });
    }

    function toggleFilterMonth(){
      const t = $('#flt_type').val();
      if (t === 'program') { $('#flt_month_box').hide(); } else { $('#flt_month_box').show(); }
    }

    function syncFiltersFromForm(){
      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      $('#flt_month').val($('#et_month').val()).trigger('change.select2');
      $('#flt_plate').val($('#plate_number').val()).trigger('change.select2');
    }

    function validateBeforeSubmit(){
      syncFiltersFromForm();
      return true;
    }

    function fetchFuelList(){
      const type  = $('#flt_type').val();
      const owner = $('#flt_owner').val();
      const month = $('#flt_month').val();
      const plate = $('#flt_plate').val();
      $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Loading…</td></tr>');
      $.get('ajax_fuel_list.php', { budget_type:type, owner_id:owner, et_month:month, plate:plate }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          const rows = j.rows || [];
          if(rows.length===0){
            $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">No fuel transactions found.</td></tr>');
            return;
          }
          let html='';
          rows.forEach(f=>{
            const printUrl = (f.budget_type==='program')
              ? `reports/fuel_transaction_report2.php?id=${f.id}`
              : `reports/fuel_transaction_report.php?id=${f.id}`;
            const dataJson = encodeURIComponent(JSON.stringify(f));
            let actions = `
              <a href="${printUrl}" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                <i class="fas fa-print mr-1"></i> Print
              </a>
            `;
            <?php if ($is_admin): ?>
            actions += `
              <a href="?action=edit&id=${f.id}" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200">
                <i class="fas fa-edit mr-1"></i> Edit
              </a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this transaction?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${f.id}">
                <input type="hidden" name="csrf" value="${csrfToken}">
                <button class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200">
                  <i class="fas fa-trash mr-1"></i> Delete
                </button>
              </form>
            `;
            <?php endif; ?>
            html += `
              <tr class="row-click" data-json="${dataJson}">
                <td class="px-4 py-4 text-sm text-gray-900">${esc((f.date||'').replace('T',' ').slice(0,19))}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${esc(f.owner_code || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${esc(f.driver_name || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${esc(f.plate_number || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900 ethio-font">${esc(f.et_month || '')}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${Number(f.total_amount||0).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                <td class="px-4 py-4 text-sm"><div class="flex space-x-2">${actions}</div></td>
              </tr>`;
          });
          $('#transactionsTable').html(html);
          filterTransactions();
        }catch(e){
          $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Failed to load.</td></tr>');
        }
      }).fail(()=>$('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Failed to load.</td></tr>'));
    }

    function filterTransactions() {
      const filter = (document.getElementById('searchInput').value||'').toLowerCase();
      const rows = document.querySelectorAll('#transactionsTable tr');
      rows.forEach(row=>{
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    }

    function fillFormFromRow(d){
      try{
        filling = true;

        $('#budget_type').val(d.budget_type || 'governmental').trigger('change.select2');
        setBudgetTypeUI($('#budget_type').val());
        fetchAndPopulateOwners();

        $('#owner_id').val(String(d.owner_id||'')).trigger('change.select2');
        const ownerOpt = $('#owner_id').find('option:selected');
        const p_koox = ownerOpt.data('p_koox') || '-';
        const ownerName = ownerOpt.text().split(' - ')[1] || ownerOpt.text();
        $('#program_name_display').text('Owner: ' + ownerName);
        if ($('#budget_type').val() === 'governmental') {
          $('#program_p_koox_display').text('P/Koox: ' + (p_koox || '-')).show();
        } else {
          $('#program_p_koox_display').text('-').hide();
        }

        if ((d.budget_type||'governmental') !== 'program') {
          $('#et_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#et_month').val('').trigger('change.select2');
        }

        $('#plate_number').val(d.plate_number || '').trigger('change.select2');
        const plateOpt = $('#plate_number').find('option:selected');
        $('#vehicle_model_display').text(plateOpt.data('model') || '-');
        $('#previous_gauge_display').text('Previous Gauge: ' + Number(d.previous_gauge||0).toFixed(2));

        $('#driver_name').val(d.driver_name || '');
        $('#current').val(Number(d.current_gauge||0).toFixed(2));
        $('#journey').val(Number(d.journey_distance||0).toFixed(2));
        $('#price').val(Number(d.fuel_price||0).toFixed(2));
        $('#refuelable').val(Number(d.refuelable_amount||0).toFixed(2));
        $('#total').val(Number(d.total_amount||0).toFixed(2));
        $('#new_gauge').val(Number(d.new_gauge||0).toFixed(2));
        $('#gap').val(Number(d.gauge_gap||0).toFixed(2));

        $('#total_amount_card').text(birr(d.total_amount||0));
        $('#new_gauge_card').text(Number(d.new_gauge||0).toFixed(2));
        $('#gap_card').text(Number(d.gauge_gap||0).toFixed(2));

        $('#flt_type').val(d.budget_type || 'governmental').trigger('change.select2');
        updateFilterOwnerOptions();
        $('#flt_owner').val(String(d.owner_id||'')).trigger('change.select2');
        if ((d.budget_type||'governmental') !== 'program') {
          $('#flt_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#flt_month').val('').trigger('change.select2');
        }
        $('#flt_plate').val(d.plate_number || '').trigger('change.select2');

        filling = false;

        loadFuelRemaining();
        refreshGrandTotals();

      }catch(e){
        filling = false;
        console.error('fillFormFromRow error', e);
      }
    }

    $(document).ready(function(){
      $('.select2').select2({ theme:'classic', width:'100%',
        matcher: function(params, data) {
          if ($.trim(params.term) === '') return data;
          if (typeof data.text === 'undefined') return null;
          if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
          for (const key in data.element.dataset) {
            if (String(data.element.dataset[key]).toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
          }
          return null;
        }
      });

      $('#budget_type').on('change', function(){
        if (filling) return;
        onBudgetTypeChange();
        fetchAndPopulateOwners();
        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        toggleFilterMonth();
        fetchFuelList();
      });

      $('#owner_id').on('change', function(){ if (!filling) onOwnerChange(); });

      $('#et_month').on('change', function(){
        if (filling) return;
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        fetchFuelList();
        loadFuelRemaining();
      });

      $('#plate_number').on('change', function(){
        if (filling) return;
        const p = this.value || '';
        fetchVehicleDetails(p);
        $('#flt_plate').val(p).trigger('change.select2');
        fetchFuelList();
      });

      $('#flt_plate').on('change', function(){
        const p = this.value || '';
        filling = true;
        $('#plate_number').val(p).trigger('change.select2');
        filling = false;
        fetchVehicleDetails(p);
        fetchFuelList();
      });

      $('#flt_type').on('change', function(){
        updateFilterOwnerOptions();
        toggleFilterMonth();
        fetchFuelList();
      });
      $('#flt_owner, #flt_month').on('change', function(){ fetchFuelList(); });

      $('#transactionsTable').on('click', 'tr.row-click', function(e){
        if ($(e.target).closest('a,button,form').length) return;
        const dataJson = $(this).attr('data-json');
        if (!dataJson) return;
        try {
          const d = JSON.parse(decodeURIComponent(dataJson));
          fillFormFromRow(d);
        } catch (err) { console.error('row parse error', err); }
      });

      // INIT
      setBudgetTypeUI($('#budget_type').val());
      fetchAndPopulateOwners();
      updateFilterOwnerOptions();
      toggleFilterMonth();

      if (isEdit) {
        const p = $('#plate_number').val();
        if (p) fetchVehicleDetails(p);
        loadFuelRemaining();
        refreshGrandTotals();

        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
        $('#flt_plate').val($('#plate_number').val()).trigger('change.select2');
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        fetchFuelList();
      } else {
        calculateFuel();
        loadFuelRemaining();
        refreshGrandTotals();

        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
        $('#flt_plate').val($('#plate_number').val()).trigger('change.select2');
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        fetchFuelList();
      }
    });

    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });
  </script>
</body>
</html>