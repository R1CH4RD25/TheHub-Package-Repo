-- ============================================================
-- Sprint 2: Security Layer Schema
-- Package rate limiting, sensitive action tokens, audit events
-- ============================================================

-- Rate limiting storage (fallback when Redis unavailable)
CREATE TABLE IF NOT EXISTS package_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_key VARCHAR(255) NOT NULL COMMENT 'e.g., com.woodson.student-dir.mutation.resetPassword',
    window_start DATETIME NOT NULL,
    request_count INT NOT NULL DEFAULT 1,
    max_requests INT NOT NULL DEFAULT 60,
    window_seconds INT NOT NULL DEFAULT 60,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_rate_user_action (user_id, action_key),
    INDEX idx_rate_window (window_start),
    INDEX idx_rate_cleanup (window_start, window_seconds),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sensitive action tokens (time-limited, single-use reveal tokens)
CREATE TABLE IF NOT EXISTS sensitive_action_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(128) NOT NULL UNIQUE COMMENT 'Secure random token (hex)',
    user_id INT NOT NULL,
    package_id VARCHAR(255) NOT NULL,
    action_name VARCHAR(255) NOT NULL COMMENT 'e.g., revealPassword, viewSSN',
    target_record_id INT NULL COMMENT 'Record being revealed',
    reason TEXT NOT NULL COMMENT 'User-supplied justification',
    expires_at DATETIME NOT NULL COMMENT 'Token expiry (30-60s)',
    used_at DATETIME NULL COMMENT 'When token was consumed',
    revoked_at DATETIME NULL COMMENT 'Manual revocation',
    ip_address VARCHAR(45) NOT NULL,
    correlation_id VARCHAR(36) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token_lookup (token),
    INDEX idx_token_user (user_id),
    INDEX idx_token_expiry (expires_at),
    INDEX idx_token_package (package_id, action_name),
    INDEX idx_token_cleanup (expires_at, used_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package audit events log (dedicated high-volume table, separate from main audit_log)
CREATE TABLE IF NOT EXISTS package_audit_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL COMMENT 'query, mutation, access_denied, rate_limited, reveal, policy_violation',
    package_id VARCHAR(255) NOT NULL,
    action_name VARCHAR(255) NOT NULL,
    user_id INT NULL,
    target_record_id INT NULL,
    severity ENUM('info', 'warning', 'critical', 'alert') NOT NULL DEFAULT 'info',
    outcome ENUM('success', 'denied', 'error', 'rate_limited') NOT NULL DEFAULT 'success',
    details JSON NULL COMMENT 'Structured event data',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    correlation_id VARCHAR(36) NOT NULL,
    execution_time_ms DECIMAL(10,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_pae_package (package_id),
    INDEX idx_pae_user (user_id),
    INDEX idx_pae_type (event_type),
    INDEX idx_pae_severity (severity),
    INDEX idx_pae_outcome (outcome),
    INDEX idx_pae_created (created_at),
    INDEX idx_pae_correlation (correlation_id),
    INDEX idx_pae_package_action (package_id, action_name),
    INDEX idx_pae_user_type (user_id, event_type, created_at),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert rules for anomaly detection
CREATE TABLE IF NOT EXISTS package_alert_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id VARCHAR(255) NULL COMMENT 'NULL = applies to all packages',
    rule_name VARCHAR(100) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    threshold_count INT NOT NULL DEFAULT 10 COMMENT 'Trigger after N events',
    threshold_window_seconds INT NOT NULL DEFAULT 300 COMMENT 'Within this time window',
    severity ENUM('warning', 'critical', 'alert') NOT NULL DEFAULT 'warning',
    action ENUM('log', 'notify', 'block') NOT NULL DEFAULT 'log',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE INDEX idx_par_name (rule_name),
    INDEX idx_par_package (package_id),
    INDEX idx_par_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggered alerts log
CREATE TABLE IF NOT EXISTS package_triggered_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    user_id INT NULL,
    package_id VARCHAR(255) NULL,
    event_count INT NOT NULL,
    window_start DATETIME NOT NULL,
    window_end DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_pta_rule (rule_id),
    INDEX idx_pta_user (user_id),
    INDEX idx_pta_unresolved (resolved_at),

    FOREIGN KEY (rule_id) REFERENCES package_alert_rules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default alert rules
INSERT IGNORE INTO package_alert_rules (rule_name, event_type, threshold_count, threshold_window_seconds, severity, action) VALUES
('excessive_queries', 'query', 100, 60, 'warning', 'log'),
('excessive_mutations', 'mutation', 50, 60, 'warning', 'log'),
('access_denied_spike', 'access_denied', 10, 300, 'critical', 'notify'),
('rate_limit_abuse', 'rate_limited', 20, 300, 'alert', 'block'),
('sensitive_reveal_spike', 'reveal', 5, 300, 'critical', 'notify');
