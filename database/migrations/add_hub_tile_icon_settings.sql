-- Add hub tile icon settings to site_settings table
-- These allow customization of the hub section tile icons

INSERT INTO site_settings (setting_key, setting_value, description) VALUES
('hub_tile_icon_custom_enabled', '0', 'Enable custom icon for hub section tiles (1 = enabled, 0 = use emoji)'),
('hub_tile_icon_path', '/assets/images/branding/Branding_NoBG.png', 'Path to custom hub tile icon image')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);
