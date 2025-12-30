<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch dropdown data
$owners      = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$vehicles    = $pdo->query("SELECT * FROM vehicles")->fetchAll();
$fuel_trans  = $pdo->query("
    SELECT f.*, o.code AS owner_code
      FROM fuel_transactions f
      JOIN budget_owners o
        ON f.owner_id = o.id
")->fetchAll();

// Last fuel price to prefill the form
$last_price = $pdo
    ->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")
    ->fetchColumn() ?: 0;

// Editing existing record?
$fuel = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([ $_GET['id'] ]);
    $fuel = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_id         = $_POST['owner_id'];
    $driver_name      = $_POST['driver_name'];
    $plate_number     = $_POST['plate_number'];
    $et_month         = $_POST['et_month'];
    $current_gauge    = (float)($_POST['current_gauge']    ?? 0);
    $journey_distance = (float)($_POST['journey_distance'] ?? 0);
    $fuel_price       = (float)($_POST['fuel_price']       ?? 0);
    $refuelable_amt   = (float)($_POST['refuelable_amount']?? 0);
    $total_amount     = (float)($_POST['total_amount']     ?? 0);
    $new_gauge        = (float)($_POST['new_gauge']        ?? 0);
    $gauge_gap        = (float)($_POST['gauge_gap']        ?? 0);

    // Validate gauge progression
    $stmt = $pdo->prepare("
        SELECT new_gauge
          FROM fuel_transactions
         WHERE plate_number = ?
         ORDER BY date DESC
         LIMIT 1
    ");
    $stmt->execute([ $plate_number ]);
    $last_new_gauge = (float)($stmt->fetchColumn() ?: 0);

    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $message = 'Gauge error: Current gauge less than expected new gauge.';
    } else {
        $pdo->beginTransaction();
        try {
            // 1) Determine Ethiopian year and quarter from selected month
            $year        = date('Y') - 8;
            $quarterMap = [
                'ሐምሌ'=>1,'ነሐሴ'=>1,'መስከረም'=>1,
                'ጥቅምት'=>2,'ህዳር'=>2,'ታኅሳስ'=>2,
                'ጥር'=>3,'የካቲቷ'=>3,'መጋቢቷ'=>3,
                'ሚያዝያ'=>4,'ግንቦቷ'=>4,'ሰኔ'=>4
            ];
            $quarter = $quarterMap[$et_month] ?? 0;

            // 2) Deduct from monthly budget if exists, else from yearly
            $fuel_code_id = 5; // Sansii kee Sukutih
            // Fetch monthly budget row
            $stmt = $pdo->prepare("
                SELECT * 
                  FROM budgets
                 WHERE owner_id   = ?
                   AND code_id    = ?
                   AND year       = ?
                   AND month      = ?
            ");
            $stmt->execute([ $owner_id, $fuel_code_id, $year, $et_month ]);
            $budget = $stmt->fetch();

            if ($budget) {
                // Use remaining_monthly field for fuel
                $new_rem_month = $budget['remaining_monthly'] - $total_amount;
                if ($new_rem_month < 0) {
                    throw new Exception('Insufficient remaining monthly budget for fuel.');
                }
                $stmt = $pdo->prepare("
                    UPDATE budgets
                       SET remaining_monthly = ?
                     WHERE id = ?
                ");
                $stmt->execute([ $new_rem_month, $budget['id'] ]);
            } else {
                // Fallback to yearly
                $stmt = $pdo->prepare("
                    SELECT *
                      FROM budgets
                     WHERE owner_id     = ?
                       AND code_id      = ?
                       AND year         = ?
                       AND monthly_amount = 0
                ");
                $stmt->execute([ $owner_id, $fuel_code_id, $year ]);
                $budget_yearly = $stmt->fetch();
                if (! $budget_yearly) {
                    throw new Exception('No fuel budget allocated.');
                }
                $new_rem_year = $budget_yearly['remaining_yearly'] - $total_amount;
                if ($new_rem_year < 0) {
                    throw new Exception('Insufficient remaining yearly budget for fuel.');
                }
                $stmt = $pdo->prepare("
                    UPDATE budgets
                       SET remaining_yearly = ?
                     WHERE id = ?
                ");
                $stmt->execute([ $new_rem_year, $budget_yearly['id'] ]);
            }

            // 3) Insert or update fuel transaction
            if ($fuel) {
                $stmt = $pdo->prepare("
                    UPDATE fuel_transactions
                       SET owner_id           = ?,
                           driver_name        = ?,
                           plate_number       = ?,
                           et_month           = ?,
                           previous_gauge     = ?,
                           current_gauge      = ?,
                           journey_distance   = ?,
                           fuel_price         = ?,
                           refuelable_amount  = ?,
                           total_amount       = ?,
                           new_gauge          = ?,
                           gauge_gap          = ?
                     WHERE id = ?
                ");
                $stmt->execute([
                    $owner_id, $driver_name, $plate_number, $et_month,
                    $last_new_gauge, $current_gauge, $journey_distance,
                    $fuel_price, $refuelable_amt, $total_amount,
                    $new_gauge, $gauge_gap, $fuel['id']
                ]);
                $message = 'Fuel transaction updated';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fuel_transactions (
                      owner_id,
                      driver_name,
                      plate_number,
                      et_month,
                      previous_gauge,
                      current_gauge,
                      journey_distance,
                      fuel_price,
                      refuelable_amount,
                      total_amount,
                      new_gauge,
                      gauge_gap
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $owner_id, $driver_name, $plate_number, $et_month,
                    $last_new_gauge, $current_gauge, $journey_distance,
                    $fuel_price, $refuelable_amt, $total_amount,
                    $new_gauge, $gauge_gap
                ]);
                $message = 'Fuel transaction added';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([ $_GET['id'] ]);
    header('Location: fuel_management.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Fuel Management - Budget System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#4f46e5',
            secondary: '#7c3aed',
            light: '#f8fafc',
            lighter: '#f1f5f9',
          }
        }
      }
    }
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
      min-height: 100vh;
      color: #334155;
    }
    
    .ethiopic {
      font-family: 'Noto Sans Ethiopic', sans-serif;
    }
    
    .card-hover {
      transition: all 0.3s ease;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    
    .card-hover:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    
    .sidebar {
      width: 260px;
      position: fixed;
      left: 0;
      top: 0;
      height: 100vh;
      z-index: 1000;
      background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      color: white;
      transition: transform 0.3s ease;
    }
    
    .sidebar.collapsed {
      transform: translateX(-260px);
    }
    
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-260px);
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
    }
    
    .main-content {
      transition: margin-left 0.3s ease;
      margin-left: 260px;
      width: calc(100% - 260px);
    }
    
    .main-content.expanded {
      margin-left: 0;
      width: 100%;
    }
    
    .input-group {
      transition: all 0.3s ease;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      padding: 0.5rem 0.75rem;
      display: flex;
      align-items: center;
    }
    
    .input-group:focus-within {
      transform: translateY(-2px);
      border-color: #4f46e5;
      box-shadow: 0 0 0 1px #4f46e5;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    .shake {
      animation: shake 0.5s;
    }
    
    input, select, textarea {
      outline: none;
      width: 100%;
      background: transparent;
    }
    
    .btn-primary {
      background-color: #4f46e5;
      color: white;
      padding: 0.75rem 1rem;
      border-radius: 0.5rem;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-primary:hover {
      background-color: #4338ca;
    }
    
    .btn-secondary {
      background-color: #6b7280;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-secondary:hover {
      background-color: #4b5563;
    }
    
    .btn-danger {
      background-color: #ef4444;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-danger:hover {
      background-color: #dc2626;
    }
    
    .btn-info {
      background-color: #06b6d4;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-info:hover {
      background-color: #0891b2;
    }
    
    .bureau-logo {
      max-width: 180px;
      max-height: 60px;
      object-fit: contain;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body class="text-slate-700 flex">
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="p-5">
      <div class="flex flex-col items-center justify-center mb-6">
        <!-- Bureau Logo -->
        <img src="images/bureau-logo.png" alt="Bureau Logo" class="bureau-logo bg-white p-2 rounded-lg"
             onerror="this.src='https://via.placeholder.com/180x60/4f46e5/ffffff?text=Bureau+Logo'">
        
        <div class="flex items-center mt-3">
          <i class="fas fa-wallet text-amber-300 text-2xl mr-2"></i>
          <h2 class="text-lg font-bold text-white">Budget System</h2>
        </div>
      </div>
      
      <ul class="space-y-2">
        <li>
          <a href="dashboard.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
            <i class="fas fa-tachometer-alt w-5"></i>
            <span class="ml-3">Dashboard</span>
          </a>
        </li>
        <?php if ($_SESSION['role'] == 'admin'): ?>
          <li>
            <a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
              <i class="fas fa-plus-circle w-5"></i>
              <span class="ml-3">Budget Adding</span>
            </a>
          </li>
          <li>
            <a href="settings_owners.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
              <i class="fas fa-building w-5"></i>
              <span class="ml-3">Settings Owners</span>
            </a>
          </li>
          <li>
            <a href="settings_codes.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
              <i class="fas fa-code w-5"></i>
              <span class="ml-3">Settings Codes</span>
            </a>
          </li>
        <?php endif; ?>
        <li>
          <a href="transaction.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
            <i class="fas fa-exchange-alt w-5"></i>
            <span class="ml-3">Transaction</span>
          </a>
        </li>
        <li>
          <a href="fuel_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-white/20">
            <i class="fas fa-gas-pump w-5"></i>
            <span class="ml-3">Fuel Management</span>
          </a>
        </li>
        <li>
          <a href="users_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
            <i class="fas fa-users w-5"></i>
            <span class="ml-3">Users Management</span>
          </a>
        </li>
        <li>
          <a href="logout.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
            <i class="fas fa-sign-out-alt w-5"></i>
            <span class="ml-3">Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="p-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
            Fuel Management
          </h1>
          <p class="text-slate-600 mt-2">Manage fuel transactions and consumption</p>
        </div>
        <div class="flex items-center space-x-4 mt-4 md:mt-0">
          <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <!-- Fuel Form -->
      <div class="bg-white rounded-xl p-6 card-hover mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Fuel Transaction Form</h2>
        <?php if (isset($message)): ?>
          <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6">
            <i class="fas fa-info-circle mr-2"></i>
            <?php echo htmlspecialchars($message); ?>
          </div>
        <?php endif; ?>
        
        <form method="post" class="space-y-6">
          <?php if ($fuel): ?>
            <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner</label>
              <select name="owner_id" id="owner_id" class="input-group" onchange="loadFuelRemaining()" required>
                <option value="">Select owner…</option>
                <?php foreach ($owners as $o): ?>
                  <option 
                    value="<?php echo $o['id']; ?>" 
                    <?php echo ($fuel && $fuel['owner_id']==$o['id'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($o['code'] . ' – ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month</label>
              <select name="et_month" id="et_month" class="input-group" onchange="loadFuelRemaining()" required>
                <option value="">Select month…</option>
                <?php
                  $etMonths = ['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር',
                               'የካቲቷ','መጋቢቷ','ሚያዝያ','ግንቦቷ','ሰኔ',
                               'ሐምሌ','ነሐሴ'];
                  foreach ($etMonths as $m): 
                    $sel = ($fuel && $fuel['et_month']==$m) ? 'selected' : '';
                ?>
                  <option value="<?php echo $m;?>" <?php echo $sel;?>><?php echo $m;?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number</label>
              <select 
                name="plate_number" 
                id="plate_number" 
                class="input-group" 
                onchange="fetchLastGauge(this.value)" 
                required>
                <option value="">Select vehicle…</option>
                <?php foreach ($vehicles as $v): ?>
                  <option 
                    value="<?php echo htmlspecialchars($v['plate_no']); ?>" 
                    <?php echo ($fuel && $fuel['plate_number']==$v['plate_no'])?'selected':''; ?>>
                    <?php echo htmlspecialchars($v['plate_no']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Driver Name</label>
              <input 
                type="text" 
                name="driver_name" 
                class="input-group" 
                value="<?php echo $fuel['driver_name'] ?? ''; ?>" 
                required>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Current Gauge</label>
              <input 
                type="number" 
                step="0.01" 
                id="current" 
                name="current_gauge" 
                class="input-group" 
                value="<?php echo $fuel['current_gauge'] ?? ''; ?>" 
                onchange="calculateFuel()" 
                required>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (Km)</label>
              <input 
                type="number" 
                step="0.01" 
                id="journey" 
                name="journey_distance" 
                class="input-group" 
                value="<?php echo $fuel['journey_distance'] ?? ''; ?>" 
                onchange="calculateFuel()" 
                required>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price /L</label>
              <input 
                type="number" 
                step="0.01" 
                id="price" 
                name="fuel_price" 
                class="input-group" 
                value="<?php echo $fuel['fuel_price'] ?? $last_price; ?>" 
                onchange="calculateFuel()" 
                required>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable (L)</label>
              <input type="number" step="0.01" id="refuelable" name="refuelable_amount" class="input-group" readonly>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Total Amount</label>
              <input type="number" step="0.01" id="total" name="total_amount" class="input-group" readonly>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">New Gauge</label>
              <input type="number" step="0.01" id="new_gauge" name="new_gauge" class="input-group" readonly>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Gauge Gap</label>
              <input type="number" step="0.01" id="gap" name="gauge_gap" class="input-group" readonly>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
            <div class="bg-slate-100 p-4 rounded-lg">
              <label class="block text-sm font-medium text-slate-700 mb-1">Remaining Monthly Fuel Budget</label>
              <div class="text-xl font-bold text-slate-800" id="rem_monthly">0.00</div>
            </div>
            
            <div class="bg-slate-100 p-4 rounded-lg">
              <label class="block text-sm font-medium text-slate-700 mb-1">Remaining Quarterly Fuel Budget</label>
              <div class="text-xl font-bold text-slate-800" id="rem_quarterly">0.00</div>
            </div>
            
            <div class="bg-slate-100 p-4 rounded-lg">
              <label class="block text-sm font-medium text-slate-700 mb-1">Remaining Yearly Fuel Budget</label>
              <div class="text-xl font-bold text-slate-800" id="rem_yearly">0.00</div>
            </div>
          </div>

          <div class="flex space-x-4 mt-6">
            <button type="submit" class="btn-primary">
              <i class="fas <?php echo $fuel ? 'fa-sync' : 'fa-save'; ?> mr-2"></i>
              <?php echo $fuel ? 'Update Transaction' : 'Save Transaction'; ?>
            </button>
            <button type="button" class="btn-info" onclick="window.print()">
              <i class="fas fa-print mr-2"></i> Print
            </button>
          </div>
        </form>
      </div>

      <!-- Existing Transactions -->
      <div class="bg-white rounded-xl p-6 card-hover">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold text-slate-800">Existing Fuel Transactions</h2>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left text-slate-600">
            <thead class="text-xs uppercase bg-slate-100 text-slate-700">
              <tr>
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Owner</th>
                <th class="px-4 py-3">Driver</th>
                <th class="px-4 py-3">Plate</th>
                <th class="px-4 py-3">Month</th>
                <th class="px-4 py-3">Total Amount</th>
                <th class="px-4 py-3 text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($fuel_trans as $f): ?>
                <tr class="border-b border-slate-200 hover:bg-slate-50">
                  <td class="px-4 py-2 font-medium"><?php echo $f['id']; ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($f['owner_code']); ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($f['driver_name']); ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($f['plate_number']); ?></td>
                  <td class="px-4 py-2"><?php echo htmlspecialchars($f['et_month']); ?></td>
                  <td class="px-4 py-2 font-medium"><?php echo number_format($f['total_amount'], 2); ?> ETB</td>
                  <td class="px-4 py-2">
                    <div class="flex justify-center space-x-2">
                      <a href="?action=edit&id=<?php echo $f['id']; ?>" class="btn-secondary">
                        <i class="fas fa-edit mr-1"></i> Edit
                      </a>
                      <a href="?action=delete&id=<?php echo $f['id']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this transaction?')">
                        <i class="fas fa-trash mr-1"></i> Delete
                      </a>
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

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
      
      // Initialize any necessary functionality
      calculateFuel();
    });

    // Fetch last gauge for selected plate
    function fetchLastGauge(plate) {
      if (!plate) return;
      $.get('get_last_gauge.php', { plate_number: plate }, data => {
        const resp = JSON.parse(data);
        document.getElementById('current').value = resp.last_gauge || 0;
        calculateFuel();
      });
    }

    // Calculate liters & cost & gauges
    function calculateFuel() {
      const current = parseFloat(document.getElementById('current').value) || 0;
      const journey = parseFloat(document.getElementById('journey').value)   || 0;
      const price   = parseFloat(document.getElementById('price').value)     || 0;

      // your business logic (e.g. 5 km per liter)
      const refuel = journey / 5;
      const total  = refuel * price;
      const newG   = current + journey;
      const gap    = newG - current;

      document.getElementById('refuelable').value = refuel.toFixed(2);
      document.getElementById('total').value      = total.toFixed(2);
      document.getElementById('new_gauge').value  = newG.toFixed(2);
      document.getElementById('gap').value        = gap.toFixed(2);
    }

    // Load remaining budgets via AJAX
    function loadFuelRemaining() {
      const ownerId = document.getElementById('owner_id').value;
      const etMonth = document.getElementById('et_month').value;
      const codeId  = 5; // fuel code

      if (!ownerId || !etMonth) return;

      fetch(`get_remaining.php?owner_id=${ownerId}&code_id=${codeId}&month=${encodeURIComponent(etMonth)}`)
        .then(res => res.json())
        .then(data => {
          document.getElementById('rem_monthly').textContent   = parseFloat(data.remaining_month || 0).toFixed(2);
          document.getElementById('rem_quarterly').textContent = parseFloat(data.remaining_quarter || 0).toFixed(2);
          document.getElementById('rem_yearly').textContent    = parseFloat(data.remaining_year || 0).toFixed(2);
        })
        .catch(console.error);
    }
  </script>
</body>
</html>