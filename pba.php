<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Add budget_type and program_name to the budgets table if they don't exist
try {
    $pdo->exec("
        ALTER TABLE budgets 
        ADD COLUMN IF NOT EXISTS budget_type ENUM('governmental', 'program') DEFAULT 'governmental',
        ADD COLUMN IF NOT EXISTS program_name VARCHAR(255) DEFAULT NULL
    ");
} catch (PDOException $e) {
    // Columns might already exist, so we can ignore the error
}

$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$codes = $pdo->query("SELECT * FROM budget_codes")->fetchAll();

// Fetch budgets based on selected owner and code
$selected_owner_id = isset($_POST['owner_id']) ? $_POST['owner_id'] : (isset($_GET['owner_id']) ? $_GET['owner_id'] : null);
$selected_code_id = isset($_POST['code_id']) ? $_POST['code_id'] : (isset($_GET['code_id']) ? $_GET['code_id'] : null);
$budget_query = "SELECT b.*, o.code AS owner_code, o.name AS owner_name, c.code AS budget_code, c.name AS budget_name 
                 FROM budgets b 
                 JOIN budget_owners o ON b.owner_id = o.id 
                 JOIN budget_codes c ON b.code_id = c.id";
$params = [];
if ($selected_owner_id && $selected_code_id) {
    $budget_query .= " WHERE b.owner_id = ? AND b.code_id = ?";
    $params = [$selected_owner_id, $selected_code_id];
}
$stmt = $pdo->prepare($budget_query);
$stmt->execute($params);
$budgets = $stmt->fetchAll();

$budget = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $budget = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id = $_POST['owner_id'];
    $code_id = $_POST['code_id'];
    $adding_date = $_POST['adding_date'];
    $budget_type = $_POST['budget_type'];
    $program_name = isset($_POST['program_name']) ? $_POST['program_name'] : null;
    
    // For governmental budgets, we need month and quarter info
    if ($budget_type == 'governmental') {
        $et_info = getEtMonthAndQuarter($adding_date);
        $month = $_POST['month'] ?? '';
        $quarter = $quarterMap[$month] ?? 0;
        $monthly_amount = (float)($_POST['monthly_amount'] ?? 0);
    } else {
        // For program budgets, we don't use month/quarter
        $month = '';
        $quarter = 0;
        $monthly_amount = 0;
    }
    
    $yearly_amount = (float)($_POST['yearly_amount'] ?? 0);
    $year = date('Y', strtotime($adding_date)) - 8;

    $pdo->beginTransaction();
    try {
        if ($budget_type == 'governmental') {
            // Fetch existing yearly budget for validation (governmental only)
            $stmt = $pdo->prepare("SELECT id, yearly_amount, remaining_yearly FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0 AND budget_type = 'governmental'");
            $stmt->execute([$owner_id, $code_id, $year]);
            $yearly_budget = $stmt->fetch();

            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                // Update budget
                $stmt = $pdo->prepare("UPDATE budgets SET owner_id = ?, code_id = ?, adding_date = ?, year = ?, yearly_amount = ?, month = ?, monthly_amount = ?, quarter = ?, budget_type = ?, program_name = ? WHERE id = ?");
                $stmt->execute([$owner_id, $code_id, $adding_date, $year, $yearly_amount, $month, $monthly_amount, $quarter, $budget_type, $program_name, $_POST['id']]);
                $message = 'Budget updated';
            } else {
                if ($yearly_amount > 0) {
                    if ($yearly_budget) {
                        $message = 'Yearly budget already added for this owner and code.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter, remaining_yearly, remaining_monthly, remaining_quarterly, budget_type, program_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$owner_id, $code_id, $adding_date, $year, $yearly_amount, '', 0, 0, $yearly_amount, 0, 0, $budget_type, $program_name]);
                        $message = 'Yearly budget added successfully.';
                    }
                } elseif ($monthly_amount > 0) {
                    if (!$yearly_budget) {
                        $message = 'No yearly budget exists. Add yearly budget first.';
                    } else {
                        if ($monthly_amount > $yearly_budget['remaining_yearly']) {
                            $message = 'Monthly amount exceeds remaining yearly budget.';
                            $pdo->exec("LOCK TABLES budgets WRITE"); //added
                        } else {
                            $new_remaining_yearly = $yearly_budget['remaining_yearly'] - $monthly_amount;
                            $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter, remaining_yearly, remaining_monthly, remaining_quarterly, budget_type, program_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$owner_id, $code_id, $adding_date, $year, 0, $month, $monthly_amount, $quarter, $new_remaining_yearly, $monthly_amount, $monthly_amount * 3, $budget_type, $program_name]);
                            $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                            $stmt->execute([$new_remaining_yearly, $yearly_budget['id']]);
                            $message = 'Monthly budget added successfully.';
                        }
                    }
                } else {
                    $message = 'Please enter a yearly or monthly amount.';
                }
            }
        } else {
            // Program budget handling
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                // Update program budget
                $stmt = $pdo->prepare("UPDATE budgets SET owner_id = ?, code_id = ?, adding_date = ?, year = ?, yearly_amount = ?, budget_type = ?, program_name = ? WHERE id = ?");
                $stmt->execute([$owner_id, $code_id, $adding_date, $year, $yearly_amount, $budget_type, $program_name, $_POST['id']]);
                $message = 'Program budget updated';
            } else {
                // Check if program budget already exists for this owner and code
                $stmt = $pdo->prepare("SELECT id FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND budget_type = 'program'");
                $stmt->execute([$owner_id, $code_id, $year]);
                $existing_program_budget = $stmt->fetch();
                
                if ($existing_program_budget) {
                    $message = 'Program budget already exists for this owner and code.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, yearly_amount, month, monthly_amount, quarter, remaining_yearly, remaining_monthly, remaining_quarterly, budget_type, program_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$owner_id, $code_id, $adding_date, $year, $yearly_amount, '', 0, 0, $yearly_amount, 0, 0, $budget_type, $program_name]);
                    $message = 'Program budget added successfully.';
                }
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT owner_id, code_id, year, monthly_amount, remaining_yearly, budget_type FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $budget = $stmt->fetch();

    $pdo->beginTransaction();
    try {
        if ($budget['budget_type'] == 'governmental' && $budget['monthly_amount'] > 0) {
            $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = remaining_yearly + ? WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0 AND budget_type = 'governmental'");
            $stmt->execute([$budget['monthly_amount'], $budget['owner_id'], $budget['code_id'], $budget['year']]);
        }
        $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $pdo->commit();
        $message = 'Budget deleted';
        header('Location: budget_adding.php?owner_id=' . $budget['owner_id'] . '&code_id=' . $budget['code_id']);
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Adding - Budget System</title>
    <script src="css/tailwind.css"> </script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
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
        
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
        
        .budget-type-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .budget-type-governmental {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        
        .budget-type-program {
            background-color: #dbeafe;
            color: #1e40af;
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
                    <a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-white/20">
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
                    <a href="fuel_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-gas-pump w-5"></i>
                        <span class="ml-3">Fuel Management</span>
                    </a>
                </li>
                <li>
                    <a href="perdium.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-dollar-sign w-5"></i>
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
                        Budget Management
                    </h1>
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
                        <i class="fas fa-info-circle mr-2"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="space-y-6">
                    <?php if ($budget): ?>
                        <input type="hidden" name="id" value="<?php echo $budget['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    
                    <!-- Budget Type Selection -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Budget Type</label>
                        <div class="flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" name="budget_type" value="governmental" class="mr-2" <?php echo (!$budget || $budget['budget_type'] == 'governmental') ? 'checked' : ''; ?> onchange="toggleBudgetTypeFields()">
                                <span>Government Budget</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="budget_type" value="program" class="mr-2" <?php echo ($budget && $budget['budget_type'] == 'program') ? 'checked' : ''; ?> onchange="toggleBudgetTypeFields()">
                                <span>Program Budget</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Adding Date</label>
                            <input type="date" id="adding_date" name="adding_date" class="input-group" value="<?php echo $budget ? $budget['adding_date'] : date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owners Code</label>
                            <select name="owner_id" class="input-group" required onchange="updateSelectedOwner(this)">
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?php echo $o['id']; ?>" <?php echo $budget && $budget['owner_id'] == $o['id'] ? 'selected' : ''; ?>>
                                        <?php echo $o['code'] . ' - ' . $o['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-slate-500 text-sm mt-1 block">Selected Owner: <span id="selected_owner"><?php echo $budget ? ($owners[array_search($budget['owner_id'], array_column($owners, 'id'))]['name']) : ''; ?></span></small>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Code</label>
                            <select name="code_id" class="input-group" required onchange="updateSelectedCode(this)">
                                <?php foreach ($codes as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $budget && $budget['code_id'] == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo $c['code'] . ' - ' . $c['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-slate-500 text-sm mt-1 block">Selected Code: <span id="selected_code"><?php echo $budget ? ($codes[array_search($budget['code_id'], array_column($codes, 'id'))]['name']) : ''; ?></span></small>
                        </div>
                        
                        <!-- Program Name Field (for program budgets) -->
                        <div id="program_name_field" style="display: none;">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Program Name</label>
                            <input type="text" name="program_name" class="input-group" value="<?php echo $budget ? $budget['program_name'] : ''; ?>" placeholder="Enter program name (e.g., Global Fund)">
                        </div>
                        
                        <!-- Month Selection (for governmental budgets) -->
                        <div id="month_field">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Select Month Info</label>
                            <select id="month" name="month" class="input-group" onchange="updateQuarter()">
                                <?php foreach ($etMonths as $m): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $budget && $budget['month'] == $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Monthly Amount (for governmental budgets) -->
                        <div id="monthly_amount_field">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Monthly Amount</label>
                            <input type="number" step="0.01" name="monthly_amount" class="input-group" value="<?php echo $budget ? $budget['monthly_amount'] : ''; ?>">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Yearly Amount</label>
                            <input type="number" step="0.01" name="yearly_amount" class="input-group" value="<?php echo $budget ? $budget['yearly_amount'] : ''; ?>" required>
                        </div>
                    </div>
                    
                    <!-- Quarter Display (for governmental budgets) -->
                    <div id="quarter_field" class="bg-slate-100 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Quarter</label>
                        <div class="text-xl font-bold text-slate-800" id="quarter_label">Calculating...</div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn-primary">
                            <?php echo $budget ? 'Update' : 'Save'; ?>
                        </button>
                        <button type="button" class="btn-info" onclick="window.print()">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
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
                                <th class="px-4 py-3">Directorates/Programes</th>
                                <th class="px-4 py-3">Budget Codes</th>
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Monthly Amount</th>
                                <th class="px-4 py-3">Yearly Amount</th>
                                <th class="px-4 py-3">Program Name</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $b): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50">
                                    <td class="px-4 py-2 font-medium"><?php echo $b['id']; ?></td>
                                    <td class="px-4 py-2">
                                        <span class="budget-type-badge <?php echo $b['budget_type'] == 'governmental' ? 'budget-type-governmental' : 'budget-type-program'; ?>">
                                            <?php echo ucfirst($b['budget_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2"><?php echo $b['owner_code'] . ' - ' . $b['owner_name']; ?></td>
                                    <td class="px-4 py-2"><?php echo $b['budget_code'] . ' - ' . $b['budget_name']; ?></td>
                                    <td class="px-4 py-2"><?php echo $b['month']; ?></td>
                                    <td class="px-4 py-2 font-medium"><?php echo number_format($b['monthly_amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2 font-medium"><?php echo number_format($b['yearly_amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2"><?php echo $b['program_name']; ?></td>
                                    <td class="px-4 py-2">
                                        <div class="flex space-x-2">
                                            <a href="?action=edit&id=<?php echo $b['id']; ?>" class="btn-secondary btn-sm">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <a href="?action=delete&id=<?php echo $b['id']; ?>" class="btn-danger btn-sm" onclick="return confirm('Are you sure?')">
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
            
            // Initialize quarter on page load
            updateQuarter();
            
            // Initialize selected owner and code text
            const ownerSelect = document.querySelector('select[name="owner_id"]');
            if (ownerSelect) updateSelectedOwner(ownerSelect);
            
            const codeSelect = document.querySelector('select[name="code_id"]');
            if (codeSelect) updateSelectedCode(codeSelect);
            
            // Initialize budget type fields
            toggleBudgetTypeFields();
        });

        // Toggle fields based on budget type
        function toggleBudgetTypeFields() {
            const budgetType = document.querySelector('input[name="budget_type"]:checked').value;
            const programNameField = document.getElementById('program_name_field');
            const monthField = document.getElementById('month_field');
            const monthlyAmountField = document.getElementById('monthly_amount_field');
            const quarterField = document.getElementById('quarter_field');
            const monthSelect = document.getElementById('month');
            const monthlyAmountInput = document.querySelector('input[name="monthly_amount"]');
            
            if (budgetType === 'program') {
                // Show program name field, hide month-related fields
                programNameField.style.display = 'block';
                monthField.style.display = 'none';
                monthlyAmountField.style.display = 'none';
                quarterField.style.display = 'none';
                
                // Make month and monthly amount optional
                monthSelect.removeAttribute('required');
                monthlyAmountInput.removeAttribute('required');
            } else {
                // Hide program name field, show month-related fields
                programNameField.style.display = 'none';
                monthField.style.display = 'block';
                monthlyAmountField.style.display = 'block';
                quarterField.style.display = 'block';
                
                // Make month required for governmental budgets
                monthSelect.setAttribute('required', 'required');
            }
        }

        // Update selected owner text
        function updateSelectedOwner(select) {
            const selectedOption = select.options[select.selectedIndex];
            const ownerName = selectedOption.text.split(' - ')[1];
            document.getElementById('selected_owner').textContent = ownerName;
        }

        // Update selected code text
        function updateSelectedCode(select) {
            const selectedOption = select.options[select.selectedIndex];
            const codeName = selectedOption.text.split(' - ')[1];
            document.getElementById('selected_code').textContent = codeName;
        }

        // Update quarter based on selected month
        function updateQuarter() {
            const monthSelect = document.getElementById('month');
            const selectedMonth = monthSelect.value;
            const quarterMap = {
                'Meskerem': 1, 'Tikimt': 1, 'Hidar': 1,
                'Tahsas': 2, 'Tir': 2, 'Yekatit': 2,
                'Megabit': 3, 'Miazia': 3, 'Ginbot': 3,
                'Sene': 4, 'Hamle': 4, 'Nehase': 4,
                'Pagume': 4
            };
            
            const quarter = quarterMap[selectedMonth] || 'Unknown';
            document.getElementById('quarter_label').textContent = `Q${quarter}`;
        }
    </script>
</body>
</html>