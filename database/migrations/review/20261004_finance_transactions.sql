-- ============================================================================================
-- SJQIBMS — Financial Management, Phase 2: collections and disbursements
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`). Requires 20261003_role_punong_barangay.
--
-- Creates THREE new tables; no existing table is changed.
--   finance_categories: Income / Expense categories, managed by the Treasurer (renamed or deactivated, never deleted).
--     Seeded with the ten standard categories. is_allotment marks the category whose income uses NTA-YYYY-## numbers.
--   finance_transactions: every collection (Income) and disbursement (Expense). Money is DECIMAL(14,2).
--     reference_no: Income = the OR number typed by the Treasurer (e.g. OR-2026-0412); allotments = NTA-YYYY-##;
--     Expenses = DV-YYYY-####. ref_kind / ref_year / ref_seq hold the automatic numbers (restarting every year); the
--     UNIQUE keys guard against duplicates.
--     Status: Income is 'posted' when recorded; Expense goes 'pending_approval' -> 'approved' or 'rejected' (reason
--     required) -> 'released'. Any record can be 'cancelled' with a required reason. Nothing is ever deleted.
--   finance_attachments: receipts and supporting documents (images / PDF) stored privately in storage/finance/ and
--     served only through finance_attachment.php. Removing an attachment archives it; the file is kept.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('finance_categories', 'finance_transactions', 'finance_attachments'); -- expected: 0
-- ============================================================================================

CREATE TABLE finance_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    is_allotment TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    created_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_finance_categories_name (name),
    KEY idx_finance_categories_type (type, is_active),
    CONSTRAINT fk_finance_categories_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO finance_categories (name, type, is_allotment, sort_order) VALUES
    ('Service Fees', 'income', 0, 10),
    ('Allotment', 'income', 1, 20),
    ('Donations', 'income', 0, 30),
    ('Other Income', 'income', 0, 90),
    ('Personnel', 'expense', 0, 10),
    ('Supplies', 'expense', 0, 20),
    ('Infrastructure', 'expense', 0, 30),
    ('Utilities', 'expense', 0, 40),
    ('Programs/Activities', 'expense', 0, 50),
    ('Other Expense', 'expense', 0, 90);

CREATE TABLE finance_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(30) NOT NULL,
    ref_kind ENUM('or', 'nta', 'dv') NOT NULL,
    ref_year SMALLINT UNSIGNED NULL,
    ref_seq INT UNSIGNED NULL,
    type ENUM('income', 'expense') NOT NULL,
    transaction_date DATE NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    payor_or_payee VARCHAR(150) NOT NULL,
    resident_id BIGINT UNSIGNED NULL,
    payment_mode ENUM('cash', 'check', 'bank_transfer', 'other') NULL,
    check_no VARCHAR(40) NULL,
    status ENUM('posted', 'pending_approval', 'approved', 'rejected', 'released', 'cancelled') NOT NULL,
    remarks TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_by BIGINT UNSIGNED NULL,
    rejected_at DATETIME NULL,
    reject_reason VARCHAR(255) NULL,
    released_by BIGINT UNSIGNED NULL,
    released_at DATETIME NULL,
    release_date DATE NULL,
    cancelled_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    status_before_cancel VARCHAR(20) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_finance_transactions_reference (reference_no),
    UNIQUE KEY uq_finance_transactions_sequence (ref_kind, ref_year, ref_seq),
    KEY idx_finance_transactions_type_status (type, status),
    KEY idx_finance_transactions_date (transaction_date),
    KEY idx_finance_transactions_release (release_date),
    KEY idx_finance_transactions_category (category_id),
    KEY idx_finance_transactions_resident (resident_id),
    CONSTRAINT fk_finance_transactions_category FOREIGN KEY (category_id) REFERENCES finance_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_finance_transactions_resident FOREIGN KEY (resident_id) REFERENCES residents (id) ON DELETE RESTRICT,
    CONSTRAINT fk_finance_transactions_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_transactions_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_transactions_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_transactions_rejected_by FOREIGN KEY (rejected_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_transactions_released_by FOREIGN KEY (released_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_transactions_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_finance_transactions_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE finance_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(150) NOT NULL,
    stored_name VARCHAR(60) NOT NULL,
    mime_type VARCHAR(50) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    archived_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_finance_attachments_stored (stored_name),
    KEY idx_finance_attachments_transaction (transaction_id, archived_at),
    CONSTRAINT fk_finance_attachments_transaction FOREIGN KEY (transaction_id) REFERENCES finance_transactions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_finance_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_finance_attachments_archived_by FOREIGN KEY (archived_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
