<?php
require_once 'includes/init.php';

// Check if budget type is selected
if (!isset($_SESSION['selected_budget_type'])) {
    header('Location: budget_selection.php');
    exit;
}

$budget_type = $_SESSION['selected_budget_type'];

// Fetch budgets based on type
if ($budget_type == 'governmental') {
    $budgets = $pdo->query("
        SELECT b.*, o.name as owner_name, c.name as code_name 
        FROM budgets b 
        JOIN budget_owners o ON b.owner_id = o.id 
        JOIN budget_codes c ON b.code_id = c.id 
        WHERE b.budget_type = 'governmental'
    ")->fetchAll();
} else {
    $budgets = $pdo->query("
        SELECT b.*, o.name as owner_name, c.name as code_name 
        FROM budgets b 
        JOIN budget_owners o ON b.owner_id = o.id 
        JOIN budget_codes c ON b.code_id = c.id 
        WHERE b.budget_type = 'program'
    ")->fetchAll();
}

// Handle form submission for perdium requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Your existing perdium processing code, but with budget type consideration
    $budget_id = $_POST['budget_id'];
    $amount = $_POST['amount'];
    $purpose = $_POST['purpose'];
    $employee_name = $_POST['employee_name'];
    
    // Check if budget is available
    $stmt = $pdo->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->execute([$budget_id]);
    $budget = $stmt->fetch();
    
    if ($budget) {
        // For governmental budgets, check monthly allocation
        if ($budget['budget_type'] == 'governmental') {
            $current_month = date('n'); // Current month as a number (1-12)
            $monthly_field = "monthly_" . $current_month;
            
            if (isset($budget[$monthly_field]) && $budget[$monthly_field] >= $amount) {
                // Process the request
                $stmt = $pdo->prepare("
                    INSERT INTO perdium_requests 
                    (budget_id, amount, purpose, employee_name, status, created_at) 
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$budget_id, $amount, $purpose, $employee_name]);
                
                // Update the budget
                $stmt = $pdo->prepare("
                    UPDATE budgets 
                    SET $monthly_field = $monthly_field - ? 
                    WHERE id = ?
                ");
                $stmt->execute([$amount, $budget_id]);
                
                $message = "Perdium request submitted successfully";
            } else {
                $error = "Insufficient monthly budget for this request";
            }
        } else {
            // For program budgets, check yearly allocation
            if ($budget['yearly_amount'] >= $amount) {
                // Process the request
                $stmt = $pdo->prepare("
                    INSERT INTO perdium_requests 
                    (budget_id, amount, purpose, employee_name, status, created_at) 
                    VALUES (?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$budget_id, $amount, $purpose, $employee_name]);
                
                // Update the budget
                $stmt = $pdo->prepare("
                    UPDATE budgets 
                    SET yearly_amount = yearly_amount - ? 
                    WHERE id = ?
                ");
                $stmt->execute([$amount, $budget_id]);
                
                $message = "Perdium request submitted successfully";
            } else {
                $error = "Insufficient yearly budget for this request";
            }
        }
    } else {
        $error = "Invalid budget selected";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perdium Management - Budget System</title>
    <script src="css/tailwind.css"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        /* Your existing styles for perdium.php */
    </style>
</head>
<body class="flex">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <!-- Header with Budget Type Indicator -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 p-6 bg-white rounded-xl shadow-sm">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-800">
                        Perdium Management
                    </h1>
                    <p class="text-slate-600 mt-2">
                        Managing 
                        <span class="font-semibold <?php echo $budget_type == 'governmental' ? 'text-primary' : 'text-green-600'; ?>">
                            <?php echo $budget_type == 'governmental' ? 'Governmental' : 'Program'; ?> Budget
                        </span>
                    </p>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <a href="budget_selection.php" class="btn-secondary">
                        <i class="fas fa-exchange-alt mr-2"></i> Change Budget Type
                    </a>
                    <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 p-2 rounded-lg md:hidden shadow-sm" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>

            <!-- Budget Type Specific Information -->
            <div class="bg-white rounded-xl p-6 card-hover mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-4">
                    <?php echo $budget_type == 'governmental' ? 'Governmental Budget Details' : 'Program Budget Details'; ?>
                </h2>
                
                <?php if ($budget_type == 'governmental'): ?>
                    <p class="text-slate-600">
                        Governmental budgets are allocated quarterly and monthly from the Regional Finance Bureau. 
                        Each directorate has specific monthly allocations that can be used within that month.
                    </p>
                <?php else: ?>
                    <p class="text-slate-600">
                        Program budgets are activity-based funds from partner organizations and NGOs. 
                        These are allocated yearly and can be used for specific activities throughout the year.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Your existing perdium form with adjustments for budget type -->
            <div class="bg-white rounded-xl p-6 card-hover mb-8">
                <h2 class="text-xl font-bold text-slate-800 mb-6">Request Perdium</h2>
                
                <?php if (isset($message)): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Select Budget</label>
                            <div class="input-group">
                                <select name="budget_id" required>
                                    <option value="">Select a budget</option>
                                    <?php foreach ($budgets as $budget): ?>
                                        <option value="<?php echo $budget['id']; ?>">
                                            <?php echo htmlspecialchars($budget['owner_name'] . ' - ' . $budget['code_name']); ?>
                                            <?php if ($budget_type == 'program' && !empty($budget['program_name'])): ?>
                                                (<?php echo htmlspecialchars($budget['program_name']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Amount</label>
                            <div class="input-group">
                                <input type="number" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Purpose</label>
                        <div class="input-group">
                            <textarea name="purpose" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Employee Name</label>
                        <div class="input-group">
                            <input type="text" name="employee_name" required>
                        </div>
                    </div>
                    
                    <div class="flex space-x-4 mt-6">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane mr-2"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>

            <!-- Budget List -->
            <div class="bg-white rounded-xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">
                        Available <?php echo $budget_type == 'governmental' ? 'Governmental' : 'Program'; ?> Budgets
                    </h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th class="px-4 py-3">Budget Owner</th>
                                <th class="px-4 py-3">Budget Code</th>
                                <?php if ($budget_type == 'program'): ?>
                                    <th class="px-4 py-3">Program Name</th>
                                <?php endif; ?>
                                <th class="px-4 py-3">Yearly Amount</th>
                                <?php if ($budget_type == 'governmental'): ?>
                                    <th class="px-4 py-3">Current Month</th>
                                    <th class="px-4 py-3">Monthly Allocation</th>
                                <?php endif; ?>
                                <th class="px-4 py-3">Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50">
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($budget['owner_name']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($budget['code_name']); ?></td>
                                    <?php if ($budget_type == 'program'): ?>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($budget['program_name'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td class="px-4 py-3"><?php echo number_format($budget['yearly_amount'], 2); ?> ETB</td>
                                    <?php if ($budget_type == 'governmental'): ?>
                                        <td class="px-4 py-3">
                                            <?php 
                                                $ethiopian_months = [
                                                    'መስከረም', 'ጥቅምት', 'ኅዳር', 'ታኅሣሥ',
                                                    'ጥር', 'የካቲት', 'መጋቢት', 'ሚያዝያ',
                                                    'ግንቦት', 'ሰኔ', 'ሐምሌ', 'ነሐሴ'
                                                ];
                                                $current_month = date('n') - 1; // Adjust for 0-based index
                                                echo $ethiopian_months[$current_month];
                                            ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php 
                                                $monthly_field = "monthly_" . (date('n'));
                                                echo number_format($budget[$monthly_field] ?? 0, 2); 
                                            ?> ETB
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-4 py-3 font-medium">
                                        <?php 
                                            if ($budget_type == 'governmental') {
                                                $monthly_field = "monthly_" . (date('n'));
                                                echo number_format($budget[$monthly_field] ?? 0, 2);
                                            } else {
                                                echo number_format($budget['yearly_amount'], 2);
                                            }
                                        ?> ETB
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Your existing JavaScript code
    </script>
</body>
</html>