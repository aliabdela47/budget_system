<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch dropdown data
$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$employees = $pdo->query("SELECT * FROM employees")->fetchAll();
$cities = $pdo->query("SELECT * FROM cities")->fetchAll();

// Fetch perdium transactions
$perdium_trans = $pdo->query("
    SELECT p.*, 
           o.name AS owner_name, 
           o.code AS owner_code,
           e.name AS employee_name,
           e.salary AS employee_salary,
           e.directorate AS employee_directorate,
           c.name_amharic AS city_name
    FROM perdium_transactions p
    JOIN budget_owners o ON p.budget_owner_id = o.id
    JOIN employees e ON p.employee_id = e.id
    JOIN cities c ON p.city_id = c.id
    ORDER BY p.created_at DESC
")->fetchAll();

// Get current Ethiopian month and year
$current_ethio_month = get_current_ethiopian_month();
$current_ethio_year = date('Y') - 8; // Ethiopian year offset

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budget_owner_id = $_POST['budget_owner_id'];
    $employee_id = $_POST['employee_id'];
    $city_id = $_POST['city_id'];
    $total_days = (int)$_POST['total_days'];
    $departure_date = $_POST['departure_date'];
    $arrival_date = $_POST['arrival_date'];
    $et_month = $_POST['et_month'];
    
    // Get employee salary
    $stmt = $pdo->prepare("SELECT salary FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee_salary = $stmt->fetchColumn();
    
    // Get city rates
    $stmt = $pdo->prepare("SELECT rate_low, rate_medium, rate_high FROM cities WHERE id = ?");
    $stmt->execute([$city_id]);
    $city_rates = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Determine perdium rate based on salary
    if ($employee_salary <= 3933) {
        $perdium_rate = $city_rates['rate_low'];
    } elseif ($employee_salary <= 9055) {
        $perdium_rate = $city_rates['rate_medium'];
    } else {
        $perdium_rate = $city_rates['rate_high'];
    }
    
    // Calculate perdium amount using the formula
    $A = ($perdium_rate * 0.1) + ($perdium_rate * 0.25) + ($perdium_rate * 0.25);
    $B = $A * ($total_days - 2);
    $C = $A;
    $D = $perdium_rate * ($total_days - 1) * 0.4;
    $total_amount = $A + $B + $C + $D;
    
    // Budget code for Ayroh Assentah (constant id=6)
    $budget_code_id = 6;
    
    $pdo->beginTransaction();
    try {
        // 1) Deduct from monthly budget
        $stmt = $pdo->prepare("
            SELECT * 
            FROM budgets
            WHERE owner_id = ?
            AND code_id = ?
            AND year = ?
            AND month = ?
        ");
        $stmt->execute([$budget_owner_id, $budget_code_id, $current_ethio_year, $et_month]);
        $budget = $stmt->fetch();
        
        if ($budget) {
            // Use remaining_monthly field for perdium
            $new_rem_month = $budget['remaining_monthly'] - $total_amount;
            if ($new_rem_month < 0) {
                throw new Exception('Insufficient remaining monthly budget for perdium.');
            }
            $stmt = $pdo->prepare("
                UPDATE budgets
                SET remaining_monthly = ?
                WHERE id = ?
            ");
            $stmt->execute([$new_rem_month, $budget['id']]);
        } else {
            throw new Exception('No perdium budget allocated for the selected month.');
        }


        // 2) Insert perdium transaction
        $stmt = $pdo->prepare("
            INSERT INTO perdium_transactions (
                employee_id,
                budget_owner_id,
                city_id,
                perdium_rate,
                total_days,
                departure_date,
                arrival_date,
                total_amount,
                et_month
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $employee_id, 
            $budget_owner_id, 
            $city_id,
            $perdium_rate,
            $total_days,
            $departure_date,
            $arrival_date,
            $total_amount,
            $et_month
        ]);
        
        $pdo->commit();
        $_SESSION['message'] = 'Perdium transaction added successfully';
        $_SESSION['message_type'] = 'success';
        
        // PRG: Redirect after processing to avoid resubmit
        header('Location: perdium_management.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

// Handle delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM perdium_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['message'] = 'Perdium transaction deleted';
    $_SESSION['message_type'] = 'success';
    header('Location: perdium_management.php');
    exit;
}

// Clear message after displaying
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Perdium Management - Budget System</title>
  <script src="css/tailwind.css"></script>
  
  
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


  <link rel="stylesheet" href="css/all.min.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#4f46e5',
            secondary: '#7c3aed',
            light: '#f8fafc',
            lighter: '#f1f5f9',
            success: '#10B981',
            error: '#EF4444',
            warning: '#F59E0B',
          }
        }
      }
    }
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap');
    
    body { font-family: 'Inter', sans-serif; }
    
    .ethio-font {
      font-family: 'Noto Sans Ethiopic', sans-serif;
    }
    
    .fade-out {
      opacity: 1;
      transition: opacity 0.5s ease-out;
    }
    
    .fade-out.hide {
      opacity: 0;
    }
    
    .sidebar {
      width: 280px;
      transition: all 0.3s ease;
    }
    
    .sidebar.collapsed {
      margin-left: -280px;
    }
    
    .main-content {
      width: calc(100% - 280px);
      transition: all 0.3s ease;
    }
    
    .main-content.expanded {
      width: 100%;
    }
    
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        left: 0;
        z-index: 1000;
        height: 100vh;
        overflow-y: auto;
      }
      
      .main-content {
        width: 100%;
      }
    }
    
    .employee-search-results {
      position: absolute;
      background: white;
      border: 1px solid #ddd;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      width: 100%;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .employee-search-result {
      padding: 10px;
      cursor: pointer;
      border-bottom: 1px solid #eee;
    }
    
    .employee-search-result:hover {
      background-color: #f5f5f5;
    }
  </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
  <!-- Sidebar -->
  <div class="sidebar bg-gradient-to-b from-primary to-secondary text-white" id="sidebar">
    <div class="p-5">
      <div class="flex flex-col items-center justify-center mb-6">
        <!-- Bureau Logo -->
        <img src="images/bureau-logo.png" alt="Bureau Logo" class="bureau-logo bg-white p-2 rounded-lg w-40"
             onerror="this.src='https://via.placeholder.com/180x60/4f46e5/ffffff?text=Bureau+Logo'">
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
          <li>
            <a href="settings_vehicles.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
              <i class="fas fa-car w-5"></i>
              <span class="ml-3">Settings Vehicles</span>
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
          <a href="fuel_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
            <i class="fas fa-gas-pump w-5"></i>
            <span class="ml-3">Fuel Management</span>
          </a>
        </li>
        <li>
          <a href="perdium_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-white/20">
            <i class="fas fa-money-bill-wave w-5"></i>
            <span class="ml-3">Perdium Management</span>
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
            Perdium Management
          </h1>
          <p class="text-slate-600 mt-2">Manage perdium transactions and allowances</p>
        </div>
        <div class="flex items-center space-x-4 mt-4 md:mt-0">
          <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <!-- Message Notification -->
      <?php if ($message): ?>
        <div id="message" class="fade-out mb-6 p-4 rounded-lg <?php 
          echo $message_type == 'error' ? 'bg-error/20 text-error' : 
               ($message_type == 'success' ? 'bg-success/20 text-success' : 
               'bg-primary/20 text-primary');
        ?>">
          <div class="flex justify-between items-center">
            <p><?php echo htmlspecialchars($message); ?></p>
            <button onclick="document.getElementById('message').classList.add('hide')" class="text-lg">&times;</button>
          </div>
        </div>
        
        <script>
          // Auto-hide message after 5 seconds
          setTimeout(function() {
            const message = document.getElementById('message');
            if (message) {
              message.classList.add('hide');
              setTimeout(() => message.remove(), 500);
            }
          }, 5000);
        </script>
      <?php endif; ?>

      <!-- Perdium Transaction Form -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">
          Add New Perdium Transaction
        </h2>
        <form id="perdiumForm" method="POST" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
              <select name="budget_owner_id" id="budget_owner_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="loadPerdiumRemaining()">
                <option value="">Select Owner</option>
                <?php foreach ($owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>">
                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            
       <select name="employee_id" id="employee_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
         <option value="">Select Employee</option>
         <?php foreach ($employees as $e): ?>
         <option value="<?php echo $e['id']; ?>" data-salary="<?php echo $e['salary']; ?>" data-directorate="<?php echo $e['directorate']; ?>">
            <?php echo htmlspecialchars($e['name'] . ' - ' . $e['directorate']); ?>
            </option>
            <?php endforeach; ?>
            </select>
            
            
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Employee Salary</label>
              <input type="text" id="employee_salary" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Employee Directorate</label>
              <input type="text" id="employee_directorate" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Destination City *</label>
              <select name="city_id" id="city_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="calculatePerdium()">
                <option value="">Select City</option>
                <?php foreach ($cities as $c): ?>
                  <option value="<?php echo $c['id']; ?>" data-rate-low="<?php echo $c['rate_low']; ?>" data-rate-medium="<?php echo $c['rate_medium']; ?>" data-rate-high="<?php echo $c['rate_high']; ?>">
                    <?php echo htmlspecialchars($c['name_amharic'] . ' (' . $c['name_english'] . ')'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Perdium Rate</label>
              <input type="text" id="perdium_rate" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Total Perdium Days *</label>
              <input type="number" name="total_days" id="total_days" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="calculateDates()" oninput="calculatePerdium()">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Departure Date *</label>
              <input type="date" name="departure_date" id="departure_date" required <?php if ($isOfficer): ?>readonly<?php endif; ?> class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="calculateArrivalDate()">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Arrival Date *</label>
              <input type="date" name="arrival_date" id="arrival_date" required <?php if ($isOfficer): ?>readonly<?php endif; ?>class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="calculateTotalDays()">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="loadPerdiumRemaining()">
                <option value="">Select Month</option>
                <option value="መስከረም">መስከረም</option>
                <option value="ጥቅምት">ጥቅምት</option>
                <option value="ህዳር">ህዳር</option>
                <option value="ታኅሳስ">ታኅሳስ</option>
                <option value="ጥር">ጥር</option>
                <option value="የካቲት">የካቲት</option>
                <option value="መጋቢት">መጋቢት</option>
                <option value="ሚያዝያ">ሚያዝያ</option>
                <option value="ግንቦት">ግንቦት</option>
                <option value="ሰኔ">ሰኔ</option>
                <option value="ሐምሌ">ሐምሌ</option>
                <option value="ነሐሴ">ነሐሴ</option>
              </select>
            </div>
            
            <div class="md:col-span-2 lg:col-span-3">
              <div class="bg-blue-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">Perdium Calculation</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                  <div>
                    <p class="text-blue-700">A (Departure): <span id="calc_A">0.00</span></p>
                    <p class="text-xs text-blue-600">(PR × 10%) + (PR × 25%) + (PR × 25%)</p>
                  </div>
                  <div>
                    <p class="text-blue-700">B (Field Days): <span id="calc_B">0.00</span></p>
                    <p class="text-xs text-blue-600">A × (TPD - 2)</p>
                  </div>
                  <div>
                    <p class="text-blue-700">C (Arrival): <span id="calc_C">0.00</span></p>
                    <p class="text-xs text-blue-600">A</p>
                  </div>
                  <div>
                    <p class="text-blue-700">D (Lodging): <span id="calc_D">0.00</span></p>
                    <p class="text-xs text-blue-600">PR × (TPD - 1) × 40%</p>
                  </div>
                </div>
                <div class="mt-4 pt-2 border-t border-blue-200">
                  <p class="text-xl font-bold text-blue-800">Total Perdium Payment: <span id="total_perdium">0.00</span> Birr</p>
                </div>
              </div>
            </div>
          </div>
          
          <div class="flex justify-end space-x-4 pt-4">
            <button type="reset" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
              Reset
            </button>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary">
              Submit Perdium Request
            </button>
          </div>
        </form>
      </div>

      <!-- Budget Status -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Status (Ayroh Assentah)</h2>
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

      <!-- Perdium Transactions Table -->
      <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Perdium Transactions</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">City</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="transactionsTable">
              <?php if (empty($perdium_trans)): ?>
                <tr>
                  <td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">
                    No perdium transactions found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($perdium_trans as $p): ?>
                  <tr>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo date('M j, Y', strtotime($p['created_at'])); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($p['owner_code']); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($p['employee_name']); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900 ethio-font"><?php echo htmlspecialchars($p['city_name']); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo $p['total_days']; ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo number_format($p['total_amount'], 2); ?></td>
                    <td class="px-4 py-4 text-sm">
                      <div class="flex space-x-2">
                        <a href="reports/perdium_transaction_report.php?id=<?php echo $p['id']; ?>" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                          <i class="fas fa-print mr-1"></i> Print
                        </a>
                        <a href="?action=delete&id=<?php echo $p['id']; ?>" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this transaction?')">
                          <i class="fas fa-trash mr-1"></i> Delete
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <script src="js/jquery-3.7.1.min.js"></script>
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
      
      // Set default dates
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 1);
      document.getElementById('departure_date').valueAsDate = tomorrow;
      
      // Set current Ethiopian month
      document.getElementById('et_month').value = '<?php echo $current_ethio_month; ?>';
    });
    
    
    // Employee Search new
    
    $('#employee_id').select2({
    theme: 'classic',
    width: '100%',
    placeholder: "Select an employee",
    allowClear: true
}).on('change', function() {
    var selectedOption = $(this).find('option:selected');
    var salary = selectedOption.data('salary');
    var directorate = selectedOption.data('directorate');
    
    $('#employee_salary').val(salary);
    $('#employee_directorate').val(directorate);
    
    // Calculate perdium if city is already selected
    calculatePerdium();
});

   // Employee Search new


    
    
    // Calculate perdium amount
    function calculatePerdium() {
      const salary = parseFloat($('#employee_salary').val()) || 0;
      const citySelect = document.getElementById('city_id');
      const cityOption = citySelect.options[citySelect.selectedIndex];
      const totalDays = parseInt($('#total_days').val()) || 0;
      
      if (!cityOption.value || !salary || totalDays < 1) return;
      
      const rateLow = parseFloat(cityOption.getAttribute('data-rate-low'));
      const rateMedium = parseFloat(cityOption.getAttribute('data-rate-medium'));
      const rateHigh = parseFloat(cityOption.getAttribute('data-rate-high'));
      
      let perdiumRate = 0;
      if (salary <= 3933) {
        perdiumRate = rateLow;
      } else if (salary <= 9055) {
        perdiumRate = rateMedium;
      } else {
        perdiumRate = rateHigh;
      }
      
      $('#perdium_rate').val(perdiumRate.toFixed(2));
      
      // Calculate using the formula: A + B + C + D
      const A = (perdiumRate * 0.1) + (perdiumRate * 0.25) + (perdiumRate * 0.25);
      const B = A * (totalDays - 2);
      const C = A;
      const D = perdiumRate * (totalDays - 1) * 0.4;
      const total = A + B + C + D;
      
      $('#calc_A').text(A.toFixed(2));
      $('#calc_B').text(B.toFixed(2));
      $('#calc_C').text(C.toFixed(2));
      $('#calc_D').text(D.toFixed(2));
      $('#total_perdium').text(total.toFixed(2));
    }
    
    // Calculate dates based on total days
    function calculateDates() {
      const totalDays = parseInt($('#total_days').val()) || 0;
      const departureDate = new Date($('#departure_date').val());
      
      if (totalDays < 1 || isNaN(departureDate.getTime())) return;
      
      const arrivalDate = new Date(departureDate);
      arrivalDate.setDate(departureDate.getDate() + totalDays - 1);
      
      $('#arrival_date').val(arrivalDate.toISOString().split('T')[0]);
    }
    
    // Calculate total days based on dates
    function calculateTotalDays() {
      const departureDate = new Date($('#departure_date').val());
      const arrivalDate = new Date($('#arrival_date').val());
      
      if (isNaN(departureDate.getTime()) || isNaN(arrivalDate.getTime())) return;
      
      const timeDiff = arrivalDate.getTime() - departureDate.getTime();
      const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
      
      if (dayDiff > 0) {
        $('#total_days').val(dayDiff);
        calculatePerdium();
      }
    }
    
    // Calculate arrival date when departure date changes
    function calculateArrivalDate() {
      calculateDates();
      calculatePerdium();
    }
    
    $(document).ready(function() {
    // Initialize Select2 for the dropdowns
    $('#budget_owner_id, #city_id, #et_month').select2({
        theme: 'classic',
        width: '100%'
    });
});
    
    // Load remaining perdium budget
    function loadPerdiumRemaining() {
      const ownerId = $('#budget_owner_id').val();
      const etMonth = $('#et_month').val();
      const codeId = 6; // Ayroh Assentah code
      const year = <?php echo $current_ethio_year; ?>;

      if (!ownerId || !etMonth) return;

      $.get('get_remaining_perdium.php', {
        owner_id: ownerId,
        code_id: codeId,
        month: etMonth,
        year: year
      }, function(data) {
        const rem = JSON.parse(data);
        $('#rem_monthly').text(parseFloat(rem.remaining_monthly || 0).toFixed(2));
        $('#rem_quarterly').text(parseFloat(rem.remaining_quarterly || 0).toFixed(2));
        $('#rem_yearly').text(parseFloat(rem.remaining_yearly || 0).toFixed(2));
      }).fail(function() {
        console.error('Failed to load remaining budget');
      });
    }
  </script>
</body>
</html>