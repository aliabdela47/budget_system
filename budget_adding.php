<?php
require_once 'includes/init.php';
require_once 'includes/functions.php';
require_admin(); // Admin only

// Secure CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function csrf_check($t) { return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

// PDO safer defaults
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$BUDGET_TYPE_PROGRAM = 'program'; // matches your enum

// EC months (English transliterations used for storage/display here)
$EC_MONTHS_EN = ['Meskerem','Tikimt','Hidar','Tahsas','Tir','Yekatit','Megabit','Miazia','Ginbot','Sene','Hamle','Nehase','Pagume'];
$quarterMap = [
    'Meskerem'=>1,'Tikimt'=>1,'Hidar'=>1,
    'Tahsas'=>2,'Tir'=>2,'Yekatit'=>2,
    'Megabit'=>3,'Miazia'=>3,'Ginbot'=>3,
    'Sene'=>4,'Hamle'=>4,'Nehase'=>4,'Pagume'=>4
];

// Month normalizer -> ec_month number (accept Amharic or English)
function ecMonthNoFromString(string $m): ?int {
    static $map = [
        'Meskerem'=>1,'Tikimt'=>2,'Hidar'=>3,'Tahsas'=>4,'Tir'=>5,'Yekatit'=>6,'Megabit'=>7,'Miazia'=>8,'Ginbot'=>9,'Sene'=>10,'Hamle'=>11,'Nehase'=>12,'Pagume'=>13,
        'መስከረም'=>1,'ጥቅምት'=>2,'ህዳር'=>3,'ታኅሳስ'=>4,'ጥር'=>5,'የካቲት'=>6,'መጋቢት'=>7,'ሚያዝያ'=>8,'ግንቦት'=>9,'ሰኔ'=>10,'ሐምሌ'=>11,'ነሐሴ'=>12,'ጳጉሜ'=>13
    ];
    $m = trim($m);
    return $map[$m] ?? null;
}

