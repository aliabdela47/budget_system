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

// Get current date and time
$currentDate = date('F j, Y');
$currentTime = date('H:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard - Budget System</title>
    <script src="css/tailwind.css"></script>

   <link rel="stylesheet" href="css/all.min.css">
     <script src="js/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        secondary: '#7c3aed',
                        light: '#f8fafc',
                        lighter: '#f1f5f9',
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #334155;
        }
        
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .progress-bar {
            background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 100%);
            height: 8px;
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }
        
        .sidebar {
            width: 260px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            background: linear-gradient(180deg, #4f46e5 0%, #7c3aed 100%);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            color: white;
            transition: transform 0.3s ease;
        }
        
        .sidebar.collapsed {
            transform: translateX(-260px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-260px);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        .main-content {
            transition: margin-left 0.3s ease;
            margin-left: 260px;
            width: calc(100% - 260px);
        }
        
        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }
    </style>
</head>
<body class="text-slate-700">
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-5">
            <div class="flex items-center justify-center mb-8">
                <i class="fas fa-wallet text-amber-300 text-3xl mr-3"></i>
                <h2 class="text-xl font-bold text-white">Budget System</h2>
            </div>
            <ul class="space-y-2">
                <li>
                    <a href="dashboard.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white bg-white/20">
                        <i class="fas fa-tachometer-alt w-5"></i>
                        <span class="ml-3">Dashboard</span>
                    </a>
                </li>
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li>
                        <a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                            <i class="fas fa-plus-circle w-5"></i>
                            <span class="ml-3">Budgets</span>
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
                <?php endif; ?>
                <li>
                    <a href="transaction.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-exchange-alt w-5"></i>
                        <span class="ml-3">Transaction</span>
                    </a>
                </li>
                <li>
                    <a href="fuel_management.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-gas-pump w-5"></i>
                        <span class="ml-3">Fuel Management</span>
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
                        Welcome to Afar Regional Health Bureau's Financial System,
                        <span class="text-indigo-600"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!
                    </h1>
                    <p class="text-slate-600 mt-2">Here's your financial overview</p>
                    <!-- Date Display -->
                    <div class="flex items-center mt-3 bg-indigo-100 rounded-lg p-2 max-w-md">
                        <i class="fas fa-calendar-alt text-indigo-600 mr-2"></i>
                        <span class="text-indigo-800 font-semibold">
                            <?php echo $currentDate; ?> | <?php echo $currentTime; ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg flex items-center transition shadow-md" onclick="window.print()">
                        <i class="fas fa-print mr-2"></i> Print Report
                    </button>
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl p-6 card-hover">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Allocated</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?php echo number_format($total_allocated, 2); ?> ብር </h3>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Total Budget</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 card-hover">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Used</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?php echo number_format($total_used, 2); ?> ብር</h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Utilization: <?php echo $total_allocated > 0 ? number_format(($total_used / $total_allocated) * 100, 2) : '0'; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: <?php echo $total_allocated > 0 ? ($total_used / $total_allocated) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 card-hover">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Remaining Budget</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?php echo number_format($remaining_budget, 2); ?> ብር</h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-wallet text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Available: <?php echo $total_allocated > 0 ? number_format(($remaining_budget / $total_allocated) * 100, 2) : '0'; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: <?php echo $total_allocated > 0 ? ($remaining_budget / $total_allocated) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Budget Usage Proportion -->
                <div class="bg-white rounded-xl p-6 card-hover">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Usage Proportion (%)</h2>
                    <canvas id="pieChart" height="300"></canvas>
                </div>

                <!-- Allocated vs Used Budget -->
                <div class="bg-white rounded-xl p-6 card-hover">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Allocated vs Used Budget</h2>
                    <canvas id="barChart" height="300"></canvas>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white rounded-xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Recent Transactions</h2>
                    <a href="transaction.php" class="text-indigo-600 hover:text-indigo-700 text-sm flex items-center font-medium">
                        View all <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th scope="col" class="px-4 py-3">ID</th>
                                <th scope="col" class="px-4 py-3">Directorate</th>
                                <th scope="col" class="px-4 py-3">Code</th>
                                <th scope="col" class="px-4 py-3">Employee</th>
                                <th scope="col" class="px-4 py-3">Amount</th>
                                <th scope="col" class="px-4 py-3">Month</th>
                                <th scope="col" class="px-4 py-3">Quarter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $t): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50">
                                    <td class="px-4 py-2 font-medium"><?php echo $t['id']; ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['owner_code']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['budget_code']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['employee_name']); ?></td>
                                    <td class="px-4 py-2 font-medium"><?php echo number_format($t['amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($t['et_month']); ?></td>
                                    <td class="px-4 py-2"><?php echo $t['quarter']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <footer class="mt-10 text-center text-slate-500 text-sm">
                <p>Powered by: <a href="https://ali.et" class="text-indigo-600 hover:text-indigo-700 font-medium" target="_blank">Ali Abdela</a> - All rights reserved © <?php echo date('Y'); ?></p>
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
                    backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { 
                        position: 'bottom', 
                        labels: { 
                            color: '#334155',
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
                        backgroundColor: '#4f46e5', 
                        borderRadius: 5 
                    },
                    { 
                        label: 'Used', 
                        data: directorateData.map(d => d.used || 0), 
                        backgroundColor: '#10b981', 
                        borderRadius: 5 
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { color: '#64748b' }
                    },
                    x: { 
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: { color: '#64748b' }
                    }
                },
                plugins: {
                    legend: { 
                        position: 'top', 
                        labels: { color: '#334155' } 
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