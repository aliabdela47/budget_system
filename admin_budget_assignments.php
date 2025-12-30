<?php
require_once 'includes/init.php';

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Budget Assignments Management';
require_once 'includes/head.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_budgets'])) {
        $user_id = (int)$_POST['user_id'];
        $budget_type = $_POST['budget_type'];
        $budget_owner_ids = $_POST['budget_owner_ids'] ?? [];
        
        // Delete existing assignments for this user and budget type
        $stmt = $pdo->prepare("DELETE FROM user_budget_assignments WHERE user_id = ? AND budget_type = ?");
        $stmt->execute([$user_id, $budget_type]);
        
        // Insert new assignments
        $stmt = $pdo->prepare("INSERT INTO user_budget_assignments (user_id, budget_type, budget_owner_id) VALUES (?, ?, ?)");
        foreach ($budget_owner_ids as $owner_id) {
            $stmt->execute([$user_id, $budget_type, (int)$owner_id]);
        }
        
        $_SESSION['flash_message'] = 'Budget assignments updated successfully!';
        header('Location: admin_budget_assignments.php');
        exit;
    }
}

// Get all users (officers only)
$officers = $pdo->query("SELECT * FROM users WHERE role = 'officer' ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

// Get all budget owners
$gov_owners = $pdo->query("SELECT * FROM budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$prog_owners = $pdo->query("SELECT * FROM p_budget_owners ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
?>

<body class="text-slate-700 flex bg-gray-100 min-h-screen">
    <?php require_once 'includes/sidebar.php'; ?>
    
    <div class="main-content" id="mainContent">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-slate-800">Budget Assignments Management</h1>
            </div>

            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-700 rounded">
                    <?php echo htmlspecialchars($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Assign Budgets to Users</h2>
                
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Select User</label>
                            <select name="user_id" id="user_id" class="w-full border rounded p-2" required>
                                <option value="">Select User</option>
                                <?php foreach ($officers as $officer): ?>
                                    <option value="<?php echo $officer['id']; ?>">
                                        <?php echo htmlspecialchars($officer['username'] . ' - ' . $officer['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Budget Type</label>
                            <select name="budget_type" id="budget_type" class="w-full border rounded p-2" required>
                                <option value="governmental">Government Budget</option>
                                <option value="program">Programs Budget</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-1">Current Assignments</label>
                            <div id="current_assignments" class="p-2 bg-gray-100 rounded min-h-10">
                                Select a user to see current assignments
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Available Budget Owners</label>
                        <div id="budget_owners_container" class="border rounded p-3 max-h-60 overflow-y-auto">
                            <p class="text-gray-500">Select budget type to see available owners</p>
                        </div>
                    </div>
                    
                    <button type="submit" name="assign_budgets" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Save Assignments
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Current Assignments Overview</h2>
                <div id="assignments_overview">
                    <?php
                    // Display current assignments
                    $assignments_stmt = $pdo->query("
                        SELECT u.username, u.name, uba.budget_type, 
                               COUNT(uba.budget_owner_id) as assignment_count
                        FROM users u
                        LEFT JOIN user_budget_assignments uba ON u.id = uba.user_id
                        WHERE u.role = 'officer'
                        GROUP BY u.id, uba.budget_type
                        ORDER BY u.username
                    ");
                    $assignments = $assignments_stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($assignments) {
                        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                        $current_user = null;
                        foreach ($assignments as $assignment) {
                            if ($current_user !== $assignment['username']) {
                                if ($current_user !== null) echo '</div>';
                                echo '<div class="border rounded p-4">';
                                echo '<h3 class="font-semibold">' . htmlspecialchars($assignment['username']) . ' - ' . htmlspecialchars($assignment['name']) . '</h3>';
                                $current_user = $assignment['username'];
                            }
                            echo '<div class="mt-2 text-sm">';
                            echo htmlspecialchars(ucfirst($assignment['budget_type'])) . ': ' . $assignment['assignment_count'] . ' assignments';
                            echo '</div>';
                        }
                        if ($current_user !== null) echo '</div>';
                        echo '</div>';
                    } else {
                        echo '<p class="text-gray-500">No assignments found.</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Load budget owners when type changes
        $('#budget_type').change(function() {
            const budgetType = $(this).val();
            loadBudgetOwners(budgetType);
        });

        // Load user's current assignments when user is selected
        $('#user_id').change(function() {
            const userId = $(this).val();
            const budgetType = $('#budget_type').val();
            
            if (userId) {
                loadCurrentAssignments(userId, budgetType);
                loadBudgetOwners(budgetType, userId);
            } else {
                $('#current_assignments').html('Select a user to see current assignments');
            }
        });

        function loadBudgetOwners(budgetType, userId = null) {
            $.get('ajax_get_admin_owners.php', { 
                budget_type: budgetType,
                user_id: userId 
            }, function(response) {
                if (response.success) {
                    let html = '';
                    response.owners.forEach(owner => {
                        const checked = owner.assigned ? 'checked' : '';
                        html += `
                            <div class="flex items-center mb-2">
                                <input type="checkbox" name="budget_owner_ids[]" value="${owner.id}" ${checked} 
                                       class="mr-2" id="owner_${owner.id}">
                                <label for="owner_${owner.id}">${owner.code} - ${owner.name}</label>
                            </div>
                        `;
                    });
                    $('#budget_owners_container').html(html || '<p class="text-gray-500">No owners found</p>');
                }
            }, 'json');
        }

        function loadCurrentAssignments(userId, budgetType) {
            $.get('ajax_get_user_assignments.php', { 
                user_id: userId,
                budget_type: budgetType 
            }, function(response) {
                if (response.success) {
                    if (response.assignments.length > 0) {
                        let html = '<ul class="list-disc list-inside">';
                        response.assignments.forEach(assignment => {
                            html += `<li>${assignment.code} - ${assignment.name}</li>`;
                        });
                        html += '</ul>';
                        $('#current_assignments').html(html);
                    } else {
                        $('#current_assignments').html('No assignments for this budget type');
                    }
                }
            }, 'json');
        }
    });
    </script>
</body>
</html>