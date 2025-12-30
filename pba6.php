<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Ensure PDO throws exceptions
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$BUDGET_TYPE_PROGRAM = 'program'; // change to 'programs' if your enum uses that

// Fallback EC months + quarter mapping
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

// Helper to get EC year from adding date
function ecYearFromDate($adding_date) {
    return function_exists('getEtMonthAndQuarter')
        ? (getEtMonthAndQuarter($adding_date)['year'] ?? (date('Y', strtotime($adding_date)) - 8))
        : (date('Y', strtotime($adding_date)) - 8);
}

/* ========== JSON endpoint: Availability (per selection) ========== */
if (isset($_GET['action']) && $_GET['action'] === 'availability') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $code_id_raw = $_GET['code_id'] ?? '';
        $code_id     = ($code_id_raw === '' ? null : (int)$code_id_raw);
        $adding_date = $_GET['adding_date'] ?? date('Y-m-d');
        $month       = $_GET['month'] ?? '';
        if (!$owner_id) throw new Exception('owner_id required');

        $year = ecYearFromDate($adding_date);
        $resp = ['ok'=>true, 'yearlyAvailable'=>0, 'monthlyAvailable'=>0];

        if ($budget_type === $BUDGET_TYPE_PROGRAM) {
            // Program: yearly-only; code_id may be NULL
            $sql = "SELECT COALESCE(SUM(remaining_yearly),0) AS y
                    FROM budgets
                    WHERE owner_id=? AND year=? AND budget_type=? AND monthly_amount=0";
            $p   = [$owner_id, $year, $BUDGET_TYPE_PROGRAM];
            if ($code_id === null) $sql .= " AND code_id IS NULL";
            else { $sql .= " AND code_id=?"; $p[] = $code_id; }
            $st = $pdo->prepare($sql); $st->execute($p);
            $resp['yearlyAvailable'] = (float)$st->fetchColumn();
        } else {
            // Government: yearly + monthly
            $stY = $pdo->prepare("SELECT remaining_yearly
                                  FROM budgets
                                  WHERE owner_id=? AND code_id=? AND year=? AND budget_type='governmental' AND monthly_amount=0
                                  ORDER BY id DESC LIMIT 1");
            $stY->execute([$owner_id, (int)$code_id, $year]);
            $resp['yearlyAvailable'] = (float)($stY->fetchColumn() ?: 0);

            $stM = $pdo->prepare("SELECT COALESCE(SUM(remaining_monthly),0)
                                  FROM budgets
                                  WHERE owner_id=? AND code_id=? AND year=? AND budget_type='governmental'
                                        AND monthly_amount>0 AND month=?");
            $stM->execute([$owner_id, (int)$code_id, $year, $month]);
            $resp['monthlyAvailable'] = (float)$stM->fetchColumn();
        }
        echo json_encode($resp);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ========== JSON endpoint: Budgets list (filter without reload) ========== */
if (isset($_GET['action']) && $_GET['action'] === 'budgets') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $code_id_raw = $_GET['code_id'] ?? '';
        $has_code    = ($code_id_raw !== '');
        $code_id     = $has_code ? (int)$code_id_raw : null;

        $q = "SELECT b.id, b.budget_type, b.program_name,
                     o.code AS owner_code, o.name AS owner_name,
                     c.code AS budget_code, c.name AS budget_name,
                     b.month, b.monthly_amount, b.yearly_amount
              FROM budgets b
              LEFT JOIN budget_owners o ON b.owner_id = o.id
              LEFT JOIN budget_codes  c ON b.code_id  = c.id
              WHERE b.budget_type = ?";
        $p = [$budget_type];
        if ($owner_id > 0) { $q .= " AND b.owner_id = ?"; $p[] = $owner_id; }
        if ($has_code)     { $q .= " AND b.code_id  = ?"; $p[] = $code_id; }
        $q .= " ORDER BY b.adding_date DESC, b.id DESC LIMIT 500";
        $st = $pdo->prepare($q); $st->execute($p);
        echo json_encode(['ok'=>true, 'rows'=>$st->fetchAll()]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

/* ========== JSON endpoint: Grand totals (new) ========== */
if (isset($_GET['action']) && $_GET['action'] === 'grandTotals') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $code_id_raw = $_GET['code_id'] ?? '';
        $has_code    = ($code_id_raw !== '');
        $code_id     = $has_code ? (int)$code_id_raw : null;
        $adding_date = $_GET['adding_date'] ?? date('Y-m-d');
        $year        = ecYearFromDate($adding_date);

        $data = ['ok'=>true];

        if ($budget_type === $BUDGET_TYPE_PROGRAM) {
            // Programs: Show total sum of yearly budgets (sum of yearly_amount) for all owners when owner not selected
            $sql = "SELECT COALESCE(SUM(yearly_amount),0) FROM budgets
                    WHERE budget_type=? AND monthly_amount=0 AND year=?";
            $p   = [$BUDGET_TYPE_PROGRAM, $year];
            // We only show this when owner is not selected; while returning always is fine
            $st = $pdo->prepare($sql); $st->execute($p);
            $data['programTotalYearlyAmount'] = (float)$st->fetchColumn(); // yearly budget sum
        } else {
            // Government totals (remaining_yearly on yearly rows)
            // Bureau total (no owner, no code)
            $sqlB = "SELECT COALESCE(SUM(remaining_yearly),0) FROM budgets
                     WHERE budget_type='governmental' AND monthly_amount=0 AND year=?";
            $stB = $pdo->prepare($sqlB); $stB->execute([$year]);
            $data['govtBureauYearlyRemaining'] = (float)$stB->fetchColumn();

            // Owner total (owner selected, code not selected)
            if ($owner_id > 0) {
                $sqlO = "SELECT COALESCE(SUM(remaining_yearly),0) FROM budgets
                         WHERE budget_type='governmental' AND monthly_amount=0 AND year=? AND owner_id=?";
                $stO = $pdo->prepare($sqlO); $stO->execute([$year, $owner_id]);
                $data['govtOwnerYearlyRemaining'] = (float)$stO->fetchColumn();
            } else {
                $data['govtOwnerYearlyRemaining'] = null;
            }
        }

        echo json_encode($data);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Load owners and codes
$owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$codes  = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

// State for server-rendered form
$selected_owner_id  = $_POST['owner_id'] ?? ($_GET['owner_id'] ?? null);
$selected_code_id   = $_POST['code_id']  ?? ($_GET['code_id']  ?? null);
$selected_type      = $_POST['budget_type'] ?? ($_GET['budget_type'] ?? 'governmental'); // governmental | program

// Edit record
$budget = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($budget) $selected_type = $budget['budget_type'];
}

// Save (POST) - unchanged core logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (!isset($_GET['action']) || $_GET['action']!=='availability')) {
    $owner_id      = (int)($_POST['owner_id'] ?? 0);
    $code_id       = isset($_POST['code_id']) && $_POST['code_id'] !== '' ? (int)$_POST['code_id'] : null;
    $adding_date   = $_POST['adding_date'] ?? date('Y-m-d');
    $budget_type   = ($_POST['budget_type'] ?? 'governmental') === $BUDGET_TYPE_PROGRAM ? $BUDGET_TYPE_PROGRAM : 'governmental';
    $program_name  = trim($_POST['program_name'] ?? '');
    $month         = $_POST['month'] ?? '';
    $yearly_amount = (float)($_POST['yearly_amount'] ?? 0);
    $monthly_amount= (float)($_POST['monthly_amount'] ?? 0);

    $year   = ecYearFromDate($adding_date);
    $quarter= $budget_type === 'governmental' ? ($quarterMap[$month] ?? 0) : 0;

    if (!$owner_id) {
        $message = 'Please select Budget Owner.';
    } else {
        $pdo->beginTransaction();
        try {
            if (isset($_POST['id']) && ($_POST['action'] ?? '') === 'update') {
                $id = (int)$_POST['id'];
                if ($budget_type === $BUDGET_TYPE_PROGRAM) {
                    $stmt = $pdo->prepare("UPDATE budgets
                        SET owner_id=?, code_id=?, adding_date=?, year=?, yearly_amount=?, month=?, monthly_amount=?, quarter=?,
                            remaining_yearly=?, remaining_monthly=?, remaining_quarterly=?, budget_type=?, program_name=?, is_yearly=?
                        WHERE id=?");
                    $stmt->execute([
                        $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                        '', 0, 0,
                        $yearly_amount, 0, 0,
                        $budget_type, ($program_name ?: null), 1,
                        $id
                    ]);
                    $message = 'Program yearly budget updated';
                } else {
                    $stmt = $pdo->prepare("UPDATE budgets
                        SET owner_id=?, code_id=?, adding_date=?, year=?, yearly_amount=?, month=?, monthly_amount=?, quarter=?, budget_type=?, program_name=?
                        WHERE id=?");
                    $stmt->execute([
                        $owner_id, $code_id, $adding_date, $year, $yearly_amount, $month, $monthly_amount, $quarter,
                        'governmental', null, $id
                    ]);
                    $message = 'Government budget updated';
                }
                $pdo->commit();
            } else {
                if ($budget_type === $BUDGET_TYPE_PROGRAM) {
                    if ($monthly_amount > 0) throw new Exception('Programs budget does not allow Monthly Amount.');
                    if ($yearly_amount <= 0) throw new Exception('Enter a valid Yearly Amount for programs budget.');

                    $sql = "SELECT id FROM budgets WHERE owner_id=? AND year=? AND budget_type=? AND monthly_amount=0";
                    $p   = [$owner_id, $year, $BUDGET_TYPE_PROGRAM];
                    if ($code_id !== null) { $sql .= " AND code_id=?"; $p[] = $code_id; }
                    else { $sql .= " AND code_id IS NULL"; }
                    $chk = $pdo->prepare($sql); $chk->execute($p);
                    if ($chk->fetch()) throw new Exception('Program yearly budget already exists for this Owner/Code/Year.');

                    $stmt = $pdo->prepare("INSERT INTO budgets
                        (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter,
                         remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type, program_name)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([
                        $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                        '', 0, 0,
                        $yearly_amount, 0, 0,
                        1, $BUDGET_TYPE_PROGRAM, ($program_name ?: null)
                    ]);
                    $pdo->commit();
                    $message = 'Program yearly budget added successfully.';
                } else {
                    if (!$code_id) throw new Exception('Select Budget Code for Government budgets.');
                    if ($monthly_amount > 0 && empty($month)) throw new Exception('Select Month for Monthly Amount.');

                    $sqlY = "SELECT id, yearly_amount, remaining_yearly FROM budgets
                             WHERE owner_id=? AND code_id=? AND year=? AND monthly_amount=0 AND budget_type='governmental'
                             FOR UPDATE";
                    $stY  = $pdo->prepare($sqlY);
                    $stY->execute([$owner_id, $code_id, $year]);
                    $yearly_budget = $stY->fetch(PDO::FETCH_ASSOC);

                    $didYearly = false; $didMonthly = false;

                    if ($yearly_amount > 0) {
                        if ($yearly_budget) throw new Exception('Yearly budget already exists for this Owner + Code + Year (government).');
                        $insY = $pdo->prepare("INSERT INTO budgets
                            (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter,
                             remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $insY->execute([
                            $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                            '', 0, 0,
                            $yearly_amount, 0, 0,
                            1, 'governmental'
                        ]);
                        $yearly_budget = [
                            'id' => $pdo->lastInsertId(),
                            'yearly_amount' => $yearly_amount,
                            'remaining_yearly' => $yearly_amount
                        ];
                        $didYearly = true;
                    }

                    if ($monthly_amount > 0) {
                        if (!$yearly_budget) throw new Exception('No yearly budget exists. Add yearly budget first (or provide both Yearly and Monthly now).');
                        if ($monthly_amount > (float)$yearly_budget['remaining_yearly']) throw new Exception('Monthly amount exceeds remaining yearly budget.');

                        $quarter = $quarterMap[$month] ?? 0;
                        $new_remaining_yearly = (float)$yearly_budget['remaining_yearly'] - $monthly_amount;

                        $insM = $pdo->prepare("INSERT INTO budgets
                            (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter,
                             remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $insM->execute([
                            $owner_id, $code_id, $adding_date, $year, 0,
                            $month, $monthly_amount, $quarter,
                            $new_remaining_yearly, $monthly_amount, $monthly_amount * 3,
                            0, 'governmental'
                        ]);
                        $upd = $pdo->prepare("UPDATE budgets SET remaining_yearly=? WHERE id=?");
                        $upd->execute([$new_remaining_yearly, $yearly_budget['id']]);
                        $didMonthly = true;
                    }

                    if (!$didYearly && !$didMonthly) throw new Exception('Please enter a Yearly or Monthly amount.');
                    if ($didYearly && $didMonthly) $message = 'Yearly + Monthly governmental budgets added successfully.';
                    elseif ($didYearly) $message = 'Yearly governmental budget added successfully.';
                    else $message = 'Monthly governmental budget added successfully.';
                    $pdo->commit();
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Delete (unchanged)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT owner_id, code_id, year, monthly_amount, remaining_yearly, budget_type FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        if ($budget && $budget['budget_type'] === 'governmental' && (float)$budget['monthly_amount'] > 0) {
            $stmt = $pdo->prepare("UPDATE budgets
                                   SET remaining_yearly = remaining_yearly + ?
                                   WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0 AND budget_type='governmental'");
            $stmt->execute([$budget['monthly_amount'], $budget['owner_id'], $budget['code_id'], $budget['year']]);
        }
        $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        $pdo->commit();
        $message = 'Budget deleted';
        $redir = 'budget_adding.php?owner_id=' . $budget['owner_id'] . '&budget_type=' . urlencode($budget['budget_type']);
        if (!empty($budget['code_id'])) $redir .= '&code_id=' . $budget['code_id'];
        header('Location: ' . $redir);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error deleting budget: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Budget Adding - Budget System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { primary:'#4f46e5', secondary:'#7c3aed', accent:'#06b6d4' } } } }
  </script>
  <link rel="stylesheet" href="css/all.min.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f1f5f9 0%,#e2e8f0 100%);min-height:100vh;color:#334155;}
    .card-hover{transition:all .3s ease; box-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);}
    .card-hover:hover{transform:translateY(-3px); box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);}
    .input-group{transition:all .3s ease;border:1px solid #d1d5db;border-radius:.5rem;padding:.5rem .75rem;display:flex;align-items:center;background:#fff}
    .input-group:focus-within{transform:translateY(-2px);border-color:#4f46e5;box-shadow:0 0 0 1px #4f46e5;}
    input,select,textarea{outline:none;width:100%;background:transparent;}
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
        <i class="fas fa-wallet text-amber-300 text-3xl mr-3"></i>
        <h2 class="text-xl font-bold text-white">Budget System</h2>
      </div>
      <ul class="space-y-2">
        <li><a href="dashboard.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-tachometer-alt w-5"></i><span class="ml-3">Dashboard</span></a></li>
        <li><a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-white/20"><i class="fas fa-plus-circle w-5"></i><span class="ml-3">Budget Adding</span></a></li>
        <li><a href="settings_owners.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-cog w-5"></i><span class="ml-3">Settings</span></a></li>
        <li><a href="transaction.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-exchange-alt w-5"></i><span class="ml-3">Transaction</span></a></li>
        <li><a href="fuel_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-gas-pump w-5"></i><span class="ml-3">Fuel Management</span></a></li>
        <li><a href="users_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10"><i class="fas fa-users w-5"></i><span class="ml-3">Users Management</span></a></li>
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
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Budget Management</h1>
          <p class="text-slate-600 mt-2">Add and manage budget allocations</p>
        </div>
        <div class="flex items-center space-x-4 mt-4 md:mt-0">
          <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <!-- Budget Form -->
      <div class="bg-white rounded-xl p-6 card-hover mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Adding Form</h2>
        <?php if (isset($message)): ?>
          <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6">
            <i class="fas fa-info-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>

        <form method="post" class="space-y-6" id="budgetForm">
          <?php if ($budget): ?>
            <input type="hidden" name="id" value="<?php echo (int)$budget['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>

          <!-- Budget Type + Program Name -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Source Type</label>
              <select name="budget_type" id="budget_type" class="input-group" onchange="toggleBudgetType(); refreshAvailability(); refreshGrandTotals(); fetchBudgets();" required>
                <option value="governmental" <?php echo ($selected_type==='governmental' ? 'selected':''); ?>>Government Budget</option>
                <option value="<?php echo $BUDGET_TYPE_PROGRAM; ?>" <?php echo ($selected_type===$BUDGET_TYPE_PROGRAM ? 'selected':''); ?>>Programs Budget</option>
              </select>
              <small class="text-slate-500">Government: monthly + yearly. Programs: yearly only.</small>
            </div>
            <div id="program_name_box" style="display:none;">
              <label class="block text-sm font-medium text-slate-700 mb-1">Program Name (NGO/Partner)</label>
              <input type="text" name="program_name" class="input-group" placeholder="e.g., Global Fund"
                     value="<?php echo $budget ? htmlspecialchars($budget['program_name'] ?? '') : ''; ?>">
            </div>
          </div>

          <!-- Owner + Date -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Adding Date</label>
              <input type="date" id="adding_date" name="adding_date" class="input-group"
                     value="<?php echo $budget ? htmlspecialchars(substr($budget['adding_date'],0,10)) : date('Y-m-d'); ?>" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owners Code</label>
              <select name="owner_id" class="input-group" onchange="updateSelectedOwner(this); refreshAvailability(); refreshGrandTotals(); fetchBudgets();">
                <option value="">-- Select Owner --</option>
                <?php foreach ($owners as $o): ?>
                  <option value="<?php echo (int)$o['id']; ?>" <?php
                    $sel = $budget ? ((int)$budget['owner_id']===(int)$o['id']) : ((int)$selected_owner_id===(int)$o['id']);
                    echo $sel ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-slate-500 text-sm mt-1 block">Selected Owner: <span id="selected_owner">
                <?php
                  if ($budget) {
                    $found = array_filter($owners, fn($x)=> (int)$x['id']===(int)$budget['owner_id']);
                    echo $found ? htmlspecialchars(array_values($found)[0]['name']) : '';
                  }
                ?>
              </span></small>
            </div>
          </div>

          <!-- Code + Month -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Code</label>
              <select name="code_id" id="code_id" class="input-group" onchange="updateSelectedCode(this); refreshAvailability(); refreshGrandTotals(); fetchBudgets();">
                <option value="">-- Select Code --</option>
                <?php foreach ($codes as $c): ?>
                  <option value="<?php echo (int)$c['id']; ?>" <?php
                    $sel = $budget ? ((int)$budget['code_id']===(int)$c['id']) : ((int)$selected_code_id===(int)$c['id']);
                    echo $sel ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="text-slate-500 text-sm mt-1 block">Selected Code: <span id="selected_code">
                <?php
                  if ($budget && $budget['code_id']) {
                    $found = array_filter($codes, fn($x)=> (int)$x['id']===(int)$budget['code_id']);
                    echo $found ? htmlspecialchars(array_values($found)[0]['name']) : '—';
                  } else echo '—';
                ?>
              </span></small>
              <small id="code_required_hint" class="text-xs text-amber-600" style="display:none;">Required for Government budgets</small>
            </div>

            <div id="month_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Select Month Info (EC)</label>
              <select id="month" name="month" class="input-group" onchange="updateQuarter(); refreshAvailability();">
                <?php foreach ($etMonths as $m): ?>
                  <option value="<?php echo htmlspecialchars($m); ?>" <?php
                    $sel = $budget ? ($budget['month']===$m) : false;
                    echo $sel ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($m); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Amounts -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div id="monthly_amount_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Monthly Amount</label>
              <input type="number" step="0.01" name="monthly_amount" id="monthly_amount" class="input-group"
                     value="<?php echo $budget ? htmlspecialchars($budget['monthly_amount']) : ''; ?>">
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Yearly Amount</label>
              <input type="number" step="0.01" name="yearly_amount" id="yearly_amount" class="input-group"
                     value="<?php echo $budget ? htmlspecialchars($budget['yearly_amount']) : ''; ?>">
            </div>
          </div>

          <!-- Availability cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="availability_panel">
            <!-- Monthly (Gov only) -->
            <div id="avail_monthly_card" class="rounded-xl p-5 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-amber-200 text-amber-800"><i class="fas fa-calendar-alt"></i></div>
                <div>
                  <div class="text-sm text-amber-700 font-medium">Available Monthly Budget</div>
                  <div id="avail_monthly" class="text-2xl font-extrabold text-amber-900 mt-1">—</div>
                </div>
              </div>
            </div>
            <!-- Yearly -->
            <div class="rounded-xl p-5 bg-gradient-to-r from-emerald-100 to-emerald-50 border border-emerald-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-emerald-200 text-emerald-800"><i class="fas fa-coins"></i></div>
                <div>
                  <div class="text-sm text-emerald-700 font-medium">Available Yearly Budget</div>
                  <div id="avail_yearly" class="text-2xl font-extrabold text-emerald-900 mt-1">—</div>
                </div>
              </div>
            </div>
            <!-- Programs Total (shown only when Programs + Owner not selected) -->
            <div id="program_total_card" class="rounded-xl p-5 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-layer-group"></i></div>
                <div>
                  <div class="text-sm text-purple-700 font-medium" id="program_total_label">All Programs Total Yearly Budget</div>
                  <div id="program_total_amount" class="text-2xl font-extrabold text-purple-900 mt-1">—</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Quarter -->
          <div class="bg-slate-50 p-4 rounded-lg border" id="quarter_container">
            <label class="block text-sm font-medium text-slate-700 mb-1">Quarter</label>
            <div class="text-xl font-bold text-slate-800" id="quarter_label">Calculating...</div>
          </div>

          <div class="flex gap-3 mt-6">
            <button type="submit" class="btn btn-primary"><?php echo $budget ? 'Update' : 'Save'; ?></button>
            <button type="button" class="btn btn-info" onclick="window.print()"><i class="fas fa-print mr-2"></i> Print</button>
          </div>
        </form>
      </div>

      <!-- Government Grand Total card (below availability) -->
      <div id="grand_total_card" class="rounded-xl p-5 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm mb-8" style="display:none;">
        <div class="flex items-center gap-3">
          <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-building"></i></div>
          <div>
            <div class="text-sm text-purple-700 font-medium" id="grand_total_label">Grand Yearly Budget</div>
            <div id="grand_total_amount" class="text-2xl font-extrabold text-purple-900 mt-1">—</div>
          </div>
        </div>
      </div>

      <!-- Existing Budgets (AJAX) -->
      <div class="bg-white rounded-xl p-6 card-hover">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-xl font-bold text-slate-800">Existing Budgets</h2>
            <p class="text-slate-500 text-sm">Filtered by Budget Source Type (and Owner/Code if selected)</p>
          </div>
          <div class="text-sm text-slate-500" id="budget_count">Loading…</div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs uppercase bg-slate-100 text-slate-700">
              <tr>
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Program</th>
                <th class="px-4 py-3">Directorates/Programs</th>
                <th class="px-4 py-3">Budget Codes</th>
                <th class="px-4 py-3">Month</th>
                <th class="px-4 py-3">Monthly Amount</th>
                <th class="px-4 py-3">Yearly Amount</th>
                <th class="px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody id="budgets_tbody">
              <tr><td class="px-4 py-3" colspan="9">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script>
    function formatMoney(n){ return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }
    function birr(n){ return `${formatMoney(n)} ብር`; }
    function escapeHtml(s){ return (s??'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

    document.addEventListener('DOMContentLoaded', () => {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('mainContent');
      const toggleBtn = document.getElementById('sidebarToggle');
      if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
          sidebar.classList.toggle('collapsed');
          mainContent.classList.toggle('expanded');
        });
      }

      toggleBudgetType();
      updateQuarter();
      refreshAvailability();
      refreshGrandTotals();
      fetchBudgets();

      const owner = document.querySelector('select[name="owner_id"]');
      const code  = document.getElementById('code_id');
      const month = document.getElementById('month');
      const date  = document.getElementById('adding_date');
      const type  = document.getElementById('budget_type');

      if (owner) owner.addEventListener('change', () => { refreshAvailability(); refreshGrandTotals(); fetchBudgets(); });
      if (code)  code.addEventListener('change', () => { refreshAvailability(); refreshGrandTotals(); fetchBudgets(); });
      if (month) month.addEventListener('change', refreshAvailability);
      if (date)  date.addEventListener('change', () => { refreshAvailability(); refreshGrandTotals(); });
      if (type)  type.addEventListener('change', () => { toggleBudgetType(); refreshAvailability(); refreshGrandTotals(); fetchBudgets(); });
    });

    function updateSelectedOwner(select) {
      const selectedOption = select.options[select.selectedIndex];
      const ownerName = selectedOption && selectedOption.text.includes(' - ')
        ? selectedOption.text.split(' - ')[1] : '';
      const el = document.getElementById('selected_owner');
      if (el) el.textContent = ownerName;
    }

    function updateSelectedCode(select) {
      const selectedOption = select.options[select.selectedIndex];
      let codeName = '—';
      if (selectedOption && selectedOption.value !== '' && selectedOption.text.includes(' - ')) {
        codeName = selectedOption.text.split(' - ')[1];
      }
      const el = document.getElementById('selected_code');
      if (el) el.textContent = codeName;
    }

    function updateQuarter() {
      const monthSelect = document.getElementById('month');
      if (!monthSelect) return;
      const selectedMonth = monthSelect.value;
      const quarterMap = {
        'Meskerem': 1, 'Tikimt': 1, 'Hidar': 1,
        'Tahsas': 2, 'Tir': 2, 'Yekatit': 2,
        'Megabit': 3, 'Miazia': 3, 'Ginbot': 3,
        'Sene': 4, 'Hamle': 4, 'Nehase': 4,
        'Pagume': 4
      };
      const quarter = quarterMap[selectedMonth] || '—';
      const qlbl = document.getElementById('quarter_label');
      if (qlbl) qlbl.textContent = 'Q' + quarter;
    }

    function toggleBudgetType() {
      const typeEl = document.getElementById('budget_type');
      const type = typeEl ? typeEl.value : 'governmental';

      const programBox = document.getElementById('program_name_box');
      const monthBox   = document.getElementById('month_box');
      const mAmtBox    = document.getElementById('monthly_amount_box');
      const qContainer = document.getElementById('quarter_container');
      const codeEl     = document.getElementById('code_id');
      const codeHint   = document.getElementById('code_required_hint');
      const monthlyAvailCard = document.getElementById('avail_monthly_card');
      const programTotalCard = document.getElementById('program_total_card');
      const grandTotalCard   = document.getElementById('grand_total_card');

      if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
        if (programBox) programBox.style.display = 'block';
        if (monthBox)   monthBox.style.display = 'none';
        if (mAmtBox)    mAmtBox.style.display = 'none';
        if (qContainer) qContainer.style.display = 'none';
        if (monthlyAvailCard) monthlyAvailCard.style.display = 'none';
        if (grandTotalCard) grandTotalCard.style.display = 'none'; // government-only card
        if (codeEl) codeEl.required = false;
        if (codeHint) codeHint.style.display = 'none';
        const mAmt = document.getElementById('monthly_amount'); if (mAmt) mAmt.value = '';
        // Show/hide program total card in refreshGrandTotals()
      } else {
        if (programBox) programBox.style.display = 'none';
        if (monthBox)   monthBox.style.display = 'block';
        if (mAmtBox)    mAmtBox.style.display = 'block';
        if (qContainer) qContainer.style.display = 'block';
        if (monthlyAvailCard) monthlyAvailCard.style.display = 'block';
        if (codeEl) codeEl.required = true;
        if (codeHint) codeHint.style.display = 'inline';
        if (programTotalCard) programTotalCard.style.display = 'none';
      }
    }

    async function refreshAvailability() {
      const type  = document.getElementById('budget_type')?.value || 'governmental';
      const owner = document.querySelector('select[name="owner_id"]')?.value || '';
      const code  = document.getElementById('code_id')?.value ?? '';
      const monthEl = document.getElementById('month');
      const month = monthEl ? monthEl.value : '';
      const adding_date = document.getElementById('adding_date')?.value || new Date().toISOString().slice(0,10);

      if (!owner) {
        document.getElementById('avail_yearly').textContent = '—';
        const am = document.getElementById('avail_monthly'); if (am) am.textContent = '—';
        return;
      }

      const url = new URL(window.location.href);
      url.searchParams.set('action','availability');
      url.searchParams.set('budget_type', type);
      url.searchParams.set('owner_id', owner);
      url.searchParams.set('code_id', code);
      url.searchParams.set('adding_date', adding_date);
      url.searchParams.set('month', month);

      try {
        const r = await fetch(url.toString(), { cache: 'no-store' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'fetch failed');

        document.getElementById('avail_yearly').textContent = birr(j.yearlyAvailable);
        const monthlyCard = document.getElementById('avail_monthly_card');
        const monthlyVal  = document.getElementById('avail_monthly');
        if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
          if (monthlyCard) monthlyCard.style.display = 'none';
        } else {
          if (monthlyCard) monthlyCard.style.display = 'block';
          if (monthlyVal)  monthlyVal.textContent = birr(j.monthlyAvailable);
        }
      } catch (e) {
        document.getElementById('avail_yearly').textContent = '—';
        const am = document.getElementById('avail_monthly'); if (am) am.textContent = '—';
      }
    }

    async function refreshGrandTotals() {
      const type  = document.getElementById('budget_type')?.value || 'governmental';
      const owner = document.querySelector('select[name="owner_id"]')?.value || '';
      const code  = document.getElementById('code_id')?.value ?? '';
      const adding_date = document.getElementById('adding_date')?.value || new Date().toISOString().slice(0,10);

      const url = new URL(window.location.href);
      url.searchParams.set('action','grandTotals');
      url.searchParams.set('budget_type', type);
      url.searchParams.set('owner_id', owner);
      url.searchParams.set('code_id', code);
      url.searchParams.set('adding_date', adding_date);

      const programTotalCard = document.getElementById('program_total_card');
      const programTotalAmount = document.getElementById('program_total_amount');
      const programTotalLabel  = document.getElementById('program_total_label');
      const grandTotalCard = document.getElementById('grand_total_card');
      const grandTotalAmount = document.getElementById('grand_total_amount');
      const grandTotalLabel  = document.getElementById('grand_total_label');

      try {
        const r = await fetch(url.toString(), { cache: 'no-store' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'fetch failed');

        // Programs case: show purple total card on the right ONLY when owner not selected
        if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
          const ownerSelected = !!owner;
          if (!ownerSelected) {
            if (programTotalCard) programTotalCard.style.display = 'block';
            if (programTotalLabel) programTotalLabel.textContent = 'All Programs Total Yearly Budget';
            if (programTotalAmount) programTotalAmount.textContent = birr(j.programTotalYearlyAmount || 0);
          } else {
            if (programTotalCard) programTotalCard.style.display = 'none';
          }
          if (grandTotalCard) grandTotalCard.style.display = 'none';
          return;
        }

        // Government case: show purple card BELOW availability
        const ownerSelected = !!owner;
        const codeSelected  = (code !== '');

        if (!ownerSelected && !codeSelected) {
          // Bureau total across all owners/codes (remaining_yearly sum)
          if (grandTotalCard) grandTotalCard.style.display = 'block';
          if (grandTotalLabel) grandTotalLabel.textContent = "Bureau's Yearly Government Budget is";
          if (grandTotalAmount) grandTotalAmount.textContent = birr(j.govtBureauYearlyRemaining || 0);
        } else if (ownerSelected && !codeSelected) {
          // Owner total across all codes
          const ownerSelect = document.querySelector('select[name="owner_id"]');
          const ownerName = (ownerSelect && ownerSelect.selectedIndex > 0)
            ? (ownerSelect.options[ownerSelect.selectedIndex].text.split(' - ')[1] || 'Selected Owner')
            : 'Selected Owner';
          if (grandTotalCard) grandTotalCard.style.display = 'block';
          if (grandTotalLabel) grandTotalLabel.textContent = `${ownerName}'s Total Yearly Budget (Grand Yearly Budget)`;
          if (grandTotalAmount) grandTotalAmount.textContent = birr(j.govtOwnerYearlyRemaining || 0);
        } else {
          if (grandTotalCard) grandTotalCard.style.display = 'none';
        }

        // Programs total card must be hidden in government type
        if (programTotalCard) programTotalCard.style.display = 'none';
      } catch (e) {
        if (programTotalCard) programTotalCard.style.display = 'none';
        if (grandTotalCard) grandTotalCard.style.display = 'none';
      }
    }

    async function fetchBudgets() {
      const type  = document.getElementById('budget_type')?.value || 'governmental';
      const owner = document.querySelector('select[name="owner_id"]')?.value || '';
      const code  = document.getElementById('code_id')?.value ?? '';

      const url = new URL(window.location.href);
      url.searchParams.set('action','budgets');
      url.searchParams.set('budget_type', type);
      if (owner) url.searchParams.set('owner_id', owner); else url.searchParams.delete('owner_id');
      if (code !== '') url.searchParams.set('code_id', code); else url.searchParams.delete('code_id');

      const tbody = document.getElementById('budgets_tbody');
      const count = document.getElementById('budget_count');
      if (tbody) tbody.innerHTML = `<tr><td class="px-4 py-3" colspan="9">Loading…</td></tr>`;
      if (count) count.textContent = 'Loading…';

      try {
        const r = await fetch(url.toString(), { cache: 'no-store' });
        const j = await r.json();
        if (!j.ok) throw new Error(j.error || 'fetch failed');

        const rows = j.rows 