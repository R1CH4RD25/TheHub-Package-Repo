-- Add header and footer color settings
-- Allows customization of header text/background and footer text/background colors

INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('header_bg_color', '#000000', 'color', 'Header/navbar background color'),
('header_text_color', '#FFFFFF', 'color', 'Header/navbar text color'),
('footer_bg_color', '#F3F4F6', 'color', 'Footer background color'),
('footer_text_color', '#6B7280', 'color', 'Footer text color')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
