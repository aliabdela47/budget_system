<?php 
require_once 'includes/init.php';
include 'includes/sidebar.php';
// Check if user is officer
$is_officer = ($_SESSION['role'] == 'officer');
// Fetch user's name from database
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user_data && !empty($user_data['name'])) {
    $display_name = $user_data['name'];
} else {
    $display_name = $_SESSION['username']; // fallback
}



// Fetch data for charts: Allocated, Used, and Remaining per owner
$directorate_data = [];
$stmt = $pdo->query("
    SELECT 
        o.id, 
        o.name, 
        SUM(b.yearly_amount + b.monthly_amount) AS allocated,
        (SELECT SUM(t.amount) FROM transactions t WHERE t.owner_id = o.id) AS used,
        SUM(b.remaining_yearly + b.remaining_monthly) AS remaining
    FROM budget_owners o 
    LEFT JOIN budgets b ON o.id = b.owner_id
    GROUP BY o.id, o.name
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Ensure used and remaining are not null
    $row['used'] = $row['used'] ?? 0;
    $row['remaining'] = $row['allocated'] - $row['used']; // Recalculate for accuracy
    $directorate_data[] = $row;
}

// Fetch recent transactions (added more details)
$recent_transactions = $pdo->query("
    SELECT t.*, o.name AS owner_name, c.name AS code_name
    FROM transactions t
    JOIN budget_owners o ON t.owner_id = o.id
    JOIN budget_codes c ON t.code_id = c.id
    ORDER BY t.date DESC, t.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate overall totals
$total_allocated = array_sum(array_column($directorate_data, 'allocated'));
$total_used = array_sum(array_column($directorate_data, 'used'));
$total_remaining = $total_allocated - $total_used;

// Get current date and time
$currentDate = date('F j, Y');
$currentTime = date('g:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Budget System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css"> <!-- Add this line -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        
        
        .input-group {
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
            border-radius: 0.75rem;
            padding: 0.6rem 1rem;
            display: flex;
            align-items: center;
            background: white;
        }
        
        .input-group:focus-within {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
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
        
        .table-row {
            transition: all 0.3s ease;
        }
        
        .table-row:hover {
            background: #f8fafc;
            transform: scale(1.01);
        }
    </style>
</head>
<body class="text-slate-700">
  

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <!-- Header -->
            <div class="glass-effect flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 rounded-2xl shadow-sm">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Welcome to AFAR-RHB Integrated Financial System, <span class="gradient-text"><?php echo htmlspecialchars($display_name); ?></span>!
                    </h1>
                    <p class="text-slate-600 mt-2">Here's your financial overview and analytics</p>
                    <!-- Date Display -->
                    <div class="flex items-center mt-4 bg-indigo-100 rounded-xl p-3 max-w-md shadow-inner">
                        <i class="fas fa-calendar-alt text-indigo-600 mr-3"></i>
                        <span class="text-indigo-800 font-semibold" id="currentDateTime">
                            Loading date and time...
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="btn btn-primary flex items-center" onclick="window.print()">
                        <i class="fas fa-print mr-2"></i> Print Report
                    </button>
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-3 rounded-xl md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

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
                                <option value="1"> አስተዳደር ባጀት | 341/01/01</option>
                                <option value="2">የበሽታ መከላከልና መቆጣጠር ዳያሬክቶሬት | 341/05/19</option>
                                <option value="3">የክሊኒካል አገልግሎት ዳያሬክቶሬት | 341/03/02</option>
                                <option value="4">የእቅድ ዝግጅት፣ ክትትልና ግምገማ  ዳይሬክቶሬት | 341/07/25</option>
                                <option value="2">የእናቶች ፤ ህፃናት አፍላ ወጣቶች እና አገልግሎት ዳያሬክቶሬት | 341/04/16</option>
                                <option value="3">የማህበረሰበ ተሳትፎና የመጀመርያ ደረጃ ጤና እንክብካቤ ዳይሬክቶሬት | 341/06/17</option>
                                <option value="4">የሥርአተ ምግብ ማስተባበርያ ዳይሬክቶሬት | 341/04/18</option>
                                <option value="2">የህክምና ግብዓቶች አቅርቦትና የፋርማሲ አገልግሎት ዳይሬክቶሬት | 341/02/02</option>
                                <option value="3">የጤናና ጤና ነክ አገልግሎቶች ብቃትና ጥራት ማረጋገጥ ዳይሬክቶሬት | 341/07/27</option>
                                </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget Type</label>
                            <select class="input-group w-full" id="typeFilter">
                                <option value="">All Types</option>
                                <option value="yearly">Sansii kee Sukutih</option>
                                <option value="monthly">Ayroh Assentah</option>
                                <option value="quarterly">Transporti Mekláh</option>
                                <option value="monthly">Qibni-Cadih</option>
                                <option value="quarterly">Kiráh</option>
                                <option value="monthly">Maysaxxgáh</option>
                                <option value="quarterly">Quukah</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-5 py-2.5 rounded-xl transition" onclick="clearFilters()">
                            Clear Filters
                        </button>
                        <button class="btn btn-primary" onclick="applyFilters()">
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Total Allocated</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">1,250,000 ETB</h3>
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
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">875,000 ETB</h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-xl">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Utilization</span>
                            <span>70%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2 bg-gradient-to-r from-green-500 to-emerald-600" style="width: 70%"></div>
                        </div>
                    </div>
                </div>

                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Remaining Budget</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">375,000 ETB</h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-xl">
                            <i class="fas fa-wallet text-blue-600 text-xl"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-sm text-slate-500 mb-1">
                            <span>Available</span>
                            <span>30%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="progress-bar rounded-full h-2 bg-gradient-to-r from-blue-500 to-cyan-600" style="width: 30%"></div>
                        </div>
                    </div>
                </div>

                <div class="stats-card glass-effect p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-500">Monthly Average</p>
                            <h3 class="text-2xl font-bold text-slate-800 mt-1">104,167 ETB</h3>
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
                        <h2 class="text-xl font-bold text-slate-800">Allocation by Project Koox:</h2>
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
            <div class="glass-effect rounded-2xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Recent Budgets</h2>
                    <a href="budget_adding.php" class="text-indigo-600 hover:text-indigo-700 text-sm flex items-center font-medium">
                        View all <i class="fas fa-chevron-right ml-1 text-xs"></i>
                    </a>
                </div>

                <div class="overflow-x-auto rounded-2xl">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th scope="col" class="px-6 py-4">ID</th>
                                <th scope="col" class="px-6 py-4">Project Koox:</th>
                                <th scope="col" class="px-6 py-4">Budget Koox:</th>
                                <th scope="col" class="px-6 py-4">Yearly Amount</th>
                                <th scope="col" class="px-6 py-4">Monthly Amount</th>
                                <th scope="col" class="px-6 py-4">Remaining</th>
                                <th scope="col" class="px-6 py-4">Date Added</th>
                                <th scope="col" class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-row border-b border-slate-200">
                                <td class="px-6 py-4 font-medium">1</td>
                                <td class="px-6 py-4">341/01/01</td>
                                <td class="px-6 py-4">6217</td>
                                <td class="px-6 py-4 font-medium">500,000 ETB</td>
                                <td class="px-6 py-4">41,667 ETB</td>
                                <td class="px-6 py-4">
                                    <span class="bg-green-100 text-green-800 text-xs font-medium px-3 py-1.5 rounded-full">125,000 ETB</span>
                                </td>
                                <td class="px-6 py-4">2025-01-15</td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-3">
                                        <button class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="table-row border-b border-slate-200">
                                <td class="px-6 py-4 font-medium">2</td>
                                <td class="px-6 py-4">341/05/19</td>
                                <td class="px-6 py-4">6231</td>
                                <td class="px-6 py-4 font-medium">350,000 ETB</td>
                                <td class="px-6 py-4">29,167 ETB</td>
                                <td class="px-6 py-4">
                                    <span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-3 py-1.5 rounded-full">87,500 ETB</span>
                                </td>
                                <td class="px-6 py-4">2025-01-10</td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-3">
                                        <button class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="table-row border-b border-slate-200">
                                <td class="px-6 py-4 font-medium">3</td>
                                <td class="px-6 py-4">341/07/25</td>
                                <td class="px-6 py-4">6232</td>
                                <td class="px-6 py-4 font-medium">400,000 ETB</td>
                                <td class="px-6 py-4">33,333 ETB</td>
                                <td class="px-6 py-4">
                                    <span class="bg-red-100 text-red-800 text-xs font-medium px-3 py-1.5 rounded-full">25,000 ETB</span>
                                </td>
                                <td class="px-6 py-4">2025-01-05</td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-3">
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
                    labels: ['341/01/01', '341/05/19', '341/07/25', '341/04/16', '341/06/17'],
                    datasets: [{
                        data: [40, 25, 20, 10, 5],
                        backgroundColor: [
                            '#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'
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