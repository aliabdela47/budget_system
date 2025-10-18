<?php
/**
 * Diagnostic Script: Budget Duplicates Detector
 * 
 * This script scans the budgets and p_budgets tables for duplicate rows
 * that would violate the unique constraints to be added in the migration.
 * 
 * Run this script BEFORE applying the migration SQL to identify and fix
 * any duplicate budget rows.
 * 
 * Usage: php tools/diagnose_budget_dupes.php
 */

require_once __DIR__ . '/../includes/init.php';

echo "=================================================\n";
echo "Budget Duplicates Diagnostic Report\n";
echo "=================================================\n\n";

// Check for duplicates in budgets table
echo "Checking budgets table for duplicates...\n";
echo "-------------------------------------------------\n";

$sql = "
    SELECT 
        budget_type,
        owner_id,
        code_id,
        year,
        COALESCE(month, 'YEARLY') as month_key,
        COUNT(*) as duplicate_count,
        GROUP_CONCAT(id ORDER BY id) as row_ids
    FROM budgets
    GROUP BY budget_type, owner_id, code_id, year, month_key
    HAVING COUNT(*) > 1
    ORDER BY budget_type, owner_id, year, month_key
";

$stmt = $pdo->query($sql);
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✓ No duplicates found in budgets table.\n\n";
} else {
    echo "✗ Found " . count($duplicates) . " duplicate groups in budgets table:\n\n";
    
    foreach ($duplicates as $dup) {
        echo "Budget Type: {$dup['budget_type']}\n";
        echo "Owner ID: {$dup['owner_id']}\n";
        echo "Code ID: {$dup['code_id']}\n";
        echo "Year: {$dup['year']}\n";
        echo "Month Key: {$dup['month_key']}\n";
        echo "Duplicate Count: {$dup['duplicate_count']}\n";
        echo "Row IDs: {$dup['row_ids']}\n";
        echo "---\n";
    }
    
    echo "\nACTION REQUIRED: Please resolve these duplicates before applying the migration.\n";
    echo "You can:\n";
    echo "1. Merge duplicate rows by consolidating their amounts\n";
    echo "2. Delete duplicate rows if they are erroneous\n";
    echo "3. Update the month value if rows should represent different months\n\n";
}

// Check for duplicates in p_budgets table (if it exists)
echo "Checking p_budgets table for duplicates...\n";
echo "-------------------------------------------------\n";

try {
    // First check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'p_budgets'");
    if ($checkTable->rowCount() > 0) {
        // Check if code_id column exists
        $checkColumn = $pdo->query("SHOW COLUMNS FROM p_budgets LIKE 'code_id'");
        $hasCodeId = ($checkColumn->rowCount() > 0);
        
        if ($hasCodeId) {
            $sql = "
                SELECT 
                    owner_id,
                    code_id,
                    year,
                    COUNT(*) as duplicate_count,
                    GROUP_CONCAT(id ORDER BY id) as row_ids
                FROM p_budgets
                GROUP BY owner_id, code_id, year
                HAVING COUNT(*) > 1
                ORDER BY owner_id, year
            ";
        } else {
            $sql = "
                SELECT 
                    owner_id,
                    year,
                    COUNT(*) as duplicate_count,
                    GROUP_CONCAT(id ORDER BY id) as row_ids
                FROM p_budgets
                GROUP BY owner_id, year
                HAVING COUNT(*) > 1
                ORDER BY owner_id, year
            ";
        }
        
        $stmt = $pdo->query($sql);
        $pDuplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pDuplicates)) {
            echo "✓ No duplicates found in p_budgets table.\n\n";
        } else {
            echo "✗ Found " . count($pDuplicates) . " duplicate groups in p_budgets table:\n\n";
            
            foreach ($pDuplicates as $dup) {
                echo "Owner ID: {$dup['owner_id']}\n";
                if ($hasCodeId) {
                    echo "Code ID: {$dup['code_id']}\n";
                }
                echo "Year: {$dup['year']}\n";
                echo "Duplicate Count: {$dup['duplicate_count']}\n";
                echo "Row IDs: {$dup['row_ids']}\n";
                echo "---\n";
            }
            
            echo "\nACTION REQUIRED: Please resolve these duplicates before applying the migration.\n\n";
        }
    } else {
        echo "ℹ p_budgets table does not exist. Skipping.\n\n";
    }
} catch (PDOException $e) {
    echo "ℹ Could not check p_budgets table: " . $e->getMessage() . "\n\n";
}

// Summary
echo "=================================================\n";
echo "Summary\n";
echo "=================================================\n";

$totalDuplicates = count($duplicates);
$pTotalDuplicates = isset($pDuplicates) ? count($pDuplicates) : 0;

if ($totalDuplicates === 0 && $pTotalDuplicates === 0) {
    echo "✓ All clear! No duplicates found.\n";
    echo "You can safely apply the migration SQL.\n";
} else {
    echo "✗ Total duplicate groups found: " . ($totalDuplicates + $pTotalDuplicates) . "\n";
    echo "Please resolve all duplicates before applying the migration.\n";
}

echo "\n";
echo "Next Steps:\n";
echo "1. If duplicates found: Fix them manually in the database\n";
echo "2. Run this script again to verify all duplicates are resolved\n";
echo "3. Apply the migration: sql/migrations/2025-10-18-001-strict-monthly-budgets.sql\n";
echo "=================================================\n";
