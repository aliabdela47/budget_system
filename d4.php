<?php
session_start();
include 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch data for charts: Allocated and Used per directorate
$directorate_data = [];
$stmt = $pdo->query("SELECT o.id, o.name, SUM(b.yearly_amount) AS allocated, (SELECT SUM(t.amount) FROM transactions t WHERE t.owner_id = o.id) AS used 
                     FROM budget_owners o LEFT JOIN budgets b ON o.id = b.owner_id GROUP BY o.id");
while ($row = $stmt->fetch()) {
    $directorate_data[] = $row;
}

// Fetch recent transactions
$recent_transactions = $pdo->query("SELECT t.*, o.code AS owner_code, c.code AS budget_code 
                                    FROM transactions t 
                                    JOIN budget_owners o ON t.owner_id = o.id 
                                    JOIN budget_codes c ON t.code_id = c.id 
                                    ORDER BY t.date DESC LIMIT 5")->fetchAll();

// Calculate totals
$total_allocated = array_sum(array_column($directorate_data, 'allocated'));
$total_used = array_sum(array_column($directorate_data, 'used'));
$remaining_budget = $total_allocated - $total_used;

// Ethiopian date conversion function
function gregorianToEthiopian($date) {
    $gregorian = new DateTime($date);
    $year = $gregorian->format('Y');
    $month = $gregorian->format('n');
    $day = $gregorian->format('j');
    
    // Calculate Ethiopian year
    $ethiopianYear = $year - 8;
    if ($month < 9 || ($month == 9 && $day < 11)) {
        $ethiopianYear--;
    }
    
    // Ethiopian months in Amharic
    $ethiopianMonths = [
        1 => "መስከረም", 2 => "ጥቅምት", 3 => "ኅዳር", 4 => "ታኅሣሥ", 
        5 => "ጥር", 6 => "የካቲት", 7 => "መጋቢት", 8 => "ሚያዝያ", 
        9 => "ግንቦት", 10 => "ሰኔ", 11 => "ሐምሌ", 12 => "ነሐሴ", 13 => "ጳጉሜ"
    ];
    
    // Calculate Ethiopian month and day (simplified)
    $jd = gregoriantojd($month, $day, $year);
    $ethiopian = jdtoethiopic($jd);
    
    return [
        'day' => $ethiopian[2],
        'month' => $ethiopianMonths[$ethiopian[1]],
        'year' => $ethiopian[0],
        'era' => 'ዓ.ም'
    ];
}

// Fallback function if calendar functions not available
if (!function_exists('jdtoethiopic')) {
    function jdtoethiopic($jd) {
        // Simplified conversion (not precise but works for display)
        $gregorian = jdtogregorian($jd);
        list($gMonth, $gDay, $gYear) = explode('/', $gregorian);
        
        $ethYear = $gYear - 8;
        if ($gMonth < 9 || ($gMonth == 9 && $gDay < 11)) {
            $ethYear--;
        }
        
        // Estimate month and day
        $ethMonth = (($gMonth + 8) % 12) + 1;
        $ethDay = $gDay;
        
        return [0, $ethMonth, $ethDay, $ethYear];
    }
}

// Get current Ethiopian date
$ethiopianDate = gregorianToEthiopian(date('Y-m-d'));

// Get current Ethiopian time (EAT is UTC+3)
$ethiopianTime = date('H:i', time() + 3 * 3600);
$hour = (int)date('H', time() + 3 * 3600);
$timePeriod = ($hour < 12) ? 'ጡዋት' : 'ማታ';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        secondary: '#8b5cf6',
                        dark: '#1f2937',
                        darker: '#111827',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
        }
        
        .ethiopic {
            font-family: 'Noto Sans Ethiopic', sans-serif;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        
        .gradient-border {
            position: relative;
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            padding: 1px;
            border-radius: 0.75rem;
        }
        
        .gradient-border::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 0.75rem;
            padding: 2px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
            height: 8px;
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }
        
        .sidebar {
            width: 260px;
            transition: all 0.3s ease;
            z-index: 1000;
            background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
        }
        
        .sidebar.collapsed {
            margin-left: -260px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                height: 100%;
                margin-left: -260px;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
        }
        
        .main-content {
            transition: margin-left 0.3s ease;
            width: calc(100% - 260px);
            margin-left: 260px;
        }
        
        .main-content.expanded {
            width: 100%;
            margin-left: 0;
        }
        
        .notification-dot {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 12px;
            height: 12px;
            background: #ef4444;
            border-radius: 50%;
        }
    </style>
</head>
<body class="text-gray-200 flex">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-5">
            <div class="flex items-center justify-center mb-8">
                <i class="fas fa-wallet text-indigo-400 text-3xl mr-3"></i>
                <h2 class="text-xl font-bold text-white">Budget System</h2>
            </div>
            <ul class="space-y-2">
                <li>
                    <a href="dashboard.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-gradient-to-r from-indigo-600 to-purple-600">
                        <i class="fas fa-tachometer-alt w-5"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li>
                        <a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-plus-circle w-5"></i>
                            <span class="ml-3">Budgets</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings_owners.php" class="flex items-center p-3 text-base font-normal rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-building w-5"></i>
                            <span class="ml-3">Settings Owners</span>
                        </a>
                    </li>
                    <li>
                        <a href="settings_codes.php" class="flex items-center p-3 text-base font-normal rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                            <i class="fas fa-code w-5"></i>
                            <span class="ml-3">Settings Codes</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="transaction.php" class="flex items-center p-3 text-base font-normal rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                        <i class="fas fa-exchange-alt w-5"></i>
                        <span class="ml-3">Transaction</span>
                    </a>
                </li>
                <li>
                    <a href="fuel_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                        <i class="fas fa-gas-pump w-5"></i>
                        <span class="ml-3">Fuel Management</span>
                    </a>
                </li>
                <li>
                    <a href="users_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
                        <i class="fas fa-users w-5"></i>
                        <span class="ml-3">Users Management</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="flex items-center p-3 text-base font-normal rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white">
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
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 gradient-border">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold">
                        <span class="text-white">Welcome to Afar Regional Health Bureau's Financial System,</span>
                        <span class="text-indigo-300"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!
                    </h1>
                    <p class="text-indigo-200 mt-2">Here's your financial overview</p>
                    <!-- Ethiopian Date Display -->
                    <div class="flex items-center mt-3 bg-indigo-900/30 rounded-lg p-2 max-w-md">
                        <i class="fas fa-calendar-alt text-amber-400 mr-2"></i>
                        <span class="text-amber-300 ethiopic font-semibold">
                            <?php echo $ethiopianDate['month'] . ' ' . $ethiopianDate['day'] . ' ' . $ethiopianDate['year'] . ' ' . $ethiopianDate['era']; ?>
                            | <?php echo $ethiopianTime . ' ' . $timePeriod; ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white px-4 py-2 rounded-lg flex items-center transition" onclick="window.print()">
                        <i class="fas fa-print mr-2"></i> Print Report
                    </button>
                    <button class="bg-gray-700 hover:bg-gray-600 text-white p-2 rounded-lg md:hidden" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-6 card-hover">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400">Total Allocated</p>
                            <h3 class="text-2xl font-bold text-white mt-1"><?php echo number_format($total_allocated, 2); ?> ETB</h3>
                        </div>
                        <div class="bg-indigo-500 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-400 mb-1">
                            <span>Total Budget</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-6 card-hover">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400">Total Used</p>
                            <h3 class="text-2xl font-bold text-white mt-1"><?php echo number_format($total_used, 2); ?> ETB</h3>
                        </div>
                        <div class="bg-green-500 p-3 rounded-lg">
                            <i class="fas fa-chart-line text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-400 mb-1">
                            <span>Utilization: <?php echo $total_allocated > 0 ? number_format(($total_used / $total_allocated) * 100, 2) : '0'; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: <?php echo $total_allocated > 0 ? ($total_used / $total_allocated) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-6 card-hover">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-gray-400">Remaining Budget</p>
                            <h3 class="text-2xl font-bold text-white mt-1"><?php echo number_format($remaining_budget, 2); ?> ETB</h3>
                        </div>
                        <div class="bg-blue-500 p-3 rounded-lg">
                            <i class="fas fa-wallet text-white text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-gray-400 mb-1">
                            <span>Available: <?php echo $total_allocated > 0 ? number_format(($remaining_budget / $total_allocated) * 100, 2) : '0'; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: <?php echo $total_allocated > 0 ? ($remaining_budget / $total_allocated) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Budget Usage Proportion -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-6 card-hover">
                    <h2 class="text-xl font-bold text-white mb-6">Budget Usage Proportion (%)</h2>
                    <canvas id="pieChart" height="300"></canvas>
                </div>

                <!-- Allocated vs Used Budget -->
                <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-6 card-hover">
                    <h2 class="text-xl font-bold text-white mb-6">Allocated vs Used Budget</h2>
                    <canvas id="barChart" height="300"></canvas>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-white">Recent Transactions</h2>
                    <a href="transaction.php" class="text-indigo-400 hover:text-indigo-300 text-sm flex items-center">
                        View all <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-400">
                        <thead class="text-xs uppercase bg-gray-700 text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">ID</th>
                                <th scope="col" class="px-4 py-3">Owner</th>
                                <th scope="col" class="px-4 py-3">Code</th>
                                <th scope="col" class="px-4 py-3">Employee</th>
                                <th scope="col" class="px-4 py-3">Amount</th>
                                <th scope="col" class="px-4 py-3">Month</th>
                                <th scope="col" class="px-4 py-3">Quarter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $t): ?>
                                <tr class="border-b bg-gray-800 border-gray-700 hover:bg-gray-700">
                                    <td class="px-4 py-2"><?php echo $t['id']; ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['owner_code']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['budget_code']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['employee_name']); ?></td>
                                    <td class="px-4 py-2"><?php echo number_format($t['amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['et_month']); ?></td>
                                    <td class="px-4 py-2"><?php echo $t['quarter']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <footer class="mt-10 text-center text-gray-500 text-sm">
                <p>Developed/Powered by: <a href="https://ali.et" class="text-indigo-400 hover:text-indigo-300" target="_blank">Ali Abdela</a> - All rights reserved <?php echo date('Y'); ?></p>
            </footer>
        </div>
    </div>

    <script>
        const directorateData = <?php echo json_encode($directorate_data); ?>;

        // Pie Chart: Usage Proportion
        const pieCtx = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: directorateData.map(d => d.name),
                datasets: [{
                    label: 'Used Budget Proportion',
                    data: directorateData.map(d => ((d.used / d.allocated) * 100 || 0).toFixed(2)),
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'],
                    borderColor: '#1f2937',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            color: '#e5e7eb',
                            font: { family: 'Inter', size: 12 } 
                        } 
                    },
                },
                animation: { animateScale: true, animateRotate: true }
            }
        });

        // Bar Chart: Allocated vs Used
        const barCtx = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: directorateData.map(d => d.name),
                datasets: [
                    { 
                        label: 'Allocated', 
                        data: directorateData.map(d => d.allocated || 0), 
                        backgroundColor: '#36A2EB', 
                        borderRadius: 5 
                    },
                    { 
                        label: 'Used', 
                        data: directorateData.map(d => d.used || 0), 
                        backgroundColor: '#FF6384', 
                        borderRadius: 5 
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: '#e5e7eb' }
                    },
                    x: { 
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: '#e5e7eb' }
                    }
                },
                plugins: {
                    legend: { 
                        position: 'top', 
                        labels: { color: '#e5e7eb' } 
                    }
                }
            }
        });

        // Sidebar Toggle
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
            
            // Animate progress bars
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>