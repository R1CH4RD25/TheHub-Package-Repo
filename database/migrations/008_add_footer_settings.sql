-- Footer Settings Migration
-- Adds footer configuration settings (show version, show user, custom text)
SET NAMES utf8mb4;

-- ============================================================================
-- ADD FOOTER CONFIGURATION SETTINGS
-- ============================================================================

-- Insert footer_show_version setting (default true)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'footer_show_version',
    '1',
    'boolean',
    'Display version number in footer'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'boolean',
    description = 'Display version number in footer';

-- Insert footer_show_user setting (default true)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'footer_show_user',
    '1',
    'boolean',
    'Display logged-in username in footer'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'boolean',
    description = 'Display logged-in username in footer';

-- Insert footer_custom_text setting (default empty)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'footer_custom_text',
    '',
    'string',
    'Custom text to display in footer'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'string',
    description = 'Custom text to display in footer';
