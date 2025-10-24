-- ============================================================================
-- ENHANCED PACKAGE MANAGEMENT SCHEMA
-- Version control, compatibility checks, and failure reporting
-- ============================================================================

-- Add version/compatibility fields to existing section_packages table
ALTER TABLE section_packages
ADD COLUMN hub_version_min VARCHAR(20) COMMENT 'Minimum Hub version required',
ADD COLUMN hub_version_max VARCHAR(20) COMMENT 'Maximum Hub version (if known)',
ADD COLUMN php_version_min VARCHAR(20) COMMENT 'Minimum PHP version',
ADD COLUMN mysql_version_min VARCHAR(20) COMMENT 'Minimum MySQL version',
ADD COLUMN dependencies JSON COMMENT 'Required packages/modules',
ADD COLUMN conflicts JSON COMMENT 'Conflicting packages',
ADD COLUMN tested_up_to VARCHAR(20) COMMENT 'Tested up to Hub version',
ADD COLUMN is_deprecated BOOLEAN DEFAULT FALSE COMMENT 'Package deprecated',
ADD COLUMN deprecation_reason TEXT COMMENT 'Why deprecated',
ADD COLUMN changelog JSON COMMENT 'Version history',
ADD COLUMN screenshots JSON COMMENT 'Screenshot URLs',
ADD COLUMN repository_url VARCHAR(255) COMMENT 'Source repository',
ADD COLUMN demo_url VARCHAR(255) COMMENT 'Live demo',
ADD COLUMN support_url VARCHAR(255) COMMENT 'Support/docs URL';

