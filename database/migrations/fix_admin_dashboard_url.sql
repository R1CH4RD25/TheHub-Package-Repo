-- Fix admin dashboard section URL
-- Issue: Clicking Admin Dashboard in hub was redirecting to /admin which then redirected to /admin/users
-- This caused a refresh loop for some users
-- Solution: Point directly to /admin/users

UPDATE sections 
SET base_url = '/admin/users' 
WHERE slug = 'admin-dashboard';
