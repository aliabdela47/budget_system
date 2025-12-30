<?php
require_once 'includes/init.php';
require_once 'includes/sidebar.php';

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
$owners = $pdo->query("SELECT * FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY plate_no")->fetchAll(PDO::FETCH_ASSOC);

// Last price
$last_price = $pdo->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")->fetchColumn() ?: 0;

// Editing?
$fuel = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $fuel = $stmt->fetch(PDO::FETCH_ASSOC);
}

function ecYear(): int { return (int)date('Y') - 8; }

// Handle POST (Add/Update)
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

    // Get the correct owner table based on budget type
    $owner_table = $budget_type === 'program' ? 'p_budget_owners' : 'budget_owners';
    
    // p_koox from owners
    $stmt = $pdo->prepare("SELECT p_koox FROM $owner_table WHERE id = ?");
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
                // Deduct from program yearly budgets (owner-level)
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

                // Insert/Update tx
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
                // Governmental (original logic: monthly first, fallback yearly)
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM budgets
                    WHERE owner_id = ?
                      AND code_id = ?
                      AND year = ?
                      AND month = ?
                ");
                $stmt->execute([$owner_id, $fuel_code_id, $year, $et_month]);
                $budget = $stmt->fetch(PDO::FETCH_ASSOC);
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
                    $budget_yearly = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$budget_yearly) throw new Exception('No fuel budget allocated.');
                    $new_rem_year = $budget_yearly['remaining_yearly'] - $total_amount;
                    if ($new_rem_year < 0) throw new Exception('Insufficient remaining yearly budget for fuel.');
                    $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                    $stmt->execute([$new_rem_year, $budget_yearly['id']]);
                }

                // Insert/update tx
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
    .row-click { cursor:pointer; }
  </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
  <div class="main-content" id="mainContent">
    <div class="p-6">
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

      <?php if ($message): ?>
        <div id="message" class="fade-out mb-6 p-4 rounded-lg <?php echo $message_type == 'error' ? 'bg-red-100 text-red-700' : ($message_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'); ?>">
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
            <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>

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

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Vehicle Plate No. *</label>
              <select name="plate_number" id="plate_number" required class="w-full select2">
                <option value="">Select Vehicle</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?php echo htmlspecialchars($v['plate_no']); ?>" <?php echo isset($fuel) && $fuel['plate_number']===$v['plate_no'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Driver's Name *</label>
              <input type="text" name="driver_name" id="driver_name" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" value="<?php echo htmlspecialchars($fuel['driver_name'] ?? ''); ?>" required>
            </div>
            
            <div id="et_month_div" class="hidden">
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" class="w-full select2">
                <?php for ($i = 1; $i <= 13; $i++): ?>
                  <option value="<?php echo $i; ?>" <?php echo isset($fuel) && $fuel['et_month']==$i ? 'selected' : ''; ?>>
                    <?php echo get_month_name($i); ?>
                  </option>
                <?php endfor; ?>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price (Birr/Litre) *</label>
              <input type="number" step="0.01" name="fuel_price" id="fuel_price" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" value="<?php echo htmlspecialchars($fuel['fuel_price'] ?? $last_price); ?>" required>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Current Gauge (Litre) *</label>
              <input type="number" step="0.01" name="current_gauge" id="current_gauge" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" value="<?php echo htmlspecialchars($fuel['current_gauge'] ?? ''); ?>" required>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable Amount (Birr)</label>
              <input type="number" step="0.01" name="refuelable_amount" id="refuelable_amount" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" value="<?php echo htmlspecialchars($fuel['refuelable_amount'] ?? ''); ?>">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (KM)</label>
              <input type="number" step="0.01" name="journey_distance" id="journey_distance" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" value="<?php echo htmlspecialchars($fuel['journey_distance'] ?? ''); ?>">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">New Gauge (Litre) *</label>
              <input type="number" step="0.01" name="new_gauge" id="new_gauge" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" value="<?php echo htmlspecialchars($fuel['new_gauge'] ?? ''); ?>" required>
            </div>
          </div>

          <div class="flex items-center space-x-4">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 shadow-md">
              <i class="fas fa-save mr-2"></i> <?php echo isset($fuel) ? 'Update' : 'Add'; ?> Transaction
            </button>
            <?php if (isset($fuel)): ?>
              <a href="fuel_management.php" class="bg-gray-300 hover:bg-gray-400 text-slate-700 font-bold py-2 px-4 rounded-lg transition duration-300 ease-in-out shadow-md">
                <i class="fas fa-times-circle mr-2"></i> Cancel
              </a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="p-6 bg-white rounded-xl shadow-sm info-card">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-500 text-white mr-4">
              <i class="fas fa-wallet fa-xl"></i>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-500">Total Fuel Amount</p>
              <p id="total_amount_display" class="text-2xl font-bold text-slate-800">Calculating...</p>
            </div>
          </div>
        </div>
        <div class="p-6 bg-white rounded-xl shadow-sm program-card">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-500 text-white mr-4">
              <i class="fas fa-gas-pump fa-xl"></i>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-500">Remaining Budget</p>
              <p id="remaining_budget_display" class="text-2xl font-bold text-slate-800">Calculating...</p>
            </div>
          </div>
        </div>
        <div class="p-6 bg-white rounded-xl shadow-sm vehicle-card">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-500 text-white mr-4">
              <i class="fas fa-tachometer-alt fa-xl"></i>
            </div>
            <div>
              <p class="text-sm font-semibold text-slate-500">Last Gauge Reading</p>
              <p id="last_gauge_display" class="text-2xl font-bold text-slate-800">Calculating...</p>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-4">Fuel Transaction List</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Filter by Type</label>
            <select id="flt_type" class="w-full select2">
              <option value="">All</option>
              <option value="governmental">Government Budget</option>
              <option value="program">Programs Budget</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Filter by Owner</label>
            <select id="flt_owner" class="w-full select2">
              <option value="">All</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Filter by Vehicle</label>
            <select id="flt_plate" class="w-full select2">
              <option value="">All</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?php echo htmlspecialchars($v['plate_no']); ?>">
                  <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="flt_month_div" class="hidden">
            <label class="block text-sm font-medium text-slate-700 mb-1">Filter by Month</label>
            <select id="flt_month" class="w-full select2">
              <option value="">All</option>
              <?php for ($i = 1; $i <= 13; $i++): ?>
                <option value="<?php echo $i; ?>">
                  <?php echo get_month_name($i); ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
          <table class="w-full text-sm text-left text-slate-500">
            <thead class="text-xs text-slate-700 uppercase bg-slate-50">
              <tr>
                <th scope="col" class="py-3 px-6">ID</th>
                <th scope="col" class="py-3 px-6">Date</th>
                <th scope="col" class="py-3 px-6">Budget Owner</th>
                <th scope="col" class="py-3 px-6">Plate No.</th>
                <th scope="col" class="py-3 px-6">Current Gauge</th>
                <th scope="col" class="py-3 px-6">New Gauge</th>
                <th scope="col" class="py-3 px-6">Total Amount</th>
                <th scope="col" class="py-3 px-6">Action</th>
              </tr>
            </thead>
            <tbody id="fuelTableBody">
              </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.34/moment-timezone-with-data.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.34/moment-timezone-with-data-2012-2022.min.js"></script>

  <script>
    // Flag to prevent re-triggering logic during form fill
    let filling = false;
    let isEdit = <?php echo isset($fuel) ? 'true' : 'false'; ?>;
    const initialFuel = <?php echo isset($fuel) ? json_encode($fuel) : 'null'; ?>;
    
    // Set up Select2
    $('.select2').select2({
      placeholder: 'Select an option',
      allowClear: true
    });

    // Helper to format currency
    function formatBirr(amount) {
      if (amount === null || amount === undefined) return 'N/A';
      return new Intl.NumberFormat('en-ET', {
        style: 'currency',
        currency: 'ETB',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(amount).replace('ETB', 'ብር');
    }

    // Helper to get Ethiopian month name
    function getEthiopianMonthName(month) {
      const ethiopianMonths = ["", "Meskerem", "Tikimt", "Hidar", "Tahsas", "Tir", "Yekatit", "Megabit", "Miyazya", "Genbot", "Sene", "Hamle", "Nehase", "Pagume"];
      return ethiopianMonths[month] || "N/A";
    }

    // Function to show/hide the Ethiopian Month dropdown
    function toggleMonthDropdown(budget_type) {
      if (budget_type === 'governmental') {
        $('#et_month_div').show();
      } else {
        $('#et_month_div').hide();
      }
    }

    // Function to toggle filter month dropdown
    function toggleFilterMonth() {
      if ($('#flt_type').val() === 'governmental') {
        $('#flt_month_div').show();
      } else {
        $('#flt_month_div').hide();
      }
    }

    // New function to fetch owners dynamically
    function fetchAndPopulateOwners() {
      const budget_type = $('#budget_type').val();
      const ownerDropdown = $('#owner_id');
      const filterOwnerDropdown = $('#flt_owner');
      ownerDropdown.empty().append('<option value="">Loading...</option>').prop('disabled', true);
      filterOwnerDropdown.empty().append('<option value="">All</option>').prop('disabled', false);

      $.ajax({
        url: 'ajax_get_owners.php',
        type: 'GET',
        data: { budget_type: budget_type },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            ownerDropdown.empty();
            filterOwnerDropdown.empty().append('<option value="">All</option>');
            if (response.owners.length > 0) {
              ownerDropdown.append('<option value="">Select a Budget Owner</option>');
              $.each(response.owners, function(i, owner) {
                ownerDropdown.append(
                  $('<option></option>').val(owner.id).text(owner.code + ' - ' + owner.name)
                );
                filterOwnerDropdown.append(
                  $('<option></option>').val(owner.id).text(owner.code + ' - ' + owner.name)
                );
              });
            } else {
              ownerDropdown.append('<option value="">No Owners Found</option>');
            }
          } else {
            ownerDropdown.empty().append('<option value="">Error fetching owners</option>');
          }
        },
        error: function() {
          ownerDropdown.empty().append('<option value="">Error fetching owners</option>');
        },
        complete: function() {
          ownerDropdown.prop('disabled', false);
          // If in edit mode, select the correct owner after populating
          if (isEdit && initialFuel) {
            ownerDropdown.val(initialFuel.owner_id).trigger('change.select2');
          }
        }
      });
    }

    // Function to load the remaining budget
    function loadFuelRemaining() {
      const owner_id = $('#owner_id').val();
      const budget_type = $('#budget_type').val();
      const et_month = $('#et_month').val();
      
      if (!owner_id) {
        $('#remaining_budget_display').text('N/A').removeClass('text-green-700 text-red-700').addClass('text-slate-800');
        return;
      }

      $.ajax({
        url: 'ajax_get_budget.php',
        type: 'GET',
        data: { 
          owner_id: owner_id,
          code_id: 5, // Fuel code id
          budget_type: budget_type,
          et_month: et_month
        },
        success: function(data) {
          const remaining = parseFloat(data.remaining);
          $('#remaining_budget_display').text(formatBirr(remaining));
          if (remaining < 0) {
            $('#remaining_budget_display').removeClass('text-slate-800').addClass('text-red-700').removeClass('text-green-700');
          } else {
            $('#remaining_budget_display').removeClass('text-slate-800').addClass('text-green-700').removeClass('text-red-700');
          }
        },
        error: function() {
          $('#remaining_budget_display').text('Error').removeClass('text-green-700').addClass('text-red-700');
        }
      });
    }

    // Function to calculate fuel costs
    function calculateFuel() {
        const current_gauge = parseFloat($('#current_gauge').val()) || 0;
        const refuelable_amount = parseFloat($('#refuelable_amount').val()) || 0;
        const fuel_price = parseFloat($('#fuel_price').val()) || 0;
        
        let new_gauge = current_gauge + (refuelable_amount / fuel_price);
        let gauge_gap = (refuelable_amount / fuel_price);
        let total_amount = refuelable_amount;

        $('#new_gauge').val(new_gauge.toFixed(2));
        $('#total_amount_display').text(formatBirr(total_amount));
        $('#total_amount').val(total_amount);
        $('#gauge_gap').val(gauge_gap);
    }
    
    function refreshGrandTotals() {
      // Refresh overall totals for the current filters
      const budget_type = $('#flt_type').val();
      const owner_id = $('#flt_owner').val();
      const plate_number = $('#flt_plate').val();
      const et_month = $('#flt_month').val();

      $.ajax({
        url: 'ajax_get_fuel_totals.php',
        type: 'GET',
        data: {
          budget_type: budget_type,
          owner_id: owner_id,
          plate_number: plate_number,
          et_month: et_month
        },
        success: function(data) {
          $('#total_amount_display').text(formatBirr(data.total_amount));
        }
      });
    }

    function fetchFuelList() {
      const budget_type = $('#flt_type').val();
      const owner_id = $('#flt_owner').val();
      const plate_number = $('#flt_plate').val();
      const et_month = $('#flt_month').val();

      $.ajax({
        url: 'ajax_get_fuel_list.php',
        type: 'GET',
        data: {
          budget_type: budget_type,
          owner_id: owner_id,
          plate_number: plate_number,
          et_month: et_month
        },
        success: function(response) {
          $('#fuelTableBody').empty();
          if (response.length > 0) {
            $.each(response, function(i, fuel) {
              const row = $('<tr>').addClass('bg-white border-b hover:bg-slate-50 row-click').attr('data-json', encodeURIComponent(JSON.stringify(fuel)));
              row.append($('<td>').addClass('py-4 px-6').text(fuel.id));
              row.append($('<td>').addClass('py-4 px-6').text(fuel.created_at.substring(0, 10)));
              row.append($('<td>').addClass('py-4 px-6').text(fuel.owner_code + ' - ' + fuel.owner_name));
              row.append($('<td>').addClass('py-4 px-6').text(fuel.plate_number));
              row.append($('<td>').addClass('py-4 px-6').text(fuel.current_gauge));
              row.append($('<td>').addClass('py-4 px-6').text(fuel.new_gauge));
              row.append($('<td>').addClass('py-4 px-6').text(formatBirr(fuel.total_amount)));

              const actionCell = $('<td>').addClass('py-4 px-6 flex space-x-2');
              actionCell.append($('<a>').attr('href', `fuel_management.php?action=edit&id=${fuel.id}`).addClass('text-blue-600 hover:text-blue-900').html('<i class="fas fa-edit"></i>'));
              actionCell.append($('<a>').attr('href', `fuel_management.php?action=delete&id=${fuel.id}`).addClass('text-red-600 hover:text-red-900').attr('onclick', "return confirm('Are you sure you want to delete this transaction?');").html('<i class="fas fa-trash-alt"></i>'));
              row.append(actionCell);
              $('#fuelTableBody').append(row);
            });
          } else {
            $('#fuelTableBody').append('<tr><td colspan="8" class="text-center py-4 text-slate-500">No fuel transactions found.</td></tr>');
          }
        },
        error: function() {
          $('#fuelTableBody').empty().append('<tr><td colspan="8" class="text-center py-4 text-red-500">Error loading data.</td></tr>');
        }
      });
    }

    function fetchVehicleDetails(plate_no) {
      if (!plate_no) {
        $('#last_gauge_display').text('N/A').removeClass('text-green-700').addClass('text-slate-800');
        return;
      }
      $.ajax({
        url: 'ajax_get_last_fuel_gauge.php',
        type: 'GET',
        data: { plate_no: plate_no },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            $('#last_gauge_display').text(response.last_gauge + ' Litre');
            $('#last_gauge_display').removeClass('text-slate-800').addClass('text-green-700');
          } else {
            $('#last_gauge_display').text('N/A').removeClass('text-green-700').addClass('text-slate-800');
          }
        }
      });
    }

    function syncFiltersFromForm() {
      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      $('#flt_plate').val($('#plate_number').val()).trigger('change.select2');
      $('#flt_month').val($('#et_month').val()).trigger('change.select2');
    }

    function fillFormFromRow(d) {
      filling = true;
      $('#budget_type').val(d.budget_type).trigger('change.select2');
      setTimeout(() => { // Small delay to allow owner dropdown to populate via AJAX
        $('#owner_id').val(d.owner_id).trigger('change.select2');
        $('#plate_number').val(d.plate_number).trigger('change.select2');
        $('#driver_name').val(d.driver_name);
        $('#fuel_price').val(d.fuel_price);
        $('#current_gauge').val(d.current_gauge);
        $('#refuelable_amount').val(d.refuelable_amount);
        $('#journey_distance').val(d.journey_distance);
        $('#new_gauge').val(d.new_gauge);
        $('#et_month').val(d.et_month).trigger('change.select2');
        $('#action').val('update');
        $('input[name="id"]').val(d.id);
        isEdit = true;
        
        calculateFuel();
        loadFuelRemaining();
        fetchVehicleDetails(d.plate_number);
        refreshGrandTotals();
        syncFiltersFromForm();
        
        filling = false;
      }, 500); // Wait for the AJAX call to complete
    }

    // Event handlers
    $('#budget_type').on('change', function() {
      const budget_type = $(this).val();
      toggleMonthDropdown(budget_type);
      fetchAndPopulateOwners();
      loadFuelRemaining();
    });

    $('#owner_id').on('change', function() {
      if (!filling) loadFuelRemaining();
    });

    $('#et_month').on('change', function() {
      if (!filling) loadFuelRemaining();
    });

    $('#current_gauge, #refuelable_amount, #fuel_price').on('input', function() {
      calculateFuel();
    });

    $('#flt_type').on('change', function() {
      toggleFilterMonth();
      fetchAndPopulateOwners(); // This will also update the filter dropdown for owners
      fetchFuelList();
      refreshGrandTotals();
    });
    
    $('#flt_owner').on('change', function() {
      fetchFuelList();
      refreshGrandTotals();
    });

    $('#flt_plate').on('change', function() {
      fetchFuelList();
      refreshGrandTotals();
    });

    $('#flt_month').on('change', function() {
      fetchFuelList();
      refreshGrandTotals();
    });

    // Handle row clicks for editing
    $(document).on('click', 'tr.row-click', function(e){
      if ($(e.target).closest('a,button').length) return;
      const dataJson = $(this).attr('data-json');
      if (!dataJson) return;
      try {
        const d = JSON.parse(decodeURIComponent(dataJson));
        fillFormFromRow(d);
      } catch (err) {
        console.error('row parse error', err);
      }
    });

    // --- INITIALIZE PAGE ---
    $(document).ready(function() {
      const isEdit = <?php echo isset($fuel) ? 'true' : 'false'; ?>;
      
      toggleMonthDropdown($('#budget_type').val());
      fetchAndPopulateOwners(); // Fetch owners for the initially selected budget type
      
      if (isEdit) {
        // For edit mode, most fields are populated by PHP `value` attributes.
        // The AJAX call will handle selecting the owner.
        // We just need to trigger the handlers to populate dependent data.
        toggleMonthDropdown(initialFuel.budget_type);
        $('#owner_id').val(initialFuel.owner_id);
        $('#plate_number').val(initialFuel.plate_number).trigger('change.select2');
        $('#et_month').val(initialFuel.et_month).trigger('change.select2');
        fetchVehicleDetails(initialFuel.plate_number);
        calculateFuel();
        loadFuelRemaining();
        refreshGrandTotals();
        syncFiltersFromForm();
      } else {
        // For a new form
        fetchVehicleDetails($('#plate_number').val());
        calculateFuel();
        loadFuelRemaining();
        refreshGrandTotals();
        syncFiltersFromForm();
      }
      fetchFuelList();
    });

    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });
  </script>
</body>
</html>
