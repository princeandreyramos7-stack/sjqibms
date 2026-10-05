-- ============================================================================================
-- SJQIBMS — Financial Management, Phase 3: annual budget
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires 20261004_finance_transactions.
--
-- Creates ONE new table; no existing table is changed.
--   finance_budgets: the amount appropriated for one Expense category in one year (DECIMAL(14,2)), managed by the
--   Treasurer. One row per year and category. Changes are recorded in the audit log with old and new amounts.
--   Released, remaining and percentage used are computed from finance_transactions (never stored).
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_budgets'; -- expected: 0
-- ============================================================================================

CREATE TABLE finance_budgets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    budget_year SMALLINT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    appropriated DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_finance_budgets_year_category (budget_year, category_id),
    KEY idx_finance_budgets_category (category_id),
    CONSTRAINT fk_finance_budgets_category FOREIGN KEY (category_id) REFERENCES finance_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_finance_budgets_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_budgets_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_finance_budgets_amount CHECK (appropriated >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
