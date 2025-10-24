-- Migration: Add support for local user authentication
-- This allows users to authenticate with username/password in addition to OAuth

-- Modify google_id to be nullable (since local users won't have a Google ID)
ALTER TABLE users MODIFY google_id VARCHAR(255) UNIQUE NULL;

-- Add password field for local authentication
ALTER TABLE users ADD COLUMN password VARCHAR(255) NULL AFTER email;

-- Add username field for local login (nullable to maintain backward compatibility)
ALTER TABLE users ADD COLUMN username VARCHAR(100) UNIQUE NULL AFTER email;

-- Add index on username for faster lookups
ALTER TABLE users ADD INDEX idx_username (username);
