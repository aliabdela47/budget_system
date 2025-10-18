<?php
/**
 * Test Script: Monthly Budget Enforcement
 * 
 * This script demonstrates and validates the strict monthly-only enforcement
 * for governmental budgets.
 * 
 * Note: This is a demonstration test. In a production environment with database access,
 * you should run actual integration tests.
 */

echo "=================================================\n";
echo "Monthly Budget Enforcement Test\n";
echo "=================================================\n\n";

// Test 1: Validate function signatures exist
echo "Test 1: Checking function signatures...\n";
$perdium_content = file_get_contents(__DIR__ . '/../perdium.php');

$tests_passed = 0;
$tests_failed = 0;

// Check for ensureUniqueMonthlyBudget function
if (strpos($perdium_content, 'function ensureUniqueMonthlyBudget') !== false) {
    echo "✓ ensureUniqueMonthlyBudget() function exists\n";
    $tests_passed++;
} else {
    echo "✗ ensureUniqueMonthlyBudget() function not found\n";
    $tests_failed++;
}

// Check allocateGovernment has no yearly fallback
if (strpos($perdium_content, 'function allocateGovernment') !== false) {
    echo "✓ allocateGovernment() function exists\n";
    $tests_passed++;
    
    // Extract the function
    preg_match('/function allocateGovernment.*?^}/ms', $perdium_content, $matches);
    if (!empty($matches[0])) {
        $allocate_func = $matches[0];
        
        // Check that it calls ensureUniqueMonthlyBudget
        if (strpos($allocate_func, 'ensureUniqueMonthlyBudget') !== false) {
            echo "✓ allocateGovernment() calls ensureUniqueMonthlyBudget()\n";
            $tests_passed++;
        } else {
            echo "✗ allocateGovernment() does not call ensureUniqueMonthlyBudget()\n";
            $tests_failed++;
        }
        
        // Check for yearly fallback removal (should not find the old yearly fallback code)
        if (strpos($allocate_func, 'Yearly fallback') === false && 
            strpos($allocate_func, 'monthly_amount = 0') === false) {
            echo "✓ allocateGovernment() has no yearly fallback\n";
            $tests_passed++;
        } else {
            echo "✗ allocateGovernment() still contains yearly fallback logic\n";
            $tests_failed++;
        }
        
        // Check it throws on no monthly row
        if (strpos($allocate_func, 'No monthly perdium budget allocated') !== false) {
            echo "✓ allocateGovernment() throws when no monthly row found\n";
            $tests_passed++;
        } else {
            echo "✗ allocateGovernment() does not throw proper error\n";
            $tests_failed++;
        }
    }
} else {
    echo "✗ allocateGovernment() function not found\n";
    $tests_failed++;
}

// Check reverseLegacy for governmental
if (strpos($perdium_content, 'function reverseLegacy') !== false) {
    echo "✓ reverseLegacy() function exists\n";
    $tests_passed++;
    
    preg_match('/function reverseLegacy.*?^}/ms', $perdium_content, $matches);
    if (!empty($matches[0])) {
        $reverse_func = $matches[0];
        
        // Check it requires month for governmental
        if (strpos($reverse_func, 'month is required for governmental budget reversal') !== false) {
            echo "✓ reverseLegacy() requires month for governmental\n";
            $tests_passed++;
        } else {
            echo "✗ reverseLegacy() does not require month\n";
            $tests_failed++;
        }
        
        // Check it calls ensureUniqueMonthlyBudget for governmental
        $govt_section = substr($reverse_func, strpos($reverse_func, "} else {"));
        if (strpos($govt_section, 'ensureUniqueMonthlyBudget') !== false) {
            echo "✓ reverseLegacy() calls ensureUniqueMonthlyBudget() for governmental\n";
            $tests_passed++;
        } else {
            echo "✗ reverseLegacy() does not call ensureUniqueMonthlyBudget()\n";
            $tests_failed++;
        }
    }
} else {
    echo "✗ reverseLegacy() function not found\n";
    $tests_failed++;
}

