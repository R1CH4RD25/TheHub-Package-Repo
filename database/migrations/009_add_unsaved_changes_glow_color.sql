-- Add unsaved changes glow color setting
-- Allows customization of the glow animation on the Save button

INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('unsaved_changes_glow_color', '#C99700', 'color', 'Glow color for unsaved changes indicator')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
