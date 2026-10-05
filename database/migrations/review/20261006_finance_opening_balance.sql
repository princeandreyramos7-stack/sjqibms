-- ============================================================================================
-- SJQIBMS — Financial Management, Phase 4: beginning balance
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires 20261004_finance_transactions.
--
-- Creates ONE new table; no existing table is changed.
--   finance_opening_balances: the barangay's fund balance as of the day recording starts in the system (DECIMAL(14,2),
--   may be negative only if the barangay really starts in deficit). Set once by the Treasurer. It is never edited or
--   deleted: a wrong entry is cancelled with a reason and a new one is entered. Only one entry is current at a time
--   (current_flag is 1 only while not cancelled; UNIQUE). Collections and disbursements cannot be dated before it.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_opening_balances'; -- expected: 0
-- ============================================================================================

CREATE TABLE finance_opening_balances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    as_of_date DATE NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    remarks VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancel_reason VARCHAR(255) NULL,
    current_flag TINYINT(1) AS (IF(cancelled_at IS NULL, 1, NULL)) STORED,
    UNIQUE KEY uq_finance_opening_current (current_flag),
    CONSTRAINT fk_finance_opening_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_opening_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
