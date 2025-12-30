<?php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Store the selected budget type in session
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['budget_type'])) {
    $_SESSION['selected_budget_type'] = $_POST['budget_type'];
    
    // Redirect based on where the selection was made from
    if (isset($_POST['redirect_to'])) {
        header('Location: ' . $_POST['redirect_to']);
    } else {
        header('Location: perdium.php');
    }
    exit;
}

// If already selected, redirect to perdium
if (isset($_SESSION['selected_budget_type'])) {
    header('Location: perdium.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Budget Type - Budget System</title>
    <script src="css/tailwind.css"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
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
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        .budget-option {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .budget-option:hover {
            transform: scale(1.05);
            border-color: #4f46e5;
        }
        
        .budget-option.governmental {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
        }
        
        .budget-option.program {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Select Budget Type
                    </h1>
                    <p class="text-slate-600 mt-2">Choose the type of budget you want to manage</p>
                </div>
            </div>

            <!-- Budget Options -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-12">
                <!-- Governmental Budget Option -->
                <form method="post" class="budget-option governmental rounded-2xl p-8 card-hover flex flex-col items-center justify-center text-center">
                    <input type="hidden" name="budget_type" value="governmental">
                    <input type="hidden" name="redirect_to" value="perdium.php">
                    
                    <div class="bg-white/20 p-6 rounded-full mb-6">
                        <i class="fas fa-landmark text-4xl"></i>
                    </div>
                    
                    <h2 class="text-2xl font-bold mb-4">Governmental Budget</h2>
                    <p class="mb-6">Manage quarterly and monthly allocated budgets from Regional Finance Bureau</p>
                    
                    <button type="submit" class="bg-white text-primary font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition">
                        Select Governmental Budget
                    </button>
                </form>

                <!-- Program Budget Option -->
                <form method="post" class="budget-option program rounded-2xl p-8 card-hover flex flex-col items-center justify-center text-center">
                    <input type="hidden" name="budget_type" value="program">
                    <input type="hidden" name="redirect_to" value="perdium.php">
                    
                    <div class="bg-white/20 p-6 rounded-full mb-6">
                        <i class="fas fa-handshake text-4xl"></i>
                    </div>
                    
                    <h2 class="text-2xl font-bold mb-4">Program Budget</h2>
                    <p class="mb-6">Manage activity-based budgets from partner organizations and NGOs</p>
                    
                    <button type="submit" class="bg-white text-green-700 font-semibold py-3 px-8 rounded-lg hover:bg-gray-100 transition">
                        Select Program Budget
                    </button>
                </form>
            </div>
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
        });
    </script>
</body>
</html>