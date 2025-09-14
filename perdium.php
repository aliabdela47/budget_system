<?php
require_once 'includes/init.php';
include 'includes/sidebar.php';


// Check if user is officer
$is_officer = ($_SESSION['role'] == 'officer');

// Fetch user's name from database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? $_SESSION['username'];

// Fetch dropdown data
$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$employees = $pdo->query("SELECT * FROM emp_list")->fetchAll();
$cities = $pdo->query("SELECT * FROM cities")->fetchAll();

// Fetch perdium transactions
$perdium_trans = $pdo->query("
    SELECT p.*,
           o.name AS owner_name,
           o.code AS owner_code,
           o.p_koox AS owner_p_koox,
           e.name_am AS employee_name,
           e.name AS employee_name_english,
           e.salary AS employee_salary,
           e.directorate AS employee_directorate,
           c.name_amharic AS city_name
    FROM perdium_transactions p
    JOIN budget_owners o ON p.budget_owner_id = o.id
    JOIN emp_list e ON p.employee_id = e.id
    JOIN cities c ON p.city_id = c.id
    ORDER BY p.created_at DESC
")->fetchAll();

// Get current Ethiopian month and year
$et_info = getEtInfo(date('Y-m-d'));
//$current_ethio_month = get_current_ethiopian_month();
$current_ethio_year = date('Y') - 8; // Ethiopian year offset

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate all required fields
    $required_fields = ['budget_owner_id', 'employee_id', 'city_id', 'total_days', 'departure_date', 'arrival_date', 'et_month'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $_SESSION['message'] = 'Missing required fields: ' . implode(', ', $missing_fields);
        $_SESSION['message_type'] = 'error';
        header('Location: perdium.php');
        exit;
    }
    
    $budget_owner_id = (int)$_POST['budget_owner_id'];
    $employee_id = (int)$_POST['employee_id'];
    $city_id = (int)$_POST['city_id'];
    $total_days = (int)$_POST['total_days'];
    $departure_date = $_POST['departure_date'];
    $arrival_date = $_POST['arrival_date'];
    $et_month = $_POST['et_month'];
    
    // Get p_koox from budget_owners
    $stmt = $pdo->prepare("SELECT p_koox FROM budget_owners WHERE id = ?");
    $stmt->execute([$budget_owner_id]);
    $budget_owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $p_koox = $budget_owner['p_koox'] ?? '';
    
    // Get employee salary
    $stmt = $pdo->prepare("SELECT salary, directorate FROM emp_list WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        $_SESSION['message'] = 'Selected employee not found';
        $_SESSION['message_type'] = 'error';
        header('Location: perdium.php');
        exit;
    }
    
    $employee_salary = $employee['salary'];
    $employee_directorate = $employee['directorate'];
    
    // Get city rates
    $stmt = $pdo->prepare("SELECT rate_low, rate_medium, rate_high FROM cities WHERE id = ?");
    $stmt->execute([$city_id]);
    $city_rates = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$city_rates) {
        $_SESSION['message'] = 'Selected city not found';
        $_SESSION['message_type'] = 'error';
        header('Location: perdium.php');
        exit;
    }
    
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
    $C = ($perdium_rate * 0.1) + ($perdium_rate * 0.25);
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
        
        // 2) Insert perdium transaction - Now includes p_koox
        $stmt = $pdo->prepare("
            INSERT INTO perdium_transactions (
                employee_id,
                budget_owner_id,
                p_koox,
                budget_code_id,
                city_id,
                perdium_rate,
                total_days,
                departure_date,
                arrival_date,
                total_amount,
                et_month
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $employee_id,
            $budget_owner_id,
            $p_koox,
            $budget_code_id,
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
        header('Location: perdium.php');
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
    header('Location: perdium.php');
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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../assets/css/materialize.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css"> <!-- Add this line -->
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap');
        
        body { font-family: 'Inter', sans-serif; }
        .ethio-font { font-family: 'Noto Sans Ethiopic', sans-serif; }
        
        .fade-out {
            opacity: 1;
            transition: opacity 0.5s ease-out;
        }
        .fade-out.hide { opacity: 0; }
        
        
        
        /* Select2 customization */
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
            padding-left: 12px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #4f46e5;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border-left: 4px solid #3b82f6;
        }
        .program-card {
            background: linear-gradient(135deg, #f0f4ff 0%, #e0e7ff 100%);
            border-left: 4px solid #6366f1;
        }
        .form-control:disabled, .form-control[readonly] {
            background-color: #f9fafb;
        }
        
        .employee-option {
            display: flex;
            justify-content: space-between;
        }
        .employee-name {
            font-weight: 500;
        }
        .employee-details {
            color: #6b7280;
            font-size: 0.875rem;
        }
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
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Perdium Management
                    </h1>
                    <p class="text-slate-600 mt-2">Manage perdium transactions and allowances</p>
                    <div class="mt-3 bg-indigo-100 rounded-lg p-3 max-w-md info-card">
                        <i class="fas fa-user-circle text-indigo-600 mr-2"></i>
                        <span class="text-indigo-800 font-semibold">
                            Welcome, <?php echo htmlspecialchars($user_name); ?>!
                            (<?php echo ucfirst($_SESSION['role']); ?>)
                        </span>
                    </div>
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
                    echo $message_type == 'error' ? 'bg-red-100 text-red-700' :
                    ($message_type == 'success' ? 'bg-green-100 text-green-700' :
                    'bg-blue-100 text-blue-700');
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
                        <!-- Budget Owner -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
                            <select name="budget_owner_id" id="budget_owner_id" required class="w-full select2">
                                <option value="">Select Owner</option>
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?php echo $o['id']; ?>" data-p_koox="<?php echo htmlspecialchars($o['p_koox']); ?>">
                                        <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Employee Selection -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Employee *</label>
                            <select name="employee_id" id="employee_id" required class="w-full select2">
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" 
                                            data-salary="<?php echo $e['salary']; ?>" 
                                            data-directorate="<?php echo htmlspecialchars($e['directorate']); ?>"
                                            data-name-am="<?php echo htmlspecialchars($e['name_am']); ?>"
                                            data-name-en="<?php echo htmlspecialchars($e['name']); ?>">
                                        <?php echo htmlspecialchars($e['name_am'] . ' - ' . $e['name'] . ' - ' . $e['directorate'] . ' (' . $e['salary'] . ' Birr)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Employee Details -->
                        <div class="info-card p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-blue-800 mb-2">Employee Details</h3>
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div>
                                    <p id="employee_name_display" class="text-sm font-medium text-gray-900">-</p>
                                    <p id="employee_salary_display" class="text-xs text-gray-600">Salary: -</p>
                                    <p id="employee_directorate_display" class="text-xs text-gray-600">Directorate: -</p>
                                </div>
                            </div>
                        </div>

                        <!-- Program Details -->
                        <div class="program-card p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-indigo-800 mb-2">Program Details and Koox:</h3>
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

                        <!-- Destination City -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Destination City *</label>
                            <select name="city_id" id="city_id" required class="w-full select2">
                                <option value="">Select City</option>
                                <?php foreach ($cities as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" 
                                            data-rate-low="<?php echo $c['rate_low']; ?>" 
                                            data-rate-medium="<?php echo $c['rate_medium']; ?>" 
                                            data-rate-high="<?php echo $c['rate_high']; ?>">
                                        <?php echo htmlspecialchars($c['name_amharic'] . ' (' . $c['name_english'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Perdium Rate -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Perdium Rate</label>
                            <div class="flex items-center bg-green-50 p-3 rounded-lg">
                                <i class="fas fa-money-bill-wave text-green-600 mr-2"></i>
                                <input type="text" id="perdium_rate" readonly class="w-full bg-transparent border-none focus:ring-0 text-green-800 font-bold">
                            </div>
                        </div>

                        <!-- Ethiopian Month -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
                            <select name="et_month" id="et_month" required class="w-full select2">
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

                        <!-- Total Perdium Days -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Total Perdium Days *</label>
                            <input type="number" name="total_days" id="total_days" min="1" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   onchange="calculateDates()" oninput="calculatePerdium()">
                        </div>

                        <!-- Departure Date -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Departure Date *</label>
                            <input type="date" name="departure_date" id="departure_date" required
                                   <?php if ($is_officer): ?>readonly<?php endif; ?>
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   onchange="calculateArrivalDate()">
                        </div>

                        <!-- Arrival Date -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Arrival Date *</label>
                            <input type="date" name="arrival_date" id="arrival_date" required
                                   <?php if ($is_officer): ?>readonly<?php endif; ?>
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   onchange="calculateTotalDays()">
                        </div>
                    </div>

                    <!-- Perdium Calculation Card -->
                    <div class="mt-6 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-lg border border-blue-200">
                        <h3 class="text-lg font-semibold text-blue-800 mb-4 flex items-center">
                            <i class="fas fa-calculator mr-2"></i> Perdium Calculation Details
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <p class="text-blue-700 font-medium">A (Departure): <span id="calc_A" class="text-blue-900">0.00</span></p>
                                <p class="text-xs text-blue-600 mt-1">(PR × 10%) + (PR × 25%) + (PR × 25%)</p>
                            </div>
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <p class="text-blue-700 font-medium">B (Field Days): <span id="calc_B" class="text-blue-900">0.00</span></p>
                                <p class="text-xs text-blue-600 mt-1">A × (TPD - 2)</p>
                            </div>
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <p class="text-blue-700 font-medium">C (Arrival): <span id="calc_C" class="text-blue-900">0.00</span></p>
                                <p class="text-xs text-blue-600 mt-1">(PR × 10%) + (PR × 25%)<br>No Diraar kee Kaqada</p>
                            </div>
                            <div class="bg-white p-3 rounded-lg shadow-sm">
                                <p class="text-blue-700 font-medium">D (Lodging): <span id="calc_D" class="text-blue-900">0.00</span></p>
                                <p class="text-xs text-blue-600 mt-1">PR × (TPD - 1) × 40% <br> No Lodging Allowance on the Arrival Date</p>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <p class="text-xl font-bold text-blue-800 flex items-center">
                                <i class="fas fa-money-bill-wave mr-2"></i>
                                Total Perdium Payment: <span id="total_perdium" class="ml-2">0.00</span> Birr
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 pt-4">
                        <button type="reset" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            <i class="fas fa-redo mr-2"></i> Reset
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition shadow-md">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Perdium Request
                        </button>
                    </div>
                </form>
            </div>

            <!-- Live Search Box -->
            <div class="mb-4">
                <input type="text" id="searchInput"
                       placeholder="Search Perdium transactions..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       onkeyup="filterTransactions()">
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
                                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($p['p_koox']); ?></td>
                                        <td class="px-4 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($p['employee_name'] . ' (' . $p['employee_name_english'] . ')'); ?>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 ethio-font"><?php echo htmlspecialchars($p['city_name']); ?></td>
                                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo $p['total_days']; ?></td>
                                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo number_format($p['total_amount'], 2); ?></td>
                                        <td class="px-4 py-4 text-sm">
                                            <div class="flex space-x-2">
                                                <a href="reports/preport.php?id=<?php echo $p['id']; ?>" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                                                    <i class="fas fa-print mr-1"></i> Print
                                                </a>
                                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                                    <a href="?action=delete&id=<?php echo $p['id']; ?>" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this transaction?')">
                                                        <i class="fas fa-trash mr-1"></i> Delete
                                                    </a>
                                                <?php endif; ?>
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
    <!-- Select2 JS -->
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
            
            // Initialize Select2 with better search functionality
            $('.select2').select2({
                theme: 'classic',
                width: '100%',
                // Enable searching in all text content
                matcher: function(params, data) {
                    // If there are no search terms, return all of the data
                    if ($.trim(params.term) === '') {
                        return data;
                    }

                    // Do not display the item if there is no 'text' property
                    if (typeof data.text === 'undefined') {
                        return null;
                    }

                    // Check if the option's text contains the term
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                        return data;
                    }

                    // Check if any data attributes contain the term
                    for (const key in data.element.dataset) {
                        if (String(data.element.dataset[key]).toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                            return data;
                        }
                    }

                    // Return null if the term should not be displayed
                    return null;
                }
            });
            
            // Set default dates
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('departure_date').valueAsDate = tomorrow;
            
            // Set current Ethiopian month
            document.getElementById('et_month').value = '<?php echo $current_ethio_month; ?>';
            
            // Load remaining budget
            loadPerdiumRemaining();
            
            // Employee selection handler
            $('#employee_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const employeeId = selectedOption.val();
                const employeeNameAm = selectedOption.data('name-am');
                const employeeNameEn = selectedOption.data('name-en');
                const salary = selectedOption.data('salary');
                const directorate = selectedOption.data('directorate');
                
                if (employeeId) {
                    // Update employee details display
                    $('#employee_name_display').text(employeeNameAm + ' (' + employeeNameEn + ')');
                    $('#employee_salary_display').text('Salary: ' + salary + ' Birr');
                    $('#employee_directorate_display').text('Directorate: ' + directorate);
                    
                    // Recalculate perdium
                    calculatePerdium();
                } else {
                    // Clear employee details
                    $('#employee_name_display').text('-');
                    $('#employee_salary_display').text('Salary: -');
                    $('#employee_directorate_display').text('Directorate: -');
                }
            });
            
            // Budget Owner selection handler
            $('#budget_owner_id').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const p_koox = selectedOption.data('p_koox');
                const ownerName = selectedOption.text().split(' - ')[1] || selectedOption.text();
                
                if (selectedOption.val()) {
                    // Update program details display
                    $('#program_p_koox_display').text(p_koox || '-');
                    $('#program_name_display').text('Project Koox: ' + ownerName);
                } else {
                    // Clear program details
                    $('#program_p_koox_display').text('-');
                    $('#program_name_display').text('Project Koox: -');
                }
                
                // Also update the remaining budget
                loadPerdiumRemaining();
            });
            
            // City selection handler
            $('#city_id').on('change', function() {
                calculatePerdium();
            });
            
            // Ethiopian month selection handler
            $('#et_month').on('change', function() {
                loadPerdiumRemaining();
            });
        });
        
        // Calculate perdium amount
        function calculatePerdium() {
            const employeeSelect = document.getElementById('employee_id');
            const selectedEmployee = employeeSelect.options[employeeSelect.selectedIndex];
            const citySelect = document.getElementById('city_id');
            const selectedCity = citySelect.options[citySelect.selectedIndex];
            const totalDays = parseInt($('#total_days').val()) || 0;
            
            if (!selectedEmployee || !selectedEmployee.value || !selectedCity || !selectedCity.value || totalDays < 1) {
                // Reset values if any required field is missing
                $('#perdium_rate').val('');
                $('#calc_A').text('0.00');
                $('#calc_B').text('0.00');
                $('#calc_C').text('0.00');
                $('#calc_D').text('0.00');
                $('#total_perdium').text('0.00');
                return;
            }
            
            const salary = parseFloat(selectedEmployee.getAttribute('data-salary'));
            const rateLow = parseFloat(selectedCity.getAttribute('data-rate-low'));
            const rateMedium = parseFloat(selectedCity.getAttribute('data-rate-medium'));
            const rateHigh = parseFloat(selectedCity.getAttribute('data-rate-high'));
            
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
            const C = (perdiumRate * 0.1) + (perdiumRate * 0.25);
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
            const departureDateInput = $('#departure_date');
            const departureDate = new Date(departureDateInput.val());
            
            if (totalDays < 1 || isNaN(departureDate.getTime())) return;
            
            const arrivalDate = new Date(departureDate);
            arrivalDate.setDate(departureDate.getDate() + totalDays - 1);
            
            $('#arrival_date').val(arrivalDate.toISOString().split('T')[0]);
            calculatePerdium();
        }
        
        // Calculate total days based on dates
        function calculateTotalDays() {
            const departureDateInput = $('#departure_date');
            const arrivalDateInput = $('#arrival_date');
            
            const departureDate = new Date(departureDateInput.val());
            const arrivalDate = new Date(arrivalDateInput.val());
            
            if (isNaN(departureDate.getTime()) || isNaN(arrivalDate.getTime())) return;
            
            // Calculate difference in days
            const timeDiff = arrivalDate.getTime() - departureDate.getTime();
            const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1; // +1 to include both start and end days
            
            if (dayDiff > 0) {
                $('#total_days').val(dayDiff);
                calculatePerdium();
            }
        }
        
        // Calculate arrival date when departure date changes
        function calculateArrivalDate() {
            calculateDates();
        }
        
        // Load remaining perdium budget
        function loadPerdiumRemaining() {
            const ownerId = $('#budget_owner_id').val();
            const etMonth = $('#et_month').val();
            const codeId = 6; // Ayroh Assentah code
            const year = <?php echo $current_ethio_year; ?>;
            
            if (!ownerId || !etMonth) {
                $('#rem_monthly').text('0.00');
                $('#rem_quarterly').text('0.00');
                $('#rem_yearly').text('0.00');
                return;
            }
            
            $.get('get_remaining_perdium.php', {
                owner_id: ownerId,
                code_id: codeId,
                month: etMonth,
                year: year
            }, function(data) {
                try {
                    const rem = JSON.parse(data);
                    $('#rem_monthly').text(parseFloat(rem.remaining_monthly || 0).toFixed(2));
                    $('#rem_quarterly').text(parseFloat(rem.remaining_quarterly || 0).toFixed(2));
                    $('#rem_yearly').text(parseFloat(rem.remaining_yearly || 0).toFixed(2));
                } catch (e) {
                    console.error('Error parsing response:', e);
                    $('#rem_monthly').text('0.00');
                    $('#rem_quarterly').text('0.00');
                    $('#rem_yearly').text('0.00');
                }
            }).fail(function() {
                console.error('Failed to load remaining budget');
                $('#rem_monthly').text('0.00');
                $('#rem_quarterly').text('0.00');
                $('#rem_yearly').text('0.00');
            });
        }
    </script>
    
    <!-- Searching scripts -->
    <script>
        function filterTransactions() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('transactionsTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j] && cells[j].textContent.toLowerCase().includes(filter)) {
                        match = true;
                        break;
                    }
                }
                
                rows[i].style.display = match ? '' : 'none';
            }
        }
    </script>
</body>
</html>