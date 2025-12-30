<?php
require_once 'includes/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Set budget type to program for this page
$_SESSION['selected_budget_type'] = 'program';

// Fetch program budgets
$budgets = $pdo->query("
    SELECT b.*, o.name as owner_name, c.name as code_name 
    FROM budgets b 
    JOIN budget_owners o ON b.owner_id = o.id 
    JOIN budget_codes c ON b.code_id = c.id 
    WHERE b.budget_type = 'program'
")->fetchAll();

// Handle budget editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_budget') {
    $budget_id = $_POST['budget_id'];
    $yearly_amount = $_POST['yearly_amount'];
    $program_name = $_POST['program_name'];
    $activity_based = isset($_POST['activity_based']) ? 1 : 0;
    
    $stmt = $pdo->prepare("
        UPDATE budgets 
        SET yearly_amount = ?, program_name = ?, activity_based = ? 
        WHERE id = ?
    ");
    $stmt->execute([$yearly_amount, $program_name, $activity_based, $budget_id]);
    
    $message = "Budget updated successfully";
}

// Handle budget deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $message = "Budget deleted successfully";
    header('Location: program_budget_management.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Budget Management - Budget System</title>
    <script src="css/tailwind.css"></script>
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        /* Your existing styles */
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
                        Program Budget Management
                    </h1>
                    <p class="text-slate-600 mt-2">Manage activity-based program budgets from partner organizations</p>
                </div>
                <div class="flex items-center space-x-4 mt-4 md:mt-0">
                    <a href="budget_adding.php" class="btn-primary">
                        <i class="fas fa-plus mr-2"></i> Add New Budget
                    </a>
                </div>
            </div>

            <!-- Program Budgets List -->
            <div class="bg-white rounded-xl p-6 card-hover">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-slate-800">Program Budgets</h2>
                </div>
                
                <?php if (isset($message)): ?>
                    <div class="bg-green-50 text-green-700 p-4 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs uppercase bg-slate-100 text-slate-700">
                            <tr>
                                <th class="px-4 py-3">Budget Owner</th>
                                <th class="px-4 py-3">Budget Code</th>
                                <th class="px-4 py-3">Program Name</th>
                                <th class="px-4 py-3">Yearly Amount</th>
                                <th class="px-4 py-3">Activity Based</th>
                                <th class="px-4 py-3">Remaining</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): ?>
                                <tr class="border-b border-slate-200 hover:bg-slate-50">
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($budget['owner_name']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($budget['code_name']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($budget['program_name'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-3"><?php echo number_format($budget['yearly_amount'], 2); ?> ETB</td>
                                    <td class="px-4 py-3">
                                        <?php echo $budget['activity_based'] ? 'Yes' : 'No'; ?>
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        <?php echo number_format($budget['yearly_amount'], 2); ?> ETB
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-center space-x-2">
                                            <button onclick="openEditModal(<?php echo $budget['id']; ?>, '<?php echo htmlspecialchars($budget['owner_name']); ?>', '<?php echo htmlspecialchars($budget['code_name']); ?>', '<?php echo htmlspecialchars($budget['program_name'] ?? ''); ?>', <?php echo $budget['yearly_amount']; ?>, <?php echo $budget['activity_based']; ?>)" class="btn-secondary">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                            <a href="?action=delete&id=<?php echo $budget['id']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this budget?')">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md">
            <h2 class="text-xl font-bold text-slate-800 mb-4">Edit Program Budget</h2>
            
            <form method="post" class="space-y-4">
                <input type="hidden" name="action" value="update_budget">
                <input type="hidden" name="budget_id" id="edit_budget_id">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Budget Owner</label>
                    <div class="input-group">
                        <input type="text" id="edit_owner_name" readonly>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Budget Code</label>
                    <div class="input-group">
                        <input type="text" id="edit_code_name" readonly>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Program Name</label>
                    <div class="input-group">
                        <input type="text" name="program_name" id="edit_program_name">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Yearly Amount (ETB)</label>
                    <div class="input-group">
                        <input type="number" name="yearly_amount" id="edit_yearly_amount" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="activity_based" id="edit_activity_based" class="mr-2">
                    <label for="edit_activity_based" class="text-sm text-slate-700">Activity-based budget</label>
                </div>
                
                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" onclick="closeEditModal()" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Update Budget</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, ownerName, codeName, programName, yearlyAmount, activityBased) {
            document.getElementById('edit_budget_id').value = id;
            document.getElementById('edit_owner_name').value = ownerName;
            document.getElementById('edit_code_name').value = codeName;
            document.getElementById('edit_program_name').value = programName;
            document.getElementById('edit_yearly_amount').value = yearlyAmount;
            document.getElementById('edit_activity_based').checked = activityBased;
            
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>