<?php
ini_set('display_errors', 1);


// Dashboard Page with Filters

// --- Includes & Authentication ---
require_once 'includes/init.php';

// Require login to access this page.
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// --- Helper Functions ---

/**
 * Escapes special characters for safe HTML output.
 */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a number to 2 decimal places.
 */
function fmt($n) {
    return number_format((float)$n, 2);
}

// --- Data Fetching and Filtering Logic ---

// Fetch the current user's display name.
$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$display_name = $user_data['name'] ?? ($_SESSION['username'] ?? 'User');

// Get filter values from GET request.
$filter_owner = isset($_GET['owner_id']) && $_GET['owner_id'] !== '' ? (int)$_GET['owner_id'] : 0;
$filter_code = isset($_GET['code_id']) && $_GET['code_id'] !== '' ? (int)$_GET['code_id'] : 0;
$filter_start = $_GET['start'] ?? '';
$filter_end = $_GET['end'] ?? '';

// Normalize dates to Y-m-d format for queries.
$start_dt = $filter_start ? date('Y-m-d', strtotime($filter_start)) : '';
$end_dt = $filter_end ? date('Y-m-d', strtotime($filter_end)) : '';

// Fetch lists for filter dropdowns.
$owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$codes = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

// --- Prepare Shared Query Filters ---

$params = [];
$where = [];

