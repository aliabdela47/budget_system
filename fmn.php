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

// Check if it's an AJAX search request
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $stmt = $pdo->prepare("
        SELECT f.*, o.code AS owner_code
        FROM fuel_transactions f
        JOIN budget_owners o ON f.owner_id = o.id
        WHERE f.driver_name LIKE :search 
           OR f.plate_number LIKE :search 
           OR o.code LIKE :search 
           OR o.name LIKE :search
        ORDER BY f.date DESC
    ");
    $stmt->execute(['search' => '%' . $search . '%']);
    $fuel_trans = $stmt->fetchAll();
    
    // Return JSON response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode($fuel_trans);
    exit;
}

// Regular page load - get all transactions
$fuel_trans = $pdo->query("
    SELECT f.*, o.code AS owner_code
    FROM fuel_transactions f
    JOIN budget_owners o ON f.owner_id = o.id
    ORDER BY f.date DESC
")->fetchAll();

// Last fuel price to prefill the form
$last_price = $pdo
    ->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")
    ->fetchColumn() ?: 0;

// Editing existing record?
$fuel = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
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
        $_SESSION['message'] = 'Gauge error: Current gauge less than expected new gauge.';
        $_SESSION['message_type'] = 'error';
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
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
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
                    $new_gauge, $gauge_gap, $_POST['id']
                ]);
                $_SESSION['message'] = 'Fuel transaction updated';
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
                $_SESSION['message'] = 'Fuel transaction added';
            }

            $pdo->commit();
            $_SESSION['message_type'] = 'success';
            
            // PRG: Redirect after processing to avoid resubmit
            header('Location: fuel_management.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Handle delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([ $_GET['id'] ]);
    $_SESSION['message'] = 'Fuel transaction deleted';
    $_SESSION['message_type'] = 'success';
    header('Location: fuel_management.php');
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
    body { font-family: 'Inter', sans-serif; }
    
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

    /* Live search specific styles */
    .search-container {
      position: relative;
      margin-bottom: 1rem;
    }
    
    .search-results {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 0.375rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      z-index: 10;
      max-height: 300px;
      overflow-y: auto;
    }
    
    .search-result-item {
      padding: 0.75rem;
      border-bottom: 1px solid #f1f5f9;
      cursor: pointer;
    }
    
    .search-result-item:hover {
      background-color: #f8fafc;
    }
    
    .search-result-item:last-child {
      border-bottom: none;
    }
    
    .highlight {
      background-color: #ffeb3b;
      font-weight: bold;
    }
    
    .loading-spinner {
      display: inline-block;
      width: 20px;
      height: 20px;
      border: 3px solid #f3f3f3;
      border-top: 3px solid #4f46e5;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-right: 8px;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
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

      <!-- Fuel Transaction Form -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">
          <?php echo isset($fuel) ? 'Edit Fuel Transaction' : 'Add New Fuel Transaction'; ?>
        </h2>
        <form id="fuelForm" method="POST" class="space-y-4">
          <?php if (isset($fuel)): ?>
            <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>
          
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
              <select name="owner_id" id="owner_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="loadFuelRemaining()">
                <option value="">Select Owner</option>
                <?php foreach ($owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>" <?php echo (isset($fuel) && $fuel['owner_id'] == $o['id']) || (isset($_POST['owner_id']) && $_POST['owner_id'] == $o['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Driver Name *</label>
              <input type="text" name="driver_name" value="<?php echo isset($fuel) ? htmlspecialchars($fuel['driver_name']) : (isset($_POST['driver_name']) ? htmlspecialchars($_POST['driver_name']) : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number *</label>
              <select name="plate_number" id="plate_number" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="fetchLastGauge(this.value)">
                <option value="">Select Vehicle</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?php echo $v['plate_no']; ?>" <?php echo (isset($fuel) && $fuel['plate_number'] == $v['plate_no']) || (isset($_POST['plate_number']) && $_POST['plate_number'] == $v['plate_no']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['model']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" onchange="loadFuelRemaining()">
                <option value="">Select Month</option>
                <option value="መስከረም" <?php echo (isset($fuel) && $fuel['et_month'] == 'መስከረም') || (isset($_POST['et_month']) && $_POST['et_month'] == 'መስከረም') ? 'selected' : ''; ?>>መስከረም</option>
                <option value="ጥቅምት" <?php echo (isset($fuel) && $fuel['et_month'] == 'ጥቅምት') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ጥቅምት') ? 'selected' : ''; ?>>ጥቅምት</option>
                <option value="ህዳር" <?php echo (isset($fuel) && $fuel['et_month'] == 'ህዳር') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ህዳር') ? 'selected' : ''; ?>>ህዳር</option>
                <option value="ታኅሳስ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ታኅሳስ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ታኅሳስ') ? 'selected' : ''; ?>>ታኅሳስ</option>
                <option value="ጥር" <?php echo (isset($fuel) && $fuel['et_month'] == 'ጥር') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ጥር') ? 'selected' : ''; ?>>ጥር</option>
                <option value="የካቲቷ" <?php echo (isset($fuel) && $fuel['et_month'] == 'የካቲቷ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'የካቲቷ') ? 'selected' : ''; ?>>የካቲቷ</option>
                <option value="መጋቢቷ" <?php echo (isset($fuel) && $fuel['et_month'] == 'መጋቢቷ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'መጋቢቷ') ? 'selected' : ''; ?>>መጋቢቷ</option>
                <option value="ሚያዝያ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ሚያዝያ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ሚያዝያ') ? 'selected' : ''; ?>>ሚያዝያ</option>
                <option value="ግንቦቷ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ግንቦቷ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ግንቦቷ') ? 'selected' : ''; ?>>ግንቦቷ</option>
                <option value="ሰኔ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ሰኔ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ሰኔ') ? 'selected' : ''; ?>>ሰኔ</option>
                <option value="ሐምሌ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ሐምሌ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ሐምሌ') ? 'selected' : ''; ?>>ሐምሌ</option>
                <option value="ነሐሴ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ነሐሴ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ነሐሴ') ? 'selected' : ''; ?>>ነሐሴ</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Current Gauge *</label>
              <input type="number" step="0.01" name="current_gauge" id="current" value="<?php echo isset($fuel) ? $fuel['current_gauge'] : (isset($_POST['current_gauge']) ? $_POST['current_gauge'] : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" oninput="calculateFuel()">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (km) *</label>
              <input type="number" step="0.01" name="journey_distance" id="journey" value="<?php echo isset($fuel) ? $fuel['journey_distance'] : (isset($_POST['journey_distance']) ? $_POST['journey_distance'] : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" oninput="calculateFuel()">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price (per liter) *</label>
              <input type="number" step="0.01" name="fuel_price" id="price" value="<?php echo isset($fuel) ? $fuel['fuel_price'] : (isset($_POST['fuel_price']) ? $_POST['fuel_price'] : $last_price); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" oninput="calculateFuel()">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable Amount (liters)</label>
              <input type="number" step="0.01" name="refuelable_amount" id="refuelable" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Total Amount</label>
              <input type="number" step="0.01" name="total_amount" id="total" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">New Gauge</label>
              <input type="number" step="0.01" name="new_gauge" id="new_gauge" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Gauge Gap</label>
              <input type="number" step="0.01" name="gauge_gap" id="gap" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
          </div>
          
          <div class="flex justify-end space-x-4 pt-4">
            <?php if (isset($fuel)): ?>
              <a href="fuel_management.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                Cancel
              </a>
            <?php endif; ?>
            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary">
              <?php echo isset($fuel) ? 'Update Transaction' : 'Add Transaction'; ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Budget Status -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Status</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="bg-blue-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-blue-800">Monthly Remaining</h3>
            <p class="text-2xl font-bold text-blue-600" id="rem_monthly">0.00</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-green-800">Quarterly Remaining</h3>
            <p class="text-2xl font-bold text-green-600" id="rem_quarterly">0.00</p>
          </div>
          <div class="bg-purple-50 p-4 rounded-lg">
            <h3 class="text-sm font-medium text-purple-800">Yearly Remaining</h3>
            <p class="text-2xl font-bold text-purple-600" id="rem_yearly">0.00</p>
          </div>
        </div>
      </div>

      <!-- Generate Fuel Report -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Generate Fuel Report</h2>
        <form action="reports/fuel_filtered_report.php" method="GET" target="_blank" class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Start Date</label>
              <input type="date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">End Date</label>
              <input type="date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" value="<?php echo date('Y-m-t'); ?>">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner</label>
              <select name="owner_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                <option value="">All Owners</option>
                <?php foreach ($owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>">
                    <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="flex space-x-4">
            <button type="submit" name="format" value="html" class="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary">
              <i class="fas fa-file-alt mr-2"></i> View HTML Report
            </button>
            <button type="submit" name="format" value="pdf" class="px-4 py-2 bg-secondary text-white rounded-md hover:bg-secondary-dark focus:outline-none focus:ring-2 focus:ring-secondary">
              <i class="fas fa-file-pdf mr-2"></i> Download PDF
            </button>
          </div>
        </form>
      </div>

      <!-- Fuel Transactions Table with Live Search -->
      <div class="bg-white rounded-xl p-6 shadow-sm">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Fuel Transactions</h2>
        
        <!-- Live Search Input -->
        <div class="mb-6">
          <div class="search-container">
            <div class="relative">
              <input type="text" id="liveSearch" placeholder="Search transactions by driver, plate, or owner..." 
                    class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
              <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <i class="fas fa-search text-gray-400"></i>
              </div>
            </div>
            <div id="searchResults" class="search-results hidden"></div>
          </div>
        </div>
        
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
              <?php if (empty($fuel_trans)): ?>
                <tr>
                  <td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">
                    No fuel transactions found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($fuel_trans as $f): ?>
                  <tr>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo date('M j, Y', strtotime($f['date'])); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($f['owner_code']); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($f['driver_name']); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($f['plate_number']); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($f['et_month']); ?></td>
                    <td class="px-4 py-4 text-sm text-gray-900"><?php echo number_format($f['total_amount'], 2); ?></td>
                    <td class="px-4 py-4 text-sm">
                      <div class="flex space-x-2">
                        <a href="?action=edit&id=<?php echo $f['id']; ?>" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200">
                          <i class="fas fa-edit mr-1"></i> Edit
                        </a>
                        <a href="reports/fuel_filtered_report.php?id=<?php echo $f['id']; ?>" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                          <i class="fas fa-print mr-1"></i> Print
                        </a>
                        <a href="?action=delete&id=<?php echo $f['id']; ?>" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this transaction?')">
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

    // AJAX form submission to avoid page reload
    $(document).ready(function() {
      $('#fuelForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = $(this).serialize();
        const formAction = $(this).attr('action') || window.location.href;
        const formMethod = $(this).attr('method') || 'POST';
        
        $.ajax({
          url: formAction,
          type: formMethod,
          data: formData,
          success: function(response) {
            // Show success message
            $('#message').remove();
            $('.main-content > .p-6').prepend(
              '<div id="message" class="fade-out mb-6 p-4 rounded-lg bg-success/20 text-success">' +
                '<div class="flex justify-between items-center">' +
                  '<p>Transaction saved successfully</p>' +
                  '<button onclick="document.getElementById(\'message\').classList.add(\'hide\')" class="text-lg">&times;</button>' +
                '</div>' +
              '</div>'
            );
            
            // Auto-hide message
            setTimeout(function() {
              $('#message').addClass('hide');
              setTimeout(() => $('#message').remove(), 500);
            }, 5000);
            
            // Reload the transactions table only
            $.get('get_fuel_transactions.php', function(data) {
              $('#transactionsTable').html(data);
            });
            
            // Reset form if not in edit mode
            if (!$('input[name="id"]').length) {
              $('#fuelForm')[0].reset();
              calculateFuel();
            }
          },
          error: function(xhr, status, error) {
            // Show error message
            $('#message').remove();
            $('.main-content > .p-6').prepend(
              '<div id="message" class="fade-out mb-6 p-4 rounded-lg bg-error/20 text-error">' +
                '<div class="flex justify-between items-center'>' +
                  '<p>Error: ' + xhr.responseText + '</p>' +
                  '<button onclick="document.getElementById(\'message\').classList.add(\'hide\')" class="text-lg">&times;</button>' +
                '</div>' +
              '</div>'
            );
          }
        });
      });

      // Live Search functionality
      let searchTimeout;
      $('#liveSearch').on('input', function() {
        const searchText = $(this).val().trim();
        const searchResults = $('#searchResults');
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        if (searchText.length === 0) {
          searchResults.addClass('hidden');
          return;
        }
        
        // Show loading indicator
        searchResults.html('<div class="search-result-item"><span class="loading-spinner"></span>Searching...</div>').removeClass('hidden');
        
        // Set new timeout with debounce
        searchTimeout = setTimeout(function() {
          $.ajax({
            url: 'fuel_management.php',
            type: 'GET',
            data: { search: searchText },
            success: function(data) {
              try {
                const results = JSON.parse(data);
                displaySearchResults(results, searchText);
              } catch (e) {
                console.error('Error parsing search results:', e);
                searchResults.html('<div class="search-result-item">Error loading results</div>');
              }
            },
            error: function(xhr, status, error) {
              console.error('Search error:', error);
              searchResults.html('<div class="search-result-item">Search error occurred</div>');
            }
          });
        }, 300); // 300ms debounce
      });

      // Display search results
      function displaySearchResults(results, searchText) {
        const resultsContainer = $('#searchResults');
        resultsContainer.empty();
        
        if (results.length === 0) {
          resultsContainer.html('<div class="search-result-item">No results found</div>');
        } else {
          results.forEach(transaction => {
            const resultItem = `
              <div class="search-result-item" data-id="${transaction.id}">
                <div class="font-medium">${highlight(transaction.driver_name, searchText)}</div>
                <div class="text-sm text-gray-600">
                  ${highlight(transaction.plate_number, searchText)} | 
                  ${highlight(transaction.owner_code, searchText)} | 
                  ${transaction.et_month} | 
                  $${parseFloat(transaction.total_amount).toFixed(2)}
                </div>
                <div class="text-xs text-gray-500 mt-1">
                  ${new Date(transaction.date).toLocaleDateString()}
                </div>
              </div>
            `;
            resultsContainer.append(resultItem);
          });
        }
        
        resultsContainer.removeClass('hidden');
      }

      // Highlight matching text in search results
      function highlight(text, search) {
        if (!search) return text;
        const regex = new RegExp(`(${search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return text.replace(regex, '<span class="highlight">$1</span>');
      }

      // Handle click on search result
      $(document).on('click', '.search-result-item', function() {
        const transactionId = $(this).data('id');
        // Scroll to the transaction in the table
        const tableRow = $(`tr:has(a[href*="id=${transactionId}"])`);
        if (tableRow.length) {
          $('html, body').animate({
            scrollTop: tableRow.offset().top - 100
          }, 500);
          
          // Highlight the row temporarily
          tableRow.addClass('bg-yellow-100');
          setTimeout(() => {
            tableRow.removeClass('bg-yellow-100');
          }, 2000);
        }
        
        $('#searchResults').addClass('hidden');
        $('#liveSearch').val('');
      });

      // Close search results when clicking outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.search-container').length) {
          $('#searchResults').addClass('hidden');
        }
      });
    });
  </script>
</body>
</html>