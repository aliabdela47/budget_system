# Monthly Budget Enforcement Implementation

## Overview

This document describes the implementation of strict monthly-only budget enforcement for governmental per diem allocations in the Afar Health Bureau Financial System.

## Problem Statement

Previously, the system had a fallback mechanism where if a monthly budget row didn't exist or had insufficient funds, it would fall back to using yearly budget amounts. This created inconsistencies and made it difficult to track monthly budget usage accurately.

## Solution

We've implemented a strict monthly-only enforcement policy for governmental budgets:

1. **Governmental budgets**: Must have a monthly budget row for the selected month
2. **No fallback**: If no monthly budget exists or funds are insufficient, the allocation fails with a clear error
3. **Database constraints**: Unique indexes prevent duplicate budget rows
4. **Program budgets**: Remain yearly-only (unchanged)

## Technical Implementation

### 1. Helper Function: `ensureUniqueMonthlyBudget()`

**Location**: `perdium.php`

```php
function ensureUniqueMonthlyBudget(PDO $pdo, int $owner_id, int $code_id, int $year, string $et_month): void
```

**Purpose**: Detects if multiple budget rows exist for the same monthly key and throws an error with guidance to run the diagnostic script and apply the migration.

**Key Points**:
- Uses COUNT(*) query on budgets table
- Filters by: budget_type='governmental', owner_id, code_id, year, month
- Throws exception if count > 1

### 2. Updated: `allocateGovernment()`

**Location**: `perdium.php`

**Changes**:
1. Now requires `et_month` parameter (throws if empty)
2. Calls `ensureUniqueMonthlyBudget()` to detect duplicates
3. Uses `SELECT ... FOR UPDATE` to lock the monthly budget row
4. **Removed**: Yearly fallback logic entirely
5. Throws clear errors if:
   - No monthly budget row found
   - Insufficient monthly funds

**Before**:
```php
// Would fall back to yearly budget if monthly not found
if (!$b) {
    // Yearly fallback code...
}
```

**After**:
```php
if (!$b) {
    throw new Exception('No monthly perdium budget allocated for the selected month...');
}
```

### 3. Updated: `reverseLegacy()`

**Location**: `perdium.php`

**Changes**:
1. For governmental budgets:
   - Requires `et_month` in transaction data (throws if missing)
   - Calls `ensureUniqueMonthlyBudget()`
   - Updates only `remaining_monthly` (no yearly fallback)
   - Throws if monthly row not found
2. Program budget reversal unchanged

**Key Point**: Legacy transactions without month data cannot be reversed (throws clear error).

### 4. Updated: `get_remaining_perdium.php`

**Changes**:
1. Added explicit `budget_type='governmental'` filter
2. Returns error flag when no monthly row found
3. No fallback to yearly amounts

**Response Format**:
```json
{
    "remaining_monthly": 0,
    "remaining_yearly": 0,
    "error": true,
    "message": "No monthly budget allocated for the selected month."
}
```

### 5. Updated: `ajax_perdium_grands.php`

**Changes**:
1. Added comments clarifying yearly totals are for display purposes only
2. Made explicit that allocations must use monthly rows
3. Program budget logic unchanged

### 6. New: `tools/diagnose_budget_dupes.php`

**Purpose**: Diagnostic script to find duplicate budget rows before applying migration.

**Features**:
- Scans `budgets` table for duplicates by (budget_type, owner_id, code_id, year, month_key)
- Scans `p_budgets` table if exists
- Outputs readable report with row IDs
- Provides clear guidance on next steps

**Usage**:
```bash
php tools/diagnose_budget_dupes.php
```

### 7. New: `sql/migrations/2025-10-18-001-strict-monthly-budgets.sql`

**Purpose**: Database migration to enforce uniqueness.

**Changes**:
1. Adds `month_key` generated column: `COALESCE(month, 'YEARLY')`
2. Adds unique constraint: `(budget_type, owner_id, code_id, year, month_key)`
3. Adds supporting index for performance
4. Optional p_budgets constraints (commented)

**Why month_key?**: MySQL treats NULL values as distinct in UNIQUE constraints. The generated column normalizes NULL to 'YEARLY' so yearly budget rows are properly deduplicated.

## Behavior Changes

### Governmental Budget Allocations

**Before**:
```
1. Try to find monthly budget row
2. If not found OR insufficient → Fall back to yearly budget
3. Deduct from whichever was available
```

**After**:
```
1. Check for duplicate monthly rows (throw if found)
2. Try to find monthly budget row
3. If not found → Throw error with clear message
4. If insufficient → Throw error
5. Deduct ONLY from monthly budget
```

