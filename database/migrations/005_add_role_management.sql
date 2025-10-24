-- Role Management System
SET NAMES utf8mb4;

-- ============================================================================
-- ROLE SETTINGS TABLE
-- Track which roles are active/inactive system-wide
-- ============================================================================

CREATE TABLE IF NOT EXISTS role_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_value VARCHAR(50) NOT NULL UNIQUE COMMENT 'Role value from Roles.php',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Is this role currently enabled?',
    display_order INT DEFAULT 0 COMMENT 'Custom display order (overrides hierarchy)',
    notes TEXT NULL COMMENT 'Admin notes about this role',
    disabled_at TIMESTAMP NULL COMMENT 'When role was disabled',
    disabled_by INT NULL COMMENT 'Who disabled the role',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (disabled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active),
    INDEX idx_role (role_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Manage which roles are active/visible in the system';

-- ============================================================================
-- POPULATE WITH CURRENT ROLES
-- ============================================================================

-- Insert all current roles as active by default
INSERT INTO role_settings (role_value, is_active, display_order) VALUES
('super_admin', TRUE, 1),
('admin', TRUE, 2),
('principal', TRUE, 3),
('counselor', TRUE, 4),
('substitute_manager', TRUE, 5),
('business_manager', TRUE, 6),
('maintenance_director', TRUE, 7),
('custodial_manager', TRUE, 8),
('maintenance_staff', TRUE, 9),
('custodial', TRUE, 10),
('cafeteria', TRUE, 11),
('student', TRUE, 12),
('staff', TRUE, 13)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
