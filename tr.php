<?php
require_once 'includes/init.php';

$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$codes = $pdo->query("SELECT * FROM budget_codes")->fetchAll();

// Get current Ethiopian info using the centralized function
$et_info = getEtInfo(date('Y-m-d'));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ... (Your existing POST logic for adding transactions remains here) ...
    // Make sure it uses $et_info['month'] and $et_info['quarter'] correctly.
}

// Fetch transactions to display
$transactions = $pdo->query("
    SELECT t.*, o.code AS owner_code, c.code AS budget_code 
    FROM transactions t 
    JOIN budget_owners o ON t.owner_id = o.id 
    JOIN budget_codes c ON t.code_id = c.id
    ORDER BY t.date DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction - Budget System</title>
    <!-- Your existing CSS links -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        /* Your existing styles */
        body { display: flex; }
        .main-content { flex: 1; }
    </style>
</head>
<body class="text-slate-700 flex bg-gray-100 min-h-screen">
    <!-- Centralized Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">Transaction Management</h1>
                    <p class="text-slate-600 mt-2">Add and manage financial transactions</p>
                </div>
                 <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggleMobile">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Flash Message -->
            <?php flash(); ?>
            <?php if (isset($message)): ?>
                <div class="bg-blue-50 text-blue-700 p-4 rounded-lg mb-6">
                    <i class="fas fa-info-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Your existing Transaction form and table HTML goes here -->
            <!-- ... -->
            <!-- Make sure any JS functions are also included -->
            <!-- ... -->

        </div>
    </div>

    <!-- Your existing page-specific JavaScript goes here -->
</body>
</html>