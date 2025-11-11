-- Migration: Add file_hash column to section_packages
-- Date: 2025-11-11
-- Purpose: Store SHA256 hash of package files for integrity verification

ALTER TABLE section_packages 
ADD COLUMN file_hash VARCHAR(64) NULL 
AFTER file_size;

-- Add index for performance
CREATE INDEX idx_file_hash ON section_packages(file_hash);
