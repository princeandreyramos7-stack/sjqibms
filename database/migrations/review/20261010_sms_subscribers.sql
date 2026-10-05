-- ============================================================================================
-- SJQIBMS — SMS announcement subscribers (public registration with SMS OTP)
-- STATUS: REVIEW ONLY. APPLY ONLY WITH EXPLICIT APPROVAL AND A VERIFIED BACKUP.
-- Target: MariaDB 10.4.32 (live database `sjqibms`).
--
-- Creates TWO new tables; no existing table is changed.
--   sms_subscribers : residents who registered on the public page to receive announcement texts. One mobile number,
--                     one registration (UNIQUE). The number is stored as 639XXXXXXXXX (the format Semaphore needs).
--                     The consent text and time are kept as proof of consent (Data Privacy Act of 2012).
--   sms_otp_requests: one row per OTP sent. Only a hash of the 6-digit code is stored. Used for the 5-minute expiry,
--                     the 3 wrong tries, the 60-second resend cooldown, the 5-per-number-per-day limit and a per-IP
--                     limit against SMS flooding.
--
-- PRE-FLIGHT (read-only):
--   SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
--     AND TABLE_NAME IN ('sms_subscribers', 'sms_otp_requests'); -- expected: 0
-- ============================================================================================

CREATE TABLE sms_subscribers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    purok VARCHAR(80) NOT NULL,
    street VARCHAR(150) NULL,
    mobile CHAR(12) NOT NULL,
    consent_text VARCHAR(500) NOT NULL,
    consent_at DATETIME NOT NULL,
    verified_at DATETIME NOT NULL,
    status ENUM('active', 'unsubscribed') NOT NULL DEFAULT 'active',
    registered_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sms_subscribers_mobile (mobile),
    KEY idx_sms_subscribers_status (status, purok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sms_otp_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mobile CHAR(12) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    request_token CHAR(64) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    used_at DATETIME NULL,
    requested_ip VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sms_otp_token (request_token),
    KEY idx_sms_otp_mobile (mobile, created_at),
    KEY idx_sms_otp_ip (requested_ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
