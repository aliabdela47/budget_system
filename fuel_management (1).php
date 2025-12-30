<?php
include 'includes/db.php';
include 'includes/functions.php';
if (!isset($_SESSION['user_id'])) header('Location: index.php');

$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll(); // Fetch vehicles for dropdown
$fuel_trans = $pdo->query("SELECT f.*, o.code AS owner_code 
                           FROM fuel_transactions f 
                           JOIN budget_owners o ON f.owner_id = o.id")->fetchAll();
$last_price = $pdo->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")->fetchColumn() ?: 0;

$fuel = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $fuel = $stmt->fetch();
}

$et_info = getEtMonthAndQuarter(date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id = $_POST['owner_id'];
    $driver_name = $_POST['driver_name'];
    $plate_number = $_POST['plate_number'];
    $et_month = $_POST['et_month'];
    $current_gauge = (float)$_POST['current_gauge'];
    $journey_distance = (float)$_POST['journey_distance'];
    $fuel_price = (float)$_POST['fuel_price'];
    $refuelable_amount = (float)$_POST['refuelable_amount'];
    $total_amount = (float)$_POST['total_amount'];
    $new_gauge = (float)$_POST['new_gauge'];
    $gauge_gap = (float)$_POST['gauge_gap'];

    $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number = ? ORDER BY date DESC LIMIT 1");
    $stmt->execute([$plate_number]);
    $last_new_gauge = $stmt->fetchColumn() ?: 0;
    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $message = 'Gauge error: Current gauge less than expected new gauge';
    } else {
        $pdo->beginTransaction();
        try {
            // Deduct from budget code ID 5 (Sansii kee Sukutih 6217)
            $fuel_code_id = 5; // Fixed ID for fuel budget code
            $year = date('Y') - 8; // Ethiopian year
            $quarterMap = [
                'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
                'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሳስ' => 2,
                'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
                'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4,
            ];
            $quarter = $quarterMap[$et_month] ?? 0;

            // Fetch monthly budget for fuel
            $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?");
            $stmt->execute([$owner_id, $fuel_code_id, $year, $et_month]);
            $budget = $stmt->fetch();

            if ($budget) {
                $new_remaining_monthly = $budget['remaining_monthly'] - $total_amount;
                if ($new_remaining_monthly < 0) {
                    throw new Exception('Insufficient remaining monthly budget for fuel.');
                }
                $stmt = $pdo->prepare("UPDATE budgets SET remaining_monthly = ? WHERE id = ?");
                $stmt->execute([$new_remaining_monthly, $budget['id']]);
            } else {
                // Fetch yearly budget if monthly not found
                $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0");
                $stmt->execute([$owner_id, $fuel_code_id, $year]);
                $budget_yearly = $stmt->fetch();

                if ($budget_yearly) {
                    $new_remaining_yearly = $budget_yearly['remaining_yearly'] - $total_amount;
                    if ($new_remaining_yearly < 0) {
                        throw new Exception('Insufficient remaining yearly budget for fuel.');
                    }
                    $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                    $stmt->execute([$new_remaining_yearly, $budget_yearly['id']]);
                } else {
                    throw new Exception('No fuel budget allocated for the selected month.');
                }
            }

            // Insert or update fuel transaction
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("UPDATE fuel_transactions SET owner_id = ?, driver_name = ?, plate_number = ?, et_month = ?, current_gauge = ?, journey_distance = ?, fuel_price = ?, refuelable_amount = ?, total_amount = ?, new_gauge = ?, gauge_gap = ? WHERE id = ?");
                $stmt->execute([$owner_id, $driver_name, $plate_number, $et_month, $current_gauge, $journey_distance, $fuel_price, $refuelable_amount, $total_amount, $new_gauge, $gauge_gap, $_POST['id']]);
                $message = 'Fuel transaction updated';
            } else {
                $stmt = $pdo->prepare("INSERT INTO fuel_transactions (owner_id, driver_name, plate_number, et_month, previous_gauge, current_gauge, journey_distance, fuel_price, refuelable_amount, total_amount, new_gauge, gauge_gap) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$owner_id, $driver_name, $plate_number, $et_month, $last_new_gauge, $current_gauge, $journey_distance, $fuel_price, $refuelable_amount, $total_amount, $new_gauge, $gauge_gap]);
                $message = 'Fuel transaction added';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = 'Fuel transaction deleted';
    header('Location: fuel_management.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuel Management - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
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
        
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
        }
        
        .select2-container--default .select2-selection--single:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 1px #4f46e5;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 0;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
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
                <li>
                    <a href="dashboard.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-tachometer-alt w-5"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-plus-circle w-5"></i>
                        <span class="ml-3">Budget Adding</span>
                    </a>
                </li>
                <li>
                    <a href="settings_owners.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-cog w-5"></i>
                        <span class="ml-3">Settings</span>
                    </a>
                </li>
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
                    <p class="text-slate-600 mt-2">Manage fuel transactions and vehicle refueling</p>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Fuel Management Form -->
            <div class="bg-white rounded-xl p-6 card-hover mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Fuel Management Form</h2>
                <?php if (isset($message)): ?>
                    <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="space-y-6">
                    <?php if ($fuel): ?>
                        <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owners Code</label>
                            <select name="owner_id" class="input-group" required>
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?php echo $o['id']; ?>" <?php echo $fuel && $fuel['owner_id'] == $o['id'] ? 'selected' : ''; ?>>
                                        <?php echo $o['code'] . ' - ' . $o['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month</label>
                            <select name="et_month" id="month" class="input-group" onchange="updateQuarter()" required>
                                <?php foreach ($etMonths as $month): ?>
                                    <option value="<?php echo $month; ?>" <?php echo ($fuel && $fuel['et_month'] == $month) || (!$fuel && $et_info['etMonth'] == $month) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($month); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="mt-2">
                                <label class="block text-sm font-medium text-slate-700">Quarter: <span id="quarter_label"><?php echo $fuel ? $fuel['quarter'] : $et_info['quarter']; ?></span></label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number</label>
                            <select name="plate_number" id="plate_number" class="input-group select2" onchange="fetchLastGauge(this.value)" required>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?php echo htmlspecialchars($v['plate_no']); ?>" <?php echo $fuel && $fuel['plate_number'] == $v['plate_no'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v['plate_no']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Driver Name</label>
                            <input type="text" name="driver_name" class="input-group" value="<?php echo $fuel ? $fuel['driver_name'] : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Current Gauge</label>
                            <input type="number" step="0.01" id="current" name="current_gauge" class="input-group" value="<?php echo $fuel ? $fuel['current_gauge'] : ''; ?>" onchange="calculateFuel()" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (Km)</label>
                            <input type="number" step="0.01" id="journey" name="journey_distance" class="input-group" value="<?php echo $fuel ? $fuel['journey_distance'] : ''; ?>" onchange="calculateFuel()" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price /L</label>
                            <input type="number" step="0.01" id="price" name="fuel_price" class="input-group" value="<?php echo $fuel ? $fuel['fuel_price'] : $last_price; ?>" onchange="calculateFuel()" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable Amount (Ltr)</label>
                            <input type="number" step="0.01" id="refuelable" name="refuelable_amount" class="input-group" readonly>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Total Amount</label>
                            <input type="number" step="0.01" id="total" name="total_amount" class="input-group" readonly>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">New Gauge</label>
                            <input type="number" step="0.01" id="new_gauge" name="new_gauge" class="input-group" readonly>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Gauge Gap</label>
                            <input type="number" step="0.01" id="gap" name="gauge_gap" class="input-group" readonly>
                        </div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn-primary">
                            <?php echo $fuel ? 'Update' : 'Save'; ?>
                        </button>
                        <button type="button" class="btn-info" onclick="window.print()">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Fuel Transactions -->
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
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Driver</th>
                                <th class="px-4 py-3">Plate</th>
                                <th class="px-4 py-3">Total Amount</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fuel_trans as $f): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50">
                                    <td class="px-4 py-2 font-medium"><?php echo $f['id']; ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($f['owner_code']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($f['et_month']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($f['driver_name']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($f['plate_number']); ?></td>
                                    <td class="px-4 py-2 font-medium"><?php echo number_format($f['total_amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2">
                                        <div class="flex space-x-2">
                                            <a href="?action=edit&id=<?php echo $f['id']; ?>" class="btn-secondary btn-sm">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <a href="?action=delete&id=<?php echo $f['id']; ?>" class="btn-danger btn-sm" onclick="return confirm('Are you sure?')">
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
            
            // Initialize Select2
            $('.select2').select2({
                placeholder: "Select a plate number",
                allowClear: true
            });
            
            // Calculate fuel on page load if values exist
            calculateFuel();
        });

        function fetchLastGauge(plateNumber) {
            if (plateNumber) {
                $.get('get_last_gauge.php', { plate_number: plateNumber }, function(data) {
                    const response = JSON.parse(data);
                    $('#current').val(response.last_gauge);
                    calculateFuel(); // Recalculate after setting last gauge
                });
            }
        }

        function calculateFuel() {
            const current = parseFloat(document.getElementById('current').value) || 0;
            const journey = parseFloat(document.getElementById('journey').value) || 0;
            const price = parseFloat(document.getElementById('price').value) || 0;

            const refuelable = journey / 5; // Assuming 5 km per liter as an example
            const total = refuelable * price;
            //const newGauge = current + refuelable;
            const newGauge = current + journey;
            const gap = newGauge - current;

            document.getElementById('refuelable').value = refuelable.toFixed(2);
            document.getElementById('total').value = total.toFixed(2);
            document.getElementById('new_gauge').value = newGauge.toFixed(2);
            document.getElementById('gap').value = gap.toFixed(2);
        }

        function updateQuarter() {
            const month = document.getElementById('month').value;
            const quarterMap = {
                'ሐምሌ': 1, 'ነሐሴ': 1, 'መስከረም': 1,
                'ጥቅምት': 2, 'ህዳር': 2, 'ታኅሳስ': 2,
                'ጥር': 3, 'የካቲቷ': 3, 'መጋቢቷ': 3,
                'ሚያዝያ': 4, 'ግንቦቷ': 4, 'ሰኔ': 4
            };
            document.getElementById('quarter_label').textContent = quarterMap[month] || 0;
        }
    </script>
</body>
</html>