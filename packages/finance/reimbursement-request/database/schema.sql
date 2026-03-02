-- Reimbursement Request & Fuel Tracking Schema
-- Package: finance.reimbursement-request v1.0.0

CREATE TABLE IF NOT EXISTS reimb_monetary_requests (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id        INT UNSIGNED NOT NULL COMMENT 'Hub user ID',
    expense_date        DATE NOT NULL,
    category            VARCHAR(100) NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    vendor_name         VARCHAR(255) NULL,
    receipt_type        ENUM('upload','physical') NOT NULL DEFAULT 'upload',
    receipt_path        VARCHAR(500) NULL COMMENT 'Path to uploaded receipt file',
    notes               TEXT NULL,
    status              ENUM('submitted','reviewing','needs_info','approved','denied','paid') NOT NULL DEFAULT 'submitted',
    supervisor_id       INT UNSIGNED NULL,
    supervisor_date     TIMESTAMP NULL,
    bm_id               INT UNSIGNED NULL COMMENT 'Business manager who marked paid',
    paid_date           DATE NULL,
    paid_reference      VARCHAR(255) NULL COMMENT 'Check number or reference',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_requester (requester_id, status),
    INDEX idx_status (status, expense_date),
    INDEX idx_paid (status, paid_date),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reimb_fuel_trips (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED NOT NULL COMMENT 'Hub user ID',
    trip_date           DATE NOT NULL,
    destination         VARCHAR(255) NOT NULL,
    with_trailer        TINYINT(1) NOT NULL DEFAULT 0,
    trip_miles          INT NOT NULL,
    category            VARCHAR(100) NOT NULL,
    notes               TEXT NULL,
    acquiring_now       TINYINT(1) NOT NULL DEFAULT 0,
    gallons_earned      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    gallons_claimed     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    receipt_required    TINYINT(1) NOT NULL DEFAULT 0,
    receipt_type        ENUM('upload','physical','none') NOT NULL DEFAULT 'none',
    receipt_path        VARCHAR(500) NULL,
    fiscal_year_start   DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_user_date (user_id, trip_date),
    INDEX idx_category (category, trip_date),
    INDEX idx_fiscal (fiscal_year_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reimb_settings (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fiscal_year_start_month     TINYINT NOT NULL DEFAULT 9,
    fiscal_year_start_day       TINYINT NOT NULL DEFAULT 1,
    allow_physical_receipts     TINYINT(1) NOT NULL DEFAULT 1,
    notify_submitter_on_submit  TINYINT(1) NOT NULL DEFAULT 0,
    notify_submitter_on_approval TINYINT(1) NOT NULL DEFAULT 0,
    notify_supervisor_on_submit TINYINT(1) NOT NULL DEFAULT 0,
    notify_bm_on_submit         TINYINT(1) NOT NULL DEFAULT 0,
    notify_bm_on_approval       TINYINT(1) NOT NULL DEFAULT 0,
    bm_notification_email       VARCHAR(320) NULL,
    admin_notification_email    VARCHAR(320) NULL,
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default settings row
INSERT INTO reimb_settings (fiscal_year_start_month, fiscal_year_start_day)
VALUES (9, 1)
ON DUPLICATE KEY UPDATE id=id;

CREATE TABLE IF NOT EXISTS reimb_audit_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type     VARCHAR(50) NOT NULL COMMENT 'monetary_request or fuel_trip',
    entity_id       INT UNSIGNED NOT NULL,
    action          VARCHAR(100) NOT NULL,
    actor_id        INT UNSIGNED NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    reason          TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_actor (actor_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
