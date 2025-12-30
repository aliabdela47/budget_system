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

// Add budget_type column to perdium_transactions if it doesn't exist
try { 
    $pdo->query("ALTER TABLE perdium_transactions ADD COLUMN budget_type ENUM('governmental','program') NOT NULL DEFAULT 'governmental' AFTER id"); 
} catch (PDOException $e) {}

// Ensure p_koox column exists
try { 
    $pdo->query("ALTER TABLE perdium_transactions ADD COLUMN p_koox VARCHAR(255) AFTER budget_owner_id"); 
} catch (PDOException $e) {}

// Get owners from both tables
$gov_owners = $pdo->query("SELECT * FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$prog_owners = $pdo->query("SELECT * FROM p_budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

// Get employees
$employees = $pdo->query("SELECT * FROM emp_list ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get cities with proper encoding
$cities = $pdo->query("SELECT * FROM cities ORDER BY name_english")->fetchAll(PDO::FETCH_ASSOC);

// Default perdium rate
$perdium_rate = 100; // Default value

// Last perdium transaction for reference
$last_perdium = $pdo->query("SELECT * FROM perdium_transactions ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Editing?
$perdium = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM perdium_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $perdium = $stmt->fetch(PDO::FETCH_ASSOC);
}

function ecYear(): int { return (int)date('Y') - 8; }

// Handle POST (Add/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['employee_id'] ?? '';
    $owner_id = $_POST['owner_id'] ?? '';
    $budget_type = ($_POST['budget_type'] ?? 'governmental') === 'program' ? 'program' : 'governmental';
    $city_id = $_POST['city_id'] ?? '';
    $perdium_rate = (float)($_POST['perdium_rate'] ?? 0);
    $total_days = (int)($_POST['total_days'] ?? 0);
    $departure_date = $_POST['departure_date'] ?? '';
    $arrival_date = $_POST['arrival_date'] ?? '';
    $et_month = $budget_type === 'program' ? '' : ($_POST['et_month'] ?? '');
    
    // Calculate total amount
   // $total_amount = $perdium_rate * $total_days;
    
        // Calculate perdium amount using the formula
    $A = ($perdium_rate * 0.1) + ($perdium_rate * 0.25) + ($perdium_rate * 0.25);
    $B = $A * ($total_days - 2);
    $C = ($perdium_rate * 0.1) + ($perdium_rate * 0.25);
    $D = $perdium_rate * ($total_days - 1) * 0.4;
    $total_amount = $A + $B + $C + $D;

    // Get the correct owner table based on budget type
    $owner_table = $budget_type === 'program' ? 'p_budget_owners' : 'budget_owners';
    
    // p_koox from owners
    $stmt = $pdo->prepare("SELECT p_koox FROM $owner_table WHERE id = ?");
    $stmt->execute([$owner_id]);
    $budget_owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $p_koox = $budget_owner['p_koox'] ?? '';

    $pdo->beginTransaction();
    try {
        $year = ecYear();
        $perdium_code_id = 6; // Ayroh Assentah (Perdium)

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

            // Insert/Update transaction
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("
                    UPDATE perdium_transactions
                    SET budget_type='program',
                        employee_id=?, budget_owner_id=?, p_koox=?, city_id=?, 
                        perdium_rate=?, total_days=?, departure_date=?, arrival_date=?,
                        total_amount=?, et_month=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $employee_id, $owner_id, $p_koox, $city_id,
                    $perdium_rate, $total_days, $departure_date, $arrival_date,
                    $total_amount, '', $_POST['id']
                ]);
                $_SESSION['message'] = 'Program perdium transaction updated';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO perdium_transactions (
                        budget_type, employee_id, budget_owner_id, p_koox, city_id, 
                        perdium_rate, total_days, departure_date, arrival_date,
                        total_amount, et_month
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    'program', $employee_id, $owner_id, $p_koox, $city_id,
                    $perdium_rate, $total_days, $departure_date, $arrival_date,
                    $total_amount, ''
                ]);
                $_SESSION['message'] = 'Program perdium transaction added';
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
            $stmt->execute([$owner_id, $perdium_code_id, $year, $et_month]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($budget) {
                $new_rem_month = $budget['remaining_monthly'] - $total_amount;
                if ($new_rem_month < 0) throw new Exception('Insufficient remaining monthly budget for perdium.');
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
                $stmt->execute([$owner_id, $perdium_code_id, $year]);
                $budget_yearly = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$budget_yearly) throw new Exception('No perdium budget allocated.');
                $new_rem_year = $budget_yearly['remaining_yearly'] - $total_amount;
                if ($new_rem_year < 0) throw new Exception('Insufficient remaining yearly budget for perdium.');
                $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                $stmt->execute([$new_rem_year, $budget_yearly['id']]);
            }

            // Insert/update transaction
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("
                    UPDATE perdium_transactions
                    SET budget_type='governmental',
                        employee_id=?, budget_owner_id=?, p_koox=?, city_id=?, 
                        perdium_rate=?, total_days=?, departure_date=?, arrival_date=?,
                        total_amount=?, et_month=?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $employee_id, $owner_id, $p_koox, $city_id,
                    $perdium_rate, $total_days, $departure_date, $arrival_date,
                    $total_amount, $et_month, $_POST['id']
                ]);
                $_SESSION['message'] = 'Perdium transaction updated';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO perdium_transactions (
                        budget_type, employee_id, budget_owner_id, p_koox, city_id, 
                        perdium_rate, total_days, departure_date, arrival_date,
                        total_amount, et_month
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    'governmental', $employee_id, $owner_id, $p_koox, $city_id,
                    $perdium_rate, $total_days, $departure_date, $arrival_date,
                    $total_amount, $et_month
                ]);
                $_SESSION['message'] = 'Perdium transaction added';
            }
        }

        $pdo->commit();
        $_SESSION['message_type'] = 'success';
        header('Location: perdium.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// Delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM perdium_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['message'] = 'Perdium transaction deleted';
    $_SESSION['message_type'] = 'success';
    header('Location: perdium.php'); exit;
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
  <title>Perdium Management - Budget System</title>
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
    .employee-card{background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border-left:4px solid #22c55e}
    .row-click { cursor:pointer; }
  </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
  <!-- Sidebar -->


  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="p-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Perdium Management</h1>
          <p class="text-slate-600 mt-2">Manage perdium transactions and expenses</p>
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

      <!-- Perdium Form -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6"><?php echo isset($perdium) ? 'Edit Perdium Transaction' : 'Add New Perdium Transaction'; ?></h2>
        <form id="perdiumForm" method="POST" class="space-y-4" onsubmit="return validateBeforeSubmit();">
          <?php if (isset($perdium)): ?>
            <input type="hidden" name="id" value="<?php echo $perdium['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>

          <!-- Top row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Budget Source Type -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Source Type *</label>
              <select name="budget_type" id="budget_type" class="w-full select2" onchange="updateOwnerOptions()">
                <option value="governmental" <?php echo isset($perdium) ? ($perdium['budget_type']==='governmental' ? 'selected' : '') : 'selected'; ?>>Government Budget</option>
                <option value="program" <?php echo isset($perdium) && $perdium['budget_type']==='program' ? 'selected' : ''; ?>>Programs Budget</option>
              </select>
            </div>

            <!-- Budget Owner -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
              <select name="owner_id" id="owner_id" required class="w-full select2">
                <option value="">Select Owner</option>
                <?php foreach ($gov_owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>" data-budget-type="governmental" data-p_koox="<?php echo htmlspecialchars($o['p_koox'] ?? ''); ?>"
                    <?php
                    $sel = false;
                    if (isset($perdium) && $perdium['budget_type']=='governmental' && $perdium['budget_owner_id'] == $o['id']) $sel = true;
                    if (isset($_POST['owner_id']) && $_POST['owner_id'] == $o['id'] && ($_POST['budget_type'] ?? 'governmental') == 'governmental') $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
                <?php foreach ($prog_owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>" data-budget-type="program" data-p_koox="<?php echo htmlspecialchars($o['p_koox'] ?? ''); ?>"
                    <?php
                    $sel = false;
                    if (isset($perdium) && $perdium['budget_type']=='program' && $perdium['budget_owner_id'] == $o['id']) $sel = true;
                    if (isset($_POST['owner_id']) && $_POST['owner_id'] == $o['id'] && ($_POST['budget_type'] ?? 'governmental') == 'program') $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Program/Owner Card -->
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
          </div>

          <!-- Second row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Employee -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Employee *</label>
              <select name="employee_id" id="employee_id" required class="w-full select2">
                <option value="">Select Employee</option>
                <?php foreach ($employees as $e): ?>
                  <option value="<?php echo $e['id']; ?>"
                    data-salary="<?php echo $e['salary']; ?>"
                    data-position="<?php echo htmlspecialchars($e['taamagoli'] ?? ''); ?>"
                    data-department="<?php echo htmlspecialchars($e['directorate'] ?? ''); ?>"
                    <?php
                    $sel = false;
                    if (isset($perdium) && $perdium['employee_id'] == $e['id']) $sel = true;
                    if (isset($_POST['employee_id']) && $_POST['employee_id'] == $e['id']) $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($e['name'] . ' - ' . $e['taamagoli']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- City -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Destination City *</label>
              <select name="city_id" id="city_id" required class="w-full select2">
                <option value="">Select City</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?php echo $c['id']; ?>"
                    data-rate-low="<?php echo $c['rate_low']; ?>"
                    data-rate-medium="<?php echo $c['rate_medium']; ?>"
                    data-rate-high="<?php echo $c['rate_high']; ?>"
                    <?php
                    $sel = false;
                    if (isset($perdium) && $perdium['city_id'] == $c['id']) $sel = true;
                    if (isset($_POST['city_id']) && $_POST['city_id'] == $c['id']) $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($c['name_amharic']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Ethiopian Month (Gov only) -->
            <div id="et_month_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full select2">
                <option value="">Select Month</option>
                <?php foreach (['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'] as $m): ?>
                  <option value="<?php echo $m; ?>"
                    <?php
                      $sel = false;
                      if (isset($perdium) && $perdium['et_month'] == $m && $perdium['budget_type']!=='program') $sel = true;
                      if (isset($_POST['et_month']) && $_POST['et_month'] == $m) $sel = true;
                      echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo $m; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Employee card -->
          <div class="employee-card p-4 rounded-lg">
            <h3 class="text-sm font-medium text-green-800 mb-2">Employee Details</h3>
            <div class="flex items-center">
              <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-user-tie text-green-600"></i>
              </div>
              <div>
                <p id="employee_position_display" class="text-sm font-medium text-gray-900">-</p>
                <p id="employee_department_display" class="text-xs text-gray-600">Department: -</p>
                <p id="employee_salary_display" class="text-xs text-gray-600">Salary: -</p>
              </div>
            </div>
          </div>

          <!-- Dates and Rate -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Departure Date *</label>
              <input type="date" name="departure_date" id="departure_date" value="<?php
                echo isset($perdium) ? $perdium['departure_date'] : (isset($_POST['departure_date']) ? $_POST['departure_date'] : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Arrival Date *</label>
              <input type="date" name="arrival_date" id="arrival_date" value="<?php
                echo isset($perdium) ? $perdium['arrival_date'] : (isset($_POST['arrival_date']) ? $_POST['arrival_date'] : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Total Perdium Days *</label>
              <input type="number" name="total_days" id="total_days" value="<?php
                echo isset($perdium) ? $perdium['total_days'] : (isset($_POST['total_days']) ? $_POST['total_days'] : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="updateDates()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Perdium Rate (per day) *</label>
              <input type="number" step="0.01" name="perdium_rate" id="perdium_rate" value="<?php
                echo isset($perdium) ? $perdium['perdium_rate'] : (isset($_POST['perdium_rate']) ? $_POST['perdium_rate'] : $perdium_rate);
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculatePerdium()">
            </div>
          </div>

          <!-- Calculated values -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-xl p-4 bg-gradient-to-r from-sky-100 to-sky-50 border border-sky-200 shadow-sm">
              <div class="text-sm text-sky-700 font-medium">Total Days</div>
              <div id="total_days_card" class="text-2xl font-extrabold text-sky-900 mt-1">0</div>
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="text-sm text-amber-700 font-medium">Total Amount</div>
              <div id="total_amount_card" class="text-2xl font-extrabold text-amber-900 mt-1">0.00 ብር</div>
              <input type="hidden" name="total_amount" id="total_amount" value="0">
            </div>
          </div>

          <!-- Availability cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div id="rem_monthly_card" class="rounded-xl p-5 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-amber-200 text-amber-800"><i class="fas fa-calendar-alt"></i></div>
                <div>
                  <div class="text-sm text-amber-700 font-medium">Monthly Perdium Budget</div>
                  <div id="rem_monthly" class="text-2xl font-extrabold text-amber-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <div class="rounded-xl p-5 bg-gradient-to-r from-emerald-100 to-emerald-50 border border-emerald-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-emerald-200 text-emerald-800"><i class="fas fa-coins"></i></div>
                <div>
                  <div class="text-sm text-emerald-700 font-medium" id="yearly_label">Available Yearly Perdium Budget</div>
                  <div id="rem_yearly" class="text-2xl font-extrabold text-emerald-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <!-- Programs Bureau Total (right when programs + no owner) -->
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

          <!-- Government Grand (below) -->
          <div id="government_grand_card" class="rounded-xl p-5 mt-4 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
            <div class="flex items-center gap-3">
              <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-building"></i></div>
              <div>
                <div id="gov_grand_label" class="text-sm text-purple-700 font-medium">Bureau’s Yearly Government Budget</div>
                <div id="gov_grand_amount" class="text-2xl font-extrabold text-purple-900 mt-1">0.00 ብር</div>
              </div>
            </div>
            </div>
          </div>

          <div class="flex justify-end space-x-4 pt-2">
            <?php if (isset($perdium)): ?>
              <a href="perdium.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">Cancel</a>
            <?php endif; ?>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <?php echo isset($perdium) ? 'Update Transaction' : 'Add Transaction'; ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Filter toolbar (AJAX list) -->
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
                <option value="<?php echo $o['id']; ?>" data-budget-type="governmental"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
              <?php endforeach; ?>
              <?php foreach ($prog_owners as $o): ?>
                <option value="<?php echo $o['id']; ?>" data-budget-type="program"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="flt_month_box">
            <label class="block text-sm font-medium mb-1">Month (Gov only)</label>
            <select id="flt_month" class="w-full select2">
              <option value="">Any Month</option>
              <?php foreach (['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'] as $m): ?>
                <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Employee</label>
            <select id="flt_employee" class="w-full select2">
              <option value="">Any Employee</option>
              <?php foreach ($employees as $e): ?>
                <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Live Search Box -->
      <div class="mb-4">
        <input type="text" id="searchInput" placeholder="Search transactions..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" onkeyup="filterTransactions()">
      </div>

      <!-- Perdium Transactions Table -->
      <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Perdium Transactions</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
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
    const defaultPerdiumRate = <?php echo json_encode((float)$perdium_rate); ?>;
    let filling = false; // guard to suppress change handlers during programmatic fill
    const isEdit = <?php echo isset($perdium) ? 'true' : 'false'; ?>;
    let currentEmployeeSalary = 0;

    function fmt(n){return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
    function birr(n){return fmt(n)+' ብር';}

    function calculatePerdium() {
      const days = parseInt($('#total_days').val()) || 0;
      const rate = parseFloat($('#perdium_rate').val()) || 0;
      const totalAmount = days * rate;
      
      $('#total_amount').val(totalAmount.toFixed(2));
      $('#total_amount_card').text(birr(totalAmount));
      $('#total_days_card').text(days);
    }

    function updateDates() {
      const totalDays = parseInt($('#total_days').val()) || 0;
      
      if (totalDays > 0) {
        // Set departure date to tomorrow if not already set
        if (!$('#departure_date').val()) {
          const tomorrow = new Date();
          tomorrow.setDate(tomorrow.getDate() + 1);
          $('#departure_date').val(tomorrow.toISOString().split('T')[0]);
        }
        
        // Set arrival date based on departure date and total days
        if ($('#departure_date').val()) {
          const departureDate = new Date($('#departure_date').val());
          const arrivalDate = new Date(departureDate);
          arrivalDate.setDate(departureDate.getDate() + totalDays);
          $('#arrival_date').val(arrivalDate.toISOString().split('T')[0]);
        }
      }
      
      calculatePerdium();
    }

    function calculatePerdiumRate() {
      if (!currentEmployeeSalary || !$('#city_id').val()) {
        return;
      }
      
      const cityOption = $('#city_id').find('option:selected');
      const rateLow = parseFloat(cityOption.data('rate-low')) || 0;
      const rateMedium = parseFloat(cityOption.data('rate-medium')) || 0;
      const rateHigh = parseFloat(cityOption.data('rate-high')) || 0;

      // Determine rate based on employee salary
      let rate = rateLow; // default
      if (currentEmployeeSalary > 10000) {
        rate = rateHigh;
      } else if (currentEmployeeSalary > 5000) {
        rate = rateMedium;
      }

      $('#perdium_rate').val(rate.toFixed(2));
      calculatePerdium(); // recalculate total amount
    }

    function updateOwnerOptions() {
      const budgetType = $('#budget_type').val();
      $('#owner_id option').each(function() {
        const optionBudgetType = $(this).data('budget-type');
        if (optionBudgetType === budgetType) {
          $(this).show();
        } else {
          $(this).hide();
          if ($(this).is(':selected')) {
            $(this).prop('selected', false);
          }
        }
      });
      
      // Trigger change to update UI
      $('#owner_id').trigger('change.select2');
    }

    function setBudgetTypeUI(type) {
      if (type === 'program') {
        $('#et_month_box').hide();
        $('#et_month').prop('required', false);
        $('#rem_monthly_card').hide();
        $('#yearly_label').text('Available Yearly Budget');
        $('#flt_month_box').hide();
      } else {
        $('#et_month_box').show();
        $('#et_month').prop('required', true);
        $('#rem_monthly_card').show();
        $('#yearly_label').text('Available Yearly Perdium Budget');
        $('#flt_month_box').show();
      }
      
      // Update owner options based on budget type
      updateOwnerOptions();
    }

    function clearOwnerDependentFields() {
      filling = true;
      $('#owner_id').val('').trigger('change.select2');
      $('#et_month').val('').trigger('change.select2');
      filling = false;

      $('#program_p_koox_display').text('-');
      $('#program_name_display').text('Project Koox: -');

      $('#rem_monthly').text('0.00');
      $('#rem_yearly').text('0.00');
      $('#programs_total_card').hide();
      $('#government_grand_card').hide();

      $('#flt_owner').val('').trigger('change.select2');
      $('#flt_month').val('').trigger('change.select2');
    }

    function resetPerdiumFormOnTypeSwitch(){
      filling = true;
      $('#owner_id').val('').trigger('change.select2');
      $('#employee_id').val('').trigger('change.select2');
      $('#city_id').val('').trigger('change.select2');
      $('#et_month').val('').trigger('change.select2');
      filling = false;

      $('#departure_date').val('');
      $('#arrival_date').val('');
      $('#total_days').val('');
      $('#perdium_rate').val(defaultPerdiumRate);
      $('#total_amount').val(0);
      $('#employee_position_display').text('-');
      $('#employee_department_display').text('Department: -');
      $('#employee_salary_display').text('Salary: -');
      $('#program_p_koox_display').text('-');
      $('#program_name_display').text('Project Koox: -');

      $('#total_days_card').text('0');
      $('#total_amount_card').text('0.00 ብር');

      $('#rem_monthly').text('0.00');
      $('#rem_yearly').text('0.00');
      $('#programs_total_card').hide();
      $('#government_grand_card').hide();

      // Sync filters to match form
      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val('').trigger('change.select2');
      $('#flt_month').val('').trigger('change.select2');
      $('#flt_employee').val('').trigger('change.select2');

      fetchPerdiumList();
    }

    function onBudgetTypeChange(){
      const t = $('#budget_type').val();
      setBudgetTypeUI(t);
      resetPerdiumFormOnTypeSwitch();
    }

    function onOwnerChange(){
      const selectedOption = $('#owner_id').find('option:selected');
      const p_koox = selectedOption.data('p_koox') || '-';
      const ownerName = selectedOption.text().split(' - ')[1] || selectedOption.text();
      $('#program_p_koox_display').text(p_koox || '-');
      $('#program_name_display').text('Project Koox: ' + ownerName);

      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      fetchPerdiumList();
      loadPerdiumRemaining();
      refreshGrandTotals();
    }

    function onEmployeeChange() {
      const selectedOption = $('#employee_id').find('option:selected');
      const employeeText = selectedOption.text();
      const parts = employeeText.split(' - ');
      
      if (parts.length > 1) {
        $('#employee_position_display').text(parts[1]);
        $('#employee_department_display').text('Department: ' + (selectedOption.data('department') || '-'));
      } else {
        $('#employee_position_display').text('-');
        $('#employee_department_display').text('Department: -');
      }
      
      // Update salary display and variable
      currentEmployeeSalary = parseFloat(selectedOption.data('salary')) || 0;
      $('#employee_salary_display').text('Salary: ' + fmt(currentEmployeeSalary));
      
      // Recalculate perdium rate if a city is selected
      calculatePerdiumRate();
      
      $('#flt_employee').val($('#employee_id').val()).trigger('change.select2');
      fetchPerdiumList();
    }

    function onCityChange() {
      calculatePerdiumRate();
    }

    function loadPerdiumRemaining(){
      const ownerId = $('#owner_id').val();
      const etMonth = $('#et_month').val();
      const year = new Date().getFullYear() - 8;
      const type = $('#budget_type').val();

      if (!ownerId) {
        $('#rem_monthly').text('0.00');
        $('#rem_yearly').text('0.00');
        return;
      }
      
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
        $.get('get_remaining_perdium.php', { owner_id: ownerId, code_id: 6, month: etMonth, year: year }, function(resp){
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

      $.get('ajax_perdium_grands.php',{ budget_type:type, owner_id:ownerId, year:year }, function(resp){
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
      $('#flt_employee').val($('#employee_id').val()).trigger('change.select2');
    }

    function validateBeforeSubmit(){
      syncFiltersFromForm();
      return true;
    }

    function fetchPerdiumList(){
      const type  = $('#flt_type').val();
      const owner = $('#flt_owner').val();
      const month = $('#flt_month').val();
      const employee = $('#flt_employee').val();
      $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Loading…</td></tr>');
      $.get('ajax_perdium_list.php', { budget_type:type, owner_id:owner, et_month:month, employee_id:employee }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          const rows = j.rows || [];
          if(rows.length===0){
            $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">No perdium transactions found.</td></tr>');
            return;
          }
          let html='';
          rows.forEach(f=>{
            const printUrl = (f.budget_type==='program')
              ? `reports/preport2.php?id=${f.id}`
              : `reports/preport.php?id=${f.id}`;
            const dataJson = encodeURIComponent(JSON.stringify(f));
            html += `
              <tr class="row-click" data-json="${dataJson}">
                <td class="px-4 py-4 text-sm text-gray-900">${(f.created_at||'').replace('T',' ').slice(0,19)}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.employee_name || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.owner_code || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.city_name || ''}</td>
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

    // Populate form from clicked row
    function fillFormFromRow(d){
      try{
        filling = true;

        // Budget type
        $('#budget_type').val(d.budget_type || 'governmental').trigger('change.select2');
        setBudgetTypeUI($('#budget_type').val()); // UI only, no reset

        // Owner
        $('#owner_id').val(String(d.budget_owner_id||'')).trigger('change.select2');
        const ownerOpt = $('#owner_id').find('option:selected');
        const p_koox = ownerOpt.data('p_koox') || '-';
        const ownerName = ownerOpt.text().split(' - ')[1] || ownerOpt.text();
        $('#program_p_koox_display').text(p_koox || '-');
        $('#program_name_display').text('Project Koox: ' + ownerName);

        // Month (gov only)
        if ((d.budget_type||'governmental') !== 'program') {
          $('#et_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#et_month').val('').trigger('change.select2');
        }

        // Employee
        $('#employee_id').val(String(d.employee_id||'')).trigger('change.select2');
        const employeeOpt = $('#employee_id').find('option:selected');
        const employeeText = employeeOpt.text();
        const parts = employeeText.split(' - ');
        if (parts.length > 1) {
          $('#employee_position_display').text(parts[1]);
          $('#employee_department_display').text('Department: ' + (employeeOpt.data('department') || '-'));
        } else {
          $('#employee_position_display').text('-');
          $('#employee_department_display').text('Department: -');
        }
        
        // Update salary display and variable
        currentEmployeeSalary = parseFloat(employeeOpt.data('salary')) || 0;
        $('#employee_salary_display').text('Salary: ' + fmt(currentEmployeeSalary));

        // City
        $('#city_id').val(String(d.city_id||'')).trigger('change.select2');

        // Values
        $('#departure_date').val(d.departure_date || '');
        $('#arrival_date').val(d.arrival_date || '');
        $('#total_days').val(Number(d.total_days||0));
        $('#perdium_rate').val(Number(d.perdium_rate||0).toFixed(2));
        $('#total_amount').val(Number(d.total_amount||0).toFixed(2));

        // Cards
        $('#total_days_card').text(Number(d.total_days||0));
        $('#total_amount_card').text(birr(d.total_amount||0));

        // Sync lower filters to match this row
        $('#flt_type').val(d.budget_type || 'governmental').trigger('change.select2');
        $('#flt_owner').val(String(d.budget_owner_id||'')).trigger('change.select2');
        if ((d.budget_type||'governmental') !== 'program') {
          $('#flt_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#flt_month').val('').trigger('change.select2');
        }
        $('#flt_employee').val(String(d.employee_id||'')).trigger('change.select2');

        filling = false;

        // Refresh availability and grands
        loadPerdiumRemaining();
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

      // Main form dropdowns drive filtering and form behavior
      $('#budget_type').on('change', function(){
        if (filling) return;
        onBudgetTypeChange();
        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        toggleFilterMonth();
        fetchPerdiumList();
      });

      $('#owner_id').on('change', function(){
        if (filling) return;
        onOwnerChange();
      });

      $('#employee_id').on('change', function(){
        if (filling) return;
        onEmployeeChange();
      });
      
      $('#city_id').on('change', function(){
        if (filling) return;
        onCityChange();
      });

      $('#et_month').on('change', function(){
        if (filling) return;
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        fetchPerdiumList();
        loadPerdiumRemaining();
      });

      // Filter changes
      $('#flt_type').on('change', function(){
        toggleFilterMonth();
        fetchPerdiumList();
      });
      
      $('#flt_owner').on('change', function(){
        fetchPerdiumList();
      });
      
      $('#flt_month').on('change', function(){
        fetchPerdiumList();
      });
      
      $('#flt_employee').on('change', function(){
        fetchPerdiumList();
      });

      // Make table rows clickable to populate form
      $('#transactionsTable').on('click', 'tr.row-click', function(e){
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

      // INITIALIZE
      if (isEdit) {
        setBudgetTypeUI($('#budget_type').val()); // UI only, no reset
        filling = true;
        $('#budget_type').trigger('change.select2');
        $('#owner_id').trigger('change.select2');
        $('#employee_id').trigger('change.select2');
        $('#city_id').trigger('change.select2');
        <?php if (!empty($perdium) && $perdium['budget_type']!=='program'): ?>
          $('#et_month').trigger('change.select2');
        <?php endif; ?>
        filling = false;

        loadPerdiumRemaining();
        refreshGrandTotals();

        // Sync filters to form
        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
        $('#flt_employee').val($('#employee_id').val()).trigger('change.select2');
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        toggleFilterMonth();
        fetchPerdiumList();
      } else {
        setBudgetTypeUI($('#budget_type').val()); // UI only
        calculatePerdium();
        loadPerdiumRemaining();
        refreshGrandTotals();
        // Initialize filters to current form state
        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
        $('#flt_employee').val($('#employee_id').val()).trigger('change.select2');
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        toggleFilterMonth();
        fetchPerdiumList();
      }
    });

    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });
  </script>
</body>
</html>