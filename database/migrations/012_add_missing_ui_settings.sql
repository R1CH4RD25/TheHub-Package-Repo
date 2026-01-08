-- Add Missing UI Settings
-- These settings are referenced in the admin settings UI but were not in the database

INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
-- Command Center Settings
('cc_display_name', 'Command Center', 'string', 'Display name for Command Center module'),
('cc_description', 'Administrative command center for system management', 'string', 'Description for Command Center module'),
('cc_icon', 'fas fa-shield-alt', 'string', 'Icon class for Command Center module'),

-- Management Console Settings  
('enable_management_console', '1', 'boolean', 'Enable Management Console module'),
('log_management_actions', '1', 'boolean', 'Log all management console actions to audit log'),
('management_session_timeout', '30', 'number', 'Management console session timeout in minutes'),
('require_mfa_for_management', '0', 'boolean', 'Require MFA for management console access'),
('show_management_badge', '1', 'boolean', 'Show management badge on management console items'),

-- Sidebar Settings (additional)
('sidebar_width', '250', 'number', 'Sidebar width in pixels'),
('sidebar_collapsible', '1', 'boolean', 'Allow sidebar to be collapsed'),
('sidebar_default_collapsed', '0', 'boolean', 'Sidebar collapsed by default on page load'),
('show_menu_icons', '1', 'boolean', 'Show icons next to menu items'),
('menu_item_spacing', '0.5', 'number', 'Spacing between menu items in rem'),
('highlight_active_section', '1', 'boolean', 'Highlight currently active section in sidebar'),

-- Theme & User Settings
('allow_user_themes', '0', 'boolean', 'Allow users to select their own themes'),
('respect_system_theme', '1', 'boolean', 'Respect user OS dark/light mode preference'),

-- Debug Settings
('debug_mode', '0', 'boolean', 'Enable debug mode (shows detailed error messages)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
