<?php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $owner_id = $_POST['owner_id'];
    $code_id = $_POST['code_id'];
    $yearly_amount = $_POST['yearly_amount'];
    $budget_type = $_POST['budget_type'];
    $program_name = $_POST['program_name'] ?? null;
    $activity_based = isset($_POST['activity_based']) ? 1 : 0;
    
    // For governmental budgets, calculate monthly allocations
    if ($budget_type == 'governmental') {
        $quarterly_amount = $yearly_amount / 4;
        $monthly_amount = $yearly_amount / 12;
        
        // Insert into database with monthly allocations
        $stmt = $pdo->prepare("
            INSERT INTO budgets 
            (owner_id, code_id, yearly_amount, budget_type, program_name, activity_based,
             monthly_1, monthly_2, monthly_3, monthly_4, monthly_5, monthly_6,
             monthly_7, monthly_8, monthly_9, monthly_10, monthly_11, monthly_12) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $owner_id, $code_id, $yearly_amount, $budget_type, $program_name, $activity_based,
            $monthly_amount, $monthly_amount, $monthly_amount, $monthly_amount,
            $monthly_amount, $monthly_amount, $monthly_amount, $monthly_amount,
            $monthly_amount, $monthly_amount, $monthly_amount, $monthly_amount
        ]);
    } else {
        // For program budgets, just store the yearly amount
        $stmt = $pdo->prepare("
            INSERT INTO budgets 
            (owner_id, code_id, yearly_amount, budget_type, program_name, activity_based) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$owner_id, $code_id, $yearly_amount, $budget_type, $program_name, $activity_based]);
    }
    
    $message = "Budget added successfully";
}

// Fetch budget owners and codes
$owners = $pdo->query("SELECT * FROM budget_owners")->fetchAll();
$codes = $pdo->query("SELECT * FROM budget_codes")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Adding - Budget System</title>
    <script src="css/tailwind.css"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        /* Your existing styles */
    </style>
    <script>
        function toggleBudgetTypeFields() {
            const budgetType = document.getElementById('budget_type').value;
            const programFields = document.getElementById('program_fields');
            const governmentalFields = document.getElementById('governmental_fields');
            
            if (budgetType === 'program') {
                programFields.classList.remove('hidden');
                governmentalFields.classList.add('hidden');
            } else {
                programFields.classList.add('hidden');
                governmentalFields.classList.remove('hidden');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            toggleBudgetTypeFields(); // Initialize on page load
        });
    </script>
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
                        Budget Adding
                    </h1>
                    <p class="text-slate-600 mt-2">Add new governmental or program budgets</p>
                </div>
            </div>

            <!-- Budget Form -->
            <div class="bg-white rounded-xl p-6 card-hover mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Budget Form</h2>
                
                <?php if (isset($message)): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner</label>
                            <div class="input-group">
                                <select name="owner_id" required>
                                    <option value="">Select a budget owner</option>
                                    <?php foreach ($owners as $owner): ?>
                                        <option value="<?php echo $owner['id']; ?>">
                                            <?php echo htmlspecialchars($owner['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Budget Code</label>
                            <div class="input-group">
                                <select name="code_id" required>
                                    <option value="">Select a budget code</option>
                                    <?php foreach ($codes as $code): ?>
                                        <option value="<?php echo $code['id']; ?>">
                                            <?php echo htmlspecialchars($code['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Budget Type</label>
                        <div class="input-group">
                            <select name="budget_type" id="budget_type" required onchange="toggleBudgetTypeFields()">
                                <option value="governmental">Governmental Budget</option>
                                <option value="program">Program Budget</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Program-specific fields -->
                    <div id="program_fields" class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Program Name</label>
                                <div class="input-group">
                                    <input type="text" name="program_name" placeholder="Enter program name">
                                </div>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="activity_based" id="activity_based" class="mr-2">
                                <label for="activity_based" class="text-sm text-slate-700">Activity-based budget</label>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Yearly Amount (ETB)</label>
                        <div class="input-group">
                            <input type="number" name="yearly_amount" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <!-- Governmental budget info -->
                    <div id="governmental_fields">
                        <div class="bg-blue-50 text-blue-700 p-4 rounded-lg">
                            <i class="fas fa-info-circle mr-2"></i>
                            Governmental budgets will be automatically divided into monthly allocations.
                        </div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus mr-2"></i> Add Budget
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Your existing JavaScript code
    </script>
</body>
</html>