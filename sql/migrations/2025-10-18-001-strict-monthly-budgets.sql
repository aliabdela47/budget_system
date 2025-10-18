-- Migration: Enforce Strict Monthly/Yearly Budget Deduction Rules
-- Date: 2025-10-18
-- 
-- IMPORTANT: Run tools/diagnose_budget_dupes.php FIRST and clean up any duplicates
-- before applying this migration!
--
-- This migration:
-- 1. Adds a generated column month_key = COALESCE(month,'YEARLY')
-- 2. Adds a UNIQUE constraint on (budget_type, owner_id, code_id, year, month_key)
-- 3. Adds a supporting index for query performance
--
-- Changes enforce:
-- - Governmental budgets: strict monthly-only deductions (no yearly fallback)
-- - Program budgets: yearly-only deductions (month IS NULL, monthly_amount=0)

-- Add generated column for month_key
ALTER TABLE budgets 
ADD COLUMN month_key VARCHAR(20) 
GENERATED ALWAYS AS (COALESCE(month, 'YEARLY')) STORED
AFTER month;

-- Add unique constraint to prevent duplicates
ALTER TABLE budgets
ADD UNIQUE KEY uniq_budget_owner_code_year_monthkey (budget_type, owner_id, code_id, year, month_key);

-- Add supporting index for efficient queries
ALTER TABLE budgets
ADD INDEX idx_budget_owner_code_year_month (budget_type, owner_id, code_id, year, month);

-- Verification query: check if indexes were created successfully
-- SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX 
-- FROM INFORMATION_SCHEMA.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'budgets'
-- ORDER BY INDEX_NAME, SEQ_IN_INDEX;
