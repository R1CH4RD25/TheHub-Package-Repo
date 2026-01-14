-- =============================================================================
-- Package Permission System Schema
-- =============================================================================
-- Implements three-tier architecture (Hub, Management, Admin) with:
-- - Organization-defined roles (flexible per org)
-- - Package-defined roles (immutable, shipped with package)
-- - Role mapping layer (org roles → package roles)
-- - Section-aware permissions (hub.*, management.*, admin.*)
-- =============================================================================

-- Organization Roles
-- These are custom roles created by each organization to match their structure
-- Examples: Principal, Superintendent, Maintenance Director, Teacher, etc.
CREATE TABLE IF NOT EXISTS org_roles (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_org_role_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User to Organization Role Assignment
-- Users can have multiple org roles (many-to-many)
CREATE TABLE IF NOT EXISTS user_org_roles (
    user_id INT NOT NULL,
    org_role_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    PRIMARY KEY (user_id, org_role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (org_role_id) REFERENCES org_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_roles (user_id),
    INDEX idx_org_role_users (org_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package Roles
-- Immutable roles defined by package creators in manifest
-- These are the same across all installations of a package
-- NOTE: package_id references sections.id (packages are called sections in the database)
CREATE TABLE IF NOT EXISTS package_roles (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,                    -- References sections.id
    role_key VARCHAR(50) NOT NULL,              -- Unique identifier: administration, director, staff
    role_name VARCHAR(100) NOT NULL,            -- Display name: Administration, Director, Maintenance Staff
    tier_level TINYINT UNSIGNED NOT NULL,       -- Display hierarchy: 1=highest, 3=lowest (visual only)
    description TEXT,                           -- Help text for org admins mapping roles
    permissions JSON NOT NULL,                  -- Array of permissions: ["hub.submit_form", "management.view_reports"]
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_package_role (package_id, role_key),
    FOREIGN KEY (package_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_package_roles (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package Role Mappings
-- Maps organization roles to package roles (many-to-many)
-- Example: "Maintenance Director" org role → "director" package role in Vehicle Maintenance
CREATE TABLE IF NOT EXISTS package_role_mappings (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,                    -- References sections.id
    package_role_id INT UNSIGNED NOT NULL,
    org_role_id INT UNSIGNED NOT NULL,
    mapped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mapped_by INT,
    UNIQUE KEY unique_mapping (package_id, package_role_id, org_role_id),
    FOREIGN KEY (package_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (package_role_id) REFERENCES package_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (org_role_id) REFERENCES org_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (mapped_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_package_mappings (package_id),
    INDEX idx_org_role_mappings (org_role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package Sections
-- Defines what appears in Hub, Management, Admin for each package
-- Parsed from package manifest during installation
CREATE TABLE IF NOT EXISTS package_sections (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,                    -- References sections.id
    section_type ENUM('hub', 'management', 'admin') NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    config JSON,                                -- Section-specific config from manifest
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_package_section (package_id, section_type),
    FOREIGN KEY (package_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_section_type (section_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package Hub Items
-- Forms, lookups, and other items that appear in Hub (mobile-first)
CREATE TABLE IF NOT EXISTS package_hub_items (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,                    -- References sections.id
    item_key VARCHAR(50) NOT NULL,              -- Unique key: work_order, my_orders
    item_type ENUM('form', 'lookup', 'card', 'custom') NOT NULL,
    title VARCHAR(100) NOT NULL,                -- Display title
    icon VARCHAR(50),                           -- Icon name/class
    route VARCHAR(255) NOT NULL,                -- URL route
    required_permission VARCHAR(100),           -- hub.submit_form, hub.view_own_records
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    config JSON,                                -- Additional configuration
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_hub_item (package_id, item_key),
    FOREIGN KEY (package_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_package_hub_items (package_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Package Management Tabs
-- Tabs that appear in Management sidebar for each package
CREATE TABLE IF NOT EXISTS package_management_tabs (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,                    -- References sections.id
    tab_key VARCHAR(50) NOT NULL,               -- Unique key: work_orders, fleet_reports
    label VARCHAR(100) NOT NULL,                -- Display label
    route VARCHAR(255) NOT NULL,                -- URL route
    required_permission VARCHAR(100),           -- management.view_reports, management.export_data
    description TEXT,                           -- Help text
    icon VARCHAR(50),                           -- Icon name/class
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    config JSON,                                -- Additional configuration
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_management_tab (package_id, tab_key),
    FOREIGN KEY (package_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_package_mgmt_tabs (package_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Seed Data: Default Organization Roles
-- =============================================================================

INSERT INTO org_roles (name, description) VALUES
('Administrator', 'Full system access and control'),
('Principal', 'School principal with broad oversight'),
('Superintendent', 'District-level leadership'),
('Teacher', 'Classroom teaching staff'),
('Student', 'Enrolled students'),
('Parent/Guardian', 'Parent or legal guardian of student')
ON DUPLICATE KEY UPDATE name=name;

-- =============================================================================
-- Example Queries
-- =============================================================================

-- Get all permissions for a user in a specific package
-- SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(pr.permissions, CONCAT('$[', idx, ']'))) AS permission
-- FROM user_org_roles uor
-- JOIN package_role_mappings prm ON uor.org_role_id = prm.org_role_id
-- JOIN package_roles pr ON prm.package_role_id = pr.id
-- JOIN JSON_TABLE(pr.permissions, '$[*]' COLUMNS (idx FOR ORDINALITY)) AS jt
-- WHERE uor.user_id = ? AND prm.package_id = ?;

-- Get all packages where user has management permissions
-- SELECT DISTINCT p.*
-- FROM packages p
-- JOIN package_role_mappings prm ON p.id = prm.package_id
-- JOIN user_org_roles uor ON prm.org_role_id = uor.org_role_id
-- JOIN package_roles pr ON prm.package_role_id = pr.id
-- WHERE uor.user_id = ?
-- AND JSON_SEARCH(pr.permissions, 'one', 'management.%') IS NOT NULL;

-- Get all Hub items visible to a user for a package
-- SELECT phi.*
-- FROM package_hub_items phi
-- JOIN package_role_mappings prm ON phi.package_id = prm.package_id
-- JOIN user_org_roles uor ON prm.org_role_id = uor.org_role_id
-- JOIN package_roles pr ON prm.package_role_id = pr.id
-- WHERE uor.user_id = ?
-- AND phi.package_id = ?
-- AND phi.is_active = 1
-- AND JSON_CONTAINS(pr.permissions, JSON_QUOTE(phi.required_permission));

-- =============================================================================
-- Notes
-- =============================================================================
-- 1. Permissions are ADDITIVE: Users with multiple org roles get union of all permissions
-- 2. Super admins BYPASS all package permission checks (check global role first)
-- 3. Package roles and permissions are IMMUTABLE after package creation
-- 4. Organizations map their roles to package roles during package configuration
-- 5. Section visibility (Hub, Management links) determined by ANY permission in that section
