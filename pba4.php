<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

$BUDGET_TYPE_PROGRAM = 'program'; // If you change enum to 'programs', update this to 'programs'

// Fallback EC months + quarter mapping if not defined in includes/functions.php
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

$owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$codes  = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

// Current filters
$selected_owner_id  = $_POST['owner_id'] ?? ($_GET['owner_id'] ?? null);
$selected_code_id   = $_POST['code_id']  ?? ($_GET['code_id']  ?? null);
$selected_type      = $_POST['budget_type'] ?? ($_GET['budget_type'] ?? 'governmental'); // governmental | program

// Load budgets (LEFT JOIN so code_id can be NULL for programs)
$budget_query = "SELECT b.*, o.code AS owner_code, o.name AS owner_name,
                        c.code AS budget_code, c.name AS budget_name
                 FROM budgets b
                 LEFT JOIN budget_owners o ON b.owner_id = o.id
                 LEFT JOIN budget_codes  c ON b.code_id = c.id
                 WHERE 1=1";
$params = [];
if (!empty($selected_type)) {
    $budget_query .= " AND b.budget_type = ?";
    $params[] = $selected_type;
}
if (!empty($selected_owner_id)) {
    $budget_query .= " AND b.owner_id = ?";
    $params[] = $selected_owner_id;
}
if (!empty($selected_code_id)) {
    $budget_query .= " AND b.code_id = ?";
    $params[] = $selected_code_id;
}
$budget_query .= " ORDER BY b.adding_date DESC, b.id DESC";
$stmt = $pdo->prepare($budget_query);
$stmt->execute($params);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Edit record
$budget = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($budget) {
        $selected_type = $budget['budget_type'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id      = (int)($_POST['owner_id'] ?? 0);
    $code_id       = isset($_POST['code_id']) && $_POST['code_id'] !== '' ? (int)$_POST['code_id'] : null; // allow NULL for programs
    $adding_date   = $_POST['adding_date'] ?? date('Y-m-d');
    $budget_type   = ($_POST['budget_type'] ?? 'governmental') === $BUDGET_TYPE_PROGRAM ? $BUDGET_TYPE_PROGRAM : 'governmental';
    $program_name  = trim($_POST['program_name'] ?? '');
    $month         = $_POST['month'] ?? '';
    $yearly_amount = (float)($_POST['yearly_amount'] ?? 0);
    $monthly_amount= (float)($_POST['monthly_amount'] ?? 0);

    // Use your EC converter if available
    $etInfo = function_exists('getEtMonthAndQuarter') ? getEtMonthAndQuarter($adding_date) : null;
    $year   = $etInfo['year'] ?? (date('Y', strtotime($adding_date)) - 8);
    $quarter= $budget_type === 'governmental' ? ($quarterMap[$month] ?? 0) : 0;

    // Validation basics
    if (!$owner_id) { $message = 'Please select Budget Owner.'; }
    else {
        $pdo->beginTransaction();
        try {
            // UPDATE path
            if (isset($_POST['id']) && ($_POST['action'] ?? '') === 'update') {
                $id = (int)$_POST['id'];
                if ($budget_type === $BUDGET_TYPE_PROGRAM) {
                    // For programs: yearly only, nullify month/monthly/quarter
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
                    // Governmental: honor both yearly and/or monthly
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
            }
            // CREATE path
            else {
                if ($budget_type === $BUDGET_TYPE_PROGRAM) {
                    // Programs: YEARLY ONLY. code_id optional, month/monthly/quarter must be off
                    if ($monthly_amount > 0) {
                        throw new Exception('Programs budget does not allow Monthly Amount. Leave it empty or 0.');
                    }
                    if ($yearly_amount <= 0) {
                        throw new Exception('Enter a valid Yearly Amount for programs budget.');
                    }
                    // Enforce 1 yearly program row per (owner, [code], year)
                    $sql = "SELECT id FROM budgets
                            WHERE owner_id=? AND year=? AND budget_type=? AND monthly_amount=0";
                    $p   = [$owner_id, $year, $BUDGET_TYPE_PROGRAM];
                    if ($code_id !== null) {
                        $sql .= " AND code_id = ?";
                        $p[] = $code_id;
                    } else {
                        $sql .= " AND code_id IS NULL";
                    }
                    $chk = $pdo->prepare($sql);
                    $chk->execute($p);
                    if ($chk->fetch()) {
                        throw new Exception('Program yearly budget already exists for this Owner/Code/Year.');
                    }

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
                    // Governmental
                    // Lock yearly row for this (owner,code,year,budget_type=governmental)
                    $sqlY = "SELECT id, yearly_amount, remaining_yearly FROM budgets
                             WHERE owner_id=? AND code_id=? AND year=? AND monthly_amount=0 AND budget_type='governmental'
                             FOR UPDATE";
                    $stY  = $pdo->prepare($sqlY);
                    $stY->execute([$owner_id, $code_id, $year]);
                    $yearly_budget = $stY->fetch(PDO::FETCH_ASSOC);

                    if ($yearly_amount > 0) {
                        if ($yearly_budget) {
                            throw new Exception('Yearly budget already exists for this Owner + Code + Year (government).');
                        }
                        $stmt = $pdo->prepare("INSERT INTO budgets
                            (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter,
                             remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->execute([
                            $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                            '', 0, 0,
                            $yearly_amount, 0, 0,
                            1, 'governmental'
                        ]);
                        $message = 'Yearly governmental budget added successfully.';
                    } elseif ($monthly_amount > 0) {
                        if (!$yearly_budget) {
                            throw new Exception('No yearly budget exists. Add yearly budget first.');
                        }
                        if ($monthly_amount > (float)$yearly_budget['remaining_yearly']) {
                            throw new Exception('Monthly amount exceeds remaining yearly budget.');
                        }
                        // Insert monthly row
                        $new_remaining_yearly = (float)$yearly_budget['remaining_yearly'] - $monthly_amount;
                        $stmt = $pdo->prepare("INSERT INTO budgets
                            (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter,
                             remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                        $stmt->execute([
                            $owner_id, $code_id, $adding_date, $year, 0,
                            $month, $monthly_amount, $quarter,
                            $new_remaining_yearly, $monthly_amount, $monthly_amount*3,
                            0, 'governmental'
                        ]);
                        // Update yearly remaining
                        $upd = $pdo->prepare("UPDATE budgets SET remaining_yearly=? WHERE id=?");
                        $upd->execute([$new_remaining_yearly, $yearly_budget['id']]);

                        $message = 'Monthly governmental budget added successfully.';
                    } else {
                        throw new Exception('Please enter a Yearly or Monthly amount.');
                    }
                    $pdo->commit();
                }
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Delete (kept your logic, adjusted slightly)
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT owner_id, code_id, year, monthly_amount, remaining_yearly, budget_type FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->beginTransaction();
    try {
        if ($budget && $budget['budget_type'] === 'governmental' && (float)$budget['monthly_amount'] > 0) {
            // Return monthly to yearly remaining
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
  <link rel="stylesheet" href="css/tailwind.css"> <!-- fixed: was <script src> -->
  <link rel="stylesheet" href="css/all.min.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f1f5f9 0%,#e2e8f0 100%);min-height:100vh;color:#334155;}
    .card-hover{transition:all .3s ease; box-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);}
    .card-hover:hover{transform:translateY(-3px); box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);}
    .input-group{transition:all .3s ease;border:1px solid #d1d5db;border-radius:.5rem;padding:.5rem .75rem;display:flex;align-items:center;}
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

        <form method="post" class="space-y-6">
          <?php if ($budget): ?>
            <input type="hidden" name="id" value="<?php echo (int)$budget['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>

          <!-- Budget Type -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Source Type</label>
              <select name="budget_type" id="budget_type" class="input-group" onchange="toggleBudgetType()" required>
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

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Adding Date</label>
              <input type="date" id="adding_date" name="adding_date" class="input-group"
                     value="<?php echo $budget ? htmlspecialchars(substr($budget['adding_date'],0,10)) : date('Y-m-d'); ?>" required>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owners Code</label>
              <select name="owner_id" class="input-group" required onchange="updateSelectedOwner(this)">
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

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Code</label>
              <select name="code_id" id="code_id" class="input-group" onchange="updateSelectedCode(this)">
                <option value="">-- No code (General) --</option>
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
                  } else {
                    echo '—';
                  }
                ?>
              </span></small>
              <small id="code_required_hint" class="text-xs text-amber-600" style="display:none;">Required for Government budgets</small>
            </div>

            <div id="month_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Select Month Info (EC)</label>
              <select id="month" name="month" class="input-group" onchange="updateQuarter()">
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

          <div class="bg-slate-100 p-4 rounded-lg" id="quarter_container">
            <label class="block text-sm font-medium text-slate-700 mb-1">Quarter</label>
            <div class="text-xl font-bold text-slate-800" id="quarter_label">Calculating...</div>
          </div>

          <div class="flex space-x-4 mt-6">
            <button type="submit" class="btn btn-primary"><?php echo $budget ? 'Update' : 'Save'; ?></button>
            <button type="button" class="btn btn-info" onclick="window.print()"><i class="fas fa-print mr-2"></i> Print</button>
          </div>
        </form>
      </div>

      <!-- Existing Budgets -->
      <div class="bg-white rounded-xl p-6 card-hover">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold text-slate-800">Existing Budgets</h2>
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
            <tbody>
              <?php foreach ($budgets as $b): ?>
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                  <td class="px-4 py-2 font-medium"><?php echo (int)$b['id']; ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($b['budget_type']); ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($b['program_name'] ?? '—'); ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars(($b['owner_code'] ?? '—') . ' - ' . ($b['owner_name'] ?? '')); ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars(($b['budget_code'] ?? '—') . ' - ' . ($b['budget_name'] ?? '')); ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($b['month'] ?? ''); ?></td>
                  <td class="px-4 py-2 font-medium"><?php echo number_format((float)$b['monthly_amount'], 2); ?> ETB</td>
                  <td class="px-4 py-2 font-medium"><?php echo number_format((float)$b['yearly_amount'], 2); ?> ETB</td>
                  <td class="px-4 py-2">
                    <div class="flex space-x-2">
                      <a href="?action=edit&id=<?php echo (int)$b['id']; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit mr-1"></i> Edit</a>
                      <a href="?action=delete&id=<?php echo (int)$b['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')"><i class="fas fa-trash mr-1"></i> Delete</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script>
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
      const ownerSelect = document.querySelector('select[name="owner_id"]');
      if (ownerSelect) updateSelectedOwner(ownerSelect);
      const codeSelect = document.querySelector('select[name="code_id"]');
      if (codeSelect) updateSelectedCode(codeSelect);
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

      if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
        if (programBox) programBox.style.display = 'block';
        if (monthBox) monthBox.style.display = 'none';
        if (mAmtBox)  mAmtBox.style.display = 'none';
        if (qContainer) qContainer.style.display = 'none';
        if (codeEl) codeEl.required = false;
        if (codeHint) codeHint.style.display = 'none';

        // Zero out monthly fields (server ignores them too)
        const mAmt = document.getElementById('monthly_amount');
        if (mAmt) mAmt.value = '';
      } else {
        if (programBox) programBox.style.display = 'none';
        if (monthBox) monthBox.style.display = 'block';
        if (mAmtBox)  mAmtBox.style.display = 'block';
        if (qContainer) qContainer.style.display = 'block';
        if (codeEl) codeEl.required = true;
        if (codeHint) codeHint.style.display = 'inline';
      }
    }
  </script>
</body>
</html>