<?php
session_start();
include 'includes/db.php';
include 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

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
$vehicles = $pdo->query("SELECT * FROM vehicles")->fetchAll();

// Add p_koox to fuel_transactions table if it doesn't exist
try {
    $pdo->query("ALTER TABLE fuel_transactions ADD COLUMN p_koox VARCHAR(255) AFTER owner_id");
} catch (PDOException $e) {
    // Column likely already exists
}

// Fetch fuel transactions with p_koox
$fuel_trans = $pdo->query("
    SELECT f.*, o.code AS owner_code, o.p_koox
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
    $owner_id = $_POST['owner_id'];
    $driver_name = $_POST['driver_name'];
    $plate_number = $_POST['plate_number'];
    $et_month = $_POST['et_month'];
    $current_gauge = (float)($_POST['current_gauge'] ?? 0);
    $journey_distance = (float)($_POST['journey_distance'] ?? 0);
    $fuel_price = (float)($_POST['fuel_price'] ?? 0);
    $refuelable_amt = (float)($_POST['refuelable_amount']?? 0);
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $new_gauge = (float)($_POST['new_gauge'] ?? 0);
    $gauge_gap = (float)($_POST['gauge_gap'] ?? 0);
    
    // Get p_koox from budget_owners
    $stmt = $pdo->prepare("SELECT p_koox FROM budget_owners WHERE id = ?");
    $stmt->execute([$owner_id]);
    $budget_owner = $stmt->fetch(PDO::FETCH_ASSOC);
    $p_koox = $budget_owner['p_koox'] ?? '';

    // Validate gauge progression
    $stmt = $pdo->prepare("
        SELECT new_gauge
        FROM fuel_transactions
        WHERE plate_number = ?
        ORDER BY date DESC
        LIMIT 1
    ");
    $stmt->execute([$plate_number]);
    $last_new_gauge = (float)($stmt->fetchColumn() ?: 0);

    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $_SESSION['message'] = 'Gauge error: Current gauge less than expected new gauge.';
        $_SESSION['message_type'] = 'error';
    } else {
        $pdo->beginTransaction();

        try {
            // 1) Determine Ethiopian year and quarter from selected month
            $year = date('Y') - 8;
            $quarterMap = [
                'ሐምሌ'=>1,'ነሐሴ'=>1,'መስከረም'=>1,
                'ጥቅምት'=>2,'ህዳር'=>2,'ታኅሳስ'=>2,
                'ጥር'=>3,'የካቲት'=>3,'መጋቢት'=>3,
                'ሚያዝያ'=>4,'ግንቦት'=>4,'ሰኔ'=>4
            ];
            $quarter = $quarterMap[$et_month] ?? 0;

            // 2) Deduct from monthly budget if exists, else from yearly
            $fuel_code_id = 5; // Sansii kee Sukutih

            // Fetch monthly budget row
            $stmt = $pdo->prepare("
                SELECT *
                FROM budgets
                WHERE owner_id = ?
                AND code_id = ?
                AND year = ?
                AND month = ?
            ");
            $stmt->execute([$owner_id, $fuel_code_id, $year, $et_month]);
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
                $stmt->execute([$new_rem_month, $budget['id']]);
            } else {
                // Fallback to yearly
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM budgets
                    WHERE owner_id = ?
                    AND code_id = ?
                    AND year = ?
                    AND monthly_amount = 0
                ");
                $stmt->execute([$owner_id, $fuel_code_id, $year]);
                $budget_yearly = $stmt->fetch();

                if (!$budget_yearly) {
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
                $stmt->execute([$new_rem_year, $budget_yearly['id']]);
            }

            // 3) Insert or update fuel transaction
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("
                    UPDATE fuel_transactions
                    SET owner_id = ?,
                    p_koox = ?,
                    driver_name = ?,
                    plate_number = ?,
                    et_month = ?,
                    previous_gauge = ?,
                    current_gauge = ?,
                    journey_distance = ?,
                    fuel_price = ?,
                    refuelable_amount = ?,
                    total_amount = ?,
                    new_gauge = ?,
                    gauge_gap = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
                    $last_new_gauge, $current_gauge, $journey_distance,
                    $fuel_price, $refuelable_amt, $total_amount,
                    $new_gauge, $gauge_gap, $_POST['id']
                ]);

                $_SESSION['message'] = 'Fuel transaction updated';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fuel_transactions (
                        owner_id,
                        p_koox,
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
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
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
    $stmt->execute([$_GET['id']]);
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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="../assets/css/materialize.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        
        .sidebar {
            width: 280px;
            transition: all 0.3s ease;
        }
        .sidebar.collapsed { margin-left: -280px; }
        
        .main-content {
            width: calc(100% - 280px);
            transition: all 0.3s ease;
        }
        .main-content.expanded { width: 100%; }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                z-index: 1000;
                height: 100vh;
                overflow-y: auto;
            }
            .main-content { width: 100%; }
        }
        
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
        .vehicle-card {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-left: 4px solid #22c55e;
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
    <div class="sidebar bg-gradient-to-b from-blue-800 to-blue-600 text-white" id="sidebar">
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
                    <a href="perdium.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
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
                        Fuel Management
                    </h1>
                    <p class="text-slate-600 mt-2">Manage fuel transactions and consumption</p>
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
                        <!-- Budget Owner -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
                            <select name="owner_id" id="owner_id" required class="w-full select2" onchange="loadFuelRemaining()">
                                <option value="">Select Owner</option>
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?php echo $o['id']; ?>" 
                                            data-p_koox="<?php echo htmlspecialchars($o['p_koox']); ?>"
                                            <?php echo (isset($fuel) && $fuel['owner_id'] == $o['id']) || (isset($_POST['owner_id']) && $_POST['owner_id'] == $o['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Program Details -->
                        <div class="program-card p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-indigo-800 mb-2">Program Details</h3>
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

                        <!-- Driver Name -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Driver Name *</label>
                            <input type="text" name="driver_name" value="<?php echo isset($fuel) ? htmlspecialchars($fuel['driver_name']) : (isset($_POST['driver_name']) ? htmlspecialchars($_POST['driver_name']) : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Plate Number -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number *</label>
                            <select name="plate_number" id="plate_number" required class="w-full select2" onchange="fetchVehicleDetails(this.value)">
                                <option value="">Select Vehicle</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option value="<?php echo $v['plate_no']; ?>" 
                                            data-model="<?php echo htmlspecialchars($v['model']); ?>"
                                            <?php echo (isset($fuel) && $fuel['plate_number'] == $v['plate_no']) || (isset($_POST['plate_number']) && $_POST['plate_number'] == $v['plate_no']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['model']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Vehicle Details -->
                        <div class="vehicle-card p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-green-800 mb-2">Vehicle Details</h3>
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-car text-green-600"></i>
                                </div>
                                <div>
                                    <p id="vehicle_model_display" class="text-sm font-medium text-gray-900">-</p>
                                    <p id="vehicle_consumption_display" class="text-xs text-gray-600">Fuel Consumption: 5km/liter</p>
                                    <p id="previous_gauge_display" class="text-xs text-gray-600">Previous Gauge: -</p>
                                </div>
                            </div>
                        </div>

                        <!-- Ethiopian Month -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
                            <select name="et_month" id="et_month" required class="w-full select2" onchange="loadFuelRemaining()">
                                <option value="">Select Month</option>
                                <option value="መስከረም" <?php echo (isset($fuel) && $fuel['et_month'] == 'መስከረም') || (isset($_POST['et_month']) && $_POST['et_month'] == 'መስከረም') ? 'selected' : ''; ?>>መስከረም</option>
                                <option value="ጥቅምት" <?php echo (isset($fuel) && $fuel['et_month'] == 'ጥቅምት') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ጥቅምት') ? 'selected' : ''; ?>>ጥቅምት</option>
                                <option value="ህዳር" <?php echo (isset($fuel) && $fuel['et_month'] == 'ህዳር') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ህዳር') ? 'selected' : ''; ?>>ህዳር</option>
                                <option value="ታኅሳስ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ታኅሳስ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ታኅሳስ') ? 'selected' : ''; ?>>ታኅሳስ</option>
                                <option value="ጥር" <?php echo (isset($fuel) && $fuel['et_month'] == 'ጥር') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ጥር') ? 'selected' : ''; ?>>ጥር</option>
                                <option value="የካቲት" <?php echo (isset($fuel) && $fuel['et_month'] == 'የካቲት') || (isset($_POST['et_month']) && $_POST['et_month'] == 'የካቲት') ? 'selected' : ''; ?>>የካቲት</option>
                                <option value="መጋቢት" <?php echo (isset($fuel) && $fuel['et_month'] == 'መጋቢት') || (isset($_POST['et_month']) && $_POST['et_month'] == 'መጋቢት') ? 'selected' : ''; ?>>መጋቢት</option>
                                <option value="ሚያዝያ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ሚያዝያ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ሚያዝያ') ? 'selected' : ''; ?>>ሚያዝያ</option>
                                <option value="ግንቦት" <?php echo (isset($fuel) && $fuel['et_month'] == 'ግንቦት') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ግንቦት') ? 'selected' : ''; ?>>ግንቦት</option>
                                <option value="ሰኔ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ሰኔ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ሰኔ') ? 'selected' : ''; ?>>ሰኔ</option>
                                <option value="ሐምሌ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ሐምሌ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ሐምሌ') ? 'selected' : ''; ?>>ሐምሌ</option>
                                <option value="ነሐሴ" <?php echo (isset($fuel) && $fuel['et_month'] == 'ነሐሴ') || (isset($_POST['et_month']) && $_POST['et_month'] == 'ነሐሴ') ? 'selected' : ''; ?>>ነሐሴ</option>
                            </select>
                        </div>

                        <!-- Current Gauge -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Current Gauge *</label>
                            <input type="number" step="0.01" name="current_gauge" id="current" value="<?php echo isset($fuel) ? $fuel['current_gauge'] : (isset($_POST['current_gauge']) ? $_POST['current_gauge'] : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
                        </div>

                        <!-- Journey Distance (km) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (km) *</label>
                            <input type="number" step="0.01" name="journey_distance" id="journey" value="<?php echo isset($fuel) ? $fuel['journey_distance'] : (isset($_POST['journey_distance']) ? $_POST['journey_distance'] : ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
                        </div>

                        <!-- Fuel Price (per liter) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price (per liter) *</label>
                            <input type="number" step="0.01" name="fuel_price" id="price" value="<?php echo isset($fuel) ? $fuel['fuel_price'] : (isset($_POST['fuel_price']) ? $_POST['fuel_price'] : $last_price); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
                        </div>

                        <!-- Refuelable Amount (liters) -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable Amount (liters)</label>
                            <input type="number" step="0.01" name="refuelable_amount" id="refuelable" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                        </div>

                        <!-- Total Amount -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Total Amount</label>
                            <input type="number" step="0.01" name="total_amount" id="total" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                        </div>

                        <!-- New Gauge -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">New Gauge</label>
                            <input type="number" step="0.01" name="new_gauge" id="new_gauge" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                        </div>

                        <!-- Gauge Gap -->
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
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php echo isset($fuel) ? 'Update Transaction' : 'Add Transaction'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Live Search Box -->
            <div class="mb-4">
                <input type="text" id="searchInput"
                       placeholder="Search transactions..."
                       class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                       onkeyup="filterTransactions()">
            </div>

            <!-- Budget Status -->
            <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Status (Sansii kee Sukutih)</h2>
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

            <!-- Fuel Transactions Table -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Fuel Transactions</h2>
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
                                        <td class="px-4 py-4 text-sm text-gray-900 ethio-font"><?php echo htmlspecialchars($f['et_month']); ?></td>
                                        <td class="px-4 py-4 text-sm text-gray-900"><?php echo number_format($f['total_amount'], 2); ?></td>
                                        <td class="px-4 py-4 text-sm">
                                            <div class="flex space-x-2">
                                                <a href="reports/fuel_transaction_report.php?id=<?php echo $f['id']; ?>" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                                                    <i class="fas fa-print mr-1"></i> Print
                                                </a>
                                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                                    <a href="?action=edit&id=<?php echo $f['id']; ?>" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200">
                                                        <i class="fas fa-edit mr-1"></i> Edit
                                                    </a>
                                                    <a href="?action=delete&id=<?php echo $f['id']; ?>" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this transaction?')">
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
            
            // Load remaining budget
            loadFuelRemaining();
            
            // Budget Owner selection handler
            $('#owner_id').on('change', function() {
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
                loadFuelRemaining();
            });
            
            // Ethiopian month selection handler
            $('#et_month').on('change', function() {
                loadFuelRemaining();
            });
            
            // Trigger initial calculations
            calculateFuel();
        });
        
        // Fetch vehicle details when plate number changes
        function fetchVehicleDetails(plateNumber) {
            if (!plateNumber) {
                // Clear vehicle details
                $('#vehicle_model_display').text('-');
                $('#previous_gauge_display').text('Previous Gauge: -');
                return;
            }
            
            // Get model from selected option
            const selectedOption = $('#plate_number').find('option:selected');
            const model = selectedOption.data('model') || '-';
            
            // Update vehicle details display
            $('#vehicle_model_display').text(model);
            
            // Fetch last gauge for the selected vehicle
            $.get('get_last_gauge.php', { plate_number: plateNumber }, function(data) {
                try {
                    const resp = JSON.parse(data);
                    $('#previous_gauge_display').text('Previous Gauge: ' + (resp.last_gauge || '0'));
                    
                    // Set current gauge to last gauge if it's empty
                    if (!$('#current').val()) {
                        $('#current').val(resp.last_gauge || 0);
                        calculateFuel();
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    $('#previous_gauge_display').text('Previous Gauge: -');
                }
            }).fail(function() {
                console.error('Failed to fetch vehicle details');
                $('#previous_gauge_display').text('Previous Gauge: -');
            });
        }
        
        // Calculate liters & cost & gauges
        function calculateFuel() {
            const current = parseFloat($('#current').val()) || 0;
            const journey = parseFloat($('#journey').val()) || 0;
            const price = parseFloat($('#price').val()) || 0;

            // Business logic: 5 km per liter
            const refuel = journey / 5;
            const total = refuel * price;
            const newG = current + journey;
            const gap = newG - current;

            $('#refuelable').val(refuel.toFixed(2));
            $('#total').val(total.toFixed(2));
            $('#new_gauge').val(newG.toFixed(2));
            $('#gap').val(gap.toFixed(2));
        }
        
        // Load remaining fuel budget
        function loadFuelRemaining() {
            const ownerId = $('#owner_id').val();
            const etMonth = $('#et_month').val();
            const codeId = 5; // Sansii kee Sukutih code
            const year = new Date().getFullYear() - 8; // Ethiopian year
            
            if (!ownerId || !etMonth) {
                $('#rem_monthly').text('0.00');
                $('#rem_quarterly').text('0.00');
                $('#rem_yearly').text('0.00');
                return;
            }
            
            $.get('get_remaining_fuel.php', {
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