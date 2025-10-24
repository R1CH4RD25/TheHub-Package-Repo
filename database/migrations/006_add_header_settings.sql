-- Header Settings Migration
-- Adds header_height, header_match_logo_height, and footer_height settings
SET NAMES utf8mb4;

-- ============================================================================
-- ADD HEADER AND FOOTER SETTINGS
-- ============================================================================

-- Insert header_height setting (default 80px)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'header_height',
    '80',
    'integer',
    'Header height in pixels (60-150)'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'integer',
    description = 'Header height in pixels (60-150)';

-- Insert header_match_logo_height setting (default false)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'header_match_logo_height',
    '0',
    'boolean',
    'Auto-adjust header height to match logo height + padding'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'boolean',
    description = 'Auto-adjust header height to match logo height + padding';

-- Insert footer_height setting (default 40px)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description)
VALUES (
    'footer_height',
    '40',
    'integer',
    'Footer height in pixels (30-80)'
)
ON DUPLICATE KEY UPDATE 
    setting_type = 'integer',
    description = 'Footer height in pixels (30-80)';