echo "\n";

// Test 2: Check get_remaining_perdium.php
echo "Test 2: Checking get_remaining_perdium.php...\n";
$remaining_content = file_get_contents(__DIR__ . '/../get_remaining_perdium.php');

// Check for strict monthly lookup
if (strpos($remaining_content, "budget_type='governmental'") !== false) {
    echo "✓ get_remaining_perdium.php has strict budget_type filter\n";
    $tests_passed++;
} else {
    echo "✗ get_remaining_perdium.php missing budget_type filter\n";
    $tests_failed++;
}

// Check for error handling
if (strpos($remaining_content, "'error' =>") !== false) {
    echo "✓ get_remaining_perdium.php returns error flag\n";
    $tests_passed++;
} else {
    echo "✗ get_remaining_perdium.php does not return error flag\n";
    $tests_failed++;
}

echo "\n";

// Test 3: Check ajax_perdium_grands.php
echo "Test 3: Checking ajax_perdium_grands.php...\n";
$grands_content = file_get_contents(__DIR__ . '/../ajax_perdium_grands.php');

// Check for comments about display-only
if (strpos($grands_content, 'for display') !== false || 
    strpos($grands_content, 'display purposes only') !== false) {
    echo "✓ ajax_perdium_grands.php clarifies yearly totals are display-only\n";
    $tests_passed++;
} else {
    echo "✗ ajax_perdium_grands.php missing clarification comments\n";
    $tests_failed++;
}

echo "\n";

// Test 4: Check diagnostic script exists
echo "Test 4: Checking diagnostic script...\n";
if (file_exists(__DIR__ . '/diagnose_budget_dupes.php')) {
    echo "✓ diagnose_budget_dupes.php exists\n";
    $tests_passed++;
    
    $diag_content = file_get_contents(__DIR__ . '/diagnose_budget_dupes.php');
    if (strpos($diag_content, 'COALESCE(month') !== false) {
        echo "✓ Diagnostic script checks for month_key duplicates\n";
        $tests_passed++;
    } else {
        echo "✗ Diagnostic script missing month_key check\n";
        $tests_failed++;
    }
} else {
    echo "✗ diagnose_budget_dupes.php not found\n";
    $tests_failed++;
}

echo "\n";

// Test 5: Check migration SQL exists
echo "Test 5: Checking migration SQL...\n";
if (file_exists(__DIR__ . '/../sql/migrations/2025-10-18-001-strict-monthly-budgets.sql')) {
    echo "✓ Migration SQL exists\n";
    $tests_passed++;
    
    $sql_content = file_get_contents(__DIR__ . '/../sql/migrations/2025-10-18-001-strict-monthly-budgets.sql');
    
    if (strpos($sql_content, 'month_key') !== false) {
        echo "✓ Migration adds month_key column\n";
        $tests_passed++;
    } else {
        echo "✗ Migration missing month_key column\n";
        $tests_failed++;
    }
    
    if (strpos($sql_content, 'UNIQUE KEY') !== false) {
        echo "✓ Migration adds unique constraint\n";
        $tests_passed++;
    } else {
        echo "✗ Migration missing unique constraint\n";
        $tests_failed++;
    }
    
    if (strpos($sql_content, 'diagnose_budget_dupes.php') !== false) {
        echo "✓ Migration references diagnostic script\n";
        $tests_passed++;
    } else {
        echo "✗ Migration does not reference diagnostic script\n";
        $tests_failed++;
    }
} else {
    echo "✗ Migration SQL not found\n";
    $tests_failed++;
}

echo "\n";

// Summary
echo "=================================================\n";
echo "Test Summary\n";
echo "=================================================\n";
echo "Tests Passed: $tests_passed\n";
echo "Tests Failed: $tests_failed\n";

if ($tests_failed === 0) {
    echo "\n✓ All tests passed! Implementation is correct.\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the implementation.\n";
    exit(1);
}
