<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';
// Include Ethiopian calendar configuration
include 'config/ethiopian_calendar.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$codes = $pdo->query("SELECT * FROM budget_codes")->fetchAll();

// Fetch budgets based on selected owner and code
$selected_owner_id = isset($_POST['owner_id']) ? $_POST['owner_id'] : (isset($_GET['owner_id']) ? $_GET['owner_id'] : null);
$selected_code_id = isset($_POST['code_id']) ? $_POST['code_id'] : (isset($_GET['code_id']) ? $_GET['code_id'] : null);

$budget_query = "SELECT b.*, o.code AS owner_code, o.name AS owner_name, c.code AS budget_code, c.name AS budget_name 
                 FROM budgets b 
                 JOIN budget_owners o ON b.owner_id = o.id 
                 JOIN budget_codes c ON b.code_id = c.id
                 WHERE b.is_yearly = FALSE";

$params = [];
if ($selected_owner_id && $selected_code_id) {
    $budget_query .= " AND b.owner_id = ? AND b.code_id = ?";
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
    $month = $_POST['month'] ?? '';
    $yearly_amount = (float)($_POST['yearly_amount'] ?? 0);
    $monthly_amount = (float)($_POST['monthly_amount'] ?? 0);
    $year = date('Y', strtotime($adding_date)) - $ethiopianConfig['year_offset'];
    
    $pdo->beginTransaction();
    
    try {
        if (isset($_POST['id']) && $_POST['action'] == 'update') {
            // Update existing budget
            $quarter = $ethiopianConfig['quarter_map'][$month] ?? 0;
            $stmt = $pdo->prepare("UPDATE budgets SET owner_id = ?, code_id = ?, adding_date = ?, year = ?, yearly_amount = ?, month = ?, monthly_amount = ?, quarter = ? WHERE id = ?");
            $stmt->execute([$owner_id, $code_id, $adding_date, $year, $yearly_amount, $month, $monthly_amount, $quarter, $_POST['id']]);
            $message = 'Budget updated successfully';
        } else {
            if ($yearly_amount > 0) {
                // Check if yearly budget already exists
                $stmt = $pdo->prepare("SELECT id FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND is_yearly = TRUE");
                $stmt->execute([$owner_id, $code_id, $year]);
                
                if ($stmt->fetch()) {
                    $message = 'Yearly budget already exists for this owner and code.';
                } else {
                    // Insert yearly budget
                    $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, yearly_amount, is_yearly, allocated_amount, spent_amount) VALUES (?, ?, ?, ?, ?, TRUE, 0, 0)");
                    $stmt->execute([$owner_id, $code_id, $adding_date, $year, $yearly_amount]);
                    $message = 'Yearly budget added successfully.';
                }
            } elseif ($monthly_amount > 0) {
                // Check if yearly budget exists
                $stmt = $pdo->prepare("SELECT id, yearly_amount, allocated_amount FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND is_yearly = TRUE");
                $stmt->execute([$owner_id, $code_id, $year]);
                $yearly_budget = $stmt->fetch();
                
                if (!$yearly_budget) {
                    $message = 'No yearly budget exists. Add yearly budget first.';
                } else {
                    $available = $yearly_budget['yearly_amount'] - $yearly_budget['allocated_amount'];
                    
                    if ($monthly_amount > $available) {
                        $message = 'Monthly amount exceeds available yearly budget. Available: ' . number_format($available, 2);
                    } else {
                        $quarter = $ethiopianConfig['quarter_map'][$month] ?? 0;
                        
                        // Insert monthly budget
                        $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, month, monthly_amount, quarter, parent_id, allocated_amount, spent_amount, is_yearly) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, FALSE)");
                        $stmt->execute([$owner_id, $code_id, $adding_date, $year, $month, $monthly_amount, $quarter, $yearly_budget['id'], $monthly_amount]);
                        
                        // Update yearly allocated amount
                        $new_allocated = $yearly_budget['allocated_amount'] + $monthly_amount;
                        $stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = ? WHERE id = ?");
                        $stmt->execute([$new_allocated, $yearly_budget['id']]);
                        
                        $message = 'Monthly budget added successfully.';
                    }
                }
            } else {
                $message = 'Please enter a yearly or monthly amount.';
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}

