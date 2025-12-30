<?php
require_once 'includes/init.php';

// Require login
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Helpers
//function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
//function fmt($n){ return number_format((float)$n, 2); }

// --- Filters ---
$filter_type  = $_GET['type'] ?? 'all'; // 'all', 'general', 'perdium', 'fuel'
$filter_owner = isset($_GET['owner_id']) && $_GET['owner_id'] !== '' ? (int)$_GET['owner_id'] : 0;
$filter_code  = isset($_GET['code_id'])  && $_GET['code_id']  !== '' ? (int)$_GET['code_id']  : 0;
$filter_start = isset($_GET['start'])    && $_GET['start']    !== '' ? $_GET['start']         : '';
$filter_end   = isset($_GET['end'])      && $_GET['end']      !== '' ? $_GET['end']           : '';

$start_dt = ''; $end_dt = '';
if ($filter_start) { $ts = strtotime($filter_start); if ($ts) $start_dt = date('Y-m-d', $ts); }
if ($filter_end)   { $te = strtotime($filter_end);   if ($te) $end_dt   = date('Y-m-d 23:59:59', $te); } // Inclusive end date

// --- Data Fetching ---
$transactions = [];

// Base WHERE clause builder
function buildWhere($owner_col, $code_col, $date_col, &$params) {
    global $filter_owner, $filter_code, $start_dt, $end_dt;
    $where = [];
    if ($filter_owner > 0) { $where[] = "$owner_col = ?"; $params[] = $filter_owner; }
    if ($filter_code > 0 && $code_col) { $where[] = "$code_col = ?"; $params[] = $filter_code; }
    if ($start_dt) { $where[] = "$date_col >= ?"; $params[] = $start_dt; }
    if ($end_dt)   { $where[] = "$date_col <= ?"; $params[] = $end_dt; }
    return $where ? 'WHERE ' . implode(' AND ', $where) : '';
}

