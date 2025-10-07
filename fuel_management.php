<?php
ini_set('display_errors', 1);

require_once 'includes/init.php';
//require_once 'includes/sidebar.php';

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

// Get user name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['name'] ?? $_SESSION['username'];

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

// Enhanced Notification System - Fetch unread notifications
$unread_notifications = [];
$notifications_count = 0;
try {
    $stmt = $pdo->prepare("
        SELECT id, title, message, type, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $notifications_count = count($unread_notifications);
} catch (Exception $e) {
    // If notifications table doesn't exist, silently continue
    error_log("Notifications error: " . $e->getMessage());
}

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$_GET['mark_read'], $user_id]);
        
        // Update local notifications array
        foreach ($unread_notifications as $key => $notification) {
            if ($notification['id'] == $_GET['mark_read']) {
                unset($unread_notifications[$key]);
            }
        }
        $notifications_count = count($unread_notifications);
        
        header('Location: fuel_management.php');
        exit;
    } catch (Exception $e) {
        error_log("Mark read error: " . $e->getMessage());
    }
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $unread_notifications = [];
        $notifications_count = 0;
        
        header('Location: fuel_management.php');
        exit;
    } catch (Exception $e) {
        error_log("Mark all read error: " . $e->getMessage());
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
    $refuelable_amt = (float)($_POST['refuelable_amount']?? 0);
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $new_gauge = (float)($_POST['new_gauge'] ?? 0);
    $gauge_gap = (float)($_POST['gauge_gap'] ?? 0);
    
    // Budget access validation
    if (!$is_admin && !hasBudgetAccess($pdo, $user_id, $budget_type, $owner_id)) { 
        $_SESSION['message'] = 'You do not have access to this budget owner';
        $_SESSION['message_type'] = 'error';
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

    // Gauge continuity
    $stmt = $pdo->prepare("SELECT new_gauge FROM fuel_transactions WHERE plate_number = ? ORDER BY date DESC, id DESC LIMIT 1");
    $stmt->execute([$plate_number]);
    $last_new_gauge = (float)($stmt->fetchColumn() ?: 0);
    if ($last_new_gauge && $current_gauge < $last_new_gauge) {
        $_SESSION['message'] = 'Gauge error: Current gauge less than expected new gauge.';
        $_SESSION['message_type'] = 'error';
    } else {
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
                        $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                        $refuelable_amt, $total_amount, $new_gauge, $gauge_gap, $_POST['id']
                    ]);
                    $_SESSION['message'] = 'Program fuel transaction updated';
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
                        $last_new_gauge, $current_gauge, $journey_distance, $fuel_price,
                        $refuelable_amt, $total_amount, $new_gauge, $gauge_gap
                    ]);
                    $_SESSION['message'] = 'Program fuel transaction added';
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
                        $last_new_gauge, $current_gauge, $journey_distance,
                        $fuel_price, $refuelable_amt, $total_amount,
                        $new_gauge, $gauge_gap, $_POST['id']
                    ]);
                    $_SESSION['message'] = 'Fuel transaction updated';
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
                        $last_new_gauge, $current_gauge, $journey_distance,
                        $fuel_price, $refuelable_amt, $total_amount,
                        $new_gauge, $gauge_gap
                    ]);
                    $_SESSION['message'] = 'Fuel transaction added';
                }
            }

            $pdo->commit();
            $_SESSION['message_type'] = 'success';
            header('Location: fuel_management.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    if (!$is_admin) { http_response_code(403); exit('Forbidden'); }
    $stmt = $pdo->prepare("DELETE FROM fuel_transactions WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['message'] = 'Fuel transaction deleted';
    $_SESSION['message_type'] = 'success';
    header('Location: fuel_management.php'); exit;
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
    // give this page a custom title
    $pageTitle = 'F Management';
    require_once 'includes/head.php';
  ?>
  <style>
    /* Enhanced Notification Styles */
    .notification-bell {
        position: relative;
        padding: 8px 12px;
        border-radius: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .notification-bell:hover {
        background: rgba(99, 102, 241, 0.1);
    }
    
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 380px;
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }
    
    .notification-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .notification-header {
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: between;
        align-items: center;
    }
    
    .notification-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .notification-item {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        transition: background 0.2s ease;
        cursor: pointer;
    }
    
    .notification-item:hover {
        background: #f9fafb;
    }
    
    .notification-item.unread {
        background: #f0f9ff;
        border-left: 3px solid #3b82f6;
    }
    
    .notification-title {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 4px;
    }
    
    .notification-message {
        color: #6b7280;
        font-size: 14px;
        line-height: 1.4;
    }
    
    .notification-time {
        color: #9ca3af;
        font-size: 12px;
        margin-top: 4px;
    }
    
    .notification-actions {
        padding: 12px 16px;
        border-top: 1px solid #e5e7eb;
        text-align: center;
    }
    
    .empty-notifications {
        padding: 32px 16px;
        text-align: center;
        color: #9ca3af;
    }
    
    .notification-type-indicator {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    
    .type-success { background: #10b981; }
    .type-warning { background: #f59e0b; }
    .type-error { background: #ef4444; }
    .type-info { background: #3b82f6; }
  </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
  <?php require_once 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="p-6">
      <!-- Header -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Fuel Management</h1>
          <p class="text-slate-600 mt-2">Manage fuel transactions and consumption</p>
          <div class="mt-3 bg-indigo-100 rounded-lg p-3 max-w-md info-card">
            <i class="fas fa-user-circle text-indigo-600 mr-2"></i>
            <span class="text-indigo-800 font-semibold">
              Welcome, <?php echo htmlspecialchars($user_name); ?>! (<?php echo ucfirst($_SESSION['role']); ?>)
            </span>
          </div>
        </div>
        <div class="flex items-center space-x-4 mt-4 md:mt-0">
          <!-- Enhanced Notification Bell -->
          <div class="notification-bell relative" id="notificationBell">
            <i class="fas fa-bell text-xl text-slate-600"></i>
            <?php if ($notifications_count > 0): ?>
              <span class="notification-badge" id="notificationCount"><?php echo $notifications_count; ?></span>
            <?php endif; ?>
            
            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="notificationDropdown">
              <div class="notification-header">
                <h3 class="text-lg font-semibold text-slate-800">Notifications</h3>
                <?php if ($notifications_count > 0): ?>
                  <a href="?mark_all_read=1" class="text-sm text-blue-600 hover:text-blue-800">Mark all as read</a>
                <?php endif; ?>
              </div>
              
              <div class="notification-list">
                <?php if (empty($unread_notifications)): ?>
                  <div class="empty-notifications">
                    <i class="fas fa-bell-slash text-3xl text-gray-300 mb-3"></i>
                    <p class="text-gray-500">No new notifications</p>
                  </div>
                <?php else: ?>
                  <?php foreach ($unread_notifications as $notification): ?>
                    <div class="notification-item unread" data-id="<?php echo $notification['id']; ?>">
                      <div class="flex items-start">
                        <span class="notification-type-indicator type-<?php echo $notification['type'] ?? 'info'; ?>"></span>
                        <div class="flex-1">
                          <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                          <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                          <div class="notification-time">
                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                          </div>
                        </div>
                        <button class="mark-read-btn text-gray-400 hover:text-gray-600 ml-2" 
                                onclick="markNotificationRead(<?php echo $notification['id']; ?>)">
                          <i class="fas fa-check"></i>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              
              <?php if (!empty($unread_notifications)): ?>
                <div class="notification-actions">
                  <a href="?mark_all_read=1" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <i class="fas fa-check-double mr-1"></i>Mark all as read
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
          
          <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <!-- Message -->
      <?php if ($message): ?>
        <div id="message" class="fade-out mb-6 p-4 rounded-lg <?php
          echo $message_type == 'error' ? 'bg-red-100 text-red-700' :
          ($message_type == 'success' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700');
        ?>">
          <div class="flex justify-between items-center">
            <p><?php echo htmlspecialchars($message); ?></p>
            <button onclick="document.getElementById('message').classList.add('hide')" class="text-lg">&times;</button>
          </div>
        </div>
        <script>setTimeout(()=>{const m=document.getElementById('message');if(m){m.classList.add('hide');setTimeout(()=>m.remove(),500);}},5000);</script>
      <?php endif; ?>

      <!-- Fuel Form -->
      <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6"><?php echo isset($fuel) ? 'Edit Fuel Transaction' : 'Add New Fuel Transaction'; ?></h2>
        <form id="fuelForm" method="POST" class="space-y-4" onsubmit="return validateBeforeSubmit();">
          <?php if (isset($fuel)): ?>
            <input type="hidden" name="id" value="<?php echo $fuel['id']; ?>">
            <input type="hidden" name="action" value="update">
          <?php endif; ?>
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">

          <!-- Top row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Budget Source Type -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Source Type *</label>
              <select name="budget_type" id="budget_type" class="w-full select2" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
                <option value="governmental" <?php 
                  if (isset($fuel)) {
                    echo $fuel['budget_type']==='governmental' ? 'selected' : '';
                  } else {
                    echo $default_budget_type === 'governmental' ? 'selected' : '';
                  }
                ?>>Government Budget</option>
                <option value="program" <?php 
                  if (isset($fuel)) {
                    echo $fuel['budget_type']==='program' ? 'selected' : '';
                  } else {
                    echo $default_budget_type === 'program' ? 'selected' : '';
                  }
                ?>>Programs Budget</option>
              </select>
              <?php if ($budget_type_locked): ?>
                <input type="hidden" name="budget_type" value="<?php echo $default_budget_type; ?>">
                <p class="text-xs text-gray-500 mt-1">Budget source is automatically set based on your assignments</p>
              <?php endif; ?>
            </div>

            <!-- Budget Owner -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner *</label>
              <select name="owner_id" id="owner_id" required class="w-full select2">
                <option value="">Loading your budget owners...</option>
              </select>
            </div>

            <!-- Program/Owner Card -->
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
          </div>

          <!-- Second row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Plate Number -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Plate Number *</label>
              <select name="plate_number" id="plate_number" required class="w-full select2">
                <option value="">Select Vehicle</option>
                <?php foreach ($vehicles as $v): ?>
                  <option value="<?php echo $v['plate_no']; ?>" data-model="<?php echo htmlspecialchars($v['model']); ?>"
                    <?php
                    $sel = false;
                    if (isset($fuel) && $fuel['plate_number'] == $v['plate_no']) $sel = true;
                    if (isset($_POST['plate_number']) && $_POST['plate_number'] == $v['plate_no']) $sel = true;
                    echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo htmlspecialchars($v['plate_no'] . ' - ' . $v['model']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <!-- Driver -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Driver Name *</label>
              <input type="text" name="driver_name" id="driver_name" value="<?php
                echo isset($fuel) ? htmlspecialchars($fuel['driver_name']) :
                     (isset($_POST['driver_name']) ? htmlspecialchars($_POST['driver_name']) : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
            <!-- Ethiopian Month (Gov only) -->
            <div id="et_month_box">
              <label class="block text-sm font-medium text-slate-700 mb-1">Ethiopian Month *</label>
              <select name="et_month" id="et_month" required class="w-full select2">
                <option value="">Select Month</option>
                <?php foreach (['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'] as $m): ?>
                  <option value="<?php echo $m; ?>"
                    <?php
                      $sel = false;
                      if (isset($fuel) && $fuel['et_month'] == $m && $fuel['budget_type']!=='program') $sel = true;
                      if (isset($_POST['et_month']) && $_POST['et_month'] == $m) $sel = true;
                      echo $sel ? 'selected' : '';
                    ?>>
                    <?php echo $m; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Vehicle card -->
          <div class="vehicle-card p-4 rounded-lg">
            <h3 class="text-sm font-medium text-green-800 mb-2">Vehicle Details</h3>
            <div class="flex items-center">
              <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-car text-green-600"></i>
              </div>
              <div>
                <p id="vehicle_model_display" class="text-sm font-medium text-gray-900">-</p>
                <p id="previous_gauge_display" class="text-xs text-gray-600">Previous Gauge: -</p>
              </div>
            </div>
          </div>
    <!-- Numbers -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Previous Gauge *</label>
              <input type="number" step="0.01" name="current_gauge" id="current" value="<?php
                echo isset($fuel) ? $fuel['current_gauge'] : (isset($_POST['current_gauge']) ? $_POST['current_gauge'] : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
</div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Journey Distance (km) *</label>
              <input type="number" step="0.01" name="journey_distance" id="journey" value="<?php
                echo isset($fuel) ? $fuel['journey_distance'] : (isset($_POST['journey_distance']) ? $_POST['journey_distance'] : '');
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Fuel Price (per liter) *</label>
              <input type="number" step="0.01" name="fuel_price" id="price" value="<?php
                echo isset($fuel) ? $fuel['fuel_price'] : (isset($_POST['fuel_price']) ? $_POST['fuel_price'] : $last_price);
              ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" oninput="calculateFuel()">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Refuelable Amount (liters)</label>
              <input type="number" step="0.01" name="refuelable_amount" id="refuelable" readonly class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="rounded-xl p-4 bg-gradient-to-r from-sky-100 to-sky-50 border border-sky-200 shadow-sm">
              <div class="text-sm text-sky-700 font-medium">Total Amount</div>
              <div id="total_amount_card" class="text-2xl font-extrabold text-sky-900 mt-1">0.00 ብር</div>
              <input type="hidden" name="total_amount" id="total" value="0">
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="text-sm text-amber-700 font-medium">New Gauge</div>
              <div id="new_gauge_card" class="text-2xl font-extrabold text-amber-900 mt-1">0.00</div>
              <input type="hidden" name="new_gauge" id="new_gauge" value="0">
            </div>
            <div class="rounded-xl p-4 bg-gradient-to-r from-rose-100 to-rose-50 border border-rose-200 shadow-sm">
              <div class="text-sm text-rose-700 font-medium">Gauge Gap</div>
              <div id="gap_card" class="text-2xl font-extrabold text-rose-900 mt-1">0.00</div>
              <input type="hidden" name="gauge_gap" id="gap" value="0">
            </div>
          </div>

          <!-- Availability cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div id="rem_monthly_card" class="rounded-xl p-5 bg-gradient-to-r from-amber-100 to-amber-50 border border-amber-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-amber-200 text-amber-800"><i class="fas fa-calendar-alt"></i></div>
                <div>
                  <div class="text-sm text-amber-700 font-medium">Monthly Fuel Budget</div>
                  <div id="rem_monthly" class="text-2xl font-extrabold text-amber-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <div class="rounded-xl p-5 bg-gradient-to-r from-emerald-100 to-emerald-50 border border-emerald-200 shadow-sm">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-emerald-200 text-emerald-800"><i class="fas fa-coins"></i></div>
                <div>
                  <div class="text-sm text-emerald-700 font-medium" id="yearly_label">Available Yearly Fuel Budget</div>
                  <div id="rem_yearly" class="text-2xl font-extrabold text-emerald-900 mt-1">0.00</div>
                </div>
              </div>
            </div>
            <!-- Programs Bureau Total (right when programs + no owner) -->
            <div id="programs_total_card" class="rounded-xl p-5 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
              <div class="flex items-center gap-3">
                <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-layer-group"></i></div>
                <div>
                  <div class="text-sm text-purple-700 font-medium">Bureau's Programs Total Budget</div>
                  <div id="programs_total_amount" class="text-2xl font-extrabold text-purple-900 mt-1">0.00 ብር</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Government Grand (below) -->
          <div id="government_grand_card" class="rounded-xl p-5 mt-4 bg-gradient-to-r from-purple-100 to-purple-50 border border-purple-200 shadow-sm" style="display:none;">
            <div class="flex items-center gap-3">
              <div class="p-3 rounded-full bg-purple-200 text-purple-800"><i class="fas fa-building"></i></div>
              <div>
                <div id="gov_grand_label" class="text-sm text-purple-700 font-medium">Bureau's Yearly Government Budget</div>
                <div id="gov_grand_amount" class="text-2xl font-extrabold text-purple-900 mt-1">0.00 ብር</div>
              </div>
            </div>
          </div>

          <div class="flex justify-end space-x-4 pt-2">
            <?php if (isset($fuel)): ?>
              <a href="fuel_management.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">Cancel</a>
            <?php endif; ?>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <?php echo isset($fuel) ? 'Update Transaction' : 'Add Transaction'; ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Filter toolbar (AJAX list) -->
      <div class="bg-white rounded-xl p-4 shadow-sm mb-4">
        <div class="grid md:grid-cols-4 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Budget Source</label>
            <select id="flt_type" class="w-full select2" <?php echo $budget_type_locked ? 'disabled' : ''; ?>>
              <option value="governmental" <?php echo $default_budget_type === 'governmental' ? 'selected' : ''; ?>>Government</option>
              <option value="program" <?php echo $default_budget_type === 'program' ? 'selected' : ''; ?>>Programs</option>
            </select>
            <?php if ($budget_type_locked): ?>
              <input type="hidden" id="flt_type_hidden" value="<?php echo $default_budget_type; ?>">
            <?php endif; ?>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Owner</label>
            <select id="flt_owner" class="w-full select2">
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
            <label class="block text-sm font-medium mb-1">Month (Gov only)</label>
            <select id="flt_month" class="w-full select2">
              <option value="">Any Month</option>
              <?php foreach (['መስከረም','ጥቅምት','ህዳር','ታኅሳስ','ጥር','የካቲት','መጋቢት','ሚያዝያ','ግንቦት','ሰኔ','ሐምሌ','ነሐሴ'] as $m): ?>
                <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Plate</label>
            <select id="flt_plate" class="w-full select2">
              <option value="">Any Plate</option>
              <?php foreach ($vehicles as $v): ?>
                <option value="<?php echo htmlspecialchars($v['plate_no']); ?>"><?php echo htmlspecialchars($v['plate_no'].' - '.$v['model']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Live Search Box -->
      <div class="mb-4">
        <input type="text" id="searchInput" placeholder="Search transactions..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" onkeyup="filterTransactions()">
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
              <tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    const defaultFuelPrice = <?php echo json_encode((float)$last_price); ?>;
    let filling = false; // guard to suppress change handlers during programmatic fill
    const isEdit = <?php echo isset($fuel) ? 'true' : 'false'; ?>;
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const isOfficer = <?php echo $is_officer ? 'true' : 'false'; ?>;
    const csrfToken = <?php echo json_encode($_SESSION['csrf']); ?>;
    const defaultBudgetType = <?php echo json_encode($default_budget_type); ?>;
    const budgetTypeLocked = <?php echo $budget_type_locked ? 'true' : 'false'; ?>;

    function fmt(n){return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});}
    function birr(n){return fmt(n)+' ብር';}

    // Enhanced Notification Functions
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('show');
    }

    function markNotificationRead(notificationId) {
        fetch(`?mark_read=${notificationId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(() => {
            // Remove the notification from UI
            const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.remove();
            }
            
            // Update badge count
            updateNotificationBadge();
            
            // If no notifications left, show empty state
            const notificationList = document.querySelector('.notification-list');
            const notifications = notificationList.querySelectorAll('.notification-item');
            if (notifications.length === 0) {
                notificationList.innerHTML = `
                    <div class="empty-notifications">
                        <i class="fas fa-bell-slash text-3xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No new notifications</p>
                    </div>
                `;
                document.querySelector('.notification-actions').style.display = 'none';
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }

    function updateNotificationBadge() {
        const badge = document.getElementById('notificationCount');
        const notificationItems = document.querySelectorAll('.notification-item');
        const count = notificationItems.length;
        
        if (badge) {
            if (count === 0) {
                badge.remove();
            } else {
                badge.textContent = count;
            }
        } else if (count > 0) {
            // Create badge if it doesn't exist
            const bell = document.getElementById('notificationBell');
            const newBadge = document.createElement('span');
            newBadge.id = 'notificationCount';
            newBadge.className = 'notification-badge';
            newBadge.textContent = count;
            bell.appendChild(newBadge);
        }
    }

    // Close notifications when clicking outside
    document.addEventListener('click', function(event) {
        const bell = document.getElementById('notificationBell');
        const dropdown = document.getElementById('notificationDropdown');
        
        if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });

    function calculateFuel() {
      const current = parseFloat($('#current').val()) || 0;
      const journey = parseFloat($('#journey').val()) || 0;
      const price = parseFloat($('#price').val()) || 0;
      const refuel = journey / 5;
      const total = refuel * price;
      const newG = current + journey;
      const gap = newG - current;
      $('#refuelable').val(refuel.toFixed(2));
      $('#total').val(total.toFixed(2));
      $('#total_amount_card').text(birr(total));
      $('#new_gauge').val(newG.toFixed(2));
      $('#new_gauge_card').text(newG.toFixed(2));
      $('#gap').val(gap.toFixed(2));
      $('#gap_card').text(gap.toFixed(2));
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
        $('#journey').val('');
        $('#refuelable').val('');
        $('#total').val('');
        $('#new_gauge').val('');
        $('#gap').val('');
        $('#total_amount_card').text('0.00 ብር');
        $('#new_gauge_card').text('0.00');
        $('#gap_card').text('0.00');
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
          const last = Number(j.last_gauge || 0);
          $('#previous_gauge_display').text('Previous Gauge: ' + last.toFixed(2));

          // Always use last as current on plate change
          $('#current').val(last.toFixed(2));

          // Clear dependent inputs
          $('#journey').val('');
          $('#refuelable').val('');
          $('#total').val('');
          $('#new_gauge').val('');
          $('#gap').val('');

          // Reset cards
          $('#total_amount_card').text('0.00 ብር');
          $('#new_gauge_card').text('0.00');
          $('#gap_card').text('0.00');

          calculateFuel();
        } catch (e) {
          console.error('parse error', e);
          $('#previous_gauge_display').text('Previous Gauge: -');
        }
      }).fail(function() {
        console.error('get_last_gauge failed');
        $('#previous_gauge_display').text('Previous Gauge: -');
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
      return true;
    }

    function fetchFuelList(){
      const type  = $('#flt_type').val();
      const owner = $('#flt_owner').val();
      const month = $('#flt_month').val();
      const plate = $('#flt_plate').val();
      $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Loading…</td></tr>');
      $.get('ajax_fuel_list.php', { budget_type:type, owner_id:owner, et_month:month, plate:plate }, function(resp){
        try{
          const j = typeof resp === 'string' ? JSON.parse(resp) : resp;
          const rows = j.rows || [];
          if(rows.length===0){
            $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">No fuel transactions found.</td></tr>');
            return;
          }
          let html='';
          rows.forEach(f=>{
            const printUrl = (f.budget_type==='program')
              ? `reports/fuel_transaction_report.php?id=${f.id}`
              : `reports/fuel_transaction_report.php?id=${f.id}`;
            const dataJson = encodeURIComponent(JSON.stringify(f));
            let actions = `
              <a href="${printUrl}" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200" target="_blank">
                <i class="fas fa-print mr-1"></i> Print
              </a>
            `;
            <?php if ($is_admin): ?>
            actions += `
              <a href="?action=edit&id=${f.id}" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200">
                <i class="fas fa-edit mr-1"></i> Edit
              </a>
              <a href="?action=delete&id=${f.id}" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200" onclick="return confirm('Are you sure you want to delete this transaction?')">
                <i class="fas fa-trash mr-1"></i> Delete
              </a>
            `;
            <?php endif; ?>
            html += `
              <tr class="row-click" data-json="${dataJson}">
                <td class="px-4 py-4 text-sm text-gray-900">${(f.date||'').replace('T',' ').slice(0,19)}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.owner_code || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.driver_name || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${f.plate_number || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900 ethio-font">${f.et_month || ''}</td>
                <td class="px-4 py-4 text-sm text-gray-900">${Number(f.total_amount||0).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                <td class="px-4 py-4 text-sm"><div class="flex space-x-2">${actions}</div></td>
              </tr>`;
          });
          $('#transactionsTable').html(html);
          filterTransactions(); // apply search box filter
        }catch(e){
          $('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Failed to load.</td></tr>');
        }
      }).fail(()=>$('#transactionsTable').html('<tr><td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">Failed to load.</td></tr>'));
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
        $('#total').val(Number(d.total_amount||0).toFixed(2));
        $('#new_gauge').val(Number(d.new_gauge||0).toFixed(2));
        $('#gap').val(Number(d.gauge_gap||0).toFixed(2));

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
      $('.select2').select2({ theme:'classic', width:'100%',
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
        fetchAndPopulateOwners(); // Fetch initial owners
        updateFilterOwnerOptions(); // Sync filter dropdowns on initial load
        calculateFuel();
        loadFuelRemaining();
        refreshGrandTotals();
        // Initialize filters to current form state
        $('#flt_type').val($('#budget_type').val()).trigger('change.select2');
        $('#flt_owner').val($('#owner_id').val()).trigger('change.select2');
        $('#flt_plate').val($('#plate_number').val()).trigger('change.select2');
        $('#flt_month').val($('#et_month').val()).trigger('change.select2');
        toggleFilterMonth();
        fetchFuelList();
      }
    });

    document.getElementById('sidebarToggle')?.addEventListener('click', ()=>{
      document.getElementById('sidebar').classList.toggle('collapsed');
      document.getElementById('mainContent').classList.toggle('expanded');
    });

    // Initialize notification bell click handler
    document.getElementById('notificationBell')?.addEventListener('click', toggleNotifications);
  </script>
</body>
</html>