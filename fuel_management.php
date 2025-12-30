<?php
ini_set('display_errors', 1);

require_once 'includes/init.php';
//require_once 'includes/sidebar.php';

// Define fuel efficiency constant
define('FUEL_EFFICIENCY', 5); // 1 liter = 5 km

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_check($t) { return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// Roles
$is_admin   = (($_SESSION['role'] ?? '') === 'admin');
$is_officer = (($_SESSION['role'] ?? '') === 'officer');

// Role
$is_officer = ($_SESSION['role'] == 'officer');

// Get user data including profile picture
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, profile_picture, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? $_SESSION['username'];
$profile_picture = $user_data['profile_picture'] ?? '';
$user_email = $user_data['email'] ?? '';

// Get user's assigned budget types to determine default behavior
$user_assigned_budgets = getUserAssignedBudgets($pdo, $user_id);
$user_budget_types = [];
foreach ($user_assigned_budgets as $budget) {
    if (!in_array($budget['budget_type'], $user_budget_types)) {
        $user_budget_types[] = $budget['budget_type'];
    }
}

// Determine default budget type for this user
$default_budget_type = 'governmental'; // Default fallback
$budget_type_locked = false;

if ($is_admin) {
    // Admin can access both types, no locking
    $default_budget_type = 'governmental';
    $budget_type_locked = false;
} else {
    // Officer: determine based on assignments
    if (count($user_budget_types) === 1) {
        // User has only one budget type assigned
        $default_budget_type = $user_budget_types[0];
        $budget_type_locked = true;
    } elseif (count($user_budget_types) > 1) {
        // User has multiple budget types, default to first one but allow switching
        $default_budget_type = $user_budget_types[0];
        $budget_type_locked = false;
    } else {
        // User has no assignments, show governmental but locked
        $default_budget_type = 'governmental';
        $budget_type_locked = true;
    }
}

// Ensure columns exist (safe)
try { $pdo->query("ALTER TABLE fuel_transactions ADD COLUMN p_koox VARCHAR(255) AFTER owner_id"); } catch (PDOException $e) {}
try { $pdo->query("ALTER TABLE fuel_transactions ADD COLUMN budget_type ENUM('governmental','program') NOT NULL DEFAULT 'governmental' AFTER id"); } catch (PDOException $e) {}

// Get owners from both tables for the FILTER dropdown below the form - Using filtered budget owners based on user role
$gov_owners = getFilteredBudgetOwners($pdo, $user_id, $_SESSION['role'], 'governmental');
$prog_owners = getFilteredBudgetOwners($pdo, $user_id, $_SESSION['role'], 'program');

// Get vehicles
$vehicles = $pdo->query("SELECT * FROM vehicles ORDER BY plate_no")->fetchAll(PDO::FETCH_ASSOC);

// Last price
$last_price = $pdo->query("SELECT fuel_price FROM fuel_transactions ORDER BY date DESC LIMIT 1")->fetchColumn() ?: 0;

// Editing?
$fuel = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    if (!$is_admin) { http_response_code(403); exit('Forbidden'); }
    $stmt = $pdo->prepare("SELECT * FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $fuel = $stmt->fetch(PDO::FETCH_ASSOC);
}

function ecYear(): int { return (int)date('Y') - 8; }

// Handle POST (Add/Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($_SESSION['role'] ?? '', ['admin','officer'], true)) { http_response_code(403); exit('Forbidden'); }
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); exit('Bad CSRF'); }

    $action = $_POST['action'] ?? '';
    $is_update = ($action === 'update');
    
    if ($is_update && !$is_admin) { http_response_code(403); exit('Forbidden'); }

    $owner_id = $_POST['owner_id'] ?? '';
    $budget_type = ($_POST['budget_type'] ?? 'governmental') === 'program' ? 'program' : 'governmental';
    $driver_name = $_POST['driver_name'] ?? '';
    $plate_number = $_POST['plate_number'] ?? '';
    $et_month = $budget_type === 'program' ? '' : ($_POST['et_month'] ?? '');
    $current_gauge = (float)($_POST['current_gauge'] ?? 0);
    $journey_distance = (float)($_POST['journey_distance'] ?? 0);
    $fuel_price = (float)($_POST['fuel_price'] ?? 0);
    $total_amount_input = (float)($_POST['total_amount_input'] ?? 0);
    $calculation_method = $_POST['calculation_method'] ?? 'A'; // A = Journey Distance, B = Total Amount
    
    // Get previous gauge from last transaction
    $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number = ? ORDER BY date DESC, id DESC LIMIT 1");
    $stmt->execute([$plate_number]);
    $previous_gauge = (float)($stmt->fetchColumn() ?: 0);
    
    // Calculate values based on method
    if ($calculation_method === 'A') {
        // Method A: Journey Distance → Refuelable Amount → Total Amount
        $refuelable_amt = $journey_distance / FUEL_EFFICIENCY;
        $total_amount = $refuelable_amt * $fuel_price;
    } else {
        // Method B: Total Amount → Refuelable Amount → Journey Distance
        $refuelable_amt = $total_amount_input / $fuel_price;
        $journey_distance = $refuelable_amt * FUEL_EFFICIENCY;
        $total_amount = $total_amount_input;
    }
    
    // Calculate gauge values
    $new_gauge = $current_gauge + $journey_distance;
    $gauge_gap = $current_gauge - $previous_gauge;
    
    // Odometer Integrity Check
    if ($gauge_gap < 0) {
        $_SESSION['message'] = 'Gauge Error: Current gauge cannot be less than previous gauge. Please check the odometer reading.';
        $_SESSION['message_type'] = 'error';
        
        // Store form state in session for error preservation
        $_SESSION['last_form_state'] = [
            'budget_type' => $budget_type,
            'owner_id' => $owner_id,
            'plate_number' => $plate_number,
            'driver_name' => $driver_name,
            'et_month' => $et_month
        ];
        
        header('Location: fuel_management.php');
        exit;
    }

    // Budget access validation
    if (!$is_admin && !hasBudgetAccess($pdo, $user_id, $budget_type, $owner_id)) { 
        $_SESSION['message'] = 'You do not have access to this budget owner';
        $_SESSION['message_type'] = 'error';
        
        // Store form state in session for error preservation
        $_SESSION['last_form_state'] = [
            'budget_type' => $budget_type,
            'owner_id' => $owner_id,
            'plate_number' => $plate_number,
            'driver_name' => $driver_name,
            'et_month' => $et_month
        ];
        
        header('Location: fuel_management.php');
        exit;
    }

    // p_koox applies only to governmental owners
    $p_koox = null;
    if ($budget_type === 'governmental') {
        $stmt = $pdo->prepare("SELECT p_koox FROM budget_owners WHERE id = ?");
        $stmt->execute([$owner_id]);
        $budget_owner = $stmt->fetch(PDO::FETCH_ASSOC);
        $p_koox = $budget_owner['p_koox'] ?? null;
    }

    $pdo->beginTransaction();
    try {
        $year = ecYear();
        $fuel_code_id = 5; // Sansii kee Sukutih

        if ($budget_type === 'program') {
            // Deduct from program yearly budgets (owner-level)
            $stmt = $pdo->prepare("
                SELECT id, remaining_yearly
                FROM budgets
                WHERE budget_type='program'
                  AND owner_id = ?
                  AND year = ?
                  AND monthly_amount = 0
                ORDER BY id ASC
                FOR UPDATE
            ");
            $stmt->execute([$owner_id, $year]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_remaining = 0;
            foreach ($rows as $r) $total_remaining += (float)$r['remaining_yearly'];
            if ($total_amount > $total_remaining) {
                throw new Exception('Insufficient program yearly budget. Available ' . number_format($total_remaining, 2) . ' ብር');
            }

            $left = $total_amount;
            foreach ($rows as $r) {
                if ($left <= 0) break;
                $avail = (float)$r['remaining_yearly'];
                $use = min($avail, $left);
                $newRem = $avail - $use;
                $upd = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                $upd->execute([$newRem, (int)$r['id']]);
                $left -= $use;
            }

            // Insert/Update tx
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("
                    UPDATE fuel_transactions
                    SET budget_type='program',
                        owner_id=?, p_koox=?, driver_name=?, plate_number=?, et_month=?,
                        previous_gauge=?, current_gauge=?, journey_distance=?, fuel_price=?,
                        refuelable_amount=?, total_amount=?, new_gauge=?, gauge_gap=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $owner_id, $p_koox, $driver_name, $plate_number, '',
                    $previous_gauge, $current_gauge, $journey_distance, $fuel_price,
                    $refuelable_amt, $total_amount, $new_gauge, $gauge_gap, $_POST['id']
                ]);
                $_SESSION['message'] = 'Program fuel transaction updated successfully';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fuel_transactions (
                        budget_type, owner_id, p_koox, driver_name, plate_number, et_month,
                        previous_gauge, current_gauge, journey_distance, fuel_price,
                        refuelable_amount, total_amount, new_gauge, gauge_gap
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    'program', $owner_id, $p_koox, $driver_name, $plate_number, '',
                    $previous_gauge, $current_gauge, $journey_distance, $fuel_price,
                    $refuelable_amt, $total_amount, $new_gauge, $gauge_gap
                ]);
                $_SESSION['message'] = 'Program fuel transaction added successfully';
            }
        } else {
            // Governmental (original logic: monthly first, fallback yearly)
            $stmt = $pdo->prepare("
                SELECT *
                FROM budgets
                WHERE owner_id = ?
                  AND code_id = ?
                  AND year = ?
                  AND month = ?
            ");
            $stmt->execute([$owner_id, $fuel_code_id, $year, $et_month]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($budget) {
                $new_rem_month = $budget['remaining_monthly'] - $total_amount;
                if ($new_rem_month < 0) throw new Exception('Insufficient remaining monthly budget for fuel.');
                $stmt = $pdo->prepare("UPDATE budgets SET remaining_monthly = ? WHERE id = ?");
                $stmt->execute([$new_rem_month, $budget['id']]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM budgets
                    WHERE owner_id = ?
                      AND code_id = ?
                      AND year = ?
                      AND monthly_amount = 0
                ");
                $stmt->execute([$owner_id, $fuel_code_id, $year]);
                $budget_yearly = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$budget_yearly) throw new Exception('No fuel budget allocated.');
                $new_rem_year = $budget_yearly['remaining_yearly'] - $total_amount;
                if ($new_rem_year < 0) throw new Exception('Insufficient remaining yearly budget for fuel.');
                $stmt = $pdo->prepare("UPDATE budgets SET remaining_yearly = ? WHERE id = ?");
                $stmt->execute([$new_rem_year, $budget_yearly['id']]);
            }

            // Insert/update tx
            if (isset($_POST['id']) && $_POST['action'] == 'update') {
                $stmt = $pdo->prepare("
                    UPDATE fuel_transactions
                    SET budget_type='governmental',
                        owner_id=?, p_koox=?, driver_name=?, plate_number=?, et_month=?,
                        previous_gauge=?, current_gauge=?, journey_distance=?, fuel_price=?,
                        refuelable_amount=?, total_amount=?, new_gauge=?, gauge_gap=?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
                    $previous_gauge, $current_gauge, $journey_distance,
                    $fuel_price, $refuelable_amt, $total_amount,
                    $new_gauge, $gauge_gap, $_POST['id']
                ]);
                $_SESSION['message'] = 'Fuel transaction updated successfully';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO fuel_transactions (
                        budget_type, owner_id, p_koox, driver_name, plate_number, et_month,
                        previous_gauge, current_gauge, journey_distance, fuel_price,
                        refuelable_amount, total_amount, new_gauge, gauge_gap
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    'governmental', $owner_id, $p_koox, $driver_name, $plate_number, $et_month,
                    $previous_gauge, $current_gauge, $journey_distance,
                    $fuel_price, $refuelable_amt, $total_amount,
                    $new_gauge, $gauge_gap
                ]);
                $_SESSION['message'] = 'Fuel transaction added successfully';
            }
        }

        $pdo->commit();
        $_SESSION['message_type'] = 'success';
        
        // Store form state in session for preservation
        $_SESSION['last_form_state'] = [
            'budget_type' => $budget_type,
            'owner_id' => $owner_id,
            'plate_number' => $plate_number,
            'driver_name' => $driver_name,
            'et_month' => $et_month
        ];
        
        header('Location: fuel_management.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        
        // Store form state in session for error preservation
        $_SESSION['last_form_state'] = [
            'budget_type' => $budget_type,
            'owner_id' => $owner_id,
            'plate_number' => $plate_number,
            'driver_name' => $driver_name,
            'et_month' => $et_month
        ];
    }
}

// Delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    if (!$is_admin) { http_response_code(403); exit('Forbidden'); }
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['message'] = 'Fuel transaction deleted successfully';
    $_SESSION['message_type'] = 'success';
    header('Location: fuel_management.php'); exit;
}

// Handle preserved form state
$preserved_state = $_SESSION['last_form_state'] ?? null;
if ($preserved_state && !isset($fuel)) {
    // Use preserved state for form values
    $preserved_budget_type = $preserved_state['budget_type'] ?? $default_budget_type;
    $preserved_owner_id = $preserved_state['owner_id'] ?? '';
    $preserved_plate_number = $preserved_state['plate_number'] ?? '';
    $preserved_driver_name = $preserved_state['driver_name'] ?? '';
    $preserved_et_month = $preserved_state['et_month'] ?? '';
    
    // Clear the preserved state so it's only used once
    unset($_SESSION['last_form_state']);
} else {
    $preserved_budget_type = $default_budget_type;
    $preserved_owner_id = '';
    $preserved_plate_number = '';
    $preserved_driver_name = '';
    $preserved_et_month = '';
    
    // Clear preserved state if we're not using it
    unset($_SESSION['last_form_state']);
}

// Clear flash
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php
    $pageTitle = 'Fuel Management';
    require_once 'includes/head.php';
  ?>
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    /* Modern gradient backgrounds and animations */
    .gradient-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .gradient-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .gradient-button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transition: all 0.3s ease;
    }

    .gradient-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    /* Modern table styles */
    .table-modern thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        
    }
        .table-modern th {
            color: white;
            font-weight: 600;
            padding: 1rem 1.25rem;
            border: none;
        }
    
     .table-modern td {
    padding: 1rem 1.25rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            }

    .table-modern tbody tr {
        transition: all 0.2s ease;
    }

    .table-modern tbody tr:hover {
        background-color: #f8fafc;
        transform: scale(1.01);
    }

    /* Card hover effects */
    .modern-card {
        transition: all 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
    }

    .modern-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    /* Input focus effects */
    .modern-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: translateY(-1px);
    }

    /* Custom scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
    }

    /* Pulse animation for important elements */
    @keyframes gentle-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .pulse-gentle {
        animation: gentle-pulse 2s infinite;
    }

    /* Glass morphism effect */
    .glass-effect {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* User Profile Styles */
    .user-profile {
        position: relative;
        cursor: pointer;
    }

    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        border: 3px solid rgba(255, 255, 255, 0.3);
        transition: all 0.3s ease;
    }

    .user-avatar:hover {
        border-color: rgba(255, 255, 255, 0.6);
        transform: scale(1.05);
    }

    .user-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 280px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }

    .user-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .user-dropdown-header {
        padding: 20px;
        border-bottom: 1px solid #f3f4f6;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px 16px 0 0;
    }

    .user-dropdown-item {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        color: #4b5563;
        text-decoration: none;
        transition: all 0.2s ease;
        border-bottom: 1px solid #f9fafb;
    }

    .user-dropdown-item:hover {
        background: #f8fafc;
        color: #1f2937;
    }

    .user-dropdown-item:last-child {
        border-bottom: none;
        border-radius: 0 0 16px 16px;
    }

    .user-dropdown-item.logout {
        color: #ef4444;
    }

    .user-dropdown-item.logout:hover {
        background: #fef2f2;
        color: #dc2626;
    }

    .user-dropdown-icon {
        width: 20px;
        margin-right: 12px;
        text-align: center;
    }

    /* Gauge gap warning styles */
    .gauge-warning {
        border-color: #ef4444 !important;
        background-color: #fef2f2 !important;
    }
    
    .text-red-600 {
        color: #dc2626;
    }

    /* Ethiopian font for months */
    .ethio-font {
        font-family: 'Nyala', 'Ebrima', 'Abyssinica SIL', 'GF Zemen', sans-serif;
        font-size: 1.1em;
    }

    .rotate-180 {
        transform: rotate(180deg);
    }

    
           /* Main Content Layout */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        }

        /* When sidebar is collapsed on desktop */
        .sidebar.collapsed ~ .main-content {
            margin-left: 80px;
        }

        /* Mobile full width */
        @media (max-width: 1023px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }

        /* Content Container */
        .content-container {
            padding: 2rem;
            width: 100%;
            box-sizing: border-box;
        }
  </style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
  <?php require_once 'includes/sidebar-component.php'; ?>

  <!-- Main Content -->
  <div class="main-content flex-1 min-h-screen" id="mainContent">
  	<?php require_once 'includes/header.php'; ?>
    <div class="p-6">     

      <!-- Modern Flash Messaging System -->
      <?php if ($message): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const message = <?php echo json_encode($message); ?>;
            const messageType = <?php echo json_encode($message_type); ?>;
            
            const toastConfigs = {
                success: {
                    icon: 'success',
                    title: 'Success!',
                    background: '#f0f9ff',
                    iconColor: '#10b981',
                    timer: 4000
                },
                error: {
                    icon: 'error',
                    title: 'Error!',
                    background: '#fef2f2',
                    iconColor: '#ef4444',
                    timer: 5000
                },
                warning: {
                    icon: 'warning',
                    title: 'Warning!',
                    background: '#fffbeb',
                    iconColor: '#f59e0b',
                    timer: 4500
                },
                info: {
                    icon: 'info',
                    title: 'Information',
                    background: '#eff6ff',
                    iconColor: '#3b82f6',
                    timer: 4000
                }
            };

            const config = toastConfigs[messageType] || toastConfigs.info;
            
            Swal.fire({
                icon: config.icon,
                title: config.title,
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: config.timer,
                timerProgressBar: true,
                background: config.background,
                iconColor: config.iconColor,
                customClass: {
                    popup: 'rounded-xl shadow-xl border border-gray-200'
                },
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        });
        </script>
      <?php endif; ?>

      <!-- Fuel Form -->
      <div class="bg-white rounded-2xl p-8 shadow-xl mb-8 border border-gray-100">
        <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
          <i class="fas fa-plus-circle mr-3 text-blue-500"></i>
          <?php echo isset($fuel) ? 'Edit Fuel Transaction' : 'Add New Fuel Transaction'; ?>
        </h2>
        <form id="fuelForm" method="POST" class="space-y-6" onsubmit="return validateBeforeSubmit();">
          <?php if (isset($fuel)): ?>
            <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

          <!-- Top row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Budget Source Type -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Budget Source Type *</label>
              <select name="budget_type" id="budget_type" class="w-full select2 modern-input" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
                <option value="governmental" <?php 
                  if (isset($fuel)) {
                    echo $fuel['budget_type']==='governmental' ? 'selected' : '';
                  } else {
                    echo ($preserved_budget_type === 'governmental' ? 'selected' : ($default_budget_type === 'governmental' ? 'selected' : ''));
                  }
                ?>>Government Budget</option>
                <option value="program" <?php 
                  if (isset($fuel)) {
                    echo $fuel['budget_type']==='program' ? 'selected' : '';
                  } else {
                    echo ($preserved_budget_type === 'program' ? 'selected' : ($default_budget_type === 'program' ? 'selected' : ''));
                  }
                ?>>Programs Budget</option>
              </select>
              <?php if ($budget_type_locked): ?>
                <input type="hidden" name="budget_type" value="<?php echo $default_budget_type; ?>">
                <p class="text-xs text-gray-500 mt-2">Budget source is automatically set based on your assignments</p>
              <?php endif; ?>
            </div>

            <!-- Budget Owner -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Budget Owner *</label>
              <select name="owner_id" id="owner_id" required class="w-full select2 modern-input">
                <option value="">Loading your budget owners...</option>
              </select>
            </div>

            <!-- Program/Owner Card -->
            <div class="program-card p-5 rounded-2xl bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white shadow-lg transform transition-all duration-300">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <h3 class="text-sm font-semibold text-white opacity-90 mb-3 flex items-center">
                    <i class="fas fa-project-diagram mr-2"></i>Program Details
                  </h3>
                  <div class="space-y-2">
                    <div class="flex items-center space-x-3">
                      <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-code-branch text-xl text-white"></i>
                      </div>
                      <div>
                        <p id="program_p_koox_display" class="text-lg font-bold text-white">-</p>
                        <p id="program_name_display" class="text-sm text-white opacity-80">Project Koox: -</p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="w-16 h-16 bg-white bg-opacity-10 rounded-full flex items-center justify-center backdrop-blur-sm">
                  <i class="fas fa-chart-line text-2xl text-white"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Second row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Plate Number -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Plate Number *</label>
              <select name="plate_number" id="plate_number" required class="w-full select2 modern-input">
                <option value="">Select Vehicle</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?php echo $v['plate_no']; ?>" data-model="<?php echo htmlspecialchars($v['model']); ?>"
                    <?php
                    $sel = false;
                    if (isset($fuel) && $fuel['plate_number'] == $v['plate_no']) $sel = true;
                    if (isset($_POST['plate_number']) && $_POST['plate_number'] == $v['plate_no']) $sel = true;
                    if (!isset($fuel) && !isset($_POST['plate_number']) && $preserved_plate_number == $v['plate_no']) $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['model']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Driver -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Driver Name *</label>
              <input type="text" name="driver_name" id="driver_name" value="<?php
                echo isset($fuel) ? htmlspecialchars($fuel['driver_name']) :
                     (isset($_POST['driver_name']) ? htmlspecialchars($_POST['driver_name']) : 
                     (!empty($preserved_driver_name) ? htmlspecialchars($preserved_driver_name) : ''));
              ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200">
            </div>
            <!-- Ethiopian Month (Gov only) -->
            <div id="et_month_box">
              <label class="block text-sm font-medium text-slate-700 mb-2">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full select2 modern-input">
                <option value="">Select Month</option>
                <?php foreach (['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'] as $m): ?>
                  <option value="<?php echo $m; ?>"
                    <?php
                      $sel = false;
                      if (isset($fuel) && $fuel['et_month'] == $m && $fuel['budget_type']!=='program') $sel = true;
                      if (isset($_POST['et_month']) && $_POST['et_month'] == $m) $sel = true;
                      if (!isset($fuel) && !isset($_POST['et_month']) && $preserved_et_month == $m) $sel = true;
                      echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo $m; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Calculation Method Toggle -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Calculation Method *</label>
              <div class="flex items-center space-x-4 bg-gray-50 p-4 rounded-xl border border-gray-200">
                <label class="inline-flex items-center">
                  <input type="radio" name="calculation_method" value="A" checked 
                         class="calculation-method-radio h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300">
                  <span class="ml-3 text-sm font-medium text-gray-700">Method A (Journey Distance)</span>
                </label>
                <label class="inline-flex items-center">
                  <input type="radio" name="calculation_method" value="B"
                         class="calculation-method-radio h-5 w-5 text-blue-600 focus:ring-blue-500 border-gray-300">
                  <span class="ml-3 text-sm font-medium text-gray-700">Method B (Total Amount)</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Vehicle Details Card -->
          <div class="vehicle-card p-5 rounded-2xl bg-gradient-to-br from-cyan-500 via-blue-500 to-teal-500 text-white shadow-lg transform transition-all duration-300">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="text-sm font-semibold text-white opacity-90 mb-3 flex items-center">
                  <i class="fas fa-car mr-2"></i>Vehicle Details
                </h3>
                <div class="space-y-2">
                  <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center backdrop-blur-sm">
                      <i class="fas fa-tachometer-alt text-xl text-white"></i>
                    </div>
                    <div>
                      <p id="vehicle_model_display" class="text-lg font-bold text-white">-</p>
                      <p id="previous_gauge_display" class="text-sm text-white opacity-80">Previous Gauge: -</p>
                    </div>
                  </div>
                </div>
              </div>
              <div class="w-16 h-16 bg-white bg-opacity-10 rounded-full flex items-center justify-center backdrop-blur-sm">
                <i class="fas fa-gas-pump text-2xl text-white"></i>
              </div>
            </div>
          </div>

          <!-- Numbers -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Current Gauge (km) *</label>
              <input type="number" step="0.01" name="current_gauge" id="current" value="<?php
                echo isset($fuel) ? $fuel['current_gauge'] : (isset($_POST['current_gauge']) ? $_POST['current_gauge'] : '');
              ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Journey Distance (km) *</label>
              <input type="number" step="0.01" name="journey_distance" id="journey" value="<?php
                echo isset($fuel) ? $fuel['journey_distance'] : (isset($_POST['journey_distance']) ? $_POST['journey_distance'] : '');
              ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Fuel Price (per liter) *</label>
              <input type="number" step="0.01" name="fuel_price" id="price" value="<?php
                echo isset($fuel) ? $fuel['fuel_price'] : (isset($_POST['fuel_price']) ? $_POST['fuel_price'] : $last_price);
              ?>" required class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Refuelable Amount (liters)</label>
              <input type="number" step="0.01" name="refuelable_amount" id="refuelable" readonly class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-700">
            </div>
          </div>

          <!-- Additional row for Total Amount input and Gauge Gap -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Total Amount (ብር)</label>
              <input type="number" step="0.01" name="total_amount_input" id="total_input" value="<?php
                echo isset($fuel) ? $fuel['total_amount'] : (isset($_POST['total_amount_input']) ? $_POST['total_amount_input'] : '0');
              ?>" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Gauge Gap (km)</label>
              <input type="number" step="0.01" name="gauge_gap" id="gap_input" readonly class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-50 text-gray-700">
            </div>
          </div>

          <!-- Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-2xl p-6 bg-gradient-to-r from-sky-500 to-blue-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm text-sky-100 font-medium">Total Amount</div>
                  <div id="total_amount_card" class="text-2xl font-extrabold text-white mt-1">0.00 ብር</div>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <i class="fas fa-money-bill-wave text-xl text-white"></i>
                </div>
              </div>
              <input type="hidden" name="total_amount" id="total" value="0">
            </div>
            <div class="rounded-2xl p-6 bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm text-amber-100 font-medium">New Gauge</div>
                  <div id="new_gauge_card" class="text-2xl font-extrabold text-white mt-1">0.00</div>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <i class="fas fa-tachometer-alt text-xl text-white"></i>
                </div>
              </div>
              <input type="hidden" name="new_gauge" id="new_gauge" value="0">
            </div>
            <div class="rounded-2xl p-6 bg-gradient-to-r from-rose-500 to-pink-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm text-rose-100 font-medium">Gauge Gap</div>
                  <div id="gap_card" class="text-2xl font-extrabold text-white mt-1">0.00</div>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <i class="fas fa-ruler text-xl text-white"></i>
                </div>
              </div>
              <input type="hidden" name="gauge_gap" id="gap" value="0">
            </div>
          </div>

          <!-- Availability cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div id="rem_monthly_card" class="rounded-2xl p-6 bg-gradient-to-r from-amber-400 to-yellow-500 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm text-amber-100 font-medium">Monthly Fuel Budget</div>
                  <div id="rem_monthly" class="text-2xl font-extrabold text-white mt-1">0.00</div>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <i class="fas fa-calendar-alt text-xl text-white"></i>
                </div>
              </div>
            </div>
            <div class="rounded-2xl p-6 bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm text-emerald-100 font-medium" id="yearly_label">Available Yearly Fuel Budget</div>
                  <div id="rem_yearly" class="text-2xl font-extrabold text-white mt-1">0.00</div>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <i class="fas fa-coins text-xl text-white"></i>
                </div>
              </div>
            </div>
            <!-- Programs Bureau Total (right when programs + no owner) -->
            <div id="programs_total_card" class="rounded-2xl p-6 bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]" style="display:none;">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm text-purple-100 font-medium">Bureau's Programs Total Budget</div>
                  <div id="programs_total_amount" class="text-2xl font-extrabold text-white mt-1">0.00 ብር</div>
                </div>
                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                  <i class="fas fa-layer-group text-xl text-white"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- Government Grand (below) -->
          <div id="government_grand_card" class="rounded-2xl p-6 mt-4 bg-gradient-to-r from-purple-500 to-indigo-600 text-white shadow-lg transform transition-all duration-300 hover:scale-[1.02]" style="display:none;">
            <div class="flex items-center justify-between">
              <div>
                <div id="gov_grand_label" class="text-sm text-purple-100 font-medium">Bureau's Yearly Government Budget</div>
                <div id="gov_grand_amount" class="text-2xl font-extrabold text-white mt-1">0.00 ብር</div>
              </div>
              <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                <i class="fas fa-building text-xl text-white"></i>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-4 pt-4">
            <?php if (isset($fuel)): ?>
              <a href="fuel_management.php" class="px-6 py-3 bg-gray-300 text-gray-700 rounded-xl hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-all duration-200 font-medium">
                <i class="fas fa-times mr-2"></i>Cancel
              </a>
            <?php endif; ?>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl hover:from-blue-600 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200 font-medium shadow-lg hover:shadow-xl transform hover:scale-105">
              <i class="fas fa-save mr-2"></i>
              <?php echo isset($fuel) ? 'Update Transaction' : 'Add Transaction'; ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Filter toolbar (AJAX list) -->
      <div class="bg-white rounded-2xl p-6 shadow-xl mb-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-slate-800 mb-4 flex items-center">
          <i class="fas fa-filter mr-2 text-blue-500"></i>Filter Transactions
        </h3>
        <div class="grid md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium mb-2">Budget Source</label>
            <select id="flt_type" class="w-full select2 modern-input" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
              <option value="governmental" <?php echo $default_budget_type === 'governmental' ? 'selected' : ''; ?>>Government</option>
              <option value="program" <?php echo $default_budget_type === 'program' ? 'selected' : ''; ?>>Programs</option>
            </select>
            <?php if ($budget_type_locked): ?>
              <input type="hidden" id="flt_type_hidden" value="<?php echo $default_budget_type; ?>">
            <?php endif; ?>
          </div>
          <div>
            <label class="block text-sm font-medium mb-2">Owner</label>
            <select id="flt_owner" class="w-full select2 modern-input">
              <option value="">Any Owner</option>
              <?php if ($is_admin): ?>
                <!-- Admin sees all owners -->
                <?php foreach ($gov_owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>" data-budget-type="governmental"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
                <?php foreach ($prog_owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>" data-budget-type="program"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- Officer sees only assigned owners -->
                <?php foreach ($gov_owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>" data-budget-type="governmental"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
                <?php foreach ($prog_owners as $o): ?>
                  <option value="<?php echo $o['id']; ?>" data-budget-type="program"><?php echo htmlspecialchars($o['code'].' - '.$o['name']); ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <div id="flt_month_box">
            <label class="block text-sm font-medium mb-2">Month (Gov only)</label>
            <select id="flt_month" class="w-full select2 modern-input">
              <option value="">Any Month</option>
              <?php foreach (['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'] as $m): ?>
                <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-2">Plate</label>
            <select id="flt_plate" class="w-full select2 modern-input">
              <option value="">Any Plate</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?php echo htmlspecialchars($v['plate_no']); ?>"><?php echo htmlspecialchars($v['plate_no'].' - '.$v['model']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Live Search Box -->
      <div class="mb-6">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
          </div>
          <input type="text" id="searchInput" placeholder="Search transactions by driver, plate, owner..." class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent modern-input transition-all duration-200" onkeyup="filterTransactions()">
        </div>
      </div>

      <!-- Fuel Transactions Table -->
      <div class="bg-white rounded-2xl p-6 shadow-xl border border-gray-100">
        <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
          <i class="fas fa-list-alt mr-3 text-blue-500"></i>Fuel Transactions
        </h2>
        <div class="overflow-x-auto custom-scrollbar">
          <table class="min-w-full divide-y divide-gray-200 table-modern">
            <thead class="bg-gradient-to-r from-blue-500 to-indigo-600">
              <tr>
                <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Date</th>
                <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Owner</th>
                <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Driver</th>
                <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Plate No</th>
                <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Month</th>
                <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Amount</th>
                <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200" id="transactionsTable">
              <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                <div class="flex flex-col items-center justify-center py-4">
                  <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                  <span>Loading transactions...</span>
                </div>
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    const FUEL_EFFICIENCY = 5; // 1 liter = 5 km
    const defaultFuelPrice = <?php echo json_encode((float)$last_price); ?>;
    let filling = false; // guard to suppress change handlers during programmatic fill
    let lastGauge = 0;
    const isEdit = <?php echo isset($fuel) ? 'true' : 'false'; ?>;
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const isOfficer = <?php echo $is_officer ? 'true' : 'false'; ?>;
    const csrfToken = <?php echo json_encode($_SESSION['csrf']); ?>;
    const defaultBudgetType = <?php echo json_encode($default_budget_type); ?>;
    const budgetTypeLocked = <?php echo $budget_type_locked ? 'true' : 'false'; ?>;
    const preservedOwnerId = <?php echo !empty($preserved_owner_id) ? json_encode($preserved_owner_id) : 'null'; ?>;
    const preservedPlateNumber = <?php echo !empty($preserved_plate_number) ? json_encode($preserved_plate_number) : 'null'; ?>;

    function fmt(n){return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
    function birr(n){return fmt(n)+' ብር';}

    function formatDateToEthiopian(dateString) {
        if (!dateString) return '-';
        
        try {
            const date = new Date(dateString.replace(' ', 'T'));
            const ethiopianYear = date.getFullYear() - 8;
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            
            return `${day}-${month}-${ethiopianYear}`;
        } catch (e) {
            // Fallback to regular format
            try {
                const date = new Date(dateString.replace(' ', 'T'));
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                return `${day}-${month}-${year}`;
            } catch (e2) {
                return dateString.split(' ')[0]; // Just return the date part
            }
        }
    }

    function showSuccessMessage(message) {
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            background: '#f0f9ff',
            iconColor: '#10b981',
            customClass: {
                popup: 'rounded-xl shadow-xl border border-gray-200'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    }

    function showErrorMessage(message) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#fef2f2',
            iconColor: '#ef4444',
            customClass: {
                popup: 'rounded-xl shadow-xl border border-gray-200'
            },
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });
    }

    function showGaugeError() {
        Swal.fire({
            title: '<i class="fas fa-gas-pump text-4xl text-orange-500 mb-4"></i>',
            html: '<div class="text-center"><h3 class="text-xl font-bold text-gray-800 mb-2">Gauge Error</h3><p class="text-gray-600">Current gauge cannot be less than previous gauge.<br>Please check the odometer reading.</p></div>',
            icon: 'warning',
            confirmButtonColor: '#f59e0b',
            confirmButtonText: 'Understand',
            background: '#fff',
            customClass: {
                popup: 'rounded-2xl shadow-2xl'
            }
        });
    }

    function confirmDelete(event, url) {
        event.preventDefault();
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel',
            background: '#fff',
            customClass: {
                popup: 'rounded-2xl shadow-2xl'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }

    function calculateFuel() {
        const method = document.querySelector('input[name="calculation_method"]:checked').value;
        const current = parseFloat($('#current').val()) || 0;
        const price = parseFloat($('#price').val()) || 0;
        
        let journey, refuel, total;

        if (method === 'A') {
            // Method A: Journey Distance → Refuelable Amount → Total Amount
            journey = parseFloat($('#journey').val()) || 0;
            refuel = journey / FUEL_EFFICIENCY;
            total = refuel * price;
            
            $('#total_input').val(total.toFixed(2));
        } else {
            // Method B: Total Amount → Refuelable Amount → Journey Distance
            total = parseFloat($('#total_input').val()) || 0;
            refuel = total / price;
            journey = refuel * FUEL_EFFICIENCY;
            
            $('#journey').val(journey.toFixed(2));
        }

        const newG = current + journey;
        const gap = current - lastGauge;

        $('#refuelable').val(refuel.toFixed(2));
        $('#total').val(total.toFixed(2));
        $('#new_gauge').val(newG.toFixed(2));
        $('#gap').val(gap.toFixed(2));
        $('#gap_input').val(gap.toFixed(2));

        $('#total_amount_card').text(birr(total));
        $('#new_gauge_card').text(newG.toFixed(2));
        $('#gap_card').text(gap.toFixed(2));

        // Color code gauge gap
        if (gap < 0) {
            $('#gap_card').addClass('text-red-600');
            $('#gap_input').addClass('gauge-warning');
        } else {
            $('#gap_card').removeClass('text-red-600');
            $('#gap_input').removeClass('gauge-warning');
        }
    }

    function toggleCalculationMethod() {
        const method = document.querySelector('input[name="calculation_method"]:checked').value;
        
        if (method === 'A') {
            $('#journey').prop('readonly', false).removeClass('bg-gray-100');
            $('#total_input').prop('readonly', true).addClass('bg-gray-100');
        } else {
            $('#journey').prop('readonly', true).addClass('bg-gray-100');
            $('#total_input').prop('readonly', false).removeClass('bg-gray-100');
        }
        
        calculateFuel();
    }

    function setBudgetTypeUI(type) {
      if (type === 'program') {
        $('#et_month_box').hide();
        $('#et_month').prop('required', false);
        $('#rem_monthly_card').hide();
        $('#yearly_label').text('Available Yearly Budget');
        $('#flt_month_box').hide();
      } else {
        $('#et_month_box').show();
        $('#et_month').prop('required', true);
        $('#rem_monthly_card').show();
        $('#yearly_label').text('Available Yearly Fuel Budget');
        $('#flt_month_box').show();
      }
    }

    function clearPlateDependentFields() {
      filling = true;
      $('#plate_number').val('').trigger('change.select2');
      filling = false;
      $('#vehicle_model_display').text('-');
      $('#previous_gauge_display').text('Previous Gauge: -');

      $('#current').val('');
      $('#journey').val('');
      $('#refuelable').val('');
      $('#total').val('');
      $('#new_gauge').val('');
      $('#gap').val('');

      $('#total_amount_card').text('0.00 ብር');
      $('#new_gauge_card').text('0.00');
      $('#gap_card').text('0.00');

      $('#flt_plate').val('').trigger('change.select2');
    }

    function resetFuelFormOnTypeSwitch(){
      if (budgetTypeLocked) return; // Don't reset if budget type is locked
      
      filling = true;
      $('#owner_id').val('').trigger('change.select2');
      $('#et_month').val('').trigger('change.select2');
      $('#plate_number').val('').trigger('change.select2');
      filling = false;

      $('#driver_name').val('');
      $('#current').val('');
      $('#journey').val('');
      $('#price').val(defaultFuelPrice);
      $('#total_input').val('0');
      $('#refuelable').val('');
      $('#total').val('');
      $('#new_gauge').val('');
      $('#gap').val('');
      $('#vehicle_model_display').text('-');
      $('#previous_gauge_display').text('Previous Gauge: -');
      $('#program_p_koox_display').text('-');
      $('#program_name_display').text('Project Koox: -');

      $('#rem_monthly').text('0.00');
      $('#rem_yearly').text('0.00');
      $('#programs_total_card').hide();
      $('#government_grand_card').hide();
      $('#total_amount_card').text('0.00 ብር');
      $('#new_gauge_card').text('0.00');
      $('#gap_card').text('0.00');

      // Sync filters to match form
      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val('').trigger('change.select2');
      $('#flt_month').val('').trigger('change.select2');
      $('#flt_plate').val('').trigger('change.select2');

      fetchFuelList();
    }

    function onBudgetTypeChange(){
      if (budgetTypeLocked) return; // Don't allow changes if budget type is locked
      
      const t = $('#budget_type').val();
      setBudgetTypeUI(t);
      resetFuelFormOnTypeSwitch();
    }

    function fetchAndPopulateOwners(preselectId=null) {
        const budgetType = $('#budget_type').val();
        const ownerSelect = $('#owner_id');
        
        // Data needed to re-select the owner when in edit mode
        const fuelOwnerId = '<?php echo $fuel['owner_id'] ?? ''; ?>';
        const fuelBudgetType = '<?php echo $fuel['budget_type'] ?? ''; ?>';

        ownerSelect.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');

        $.ajax({
            url: 'ajax_get_owners.php',
            type: 'GET',
            data: { budget_type: budgetType },
            dataType: 'json',
            success: function(response) {
                if (response.success && Array.isArray(response.owners)) {
                    ownerSelect.html('<option value="">Select Owner</option>'); // Clear and add default
                    if (response.owners.length === 0) {
                        ownerSelect.html('<option value="">No budget owners assigned to your account</option>');
                    } else {
                        response.owners.forEach(function(owner) {
                            const option = new Option(`${owner.code} - ${owner.name}`, owner.id);
                            $(option).data('p_koox', owner.p_koox || '');
                            ownerSelect.append(option);
                        });
                        
                        // If in edit mode, re-select the correct owner if the budget type matches
                        if (preselectId) {
                            ownerSelect.val(String(preselectId));
                        } else if (isEdit && fuelOwnerId && fuelBudgetType === budgetType) {
                            ownerSelect.val(String(fuelOwnerId));
                        }
                    }
                } else {
                    ownerSelect.html('<option value="">Error loading owners</option>');
                }
            },
            error: function() {
                ownerSelect.html('<option value="">Error loading owners</option>');
            },
            complete: function() {
                ownerSelect.prop('disabled', false).trigger('change.select2');
                // If a value is selected (especially in edit mode), trigger change
                // to update other UI elements like the Program Details card.
                if (ownerSelect.val()) {
                    ownerSelect.trigger('change');
                }
            }
        });
    }

    function updateFilterOwnerOptions() {
        const budgetType = $('#flt_type').val();
        const fltOwnerSelect = $('#flt_owner');
        
        // For officers, we need to reload the filter options based on current budget type
        if (!isAdmin) {
            // Clear and disable while loading
            fltOwnerSelect.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');
            
            $.ajax({
                url: 'ajax_get_owners.php',
                type: 'GET',
                data: { budget_type: budgetType },
                dataType: 'json',
                success: function(response) {
                    if (response.success && Array.isArray(response.owners)) {
                        fltOwnerSelect.html('<option value="">Any Owner</option>');
                        response.owners.forEach(function(owner) {
                            const option = new Option(`${owner.code} - ${owner.name}`, owner.id);
                            $(option).data('budget-type', budgetType);
                            fltOwnerSelect.append(option);
                        });
                    } else {
                        fltOwnerSelect.html('<option value="">Error loading owners</option>');
                    }
                },
                error: function() { 
                    fltOwnerSelect.html('<option value="">Error loading owners</option>'); 
                },
                complete: function() {
                    fltOwnerSelect.prop('disabled', false).trigger('change.select2');
                }
            });
        } else {
            // Admin logic remains the same
            fltOwnerSelect.find('option').each(function() {
                const option = $(this);
                const optionBudgetType = option.data('budget-type');
                if (!optionBudgetType || optionBudgetType === budgetType) {
                    option.prop('disabled', false);
                } else {
                    option.prop('disabled', true);
                    if (option.is(':selected')) fltOwnerSelect.val('');
                }
            });
            fltOwnerSelect.trigger('change.select2');
        }
    }

    function onOwnerChange(){
      const selectedOption = $('#owner_id').find('option:selected');
      const p_koox = selectedOption.data('p_koox') || '-';
      const ownerName = selectedOption.text().split(' - ')[1] || selectedOption.text();
      $('#program_p_koox_display').text(p_koox || '-');
      $('#program_name_display').text('Project Koox: ' + ownerName);

      clearPlateDependentFields();

      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      fetchFuelList();
      loadFuelRemaining();
      refreshGrandTotals();
    }

    function fetchVehicleDetails(plateNumber) {
      if (!plateNumber) {
        $('#vehicle_model_display').text('-');
        $('#previous_gauge_display').text('Previous Gauge: -');
        $('#current').val('');
        lastGauge = 0;
        calculateFuel();
        return;
      }

      // Model from selected option
      const opt = $('#plate_number').find('option:selected');
      const model = opt.data('model') || '-';
      $('#vehicle_model_display').text(model);

      // Sync filter plate
      $('#flt_plate').val(plateNumber).trigger('change.select2');

      // Fetch last gauge
      $.get('get_last_gauge.php', { plate_number: plateNumber }, function(resp) {
        try {
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          lastGauge = Number(j.last_gauge || 0);
          $('#previous_gauge_display').text('Previous Gauge: ' + lastGauge.toFixed(2));
          $('#current').val(lastGauge.toFixed(2));
          calculateFuel();
        } catch (e) {
          console.error('parse error', e);
          $('#previous_gauge_display').text('Previous Gauge: -');
          lastGauge = 0;
        }
      }).fail(function() {
        console.error('get_last_gauge failed');
        $('#previous_gauge_display').text('Previous Gauge: -');
        lastGauge = 0;
      });
    }

    function loadFuelRemaining(){
      const ownerId = $('#owner_id').val();
      const etMonth = $('#et_month').val();
      const year = new Date().getFullYear() - 8;
      const type = $('#budget_type').val();

      if (!ownerId) {
        $('#rem_monthly').text('0.00');
        $('#rem_yearly').text('0.00');
        return;
      }
      if (type === 'program') {
        $.get('get_remaining_program.php', { owner_id: ownerId, year: year }, function(resp){
          try {
            const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_yearly').text(fmt(j.remaining_yearly || 0));
            $('#rem_monthly').text('0.00');
          } catch (e) { $('#rem_yearly').text('0.00'); }
        }).fail(()=>$('#rem_yearly').text('0.00'));
      } else {
        if (!etMonth) { $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); return; }
        $.get('get_remaining_fuel.php', { owner_id: ownerId, code_id: 5, month: etMonth, year: year }, function(resp){
          try {
            const rem = typeof resp === 'string' ? JSON.parse(resp) : resp;
            $('#rem_monthly').text(fmt(rem.remaining_monthly || 0));
            $('#rem_yearly').text(fmt(rem.remaining_yearly || 0));
          } catch (e) {
            $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00');
          }
        }).fail(()=>{ $('#rem_monthly').text('0.00'); $('#rem_yearly').text('0.00'); });
      }
    }

    function refreshGrandTotals(){
      const type    = $('#budget_type').val();
      const ownerId = $('#owner_id').val();
      const year    = new Date().getFullYear() - 8;

      $.get('ajax_fuel_grands.php',{ budget_type:type, owner_id:ownerId, year:year }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          if (type === 'program') {
            if (!ownerId) {
              $('#programs_total_card').show();
              $('#programs_total_amount').text(birr(j.programsTotalYearly || 0));
            } else {
              $('#programs_total_card').hide();
            }
            $('#government_grand_card').hide();
          } else {
            $('#programs_total_card').hide();
            if (!ownerId) {
              $('#government_grand_card').show();
              $('#gov_grand_label').text("Bureau's Yearly Government Budget");
              $('#gov_grand_amount').text(birr(j.govtBureauRemainingYearly || 0));
            } else {
              $('#government_grand_card').show();
              const ownerName = $('#owner_id option:selected').text().split(' - ')[1] || 'Selected Owner';
              $('#gov_grand_label').text(`${ownerName}'s Total Yearly Budget (Grand Yearly Budget)`);
              $('#gov_grand_amount').text(birr(j.govtOwnerYearlyRemaining || 0));
            }
          }
        }catch(e){
          $('#programs_total_card').hide();
          $('#government_grand_card').hide();
        }
      }).fail(function(){
        $('#programs_total_card').hide();
        $('#government_grand_card').hide();
      });
    }

    function toggleFilterMonth(){
      const t = $('#flt_type').val();
      if (t === 'program') { $('#flt_month_box').hide(); } else { $('#flt_month_box').show(); }
    }

    function syncFiltersFromForm(){
      $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
      $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
      $('#flt_month').val($('#et_month').val()).trigger('change.select2');
      $('#flt_plate').val($('#plate_number').val()).trigger('change.select2');
    }

    function validateBeforeSubmit(){
      syncFiltersFromForm();
      
      // Check gauge gap before submission
      const gap = parseFloat($('#gap').val()) || 0;
      if (gap < 0) {
          showGaugeError();
          return false;
      }
      
      return true;
    }

    function fetchFuelList(){
      const type  = $('#flt_type').val();
      const owner = $('#flt_owner').val();
      const month = $('#flt_month').val();
      const plate = $('#flt_plate').val();
      $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i><span>Loading transactions...</span></div></td></tr>');
      $.get('ajax_fuel_list.php', { budget_type:type, owner_id:owner, et_month:month, plate:plate }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          const rows = j.rows || [];
          if(rows.length===0){
            $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i><span>No fuel transactions found.</span></div></td></tr>');
            return;
          }
          let html='';
          rows.forEach(f=>{
            const printUrl = (f.budget_type==='program')
              ? `reports/fuel_transaction_report.php?id=${f.id}`
              : `reports/fuel_transaction_report.php?id=${f.id}`;
            const dataJson = encodeURIComponent(JSON.stringify(f));
            const formattedDate = formatDateToEthiopian(f.date);
            
            let actions = `
              <a href="${printUrl}" class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-green-600 text-white rounded-lg hover:from-emerald-600 hover:to-green-700 transition-all duration-200 shadow-sm flex items-center" target="_blank">
                <i class="fas fa-print mr-2"></i> Print
              </a>
            `;
            <?php if ($is_admin): ?>
            actions += `
              <a href="?action=edit&id=${f.id}" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 shadow-sm flex items-center">
                <i class="fas fa-edit mr-2"></i> Edit
              </a>
              <a href="?action=delete&id=${f.id}" class="px-4 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg hover:from-red-600 hover:to-pink-700 transition-all duration-200 shadow-sm flex items-center" onclick="confirmDelete(event, this.href)">
                <i class="fas fa-trash mr-2"></i> Delete
              </a>
            `;
            <?php endif; ?>
            html += `
              <tr class="row-click hover:bg-gray-50 transition-colors duration-150" data-json="${dataJson}">
                <td class="px-6 py-4 text-sm text-gray-900 font-medium">${formattedDate}</td>
                <td class="px-6 py-4 text-sm text-gray-900">${f.owner_code || ''}</td>
                <td class="px-6 py-4 text-sm text-gray-900">${f.driver_name || ''}</td>
                <td class="px-6 py-4 text-sm text-gray-900 font-mono font-semibold">${f.plate_number || ''}</td>
                <td class="px-6 py-4 text-sm text-gray-900 ethio-font">${f.et_month || ''}</td>
                <td class="px-6 py-4 text-sm text-gray-900 font-bold">${Number(f.total_amount||0).toLocaleString(undefined,{minimumFractionDigits:2})} ብር</td>
                <td class="px-6 py-4 text-sm"><div class="flex space-x-2">${actions}</div></td>
              </tr>`;
          });
          $('#transactionsTable').html(html);
          filterTransactions(); // apply search box filter
        }catch(e){
          $('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i><span>Failed to load transactions.</span></div></td></tr>');
        }
      }).fail(()=>$('#transactionsTable').html('<tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500"><div class="flex flex-col items-center justify-center py-4"><i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i><span>Failed to load transactions.</span></div></td></tr>'));
    }

    function filterTransactions() {
      const filter = (document.getElementById('searchInput').value||'').toLowerCase();
      const rows = document.querySelectorAll('#transactionsTable tr');
      rows.forEach(row=>{
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    }

    // Populate form from clicked row
    function fillFormFromRow(d){
      try{
        filling = true;

        // Budget type
        $('#budget_type').val(d.budget_type || 'governmental').trigger('change.select2');
        setBudgetTypeUI($('#budget_type').val()); // UI only, no reset
        fetchAndPopulateOwners(d.owner_id); // Fetch owners and it will re-select the correct one

        // Owner
        $('#owner_id').val(String(d.owner_id||'')).trigger('change.select2');
        const ownerOpt = $('#owner_id').find('option:selected');
        const p_koox = ownerOpt.data('p_koox') || '-';
        const ownerName = ownerOpt.text().split(' - ')[1] || ownerOpt.text();
        $('#program_p_koox_display').text(p_koox || '-');
        $('#program_name_display').text('Project Koox: ' + ownerName);

        // Month (gov only)
        if ((d.budget_type||'governmental') !== 'program') {
          $('#et_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#et_month').val('').trigger('change.select2');
        }

        // Plate
        $('#plate_number').val(d.plate_number || '').trigger('change.select2');
        const plateOpt = $('#plate_number').find('option:selected');
        $('#vehicle_model_display').text(plateOpt.data('model') || '-');
        $('#previous_gauge_display').text('Previous Gauge: ' + Number(d.previous_gauge||0).toFixed(2));

        // Values
        $('#driver_name').val(d.driver_name || '');
        $('#current').val(Number(d.current_gauge||0).toFixed(2));
        $('#journey').val(Number(d.journey_distance||0).toFixed(2));
        $('#price').val(Number(d.fuel_price||0).toFixed(2));
        $('#refuelable').val(Number(d.refuelable_amount||0).toFixed(2));
        $('#total_input').val(Number(d.total_amount||0).toFixed(2));
        $('#total').val(Number(d.total_amount||0).toFixed(2));
        $('#new_gauge').val(Number(d.new_gauge||0).toFixed(2));
        $('#gap').val(Number(d.gauge_gap||0).toFixed(2));
        $('#gap_input').val(Number(d.gauge_gap||0).toFixed(2));

        // Set calculation method based on available data
        if (d.journey_distance && d.journey_distance > 0) {
            $('input[name="calculation_method"][value="A"]').prop('checked', true);
        } else {
            $('input[name="calculation_method"][value="B"]').prop('checked', true);
        }
        toggleCalculationMethod();

        // Cards
        $('#total_amount_card').text(birr(d.total_amount||0));
        $('#new_gauge_card').text(Number(d.new_gauge||0).toFixed(2));
        $('#gap_card').text(Number(d.gauge_gap||0).toFixed(2));

        // Sync lower filters to match this row
        $('#flt_type').val(d.budget_type || 'governmental').trigger('change.select2');
        updateFilterOwnerOptions(); // Update filter options
        $('#flt_owner').val(String(d.owner_id||'')).trigger('change.select2');
        if ((d.budget_type||'governmental') !== 'program') {
          $('#flt_month').val(d.et_month || '').trigger('change.select2');
        } else {
          $('#flt_month').val('').trigger('change.select2');
        }
        $('#flt_plate').val(d.plate_number || '').trigger('change.select2');

        filling = false;

        // Refresh availability and grands
        loadFuelRemaining();
        refreshGrandTotals();

      }catch(e){
        filling = false;
        console.error('fillFormFromRow error', e);
      }
    }

    $(document).ready(function(){
      $('.select2').select2({ 
        theme: 'classic', 
        width: '100%',
        dropdownCssClass: 'rounded-xl shadow-xl border border-gray-200',
        matcher: function(params, data) {
          if ($.trim(params.term) === '') return data;
          if (typeof data.text === 'undefined') return null;
          if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
          for (const key in data.element.dataset) {
            if (String(data.element.dataset[key]).toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
          }
          return null;
        }
      });

      // Set the default budget type on page load
      if (!isEdit) {
        $('#budget_type').val(defaultBudgetType);
        $('#flt_type').val(defaultBudgetType);
      }

      // Main form dropdowns drive filtering and form behavior
      if (!budgetTypeLocked) {
        $('#budget_type').on('change', function(){
          if (filling) return;
          onBudgetTypeChange();
          fetchAndPopulateOwners(); // Fetch new owner list
          $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
          toggleFilterMonth();
          fetchFuelList();
        });
      }

      $('#owner_id').on('change', function(){
        if (filling) return;
        onOwnerChange();
      });

      $('#et_month').on('change', function(){
        if (filling) return;
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        fetchFuelList();
        loadFuelRemaining();
      });

      // Plate change (main form)
      $('#plate_number').on('change', function(){
        if (filling) return;
        const p = this.value || '';
        fetchVehicleDetails(p);
        $('#flt_plate').val(p).trigger('change.select2');
        fetchFuelList();
      });

      // Filter plate drives main plate, fetches and fills
      $('#flt_plate').on('change', function(){
        const p = this.value || '';
        filling = true;
        $('#plate_number').val(p).trigger('change.select2');
        filling = false;
        fetchVehicleDetails(p);
        fetchFuelList();
      });

      // Filter changes
      if (!budgetTypeLocked) {
        $('#flt_type').on('change', function(){
          updateFilterOwnerOptions(); // Sync filter owners
          toggleFilterMonth();
          fetchFuelList();
        });
      }
      $('#flt_owner, #flt_month').on('change', function(){
        fetchFuelList();
      });

      // Calculation method change handler
      $('.calculation-method-radio').on('change', function() {
          toggleCalculationMethod();
      });

      // Make table rows clickable to populate form
      $('#transactionsTable').on('click', 'tr.row-click', function(e){
        if ($(e.target).closest('a,button').length) return;
        const dataJson = $(this).attr('data-json');
        if (!dataJson) return;
        try {
          const d = JSON.parse(decodeURIComponent(dataJson));
          fillFormFromRow(d);
        } catch (err) {
          console.error('row parse error', err);
        }
      });

      // INITIALIZE
      if (isEdit) {
        setBudgetTypeUI($('#budget_type').val()); // UI only, no reset
        fetchAndPopulateOwners(); // Fetch owners and it will re-select the correct one
        filling = true;
        $('#budget_type').trigger('change.select2');
        $('#plate_number').trigger('change.select2');
        <?php if (!empty($fuel) && $fuel['budget_type']!=='program'): ?>
          $('#et_month').trigger('change.select2');
        <?php endif; ?>
        filling = false;

        const p = $('#plate_number').val();
        if (p) fetchVehicleDetails(p);

        loadFuelRemaining();
        refreshGrandTotals();

        // Sync filters to form
        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        updateFilterOwnerOptions(); // Update filter options
        $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
        $('#flt_plate').val($('#plate_number').val()).trigger('change.select2');
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        toggleFilterMonth();
        fetchFuelList();
      } else {
        setBudgetTypeUI($('#budget_type').val()); // UI only
        fetchAndPopulateOwners(preservedOwnerId); // Pass preserved owner ID
        updateFilterOwnerOptions(); // Sync filter dropdowns on initial load
        toggleCalculationMethod();
        calculateFuel();
        loadFuelRemaining();
        refreshGrandTotals();
        
        // Set preserved plate number if available
        if (preservedPlateNumber) {
            filling = true;
            $('#plate_number').val(preservedPlateNumber).trigger('change.select2');
            filling = false;
        }
        
        // Initialize filters to current form state
        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        $('#flt_owner').val(preservedOwnerId || $('#owner_id').val()).trigger('change.select2');
        $('#flt_plate').val(preservedPlateNumber || $('#plate_number').val()).trigger('change.select2');
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        toggleFilterMonth();
        fetchFuelList();
      }
    });

    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });
  </script>
</body>
</html>