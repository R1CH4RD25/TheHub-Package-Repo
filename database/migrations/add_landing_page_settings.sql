-- Add Landing Page Title and Icon Settings
-- These control the main hub header display

INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('landing_page_title', 'The Hub', 'string', 'Title displayed at top of hub landing page'),
('landing_page_icon', '🏠', 'string', 'Icon/emoji displayed next to landing page title'),
('landing_page_show_icon', '1', 'boolean', 'Show icon next to landing page title (1 = show, 0 = hide)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
