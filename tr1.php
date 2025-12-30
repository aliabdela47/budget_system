<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$codes = $pdo->query("SELECT * FROM budget_codes")->fetchAll();
$transactions = $pdo->query("SELECT t.*, o.code AS owner_code, c.code AS budget_code 
                             FROM transactions t 
                             JOIN budget_owners o ON t.owner_id = o.id 
                             JOIN budget_codes c ON t.code_id = c.id")->fetchAll();

$trans = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $trans = $stmt->fetch();
}

$et_info = getEtMonthAndQuarter(date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id = $_POST['owner_id'];
    $code_id = $_POST['code_id'];
    $amount = (float)($_POST['amount'] ?? 0); // Default to 0 if blank
    $et_month = $_POST['et_month'];
    $quarterMap = [
        'ሐምሌ' => 1, 'ነሐሴ' => 1, 'መስከረም' => 1,
        'ጥቅምት' => 2, 'ህዳር' => 2, 'ታኅሣሥ' => 2,
        'ጥር' => 3, 'የካቲቷ' => 3, 'መጋቢቷ' => 3,
        'ሚያዝያ' => 4, 'ግንቦቷ' => 4, 'ሰኔ' => 4,
    ];
    $quarter = $quarterMap[$et_month] ?? 0;
    $year = date('Y') - 8; // Ethiopian year adjustment

    // Fetch monthly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?");
    $stmt->execute([$owner_id, $code_id, $year, $et_month]);
    $budget = $stmt->fetch();

    // Fetch yearly budget
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0");
    $stmt->execute([$owner_id, $code_id, $year]);
    $budget_yearly = $stmt->fetch();

    if ($budget_yearly || $budget) {
        $yearly_amount = $budget_yearly ? $budget_yearly['yearly_amount'] : 0;
        $monthly_amount = $budget ? $budget['monthly_amount'] : 0;

        // Calculate total spent from transactions
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND YEAR(date) = ?");
        $stmt->execute([$owner_id, $code_id, date('Y')]);
        $trans_year = $stmt->fetchColumn() ?: 0;
        $calculated_remaining_year = $yearly_amount - $trans_year;

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND et_month = ? AND YEAR(date) = ?");
        $stmt->execute([$owner_id, $code_id, $et_month, date('Y')]);
        $trans_month = $stmt->fetchColumn() ?: 0;
        $calculated_remaining_month = $monthly_amount - $trans_month;

        $stmt = $pdo->prepare("SELECT SUM(monthly_amount) FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND quarter = ?");
        $stmt->execute([$owner_id, $code_id, $year, $quarter]);
        $quarterly_alloc = $stmt->fetchColumn() ?: 0;

        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE owner_id = ? AND code_id = ? AND quarter = ? AND YEAR(date) = ?");
        $stmt->execute([$owner_id, $code_id, $quarter, date('Y')]);
        $trans_quarter = $stmt->fetchColumn() ?: 0;
        $calculated_remaining_quarter = $quarterly_alloc - $trans_quarter;

        if (!$budget) {
            $calculated_remaining_month = $calculated_remaining_year;
        }

        if ($quarterly_alloc == 0) {
            $calculated_remaining_quarter = $calculated_remaining_year;
        }

        // Enhanced validation
        if ($amount < 0) {
            $message = 'Amount cannot be negative';
        } elseif ($amount > 0 && !$budget_yearly && !$budget) {
            $message = 'No budget allocated for this combination';
        } elseif ($amount > 0 && min($calculated_remaining_month, $calculated_remaining_quarter, $calculated_remaining_year) < $amount) {
            $message = 'Transaction amount exceeds remaining budget (Monthly: ' . number_format($calculated_remaining_month, 2) . ', Quarterly: ' . number_format($calculated_remaining_quarter, 2) . ', Yearly: ' . number_format($calculated_remaining_year, 2) . ')';
        } else {
            $pdo->beginTransaction();
            try {
                // Update budgets table with new amounts (initial amounts reduced by consumption)
                
                
                if ($amount > 0 && $budget) {
                    $new_monthly_amount = $monthly_amount - $amount;
                    $stmt = $pdo->prepare("UPDATE budgets SET monthly_amount = ? WHERE id = ?");
                    $stmt->execute([$new_monthly_amount, $budget['id']]);
                }
                
                // removed by copilot recommendatio
                
              //  if ($amount > 0 && $budget_yearly) {
               //     $new_yearly_amount = $yearly_amount - $amount;
                //    $stmt = $pdo->prepare("UPDATE budgets SET yearly_amount = ? // WHERE id = ?");
                //    $stmt->execute([$new_yearly_amount, $budget_yearly['id']]);
             //   }

                // removed by copilot recommendatio
                
                
                // Insert or update transaction
                $new_remaining_monthly = $calculated_remaining_month - $amount;
                $new_remaining_quarterly = $calculated_remaining_quarter - $amount;
                $new_remaining_yearly = $calculated_remaining_year - $amount;

                if (isset($_POST['id']) && $_POST['action'] == 'update') {
                    $stmt = $pdo->prepare("UPDATE transactions SET owner_id = ?, code_id = ?, employee_name = ?, ordered_by = ?, reason = ?, amount = ?, et_month = ?, quarter = ?, remaining_month = ?, remaining_quarter = ?, remaining_year = ? WHERE id = ?");
                    $stmt->execute([$owner_id, $code_id, $_POST['employee_name'], $_POST['ordered_by'], $_POST['reason'], $amount, $et_month, $quarter, $new_remaining_monthly, $new_remaining_quarterly, $new_remaining_yearly, $_POST['id']]);
                    $message = 'Transaction updated';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO transactions (owner_id, code_id, employee_name, ordered_by, reason, created_by, amount, et_month, quarter, remaining_month, remaining_quarter, remaining_year, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$owner_id, $code_id, $_POST['employee_name'], $_POST['ordered_by'], $_POST['reason'], $_SESSION['username'], $amount, $et_month, $quarter, $new_remaining_monthly, $new_remaining_quarterly, $new_remaining_yearly]);
                    $message = 'Transaction added';
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error: ' . $e->getMessage();
            }
        }
    } else {
        if ($amount > 0) {
            $message = 'No budget allocated for this combination';
        } else {
            // Allow zero amount transaction with no budget
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO transactions (owner_id, code_id, employee_name, ordered_by, reason, created_by, amount, et_month, quarter, remaining_month, remaining_quarter, remaining_year, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$owner_id, $code_id, $_POST['employee_name'], $_POST['ordered_by'], $_POST['reason'], $_SESSION['username'], $amount, $et_month, $quarter, 0, 0, 0]);
                $message = 'Transaction with zero amount added';
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Transaction - Budget System</title>
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
                    <a href="transaction.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-white/20">
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
                        Transaction Management
                    </h1>
                    <p class="text-slate-600 mt-2">Add and manage financial transactions</p>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Transaction Form -->
            <div class="bg-white rounded-xl p-6 card-hover mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Transaction Form</h2>
                <?php if (isset($message)): ?>
                    <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6">
                        <i class="fas fa-info-circle mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="space-y-6">
                    <?php if ($trans): ?>
                        <input type="hidden" name="id" value="<?php echo $trans['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owners Code</label>
                            <select name="owner_id" class="input-group" onchange="loadAvailable()" required>
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?php echo $o['id']; ?>" <?php echo ($trans && $trans['owner_id'] == $o['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Code</label>
                            <select name="code_id" class="input-group" onchange="loadAvailable()" required>
                                <?php
                                if (!empty($codes)) {
                                    foreach ($codes as $c) {
                                        echo '<option value="' . htmlspecialchars($c['id']) . '"';
                                        if ($trans && $trans['code_id'] == $c['id']) {
                                            echo ' selected';
                                        }
                                        echo '>' . htmlspecialchars($c['code'] . ' - ' . $c['name']) . '</option>';
                                    }
                                } else {
                                    echo '<option value="">No budget codes available</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month</label>
                            <select name="et_month" id="month" class="input-group" onchange="updateQuarter(); loadAvailable();" required>
                                <?php
                                $etMonths = ['መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ'];
                                foreach ($etMonths as $month) {
                                    echo '<option value="' . htmlspecialchars($month) . '"';
                                    if (($trans && $trans['et_month'] == $month) || (!$trans && $et_info['etMonth'] == $month)) {
                                        echo ' selected';
                                    }
                                    echo '>' . htmlspecialchars($month) . '</option>';
                                }
                                ?>
                            </select>
                            <div class="mt-2">
                                <label class="block text-sm font-medium text-slate-700">Quarter: <span id="quarter_label" class="font-bold"><?php echo $trans ? $trans['quarter'] : $et_info['quarter']; ?></span></label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                            <input type="number" step="0.01" name="amount" class="input-group" value="<?php echo $trans ? $trans['amount'] : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Employee Name</label>
                            <input type="text" name="employee_name" class="input-group" value="<?php echo $trans ? htmlspecialchars($trans['employee_name']) : ''; ?>" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ordered By</label>
                            <input type="text" name="ordered_by" class="input-group" value="<?php echo $trans ? htmlspecialchars($trans['ordered_by']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                        <textarea name="reason" class="input-group" rows="3"><?php echo $trans ? htmlspecialchars($trans['reason']) : ''; ?></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                        <div class="bg-slate-100 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Remaining Monthly</label>
                            <div class="text-xl font-bold text-slate-800" id="remaining_month">0.00</div>
                        </div>
                        
                        <div class="bg-slate-100 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Remaining Quarterly</label>
                            <div class="text-xl font-bold text-slate-800" id="remaining_quarter">0.00</div>
                        </div>
                        
                        <div class="bg-slate-100 p-4 rounded-lg">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Remaining Yearly</label>
                            <div class="text-xl font-bold text-slate-800" id="remaining_year">0.00</div>
                        </div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn-primary">
                            <?php echo $trans ? 'Update' : 'Save'; ?>
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
                    <h2 class="text-xl font-bold text-slate-800">Existing Transactions</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th class="px-4 py-3">ID</th>
                                <th class="px-4 py-3">Owner</th>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-4 py-3">Employee</th>
                                <th class="px-4 py-3">Amount</th>
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Quarter</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50">
                                    <td class="px-4 py-2 font-medium"><?php echo $t['id']; ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['owner_code']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['budget_code']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['employee_name']); ?></td>
                                    <td class="px-4 py-2 font-medium"><?php echo number_format($t['amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['et_month']); ?></td>
                                    <td class="px-4 py-2"><?php echo $t['quarter']; ?></td>
                                    <td class="px-4 py-2">
                                        <div class="flex space-x-2">
                                            <a href="?action=edit&id=<?php echo $t['id']; ?>" class="btn-secondary btn-sm">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </a>
                                            <a href="?action=delete&id=<?php echo $t['id']; ?>" class="btn-danger btn-sm" onclick="return confirm('Are you sure?')">
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
            
            // Load available budget on page load
            loadAvailable();
        });

        function loadAvailable() {
            const ownerId = document.querySelector('select[name="owner_id"]').value;
            const codeId = document.querySelector('select[name="code_id"]').value;
            const month = document.querySelector('select[name="et_month"]').value;

            if (ownerId && codeId && month) {
                fetch(`get_remaining.php?owner_id=${ownerId}&code_id=${codeId}&month=${encodeURIComponent(month)}`)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('remaining_month').textContent = data.remaining_month.toFixed(2);
                        document.getElementById('remaining_quarter').textContent = data.remaining_quarter.toFixed(2);
                        document.getElementById('remaining_year').textContent = data.remaining_year.toFixed(2);
                    })
                    .catch(error => console.error('Error:', error));
            }
        }

        function updateQuarter() {
            const month = document.getElementById('month').value;
            const quarterMap = {
                'ሐምሌ': 1, 'ነሐሴ': 1, 'መስከረም': 1,
                'ጥቅምት': 2, 'ህዳር': 2, 'ታኅሳስ': 2,
                'ጥር': 3, 'የካቲት': 3, 'መጋቢት': 3,
                'ሚያዝያ': 4, 'ግንቦት': 4, 'ሰኔ': 4
            };
            document.getElementById('quarter_label').textContent = quarterMap[month] || 0;
            loadAvailable();
        }
    </script>
</body>
</html>