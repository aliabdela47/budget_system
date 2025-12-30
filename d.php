<?php

// Dashboard Page

// Includes
// Make sure this file sets up the database connection and session.
require_once 'includes/init.php';

// Security: Require the user to be logged in to view the dashboard.
// The `require_login()` function from `init.php` would be a more robust alternative.
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Security headers to prevent common web vulnerabilities.
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; img-src 'self' data: https://via.placeholder.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// --- Helper Functions ---

/**
 * Escapes special characters in a string for safe HTML output.
 *
 * @param string $s The string to escape.
 * @return string The escaped string.
 */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Formats a number as currency.
 *
 * @param float $n The number to format.
 * @return string The formatted number.
 */
function fmt($n) {
    return number_format((float)$n, 2);
}

/**
 * Calculates the Ethiopian calendar year (EC).
 *
 * @return int The current Ethiopian calendar year.
 */
function ecYear() {
    return (int)date('Y') - 8;
}

// --- Data Fetching and Processing ---

// Fetch the user's display name.
$user_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$display_name = !empty($user_data['name']) ? $user_data['name'] : ($_SESSION['username'] ?? 'User');

// Build key datasets
// Allocation/Used by owner (joins budgets and sums from 3 sources)
$directorate_data = [];
$sql = "
    SELECT
        o.id,
        o.code,
        o.name,
        COALESCE(SUM(b.yearly_amount + b.monthly_amount), 0) AS allocated,
        (
            COALESCE((SELECT SUM(t.amount) FROM transactions t WHERE t.owner_id = o.id), 0) +
            COALESCE((SELECT SUM(p.total_amount) FROM perdium_transactions p WHERE p.budget_owner_id = o.id), 0) +
            COALESCE((SELECT SUM(f.total_amount) FROM fuel_transactions f WHERE f.owner_id = o.id), 0)
        ) AS used
    FROM
        budget_owners o
    LEFT JOIN budgets b ON b.owner_id = o.id
    GROUP BY
        o.id, o.code, o.name
    ORDER BY
        allocated DESC";

$stmt = $pdo->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['allocated'] = (float)$row['allocated'];
    $row['used'] = (float)$row['used'];
    $row['remaining'] = max(0, $row['allocated'] - $row['used']);
    $directorate_data[] = $row;
}

// Totals for the stats cards (KPIs)
$total_allocated = 0;
$total_used = 0;
foreach ($directorate_data as $d) {
    $total_allocated += $d['allocated'];
    $total_used += $d['used'];
}
$total_remaining = max(0, $total_allocated - $total_used);
$utilization_percentage = $total_allocated > 0 ? ($total_used / $total_allocated) * 100 : 0;
$remaining_percentage = 100 - $utilization_percentage;

// Chart data (Allocation by owner, top 7 + Others)
$chart_labels_arr = [];
$chart_alloc_arr = [];
$others_alloc = 0;
$i = 0;
foreach ($directorate_data as $d) {
    if ($d['allocated'] <= 0) continue;
    if ($i < 7) {
        $chart_labels_arr[] = $d['name'];
        $chart_alloc_arr[] = round($d['allocated'], 2);
    } else {
        $others_alloc += $d['allocated'];
    }
    $i++;
}
if ($others_alloc > 0) {
    $chart_labels_arr[] = 'Others';
    $chart_alloc_arr[] = round($others_alloc, 2);
}
$chart_labels = json_encode($chart_labels_arr, JSON_UNESCAPED_UNICODE);
$chart_allocated_data = json_encode($chart_alloc_arr);

