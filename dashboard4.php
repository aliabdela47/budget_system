<?php
require_once 'includes/init.php';

// Fetch user's name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$display_name = !empty($user_data['name']) ? $user_data['name'] : $_SESSION['username'];

// Get Amharic Date/Time
$amharic_date_time = getAmharicEtDate();

// For filters dropdowns
$owners = $pdo->query("SELECT id, code, name FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$codes = $pdo->query("SELECT id, code, name FROM budget_codes ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1', secondary: '#8b5cf6', accent: '#ec4899',
                        success: '#10b981', warning: '#f59e0b', danger: '#ef4444',
                        info: '#3b82f6', light: '#f8fafc', lighter: '#f1f5f9', dark: '#1e293b'
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        :root { --primary: #6366f1; --secondary: #8b5cf6; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); min-height: 100vh; color: #334155; display: flex; }
        .main-content { flex: 1; }
        .glass-effect { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .card-hover { transition: all 0.3s ease; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07), 0 2px 4px -1px rgba(0,0,0,0.04); }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); }
        .progress-bar { height: 8px; border-radius: 4px; transition: width 1s ease-in-out; }
        .input-group { transition: all 0.3s ease; border: 1px solid #d1d5db; border-radius: 0.75rem; padding: 0.6rem 1rem; display: flex; align-items: center; background: white; }
        .input-group:focus-within { transform: translateY(-2px); border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        .filter-section { transition: max-height 0.5s ease-in-out; overflow: hidden; max-height: 0; }
        .filter-section.open { max-height: 500px; }
        .chart-container { position: relative; height: 300px; width: 100%; }
        .stats-card { transition: transform 0.3s ease, box-shadow 0.3s ease; border-radius: 1rem; overflow: hidden; position: relative; }
        .stats-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--primary), var(--secondary)); }
        .stats-card:hover { transform: translateY(-8px); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); }
        .gradient-text { background: linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .btn { transition: all 0.3s ease; border-radius: 0.75rem; padding: 0.6rem 1.5rem; font-weight: 500; }
        .btn-primary { background: linear-gradient(90deg, var(--primary), var(--secondary)); color: white; box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4); }
        .table-row { transition: all 0.3s ease; }
        .table-row:hover { background: #f8fafc; transform: scale(1.01); }
    </style>
</head>
<body class="text-slate-700">
    <!-- Centralized, Role-Based Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <!-- Header -->
            <div class="glass-effect flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 rounded-2xl shadow-sm">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Welcome, <span class="gradient-text"><?php echo htmlspecialchars($display_name); ?></span>!
                    </h1>
                    <p class="text-slate-600 mt-2">Here's your financial overview for the Afar Health Bureau.</p>
                    <div class="flex items-center mt-4 bg-indigo-100 rounded-xl p-3 max-w-md shadow-inner">
                        <i class="fas fa-calendar-alt text-indigo-600 mr-3"></i>
                        <span class="text-indigo-800 font-semibold" id="currentDateTime">
                            <?php echo htmlspecialchars($amharic_date_time); ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="btn btn-primary flex items-center" onclick="window.print()"><i class="fas fa-print mr-2"></i> Print Report</button>
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-3 rounded-xl md:hidden shadow-sm" id="sidebarToggleMobile"><i class="fas fa-bars"></i></button>
                </div>
            </div>

            <!-- Flash message for unauthorized access -->
            <?php flash(); ?>

            <!-- Filter Section -->
            <div class="glass-effect rounded-2xl p-6 card-hover mb-8">
                <div class="flex justify-between items-center cursor-pointer" onclick="toggleFilterSection()">
                    <h2 class="text-xl font-bold text-slate-800"><i class="fas fa-filter mr-2 text-indigo-600"></i> Data Filters</h2>
                    <i class="fas fa-chevron-down text-slate-500 transition-transform" id="filterIcon"></i>
                </div>
                <div class="filter-section mt-6" id="filterSection">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Date Range</label>
                            <div class="flex space-x-3">
                                <input type="date" class="input-group flex-1" id="startDate">
                                <span class="self-center text-slate-500">to</span>
                                <input type="date" class="input-group flex-1" id="endDate">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Owner</label>
                            <select class="input-group w-full" id="ownerFilter">
                                <option value="">All Owners</option>
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?php echo $owner['id']; ?>"><?php echo htmlspecialchars($owner['code'] . ' | ' . $owner['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Code</label>
                            <select class="input-group w-full" id="typeFilter">
                                <option value="">All Codes</option>
                                <?php foreach ($codes as $code): ?>
                                    <option value="<?php echo $code['id']; ?>"><?php echo htmlspecialchars($code['code'] . ' | ' . $code['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl transition" onclick="clearFilters()">Clear Filters</button>
                        <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Allocated</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1" id="total_allocated_card">...</h3>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-xl"><i class="fas fa-money-bill-wave text-indigo-600 text-xl"></i></div>
                    </div>
                </div>
                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Used</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1" id="total_used_card">...</h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-xl"><i class="fas fa-chart-line text-green-600 text-xl"></i></div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Utilization</span>
                            <span id="utilization_percentage_text">...</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div id="utilization_progress_bar" class="progress-bar rounded-full h-2 bg-gradient-to-r from-green-500 to-emerald-600" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Remaining Budget</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1" id="total_remaining_card">...</h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-xl"><i class="fas fa-wallet text-blue-600 text-xl"></i></div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 gap-8 mb-8">
                <div class="glass-effect rounded-2xl p-6 card-hover">
                    <h2 class="text-xl font-bold text-slate-800 mb-6">Allocation by Directorate</h2>
                    <div class="chart-container"><canvas id="allocationChart"></canvas></div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="glass-effect rounded-2xl p-6 card-hover">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Recent Transactions</h2>
                <div class="overflow-x-auto rounded-2xl">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Owner</th>
                                <th class="px-6 py-4">Code</th>
                                <th class="px-6 py-4">Employee</th>
                                <th class="px-6 py-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="recent_transactions_tbody">
                           <tr><td colspan="5" class="text-center py-4">Loading transactions...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer class="mt-10 text-center text-slate-500 text-sm">
                <p>Powered by: Ali Abdela - All rights reserved © <span id="currentYear"></span></p>
            </footer>
        </div>
    </div>

    <script>
        let allocationChart; // Global chart instance

        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('currentYear').textContent = new Date().getFullYear();
            initCharts();
            applyFilters(); // Load initial data
        });

        function toggleFilterSection() {
            document.getElementById('filterSection').classList.toggle('open');
            document.getElementById('filterIcon').classList.toggle('fa-chevron-down');
            document.getElementById('filterIcon').classList.toggle('fa-chevron-up');
        }

        function clearFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('ownerFilter').value = '';
            document.getElementById('typeFilter').value = '';
            applyFilters();
            showNotification('Filters cleared.', 'info');
        }

        async function applyFilters() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const ownerId = document.getElementById('ownerFilter').value;
            const codeId = document.getElementById('typeFilter').value;

            const url = `ajax_dashboard_data.php?startDate=${startDate}&endDate=${endDate}&ownerFilter=${ownerId}&typeFilter=${codeId}`;

            try {
                const response = await fetch(url);
                const data = await response.json();
                if (!data.ok) throw new Error(data.message || 'Failed to fetch data');
                updateDashboard(data);
            } catch (error) {
                console.error('Filter Error:', error);
                showNotification('Could not load filtered data.', 'danger');
            }
        }

        function updateDashboard(data) {
            const formatETB = (num) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'ETB' }).format(num).replace('ETB', '') + ' ETB';

            // Update Stats Cards
            document.getElementById('total_allocated_card').textContent = formatETB(data.stats.total_allocated);
            document.getElementById('total_used_card').textContent = formatETB(data.stats.total_used);
            document.getElementById('total_remaining_card').textContent = formatETB(data.stats.total_remaining);
            document.getElementById('utilization_percentage_text').textContent = data.stats.utilization_percentage.toFixed(1) + '%';
            
            const progressBar = document.getElementById('utilization_progress_bar');
            progressBar.style.width = '0%';
            setTimeout(() => {
                progressBar.style.width = data.stats.utilization_percentage + '%';
            }, 100);

            // Update Allocation Chart
            allocationChart.data.labels = data.charts.allocation_labels;
            allocationChart.data.datasets[0].data = data.charts.allocation_data;
            allocationChart.update();

            // Update Recent Transactions Table
            const tbody = document.getElementById('recent_transactions_tbody');
            tbody.innerHTML = '';
            if (data.recent_transactions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">No transactions found for this filter.</td></tr>';
            } else {
                data.recent_transactions.forEach(tx => {
                    const row = `
                        <tr class="table-row border-b border-slate-200">
                            <td class="px-6 py-4">${new Date(tx.date).toLocaleDateString()}</td>
                            <td class="px-6 py-4">${tx.owner_name || ''}</td>
                            <td class="px-6 py-4">${tx.code_name || ''}</td>
                            <td class="px-6 py-4">${tx.employee_name || ''}</td>
                            <td class="px-6 py-4 text-right font-medium">${formatETB(tx.amount)}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg transition-opacity duration-300 ${
                type === 'success' ? 'bg-green-100 text-green-800' : 
                type === 'danger' ? 'bg-red-100 text-red-800' : 
                'bg-blue-100 text-blue-800'
            }`;
            notification.innerHTML = `<i class="fas fa-info-circle mr-2"></i><span>${message}</span>`;
            document.body.appendChild(notification);
            setTimeout(() => {
                notification.style.opacity = '0';
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 3000);
        }

        function initCharts() {
            // Allocation Chart (Doughnut)
            const allocationCtx = document.getElementById('allocationChart').getContext('2d');
            allocationChart = new Chart(allocationCtx, {
                type: 'doughnut',
                data: {
                    labels: [], datasets: [{
                        data: [],
                        backgroundColor: ['#6366f1','#10b981','#f59e0b','#ec4899','#8b5cf6','#3b82f6','#ef4444'],
                        borderWidth: 2, borderColor: '#fff'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
            });

            // Trend Chart (Placeholder)
            const trendCtx = document.getElementById('trendChart')?.getContext('2d');
            if(trendCtx) { // Check if element exists
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                        datasets: [
                            { label: 'Allocated', data: [65, 59, 80, 81, 56, 55, 40], borderColor: '#6366f1', fill: true, tension: 0.3 },
                            { label: 'Used', data: [28, 48, 40, 19, 86, 27, 90], borderColor: '#10b981', fill: true, tension: 0.3 }
                        ]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        }
    </script>
</body>
</html>