// Handle deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT owner_id, code_id, year, monthly_amount, spent_amount, parent_id FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $budget = $stmt->fetch();
    
    if ($budget['spent_amount'] > 0) {
        $message = 'Cannot delete budget with transactions.';
    } else {
        $pdo->beginTransaction();
        
        try {
            if ($budget['monthly_amount'] > 0) {
                // Refund the allocated amount to yearly budget
                $stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = allocated_amount - ? WHERE id = ?");
                $stmt->execute([$budget['monthly_amount'], $budget['parent_id']]);
            }
            
            // Delete the budget
            $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            
            $pdo->commit();
            $message = 'Budget deleted successfully';
            header('Location: budget_adding.php?owner_id=' . $budget['owner_id'] . '&code_id=' . $budget['code_id']);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Error deleting budget: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Adding - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
        
        .ethiopic-font {
            font-family: 'Noto Sans Ethiopic', sans-serif;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                <div id="messageContainer" class="hidden bg-blue-50 text-blue-700 p-4 rounded-lg mb-6">
                    <i class="fas fa-info-circle mr-2"></i>
                    <span id="messageText"></span>
                </div>
                <form method="post" class="space-y-6" id="budgetForm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Adding Date</label>
                            <input type="date" id="adding_date" name="adding_date" class="input-group" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owners Code</label>
                            <select name="owner_id" class="input-group" required onchange="updateSelectedOwner(this)">
                                <option value="">Select Owner</option>
                                <option value="1">OWNER1 - Owner One</option>
                                <option value="2">OWNER2 - Owner Two</option>
                                <option value="3">OWNER3 - Owner Three</option>
                            </select>
                            <small class="text-slate-500 text-sm mt-1 block">Selected Owner: <span id="selected_owner">None</span></small>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Code</label>
                            <select name="code_id" class="input-group" required onchange="updateSelectedCode(this)">
                                <option value="">Select Code</option>
                                <option value="1">CODE1 - Code One</option>
                                <option value="2">CODE2 - Code Two</option>
                                <option value="3">CODE3 - Code Three</option>
                            </select>
                            <small class="text-slate-500 text-sm mt-1 block">Selected Code: <span id="selected_code">None</span></small>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Select Month Info</label>
                            <select id="month" name="month" class="input-group ethiopic-font" onchange="updateQuarter()" required>
                                <!-- Months will be populated by JavaScript -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Monthly Amount</label>
                            <input type="number" step="0.01" name="monthly_amount" class="input-group" value="">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Yearly Amount</label>
                            <input type="number" step="0.01" name="yearly_amount" class="input-group" value="">
                        </div>
                    </div>
                    
                    <div class="bg-slate-100 p-4 rounded-lg">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Quarter</label>
                        <div class="text-xl font-bold text-slate-800" id="quarter_label">Q1</div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn-primary">
                            Save
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
                                <th class="px-4 py-3">Directorates/Programes</th>
                                <th class="px-4 py-3">Budget Codes</th>
                                <th class="px-4 py-3">Month</th>
                                <th class="px-4 py-3">Monthly Amount</th>
                                <th class="px-4 py-3">Yearly Amount</th>
                                <th class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-slate-200 hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium">1</td>
                                <td class="px-4 py-2">OWNER1 - Owner One</td>
                                <td class="px-4 py-2">CODE1 - Code One</td>
                                <td class="px-4 py-2 ethiopic-font">መስከረም</td>
                                <td class="px-4 py-2 font-medium">5,000.00 ETB</td>
                                <td class="px-4 py-2 font-medium">60,000.00 ETB</td>
                                <td class="px-4 py-2">
                                    <div class="flex space-x-2">
                                        <a href="#" class="btn-secondary btn-sm">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </a>
                                        <a href="#" class="btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash mr-1"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200 hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium">2</td>
                                <td class="px-4 py-2">OWNER2 - Owner Two</td>
                                <td class="px-4 py-2">CODE2 - Code Two</td>
                                <td class="px-4 py-2 ethiopic-font">ጥቅምት</td>
                                <td class="px-4 py-2 font-medium">7,500.00 ETB</td>
                                <td class="px-4 py-2 font-medium">90,000.00 ETB</td>
                                <td class="px-4 py-2">
                                    <div class="flex space-x-2">
                                        <a href="#" class="btn-secondary btn-sm">
                                            <i class="fas fa-edit mr-1"></i> Edit
                                        </a>
                                        <a href="#" class="btn-danger btn-sm" onclick="return confirm('Are you sure?')">
                                            <i class="fas fa-trash mr-1"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Ethiopian calendar configuration
        const ethiopianConfig = {
            months: ['መስከረም', 'ጥቅምት', 'ህዳር', 'ታኅሣሥ', 'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ', 'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ', 'ጳጉሜ'],
            quarterMap: {
                'መስከረም': 1, 'ጥቅምት': 1, 'ህዳር': 1,
                'ታኅሣሥ': 2, 'ጥር': 2, 'የካቲት': 2,
                'መጋቢት': 3, 'ሚያዝያ': 3, 'ግንቦት': 3,
                'ሰኔ': 4, 'ሐምሌ': 4, 'ነሐሴ': 4, 'ጳጉሜ': 4
            },
            yearOffset: 8
        };

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
            
            // Populate month dropdown
            populateMonthDropdown();
            
            // Initialize quarter
            updateQuarter();
            
            // Form submission handler
            document.getElementById('budgetForm').addEventListener('submit', function(e) {
                e.preventDefault();
                showMessage('Budget saved successfully!', 'success');
            });
        });

        // Populate month dropdown with Ethiopian months
        function populateMonthDropdown() {
            const monthSelect = document.getElementById('month');
            monthSelect.innerHTML = '';
            
            ethiopianConfig.months.forEach(month => {
                const option = document.createElement('option');
                option.value = month;
                option.textContent = month;
                monthSelect.appendChild(option);
            });
            
            // Set default selection to current month
            const currentMonthIndex = new Date().getMonth();
            if (monthSelect.options[currentMonthIndex]) {
                monthSelect.selectedIndex = currentMonthIndex;
            }
        }

        // Update selected owner text
        function updateSelectedOwner(select) {
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                const ownerName = selectedOption.text.split(' - ')[1];
                document.getElementById('selected_owner').textContent = ownerName;
            } else {
                document.getElementById('selected_owner').textContent = 'None';
            }
        }

        // Update selected code text
        function updateSelectedCode(select) {
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                const codeName = selectedOption.text.split(' - ')[1];
                document.getElementById('selected_code').textContent = codeName;
            } else {
                document.getElementById('selected_code').textContent = 'None';
            }
        }

        // Update quarter based on selected month
        function updateQuarter() {
            const monthSelect = document.getElementById('month');
            const selectedMonth = monthSelect.value;
            const quarter = ethiopianConfig.quarterMap[selectedMonth] || 1;
            
            document.getElementById('quarter_label').textContent = `Q${quarter}`;
        }

        // Show message to user
        function showMessage(message, type = 'info') {
            const messageContainer = document.getElementById('messageContainer');
            const messageText = document.getElementById('messageText');
            
            messageText.textContent = message;
            
            // Set color based on message type
            if (type === 'success') {
                messageContainer.className = 'bg-green-50 text-green-700 p-4 rounded-lg mb-6';
            } else if (type === 'error') {
                messageContainer.className = 'bg-red-50 text-red-700 p-4 rounded-lg mb-6';
            } else {
                messageContainer.className = 'bg-blue-50 text-blue-700 p-4 rounded-lg mb-6';
            }
            
            messageContainer.classList.remove('hidden');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                messageContainer.classList.add('hidden');
            }, 5000);
        }
    </script>
</body>
</html>