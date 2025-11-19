-- sections table already exists (packages are called "sections" in database)
-- Add capability support columns
ALTER TABLE sections
ADD COLUMN IF NOT EXISTS capabilities_json TEXT DEFAULT NULL COMMENT 'JSON capability definitions from manifest',
ADD COLUMN IF NOT EXISTS supports_capabilities BOOLEAN DEFAULT FALSE COMMENT 'Whether package uses new capability system';

-- New table for runtime capability checks (performance)
CREATE TABLE IF NOT EXISTS package_capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_slug VARCHAR(100) NOT NULL COMMENT 'References sections.slug',
    capability_key VARCHAR(50) NOT NULL COMMENT 'Machine name (e.g., approve, view_all)',
    capability_label VARCHAR(255) NOT NULL COMMENT 'Human-readable label',
    capability_description TEXT COMMENT 'Explains what this capability grants',
    capability_type ENUM('action', 'read', 'admin', 'data') DEFAULT 'action' COMMENT 'action=create/submit, read=view, admin=configure, data=export',
    default_roles JSON DEFAULT NULL COMMENT 'Roles that should receive this capability by default',
    dependencies JSON DEFAULT NULL COMMENT 'Capability keys that this capability requires',
    added_in_version VARCHAR(20) DEFAULT NULL COMMENT 'Package version that introduced this capability',
    sort_order INT DEFAULT 0 COMMENT 'Display order in permission matrix',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_package_capability (package_slug, capability_key),
    INDEX idx_package (package_slug),
    INDEX idx_type (capability_type) COMMENT 'For Access Explorer filtering by type',
    INDEX idx_version (package_slug, added_in_version) COMMENT 'For upgrade delta detection'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Explicit capability declarations per package';

-- New table for role-capability mappings (compatible with existing role system)
CREATE TABLE IF NOT EXISTS package_role_capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_slug VARCHAR(100) NOT NULL COMMENT 'References sections.slug',
    role ENUM('staff', 'student', 'maintenance_staff', 'custodial', 'cafeteria', 'custodial_manager', 'maintenance_director', 'business_manager', 'substitute_manager', 'counselor', 'principal', 'admin', 'super_admin') NOT NULL,
    capability_key VARCHAR(50) NOT NULL COMMENT 'Links to package_capabilities.capability_key',
    granted_by INT DEFAULT NULL COMMENT 'User ID who granted this permission',
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_capability (package_slug, role, capability_key),
    INDEX idx_package_role (package_slug, role) COMMENT 'Fast permission checks',
    INDEX idx_role (role) COMMENT 'For Access Explorer by-role queries',
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Which roles have which capabilities per package';
