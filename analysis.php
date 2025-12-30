<?php
require_once 'includes/init.php';

// Require login
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Helpers
//function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
//function fmt($n){ return number_format((float)$n, 2); }

// Filters
$filter_owner = isset($_GET['owner_id']) && $_GET['owner_id'] !== '' ? (int)$_GET['owner_id'] : 0;
$filter_code  = isset($_GET['code_id'])  && $_GET['code_id']  !== '' ? (int)$_GET['code_id']  : 0;
$filter_start = isset($_GET['start'])    && $_GET['start']    !== '' ? $_GET['start']         : '';
$filter_end   = isset($_GET['end'])      && $_GET['end']      !== '' ? $_GET['end']           : '';

$start_dt = '';
$end_dt   = '';
if ($filter_start) { $ts = strtotime($filter_start); if ($ts) $start_dt = date('Y-m-d', $ts); }
if ($filter_end)   { $te = strtotime($filter_end);   if ($te) $end_dt   = date('Y-m-d', $te); }

// Build WHERE clauses for each source
function buildWhere($source_alias, $owner_col, $code_col, $date_col, $filter_owner, $filter_code, $start_dt, $end_dt, &$params) {
    $where = [];
    if ($filter_owner > 0) { $where[] = "$source_alias.$owner_col = ?"; $params[] = $filter_owner; }
    if ($filter_code > 0 && $code_col) { $where[] = "$source_alias.$code_col = ?"; $params[] = $filter_code; }
    if ($start_dt) { $where[] = "$source_alias.$date_col >= ?"; $params[] = $start_dt; }
    if ($end_dt) { $where[] = "$source_alias.$date_col <= ?"; $params[] = $end_dt; }
    return $where ? 'WHERE ' . implode(' AND ', $where) : '';
}

// Monthly Spending by Source (for trend chart)
$trend = [];
// Transactions
$paramsT = [];
$whereT = buildWhere('t', 'owner_id', 'code_id', 'date', $filter_owner, $filter_code, $start_dt, $end_dt, $paramsT);
$st = $pdo->prepare("SELECT DATE_FORMAT(t.date,'%Y-%m') ym, SUM(t.amount) total FROM transactions t $whereT GROUP BY ym");
$st->execute($paramsT);
while ($r = $st->fetch(PDO::FETCH_ASSOC)) { $trend[$r['ym']]['General'] = ($trend[$r['ym']]['General'] ?? 0) + (float)$r['total']; }

// Per Diem
$paramsP = [];
$whereP = buildWhere('p', 'budget_owner_id', null, 'created_at', $filter_owner, $filter_code, $start_dt, $end_dt, $paramsP);
$st = $pdo->prepare("SELECT DATE_FORMAT(p.created_at,'%Y-%m') ym, SUM(p.total_amount) total FROM perdium_transactions p $whereP GROUP BY ym");
$st->execute($paramsP);
while ($r = $st->fetch(PDO::FETCH_ASSOC)) { $trend[$r['ym']]['Per Diem'] = ($trend[$r['ym']]['Per Diem'] ?? 0) + (float)$r['total']; }

// Fuel
$paramsF = [];
$whereF = buildWhere('f', 'owner_id', null, 'date', $filter_owner, $filter_code, $start_dt, $end_dt, $paramsF);
$st = $pdo->prepare("SELECT DATE_FORMAT(f.date,'%Y-%m') ym, SUM(f.total_amount) total FROM fuel_transactions f $whereF GROUP BY ym");
$st->execute($paramsF);
while ($r = $st->fetch(PDO::FETCH_ASSOC)) { $trend[$r['ym']]['Fuel'] = ($trend[$r['ym']]['Fuel'] ?? 0) + (float)$r['total']; }

// Aggregate and format trend data
ksort($trend);
$trend_labels_arr = [];
$trend_data = ['General' => [], 'Per Diem' => [], 'Fuel' => []];
foreach ($trend as $ym => $sources) {
    $d = DateTime::createFromFormat('Y-m', $ym);
    $trend_labels_arr[] = $d ? $d->format('M Y') : $ym;
    $trend_data['General'][] = round($sources['General'] ?? 0, 2);
    $trend_data['Per Diem'][] = round($sources['Per Diem'] ?? 0, 2);
    $trend_data['Fuel'][] = round($sources['Fuel'] ?? 0, 2);
}
$trend_labels_json = json_encode($trend_labels_arr);
$trend_data_json = json_encode(array_values($trend_data));

// Full owner breakdown table
$owner_breakdown = [];
$owners_list = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
foreach ($owners_list as $o) {
    $oid = (int)$o['id'];
    $owner_breakdown[$oid] = ['id'=>$oid, 'code'=>$o['code'], 'name'=>$o['name'], 'allocated'=>0, 'used'=>0, 'remaining'=>0, 'utilization'=>0];
}

