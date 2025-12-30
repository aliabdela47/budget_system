<?php
// tests/test_budget_system.php
include '../includes/db.php';

// Test data
$test_owner_id = 1;
$test_code_id = 1;
$test_year = 2017; // Ethiopian year

// Test 1: Create yearly budget
echo "Test 1: Creating yearly budget\n";
$stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, yearly_amount, is_yearly, allocated_amount, spent_amount) VALUES (?, ?, NOW(), ?, ?, TRUE, 0, 0)");
$stmt->execute([$test_owner_id, $test_code_id, $test_year, 100000]);
echo "Yearly budget created successfully\n\n";

// Test 2: Allocate monthly budget
echo "Test 2: Allocating monthly budget\n";
$stmt = $pdo->prepare("SELECT id FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND is_yearly = TRUE");
$stmt->execute([$test_owner_id, $test_code_id, $test_year]);
$yearly_budget = $stmt->fetch();

$stmt = $pdo->prepare("INSERT INTO budgets (owner_id, code_id, adding_date, year, month, monthly_amount, quarter, parent_id, allocated_amount, spent_amount, is_yearly) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, 0, FALSE)");
$stmt->execute([$test_owner_id, $test_code_id, $test_year, 'መስከረም', 20000, 1, $yearly_budget['id'], 20000]);
echo "Monthly budget allocated successfully\n\n";

// Test 3: Update yearly allocated amount
echo "Test 3: Updating yearly allocated amount\n";
$stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = ? WHERE id = ?");
$stmt->execute([20000, $yearly_budget['id']]);
echo "Yearly allocated amount updated successfully\n\n";

// Test 4: Add transaction
echo "Test 4: Adding transaction\n";
$stmt = $pdo->prepare("SELECT id FROM budgets WHERE owner_id = ? AND code_id = ? AND year = ? AND month = ?");
$stmt->execute([$test_owner_id, $test_code_id, $test_year, 'መስከረም']);
$monthly_budget = $stmt->fetch();

$stmt = $pdo->prepare("UPDATE budgets SET spent_amount = ? WHERE id = ?");
$stmt->execute([5000, $monthly_budget['id']]);

$stmt = $pdo->prepare("UPDATE budgets SET spent_amount = ? WHERE id = ?");
$stmt->execute([5000, $yearly_budget['id']]);
echo "Transaction recorded successfully\n\n";

// Test 5: Add extra budget
echo "Test 5: Adding extra budget\n";
$stmt = $pdo->prepare("SELECT monthly_amount FROM budgets WHERE id = ?");
$stmt->execute([$monthly_budget['id']]);
$current_amount = $stmt->fetchColumn();

$new_amount = $current_amount + 5000;
$stmt = $pdo->prepare("UPDATE budgets SET monthly_amount = ? WHERE id = ?");
$stmt->execute([$new_amount, $monthly_budget['id']]);

$stmt = $pdo->prepare("SELECT allocated_amount FROM budgets WHERE id = ?");
$stmt->execute([$yearly_budget['id']]);
$current_allocated = $stmt->fetchColumn();

$new_allocated = $current_allocated + 5000;
$stmt = $pdo->prepare("UPDATE budgets SET allocated_amount = ? WHERE id = ?");
$stmt->execute([$new_allocated, $yearly_budget['id']]);

$stmt = $pdo->prepare("INSERT INTO budget_revisions (budget_id, previous_amount, new_amount, reason, revised_by) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$monthly_budget['id'], $current_amount, $new_amount, 'Test extra budget', 1]);
echo "Extra budget added successfully\n\n";

echo "All tests completed successfully!\n";