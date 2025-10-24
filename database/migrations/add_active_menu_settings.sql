-- Add Active Menu Item Styling Settings
-- Allows customization of font size and weight for active/selected menu items

INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('active_menu_font_size', '16', 'number', 'Font size for active menu items in pixels (12-24px)'),
('active_menu_bold', '1', 'boolean', 'Make active menu items bold')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
