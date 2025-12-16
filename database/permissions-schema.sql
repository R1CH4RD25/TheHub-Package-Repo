-- Permissions and Role Management Schema
-- Run after main schema.sql

-- Permissions table - defines all available permissions
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL, -- e.g., 'users', 'packages', 'fuel', 'reports'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roles table - defines roles with metadata
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE, -- system roles can't be deleted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions junction table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT NULL,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_role (role_id),
    INDEX idx_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User roles junction table (replaces user_global_roles functionality)
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT NULL,
    UNIQUE KEY unique_user_role (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups table (for organizing users)
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group members junction table
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NULL,
    UNIQUE KEY unique_group_member (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_group (group_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Group roles junction table (groups can have roles)
CREATE TABLE IF NOT EXISTS group_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    role_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT NULL,
    UNIQUE KEY unique_group_role (group_id, role_id),
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_group (group_id),
    INDEX idx_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default permissions
INSERT INTO permissions (name, display_name, description, category) VALUES
-- User management
('view_users', 'View Users', 'View user list and details', 'users'),
('create_users', 'Create Users', 'Invite and create new users', 'users'),
('edit_users', 'Edit Users', 'Modify user details and status', 'users'),
('delete_users', 'Delete Users', 'Remove users from system', 'users'),
('manage_roles', 'Manage Roles', 'Assign roles to users', 'users'),

-- Package management
('view_packages', 'View Packages', 'View package list and tracking', 'packages'),
('create_packages', 'Create Packages', 'Log new package arrivals', 'packages'),
('edit_packages', 'Edit Packages', 'Modify package details', 'packages'),
('delete_packages', 'Delete Packages', 'Remove package records', 'packages'),
('pickup_packages', 'Mark Packages Picked Up', 'Process package pickups', 'packages'),

-- Fuel management
('view_fuel', 'View Fuel Records', 'View fuel entries and reports', 'fuel'),
('create_fuel', 'Create Fuel Records', 'Log fuel entries', 'fuel'),
('edit_fuel', 'Edit Fuel Records', 'Modify fuel entries', 'fuel'),
('delete_fuel', 'Delete Fuel Records', 'Remove fuel entries', 'fuel'),

-- Reports
('view_reports', 'View Reports', 'Access reports and analytics', 'reports'),
('export_reports', 'Export Reports', 'Download reports and data', 'reports'),

-- System
('manage_settings', 'Manage Settings', 'Configure system settings', 'system'),
('view_audit_logs', 'View Audit Logs', 'Access system audit trail', 'system')
ON DUPLICATE KEY UPDATE name=name;

-- Insert default roles
INSERT INTO roles (name, display_name, description, is_system) VALUES
('super_admin', 'Super Administrator', 'Full system access', TRUE),
('admin', 'Administrator', 'Administrative access to most features', TRUE),
('maintenance_director', 'Maintenance Director', 'Manage maintenance operations and packages', TRUE),
('maintenance', 'Maintenance Staff', 'Basic maintenance operations', TRUE),
('staff', 'Staff Member', 'Basic staff access', TRUE)
ON DUPLICATE KEY UPDATE name=name;

-- Assign permissions to roles
-- Super Admin gets everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'super_admin'
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Admin gets most things except system management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin' 
  AND p.name NOT IN ('manage_settings', 'delete_users')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Maintenance Director gets package and fuel management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'maintenance_director' 
  AND p.category IN ('packages', 'fuel', 'reports')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Maintenance staff gets basic operations
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'maintenance' 
  AND p.name IN ('view_packages', 'create_packages', 'pickup_packages', 'view_fuel', 'create_fuel')
ON DUPLICATE KEY UPDATE role_id=role_id;

-- Staff gets view access
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'staff' 
  AND p.name IN ('view_packages', 'view_fuel')
ON DUPLICATE KEY UPDATE role_id=role_id;
