<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .input-group {
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            display: flex;
            align-items: center;
        }
        
        .input-group:focus-within {
            transform: translateY(-2px);
            border-color: #4f46e5;
            box-shadow: 0 0 0 1px #4f46e5;
        }
        
        .filter-section {
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .stats-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
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
                <li>
                    <a href="budget_adding.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-plus-circle w-5"></i>
                        <span class="ml-3">Budget Adding</span>
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
                    <a href="perdium.php" class="flex items-center p-3 text-base font-normal rounded-lg text-white/80 hover:bg-white/10">
                        <i class="fas fa-dollar-sign w-5"></i>
                        <span class="ml-3">Perdium Management</span>
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
                        Welcome to Admin Dashboard, <span class="text-indigo-600">Admin User</span>!
                    </h1>
                    <p class="text-slate-600 mt-2">Here's your financial overview</p>
                    <!-- Date Display -->
                    <div class="flex items-center mt-3 bg-indigo-100 rounded-lg p-2 max-w-md">
                        <i class="fas fa-calendar-alt text-indigo-600 mr-2"></i>
                        <span class="text-indigo-800 font-semibold" id="currentDateTime">
                            Loading date and time...
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

            <!-- Filter Section -->
            <div class="bg-white rounded-xl p-6 card-hover mb-6">
                <div class="flex justify-between items-center cursor-pointer" onclick="toggleFilterSection()">
                    <h2 class="text-xl font-bold text-slate-800">Data Filters</h2>
                    <i class="fas fa-chevron-down text-slate-500 transition-transform" id="filterIcon"></i>
                </div>
                <div class="filter-section mt-4" id="filterSection">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date Range</label>
                            <div class="flex space-x-2">
                                <input type="date" class="input-group" id="startDate">
                                <span class="self-center">to</span>
                                <input type="date" class="input-group" id="endDate">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner</label>
                            <select class="input-group" id="ownerFilter">
                                <option value="">All Owners</option>
                                <option value="1">Owner 1</option>
                                <option value="2">Owner 2</option>
                                <option value="3">Owner 3</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Type</label>
                            <select class="input-group" id="typeFilter">
                                <option value="">All Types</option>
                                <option value="yearly">Yearly Budget</option>
                                <option value="monthly">Monthly Budget</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded-lg transition" onclick="clearFilters()">
                            Clear Filters
                        </button>
                        <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition" onclick="applyFilters()">
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl p-6 stats-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Allocated</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">1,250,000 ETB</h3>
                        </div>
                        <div class="bg-indigo-100 p-3 rounded-lg">
                            <i class="fas fa-money-bill-wave text-indigo-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>This Year</span>
                            <span>+15.2%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: 75%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 stats-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Used</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">875,000 ETB</h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-lg">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Utilization</span>
                            <span>70%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: 70%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 stats-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Remaining Budget</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">375,000 ETB</h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-lg">
                            <i class="fas fa-wallet text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Available</span>
                            <span>30%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: 30%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-6 stats-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Monthly Average</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">104,167 ETB</h3>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-lg">
                            <i class="fas fa-chart-pie text-purple-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Spending Trend</span>
                            <span>+5.3%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2" style="width: 53%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Budget Allocation by Owner -->
                <div class="bg-white rounded-xl p-6 card-hover">
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
                <div class="bg-white rounded-xl p-6 card-hover">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-slate-800">Budget Usage Trend</h2>
                        <select class="input-group text-sm w-auto" id="trendFilter">
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

            <!-- Recent Budgets -->
            <div class="bg-white rounded-xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Recent Budgets</h2>
                    <a href="budget_adding.php" class="text-indigo-600 hover:text-indigo-700 text-sm flex items-center font-medium">
                        View all <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th scope="col" class="px-4 py-3">ID</th>
                                <th scope="col" class="px-4 py-3">Owner</th>
                                <th scope="col" class="px-4 py-3">Code</th>
                                <th scope="col" class="px-4 py-3">Yearly Amount</th>
                                <th scope="col" class="px-4 py-3">Monthly Amount</th>
                                <th scope="col" class="px-4 py-3">Remaining</th>
                                <th scope="col" class="px-4 py-3">Date Added</th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-slate-200 hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium">1</td>
                                <td class="px-4 py-2">Health Directorate</td>
                                <td class="px-4 py-2">MED-001</td>
                                <td class="px-4 py-2 font-medium">500,000 ETB</td>
                                <td class="px-4 py-2">41,667 ETB</td>
                                <td class="px-4 py-2">
                                    <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded">125,000 ETB</span>
                                </td>
                                <td class="px-4 py-2">2025-01-15</td>
                                <td class="px-4 py-2">
                                    <div class="flex space-x-2">
                                        <button class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200 hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium">2</td>
                                <td class="px-4 py-2">Education Directorate</td>
                                <td class="px-4 py-2">EDU-002</td>
                                <td class="px-4 py-2 font-medium">350,000 ETB</td>
                                <td class="px-4 py-2">29,167 ETB</td>
                                <td class="px-4 py-2">
                                    <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-0.5 rounded">87,500 ETB</span>
                                </td>
                                <td class="px-4 py-2">2025-01-10</td>
                                <td class="px-4 py-2">
                                    <div class="flex space-x-2">
                                        <button class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-200 hover:bg-slate-50">
                                <td class="px-4 py-2 font-medium">3</td>
                                <td class="px-4 py-2">Infrastructure Directorate</td>
                                <td class="px-4 py-2">INF-003</td>
                                <td class="px-4 py-2 font-medium">400,000 ETB</td>
                                <td class="px-4 py-2">33,333 ETB</td>
                                <td class="px-4 py-2">
                                    <span class="bg-red-100 text-red-800 text-xs font-medium px-2 py-0.5 rounded">25,000 ETB</span>
                                </td>
                                <td class="px-4 py-2">2025-01-05</td>
                                <td class="px-4 py-2">
                                    <div class="flex space-x-2">
                                        <button class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <footer class="mt-10 text-center text-slate-500 text-sm">
                <p>Powered by: <a href="https://ali.et" class="text-indigo-600 hover:text-indigo-700 font-medium" target="_blank">Ali Abdela</a> - All rights reserved © <span id="currentYear"></span></p>
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
            
            // Update date and time
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute
            
            // Set current year in footer
            document.getElementById('currentYear').textContent = new Date().getFullYear();
            
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

        // Update date and time display
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
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
        }

        // Toggle filter section
        function toggleFilterSection() {
            const filterSection = document.getElementById('filterSection');
            const filterIcon = document.getElementById('filterIcon');
            
            filterSection.classList.toggle('hidden');
            filterIcon.classList.toggle('fa-chevron-down');
            filterIcon.classList.toggle('fa-chevron-up');
        }

        // Clear all filters
        function clearFilters() {
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            document.getElementById('ownerFilter').value = '';
            document.getElementById('typeFilter').value = '';
            
            // In a real application, you would reload the data without filters
            showNotification('Filters cleared successfully', 'success');
        }

        // Apply filters
        function applyFilters() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const owner = document.getElementById('ownerFilter').value;
            const type = document.getElementById('typeFilter').value;
            
            // In a real application, you would send these filters to the server
            // and update the dashboard data accordingly
            console.log('Applying filters:', { startDate, endDate, owner, type });
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
                    labels: ['Health', 'Education', 'Infrastructure', 'Agriculture', 'Administration'],
                    datasets: [{
                        data: [40, 25, 20, 10, 5],
                        backgroundColor: [
                            '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
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
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
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