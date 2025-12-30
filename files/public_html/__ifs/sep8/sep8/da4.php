<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/ethiopian-date@1.0.0/lib/ethiopian-date.min.js"></script>
    <script>
        tailwind.config = {
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
                        dark: '#1e293b'
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
            --accent: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #334155;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .card-hover {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            transition: width 1s ease-in-out;
        }
        
        .sidebar {
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 0 30px rgba(99, 102, 241, 0.4);
            color: white;
            transition: transform 0.3s ease;
        }
        
        .sidebar.collapsed {
            transform: translateX(-280px);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-280px);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        .main-content {
            transition: margin-left 0.3s ease;
            margin-left: 280px;
            width: calc(100% - 280px);
        }
        
        .main-content.expanded {
            margin-left: 0;
            width: 100%;
        }
        
        .stats-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
        }
        
        .gradient-text {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-item {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            margin: 0.25rem 0.5rem;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn {
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .table-row {
            transition: all 0.3s ease;
        }
        
        .table-row:hover {
            background: #f8fafc;
            transform: scale(1.01);
        }
        
        .ethiopian-date {
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            text-align: center;
            margin-top: 5px;
            font-size: 1.1em;
            color: #fff;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 12px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="text-slate-700">
    <!-- Menu Toggle Button (Mobile Only) -->
    <div class="menu-toggle fixed top-4 left-4 z-1100 bg-primary text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg md:hidden" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="p-6">
            <div class="flex items-center justify-center mb-10">
                <div class="bg-white/20 p-3 rounded-xl mr-3">
                    <i class="fas fa-wallet text-amber-300 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-white">Budget System</h2>
            </div>
            <ul class="space-y-3">
                <li class="nav-item active">
                    <a href="dashboard.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-4">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="budget_adding.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-plus-circle w-6 text-center"></i>
                        <span class="ml-4">Budget Adding</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings_owners.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-building w-6 text-center"></i>
                        <span class="ml-4">Settings Owners</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings_codes.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-code w-6 text-center"></i>
                        <span class="ml-4">Settings Codes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="transaction.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-exchange-alt w-6 text-center"></i>
                        <span class="ml-4">Transaction</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="fuel_management.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-gas-pump w-6 text-center"></i>
                        <span class="ml-4">Fuel Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="perdium.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-dollar-sign w-6 text-center"></i>
                        <span class="ml-4">Perdium Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="users_management.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span class="ml-4">Users Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="logout.php" class="flex items-center p-4 text-base font-normal rounded-lg text-white/90 hover:text-white">
                        <i class="fas fa-sign-out-alt w-6 text-center"></i>
                        <span class="ml-4">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <!-- Header -->
            <div class="glass-effect flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 rounded-2xl shadow-sm">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Welcome to Financial Management System, <span class="gradient-text"><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?></span>!
                    </h1>
                    <p class="text-slate-600 mt-2">Here's your financial overview and analytics</p>
                    <!-- Date Display -->
                    <div class="flex items-center mt-4 bg-indigo-100 rounded-xl p-3 max-w-md shadow-inner">
                        <i class="fas fa-calendar-alt text-indigo-600 mr-3"></i>
                        <span class="text-indigo-800 font-semibold" id="currentDateTime">
                            <?php echo $currentDate . ' | ' . $currentTime; ?>
                        </span>
                    </div>
                    <div class="ethiopian-date mt-2 bg-indigo-600 rounded-lg p-2 max-w-md" id="ethiopianDate">
                        Loading Ethiopian date...
                    </div>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="btn btn-primary flex items-center" onclick="window.print()">
                        <i class="fas fa-print mr-2"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="glass-effect rounded-2xl p-6 card-hover mb-8">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-slate-800"><i class="fas fa-filter mr-2 text-indigo-600"></i> Data Filters</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Budget Owner</label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" id="ownerFilter">
                            <option value="">All Owners</option>
                            <?php 
                            // Fetch budget owners from database
                            $owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
                            foreach ($owners as $owner): 
                            ?>
                                <option value="<?php echo $owner['id']; ?>"><?php echo htmlspecialchars($owner['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Date Range</label>
                        <div class="flex space-x-3">
                            <input type="date" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" id="startDate">
                            <span class="self-center text-slate-500">to</span>
                            <input type="date" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" id="endDate">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Budget Status</label>
                        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" id="statusFilter">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="overbudget">Over Budget</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl transition" onclick="clearFilters()">
                        Clear Filters
                    </button>
                    <button class="btn btn-primary" onclick="applyFilters()">
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Allocated</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?php echo number_format($total_allocated, 2); ?> ETB</h3>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-xl">
                            <i class="fas fa-money-bill-wave text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>This Year</span>
                            <span>+15.2%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2 bg-gradient-to-r from-indigo-500 to-purple-600" style="width: 75%"></div>
                        </div>
                    </div>
                </div>

                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Used</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?php echo number_format($total_used, 2); ?> ETB</h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-xl">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Utilization</span>
                            <span><?php echo $total_allocated > 0 ? number_format(($total_used / $total_allocated) * 100, 2) : '0'; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2 bg-gradient-to-r from-green-500 to-emerald-600" style="width: <?php echo $total_allocated > 0 ? ($total_used / $total_allocated) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Remaining Budget</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?php echo number_format($total_remaining, 2); ?> ETB</h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-wallet text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Available</span>
                            <span><?php echo $total_allocated > 0 ? number_format(($total_remaining / $total_allocated) * 100, 2) : '0'; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2 bg-gradient-to-r from-blue-500 to-cyan-600" style="width: <?php echo $total_allocated > 0 ? ($total_remaining / $total_allocated) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Monthly Average</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1"><?php echo number_format($total_used / 12, 2); ?> ETB</h3>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-xl">
                            <i class="fas fa-chart-pie text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Spending Trend</span>
                            <span>+5.3%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2 bg-gradient-to-r from-purple-500 to-pink-600" style="width: 53%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Budget Allocation by Owner -->
                <div class="glass-effect rounded-2xl p-6 card-hover">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-slate-800">Budget Allocation by Owner</h2>
                        <div class="flex space-x-2">
                            <button class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-2 rounded-lg text-xs">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="bg-slate-100 hover:bg-slate-200 text-slate-700 p-2 rounded-lg text-xs">
                                <i class="fas fa-expand"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="allocationChart"></canvas>
                    </div>
                </div>

                <!-- Budget Usage Trend -->
                <div class="glass-effect rounded-2xl p-6 card-hover">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-slate-800">Budget Usage Trend</h2>
                        <select class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm w-auto" id="trendFilter">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="glass-effect rounded-2xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Recent Transactions</h2>
                    <a href="transaction.php" class="text-indigo-600 hover:text-indigo-700 text-sm flex items-center font-medium">
                        View all <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </div>

                <div class="overflow-x-auto rounded-2xl">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th scope="col" class="px-6 py-4">Date</th>
                                <th scope="col" class="px-6 py-4">Owner</th>
                                <th scope="col" class="px-6 py-4">Code</th>
                                <th scope="col" class="px-6 py-4">Amount</th>
                                <th scope="col" class="px-6 py-4">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_transactions)): ?>
                                <tr class="table-row border-b border-slate-200">
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No recent transactions found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr class="table-row border-b border-slate-200">
                                    <td class="px-6 py-4"><?php echo date('M j, Y', strtotime($transaction['date'])); ?></td>
                                    <td class="px-6 py-4 font-medium"><?php echo htmlspecialchars($transaction['owner_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo htmlspecialchars($transaction['code_name']); ?></td>
                                    <td class="px-6 py-4"><?php echo number_format($transaction['amount'], 2); ?> ETB</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            <?php echo $transaction['amount'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo $transaction['amount'] > 0 ? 'Income' : 'Expense'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Budget Owners Section -->
            <div class="glass-effect rounded-2xl p-6 card-hover mt-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Budget Owners Summary</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($directorate_data as $owner): ?>
                    <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="text-lg font-semibold text-slate-800"><?php echo htmlspecialchars($owner['name']); ?></h3>
                            <span class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-1 rounded-full">
                                ID: <?php echo $owner['id']; ?>
                            </span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm text-slate-600">Allocated Budget</p>
                                <p class="text-lg font-bold text-slate-800"><?php echo number_format($owner['allocated'], 2); ?> ETB</p>
                            </div>
                            <div>
                                <p class="text-sm text-slate-600">Used Budget</p>
                                <p class="text-lg font-bold text-slate-800"><?php echo number_format($owner['used'], 2); ?> ETB</p>
                            </div>
                            <div>
                                <p class="text-sm text-slate-600">Remaining Budget</p>
                                <p class="text-lg font-bold text-slate-800"><?php echo number_format($owner['remaining'], 2); ?> ETB</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-sm text-slate-500 mb-1">
                                <span>Utilization</span>
                                <span><?php echo $owner['allocated'] > 0 ? number_format(($owner['used'] / $owner['allocated']) * 100, 2) : '0'; ?>%</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2">
                                <div class="h-2 rounded-full bg-gradient-to-r from-green-500 to-emerald-600" style="width: <?php echo $owner['allocated'] > 0 ? ($owner['used'] / $owner['allocated']) * 100 : 0; ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Footer -->
            <footer class="mt-10 text-center text-slate-500 text-sm">
                <p>Powered by: <a href="https://ali.et" class="text-indigo-600 hover:text-indigo-700 font-medium" target="_blank">Ali Abdela</a> - All rights reserved © <?php echo date('Y'); ?></p>
            </footer>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('menuToggle');
            
            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('active');
                });
            }
            
            // Initialize Select2
            $('#ownerFilter').select2({
                placeholder: "Select a budget owner",
                allowClear: true
            });
            
            // Update Ethiopian date
            updateEthiopianDate();
            
            // Initialize charts
            initCharts();
            
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

        // Ethiopian Date Conversion
        function getEthiopianDate() {
            const now = new Date();
            try {
                const ethiopianDate = EthiopianDate.toEthiopian(now);
                const day = ethiopianDate[2];
                const month = ethiopianDate[1];
                const year = ethiopianDate[0];
                
                // Ethiopian month names
                const monthNames = [
                    "መስከረም", "ጥቅምት", "ኅዳር", "ታኅሳስ", 
                    "ጥር", "የካቲት", "መጋቢት", "ሚያዝያ", 
                    "ግንቦት", "ሰኔ", "ሐምሌ", "ነሐሴ", "ጳጉሜ"
                ];
                
                return day + " " + monthNames[month - 1] + " " + year;
            } catch (e) {
                console.error("Error converting to Ethiopian date:", e);
                return "Ethiopian date unavailable";
            }
        }

        // Update Ethiopian date
        function updateEthiopianDate() {
            document.getElementById('ethiopianDate').textContent = getEthiopianDate();
        }

        // Clear all filters
        function clearFilters() {
            $('#ownerFilter').val(null).trigger('change');
            $('#startDate').val('');
            $('#endDate').val('');
            $('#statusFilter').val('');
            
            showNotification('Filters cleared successfully', 'success');
        }

        // Apply filters
        function applyFilters() {
            const owner = $('#ownerFilter').val();
            const startDate = $('#startDate').val();
            const endDate = $('#endDate').val();
            const status = $('#statusFilter').val();
            
            // In a real application, you would send these filters to the server
            // and update the dashboard data accordingly
            console.log('Applying filters:', { owner, startDate, endDate, status });
            showNotification('Filters applied successfully', 'success');
        }

        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transition-opacity duration-300 ${
                type === 'success' ? 'bg-green-100 text-green-800' : 
                type === 'error' ? 'bg-red-100 text-red-800' : 
                'bg-blue-100 text-blue-800'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Initialize charts
        function initCharts() {
            // Budget Allocation Chart
            const allocationCtx = document.getElementById('allocationChart').getContext('2d');
            const allocationChart = new Chart(allocationCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php 
                        $labels = [];
                        foreach ($directorate_data as $owner) {
                            $labels[] = "'" . htmlspecialchars($owner['name']) . "'";
                        }
                        echo implode(', ', $labels);
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php 
                            $values = [];
                            foreach ($directorate_data as $owner) {
                                $values[] = $owner['allocated'];
                            }
                            echo implode(', ', $values);
                            ?>
                        ],
                        backgroundColor: [
                            '#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6',
                            '#3b82f6', '#ef4444', '#84cc16', '#f97316', '#06b6d4'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: '#334155',
                                font: { family: 'Inter', size: 12 }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });

            // Budget Trend Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const trendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [
                        {
                            label: 'Allocated',
                            data: [120000, 110000, 130000, 125000, 140000, 135000, 150000, 145000, 160000, 155000, 170000, 165000],
                            borderColor: '#6366f1',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Used',
                            data: [80000, 85000, 90000, 95000, 100000, 105000, 110000, 115000, 120000, 125000, 130000, 135000],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' ETB';
                                }
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#334155'
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>