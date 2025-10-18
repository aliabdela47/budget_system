# Budget System Migrations

This directory contains SQL migration scripts for the budget system database.

## Migration: 2025-10-18-001 - Strict Monthly Budget Enforcement

### Overview

This migration enforces strict monthly-only deductions for governmental budgets and ensures database-level uniqueness for budget allocations.

### What Changes

1. **Governmental Budgets**: Now strictly use monthly budget rows only, with no fallback to yearly amounts
2. **Database Constraints**: Adds unique constraints to prevent duplicate budget rows
3. **Program Budgets**: Remain yearly-only (no changes to behavior)

### Pre-Migration Steps

**IMPORTANT**: Before applying the migration, you must check for and resolve any duplicate budget rows.

#### Step 1: Run the Diagnostic Script

```bash
php tools/diagnose_budget_dupes.php
```

This script will:
- Scan the `budgets` table for duplicate rows
- Scan the `p_budgets` table for duplicates (if exists)
- Report row IDs of any duplicates found

#### Step 2: Resolve Duplicates

If duplicates are found, you must resolve them manually:

**Option A: Merge Duplicates**
```sql
-- Example: Merge two monthly rows by combining their amounts
UPDATE budgets 
SET remaining_monthly = remaining_monthly + (
    SELECT remaining_monthly FROM budgets WHERE id = <duplicate_id>
)
WHERE id = <primary_id>;

DELETE FROM budgets WHERE id = <duplicate_id>;
```

**Option B: Delete Erroneous Duplicates**
```sql
-- If one row is clearly wrong/empty
DELETE FROM budgets WHERE id = <duplicate_id>;
```

**Option C: Update Month Values**
```sql
-- If rows should represent different months
UPDATE budgets SET month = '<correct_month>' WHERE id = <row_id>;
```

#### Step 3: Verify No Duplicates

Run the diagnostic script again to confirm all duplicates are resolved:

```bash
php tools/diagnose_budget_dupes.php
```

Expected output: "✓ All clear! No duplicates found."

### Applying the Migration

Once all duplicates are resolved, apply the migration:

```bash
mysql -u <username> -p <database_name> < sql/migrations/2025-10-18-001-strict-monthly-budgets.sql
```

Or via PHPMyAdmin:
1. Open PHPMyAdmin
2. Select your database
3. Go to "SQL" tab
4. Copy and paste the contents of `2025-10-18-001-strict-monthly-budgets.sql`
5. Click "Go"

### Post-Migration Verification

1. **Test Governmental Budget Allocation**:
   - Create a new per diem transaction with a governmental budget
   - Select a month that has a monthly budget allocated
   - Verify the transaction is created successfully
   - Check that `remaining_monthly` decreased by the transaction amount

2. **Test Error Handling**:
   - Try creating a per diem transaction for a month without a budget
   - Should see error: "No monthly perdium budget allocated for the selected month"

3. **Test Program Budget** (should work as before):
   - Create a per diem transaction with a program budget
   - Verify it uses yearly amounts (no change in behavior)

4. **Test Duplicate Prevention**:
   - Try inserting a duplicate budget row (same owner, code, year, month)
   - Should fail with unique constraint violation

### Rollback

If you need to rollback this migration:

```sql
-- Remove unique constraint
ALTER TABLE budgets DROP KEY uniq_budget_owner_code_year_monthkey;

-- Remove supporting index
DROP INDEX idx_budget_owner_code_year_month ON budgets;

-- Remove generated column
ALTER TABLE budgets DROP COLUMN month_key;
```

Note: After rollback, the application code will still enforce strict monthly semantics. You should revert the code changes as well.

### Support

If you encounter issues:
1. Check the diagnostic script output for detailed error information
2. Review the migration SQL comments for additional guidance
3. Contact the development team

### Files Modified

- `perdium.php`: Updated allocation and reversal logic
- `get_remaining_perdium.php`: Strict monthly lookup
- `ajax_perdium_grands.php`: Clarified display-only yearly totals
- `tools/diagnose_budget_dupes.php`: New diagnostic script
- `sql/migrations/2025-10-18-001-strict-monthly-budgets.sql`: Migration SQL
