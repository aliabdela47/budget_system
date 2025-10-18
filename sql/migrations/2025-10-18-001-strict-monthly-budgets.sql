-- =====================================================
-- Migration: Strict Monthly Budget Enforcement
-- Date: 2025-10-18
-- Description: Adds unique constraints to enforce:
--   1. Governmental budgets: unique monthly rows (no duplicates per owner+code+year+month)
--   2. Program budgets: unique yearly rows (no duplicates per owner+year)
-- =====================================================

-- IMPORTANT: Before running this migration:
-- 1. Run: php tools/diagnose_budget_dupes.php
-- 2. Fix any duplicate rows reported by the diagnostic script
-- 3. Verify: Run the diagnostic script again to confirm no duplicates
-- 4. Then apply this migration

-- =====================================================
-- Step 1: Add generated column for month_key
-- =====================================================
-- This column normalizes NULL months to 'YEARLY' so that MySQL's UNIQUE constraint
-- treats all yearly rows with NULL month as duplicates (since MySQL treats NULL as distinct)

ALTER TABLE budgets
  ADD COLUMN month_key VARCHAR(20) GENERATED ALWAYS AS (COALESCE(month, 'YEARLY')) STORED,
  ALGORITHM=INPLACE, 
  LOCK=NONE;

-- =====================================================
-- Step 2: Add unique constraint for budgets table
-- =====================================================
-- This enforces:
-- - Governmental monthly: one row per (governmental, owner_id, code_id, year, month)
-- - Program yearly: one row per (program, owner_id, code_id, year, 'YEARLY')

ALTER TABLE budgets
  ADD UNIQUE KEY uniq_budget_owner_code_year_monthkey (budget_type, owner_id, code_id, year, month_key);

-- =====================================================
-- Step 3: Add supporting index for lookups
-- =====================================================
-- Improves query performance for monthly lookups

CREATE INDEX idx_budget_owner_code_year_month 
  ON budgets (budget_type, owner_id, code_id, year, month);

-- =====================================================
-- Step 4: Add unique constraint for p_budgets table (if exists)
-- =====================================================
-- Uncomment the appropriate variant based on your schema:

-- Variant A: If p_budgets has code_id column
-- ALTER TABLE p_budgets 
--   ADD UNIQUE KEY uniq_pbudgets_owner_code_year (owner_id, code_id, year);

-- Variant B: If p_budgets does NOT have code_id column
-- ALTER TABLE p_budgets 
--   ADD UNIQUE KEY uniq_pbudgets_owner_year (owner_id, year);

-- =====================================================
-- Migration Complete
-- =====================================================
-- After applying this migration:
-- 1. Test governmental perdium allocations with monthly budgets
-- 2. Verify that duplicate insertions are prevented
-- 3. Verify that program budgets remain yearly-only
-- =====================================================