// 1. General Transactions
if ($filter_type === 'all' || $filter_type === 'general') {
    $params = [];
    $where = buildWhere('t.owner_id', 't.code_id', 't.date', $params);
    $stmt = $pdo->prepare("
        SELECT 'General' AS type, t.id, t.date, o.name AS owner_name, c.name AS code_name, t.employee_name AS person, t.reason, t.amount
        FROM transactions t
        LEFT JOIN budget_owners o ON t.owner_id = o.id
        LEFT JOIN budget_codes c ON t.code_id = c.id
        $where
        ORDER BY t.date DESC
    ");
    $stmt->execute($params);
    $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 2. Per Diem Transactions
if ($filter_type === 'all' || $filter_type === 'perdium') {
    $params = [];
    $where = buildWhere('p.budget_owner_id', null, 'p.created_at', $params); // Per diem doesn't have a code_id link
    $stmt = $pdo->prepare("
        SELECT 'Per Diem' AS type, p.id, p.created_at AS date, o.name AS owner_name, 'Per Diem' AS code_name, e.name AS person, ct.name_amharic AS reason, p.total_amount AS amount
        FROM perdium_transactions p
        LEFT JOIN budget_owners o ON p.budget_owner_id = o.id
        LEFT JOIN emp_list e ON p.employee_id = e.id
        LEFT JOIN cities ct ON p.city_id = ct.id
        $where
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// 3. Fuel Transactions
if ($filter_type === 'all' || $filter_type === 'fuel') {
    $params = [];
    $where = buildWhere('f.owner_id', null, 'f.date', $params); // Fuel doesn't have a code_id link
    $stmt = $pdo->prepare("
        SELECT 'Fuel' AS type, f.id, f.date, o.name AS owner_name, 'Fuel' AS code_name, f.driver_name AS person, f.plate_number AS reason, f.total_amount AS amount
        FROM fuel_transactions f
        LEFT JOIN budget_owners o ON f.owner_id = o.id
        $where
        ORDER BY f.date DESC
    ");
    $stmt->execute($params);
    $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Sort all transactions by date descending
if ($filter_type === 'all') {
    usort($transactions, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
}

// Calculate KPIs
$total_transactions = count($transactions);
$total_amount = array_sum(array_column($transactions, 'amount'));
$average_transaction = $total_transactions > 0 ? $total_amount / $total_transactions : 0;

// Data for filter dropdowns
$owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$codes  = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
	<?php
    $pageTitle = 'Reports - Budget System';
    require_once 'includes/head.php';
    ?>
    	
    <meta charset="UTF-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="icon" type="image/png" href="images/bureau-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { colors: { primary:'#6366f1', secondary:'#8b5cf6', dark:'#0b1220' } } }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family:'Inter',sans-serif; background: #f1f5f9; display:flex; }
        .dark body { background: #0f172a; color:#cbd5e1; }
        .glass { background: rgba(255,255,255,.75); backdrop-filter: blur(10px); border:1px solid rgba(255,255,255,.25); }
        .dark .glass { background: rgba(15,23,42,.75); border-color: rgba(255,255,255,.08); }
        .card { border-radius: 1rem; box-shadow: 0 8px 16px rgba(0,0,0,.06); }
        .table-row:hover { background:#f8fafc; }
        .dark .table-row:hover { background:#0b1220; }
        @media print {
            body { display: block; }
            #sidebar, #header, #filtersForm, #exportCsv, .no-print { display: none !important; }
            .main-content { width: 100%; }
            .print-container { padding: 0 !important; }
            .card { box-shadow: none; border: 1px solid #e2e8f0; }
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
<body class="text-slate-700 dark:text-slate-200">
<?php require_once 'includes/sidebar-component.php'; ?>
<div class="main-content" id="mainContent">
	<?php require_once 'includes/header.php'; ?>
    <div class="p-6 print-container">

        <div id="header" class="glass card p-6 mb-6 flex flex-col md:flex-row justify-between items-center rounded-2xl">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 dark:text-white">
                    <i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Transaction Reports
                </h1>
                <p class="mt-2 text-slate-600 dark:text-slate-400">Generate, filter, and export detailed transaction reports.</p>
            </div>
            <div class="flex items-center mt-4 md:mt-0 gap-3">
                <button id="exportCsv" class="px-4 py-2 rounded-lg bg-emerald-500 text-white font-semibold flex items-center gap-2 hover:bg-emerald-600 transition shadow-lg">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-sky-500 text-white font-semibold flex items-center gap-2 hover:bg-sky-600 transition shadow-lg">
                    <i class="fas fa-print"></i> Print
                </button>
                <button id="themeToggle" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 shadow">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>

        <div id="filtersForm" class="glass card p-6 mb-6">
            <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="text-sm font-medium mb-1 block">Date From</label>
                    <input type="date" name="start" value="<?php echo h($filter_start); ?>" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Date To</label>
                    <input type="date" name="end" value="<?php echo h($filter_end); ?>" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Type</label>
                    <select name="type" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                        <option value="all" <?php echo $filter_type==='all'?'selected':''; ?>>All Types</option>
                        <option value="general" <?php echo $filter_type==='general'?'selected':''; ?>>General</option>
                        <option value="perdium" <?php echo $filter_type==='perdium'?'selected':''; ?>>Per Diem</option>
                        <option value="fuel" <?php echo $filter_type==='fuel'?'selected':''; ?>>Fuel</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Owner</label>
                    <select name="owner_id" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                        <option value="">All Owners</option>
                        <?php foreach ($owners as $o): ?>
                            <option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_owner===(int)$o['id'] ? 'selected':''; ?>>
                                <?php echo h($o['code'].' | '.$o['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Code</label>
                    <select name="code_id" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
                        <option value="">All Codes</option>
                        <?php foreach ($codes as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_code===(int)$c['id'] ? 'selected':''; ?>>
                                <?php echo h($c['code'].' | '.$c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-5 flex justify-end gap-3 pt-2">
                    <a href="reports.php" class="px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600">Clear</a>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold flex items-center gap-2 hover:bg-indigo-700">
                        <i class="fas fa-search"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="glass card p-5 text-center">
                <div class="text-4xl font-extrabold text-indigo-600"><?php echo number_format($total_transactions); ?></div>
                <div class="text-slate-500 dark:text-slate-400 mt-1">Total Transactions</div>
            </div>
            <div class="glass card p-5 text-center">
                <div class="text-4xl font-extrabold text-emerald-600"><?php echo fmt($total_amount); ?></div>
                <div class="text-slate-500 dark:text-slate-400 mt-1">Total Amount (ETB)</div>
            </div>
            <div class="glass card p-5 text-center">
                <div class="text-4xl font-extrabold text-sky-600"><?php echo fmt($average_transaction); ?></div>
                <div class="text-slate-500 dark:text-slate-400 mt-1">Average Txn Amount (ETB)</div>
            </div>
        </div>

        <!-- Results Table -->
        <div class="glass card p-6">
            <h2 class="text-xl font-bold mb-4">Report Results</h2>
            <div class="overflow-x-auto rounded-lg">
                <table class="w-full text-sm text-left" id="reportTable">
                    <thead class="text-xs uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Person</th>
                            <th class="px-4 py-3">Reason / Details</th>
                            <th class="px-4 py-3 text-right">Amount (ETB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr><td colspan="7" class="text-center p-8 text-slate-500">No transactions found for the selected filters.</td></tr>
                        <?php else: foreach ($transactions as $tx): ?>
                            <tr class="table-row border-b border-slate-200 dark:border-slate-700">
                                <td class="px-4 py-3"><?php echo h(date('Y-m-d', strtotime($tx['date']))); ?></td>
                                <td class="px-4 py-3"><span class="font-semibold"><?php echo h($tx['type']); ?></span></td>
                                <td class="px-4 py-3"><?php echo h($tx['owner_name'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-3"><?php echo h($tx['code_name'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-3"><?php echo h($tx['person'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-3"><?php echo h($tx['reason'] ?? 'N/A'); ?></td>
                                <td class="px-4 py-3 text-right font-semibold"><?php echo fmt($tx['amount']); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Theme toggle
    (function(){
      const root = document.documentElement;
      const saved = localStorage.getItem('theme');
      if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark');
      document.getElementById('themeToggle')?.addEventListener('click', ()=>{
        root.classList.toggle('dark');
        localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
      });
    })();
    
    

    // Export to CSV
    document.getElementById('exportCsv')?.addEventListener('click', () => {
        const table = document.getElementById('reportTable');
        let csv = [];
        for (const row of table.rows) {
            const rowData = [];
            for (const cell of row.cells) {
                rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
            }
            csv.push(rowData.join(','));
        }
        const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        
        const date = new Date().toISOString().slice(0,10);
        link.setAttribute("download", `budget_report_${date}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
</script>
</body>
</html>