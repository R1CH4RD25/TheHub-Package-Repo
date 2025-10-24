-- Site Settings Table
-- Stores dynamic configuration for branding, colors, and site behavior

CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'string',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'The Hub', 'string', 'Site name displayed in browser tabs'),
('organization_name', 'Woodson ISD', 'string', 'Organization name in navbar'),
('navbar_subtitle', 'The Hub', 'string', 'Subtitle under organization name'),
('welcome_message', 'Central hub for student services and resources', 'string', 'Welcome message on hub page'),
('primary_color', '#C99700', 'color', 'Primary accent color (gold)'),
('navbar_color', '#000000', 'color', 'Navbar background color'),
('background_color', '#FFFFFF', 'color', 'Page background color'),
('accent_color', '#FFD700', 'color', 'Secondary accent color'),
('logo_path', '/assets/images/branding/Branding_NoBG.png', 'string', 'Path to branding logo'),
('logo_height', '90', 'number', 'Logo height in pixels (desktop)'),
('logo_height_mobile', '50', 'number', 'Logo height in pixels (mobile)'),
('favicon_path', '/assets/images/Cowboy_SM_favicon.png', 'string', 'Path to favicon'),
('logo_glow_enabled', '1', 'boolean', 'Enable logo glow effect'),
('session_timeout', '2', 'number', 'Session timeout in hours'),
('max_upload_size', '10', 'number', 'Maximum upload size in MB'),
('maintenance_mode', '0', 'boolean', 'Maintenance mode enabled')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Add button color settings
INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('button_primary_bg', '#C99700', 'color', 'Primary button background color'),
('button_primary_text', '#FFFFFF', 'color', 'Primary button text color'),
('button_secondary_bg', '#6B7280', 'color', 'Secondary button background color'),
('button_secondary_text', '#FFFFFF', 'color', 'Secondary button text color'),
('button_danger_bg', '#DC2626', 'color', 'Danger/delete button background color'),
('button_danger_text', '#FFFFFF', 'color', 'Danger button text color'),
('button_success_bg', '#059669', 'color', 'Success button background color'),
('button_success_text', '#FFFFFF', 'color', 'Success button text color')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
