<?php
require_once 'includes/init.php';

// Role
$is_officer = ($_SESSION['role'] == 'officer');

// Get user name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? $_SESSION['username'];

// Ensure columns exist (safe)
try { $pdo->query("ALTER TABLE fuel_transactions ADD COLUMN p_koox VARCHAR(255) AFTER owner_id"); } catch (PDOException $e) {}
try { $pdo->query("ALTER TABLE fuel_transactions ADD COLUMN budget_type ENUM('governmental','program') NOT NULL DEFAULT 'governmental' AFTER id"); } catch (PDOException $e) {}

// Dropdown data
$owners = $pdo->query("SELECT * FROM budget_owners ORDER BY code")->fetchAll();
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY plate_no")->fetchAll();

// Last price
$last_price = $pdo->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")->fetchColumn() ?: 0;

// Editing?
$fuel = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $fuel = $stmt->fetch();
}

function ecYear() { return date('Y') - 8; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id = $_POST['owner_id'] ?? '';
    $budget_type = ($_POST['budget_type'] ?? 'governmental') === 'program' ? 'program' : 'governmental';
    $driver_name = $_POST['driver_name'] ?? '';
    $plate_number = $_POST['plate_number'] ?? '';
    $et_month = $budget_type === 'program' ? '' : ($_POST['et_month'] ?? '');
    $current_gauge = (float)($_POST['current_gauge'] ?? 0);
    $journey_distance = (float)($_POST['journey_distance'] ?? 0);
    $fuel_price = (float)($_POST['fuel_price'] ?? 0);
    $refuelable_amt = (float)($_POST['refuelable_amount']?? 0);
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $new_gauge = (float)($_POST['new_gauge'] ?? 0);
    $gauge_gap = (float)($_POST['gauge_gap'] ?? 0);

    // p_koox from owners
    $stmt = $pdo->prepare("SELECT p_koox FROM budget_owners WHERE id = ?");
    $stmt->execute([$owner_id]);
    $budget_owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $p_koox = $budget_owner['p_koox'] ?? '';

    // Gauge continuity
    $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number = ? ORDER BY date DESC, id DESC LIMIT 1");
    $stmt->execute([$plate_number]);
    $last_new_gauge = (float)($stmt->fetchColumn() ?: 0);
    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $_SESSION['message'] = 'Gauge error: Current gauge less than expected new gauge.';
        $_SESSION['message_type'] = 'error';
    } else {
        $pdo->beginTransaction();
        try {
            $year = ecYear();
            $fuel_code_id = 5; // Sansii kee Sukutih

            if ($budget_type === 'program') {
                // Deduct from program yearly budgets (owner-level, yearly rows only)
                $stmt = $pdo->prepare("
                    SELECT id, remaining_yearly
                    FROM budgets
                    WHERE budget_type='program'
                      AND owner_id = ?
                      AND year = ?
                      AND monthly_amount = 0
                    ORDER BY id ASC
                    FOR UPDATE
                ");
                $stmt->execute([$owner_id, $year]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $total_remaining = 0;
                foreach ($rows as $r) $total_remaining += (float)$r['remaining_yearly'];

                if ($total_amount > $total_remaining) {
                    throw new Exception('Insufficient program yearly budget. Available ' . number_format($total_remaining, 2) . ' ብር');
                }

                $left = $total_amount;
                foreach ($rows as $r) {
                    if ($left <= 0) break;
                    $avail = (float)$r['remaining_yearly'];
                    $use = min($avail, $left);
                    $newRem = $avail - $use;
                    $upd = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                    $upd->execute([$newRem, (int)$r['id']]);
                    $left -= $use;
                }

                // Insert/Update
                if (isset($_POST['id']) && $_POST['action'] == 'update') {
                    $stmt = $pdo->prepare("
                        UPDATE fuel_transactions
                        SET budget_type='program',
                            owner_id=?, p_koox=?, driver_name=?, plate_number=?, et_month=?,
                            previous_gauge=?, current_gauge=?, journey_distance=?, fuel_price=?,
                            refuelable_amount=?, total_amount=?, new_gauge=?, gauge_gap=?
                        WHERE id=?
                    ");
                    $stmt->execute([
                        $owner_id, $p_koox, $driver_name, $plate_number, '',
                        $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                        $refuelable_amt, $total_amount, $new_gauge, $gauge_gap, $_POST['id']
                    ]);
                    $_SESSION['message'] = 'Program fuel transaction updated';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO fuel_transactions (
                            budget_type, owner_id, p_koox, driver_name, plate_number, et_month,
                            previous_gauge, current_gauge, journey_distance, fuel_price,
                            refuelable_amount, total_amount, new_gauge, gauge_gap
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        'program', $owner_id, $p_koox, $driver_name, $plate_number, '',
                        $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                        $refuelable_amt, $total_amount, $new_gauge, $gauge_gap
                    ]);
                    $_SESSION['message'] = 'Program fuel transaction added';
                }
            } else {
                // Governmental (your original deduction)
                // 1) Determine quarter (if needed)
                $quarterMap = [
                    'ሐምሌ'=>1,'ነሐሴ'=>1,'መስከረም'=>1,
                    'ጥቅምት'=>2,'ህዳር'=>2,'ታኅሳስ'=>2,
                    'ጥር'=>3,'የካቲት'=>3,'መጋቢት'=>3,
                    'ሚያዝያ'=>4,'ግንቦት'=>4,'ሰኔ'=>4
                ];
                $quarter = $quarterMap[$et_month] ?? 0;

                // 2) Deduct from monthly else yearly for fuel_code_id = 5
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM budgets
                    WHERE owner_id = ?
                      AND code_id = ?
                      AND year = ?
                      AND month = ?
                ");
                $stmt->execute([$owner_id, $fuel_code_id, $year, $et_month]);
                $budget = $stmt->fetch();

                if ($budget) {
                    $new_rem_month = $budget['remaining_monthly'] - $total_amount;
                    if ($new_rem_month < 0) throw new Exception('Insufficient remaining monthly budget for fuel.');
                    $stmt = $pdo->prepare("UPDATE budgets SET remaining_monthly = ? WHERE id = ?");
                    $stmt->execute([$new_rem_month, $budget['id']]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT *
                        FROM budgets
                        WHERE owner_id = ?
                          AND code_id = ?
                          AND year = ?
                          AND monthly_amount = 0
                    ");
                    $stmt->execute([$owner_id, $fuel_code_id, $year]);
                    $budget_yearly = $stmt->fetch();
                    if (!$budget_yearly) throw new Exception('No fuel budget allocated.');
                    $new_rem_year = $budget_yearly['remaining_yearly'] - $total_amount;
                    if ($new_rem_year < 0) throw new Exception('Insufficient remaining yearly budget for fuel.');
                    $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                    $stmt->execute([$new_rem_year, $budget_yearly['id']]);
                }

                // 3) Insert/update tx
                if (isset($_POST['id']) && $_POST['action'] == 'update') {
                    $stmt = $pdo->prepare("
                        UPDATE fuel_transactions
                        SET budget_type='governmental',
                            owner_id=?, p_koox=?, driver_name=?, plate_number=?, et_month=?,
                            previous_gauge=?, current_gauge=?, journey_distance=?, fuel_price=?,
                            refuelable_amount=?, total_amount=?, new_gauge=?, gauge_gap=?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
                        $last_new_gauge, $current_gauge, $journey_distance,
                        $fuel_price, $refuelable_amt, $total_amount,
                        $new_gauge, $gauge_gap, $_POST['id']
                    ]);
                    $_SESSION['message'] = 'Fuel transaction updated';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO fuel_transactions (
                            budget_type, owner_id, p_koox, driver_name, plate_number, et_month,
                            previous_gauge, current_gauge, journey_distance, fuel_price,
                            refuelable_amount, total_amount, new_gauge, gauge_gap
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        'governmental', $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
                        $last_new_gauge, $current_gauge, $journey_distance,
                        $fuel_price, $refuelable_amt, $total_amount,
                        $new_gauge, $gauge_gap
                    ]);
                    $_SESSION['message'] = 'Fuel transaction added';
                }
            }

            $pdo->commit();
            $_SESSION['message_type'] = 'success';
            header('Location: fuel_management.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['message'] = 'Fuel transaction deleted';
    $_SESSION['message_type'] = 'success';
    header('Location: fuel_management.php'); exit;
}

// Clear flash
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
    .form-control:disabled,.form-control[readonly]{background-color:#f9fafb}
  </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
  <!-- Sidebar -->
  <div class="sidebar bg-gradient-to-b from-blue-800 to-blue-600 text-white" id="sidebar">
    <div class="p-5">
      <div class="flex flex-col items-center justify-center mb-6">
        <img src="images/bureau-logo.png" alt="Bureau Logo" class="bureau-logo bg-white p-2 rounded-lg w-40"
             onerror="this.src='https://via.placeholder.com/180x60/4f46e5/ffffff?text=Bureau+Logo'">
      </div>
      <ul class="space-y-2">
        <li><a href="dashboard.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-tachometer-alt w-5"></i><span class="ml-3">Dashboard</span></a></li>
        <?php if ($_SESSION['role'] == 'admin'): ?>
          <li><a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-plus-circle w-5"></i><span class="ml-3">Budgets</span></a></li>
          <li><a href="settings.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-building w-5"></i><span class="ml-3">Settings</span></a></li>
          <li><a href="users_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-users w-5"></i><span class="ml-3">Users</span></a></li>
        <?php endif; ?>
        <li><a href="perdium.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-white/20"><i class="fas fa-money-bill-wave w-5"></i><span class="ml-3">Perdium</span></a></li>
        <li><a href="fuel_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-gas-pump w-5"></i><span class="ml-3">Fuel</span></a></li>
        <li><a href="transaction.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-exchange-alt w-5"></i><span class="ml-3">Transactions</span></a></li>
        <li><a href="reports.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-chart-line w-5"></i><span class="ml-3">Reports</span></a></li>
        <li><a href="logout.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-sign-out-alt w-5"></i><span class="ml-3">Logout</span></a></li>
      </ul>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="p-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Fuel Management</h1>
          <p class="text-slate-600 mt-2">Manage fuel transactions and consumption</p>
          <div class="mt-3 bg-indigo-100 rounded-lg p-3 max-w-md info-card">
            <i class="fas fa-user-circle text-indigo-600 mr-2"></i>
            <span class="text-indigo-800 font-semibold">
              Welcome, <?php echo htmlspecialchars($user_name); ?>! (<?php echo ucfirst($_SESSION['role']); ?>)
            </span>
          </div>
        </div>
        <div class="flex items-center space-x-4 mt-4 md:mt-0">
          <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <!-- Message -->
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

      <!-- Fuel Form -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6"><?php echo isset($fuel) ? 'Edit Fuel Transaction' : 'Add New Fuel Transaction'; ?></h2>
        <form id="fuelForm" method="POST" class="space-y-4">
          <?php if (isset($fuel)): ?>
            <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Budget Source Type -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Source Type *</label>
              <select name="budget_type" id="budget_type" class="w-full select2" onchange="onBudgetTypeChange(); loadFuelRemaining();">
                <option value="governmental">Government Budget</option>
                <option value="program">Programs Budget</option>
              </select>
            </div>

            <!-- Budget Owner -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
              <select name="owner_id" id="owner_id" required class="w-full select2" onchange="onOwnerChange();">
                <option value="">Select Owner</option>
                <?php foreach ($owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>"
                          data-p_koox="<?php echo htmlspecialchars($o['p_koox'] ?? ''); ?>"
                          <?php echo (isset($fuel) && $fuel['owner_id'] == $o['id']) || (isset($_POST['owner_id']) && $_POST['owner_id'] == $o['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Program Details -->
            <div class="program-card p-4 rounded-lg">
              <h3 class="text-sm font-medium text-indigo-800 mb-2">Program Details</h3>
              <div class="flex items-center">
                <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-project-diagram text-indigo-600"></i>
                </div>
                <div>
                  <p id="program_p_koox_display" class="text-sm font-medium text-gray-900">-</p>
                  <p id="program_name_display" class="text-xs text-gray-600">Project Koox: -</p>
                </div>
              </div>
            </div>

            <!-- Driver -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Driver Name *</label>
              <input type="text" name="driver_name" value="<?php echo isset($fuel) ? htmlspecialchars($fuel['driver_name']) : (isset($_POST['driver_name']) ? htmlspecialchars($_POST['driver_name']) : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>

            <!-- Plate Number -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number *</label>
              <select name="plate_number" id="plate_number" required class="w-full select2" onchange="fetchVehicleDetails(this.value)">
                <option value="">Select Vehicle</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?php echo $v['plate_no']; ?>" data-model="<?php echo htmlspecialchars($v['model']); ?>"
                    <?php echo (isset($fuel) && $fuel['plate_number'] == $v['plate_no']) || (isset($_POST['plate_number']) && $_POST['plate_number'] == $v['plate_no']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['model']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Vehicle Card -->
            <div class="vehicle-card p-4 rounded-lg">
              <h3 class="text-sm font-medium text-green-800 mb-2">Vehicle Details</h3>
              <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                  <i class="fas fa-car text-green-600"></i>
                </div>
                <div>
                  <p id="vehicle_model_display" class="text-sm font-medium text-gray-900">-</p>
                  <p id="vehicle_consumption_display" class="text-xs text-gray-600">Fuel Consumption: 5km/liter</p>
                  <p id="previous_gauge_display" class="text-xs text-gray-600">Previous Gauge: -</p>
                </div>
              </div>
            </div>

            <!-- Ethiopian Month -->
            <div id="et_month_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full select2" onchange="loadFuelRemaining();">
                <option value="">Select Month</option>
                <?php
                $months = ['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'];
                foreach ($months as $m): ?>
                  <option value="<?php echo $m; ?>"
                    <?php echo (isset($fuel) && $fuel['et_month'] == $m) || (isset($_POST['et_month']) && $_POST['et_month'] == $m) ? 'selected' : ''; ?>>
                    <?php echo $m; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Current Gauge -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Current Gauge *</label>
              <input type="number" step="0.01" name="current_gauge" id="current" value="<?php echo isset($fuel) ? $fuel['current_gauge'] : (isset($_POST['current_gauge']) ? $_POST['current_gauge'] : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>

            <!-- Journey Distance -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (km) *</label>
              <input type="number" step="0.01" name="journey_distance" id="journey" value="<?php echo isset($fuel) ? $fuel['journey_distance'] : (isset($_POST['journey_distance']) ? $_POST['journey_distance'] : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>

            <!-- Fuel Price -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price (per liter) *</label>
              <input type="number" step="0.01" name="fuel_price" id="price" value="<?php echo isset($fuel) ? $fuel['fuel_price'] : (isset($_POST['fuel_price']) ? $_POST['fuel_price'] : $last_price); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>

            <!-- Refuelable -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable Amount (liters)</label>
              <input type="number" step="0.01" name="refuelable_amount" id="refuelable" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>

            <!-- Total Amount -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Total Amount</label>
              <input type="number" step="0.01" name="total_amount" id="total" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>

            <!-- New Gauge -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">New Gauge</label>
              <input type="number" step="0.01" name="new_gauge" id="new_gauge" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>

            <!-- Gauge Gap -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Gauge Gap</label>
              <input type="number" step="0.01" name="gauge_gap" id="gap" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
          </div>

          <!-- Budget Status Cards -->
          <div class="bg-white rounded-xl p-6 shadow-sm mb-6">
            <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Status (Sansii kee Sukutih)</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-blue-800">Monthly Remaining</h3>
                <p class="text-2xl font-bold text-blue-800" id="rem_monthly">0.00</p>
              </div>
              <div class="bg-green-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-green-800">Quarterly Remaining</h3>
                <p class="text-2xl font-bold text-green-600" id="rem_quarterly">0.00</p>
              </div>
              <div class="bg-purple-50 p-4 rounded-lg">
                <h3 class="text-sm font-medium text-purple-800">Yearly Remaining</h3>
                <p class="text-2xl font-bold text-purple-800" id="rem_yearly">0.00</p>
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

      <!-- Filter toolbar (AJAX) -->
      <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
        <div class="grid md:grid-cols-4 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Budget Source</label>
            <select id="flt_type" class="w-full select2" onchange="toggleFilterMonth(); fetchFuelList();">
              <option value="governmental">Government</option>
              <option value="program">Programs</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Owner</label>
            <select id="flt_owner" class="w-full select2" onchange="fetchFuelList();">
              <option value="">Any Owner</option>
              <?php foreach ($owners as $o): ?>
                <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="flt_month_box">
            <label class="block text-sm font-medium mb-1">Month (Gov only)</label>
            <select id="flt_month" class="w-full select2" onchange="fetchFuelList();">
              <option value="">Any Month</option>
              <?php foreach (['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'] as $m): ?>
                <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Plate</label>
            <select id="flt_plate" class="w-full select2" onchange="fetchFuelList();">
              <option value="">Any Plate</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?php echo htmlspecialchars($v['plate_no']); ?>"><?php echo htmlspecialchars($v['plate_no'].' - '.$v['model']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Live Search Box -->
      <div class="mb-4">
        <input type="text" id="searchInput" placeholder="Search transactions..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" onkeyup="filterTransactions()">
      </div>

      <!-- Fuel Transactions Table -->
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
    function calculateFuel() {
      const current = parseFloat($('#current').val()) || 0;
      const journey = parseFloat($('#journey').val()) || 0;
      const price = parseFloat($('#price').val()) || 0;
      const refuel = journey / 5;
      const total = refuel * price;
      const newG = current + journey;
      const gap = newG - current;
      $('#refuelable').val(refuel.toFixed(2));
      $('#total').val(total.toFixed(2));
      $('#new_gauge').val(newG.toFixed(2));
      $('#gap').val(gap.toFixed(2));
    }

    function onBudgetTypeChange(){
      const t = $('#budget_type').val();
      if (t === 'program') {
        $('#et_month_box').hide();
        $('#et_month').prop('required', false);
        // hide monthly/quarterly cards visually (values also handled in loadFuelRemaining)
        $('.bg-blue-50').hide();
        $('.bg-green-50').hide();
      } else {
        $('#et_month_box').show();
        $('#et_month').prop('required', true);
        $('.bg-blue-50').show();
        $('.bg-green-50').show();
      }
      loadFuelRemaining();
    }

    function onOwnerChange(){
      const selectedOption = $('#owner_id').find('option:selected');
      const p_koox = selectedOption.data('p_koox') || '-';
      const ownerName = selectedOption.text().split(' - ')[1] || selectedOption.text();
      $('#program_p_koox_display').text(p_koox || '-');
      $('#program_name_display').text('Project Koox: ' + ownerName);
      loadFuelRemaining();
    }

    function loadFuelRemaining(){
      const ownerId = $('#owner_id').val();
      const etMonth = $('#et_month').val();
      const year = new Date().getFullYear() - 8;
      const type = $('#budget_type').val();

      if (!ownerId) {
        $('#rem_monthly').text('0.00');
        $('#rem_quarterly').text('0.00');
        $('#rem_yearly').text('0.00');
        return;
      }
      if (type === 'program') {
        $.get('get_remaining_program.php', { owner_id: ownerId, year: year }, function(resp){
          try {
            const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_yearly').text(parseFloat(j.remaining_yearly || 0).toFixed(2));
            $('#rem_monthly').text('0.00'); $('#rem_quarterly').text('0.00');
          } catch (e) { $('#rem_yearly').text('0.00'); }
        }).fail(()=>$('#rem_yearly').text('0.00'));
      } else {
        if (!etMonth) { $('#rem_monthly').text('0.00'); $('#rem_quarterly').text('0.00'); $('#rem_yearly').text('0.00'); return; }
        $.get('get_remaining_fuel.php', { owner_id: ownerId, code_id: 5, month: etMonth, year: year }, function(resp){
          try {
            const rem = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_monthly').text(parseFloat(rem.remaining_monthly || 0).toFixed(2));
            $('#rem_quarterly').text(parseFloat(rem.remaining_quarterly || 0).toFixed(2));
            $('#rem_yearly').text(parseFloat(rem.remaining_yearly || 0).toFixed(2));
          } catch (e) {
            $('#rem_monthly').text('0.00'); $('#rem_quarterly').text('0.00'); $('#rem_yearly').text('0.00');
          }
        }).fail(()=>{ $('#rem_monthly').text('0.00'); $('#rem_quarterly').text('0.00'); $('#rem_yearly').text('0.00'); });
      }
    }

    function toggleFilterMonth(){
      const t = $('#flt_type').val();
      if (t === 'program') { $('#flt_month_box').hide(); } else { $('#flt_month_box').show(); }
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
            html += `
              <tr>
                <td class="px-4 py-4 text-sm text-gray-900">${(f.date||'').replace('T',' ').slice(0,19)}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.owner_code || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.driver_name || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.plate_number || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900 ethio-font">${f.et_month || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${Number(f.total_amount||0).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                <td class="px-4 py-4 text-sm">
                  <div class="flex space-x-2">
                    <a href="${printUrl}" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                      <i class="fas fa-print mr-1"></i> Print
                    </a>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a href="?action=edit&id=\${f.id}" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200">
                      <i class="fas fa-edit mr-1"></i> Edit
                    </a>
                    <a href="?action=delete&id=\${f.id}" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this transaction?')">
                      <i class="fas fa-trash mr-1"></i> Delete
                    </a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>`;
          });
          $('#transactionsTable').html(html);
          filterTransactions(); // apply search box filter
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

    function fetchVehicleDetails(plateNumber) {
      if (!plateNumber) {
        $('#vehicle_model_display').text('-');
        $('#previous_gauge_display').text('Previous Gauge: -');
        return;
      }
      const selectedOption = $('#plate_number').find('option:selected');
      const model = selectedOption.data('model') || '-';
      $('#vehicle_model_display').text(model);

      $.get('get_last_gauge.php', { plate_number: plateNumber }, function(data) {
        try {
          const resp = typeof data === 'string' ? JSON.parse(data) : data;
          $('#previous_gauge_display').text('Previous Gauge: ' + (resp.last_gauge || '0'));
          if (!$('#current').val()) {
            $('#current').val(resp.last_gauge || 0);
            calculateFuel();
          }
        } catch (e) {
          $('#previous_gauge_display').text('Previous Gauge: -');
        }
      }).fail(function() {
        $('#previous_gauge_display').text('Previous Gauge: -');
      });
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

      onBudgetTypeChange();
      calculateFuel();
      loadFuelRemaining();

      // Initialize filters and list
      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      toggleFilterMonth();
      fetchFuelList();
    });

    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });
  </script>
</body>
</html>