-- Package installation attempts (success or failure)
CREATE TABLE IF NOT EXISTS section_package_installs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id VARCHAR(100) NOT NULL COMMENT 'Package identifier',
    package_version VARCHAR(20) NOT NULL COMMENT 'Version attempted',
    status ENUM('pending', 'success', 'failed', 'rolled_back') NOT NULL,
    installation_type ENUM('new', 'upgrade', 'downgrade', 'reinstall') DEFAULT 'new',
    attempted_by INT COMMENT 'User who attempted install',
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    section_id INT NULL COMMENT 'Created section (if successful)',
    error_message TEXT COMMENT 'Error if failed',
    compatibility_report JSON COMMENT 'Full compatibility check results',
    installation_log TEXT COMMENT 'Detailed installation log',
    FOREIGN KEY (attempted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
    INDEX idx_package (package_id, package_version),
    INDEX idx_status (status),
    INDEX idx_attempted_at (attempted_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track all package installation attempts';

-- Compatibility check results
CREATE TABLE IF NOT EXISTS section_compatibility_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    install_id INT NOT NULL COMMENT 'Reference to install attempt',
    check_type VARCHAR(50) NOT NULL COMMENT 'Type of check (hub_version, dependencies, etc.)',
    check_name VARCHAR(100) NOT NULL COMMENT 'What was checked',
    required_value VARCHAR(100) COMMENT 'What was required',
    actual_value VARCHAR(100) COMMENT 'What was found',
    status ENUM('pass', 'fail', 'warning') NOT NULL,
    severity ENUM('critical', 'error', 'warning', 'info') DEFAULT 'error',
    message TEXT COMMENT 'Human-readable message',
    resolution TEXT COMMENT 'How to fix if failed',
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (install_id) REFERENCES section_package_installs(id) ON DELETE CASCADE,
    INDEX idx_install (install_id),
    INDEX idx_status (status, severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Detailed compatibility check results';

-- Package dependencies (normalized)
CREATE TABLE IF NOT EXISTS section_package_dependencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id VARCHAR(100) NOT NULL,
    package_version VARCHAR(20) NOT NULL,
    dependency_type ENUM('package', 'module', 'extension', 'table') NOT NULL,
    dependency_name VARCHAR(100) NOT NULL,
    version_constraint VARCHAR(50) COMMENT 'e.g., ^1.0.0, >=2.0.0',
    is_optional BOOLEAN DEFAULT FALSE,
    UNIQUE KEY unique_dependency (package_id, package_version, dependency_type, dependency_name),
    INDEX idx_package (package_id, package_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Package dependencies for checking';

-- Migration scripts for package updates
CREATE TABLE IF NOT EXISTS section_package_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id VARCHAR(100) NOT NULL,
    from_version VARCHAR(20) NOT NULL,
    to_version VARCHAR(20) NOT NULL,
    migration_sql TEXT COMMENT 'SQL to run for upgrade',
    rollback_sql TEXT COMMENT 'SQL to rollback',
    migration_order INT DEFAULT 0 COMMENT 'Order to run migrations',
    is_required BOOLEAN DEFAULT TRUE COMMENT 'Can be skipped?',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_migration (package_id, from_version, to_version),
    INDEX idx_package (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Version upgrade/downgrade migrations';

-- Package ratings and reviews
CREATE TABLE IF NOT EXISTS section_package_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL COMMENT '1-5 stars',
    title VARCHAR(255),
    review_text TEXT,
    hub_version VARCHAR(20) COMMENT 'Hub version when reviewed',
    is_verified BOOLEAN DEFAULT FALSE COMMENT 'Verified purchase/install',
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_package (package_id, rating DESC),
    INDEX idx_user (user_id),
    CONSTRAINT rating_range CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User ratings and reviews for packages';

-- Remote package repositories (like npm registries)
CREATE TABLE IF NOT EXISTS section_package_repositories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL COMMENT 'Repository API URL',
    type ENUM('github', 'gitlab', 'custom', 'local') DEFAULT 'custom',
    is_official BOOLEAN DEFAULT FALSE COMMENT 'Official Hub repository',
    is_enabled BOOLEAN DEFAULT TRUE,
    requires_auth BOOLEAN DEFAULT FALSE,
    auth_token VARCHAR(255) COMMENT 'API token if needed',
    priority INT DEFAULT 50 COMMENT 'Search priority (higher = first)',
    last_sync TIMESTAMP NULL COMMENT 'Last time we synced',
    sync_interval INT DEFAULT 3600 COMMENT 'Sync frequency in seconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_enabled (is_enabled, priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Remote package repositories';

-- Cache of available packages from repositories
CREATE TABLE IF NOT EXISTS section_package_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repository_id INT NOT NULL,
    package_id VARCHAR(100) NOT NULL,
    package_data JSON NOT NULL COMMENT 'Cached package metadata',
    available_versions JSON COMMENT 'List of available versions',
    latest_version VARCHAR(20),
    download_url VARCHAR(500),
    cached_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (repository_id) REFERENCES section_package_repositories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cache (repository_id, package_id),
    INDEX idx_package (package_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cached package listings from repositories';

-- Package update notifications
CREATE TABLE IF NOT EXISTS section_package_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    installation_id INT NOT NULL COMMENT 'Installed section',
    package_id VARCHAR(100) NOT NULL,
    current_version VARCHAR(20) NOT NULL,
    available_version VARCHAR(20) NOT NULL,
    update_type ENUM('major', 'minor', 'patch', 'security') NOT NULL,
    is_security BOOLEAN DEFAULT FALSE COMMENT 'Security update',
    breaking_changes BOOLEAN DEFAULT FALSE COMMENT 'Has breaking changes',
    changelog TEXT COMMENT 'What changed',
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_dismissed BOOLEAN DEFAULT FALSE,
    auto_update_scheduled TIMESTAMP NULL,
    FOREIGN KEY (installation_id) REFERENCES section_installations(id) ON DELETE CASCADE,
    INDEX idx_installation (installation_id),
    INDEX idx_security (is_security, is_dismissed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track available updates for installed packages';

-- System requirements (expandable)
CREATE TABLE IF NOT EXISTS system_requirements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requirement_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'hub_version, php_version, etc.',
    requirement_value VARCHAR(100) NOT NULL COMMENT 'Current system value',
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cache system requirements for compatibility checks';

-- Insert current system info
INSERT INTO system_requirements (requirement_key, requirement_value) VALUES
('hub_version', '1.0.0'),
('php_version', '8.2.0'),
('mysql_version', '10.11.13'),
('format_version', '1.0.0')
ON DUPLICATE KEY UPDATE 
    requirement_value = VALUES(requirement_value),
    updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- VIEWS
-- ============================================================================

-- Easy view of installation history
CREATE OR REPLACE VIEW section_package_install_history AS
SELECT 
    pi.id,
    pi.package_id,
    pi.package_version,
    pi.status,
    pi.installation_type,
    pi.attempted_by,
    u.name as installer_name,
    pi.attempted_at,
    pi.completed_at,
    pi.section_id,
    s.display_name as section_name,
    pi.error_message,
    TIMESTAMPDIFF(SECOND, pi.attempted_at, pi.completed_at) as duration_seconds
FROM section_package_installs pi
LEFT JOIN users u ON pi.attempted_by = u.id
LEFT JOIN sections s ON pi.section_id = s.id
ORDER BY pi.attempted_at DESC;

-- Summary of compatibility issues
CREATE OR REPLACE VIEW section_package_compatibility_summary AS
SELECT 
    pi.id as install_id,
    pi.package_id,
    pi.package_version,
    pi.status as install_status,
    COUNT(*) as total_checks,
    SUM(CASE WHEN cc.status = 'fail' THEN 1 ELSE 0 END) as failures,
    SUM(CASE WHEN cc.status = 'warning' THEN 1 ELSE 0 END) as warnings,
    SUM(CASE WHEN cc.severity = 'critical' THEN 1 ELSE 0 END) as critical_issues,
    pi.attempted_at
FROM section_package_installs pi
LEFT JOIN section_compatibility_checks cc ON pi.id = cc.install_id
GROUP BY pi.id, pi.package_id, pi.package_version, pi.status, pi.attempted_at;

-- ============================================================================
-- SCHEMA COMPLETE
-- ============================================================================
