-- Add authentication settings to site_settings table
-- These settings control OAuth providers and domain restrictions

-- Login Methods
INSERT INTO site_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES
('allow_local_users', '0', 'boolean', 'Allow username/password authentication (not recommended)', NULL),
('enable_google_login', '1', 'boolean', 'Show "Sign in with Google" button', NULL),
('enable_microsoft_login', '1', 'boolean', 'Show "Sign in with Microsoft" button', NULL)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Domain Restrictions
INSERT INTO site_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES
('require_domain_match', '1', 'boolean', 'Only allow users from specified email domains', NULL),
('allowed_domains', 'woodsonisd.net, newcastleisd.net', 'string', 'Comma-separated list of allowed email domains', NULL)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Auto-Role Assignment (Cloud Identity Groups)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES
('enable_google_groups', '1', 'boolean', 'Auto-assign roles based on Google Workspace group membership', NULL),
('enable_microsoft_groups', '0', 'boolean', 'Auto-assign roles based on Azure AD group membership', NULL)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Session timeout (moved from Management Access section for clarity)
INSERT INTO site_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES
('session_timeout_minutes', '120', 'number', 'Session timeout in minutes (applies to all users)', NULL)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
