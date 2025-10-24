-- Modular Platform Schema for Woodson ISD
-- This allows the platform to grow beyond just vehicle maintenance

-- Modules/Apps table - Each major section of the platform
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT '📦',
    slug VARCHAR(100) NOT NULL UNIQUE,
    base_url VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User module access - Maps users to modules with specific roles per module
CREATE TABLE IF NOT EXISTS user_module_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_id INT NOT NULL,
    role ENUM('staff', 'manager', 'admin', 'super_admin') DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    granted_by INT,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_module (user_id, module_id),
    INDEX idx_user_id (user_id),
    INDEX idx_module_id (module_id),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial modules
INSERT INTO modules (name, display_name, description, icon, slug, base_url, sort_order) VALUES
('vehicles', 'Vehicle Maintenance', 'Track fuel consumption, mileage, and maintenance for district vehicles', '🚗', 'vehicles', '/vehicles', 1),
('fuel_reimbursement', 'Fuel Reimbursement', 'Submit and process fuel reimbursement requests for personal vehicle use', '⛽', 'fuel-reimbursement', '/fuel-reimbursement', 2)
ON DUPLICATE KEY UPDATE 
    display_name=VALUES(display_name),
    description=VALUES(description),
    icon=VALUES(icon),
    base_url=VALUES(base_url),
    sort_order=VALUES(sort_order);

-- View for user module access with details
CREATE OR REPLACE VIEW user_module_access_view AS
SELECT 
    uma.id,
    uma.user_id,
    u.name AS user_name,
    u.email AS user_email,
    uma.module_id,
    m.name AS module_name,
    m.display_name AS module_display_name,
    m.icon AS module_icon,
    m.slug AS module_slug,
    m.base_url AS module_base_url,
    uma.role AS module_role,
    uma.is_active,
    uma.granted_at,
    grantor.name AS granted_by_name
FROM user_module_access uma
JOIN users u ON uma.user_id = u.id
JOIN modules m ON uma.module_id = m.id
LEFT JOIN users grantor ON uma.granted_by = grantor.id
WHERE uma.is_active = TRUE AND m.is_active = TRUE;