// Spending Trend: last 6 months (combined from 3 sources)
$trend_rows = $pdo->query("
    SELECT ym, SUM(total) as total
    FROM (
        SELECT DATE_FORMAT(date, '%Y-%m') ym, SUM(amount) total FROM transactions GROUP BY ym
        UNION ALL
        SELECT DATE_FORMAT(created_at, '%Y-%m') ym, SUM(total_amount) total FROM perdium_transactions GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        UNION ALL
        SELECT DATE_FORMAT(date, '%Y-%m') ym, SUM(total_amount) total FROM fuel_transactions GROUP BY DATE_FORMAT(date, '%Y-%m')
    ) x
    GROUP BY ym
    ORDER BY ym DESC LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
$trend_rows = array_reverse($trend_rows);
$trend_labels = [];
$trend_values = [];
foreach ($trend_rows as $r) {
    $ym = $r['ym'];
    $dt = DateTime::createFromFormat('Y-m', $ym);
    $trend_labels[] = $dt ? $dt->format('M Y') : $ym;
    $trend_values[] = round((float)$r['total'], 2);
}
$trend_labels_json = json_encode($trend_labels);
$trend_values_json = json_encode($trend_values);

// Recent transactions (unified across 3 sources)
$recent = [];
// Generic transactions
$rows = $pdo->query("
    SELECT
        'General' AS src, t.id, t.date AS dt, o.name AS owner_name, c.name AS code_name, t.employee_name, t.amount
    FROM
        transactions t
    LEFT JOIN budget_owners o ON t.owner_id = o.id
    LEFT JOIN budget_codes c ON t.code_id = c.id
    ORDER BY
        t.date DESC, t.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $x) {
    $recent[] = $x;
}
// Per diem
$rows = $pdo->query("
    SELECT
        'Per Diem' AS src, p.id, p.created_at AS dt, o.name AS owner_name, 'Per Diem' AS code_name, e.name AS employee_name, p.total_amount AS amount
    FROM
        perdium_transactions p
    LEFT JOIN budget_owners o ON p.budget_owner_id = o.id
    LEFT JOIN emp_list e ON p.employee_id = e.id
    ORDER BY
        p.created_at DESC, p.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $x) {
    $recent[] = $x;
}
// Fuel
$rows = $pdo->query("
    SELECT
        'Fuel' AS src, f.id, f.date AS dt, o.name AS owner_name, 'Fuel' AS code_name, f.driver_name AS employee_name, f.total_amount AS amount
    FROM
        fuel_transactions f
    LEFT JOIN budget_owners o ON f.owner_id = o.id
    ORDER BY
        f.date DESC, f.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $x) {
    $recent[] = $x;
}

// Sort recent by date descending, take top 7.
usort($recent, function ($a, $b) {
    return strtotime($b['dt']) <=> strtotime($a['dt']);
});
$recent = array_slice($recent, 0, 7);

// Top spenders (owners) by used amount.
$top_spenders = $directorate_data;
usort($top_spenders, function ($a, $b) {
    return $b['used'] <=> $a['used'];
});
$top_spenders = array_slice($top_spenders, 0, 5);

// Low budget alerts (governmental monthly rows with remaining_monthly ratio).
$low_budgets = $pdo->query("
    SELECT
        b.*, o.code AS owner_code, o.name AS owner_name, c.code AS code_code, c.name AS code_name,
        CASE
            WHEN b.monthly_amount > 0 THEN (b.remaining_monthly / NULLIF(b.monthly_amount, 0))
            ELSE NULL
        END AS ratio
    FROM
        budgets b
    JOIN budget_owners o ON o.id = b.owner_id
    LEFT JOIN budget_codes c ON c.id = b.code_id
    WHERE
        b.budget_type = 'governmental' AND b.monthly_amount > 0
    ORDER BY
        ratio ASC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// For filter dropdowns
$owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$codes = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:   '#6366f1',
                        secondary: '#8b5cf6',
                        accent:    '#ec4899',
                        success:   '#10b981',
                        warning:   '#f59e0b',
                        danger:    '#ef4444',
                        info:      '#3b82f6',
                        light:     '#f8fafc',
                        lighter:   '#f1f5f9',
                        dark:      '#1e293b'
                    }
                }
            }
        }
    </script>
    
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
        }
        .main-content {
            flex: 1;
        }
        .glass {
            background: rgba(255, 255, 255, .7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, .25);
        }
        .card {
            transition: all .3s ease;
            border-radius: 1rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, .06);
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, .1);
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
        .badge {
            padding: .25rem .5rem;
            border-radius: .5rem;
            font-size: .75rem;
        }
    </style>
</head>