if ($filter_owner > 0) {
    $where[] = "owner_id = ?";
    $params[] = $filter_owner;
}
if ($filter_code > 0) {
    $where[] = "code_id = ?";
    $params[] = $filter_code;
}
if ($start_dt) {
    $where[] = "date >= ?";
    $params[] = $start_dt;
}
if ($end_dt) {
    $where[] = "date <= ?";
    $params[] = $end_dt;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// --- Fetching and Merging Data Sources ---

// Budgets/Allocations per owner
$allocRows = [];
$sqlAlloc = "SELECT owner_id, SUM(yearly_amount + monthly_amount) AS alloc FROM budgets $where_clause GROUP BY owner_id";
$stAlloc = $pdo->prepare($sqlAlloc);
$stAlloc->execute($params);
while ($r = $stAlloc->fetch(PDO::FETCH_ASSOC)) {
    $allocRows[(int)$r['owner_id']] = (float)$r['alloc'];
}

// Used sums from general transactions
$usedTrans = [];
$sqlTrans = "SELECT owner_id, SUM(amount) AS used FROM transactions $where_clause GROUP BY owner_id";
$stTrans = $pdo->prepare($sqlTrans);
$stTrans->execute($params);
while ($r = $stTrans->fetch(PDO::FETCH_ASSOC)) {
    $usedTrans[(int)$r['owner_id']] = (float)$r['used'];
}

// Per diem sums (only include if no code filter OR code=6)
$usedPerdiem = [];
$includeP = ($filter_code === 0 || $filter_code === 6);
if ($includeP) {
    $pWhere = [];
    $pParams = [];
    if ($filter_owner > 0) {
        $pWhere[] = "budget_owner_id = ?";
        $pParams[] = $filter_owner;
    }
    if ($start_dt) {
        $pWhere[] = "created_at >= ?";
        $pParams[] = $start_dt;
    }
    if ($end_dt) {
        $pWhere[] = "created_at <= ?";
        $pParams[] = $end_dt;
    }
    $pWhereSql = $pWhere ? ('WHERE '.implode(' AND ', $pWhere)) : '';
    $stP = $pdo->prepare("SELECT budget_owner_id AS owner_id, SUM(total_amount) AS used FROM perdium_transactions $pWhereSql GROUP BY budget_owner_id");
    $stP->execute($pParams);
    while ($r = $stP->fetch(PDO::FETCH_ASSOC)) {
        $usedPerdiem[(int)$r['owner_id']] = (float)$r['used'];
    }
}

// Fuel sums (only include if no code filter OR code=5)
$usedFuel = [];
$includeF = ($filter_code === 0 || $filter_code === 5);
if ($includeF) {
    $fWhere = [];
    $fParams = [];
    if ($filter_owner > 0) {
        $fWhere[] = "owner_id = ?";
        $fParams[] = $filter_owner;
    }
    if ($start_dt) {
        $fWhere[] = "date >= ?";
        $fParams[] = $start_dt;
    }
    if ($end_dt) {
        $fWhere[] = "date <= ?";
        $fParams[] = $end_dt;
    }
    $fWhereSql = $fWhere ? ('WHERE '.implode(' AND ', $fWhere)) : '';
    $stF = $pdo->prepare("SELECT owner_id, SUM(total_amount) AS used FROM fuel_transactions $fWhereSql GROUP BY owner_id");
    $stF->execute($fParams);
    while ($r = $stF->fetch(PDO::FETCH_ASSOC)) {
        $usedFuel[(int)$r['owner_id']] = (float)$r['used'];
    }
}

// Merge per-owner allocations and used
$directorate_data = [];
foreach ($owners as $o) {
    $oid = (int)$o['id'];
    $alloc = $allocRows[$oid] ?? 0.0;
    $used = ($usedTrans[$oid] ?? 0.0) + ($usedPerdiem[$oid] ?? 0.0) + ($usedFuel[$oid] ?? 0.0);
    $directorate_data[] = [
        'id'        => $oid,
        'code'      => $o['code'],
        'name'      => $o['name'],
        'allocated' => $alloc,
        'used'      => $used,
        'remaining' => max(0, $alloc - $used),
    ];
}

// --- Calculating Totals for KPIs ---

$total_allocated = array_sum(array_column($directorate_data, 'allocated'));
$total_used = array_sum(array_column($directorate_data, 'used'));
$total_remaining = max(0, $total_allocated - $total_used);
$utilization_percentage = $total_allocated > 0 ? ($total_used / $total_allocated) * 100 : 0;
$remaining_percentage = max(0, 100 - $utilization_percentage);

// --- Chart Data Preparation ---

// Allocation Chart: Top 7 owners + "Others"
usort($directorate_data, function($a, $b) { return $b['allocated'] <=> $a['allocated']; });
$chart_labels_arr = array_column(array_slice($directorate_data, 0, 7), 'name');
$chart_alloc_arr = array_column(array_slice($directorate_data, 0, 7), 'allocated');
$others_alloc = array_sum(array_column(array_slice($directorate_data, 7), 'allocated'));

if ($others_alloc > 0) {
    $chart_labels_arr[] = 'Others';
    $chart_alloc_arr[] = round($others_alloc, 2);
}

$chart_labels = json_encode($chart_labels_arr, JSON_UNESCAPED_UNICODE);
$chart_allocated_data = json_encode($chart_alloc_arr);

// Spending Trend: Last 6 months
$trend = [];
$sql_template = "
    SELECT DATE_FORMAT(%s, '%%Y-%%m') ym, SUM(%s) AS total
    FROM %s
    WHERE 1=1 %s GROUP BY ym
";

// Transactions
$params_t = []; $where_t = '';
if ($filter_owner > 0) { $where_t .= " AND owner_id = ?"; $params_t[] = $filter_owner; }
if ($filter_code > 0) { $where_t .= " AND code_id = ?"; $params_t[] = $filter_code; }
if ($start_dt) { $where_t .= " AND date >= ?"; $params_t[] = $start_dt; }
if ($end_dt) { $where_t .= " AND date <= ?"; $params_t[] = $end_dt; }
$stmt = $pdo->prepare(sprintf($sql_template, 'date', 'amount', 'transactions', $where_t));
$stmt->execute($params_t);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $trend[$r['ym']] = ($trend[$r['ym']] ?? 0) + (float)$r['total'];

// Per Diem
if ($includeP) {
    $params_p = []; $where_p = '';
    if ($filter_owner > 0) { $where_p .= " AND budget_owner_id = ?"; $params_p[] = $filter_owner; }
    if ($start_dt) { $where_p .= " AND created_at >= ?"; $params_p[] = $start_dt; }
    if ($end_dt) { $where_p .= " AND created_at <= ?"; $params_p[] = $end_dt; }
    $stmt = $pdo->prepare(sprintf($sql_template, 'created_at', 'total_amount', 'perdium_transactions', $where_p));
    $stmt->execute($params_p);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $trend[$r['ym']] = ($trend[$r['ym']] ?? 0) + (float)$r['total'];
}

// Fuel
if ($includeF) {
    $params_f = []; $where_f = '';
    if ($filter_owner > 0) { $where_f .= " AND owner_id = ?"; $params_f[] = $filter_owner; }
    if ($start_dt) { $where_f .= " AND date >= ?"; $params_f[] = $start_dt; }
    if ($end_dt) { $where_f .= " AND date <= ?"; $params_f[] = $end_dt; }
    $stmt = $pdo->prepare(sprintf($sql_template, 'date', 'total_amount', 'fuel_transactions', $where_f));
    $stmt->execute($params_f);
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $trend[$r['ym']] = ($trend[$r['ym']] ?? 0) + (float)$r['total'];
}

ksort($trend);
$trend = array_slice($trend, -6, 6, true);
$trend_labels = array_map(function($ym) {
    $dt = DateTime::createFromFormat('Y-m', $ym);
    return $dt ? $dt->format('M Y') : $ym;
}, array_keys($trend));
$trend_values = array_values($trend);
$trend_labels_json = json_encode($trend_labels);
$trend_values_json = json_encode($trend_values);

// --- Recent Activity (filtered) ---

$recent = [];
$sql_union = "
    SELECT 'General' src, t.id, t.date dt, o.name owner_name, c.name code_name, t.employee_name, t.amount
    FROM transactions t LEFT JOIN budget_owners o ON t.owner_id = o.id LEFT JOIN budget_codes c ON t.code_id = c.id
    WHERE 1=1 %s

    UNION ALL

    SELECT 'Per Diem' src, p.id, p.created_at dt, o.name owner_name, 'Per Diem' code_name, e.name employee_name, p.total_amount amount
    FROM perdium_transactions p LEFT JOIN budget_owners o ON p.budget_owner_id = o.id LEFT JOIN emp_list e ON p.employee_id = e.id
    WHERE 1=1 %s

    UNION ALL

    SELECT 'Fuel' src, f.id, f.date dt, o.name owner_name, 'Fuel' code_name, f.driver_name employee_name, f.total_amount amount
    FROM fuel_transactions f LEFT JOIN budget_owners o ON f.owner_id = o.id
    WHERE 1=1 %s
";

// Building WHERE clauses for each UNION part
$params_recent = [];
$where_trans = '';
if ($filter_owner > 0) { $where_trans .= " AND t.owner_id = ?"; $params_recent[] = $filter_owner; }
if ($filter_code > 0) { $where_trans .= " AND t.code_id = ?"; $params_recent[] = $filter_code; }
if ($start_dt) { $where_trans .= " AND t.date >= ?"; $params_recent[] = $start_dt; }
if ($end_dt) { $where_trans .= " AND t.date <= ?"; $params_recent[] = $end_dt; }

$where_perdiem = '';
if ($includeP) {
    if ($filter_owner > 0) { $where_perdiem .= " AND p.budget_owner_id = ?"; $params_recent[] = $filter_owner; }
    if ($start_dt) { $where_perdiem .= " AND p.created_at >= ?"; $params_recent[] = $start_dt; }
    if ($end_dt) { $where_perdiem .= " AND p.created_at <= ?"; $params_recent[] = $end_dt; }
} else {
    // Exclude per diem if code filter is active and not 'Per Diem' code (6)
    $where_perdiem = " AND 1=0 ";
}

$where_fuel = '';
if ($includeF) {
    if ($filter_owner > 0) { $where_fuel .= " AND f.owner_id = ?"; $params_recent[] = $filter_owner; }
    if ($start_dt) { $where_fuel .= " AND f.date >= ?"; $params_recent[] = $start_dt; }
    if ($end_dt) { $where_fuel .= " AND f.date <= ?"; $params_recent[] = $end_dt; }
} else {
    // Exclude fuel if code filter is active and not 'Fuel' code (5)
    $where_fuel = " AND 1=0 ";
}

$sql_recent = "
    SELECT * FROM (
        " . sprintf($sql_union, $where_trans, $where_perdiem, $where_fuel) . "
    ) as recent_activity
    ORDER BY dt DESC
    LIMIT 7
";

$stmt = $pdo->prepare($sql_recent);
$stmt->execute($params_recent);
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Low Monthly Budgets (Alerts) ---

$lowWhere = ["b.budget_type='governmental'", "b.monthly_amount>0"];
$paramsLow = [];

if ($filter_owner > 0) {
    $lowWhere[] = "b.owner_id = ?";
    $paramsLow[] = $filter_owner;
}
if ($filter_code > 0) {
    $lowWhere[] = "b.code_id = ?";
    $paramsLow[] = $filter_code;
}
if ($start_dt) {
    $lowWhere[] = "b.adding_date >= ?";
    $paramsLow[] = $start_dt;
}
if ($end_dt) {
    $lowWhere[] = "b.adding_date <= ?";
    $paramsLow[] = $end_dt;
}

$lowWhereSql = 'WHERE '.implode(' AND ', $lowWhere);

$low_budgets = $pdo->prepare("
    SELECT b.*, o.code AS owner_code, o.name AS owner_name, c.code AS code_code, c.name AS code_name,
           CASE WHEN b.monthly_amount>0 THEN (b.remaining_monthly / NULLIF(b.monthly_amount,0)) ELSE NULL END AS ratio
    FROM budgets b
    JOIN budget_owners o ON o.id = b.owner_id
    LEFT JOIN budget_codes c ON c.id = b.code_id
    $lowWhereSql
    ORDER BY ratio ASC LIMIT 5
");
$low_budgets->execute($paramsLow);
$low_budgets = $low_budgets->fetchAll(PDO::FETCH_ASSOC);

// Total count of critically low budgets (<=10%)
$lowCountStmt = $pdo->prepare("
    SELECT COUNT(*) FROM budgets b
    $lowWhereSql AND (b.remaining_monthly / NULLIF(b.monthly_amount,0)) <= 0.10
");
$lowCountStmt->execute($paramsLow);
$critically_low_count = (int)$lowCountStmt->fetchColumn();

// --- Simple AI-like Heuristics/Insights ---
$best_util_owner = null;
$best_util_ratio = -1;
foreach ($directorate_data as $d) {
    if ($d['allocated'] > 0) {
        $ratio = $d['used'] / $d['allocated'];
        if ($ratio > $best_util_ratio) {
            $best_util_ratio = $ratio;
            $best_util_owner = $d;
        }
    }
}

$trend_delta = null;
if (count($trend_values) >= 2) {
    $last = end($trend_values);
    $prev = prev($trend_values);
    if ($prev > 0) {
        $trend_delta = round((($last - $prev) / $prev) * 100, 1);
    }
}

// Re-sort `directorate_data` for the Top Spenders list.
usort($directorate_data, function($a, $b) { return $b['used'] <=> $a['used']; });
$top_spenders = array_slice($directorate_data, 0, 5);

// --- HTML begins here ---
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Budget System</title>
    <link rel="icon" type="image/png" href="images/bureau-logo.png" sizes="32x32">
    <link rel="apple-touch-icon" href="images/bureau-logo.png">
    <meta name="theme-color" content="#6366f1">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        accent: '#ec4899',
                        success: '#10b981',
                        warning: '#f59e0b',
                        danger: '#ef4444',
                        info: '#3b82f6',
                        light: '#f8fafc',
                        lighter: '#f1f5f9',
                        dark: '#0b1220'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        :root {
            --primary: #6366f1;
            --secondary: #8b5cf6;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #334155;
            display: flex;
            transition: background .3s ease;
        }
        .dark body {
            background: linear-gradient(135deg, #0b1220 0%, #0f172a 100%);
            color: #cbd5e1;
        }
        .main-content {
            flex: 1;
        }
        .glass {
            background: rgba(255, 255, 255, .75);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, .25);
        }
        .dark .glass {
            background: rgba(15, 23, 42, .75);
            border-color: rgba(255, 255, 255, .08);
        }
        .card {
            transition: all .3s ease;
            border-radius: 1rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, .06);
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, .12);
        }
        .kpi::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        .btn {
            border-radius: .75rem;
            padding: .6rem 1.2rem;
            font-weight: 600;
            transition: all .2s ease;
        }
        .btn-primary {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(99, 102, 241, .35);
        }
        .progress {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background: #e5e7eb;
        }
        .dark .progress {
            background: #1e293b;
        }
        .progress > div {
            height: 8px;
            border-radius: 4px;
            transition: width 1s ease;
        }
        .table-row {
            transition: all .2s ease;
        }
        .table-row:hover {
            background: #f8fafc;
            transform: scale(1.005);
        }
        .dark .table-row:hover {
            background: #0b1220;
        }
        .badge {
            padding: .25rem .5rem;
            border-radius: .5rem;
            font-size: .75rem;
        }
        .theme-toggle {
            border: 1px solid rgba(99, 102, 241, .35);
        }
        .filter-label {
            font-size: .8rem;
            color: #475569;
        }
        .dark .filter-label {
            color: #93a3b8;
        }
    </style>
</head>

<body class="text-slate-700 dark:text-slate-200">
    <?php require_once 'includes/sidebar.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <div class="glass card p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center rounded-2xl">
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold">
                        Welcome, <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary"><?php echo h($display_name); ?></span>
                    </h1>
                    <p class="mt-2 text-slate-600 dark:text-slate-400">Here’s the latest financial overview of your bureau.</p>
                    <div class="flex items-center mt-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl p-3 shadow-inner">
                        <i class="fas fa-calendar-alt text-indigo-600 mr-3"></i>
                        <span class="text-indigo-800 dark:text-indigo-300 font-semibold" id="currentDateTime">Loading…</span>
                    </div>
                </div>
                <div class="flex items-center mt-4 md:mt-0 gap-3">
                    <a href="reports.php" class="btn btn-primary"><i class="fas fa-file-export mr-2"></i>Reports</a>
                    <button id="themeToggle" class="px-3 py-2 rounded-xl theme-toggle bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 shadow">
                        <i class="fas fa-moon"></i>
                    </button>
                    <button class="bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 p-3 rounded-xl md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <div class="glass card p-6 mb-6">
                <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4" id="filtersForm">
                    <div>
                        <label class="filter-label mb-1 block">Date From</label>
                        <input type="date" name="start" value="<?php echo h($filter_start); ?>" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                    </div>
                    <div>
                        <label class="filter-label mb-1 block">Date To</label>
                        <input type="date" name="end" value="<?php echo h($filter_end); ?>" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                    </div>
                    <div>
                        <label class="filter-label mb-1 block">Owner</label>
                        <select name="owner_id" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                            <option value="">All Owners</option>
                            <?php foreach ($owners as $o): ?>
                                <option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_owner === (int)$o['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($o['code'] . ' | ' . $o['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="filter-label mb-1 block">Budget Code</label>
                        <select name="code_id" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                            <option value="">All Codes</option>
                            <?php foreach ($codes as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_code === (int)$c['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($c['code'] . ' | ' . $c['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4 flex justify-end gap-3 pt-2">
                        <a href="dashboard.php" class="px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600">Clear</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-2"></i>Apply Filters</button>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="glass card kpi p-6 relative bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-900/20 dark:to-slate-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Total Allocated</div>
                            <div class="text-2xl font-extrabold mt-1"><?php echo fmt($total_allocated); ?> ETB</div>
                        </div>
                        <div class="p-3 rounded-xl bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300"><i class="fas fa-sack-dollar"></i></div>
                    </div>
                </div>
                <div class="glass card kpi p-6 relative bg-gradient-to-br from-emerald-50 to-white dark:from-emerald-900/20 dark:to-slate-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Total Used</div>
                            <div class="text-2xl font-extrabold mt-1"><?php echo fmt($total_used); ?> ETB</div>
                            <div class="progress mt-2"><div style="width: <?php echo round($utilization_percentage, 1); ?>%; background: linear-gradient(90deg, #10b981, #059669)"></div></div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300"><i class="fas fa-chart-line"></i></div>
                    </div>
                </div>
                <div class="glass card kpi p-6 relative bg-gradient-to-br from-sky-50 to-white dark:from-sky-900/20 dark:to-slate-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Remaining</div>
                            <div class="text-2xl font-extrabold mt-1"><?php echo fmt($total_remaining); ?> ETB</div>
                            <div class="progress mt-2"><div style="width: <?php echo round($remaining_percentage, 1); ?>%; background: linear-gradient(90deg, #3b82f6, #06b6d4)"></div></div>
                        </div>
                        <div class="p-3 rounded-xl bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300"><i class="fas fa-wallet"></i></div>
                    </div>
                </div>
                <div class="glass card kpi p-6 relative bg-gradient-to-br from-violet-50 to-white dark:from-violet-900/20 dark:to-slate-900">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">Critical Monthly Alerts</div>
                            <div class="text-2xl font-extrabold mt-1"><?php echo (int)$critically_low_count; ?></div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Remaining &le; 10% of monthly</div>
                        </div>
                        <div class="p-3 rounded-xl bg-violet-100 dark:bg-violet-900 text-violet-700 dark:text-violet-300"><i class="fas fa-triangle-exclamation"></i></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 glass card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold"><i class="fas fa-chart-pie mr-2 text-indigo-600"></i>Allocation by Owner</h2>
                    </div>
                    <div style="height:340px"><canvas id="allocationChart"></canvas></div>
                </div>

                <div class="glass card p-6">
                    <h2 class="text-xl font-bold mb-4"><i class="fas fa-wand-magic-sparkles mr-2 text-rose-500"></i>Insights</h2>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-start gap-2">
                            <i class="fas fa-bolt text-amber-500 mt-1"></i>
                            <div>
                                Utilization: <b><?php echo round($utilization_percentage, 1); ?>%</b> of total allocated is used.
                                <?php if ($best_util_owner): ?>
                                    Top spender: <b><?php echo h($best_util_owner['code'] . ' - ' . $best_util_owner['name']); ?></b>
                                    (<?php echo round(($best_util_owner['used'] / $best_util_owner['allocated']) * 100, 1); ?>% of its allocation).
                                <?php endif; ?>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-arrow-trend-up text-emerald-500 mt-1"></i>
                            <div>
                                <?php if ($trend_delta !== null): ?>
                                    Latest month spending is <b><?php echo ($trend_delta >= 0 ? '+' : ''); echo $trend_delta; ?>%</b> vs previous month.
                                <?php else: ?>
                                    Not enough data for month-over-month trend.
                                <?php endif; ?>
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-bell text-red-500 mt-1"></i>
                            <div>
                                <b><?php echo (int)$critically_low_count; ?></b> monthly budgets are critically low (&le;10% remaining).
                            </div>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="fas fa-brain text-violet-500 mt-1"></i>
                            <div>
                                Explore deeper insights in the <a href="analysis.php" class="text-indigo-600 dark:text-indigo-400 underline">Analysis Center</a>.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 glass card p-6">
                    <h2 class="text-xl font-bold mb-4"><i class="fas fa-wave-square mr-2 text-emerald-600"></i>Spending Trend (Last 6 Months)</h2>
                    <div style="height:340px"><canvas id="trendChart"></canvas></div>
                </div>
                <div class="glass card p-6">
                    <h2 class="text-xl font-bold mb-4"><i class="fas fa-ranking-star mr-2 text-violet-600"></i>Top Spenders</h2>
                    <?php if (empty($top_spenders)): ?>
                        <div class="text-sm text-slate-500 dark:text-slate-400">No spending data.</div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($top_spenders as $ts):
                                $u = (float)$ts['used'];
                                $a = (float)$ts['allocated'];
                                $pct = $a > 0 ? round(($u / $a) * 100, 1) : 0;
                            ?>
                                <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-700">
                                    <div class="flex justify-between text-sm font-semibold">
                                        <span><?php echo h($ts['code'] . ' - ' . $ts['name']); ?></span>
                                        <span class="text-slate-600 dark:text-slate-300"><?php echo fmt($u); ?> ETB</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400"><?php echo fmt($a); ?> allocated • <?php echo $pct; ?>% used</div>
                                    <div class="progress mt-2"><div style="width: <?php echo $pct; ?>%; background:linear-gradient(90deg,#6366f1,#8b5cf6)"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="glass card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold"><i class="fas fa-clock-rotate-left mr-2 text-blue-600"></i>Recent Activity</h2>
                    <a href="reports.php" class="text-sm text-indigo-700 dark:text-indigo-300 hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto rounded-2xl">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                            <tr>
                                <th class="px-6 py-4">When</th>
                                <th class="px-6 py-4">Source</th>
                                <th class="px-6 py-4">Owner</th>
                                <th class="px-6 py-4">Code</th>
                                <th class="px-6 py-4">Employee/Driver</th>
                                <th class="px-6 py-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No recent activity.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent as $tx): ?>
                                    <tr class="table-row border-b border-slate-200 dark:border-slate-700">
                                        <td class="px-6 py-4"><?php echo h(date('M j, Y H:i', strtotime($tx['dt']))); ?></td>
                                        <td class="px-6 py-4"><span class="badge bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"><?php echo h($tx['src']); ?></span></td>
                                        <td class="px-6 py-4"><?php echo h($tx['owner_name'] ?? '—'); ?></td>
                                        <td class="px-6 py-4"><?php echo h($tx['code_name'] ?? '—'); ?></td>
                                        <td class="px-6 py-4"><?php echo h($tx['employee_name'] ?? '—'); ?></td>
                                        <td class="px-6 py-4 text-right font-semibold"><?php echo fmt($tx['amount'] ?? 0); ?> ETB</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer class="mt-10 text-center text-slate-500 dark:text-slate-400 text-sm">
                <p>&copy; <span id="currentYear"></span> Budget System • Built with care for the Afar Health Bureau</p>
            </footer>
        </div>
    </div>
    <script>
        // Theme toggle
        (function() {
            const root = document.documentElement;
            const saved = localStorage.getItem('theme');
            if (saved === 'dark') root.classList.add('dark');
            if (saved === 'light') root.classList.remove('dark');
            document.getElementById('themeToggle')?.addEventListener('click', () => {
                root.classList.toggle('dark');
                localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
            });
        })();

        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('sidebarToggle');
            toggleBtn?.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });

            updateDateTime();
            setInterval(updateDateTime, 60000);
            document.getElementById('currentYear').textContent = new Date().getFullYear();
            initCharts();

            // Animate progress bars
            document.querySelectorAll('.progress > div').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 350);
            });
        });

        function updateDateTime() {
            const now = new Date();
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            };
            const el = document.getElementById('currentDateTime');
            if (el) el.textContent = now.toLocaleDateString('en-US', options);
        }

        function initCharts() {
            // Doughnut with gradients
            const allocCtx = document.getElementById('allocationChart').getContext('2d');
            const grad1 = allocCtx.createLinearGradient(0, 0, 0, 300);
            grad1.addColorStop(0, '#6366f1');
            grad1.addColorStop(1, '#8b5cf6');
            const grad2 = allocCtx.createLinearGradient(0, 0, 0, 300);
            grad2.addColorStop(0, '#10b981');
            grad2.addColorStop(1, '#059669');
            const grad3 = allocCtx.createLinearGradient(0, 0, 0, 300);
            grad3.addColorStop(0, '#f59e0b');
            grad3.addColorStop(1, '#f97316');
            const grad4 = allocCtx.createLinearGradient(0, 0, 0, 300);
            grad4.addColorStop(0, '#ec4899');
            grad4.addColorStop(1, '#db2777');
            const grad5 = allocCtx.createLinearGradient(0, 0, 0, 300);
            grad5.addColorStop(0, '#3b82f6');
            grad5.addColorStop(1, '#06b6d4');
            const grad6 = allocCtx.createLinearGradient(0, 0, 0, 300);
            grad6.addColorStop(0, '#ef4444');
            grad6.addColorStop(1, '#dc2626');
            const grad7 = allocCtx.createLinearGradient(0, 0, 0, 300);
            grad7.addColorStop(0, '#22c55e');
            grad7.addColorStop(1, '#16a34a');

            const allocLabels = <?php echo $chart_labels; ?>;
            const allocData = <?php echo $chart_allocated_data; ?>;
            new Chart(allocCtx, {
                type: 'doughnut',
                data: {
                    labels: allocLabels,
                    datasets: [{
                        data: allocData,
                        backgroundColor: [grad1, grad2, grad3, grad4, grad5, grad6, grad7],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: document.documentElement.classList.contains('dark') ? '#e2e8f0' : '#1e293b'
                            }
                        }
                    }
                }
            });

            // Trend chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const grad = trendCtx.createLinearGradient(0, 0, 0, 320);
            grad.addColorStop(0, 'rgba(99,102,241,.35)');
            grad.addColorStop(1, 'rgba(99,102,241,.07)');
            const trendLabels = <?php echo $trend_labels_json; ?>;
            const trendValues = <?php echo $trend_values_json; ?>;
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Total Spend',
                        data: trendValues,
                        borderColor: '#6366f1',
                        backgroundColor: grad,
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#6366f1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#cbd5e1' : '#334155'
                            }
                        },
                        y: {
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#cbd5e1' : '#334155'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
