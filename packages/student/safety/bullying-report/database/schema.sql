-- Bullying Report Package Schema
-- Package: student.bullying-report v1.0.0

CREATE TABLE IF NOT EXISTS br_reports (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_date     DATE NOT NULL,
    report_time     TIME NULL,
    location        VARCHAR(100) NOT NULL,
    incident_types  JSON NOT NULL COMMENT 'Array of incident type keys',
    students_involved TEXT NULL,
    witness_present TINYINT(1) NOT NULL DEFAULT 0,
    witness_names   TEXT NULL,
    description     TEXT NOT NULL,
    previous_incidents TINYINT(1) NOT NULL DEFAULT 0,
    reporter_name   VARCHAR(255) NULL,
    reporter_grade  VARCHAR(50) NULL,
    reporter_email  VARCHAR(320) NULL,
    status          ENUM('new','under_review','investigating','resolved','dismissed') NOT NULL DEFAULT 'new',
    assigned_to     INT UNSIGNED NULL,
    staff_notes     TEXT NULL,
    resolution_date DATE NULL,
    reported_by     INT UNSIGNED NULL COMMENT 'Hub user ID if logged in',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_status (status),
    INDEX idx_report_date (report_date),
    INDEX idx_assigned (assigned_to),
    INDEX idx_reporter (reported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS br_audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id   INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,
    actor_id    INT UNSIGNED NULL,
    old_values  JSON NULL,
    new_values  JSON NULL,
    reason      TEXT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report (report_id),
    INDEX idx_actor (actor_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
