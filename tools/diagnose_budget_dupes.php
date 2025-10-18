<?php
/**
 * Diagnostic Script: Detect Duplicate Budget Entries
 * 
 * This script scans the budgets table for duplicate entries based on the composite key:
 * (budget_type, owner_id, code_id, year, COALESCE(month,'YEARLY'))
 * 
 * Run this BEFORE applying the migration to identify and clean up duplicates.
 * 
 * Usage: php tools/diagnose_budget_dupes.php
 */

require_once __DIR__ . '/../includes/db.php';

echo "=== Budget Duplicates Diagnostic Tool ===\n\n";
echo "Scanning budgets table for duplicate entries...\n\n";

try {
    // Find duplicates based on the composite key that will be enforced
    $sql = "
        SELECT 
            budget_type,
            owner_id,
            code_id,
            year,
            COALESCE(month, 'YEARLY') as month_key,
            COUNT(*) as duplicate_count,
            GROUP_CONCAT(id ORDER BY id) as budget_ids
        FROM budgets
        GROUP BY budget_type, owner_id, code_id, year, COALESCE(month, 'YEARLY')
        HAVING COUNT(*) > 1
        ORDER BY budget_type, owner_id, code_id, year, month_key
    ";
    
    $stmt = $pdo->query($sql);
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "✓ No duplicates found! The database is ready for the migration.\n\n";
        exit(0);
    }
    
    echo "✗ Found " . count($duplicates) . " groups of duplicate entries:\n\n";
    
    foreach ($duplicates as $idx => $dup) {
        echo "Duplicate Group #" . ($idx + 1) . ":\n";
        echo "  Budget Type: " . $dup['budget_type'] . "\n";
        echo "  Owner ID: " . $dup['owner_id'] . "\n";
        echo "  Code ID: " . $dup['code_id'] . "\n";
        echo "  Year: " . $dup['year'] . "\n";
        echo "  Month Key: " . $dup['month_key'] . "\n";
        echo "  Count: " . $dup['duplicate_count'] . "\n";
        echo "  Budget IDs: " . $dup['budget_ids'] . "\n";
        
        // Get detailed info for each duplicate
        $ids = explode(',', $dup['budget_ids']);
        echo "  Details:\n";
        
        $detailSql = "SELECT id, monthly_amount, yearly_amount, remaining_monthly, remaining_yearly, adding_date 
                      FROM budgets WHERE id IN (" . $dup['budget_ids'] . ") ORDER BY id";
        $detailStmt = $pdo->query($detailSql);
        $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($details as $detail) {
            echo "    ID {$detail['id']}: ";
            echo "monthly={$detail['monthly_amount']}, yearly={$detail['yearly_amount']}, ";
            echo "rem_monthly={$detail['remaining_monthly']}, rem_yearly={$detail['remaining_yearly']}, ";
            echo "added={$detail['adding_date']}\n";
        }
        echo "\n";
    }
    
    echo "\n=== ACTION REQUIRED ===\n";
    echo "Please resolve these duplicates before running the migration.\n";
    echo "Options:\n";
    echo "1. Manually merge/delete duplicate rows in the database\n";
    echo "2. Keep the row with the most recent adding_date or highest ID\n";
    echo "3. Sum the remaining amounts if appropriate for your use case\n\n";
    echo "After cleaning, re-run this script to verify.\n\n";
    
    exit(1);
    
} catch (PDOException $e) {
    echo "ERROR: Database query failed: " . $e->getMessage() . "\n";
    exit(1);
}
