# Budget System - Strict Monthly Budget Deduction Rules

## Overview

This implementation enforces strict budget deduction rules for the perdium (per diem) transaction system:

1. **Governmental Budgets**: Strict monthly-only deductions (no yearly fallback)
2. **Program Budgets**: Yearly-only deductions (no monthly)
3. **Single Table**: All budgets use the `budgets` table only (no `p_budgets`)
4. **Uniqueness**: Enforced via database constraints

## Files Changed

### Core Logic Files

1. **perdium.php**
   - Updated `allocateGovernment()`: Requires `et_month`, strict monthly deduction only
   - Updated `allocateProgram()`: Uses `budgets` table only, yearly-only deduction
   - Added `ensureUniqueMonthlyBudget()`: Pre-checks for duplicates
   - Added `ensureUniqueProgramYearlyBudget()`: Pre-checks for duplicates
   - Updated `reverseAllocations()`: Uses `budgets` table only
   - Updated `reverseLegacy()`: Enforces strict rules, requires `et_month` for governmental

2. **get_remaining_perdium.php**
   - Governmental: Strict monthly fetch (no fallback)
   - Program: Yearly-only fetch (month IS NULL, monthly_amount=0)
   - Returns clear error flags for missing budgets or duplicates

3. **ajax_perdium_grands.php**
   - Program: Totals from `budgets` table where month IS NULL
   - Governmental: Yearly values are informational only (no fallback implied)

### Database Files

4. **sql/migrations/2025-10-18-001-strict-monthly-budgets.sql**
   - Adds generated column: `month_key = COALESCE(month, 'YEARLY')`
   - Adds unique constraint: `uniq_budget_owner_code_year_monthkey`
   - Adds supporting index: `idx_budget_owner_code_year_month`

### Tools

5. **tools/diagnose_budget_dupes.php**
   - Diagnostic script to detect duplicate budget entries
   - **MUST BE RUN BEFORE MIGRATION** to identify and clean duplicates

## Migration Steps

### 1. Pre-Migration

Run the diagnostic script to identify duplicates:

```bash
php tools/diagnose_budget_dupes.php
```

If duplicates are found:
- Review the output carefully
- Manually merge/delete duplicate rows
- Keep the row with the most recent `adding_date` or highest ID
- Consider summing `remaining_*` amounts if appropriate
- Re-run diagnostic until no duplicates are found

### 2. Apply Migration

Once diagnostics show no duplicates:

```bash
mysql -u username -p database_name < sql/migrations/2025-10-18-001-strict-monthly-budgets.sql
```

### 3. Verify

Check that indexes were created:

```sql
SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'budgets'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;
```

## Budget Deduction Rules

### Governmental Budgets

- **Source**: `budgets` table
- **Selection**: 
  ```sql
  WHERE budget_type='governmental' 
    AND owner_id = ?
    AND code_id = ?
    AND year = ?
    AND month = ?
  ```
- **Deduction**: From `remaining_monthly` column only
- **Requirements**: 
  - `et_month` MUST be provided
  - Monthly budget row MUST exist
  - No fallback to yearly budget
- **Reversal**: Back to same monthly row

### Program Budgets

- **Source**: `budgets` table only (no `p_budgets`)
- **Selection**:
  ```sql
  WHERE budget_type='program'
    AND owner_id = ?
    AND code_id = ?
    AND year = ?
    AND monthly_amount = 0
    AND month IS NULL
  ```
- **Deduction**: From `remaining_yearly` column only
- **Requirements**:
  - Yearly budget row MUST exist
  - No monthly budget rows used
- **Reversal**: Back to same yearly row

## API Changes

### get_remaining_perdium.php

**New Required Parameter**: `budget_type`

**Example Requests**:

```javascript
// Governmental
GET /get_remaining_perdium.php?owner_id=1&code_id=6&month=Meskerem&year=2017&budget_type=governmental

// Program
GET /get_remaining_perdium.php?owner_id=2&code_id=6&year=2017&budget_type=program
```

**Response**:
```json
{
  "remaining_monthly": 10000.00,
  "remaining_quarterly": 30000.00,
  "remaining_yearly": 90000.00
}
```

Or with error:
```json
{
  "error": "No monthly budget found for this month",
  "remaining_monthly": 0,
  "remaining_quarterly": 0,
  "remaining_yearly": 0
}
```

### ajax_perdium_grands.php

**Optional New Parameter**: `code_id`

**Example Requests**:

```javascript
// Program totals by code
GET /ajax_perdium_grands.php?budget_type=program&code_id=6&year=2017

// Governmental owner summary (informational)
GET /ajax_perdium_grands.php?budget_type=governmental&owner_id=1&year=2017
```

## Error Messages

### Allocation Errors

- `"Ethiopian month is required for governmental budget allocation."`
  - **Cause**: Trying to allocate governmental budget without specifying month
  - **Fix**: Provide `et_month` parameter

- `"No monthly perdium budget found for {month} {year}. Monthly budget must be registered before allocation."`
  - **Cause**: Monthly budget row doesn't exist
  - **Fix**: Create monthly budget entry before allocation

- `"Insufficient remaining monthly budget for perdium."`
  - **Cause**: Not enough budget in the monthly allocation
  - **Fix**: Increase monthly budget or use different month

- `"No program budget allocated or registered for this owner/year."`
  - **Cause**: No yearly program budget exists
  - **Fix**: Create program yearly budget entry

- `"Insufficient program yearly budget."`
  - **Cause**: Not enough budget in yearly program allocation
  - **Fix**: Increase program budget

### Duplicate Detection Errors

- `"Duplicate monthly budget detected for owner_id={X}, code_id={Y}, year={Z}, month={M}. Please run the diagnostic script and add the unique index."`
  - **Fix**: Run diagnostic script, clean duplicates, apply migration

- `"Duplicate program yearly budget detected for owner_id={X}, code_id={Y}, year={Z}. Please run the diagnostic script and add the unique index."`
  - **Fix**: Run diagnostic script, clean duplicates, apply migration

## Backwards Compatibility

### Frontend Compatibility

- UI remains unchanged
- All existing frontend parameters are supported
- New `budget_type` parameter is optional (defaults appropriately)

### Database Compatibility

- Migration is **additive only** (no columns dropped)
- Existing data remains intact
- Only adds constraints and indexes

### Code Compatibility

- `p_budget_owners` table still used for organization/owner data
- Only `p_budgets` table references removed (budget allocations)
- Legacy transaction records can still be reversed safely

## Testing Checklist

- [ ] Run diagnostic script before migration
- [ ] Clean any duplicate budget entries
- [ ] Apply migration SQL
- [ ] Verify indexes were created
- [ ] Test governmental perdium allocation with valid monthly budget
- [ ] Test governmental perdium allocation without monthly budget (should fail)
- [ ] Test program perdium allocation with valid yearly budget
- [ ] Test program perdium allocation without yearly budget (should fail)
- [ ] Test reversal of governmental perdium (should update monthly)
- [ ] Test reversal of program perdium (should update yearly)
- [ ] Test duplicate detection (should fail gracefully)
- [ ] Verify frontend still works correctly

## Support

For issues or questions, refer to:
- Migration script comments
- Diagnostic tool output
- Error messages in application logs
- Database constraint violations in MySQL error log
