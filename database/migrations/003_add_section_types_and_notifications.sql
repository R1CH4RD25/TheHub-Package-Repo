-- Set character encoding
SET NAMES utf8mb4;

-- ============================================================================
-- SECTION TYPES AND WORKFLOW CONFIGURATION
-- ============================================================================

-- Add section_type and notification settings to sections table
ALTER TABLE sections 
ADD COLUMN section_type ENUM(
    'recording',        -- Data entry + exports (Fuel, Maintenance)
    'request_form',     -- Workflow with approvals (Reimbursement, Bullying Report)
    'scheduled_report', -- Automated emails (Due dates, weekly summaries)
    'hub',             -- Dashboard/portal (Staff Hub, Student Hub)
    'other'            -- Custom functionality
) NOT NULL DEFAULT 'recording' COMMENT 'Type of section functionality' AFTER description,

ADD COLUMN requires_approval BOOLEAN DEFAULT FALSE COMMENT 'Does this section require approval workflow?' AFTER section_type,
ADD COLUMN send_notifications BOOLEAN DEFAULT FALSE COMMENT 'Send email notifications for this section?' AFTER requires_approval,
ADD COLUMN notification_config JSON NULL COMMENT 'JSON config for who gets notified when' AFTER send_notifications;

-- ============================================================================
-- NOTIFICATION ROLES TABLE
-- ============================================================================