// Allocations
$paramsAlloc = [];
$whereAlloc = buildWhere('b', 'owner_id', 'code_id', 'adding_date', $filter_owner, $filter_code, $start_dt, $end_dt, $paramsAlloc);
$st = $pdo->prepare("SELECT owner_id, SUM(yearly_amount+monthly_amount) total FROM budgets b $whereAlloc GROUP BY owner_id");
$st->execute($paramsAlloc);
while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    if (isset($owner_breakdown[(int)$r['owner_id']])) {
        $owner_breakdown[(int)$r['owner_id']]['allocated'] += (float)$r['total'];
    }
}

// Used amounts (re-use params and where clauses from above)
foreach ($usedTrans as $oid => $amt) if (isset($owner_breakdown[$oid])) $owner_breakdown[$oid]['used'] += $amt;
foreach ($usedPerdiem as $oid => $amt) if (isset($owner_breakdown[$oid])) $owner_breakdown[$oid]['used'] += $amt;
foreach ($usedFuel as $oid => $amt) if (isset($owner_breakdown[$oid])) $owner_breakdown[$oid]['used'] += $amt;

// Final calculations
foreach ($owner_breakdown as &$o) {
    $o['remaining'] = max(0, $o['allocated'] - $o['used']);
    $o['utilization'] = $o['allocated'] > 0 ? ($o['used'] / $o['allocated']) * 100 : 0;
}
unset($o);

