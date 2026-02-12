-- Sprint 0 P1: Add audit_log columns for package system enforcement pipelines
-- Adds correlation_id, execution_time_ms, error tracking columns, and performance indices
-- Safe to run multiple times (IF NOT EXISTS / ADD COLUMN checks)

-- Add correlation_id for request tracing (UUID v4)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'correlation_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE audit_log ADD COLUMN correlation_id VARCHAR(36) NULL AFTER user_agent',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add execution_time_ms for performance tracking
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'execution_time_ms');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE audit_log ADD COLUMN execution_time_ms DECIMAL(10,2) NULL AFTER correlation_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add error_message for sanitized error storage (truncated to 500 chars)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'error_message');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE audit_log ADD COLUMN error_message VARCHAR(500) NULL AFTER execution_time_ms',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add error_hash for deduplication (SHA-256)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'error_hash');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE audit_log ADD COLUMN error_hash CHAR(64) NULL AFTER error_message',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add error_class for exception type tracking
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'error_class');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE audit_log ADD COLUMN error_class VARCHAR(255) NULL AFTER error_hash',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add error_top_frames for sanitized stack trace (top 5 frames only)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'error_top_frames');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE audit_log ADD COLUMN error_top_frames TEXT NULL AFTER error_class',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add package_id for package-scoped audit events
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_log' AND COLUMN_NAME = 'package_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE audit_log ADD COLUMN package_id VARCHAR(100) NULL AFTER error_top_frames',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Performance indices for common queries
CREATE INDEX IF NOT EXISTS idx_correlation_id ON audit_log(correlation_id);
CREATE INDEX IF NOT EXISTS idx_error_hash ON audit_log(error_hash);
CREATE INDEX IF NOT EXISTS idx_event_created ON audit_log(action, created_at);
CREATE INDEX IF NOT EXISTS idx_package_id ON audit_log(package_id);
