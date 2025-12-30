<?php
// tests/test_budget_system_fixed.php
include '../includes/db.php';

try {
    echo "Starting budget system tests...\n\n";
    
    // Check if test owner exists, if not create one
    echo "Checking/Creating test owner...\n";
    $stmt = $pdo->prepare("SELECT id FROM budget_owners WHERE code = 'TEST'");
    $stmt->execute();
    $test_owner = $stmt->fetch();
    
    if (!$test_owner) {
        $stmt = $pdo->prepare("INSERT INTO budget_owners (code, name) VALUES (?, ?)");
        $stmt->execute(['TEST', 'Test Owner']);
        $test_owner_id = $pdo->lastInsertId();
        echo "Created test owner with ID: $test_owner_id\n";
    } else {
        $test_owner_id = $test_owner['id'];
        echo "Using existing test owner with ID: $test_owner_id\n";
    }
    
    // Check if test code exists, if not create one
    echo "Checking/Creating test code...\n";
    $stmt = $pdo->prepare("SELECT id FROM budget_codes WHERE code = 'TEST'");
    $stmt->execute();
    $test_code = $stmt->fetch();
    
    if (!$test_code) {
        $stmt = $pdo->prepare("INSERT INTO budget_codes (code, name) VALUES (?, ?)");
        $stmt->execute(['TEST', 'Test Code']);
        $test_code_id = $pdo->lastInsertId();
        echo "Created test code with ID: $test_code_id\n";
    } else {
        $test_code_id = $test_code['id'];
        echo "Using existing test code with ID: $test_code_id\n";
    }
    
    $test_year = 2017; // Ethiopian year
    
    // Test 1: Create yearly budget
    echo "\nTest 1: Creating yearly budget\n";
    $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, yearly_amount, is_yearly, allocated_amount, spent_amount) VALUES (?, ?, NOW(), ?, ?, TRUE, 0, 0)");
    $stmt->execute([$test_owner_id, $test_code_id, $test_year, 100000]);
    $yearly_budget_id = $pdo->lastInsertId();
    echo "Yearly budget created successfully with ID: $yearly_budget_id\n";
    
    // Test 2: Allocate monthly budget
    echo "\nTest 2: Allocating monthly budget\n";
    $stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, month, monthly_amount, quarter, parent_id, allocated_amount, spent_amount, is_yearly) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, 0, FALSE)");
    $stmt->execute([$test_owner_id, $test_code_id, $test_year, 'መስከረም', 20000, 1, $yearly_budget_id, 20000]);
    $monthly_budget_id = $pdo->lastInsertId();
    echo "Monthly budget allocated successfully with ID: $monthly_budget_id\n";
    
    // Test 3: Update yearly allocated amount
    echo "\nTest 3: Updating yearly allocated amount\n";
    $stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = ? WHERE id = ?");
    $stmt->execute([20000, $yearly_budget_id]);
    echo "Yearly allocated amount updated successfully\n";
    
    // Test 4: Add transaction
    echo "\nTest 4: Adding transaction\n";
    $stmt = $pdo->prepare("UPDATE budgets SET spent_amount = ? WHERE id = ?");
    $stmt->execute([5000, $monthly_budget_id]);
    
    $stmt = $pdo->prepare("UPDATE budgets SET spent_amount = ? WHERE id = ?");
    $stmt->execute([5000, $yearly_budget_id]);
    echo "Transaction recorded successfully\n";
    
    // Test 5: Add extra budget
    echo "\nTest 5: Adding extra budget\n";
    $stmt = $pdo->prepare("SELECT monthly_amount FROM budgets WHERE id = ?");
    $stmt->execute([$monthly_budget_id]);
    $current_amount = $stmt->fetchColumn();
    
    $new_amount = $current_amount + 5000;
    $stmt = $pdo->prepare("UPDATE budgets SET monthly_amount = ? WHERE id = ?");
    $stmt->execute([$new_amount, $monthly_budget_id]);
    
    $stmt = $pdo->prepare("SELECT allocated_amount FROM budgets WHERE id = ?");
    $stmt->execute([$yearly_budget_id]);
    $current_allocated = $stmt->fetchColumn();
    
    $new_allocated = $current_allocated + 5000;
    $stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = ? WHERE id = ?");
    $stmt->execute([$new_allocated, $yearly_budget_id]);
    
    $stmt = $pdo->prepare("INSERT INTO budget_revisions (budget_id, previous_amount, new_amount, reason, revised_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$monthly_budget_id, $current_amount, $new_amount, 'Test extra budget', 1]);
    echo "Extra budget added successfully\n\n";
  
  // tests/test_budget_system_fixed.php
// ... (previous code remains the same)

    // Clean up test data
    echo "Cleaning up test data...\n";
    $pdo->beginTransaction();
    
    // Delete budget revisions first (they reference budgets)
    $stmt = $pdo->prepare("DELETE FROM budget_revisions WHERE budget_id IN (?, ?)");
    $stmt->execute([$monthly_budget_id, $yearly_budget_id]);
    
    // Delete monthly budgets first (they reference yearly budgets)
    $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
    $stmt->execute([$monthly_budget_id]);
    
    // Then delete yearly budgets
    $stmt = $pdo->prepare("DELETE FROM budgets WHERE id = ?");
    $stmt->execute([$yearly_budget_id]);
    
    // Only delete test owner and code if we created them
    if (!isset($test_owner)) {
        $stmt = $pdo->prepare("DELETE FROM budget_owners WHERE id = ?");
        $stmt->execute([$test_owner_id]);
        echo "Deleted test owner\n";
    }
    
    if (!isset($test_code)) {
        $stmt = $pdo->prepare("DELETE FROM budget_codes WHERE id = ?");
        $stmt->execute([$test_code_id]);
        echo "Deleted test code\n";
    }
    
    $pdo->commit();
    
    echo "All tests completed successfully!\n";
    
} catch (Exception $e) {
    // Rollback any changes if there was an error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
  
    
    // Clean up test data
    
    

    // Only delete test owner and code if we created them
    
    