// For filter dropdowns
$codes  = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en" class="">
<head>
  <meta charset="UTF-8">
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analysis Center - Budget System</title>
  <link rel="icon" type="image/png" href="images/bureau-logo.png" sizes="32x32">
  <link rel="apple-touch-icon" href="images/bureau-logo.png">
  <meta name="theme-color" content="#4f46e5">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="css/sidebar.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary:'#6366f1', secondary:'#8b5cf6', accent:'#ec4899',
            dark:'#0b1220'
          }
        }
      }
    }
  </script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    :root { --primary:#6366f1; --secondary:#8b5cf6; }
    body { font-family:'Inter',sans-serif; background: #f1f5f9; min-height:100vh; color:#334155; display:flex; }
    .dark body { background: #0f172a; color:#cbd5e1; }
    .glass { background: rgba(255,255,255,.75); backdrop-filter: blur(10px); border:1px solid rgba(255,255,255,.25); }
    .dark .glass { background: rgba(15,23,42,.75); border-color: rgba(255,255,255,.08); }
    .card { transition: all .3s ease; border-radius: 1rem; box-shadow: 0 8px 16px rgba(0,0,0,.06); }
    .table-row:hover { background:#f8fafc; }
    .dark .table-row:hover { background:#0b1220; }
  </style>
</head>
<body class="text-slate-700 dark:text-slate-200">
  <?php require_once 'includes/sidebar-new.php'; ?>
  <div class="main-content" id="mainContent">
    <div class="p-6">

      <!-- Header -->
      <div class="glass card p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center rounded-2xl">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 dark:text-white">
            <i class="fas fa-brain mr-2 text-rose-500"></i>Analysis Center
          </h1>
          <p class="mt-2 text-slate-600 dark:text-slate-400">Deep dive into financial data and trends.</p>
        </div>
        <div class="flex items-center mt-4 md:mt-0 gap-3">
          <button id="exportCsv" class="px-4 py-2 rounded-lg bg-emerald-500 text-white font-semibold flex items-center gap-2 hover:bg-emerald-600">
            <i class="fas fa-file-csv"></i> Export CSV
          </button>
          <button id="themeToggle" class="px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 shadow">
            <i class="fas fa-moon"></i>
          </button>
          <button class="bg-slate-200 dark:bg-slate-700 p-3 rounded-xl md:hidden shadow-sm" id="sidebarToggle">
            <i class="fas fa-bars"></i>
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="glass card p-6 mb-6">
        <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4" id="filtersForm">
          <div>
            <label class="text-sm font-medium mb-1 block">Date From</label>
            <input type="date" name="start" value="<?php echo h($filter_start); ?>" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
          </div>
          <div>
            <label class="text-sm font-medium mb-1 block">Date To</label>
            <input type="date" name="end" value="<?php echo h($filter_end); ?>" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
          </div>
          <div>
            <label class="text-sm font-medium mb-1 block">Owner</label>
            <select name="owner_id" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
              <option value="">All Owners</option>
              <?php foreach ($owners_list as $o): ?>
                <option value="<?php echo (int)$o['id']; ?>" <?php echo $filter_owner===(int)$o['id'] ? 'selected':''; ?>>
                  <?php echo h($o['code'].' | '.$o['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-sm font-medium mb-1 block">Budget Code</label>
            <select name="code_id" class="w-full border rounded-lg p-2 bg-white dark:bg-slate-800 dark:border-slate-700">
              <option value="">All Codes</option>
              <?php foreach ($codes as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $filter_code===(int)$c['id'] ? 'selected':''; ?>>
                  <?php echo h($c['code'].' | '.$c['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-4 flex justify-end gap-3 pt-2">
            <a href="analysis.php" class="px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600">Clear</a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-semibold flex items-center gap-2 hover:bg-indigo-700">
              <i class="fas fa-filter"></i>Apply Filters
            </button>
          </div>
        </form>
      </div>

      <!-- Trend Chart -->
      <div class="glass card p-6 mb-6">
        <h2 class="text-xl font-bold mb-4"><i class="fas fa-wave-square mr-2 text-emerald-500"></i>Spending Trend by Source</h2>
        <div style="height:350px"><canvas id="trendChart"></canvas></div>
      </div>

      <!-- Owner Breakdown Table -->
      <div class="glass card p-6">
        <h2 class="text-xl font-bold mb-4"><i class="fas fa-sitemap mr-2 text-violet-500"></i>Owner Utilization Breakdown</h2>
        <div class="overflow-x-auto rounded-lg">
          <table class="w-full text-sm text-left" id="ownerTable">
            <thead class="text-xs uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
              <tr>
                <th class="px-4 py-3 cursor-pointer" onclick="sortTable('ownerTable', 0)">Owner <i class="fas fa-sort"></i></th>
                <th class="px-4 py-3 cursor-pointer text-right" onclick="sortTable('ownerTable', 1, true)">Allocated <i class="fas fa-sort"></i></th>
                <th class="px-4 py-3 cursor-pointer text-right" onclick="sortTable('ownerTable', 2, true)">Used <i class="fas fa-sort"></i></th>
                <th class="px-4 py-3 cursor-pointer text-right" onclick="sortTable('ownerTable', 3, true)">Remaining <i class="fas fa-sort"></i></th>
                <th class="px-4 py-3 cursor-pointer text-right" onclick="sortTable('ownerTable', 4, true)">Utilization <i class="fas fa-sort"></i></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($owner_breakdown as $o):
                if ($filter_owner > 0 && (int)$o['id'] !== $filter_owner) continue;
                if ($o['allocated'] == 0 && $o['used'] == 0) continue;
              ?>
                <tr class="table-row border-b border-slate-200 dark:border-slate-700">
                  <td class="px-4 py-3 font-semibold"><?php echo h($o['code'].' - '.$o['name']); ?></td>
                  <td class="px-4 py-3 text-right"><?php echo fmt($o['allocated']); ?></td>
                  <td class="px-4 py-3 text-right"><?php echo fmt($o['used']); ?></td>
                  <td class="px-4 py-3 text-right font-semibold <?php echo $o['remaining'] < 0 ? 'text-red-500' : 'text-emerald-600 dark:text-emerald-400'; ?>">
                    <?php echo fmt($o['remaining']); ?>
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <span class="font-semibold"><?php echo round($o['utilization'],1); ?>%</span>
                      <div class="w-24 progress"><div style="width: <?php echo min(100, $o['utilization']); ?>%; background:linear-gradient(90deg,#6366f1,#8b5cf6)"></div></div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <footer class="mt-8 text-center text-slate-500 dark:text-slate-400 text-sm">
        <p>&copy; <?php echo date('Y'); ?> Budget System • Analysis Center</p>
      </footer>
    </div>
  </div>

  <script>
    (function(){
      const root = document.documentElement;
      const saved = localStorage.getItem('theme');
      if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) root.classList.add('dark');
      document.getElementById('themeToggle')?.addEventListener('click', ()=>{
        root.classList.toggle('dark');
        localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
      });
    })();

    document.addEventListener('DOMContentLoaded', () => {
      initCharts();

      document.getElementById('exportCsv')?.addEventListener('click', () => {
        const table = document.getElementById('ownerTable');
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
        link.setAttribute("download", "budget_analysis.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    });

    function initCharts() {
      const isDark = document.documentElement.classList.contains('dark');
      const textColor = isDark ? '#cbd5e1' : '#334155';

      const trendCtx = document.getElementById('trendChart').getContext('2d');
      const trendLabels = <?php echo $trend_labels_json; ?>;
      const [generalData, perdiumData, fuelData] = <?php echo $trend_data_json; ?>;
      new Chart(trendCtx, {
        type: 'bar',
        data: {
          labels: trendLabels,
          datasets: [
            { label: 'General', data: generalData, backgroundColor: '#6366f1' },
            { label: 'Per Diem', data: perdiumData, backgroundColor: '#10b981' },
            { label: 'Fuel', data: fuelData, backgroundColor: '#f59e0b' }
          ]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          scales: {
            x: { stacked: true, ticks: { color: textColor } },
            y: { stacked: true, ticks: { color: textColor } }
          },
          plugins: { legend: { labels: { color: textColor } } }
        }
      });
    }

    function sortTable(tableId, col, isNumeric = false) {
      const table = document.getElementById(tableId);
      const tbody = table.tBodies[0];
      const rows = Array.from(tbody.rows);
      const dir = (table.dataset.sortDir || 'asc') === 'asc' ? 'desc' : 'asc';
      table.dataset.sortDir = dir;

      rows.sort((a,b) => {
        let valA = a.cells[col].textContent.trim();
        let valB = b.cells[col].textContent.trim();
        if (isNumeric) {
          valA = parseFloat(valA.replace(/,/g, '')) || 0;
          valB = parseFloat(valB.replace(/,/g, '')) || 0;
        }
        return (valA < valB ? -1 : (valA > valB ? 1 : 0)) * (dir === 'asc' ? 1 : -1);
      });
      rows.forEach(row => tbody.appendChild(row));
    }
  </script>
</body>
</html>