// JSON endpoint: Availability (yearly + monthly remaining)
if (isset($_GET['action']) && $_GET['action'] === 'availability') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $code_id_raw = $_GET['code_id'] ?? '';
        $code_id     = ($code_id_raw === '' ? null : (int)$code_id_raw);
        $adding_date = $_GET['adding_date'] ?? date('Y-m-d');
        $month       = $_GET['month'] ?? '';

        if (!$owner_id) throw new Exception('owner_id required');

        // Derive EC year (fallback: Gregorian-8)
        $etInfo = function_exists('getEtMonthAndQuarter') ? getEtMonthAndQuarter($adding_date) : null;
        $year   = $etInfo['year'] ?? (date('Y', strtotime($adding_date)) - 8);

        $resp = ['ok'=>true, 'yearlyAvailable'=>0, 'monthlyAvailable'=>0];

        if ($budget_type === $BUDGET_TYPE_PROGRAM) {
            // Programs: yearly-only
            $sql = "SELECT COALESCE(SUM(remaining_yearly),0) FROM budgets
                    WHERE owner_id=? AND year=? AND budget_type=? AND monthly_amount=0";
            $p   = [$owner_id, $year, $BUDGET_TYPE_PROGRAM];
            if ($code_id === null) {
                $sql .= " AND code_id IS NULL";
            } else {
                $sql .= " AND code_id = ?";
                $p[] = $code_id;
            }
            $st = $pdo->prepare($sql);
            $st->execute($p);
            $resp['yearlyAvailable'] = (float)$st->fetchColumn();
        } else {
            // Governmental
            // Yearly remaining from yearly row (monthly_amount=0)
            $stY = $pdo->prepare("SELECT remaining_yearly
                                  FROM budgets
                                  WHERE owner_id=? AND code_id=? AND year=? AND budget_type='governmental' AND monthly_amount=0
                                  ORDER BY id DESC LIMIT 1");
            $stY->execute([$owner_id, (int)$code_id, $year]);
            $resp['yearlyAvailable'] = (float)($stY->fetchColumn() ?: 0);

            // Monthly remaining by ec_month (canonical)
            $ecm = ecMonthNoFromString($month);
            $stM = $pdo->prepare("SELECT COALESCE(SUM(remaining_monthly),0)
                                  FROM budgets
                                  WHERE owner_id=? AND code_id=? AND year=? AND budget_type='governmental'
                                        AND monthly_amount>0 AND ec_month=?");
            $stM->execute([$owner_id, (int)$code_id, $year, $ecm]);
            $resp['monthlyAvailable'] = (float)$stM->fetchColumn();
        }

        echo json_encode($resp);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// JSON endpoint: Budgets listing
if (isset($_GET['action']) && $_GET['action'] === 'budgets') {
    header('Content-Type: application/json');
    try {
        $budget_type = $_GET['budget_type'] ?? 'governmental';
        $owner_id    = (int)($_GET['owner_id'] ?? 0);
        $code_id_raw = $_GET['code_id'] ?? '';
        $has_code    = ($code_id_raw !== '');
        $code_id     = $has_code ? (int)$code_id_raw : null;

        $q = "SELECT b.id, b.budget_type, b.program_name,
                     COALESCE(go.code, po.code) AS owner_code,
                     COALESCE(go.name, po.name) AS owner_name,
                     c.code AS budget_code, c.name AS budget_name,
                     b.month, b.ec_month, b.monthly_amount, b.yearly_amount, b.remaining_yearly
              FROM budgets b
              LEFT JOIN budget_owners     go ON (b.owner_id = go.id AND b.budget_type='governmental')
              LEFT JOIN p_budget_owners   po ON (b.owner_id = po.id AND b.budget_type='program')
              LEFT JOIN budget_codes       c ON b.code_id  = c.id
              WHERE b.budget_type = ?";
        $p = [$budget_type];

        if ($owner_id > 0) { $q .= " AND b.owner_id = ?"; $p[] = $owner_id; }
        if ($has_code)     { $q .= " AND b.code_id  = ?"; $p[] = $code_id; }

        $q .= " ORDER BY b.adding_date DESC, b.id DESC LIMIT 500";
        $st = $pdo->prepare($q);
        $st->execute($p);
        $rows = $st->fetchAll();

        echo json_encode(['ok'=>true, 'rows'=>$rows]);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Load owners and codes for initial render
$gov_owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$prog_owners = $pdo->query("SELECT id, code, name FROM p_budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$codes  = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

// State
$selected_owner_id  = $_POST['owner_id'] ?? ($_GET['owner_id'] ?? null);
$selected_code_id   = $_POST['code_id']  ?? ($_GET['code_id']  ?? null);
$selected_type      = $_POST['budget_type'] ?? ($_GET['budget_type'] ?? 'governmental'); // governmental | program

// Edit record
$budget = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($budget) $selected_type = $budget['budget_type'];
}

// Handle POST (Create/Update/Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $_SESSION['flash_error'] = 'Bad CSRF token.';
        header('Location: budget_adding.php'); exit;
    }

    // DELETE (via POST form)
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $del_id = (int)($_POST['id'] ?? 0);
        if ($del_id <= 0) { $_SESSION['flash_error'] = 'Invalid delete request.'; header('Location: budget_adding.php'); exit; }

        $stmt = $pdo->prepare("SELECT owner_id, code_id, year, monthly_amount, remaining_yearly, budget_type, month, ec_month FROM budgets WHERE id = ?");
        $stmt->execute([$del_id]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();
        try {
            if ($b && $b['budget_type'] === 'governmental' && (float)$b['monthly_amount'] > 0) {
                // Restore the yearly remaining
                $stmt = $pdo->prepare("UPDATE budgets
                                       SET remaining_yearly = remaining_yearly + ?
                                       WHERE owner_id = ? AND code_id = ? AND year = ? AND monthly_amount = 0 AND budget_type='governmental'");
                $stmt->execute([$b['monthly_amount'], $b['owner_id'], $b['code_id'], $b['year']]);
            }
            $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
            $stmt->execute([$del_id]);

            $pdo->commit();
            $_SESSION['flash_success'] = 'Budget deleted successfully';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'Error deleting budget: ' . $e->getMessage();
        }
        header('Location: budget_adding.php?owner_id='.(int)$b['owner_id'].'&budget_type='.urlencode($b['budget_type']).($b['code_id']?'&code_id='.(int)$b['code_id']:'')); exit;
    }

    // CREATE/UPDATE
    $owner_id      = (int)($_POST['owner_id'] ?? 0);
    $code_id       = isset($_POST['code_id']) && $_POST['code_id'] !== '' ? (int)$_POST['code_id'] : null;
    $adding_date   = $_POST['adding_date'] ?? date('Y-m-d');
    $budget_type   = ($_POST['budget_type'] ?? 'governmental') === $BUDGET_TYPE_PROGRAM ? $BUDGET_TYPE_PROGRAM : 'governmental';
    $program_name  = trim($_POST['program_name'] ?? '');
    $month         = $_POST['month'] ?? '';
    $ec_month      = ecMonthNoFromString($month);
    $yearly_amount = (float)($_POST['yearly_amount'] ?? 0);
    $monthly_amount= (float)($_POST['monthly_amount'] ?? 0);

    // EC year
    $etInfo = function_exists('getEtMonthAndQuarter') ? getEtMonthAndQuarter($adding_date) : null;
    $year   = $etInfo['year'] ?? (date('Y', strtotime($adding_date)) - 8);
    $quarter= $budget_type === 'governmental' ? ($quarterMap[$month] ?? 0) : 0;

    if (!$owner_id) {
        $_SESSION['flash_error'] = 'Please select Budget Owner.';
        header('Location: budget_adding.php'); exit;
    }

    $pdo->beginTransaction();
    try {
        if (isset($_POST['id']) && ($_POST['action'] ?? '') === 'update') {
            $id = (int)$_POST['id'];
            if ($budget_type === $BUDGET_TYPE_PROGRAM) {
                // Programs: yearly-only
                $stmt = $pdo->prepare("UPDATE budgets
                    SET owner_id=?, code_id=?, adding_date=?, year=?, yearly_amount=?, month=?, ec_month=?, monthly_amount=?, quarter=?,
                        remaining_yearly=?, remaining_monthly=?, remaining_quarterly=?, budget_type=?, program_name=?, is_yearly=?
                    WHERE id=?");
                $stmt->execute([
                    $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                    '', null, 0, 0,
                    $yearly_amount, 0, 0,
                    $budget_type, ($program_name ?: null), 1,
                    $id
                ]);
                $_SESSION['flash_success'] = 'Program yearly budget updated successfully';
            } else {
                // Governmental (update a single row as-is)
                $stmt = $pdo->prepare("UPDATE budgets
                    SET owner_id=?, code_id=?, adding_date=?, year=?, yearly_amount=?, month=?, ec_month=?, monthly_amount=?, quarter=?, budget_type=?, program_name=?
                    WHERE id=?");
                $stmt->execute([
                    $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                    $month, $ec_month, $monthly_amount, $quarter,
                    'governmental', null, $id
                ]);
                $_SESSION['flash_success'] = 'Government budget updated successfully';
            }
            $pdo->commit();
            header('Location: budget_adding.php?owner_id='.$owner_id.'&budget_type='.$budget_type.($code_id?'&code_id='.$code_id:'')); exit;
        }

        // CREATE
        if ($budget_type === $BUDGET_TYPE_PROGRAM) {
            // Programs: Yearly only
            if ($monthly_amount > 0) throw new Exception('Programs budget does not allow Monthly Amount.');
            if ($yearly_amount <= 0) throw new Exception('Enter a valid Yearly Amount for programs budget.');

            // Prevent duplicates (owner,[code],year)
            $sql = "SELECT id FROM budgets
                    WHERE owner_id=? AND year=? AND budget_type=? AND monthly_amount=0";
            $p   = [$owner_id, $year, $BUDGET_TYPE_PROGRAM];
            if ($code_id !== null) { $sql .= " AND code_id = ?"; $p[] = $code_id; }
            else { $sql .= " AND code_id IS NULL"; }
            $chk = $pdo->prepare($sql);
            $chk->execute($p);
            if ($chk->fetch()) throw new Exception('Program yearly budget already exists for this Owner/Code/Year.');

            $stmt = $pdo->prepare("INSERT INTO budgets
                (owner_id, code_id, adding_date, year, yearly_amount, month, ec_month, monthly_amount, quarter,
                 remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type, program_name)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                '', null, 0, 0,
                $yearly_amount, 0, 0,
                1, $BUDGET_TYPE_PROGRAM, ($program_name ?: null)
            ]);
            $_SESSION['flash_success'] = 'Program yearly budget added successfully.';
        } else {
            // Governmental — Yearly + Monthly in same submit if provided
            if (!$code_id) throw new Exception('Select Budget Code for Government budgets.');
            
            // Check for existing yearly budget
            $stmt = $pdo->prepare("SELECT id, yearly_amount, remaining_yearly FROM budgets 
                                  WHERE owner_id=? AND code_id=? AND year=? AND monthly_amount=0 AND budget_type='governmental'");
            $stmt->execute([$owner_id, $code_id, $year]);
            $existing_yearly = $stmt->fetch(PDO::FETCH_ASSOC);

            $didYearly = false;
            $didMonthly = false;

            // Handle Yearly Budget - FIXED: Prevent duplicate yearly budgets
            if ($yearly_amount > 0) {
                if ($existing_yearly) {
                    throw new Exception('Yearly budget already exists for this Owner + Code + Year. You cannot add another yearly budget. Use edit to update the existing one.');
                } else {
                    // Check again to ensure no duplicate
                    $stmt = $pdo->prepare("SELECT id FROM budgets 
                                          WHERE owner_id=? AND code_id=? AND year=? AND monthly_amount=0 AND budget_type='governmental'");
                    $stmt->execute([$owner_id, $code_id, $year]);
                    if ($stmt->fetch()) throw new Exception('Yearly budget already exists for this Owner + Code + Year (government).');

                    $insY = $pdo->prepare("INSERT INTO budgets
                        (owner_id, code_id, adding_date, year, yearly_amount, month, ec_month, monthly_amount, quarter,
                         remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    $insY->execute([
                        $owner_id, $code_id, $adding_date, $year, $yearly_amount,
                        '', null, 0, 0,
                        $yearly_amount, 0, 0,
                        1, 'governmental'
                    ]);
                    $existing_yearly = [
                        'id' => $pdo->lastInsertId(),
                        'yearly_amount' => $yearly_amount,
                        'remaining_yearly' => $yearly_amount
                    ];
                    $didYearly = true;
                }
            }

            // Handle Monthly Budget - FIXED: Allow monthly without yearly amount if yearly budget exists
            if ($monthly_amount > 0) {
                if (!$existing_yearly) {
                    throw new Exception('No yearly budget exists for this Owner + Code + Year. Please add yearly budget first or provide yearly amount.');
                }
                
                if ($monthly_amount > (float)$existing_yearly['remaining_yearly']) {
                    throw new Exception('Monthly amount ('.number_format($monthly_amount, 2).') exceeds remaining yearly budget ('.number_format($existing_yearly['remaining_yearly'], 2).').');
                }
                
                if (!$ec_month) throw new Exception('Invalid EC month.');

                // Check if monthly budget already exists for this month
                $stmt = $pdo->prepare("SELECT id FROM budgets 
                                      WHERE owner_id=? AND code_id=? AND year=? AND ec_month=? AND monthly_amount>0 AND budget_type='governmental'");
                $stmt->execute([$owner_id, $code_id, $year, $ec_month]);
                if ($stmt->fetch()) throw new Exception('Monthly budget already exists for this Owner + Code + Year + Month.');

                $quarter = $quarterMap[$month] ?? 0;
                $new_remaining_yearly = (float)$existing_yearly['remaining_yearly'] - $monthly_amount;

                $insM = $pdo->prepare("INSERT INTO budgets
                    (owner_id, code_id, adding_date, year, yearly_amount, month, ec_month, monthly_amount, quarter,
                     remaining_yearly, remaining_monthly, remaining_quarterly, is_yearly, budget_type)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $insM->execute([
                    $owner_id, $code_id, $adding_date, $year, 0,
                    $month, $ec_month, $monthly_amount, $quarter,
                    $new_remaining_yearly, $monthly_amount, $monthly_amount * 3,
                    0, 'governmental'
                ]);
                
                // Update the yearly budget's remaining amount
                $upd = $pdo->prepare("UPDATE budgets SET remaining_yearly=? WHERE id=?");
                $upd->execute([$new_remaining_yearly, $existing_yearly['id']]);
                $didMonthly = true;
            }

            if (!$didYearly && !$didMonthly) throw new Exception('Please enter a Yearly or Monthly amount.');
            
            if ($didYearly && $didMonthly) {
                $_SESSION['flash_success'] = 'Yearly + Monthly governmental budgets added successfully.';
            } elseif ($didYearly) {
                $_SESSION['flash_success'] = 'Yearly governmental budget added successfully.';
            } else {
                $_SESSION['flash_success'] = 'Monthly governmental budget added successfully.';
            }
        }

        $pdo->commit();
        header('Location: budget_adding.php?owner_id='.$owner_id.'&budget_type='.$budget_type.($code_id?'&code_id='.$code_id:'')); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Error: ' . $e->getMessage();
        header('Location: budget_adding.php'); exit;
    }
}

// Flash message handling for modern alerts
$flash_message = $_SESSION['flash_success'] ?? $_SESSION['flash_error'] ?? null;
$flash_type = isset($_SESSION['flash_success']) ? 'success' : (isset($_SESSION['flash_error']) ? 'error' : null);
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<?php include 'includes/head.php'; ?>
    <?php
    $pageTitle = 'Budget Management';
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

        /* Modern input styles */
        .modern-input {
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 0.875rem 1rem;
            transition: all 0.3s ease;
            background: white;
            width: 100%;
        }

        .modern-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        /* Enhanced card styles */
        .modern-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        /* Availability cards */
        .availability-card {
            border-radius: 16px;
            padding: 1.5rem;
            color: white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .availability-card:hover {
            transform: scale(1.02);
        }

        /* Table styles */
        .table-modern {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

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

        .table-modern tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Button styles */
        .btn-modern {
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
        }

        /* Enhanced Select2 Styling - Matching perdium.php */
        .select2-container--default .select2-selection--single {
            height: 46px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .select2-container--default .select2-selection--single:hover {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 44px;
            padding-left: 16px;
            color: #374151;
            font-weight: 500;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px;
            right: 12px;
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #4f46e5;
            color: white;
        }

        .select2-container--default .select2-results__option {
            padding: 8px 12px !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        .select2-dropdown {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
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
                .table-modern {
                min-width: 800px;
            }
        }

        /* Content Container */
        .content-container {
            padding: 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        /* Responsive design */
        

        /* Info box for monthly budget */
        .info-box {
            background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .info-box.warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);
            border: 1px solid #fcd34d;
        }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-50 min-h-screen">
	<?php require_once 'includes/sidebar-component.php'; ?>
 
    <!-- Main Content -->
    <div class="main-content flex-1 min-h-screen" id="mainContent">
    	<?php require_once 'includes/header.php'; ?>
        <div class="p-6">
           <!-- Enhanced Flash Messaging System -->
            <?php if ($flash_message): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const message = <?php echo json_encode($flash_message); ?>;
                    const messageType = <?php echo json_encode($flash_type); ?>;

                    showRegularFlashMessage(message, messageType);

                    function showRegularFlashMessage(message, type) {
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

                        const config = toastConfigs[type] || toastConfigs.info;

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
                    }
                });
            </script>
            <?php endif; ?>

            <!-- Budget Form -->
            <div class="bg-white rounded-2xl p-8 shadow-xl mb-8 border border-gray-100 modern-card">
                <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center">
                    <i class="fas fa-plus-circle mr-3 text-blue-500"></i>
                    <?php echo $budget ? 'Edit Budget' : 'Add New Budget'; ?>
                </h2>
                
                <!-- Information Box -->
                <div class="info-box mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-500 text-lg mt-1 mr-3"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-blue-800 mb-1">Budget Adding Information</h4>
                            <p class="text-sm text-blue-700">
                                <strong>Government Budgets:</strong> You can add monthly budgets to existing yearly budgets by entering only the monthly amount. 
                                The system will automatically deduct from the available yearly budget.
                            </p>
                        </div>
                    </div>
                </div>

                <form method="post" class="space-y-6" id="budgetForm">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                    <?php if ($budget): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$budget['id']; ?>">
                        <input type="hidden" name="action" value="update">
                    <?php endif; ?>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Budget Source Type -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Source Type *</label>
                            <select name="budget_type" id="budget_type" class="w-full select2 modern-input" required>
                                <option value="governmental" <?php echo ($selected_type==='governmental' ? 'selected':''); ?>>Government Budget</option>
                                <option value="<?php echo $BUDGET_TYPE_PROGRAM; ?>" <?php echo ($selected_type===$BUDGET_TYPE_PROGRAM ? 'selected':''); ?>>Programs Budget</option>
                            </select>
                            <small class="text-slate-500 text-sm mt-2 block">
                                <i class="fas fa-info-circle mr-1"></i>
                                Government: monthly + yearly. Programs: yearly only.
                            </small>
                        </div>

                        <!-- Program Name -->
                        <div id="program_name_box" style="display:none;">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Program Name (NGO/Partner)</label>
                            <input type="text" name="program_name" class="modern-input" placeholder="e.g., Global Fund, UNDP, World Bank"
                                   value="<?php echo $budget ? htmlspecialchars($budget['program_name'] ?? '') : ''; ?>">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Adding Date -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Adding Date *</label>
                            <input type="date" id="adding_date" name="adding_date" class="modern-input"
                                   value="<?php echo $budget ? htmlspecialchars(substr($budget['adding_date'],0,10)) : date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Budget Owner -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Owner *</label>
                            <select name="owner_id" id="owner_id" class="w-full select2 modern-input" required>
                                <option value="">-- Select Owner --</option>
                                <?php
                                if ($selected_type === $BUDGET_TYPE_PROGRAM) {
                                    foreach ($prog_owners as $o): ?>
                                        <option value="<?php echo (int)$o['id']; ?>" <?php
                                        $sel = $budget ? ((int)$budget['owner_id']===(int)$o['id']) : ((int)$selected_owner_id===(int)$o['id']);
                                        echo $sel ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                                        </option>
                                    <?php endforeach;
                                } else {
                                    foreach ($gov_owners as $o): ?>
                                        <option value="<?php echo (int)$o['id']; ?>" <?php
                                        $sel = $budget ? ((int)$budget['owner_id']===(int)$o['id']) : ((int)$selected_owner_id===(int)$o['id']);
                                        echo $sel ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($o['code'] . ' - ' . $o['name']); ?>
                                        </option>
                                    <?php endforeach;
                                }
                                ?>
                            </select>
                            <small class="text-slate-500 text-sm mt-2 block">
                                Selected Owner: <span id="selected_owner" class="font-medium">
                                    <?php
                                    if ($budget) {
                                        if ($budget['budget_type'] === $BUDGET_TYPE_PROGRAM) {
                                            $found = array_filter($prog_owners, fn($x)=> (int)$x['id']===(int)$budget['owner_id']);
                                        } else {
                                            $found = array_filter($gov_owners, fn($x)=> (int)$x['id']===(int)$budget['owner_id']);
                                        }
                                        echo $found ? htmlspecialchars(array_values($found)[0]['name']) : '';
                                    }
                                    ?>
                                </span>
                            </small>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Budget Code -->
                        <div id="code_box">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Code</label>
                            <select name="code_id" id="code_id" class="w-full select2 modern-input">
                                <option value="">-- No code (General) --</option>
                                <?php foreach ($codes as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php
                                    $sel = $budget ? ((int)$budget['code_id']===(int)$c['id']) : ((int)$selected_code_id===(int)$c['id']);
                                    echo $sel ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-slate-500 text-sm mt-2 block">
                                Selected Code: <span id="selected_code" class="font-medium">
                                    <?php
                                    if ($budget && $budget['code_id']) {
                                        $found = array_filter($codes, fn($x)=> (int)$x['id']===(int)$budget['code_id']);
                                        echo $found ? htmlspecialchars(array_values($found)[0]['name']) : '—';
                                    } else echo '—';
                                    ?>
                                </span>
                            </small>
                            <small id="code_required_hint" class="text-xs text-amber-600 mt-1 block" style="display:none;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Required for Government budgets
                            </small>
                        </div>

                        <!-- Month Selection -->
                        <div id="month_box">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Select Month (EC)</label>
                            <select id="month" name="month" class="w-full select2 modern-input">
                                <?php foreach ($EC_MONTHS_EN as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m); ?>" <?php
                                    $sel = $budget ? ($budget['month']===$m) : false;
                                    echo $sel ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Monthly Amount -->
                        <div id="monthly_amount_box">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Monthly Amount</label>
                            <input type="number" step="0.01" name="monthly_amount" id="monthly_amount" class="modern-input"
                                   value="<?php echo $budget ? htmlspecialchars($budget['monthly_amount']) : ''; ?>" placeholder="0.00">
                            <small class="text-slate-500 text-sm mt-2 block">
                                <i class="fas fa-lightbulb mr-1"></i>
                                You can add monthly budget without yearly amount if yearly budget already exists
                            </small>
                        </div>

                        <!-- Yearly Amount -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Yearly Amount</label>
                            <input type="number" step="0.01" name="yearly_amount" id="yearly_amount" class="modern-input"
                                   value="<?php echo $budget ? htmlspecialchars($budget['yearly_amount']) : ''; ?>" placeholder="0.00">
                            <small class="text-slate-500 text-sm mt-2 block">
                                <i class="fas fa-lightbulb mr-1"></i>
                                Leave empty if only adding monthly budget to existing yearly budget
                            </small>
                        </div>
                    </div>

                    <!-- Availability Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="availability_panel">
                        <div id="avail_monthly_card" class="availability-card bg-gradient-to-r from-amber-500 to-orange-500">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-amber-100 font-medium mb-1">
                                        <i class="fas fa-calendar-alt mr-2"></i>Available Monthly Budget
                                    </div>
                                    <div id="avail_monthly" class="text-2xl font-bold text-white">—</div>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-calendar text-xl text-white"></i>
                                </div>
                            </div>
                        </div>
                        <div class="availability-card bg-gradient-to-r from-emerald-500 to-green-600">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm text-emerald-100 font-medium mb-1">
                                        <i class="fas fa-coins mr-2"></i>Available Yearly Budget
                                    </div>
                                    <div id="avail_yearly" class="text-2xl font-bold text-white">—</div>
                                </div>
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-piggy-bank text-xl text-white"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 pt-4">
                        <button type="button" class="btn-modern btn-info" onclick="window.print()">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
                        <button type="submit" class="btn-modern btn-primary">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo $budget ? 'Update Budget' : 'Save Budget'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Budgets Table -->
            <div class="bg-white rounded-2xl p-8 shadow-xl border border-gray-100 modern-card">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 mb-2 flex items-center">
                            <i class="fas fa-list-alt mr-3 text-blue-500"></i>Existing Budgets
                        </h2>
                        <p class="text-slate-600">Filtered by Budget Source Type (and Owner/Code if selected)</p>
                    </div>
                    <div class="text-lg font-semibold text-slate-700 mt-4 md:mt-0" id="budget_count">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading…
                    </div>
                </div>

                <div class="table-responsive overflow-x-auto custom-scrollbar">
                    <table class="min-w-full divide-y divide-gray-200 table-modern">
                        <thead>
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Type</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Program</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Directorates/Programs</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Budget Codes</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Month</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Monthly Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Yearly Amount</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Remaining Yearly</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-white uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="budgets_tbody">
                            <tr>
                                <td class="px-6 py-4 text-center text-sm text-gray-500" colspan="10">
                                    <div class="flex flex-col items-center justify-center py-8">
                                        <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                                        <span>Loading budgets...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function formatMoney(n){ 
            return (Number(n)||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); 
        }
        
        function escapeHtml(s){ 
            return (s??'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); 
        }

        function fetchAndPopulateOwners() {
            const budgetType = $('#budget_type').val();
            const ownerSelect = $('#owner_id');

            const budgetOwnerId   = '<?php echo $budget['owner_id']   ?? ''; ?>';
            const budgetBudgetType= '<?php echo $budget['budget_type']?? ''; ?>';

            ownerSelect.prop('disabled', true).html('<option value="">Loading...</option>').trigger('change.select2');

            $.ajax({
                url: 'ajax_get_owners.php',
                type: 'GET',
                data: { budget_type: budgetType },
                dataType: 'json',
                success: function(response) {
                    if (response.success && Array.isArray(response.owners)) {
                        ownerSelect.html('<option value="">Select Owner</option>');
                        response.owners.forEach(function(owner) {
                            const option = new Option(`${owner.code} - ${owner.name}`, owner.id);
                            ownerSelect.append(option);
                        });
                        if (budgetOwnerId && budgetBudgetType === budgetType) {
                            ownerSelect.val(budgetOwnerId);
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
                    ownerSelect.trigger('change');
                }
            });
        }

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

            // Enhanced Select2 initialization matching perdium.php
            $('.select2').select2({ 
                theme: 'classic',
                width: '100%',
                dropdownCssClass: 'rounded-xl shadow-xl border border-gray-200',
                matcher: function(params, data) {
                    if ($.trim(params.term) === '') return data;
                    if (typeof data.text === 'undefined') return null;

                    // Enhanced search for dropdowns
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
                    for (const key in data.element.dataset) {
                        if (String(data.element.dataset[key]).toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
                    }
                    return null;
                }
            });

            // Fixed: Add proper event handlers for all dropdowns
            $('#budget_type').on('change', function() {
                toggleBudgetType(); 
                fetchAndPopulateOwners(); 
                refreshAvailability(); 
                fetchBudgets();
            });

            $('#owner_id').on('change', function() {
                updateSelectedOwner(this); 
                refreshAvailability(); 
                fetchBudgets();
            });

            $('#code_id').on('change', function() {
                updateSelectedCode(this); 
                refreshAvailability(); 
                fetchBudgets();
            });

            $('#month').on('change', function() {
                updateQuarter(); 
                refreshAvailability();
            });

            $('#adding_date').on('change', refreshAvailability);

            toggleBudgetType();
            refreshAvailability();
            fetchBudgets();
        });

        function updateSelectedOwner(select) {
            const selectedOption = $(select).find('option:selected');
            const ownerName = selectedOption.length && selectedOption.text().includes(' - ')
                ? selectedOption.text().split(' - ')[1] : '';
            const el = document.getElementById('selected_owner');
            if (el) el.textContent = ownerName;
        }

        function updateSelectedCode(select) {
            const selectedOption = $(select).find('option:selected');
            let codeName = '—';
            if (selectedOption.length && selectedOption.val() !== '' && selectedOption.text().includes(' - ')) {
                codeName = selectedOption.text().split(' - ')[1];
            }
            const el = document.getElementById('selected_code');
            if (el) el.textContent = codeName;
        }

        function updateQuarter() {
            // Quarter calculation if needed
            // Currently quarter is calculated in backend
        }

        function toggleBudgetType() {
            const typeEl = document.getElementById('budget_type');
            const type = typeEl ? typeEl.value : 'governmental';

            const programBox = document.getElementById('program_name_box');
            const codeBox   = document.getElementById('code_box');
            const monthBox   = document.getElementById('month_box');
            const mAmtBox    = document.getElementById('monthly_amount_box');
            const codeEl     = document.getElementById('code_id');
            const codeHint   = document.getElementById('code_required_hint');
            const monthlyAvailCard = document.getElementById('avail_monthly_card');

            if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
                programBox.style.display = 'block';
                codeBox.style.display    = 'none';
                monthBox.style.display   = 'none';
                mAmtBox.style.display    = 'none';
                monthlyAvailCard.style.display = 'none';
                codeEl.required = false;
                codeHint.style.display = 'none';
                document.getElementById('monthly_amount').value = '';
                // Make yearly amount required for programs
                document.getElementById('yearly_amount').required = true;
            } else {
                programBox.style.display = 'none';
                codeBox.style.display    = 'block';
                monthBox.style.display   = 'block';
                mAmtBox.style.display    = 'block';
                monthlyAvailCard.style.display = 'block';
                codeEl.required = true;
                codeHint.style.display = 'block';
                // Make yearly amount optional for governmental (can add monthly only)
                document.getElementById('yearly_amount').required = false;
            }
        }

        async function refreshAvailability() {
            const type  = document.getElementById('budget_type')?.value || 'governmental';
            const owner = $('#owner_id').val() || '';
            const code  = $('#code_id').val() ?? '';
            const month = $('#month').val() || '';
            const adding_date = document.getElementById('adding_date')?.value || new Date().toISOString().slice(0,10);

            if (!owner) {
                document.getElementById('avail_yearly').textContent = '—';
                const am = document.getElementById('avail_monthly'); 
                if (am) am.textContent = '—';
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('action','availability');
            url.searchParams.set('budget_type', type);
            url.searchParams.set('owner_id', owner);
            url.searchParams.set('code_id', code);
            url.searchParams.set('adding_date', adding_date);
            url.searchParams.set('month', month);

            try {
                const r = await fetch(url.toString(), { cache: 'no-store' });
                const j = await r.json();
                if (!j.ok) throw new Error(j.error || 'fetch failed');

                document.getElementById('avail_yearly').textContent = formatMoney(j.yearlyAvailable) + ' ETB';
                const monthlyCard = document.getElementById('avail_monthly_card');
                const monthlyVal  = document.getElementById('avail_monthly');
                if (type === '<?php echo $BUDGET_TYPE_PROGRAM; ?>') {
                    monthlyCard.style.display = 'none';
                } else {
                    monthlyCard.style.display = 'block';
                    monthlyVal.textContent = formatMoney(j.monthlyAvailable) + ' ETB';
                }
            } catch (e) {
                document.getElementById('avail_yearly').textContent = '—';
                const am = document.getElementById('avail_monthly'); 
                if (am) am.textContent = '—';
            }
        }

        async function fetchBudgets() {
            const type  = document.getElementById('budget_type')?.value || 'governmental';
            const owner = $('#owner_id').val() || '';
            const code  = $('#code_id').val() ?? '';

            const url = new URL(window.location.href);
            url.searchParams.set('action','budgets');
            url.searchParams.set('budget_type', type);
            if (owner) url.searchParams.set('owner_id', owner); else url.searchParams.delete('owner_id');
            if (code !== '') url.searchParams.set('code_id', code); else url.searchParams.delete('code_id');

            const tbody = document.getElementById('budgets_tbody');
            const count = document.getElementById('budget_count');
            tbody.innerHTML = `<tr><td class="px-6 py-4 text-center text-sm text-gray-500" colspan="10">
                <div class="flex flex-col items-center justify-center py-4">
                    <i class="fas fa-spinner fa-spin text-2xl text-blue-500 mb-2"></i>
                    <span>Loading budgets...</span>
                </div>
            </td></tr>`;
            count.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading…';

            try {
                const r = await fetch(url.toString(), { cache: 'no-store' });
                const j = await r.json();
                if (!j.ok) throw new Error(j.error || 'fetch failed');

                const rows = j.rows || [];
                count.innerHTML = `<i class="fas fa-database mr-2"></i>${rows.length} budget(s) found`;

                if (rows.length === 0) {
                    tbody.innerHTML = `<tr><td class="px-6 py-8 text-center text-sm text-gray-500" colspan="10">
                        <div class="flex flex-col items-center justify-center py-4">
                            <i class="fas fa-inbox text-3xl text-gray-300 mb-2"></i>
                            <span>No budgets found for current filter.</span>
                        </div>
                    </td></tr>`;
                    return;
                }

                let html = '';
                rows.forEach(b => {
                    const id = Number(b.id)||0;
                    const type = escapeHtml(b.budget_type||'');
                    const prog = escapeHtml(b.program_name||'—');
                    const owner = `${escapeHtml(b.owner_code||'—')} - ${escapeHtml(b.owner_name||'')}`;
                    const code  = `${escapeHtml(b.budget_code||'—')} - ${escapeHtml(b.budget_name||'')}`;
                    const month = escapeHtml(b.month||'');
                    const mAmt  = formatMoney(b.monthly_amount||0);
                    const yAmt  = formatMoney(b.yearly_amount||0);
                    const remYearly = formatMoney(b.remaining_yearly||0);

                    html += `
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">${id}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${type === 'program' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                                    ${type}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">${prog}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">${owner}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">${code}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 font-medium">${month}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 font-bold">${mAmt} ETB</td>
                            <td class="px-6 py-4 text-sm text-gray-900 font-bold">${yAmt} ETB</td>
                            <td class="px-6 py-4 text-sm text-gray-900 font-bold ${(b.remaining_yearly || 0) < 0 ? 'text-red-600' : ''}">${remYearly} ETB</td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex space-x-2">
                                    <a href="?action=edit&id=${id}" class="px-3 py-2 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all duration-200 shadow-sm flex items-center text-xs">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </a>
                                    <form method="POST" style="display:inline" onsubmit="return confirmDeleteBudget(this)">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="${id}">
                                        <button type="submit" class="px-3 py-2 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-lg hover:from-red-600 hover:to-pink-700 transition-all duration-200 shadow-sm flex items-center text-xs">
                                            <i class="fas fa-trash mr-1"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } catch (e) {
                tbody.innerHTML = `<tr><td class="px-6 py-8 text-center text-sm text-gray-500" colspan="10">
                    <div class="flex flex-col items-center justify-center py-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-500 mb-2"></i>
                        <span>Failed to load budgets.</span>
                    </div>
                </td></tr>`;
                count.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Error';
            }
        }

        function confirmDeleteBudget(form) {
            event.preventDefault();
            
            Swal.fire({
                title: '<div class="flex items-center justify-center mb-4"><i class="fas fa-trash-alt text-4xl text-red-500 mr-3"></i><span class="text-2xl font-bold text-gray-800">Confirm Deletion</span></div>',
                html: `
                <div class="text-center py-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-2xl text-red-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Are you sure you want to delete this budget?</h3>
                    <p class="text-gray-600 mb-4">This action cannot be undone and will permanently remove the budget from the system.</p>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-3 mt-4">
                        <p class="text-sm text-red-700 flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            This will also affect any remaining budget allocations.
                        </p>
                    </div>
                </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '<i class="fas fa-trash mr-2"></i>Yes, Delete It',
                cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancel',
                background: '#fff',
                customClass: {
                    popup: 'rounded-2xl shadow-2xl border border-gray-200',
                    confirmButton: 'px-6 py-3 rounded-lg font-semibold',
                    cancelButton: 'px-6 py-3 rounded-lg font-semibold'
                },
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
            
            return false;
        }
    </script>
</body>
</html>