-- Create table to define which roles get notified for which section actions
CREATE TABLE IF NOT EXISTS section_notification_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Which section',
    action_type ENUM(
        'submission',     -- When form is submitted
        'approval',       -- When awaiting approval
        'approved',       -- When request is approved
        'rejected',       -- When request is rejected
        'due_soon',       -- When something is due soon
        'overdue',        -- When something is overdue
        'scheduled'       -- Scheduled report email
    ) NOT NULL COMMENT 'What action triggers notification',
    notify_role ENUM(
        'staff', 'student', 'maintenance_staff', 'custodial', 'cafeteria',
        'custodial_manager', 'maintenance_director', 'substitute_manager',
        'counselor', 'principal', 'admin', 'super_admin'
    ) NOT NULL COMMENT 'Which role receives notification',
    email_template VARCHAR(100) NULL COMMENT 'Template file to use for email',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Is this notification rule active?',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_section_action_role (section_id, action_type, notify_role),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_section_action (section_id, action_type),
    INDEX idx_role (notify_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Defines which roles get notified for section actions';

-- ============================================================================
-- UPDATE EXISTING SECTIONS WITH TYPES
-- ============================================================================

-- Update existing sections with appropriate types
UPDATE sections 
SET 
    section_type = 'recording',
    requires_approval = FALSE,
    send_notifications = FALSE
WHERE slug IN ('maintenance-fuel-travel', 'vehicle-maintenance');

UPDATE sections 
SET 
    section_type = 'request_form',
    requires_approval = TRUE,
    send_notifications = TRUE,
    notification_config = JSON_OBJECT(
        'notify_on_submit', true,
        'notify_on_approval', true,
        'notify_submitter', true
    )
WHERE slug IN ('travel-reimbursement', 'substitute-request', 'travel-request');

-- ============================================================================
-- RENAME TRAVEL REIMBURSEMENT TO REIMBURSEMENT REQUEST
-- ============================================================================

UPDATE sections 
SET 
    name = 'reimbursement-request',
    slug = 'reimbursement-request',
    display_name = 'Reimbursement Request',
    description = 'Submit reimbursement requests for travel, supplies, and other expenses'
WHERE slug = 'travel-reimbursement';

-- ============================================================================
-- ADD NEW SECTIONS FOR FUTURE FEATURES
-- ============================================================================

-- Bullying Reports (Request Form - Student Access)
INSERT INTO sections (name, slug, display_name, icon, description, base_url, section_type, requires_approval, send_notifications, sort_order, is_active) VALUES
('bullying-report', 'bullying-report', 'Bullying Report', '🛡️', 'Report bullying incidents (confidential and secure)', '/modules/bullying-report/', 'request_form', TRUE, TRUE, 10, TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Counselor Request (Request Form - Student Access)
INSERT INTO sections (name, slug, display_name, icon, description, base_url, section_type, requires_approval, send_notifications, sort_order, is_active) VALUES
('counselor-request', 'counselor-request', 'Counselor Request', '🎓', 'Request to meet with school counselor', '/modules/counselor-request/', 'request_form', TRUE, TRUE, 11, TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Student Hub (Hub - Student-specific dashboard)
INSERT INTO sections (name, slug, display_name, icon, description, base_url, section_type, requires_approval, send_notifications, sort_order, is_active) VALUES
('student-hub', 'student-hub', 'Student Hub', '📚', 'Central hub for student services and resources', '/student-hub.php', 'hub', FALSE, FALSE, 20, TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Staff Hub (Hub - Staff-specific dashboard)
INSERT INTO sections (name, slug, display_name, icon, description, base_url, section_type, requires_approval, send_notifications, sort_order, is_active) VALUES
('staff-hub', 'staff-hub', 'Staff Hub', '💼', 'Central hub for staff resources and tools', '/staff-hub.php', 'hub', FALSE, FALSE, 21, TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- DEFAULT NOTIFICATION RULES
-- ============================================================================

-- Reimbursement Request notifications
INSERT INTO section_notification_roles (section_id, action_type, notify_role, email_template) 
SELECT id, 'submission', 'admin', 'reimbursement_submitted.html' FROM sections WHERE slug = 'reimbursement-request'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO section_notification_roles (section_id, action_type, notify_role, email_template) 
SELECT id, 'submission', 'principal', 'reimbursement_submitted.html' FROM sections WHERE slug = 'reimbursement-request'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Bullying Report notifications (Principal and Counselor only)
INSERT INTO section_notification_roles (section_id, action_type, notify_role, email_template) 
SELECT id, 'submission', 'principal', 'bullying_report_submitted.html' FROM sections WHERE slug = 'bullying-report'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO section_notification_roles (section_id, action_type, notify_role, email_template) 
SELECT id, 'submission', 'counselor', 'bullying_report_submitted.html' FROM sections WHERE slug = 'bullying-report'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Counselor Request notifications
INSERT INTO section_notification_roles (section_id, action_type, notify_role, email_template) 
SELECT id, 'submission', 'counselor', 'counselor_request_submitted.html' FROM sections WHERE slug = 'counselor-request'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Substitute Request notifications
INSERT INTO section_notification_roles (section_id, action_type, notify_role, email_template) 
SELECT id, 'submission', 'substitute_manager', 'substitute_request_submitted.html' FROM sections WHERE slug = 'substitute-request'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- GRANT ACCESS TO NEW SECTIONS
-- ============================================================================

-- Super admin and admin get access to everything
INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'super_admin', 1 FROM sections WHERE slug IN ('bullying-report', 'counselor-request', 'student-hub', 'staff-hub')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'admin', 1 FROM sections WHERE slug IN ('bullying-report', 'counselor-request', 'student-hub', 'staff-hub')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Principal has access to bullying reports
INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'principal', 1 FROM sections WHERE slug = 'bullying-report'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Counselor has access to counselor requests and bullying reports
INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'counselor', 1 FROM sections WHERE slug IN ('counselor-request', 'bullying-report')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Students have access to student hub, bullying reports, and counselor requests
INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'student', 1 FROM sections WHERE slug IN ('student-hub', 'bullying-report', 'counselor-request')
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- Staff have access to staff hub
INSERT INTO section_role_access (section_id, role, granted_by)
SELECT id, 'staff', 1 FROM sections WHERE slug = 'staff-hub'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- COMMENTS FOR FUTURE DEVELOPMENT
-- ============================================================================

/*
FUTURE SCHEDULED REPORTS SECTION IDEAS:

1. Maintenance Due Dates Report
   - Weekly email to maintenance_director
   - Lists vehicles with upcoming oil changes, inspections
   - Configure frequency: daily, weekly, monthly

2. Custodial Cleaning Schedule
   - Email custodial_manager about AC vent cleaning due dates
   - Configure which tasks trigger notifications
   - Set lead time (e.g., 7 days before due)

3. Fuel Usage Summary
   - Weekly/monthly summary to admin/principal
   - Aggregated fuel consumption by vehicle
   - Cost analysis and trends

IMPLEMENTATION:
- Add rows to sections table with section_type = 'scheduled_report'
- Add rows to section_notification_roles with action_type = 'scheduled'
- Create cron job that reads these configs and sends emails
- Store schedule in notification_config JSON (e.g., "daily_at": "08:00")
*/
