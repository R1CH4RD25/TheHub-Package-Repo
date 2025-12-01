-- Hide Admin Dashboard card from hub
-- Reason: Admin is accessed via header menu, not hub cards
-- Date: 2025-12-01

UPDATE sections 
SET is_active = 0 
WHERE slug = 'admin-dashboard';

-- Verify
SELECT id, slug, name, is_active 
FROM sections 
WHERE slug = 'admin-dashboard';