<body class="text-slate-700">
    <?php require_once 'includes/sidebar.php'; ?>
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <div class="glass card p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center rounded-2xl">
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800">
                        Welcome, <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary"><?php echo h($display_name); ?></span>
                    </h1>
                    <p class="text-slate-600 mt-2">Here’s the latest financial overview of your bureau.</p>
                    <div class="flex items-center mt-4 bg-indigo-50 rounded-xl p-3 shadow-inner">
                        <i class="fas fa-calendar-alt text-indigo-600 mr-3"></i>
                        <span class="text-indigo-800 font-semibold" id="currentDateTime">Loading…</span>
                    </div>
                </div>
                <div class="flex items-center mt-4 md:mt-0 gap-3">
                    <a href="reports.php" class="btn btn-primary"><i class="fas fa-file-export mr-2"></i>Reports</a>
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-3 rounded-xl md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <a href="budget_adding.php" class="glass card p-5 flex items-center gap-4 hover:shadow-lg">
                    <div class="p-3 bg-indigo-100 text-indigo-700 rounded-xl"><i class="fas fa-circle-plus"></i></div>
                    <div>
                        <div class="text-slate-500 text-sm">Budgets</div>
                        <div class="font-semibold text-slate-800">Add / Manage</div>
                    </div>
                </a>
                <a href="perdium.php" class="glass card p-5 flex items-center gap-4 hover:shadow-lg">
                    <div class="p-3 bg-emerald-100 text-emerald-700 rounded-xl"><i class="fas fa-hand-holding-dollar"></i></div>
                    <div>
                        <div class="text-slate-500 text-sm">Per Diem</div>
                        <div class="font-semibold text-slate-800">Transactions</div>
                    </div>
                </a>
                <a href="fuel_management.php" class="glass card p-5 flex items-center gap-4 hover:shadow-lg">
                    <div class="p-3 bg-amber-100 text-amber-700 rounded-xl"><i class="fas fa-gas-pump"></i></div>
                    <div>
                        <div class="text-slate-500 text-sm">Fuel</div>
                        <div class="font-semibold text-slate-800">Management</div>
                    </div>
                </a>
                <a href="reports.php" class="glass card p-5 flex items-center gap-4 hover:shadow-lg">
                    <div class="p-3 bg-rose-100 text-rose-700 rounded-xl"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="text-slate-500 text-sm">Analytics</div>
                        <div class="font-semibold text-slate-800">Reports</div>
                    </div>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="glass card kpi p-6 relative">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500">Total Allocated</div>
                            <div class="text-2xl font-bold text-slate-800 mt-1"><?php echo fmt($total_allocated); ?> ETB</div>
                        </div>
                        <div class="p-3 rounded-xl bg-indigo-100 text-indigo-700"><i class="fas fa-sack-dollar"></i></div>
                    </div>
                </div>
                <div class="glass card kpi p-6 relative">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500">Total Used</div>
                            <div class="text-2xl font-bold text-slate-800 mt-1"><?php echo fmt($total_used); ?> ETB</div>
                            <div class="progress mt-2"><div style="width: <?php echo round($utilization_percentage, 1); ?>%; background: linear-gradient(90deg,#10b981,#059669);"></div></div>
                        </div>
                        <div class="p-3 rounded-xl bg-emerald-100 text-emerald-700"><i class="fas fa-chart-line"></i></div>
                    </div>
                </div>
                <div class="glass card kpi p-6 relative">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500">Remaining Budget</div>
                            <div class="text-2xl font-bold text-slate-800 mt-1"><?php echo fmt($total_remaining); ?> ETB</div>
                            <div class="progress mt-2"><div style="width: <?php echo round($remaining_percentage, 1); ?>%; background: linear-gradient(90deg,#3b82f6,#06b6d4);"></div></div>
                        </div>
                        <div class="p-3 rounded-xl bg-blue-100 text-blue-700"><i class="fas fa-wallet"></i></div>
                    </div>
                </div>
                <div class="glass card kpi p-6 relative">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-slate-500">Budget Health</div>
                            <div class="text-2xl font-bold text-slate-800 mt-1"><?php echo round($remaining_percentage, 1); ?>% Available</div>
                            <div class="text-xs text-slate-500 mt-1">vs total allocated</div>
                        </div>
                        <div class="p-3 rounded-xl bg-violet-100 text-violet-700"><i class="fas fa-heart-pulse"></i></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 glass card p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-slate-800"><i class="fas fa-chart-pie mr-2 text-indigo-600"></i>Allocation by Owner</h2>
                    </div>
                    <div style="height:320px"><canvas id="allocationChart"></canvas></div>
                </div>
                <div class="glass card p-6">
                    <h2 class="text-xl font-bold text-slate-800 mb-3"><i class="fas fa-triangle-exclamation mr-2 text-amber-600"></i>Low Monthly Budgets</h2>
                    <?php if (empty($low_budgets)): ?>
                        <div class="text-sm text-slate-500">No low budgets detected.</div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($low_budgets as $b):
                                $ratio = is_null($b['ratio']) ? null : (float)$b['ratio'];
                                $percent = is_null($ratio) ? 0 : max(0, min(100, round($ratio * 100, 1)));
                                $colorClass = ($percent <= 10) ? 'bg-red-500' : (($percent <= 30) ? 'bg-amber-500' : 'bg-emerald-500');
                            ?>
                                <div class="p-3 rounded-lg border border-slate-200">
                                    <div class="text-sm font-semibold"><?php echo h($b['owner_code'] . ' - ' . $b['owner_name']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo h(($b['code_code'] ?? '') . ' ' . $b['code_name']); ?> • <?php echo h($b['month'] ?? ''); ?></div>
                                    <div class="mt-2 flex justify-between text-xs text-slate-500">
                                        <span><?php echo fmt($b['remaining_monthly'] ?? 0); ?> ETB left</span>
                                        <span><?php echo fmt($b['monthly_amount'] ?? 0); ?> ETB</span>
                                    </div>
                                    <div class="progress mt-2"><div class="<?php echo $colorClass; ?>" style="width: <?php echo $percent; ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
                <div class="xl:col-span-2 glass card p-6">
                    <h2 class="text-xl font-bold text-slate-800 mb-4"><i class="fas fa-wave-square mr-2 text-emerald-600"></i>Spending Trend (Last 6 Months)</h2>
                    <div style="height:320px"><canvas id="trendChart"></canvas></div>
                </div>
                <div class="glass card p-6">
                    <h2 class="text-xl font-bold text-slate-800 mb-4"><i class="fas fa-ranking-star mr-2 text-violet-600"></i>Top Spenders</h2>
                    <?php if (empty($top_spenders)): ?>
                        <div class="text-sm text-slate-500">No spending data.</div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($top_spenders as $ts):
                                $u = (float)$ts['used'];
                                $a = (float)$ts['allocated'];
                                $pct = $a > 0 ? round(($u / $a) * 100, 1) : 0;
                            ?>
                                <div class="p-3 rounded-lg border border-slate-200">
                                    <div class="flex justify-between text-sm font-semibold">
                                        <span><?php echo h($ts['code'] . ' - ' . $ts['name']); ?></span>
                                        <span class="text-slate-600"><?php echo fmt($u); ?> ETB</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500"><?php echo fmt($a); ?> allocated • <?php echo $pct; ?>% used</div>
                                    <div class="progress mt-2"><div style="width: <?php echo $pct; ?>%; background:linear-gradient(90deg,#6366f1,#8b5cf6)"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="glass card p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-slate-800"><i class="fas fa-clock-rotate-left mr-2 text-blue-600"></i>Recent Activity</h2>
                    <a href="reports.php" class="text-sm text-indigo-700 hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto rounded-2xl">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
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
                                <tr><td colspan="6" class="text-center py-4">No recent activity.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent as $tx): ?>
                                    <tr class="table-row border-b border-slate-200">
                                        <td class="px-6 py-4"><?php echo h(date('M j, Y H:i', strtotime($tx['dt']))); ?></td>
                                        <td class="px-6 py-4"><span class="badge bg-slate-100 text-slate-700"><?php echo h($tx['src']); ?></span></td>
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

            <footer class="mt-10 text-center text-slate-500 text-sm">
                <p>&copy; <span id="currentYear"></span> Budget System • Built with care for the Afar Health Bureau</p>
            </footer>

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
            const allocLabels = <?php echo $chart_labels; ?>;
            const allocData = <?php echo $chart_allocated_data; ?>;
            const allocationCtx = document.getElementById('allocationChart').getContext('2d');
            new Chart(allocationCtx, {
                type: 'doughnut',
                data: {
                    labels: allocLabels,
                    datasets: [{
                        data: allocData,
                        backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6', '#ef4444', '#22c55e'],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });

            const trendCtx = document.getElementById('trendChart').getContext('2d');
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
                        backgroundColor: 'rgba(99,102,241,.15)',
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