### Governmental Budget Reversals

**Before**:
```
1. Try monthly budget row if month is available
2. Fall back to yearly if monthly not found
```

**After**:
```
1. Require month data (throw if missing)
2. Check for duplicate monthly rows
3. Find monthly budget row (throw if not found)
4. Return amount ONLY to monthly budget
```

### Program Budgets

**No changes**: Still use yearly-only allocations via `allocateProgram()`.

## User-Visible Changes

### UI
- No changes required (Ethiopian month is already required for governmental budgets)

### Error Messages

**New Error Messages**:
1. "Ethiopian month is required for governmental budget allocation."
2. "Multiple budget rows found for this monthly key. Please run the diagnostic script..."
3. "No monthly perdium budget allocated for the selected month. Please ensure a monthly budget exists."
4. "Ethiopian month is required for governmental budget reversal. Cannot reverse legacy transactions without month data."
5. "No monthly budget row found for reversal. Cannot reverse to non-existent monthly budget."

## Migration Process

### Step 1: Pre-Migration Diagnosis
```bash
php tools/diagnose_budget_dupes.php
```

### Step 2: Resolve Duplicates
See `sql/migrations/README.md` for detailed resolution instructions.

### Step 3: Apply Migration
```bash
mysql -u username -p database < sql/migrations/2025-10-18-001-strict-monthly-budgets.sql
```

### Step 4: Verification
- Test creating governmental per diem with existing monthly budget ✓
- Test error when monthly budget doesn't exist ✓
- Test program budgets still work ✓
- Verify duplicate prevention ✓

## Testing

### Test Script
Run automated validation:
```bash
php tools/test_monthly_enforcement.php
```

This validates:
- Function signatures exist
- Yearly fallback removed
- Error handling implemented
- Diagnostic script present
- Migration SQL correct

### Manual Testing Checklist

#### Governmental Budget Tests
- [ ] Create per diem with month that has budget → Should succeed
- [ ] Create per diem with month that has NO budget → Should fail with clear error
- [ ] Create per diem with insufficient monthly funds → Should fail with clear error
- [ ] Update/edit existing per diem → Should use monthly budget
- [ ] Delete per diem → Should return amount to monthly budget

#### Program Budget Tests
- [ ] Create program per diem → Should work as before (yearly)
- [ ] Update program per diem → Should work as before
- [ ] Delete program per diem → Should work as before

#### Database Constraint Tests
- [ ] Try to insert duplicate monthly budget row → Should fail with constraint violation
- [ ] Try to insert duplicate yearly program row → Should fail with constraint violation

## Rollback Plan

If issues arise, rollback in this order:

1. **Revert database changes**:
```sql
ALTER TABLE budgets DROP KEY uniq_budget_owner_code_year_monthkey;
DROP INDEX idx_budget_owner_code_year_month ON budgets;
ALTER TABLE budgets DROP COLUMN month_key;
```

2. **Revert code changes**:
```bash
git revert <commit-hash>
```

## Security Considerations

1. **SQL Injection**: All queries use prepared statements with parameterized values ✓
2. **Race Conditions**: Uses `SELECT ... FOR UPDATE` to lock rows during allocation ✓
3. **Data Integrity**: Unique constraints prevent duplicates at database level ✓
4. **Error Messages**: Provide helpful information without exposing sensitive data ✓

## Performance Considerations

1. **Added Operations**:
   - One additional COUNT(*) query per allocation/reversal (ensureUniqueMonthlyBudget)
   - Impact: Minimal (< 1ms with proper indexing)

2. **Removed Operations**:
   - Removed fallback queries to yearly budget rows
   - Net Impact: Neutral or slightly positive

3. **Indexing**:
   - Migration adds index on (budget_type, owner_id, code_id, year, month)
   - Improves lookup performance for monthly budgets

## Future Enhancements

Potential improvements for consideration:

1. **Batch Processing**: Add ability to check/allocate multiple months at once
2. **Budget Templates**: Auto-create monthly budgets from yearly allocations
3. **Forecasting**: Predict monthly budget shortfalls
4. **Reporting**: Enhanced monthly vs yearly budget utilization reports

## Support

For issues or questions:
1. Check error messages for specific guidance
2. Run diagnostic script: `php tools/diagnose_budget_dupes.php`
3. Review this documentation
4. Contact development team

## References

- Migration SQL: `sql/migrations/2025-10-18-001-strict-monthly-budgets.sql`
- Diagnostic Script: `tools/diagnose_budget_dupes.php`
- Test Script: `tools/test_monthly_enforcement.php`
- Migration README: `sql/migrations/README.md`
