-- Migration: Add Role Badge Colors to Theme System
-- Date: 2025-10-22
-- Description: Adds 12 new settings for role badge background and text colors

-- Role badge color settings (6 roles × 2 colors each = 12 settings):
-- - role_staff_bg / role_staff_text
-- - role_maintenance_bg / role_maintenance_text
-- - role_maintenance_director_bg / role_maintenance_director_text
-- - role_manager_bg / role_manager_text
-- - role_admin_bg / role_admin_text
-- - role_super_admin_bg / role_super_admin_text

-- Default color palette based on current admin.css:
-- Staff: Light Indigo (#e0e7ff bg, #3730a3 text)
-- Maintenance: Light Gold (#fef3c7 bg, #92400e text)  
-- Maintenance Director: Light Orange (#fed7aa bg, #9a3412 text)
-- Manager: Light Purple (#ddd6fe bg, #5b21b6 text)
-- Admin: Light Pink (#fce7f3 bg, #9f1239 text)
-- Super Admin: Light Red (#fee2e2 bg, #991b1b text)

-- No schema changes needed - settings stored in JSON
-- This migration will be applied via PHP script that updates each theme's settings JSON
