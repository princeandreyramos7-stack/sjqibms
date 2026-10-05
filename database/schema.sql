CREATE DATABASE IF NOT EXISTS `sjqibms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sjqibms`;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'secretary', 'treasurer', 'health_worker', 'official', 'resident') NOT NULL,
    status ENUM('pending', 'active', 'suspended') NOT NULL DEFAULT 'pending',
    resident_id BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_status (status),
    INDEX idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE residents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    household_no VARCHAR(50) NULL,
    first_name VARCHAR(80) NOT NULL,
    middle_name VARCHAR(80) NULL,
    last_name VARCHAR(80) NOT NULL,
    suffix VARCHAR(20) NULL,
    birth_date DATE NULL,
    sex ENUM('male', 'female', 'other') NULL,
    civil_status ENUM('single', 'married', 'widowed', 'separated', 'other') NULL,
    contact_number VARCHAR(30) NULL,
    address TEXT NOT NULL,
    purok VARCHAR(80) NOT NULL,
    photo_path VARCHAR(255) NULL,
    status ENUM('active', 'moved', 'deceased', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_residents_name (last_name, first_name),
    INDEX idx_residents_purok (purok),
    CONSTRAINT fk_residents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE users ADD CONSTRAINT fk_users_resident FOREIGN KEY (resident_id) REFERENCES residents(id) ON DELETE SET NULL;

CREATE TABLE document_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resident_id BIGINT UNSIGNED NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    purpose TEXT NOT NULL,
    status ENUM('pending', 'approved', 'released', 'rejected') NOT NULL DEFAULT 'pending',
    reference_code VARCHAR(40) NOT NULL UNIQUE,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    released_at DATETIME NULL,
    approved_by BIGINT UNSIGNED NULL,
    CONSTRAINT fk_document_resident FOREIGN KEY (resident_id) REFERENCES residents(id),
    CONSTRAINT fk_document_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_document_status (status)
) ENGINE=InnoDB;

CREATE TABLE announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    audience ENUM('public', 'all_residents', 'purok', 'selected_users') NOT NULL DEFAULT 'public',
    target_purok VARCHAR(80) NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcement_author FOREIGN KEY (author_id) REFERENCES users(id),
    INDEX idx_announcement_status (status)
) ENGINE=InnoDB;

CREATE TABLE push_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    endpoint TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL UNIQUE,
    subscription_json JSON NOT NULL,
    user_agent VARCHAR(500) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_push_user_active (user_id, is_active)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;

-- Development-only seed account. Replace this password before any real deployment.
-- Password: password
INSERT INTO users (name, email, password_hash, role, status)
VALUES ('System Administrator', 'admin@san-jose.local', '$2y$10$OVt/MecLN0TuoOaS35ve9enjWMG6KN8oTyfhSnzcwaw167dccsxxu', 'super_admin', 'active');
