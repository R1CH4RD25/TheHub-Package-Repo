-- Multi-Section Architecture for Woodson ISD Maintenance Platform
-- This schema supports multiple independent sections/modules with per-user access control

-- Sections Table: Defines available platform sections
CREATE TABLE IF NOT EXISTS sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10) DEFAULT '📁',
    base_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Section Access Table: Per-user, per-section role assignments
CREATE TABLE IF NOT EXISTS section_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    section_id INT NOT NULL,
    role ENUM('staff', 'maintenance', 'maintenance_director', 'manager', 'admin', 'super_admin') NOT NULL DEFAULT 'staff',
    granted_by INT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_section (user_id, section_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_section (user_id, section_id),
    INDEX idx_section (section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Convenient view for querying user section access
CREATE OR REPLACE VIEW section_access_view AS
SELECT 
    sa.id,
    sa.user_id,
    u.email as user_email,
    u.name as user_name,
    sa.section_id,
    s.name as section_name,
    s.slug as section_slug,
    s.display_name as section_display_name,
    s.icon as section_icon,
    s.base_url as section_base_url,
    s.is_active as section_is_active,
    sa.role,
    sa.granted_by,
    gb.name as granted_by_name,
    sa.granted_at,
    sa.updated_at
FROM section_access sa
JOIN users u ON sa.user_id = u.id
JOIN sections s ON sa.section_id = s.id
LEFT JOIN users gb ON sa.granted_by = gb.id;

-- Insert initial sections
INSERT INTO sections (name, slug, display_name, description, icon, base_url, sort_order) VALUES
('fuel-travel', 'fuel-travel', 'Maintenance Fuel & Travel', 'Track fuel consumption, mileage, and travel purposes for district vehicles', '🚗', '/modules/fuel-travel/', 1),
('vehicle-maintenance', 'vehicle-maintenance', 'Vehicle Maintenance', 'Schedule and track vehicle maintenance, repairs, and inspections', '🔧', '/modules/vehicle-maintenance/', 2),
('travel-reimbursement', 'travel-reimbursement', 'Travel Reimbursement', 'Submit and manage travel reimbursement requests', '✈️', '/modules/travel-reimbursement/', 3),
('substitute-request', 'substitute-request', 'Substitute Request', 'Request and manage substitute staffing', '👤', '/modules/substitute-request/', 4)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Update users table to add new roles (if not exists)
-- Note: This will be handled by migration script to preserve existing data
