-- Sidebar Settings Migration
-- Adds sidebar color and styling settings
SET NAMES utf8mb4;

-- ============================================================================
-- ADD SIDEBAR COLOR SETTINGS
-- ============================================================================

-- Insert sidebar_bg setting (default white)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'sidebar_bg',
    '#FFFFFF',
    'color',
    'Sidebar background color'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'color',
    description = 'Sidebar background color';

-- Insert sidebar_text_color setting (default gray)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'sidebar_text_color',
    '#374151',
    'color',
    'Sidebar text color'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'color',
    description = 'Sidebar text color';

-- Insert sidebar_active_highlight setting (default yellow)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'sidebar_active_highlight',
    '#FEF3C7',
    'color',
    'Sidebar active item background color'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'color',
    description = 'Sidebar active item background color';

-- Insert sidebar_active_text_color setting (default gold)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'sidebar_active_text_color',
    '#C99700',
    'color',
    'Sidebar active item text color'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'color',
    description = 'Sidebar active item text color';

-- Insert sidebar_hover_bg setting (default light gray)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'sidebar_hover_bg',
    '#F9FAFB',
    'color',
    'Sidebar hover background color'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'color',
    description = 'Sidebar hover background color';
