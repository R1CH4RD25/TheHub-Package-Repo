-- Set character encoding for emoji support
SET NAMES utf8mb4;

-- Create sections table for different platform modules
CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Internal name (slug-friendly)',
    slug VARCHAR(100) NOT NULL UNIQUE COMMENT 'URL-friendly identifier',
    display_name VARCHAR(150) NOT NULL COMMENT 'User-facing name',
    icon VARCHAR(10) DEFAULT '📁' COMMENT 'Emoji icon',
    description TEXT COMMENT 'Brief description of section purpose',
    base_url VARCHAR(255) NOT NULL COMMENT 'Path to section (e.g., /modules/travel/)',
    sort_order INT DEFAULT 0 COMMENT 'Display order (lower = first)',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether section is enabled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create section_role_access table for role-based permissions
CREATE TABLE IF NOT EXISTS section_role_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Which section',
    role ENUM('staff', 'maintenance', 'maintenance_director', 'manager', 'admin', 'super_admin') NOT NULL COMMENT 'Which role has access',
    granted_by INT NULL COMMENT 'Super admin who granted access',
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_section_role (section_id, role),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_section (section_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default sections (the ones visible in the screenshot)
INSERT INTO sections (name, slug, display_name, icon, description, base_url, sort_order, is_active) VALUES
('maintenance-fuel-travel', 'maintenance-fuel-travel', 'Maintenance Fuel & Travel', '🚗', 'Track vehicle fuel consumption, mileage, and trip purposes', '/fuel-entry.php', 1, TRUE),
('vehicle-maintenance', 'vehicle-maintenance', 'Vehicle Maintenance', '🔧', 'Vehicle maintenance records and service history', '/modules/vehicle-maintenance/', 2, TRUE),
('travel-reimbursement', 'travel-reimbursement', 'Travel Reimbursement', '💰', 'Submit and track travel reimbursement requests', '/modules/travel-reimbursement/', 3, TRUE),
('substitute-request', 'substitute-request', 'Substitute Request', '👥', 'Request substitute teachers and staff', '/modules/substitute-request/', 4, TRUE),
('travel-request', 'travel-request', 'Travel Request', '✈️', 'Submit travel requests for approval', '/modules/travel-request/', 5, TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Grant super_admin access to all sections by default
INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'super_admin', 1 FROM sections
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Grant admin access to all sections by default
INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'admin', 1 FROM sections
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;
