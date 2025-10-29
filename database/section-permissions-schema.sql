-- Section Permissions & Configuration Schema
-- Adds granular permissions, notifications, and guidelines to sections

-- Section Categories (hardcoded types with specific requirements)
CREATE TABLE IF NOT EXISTS section_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    requires_forms BOOLEAN DEFAULT FALSE,
    requires_submissions BOOLEAN DEFAULT FALSE,
    requires_notifications BOOLEAN DEFAULT FALSE,
    requires_guidelines BOOLEAN DEFAULT FALSE,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO section_categories (name, display_name, description, requires_forms, requires_submissions, requires_notifications, requires_guidelines, icon) VALUES
('reporting', 'Reporting & Forms', 'Sections with forms that users submit and staff review', TRUE, TRUE, TRUE, TRUE, 'clipboard'),
('analytics', 'Analytics & Dashboards', 'Read-only data visualization and reporting', FALSE, FALSE, FALSE, TRUE, 'chart-bar'),
('tools', 'Tools & Utilities', 'Interactive tools and user-specific features', FALSE, TRUE, FALSE, TRUE, 'tools'),
('resources', 'Resources & Documents', 'Content management and file storage', FALSE, FALSE, FALSE, TRUE, 'book'),
('administration', 'Administration', 'System configuration and management', FALSE, FALSE, FALSE, FALSE, 'cog')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name);

-- Add category to sections table
ALTER TABLE sections 
ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER base_url,
ADD CONSTRAINT fk_section_category FOREIGN KEY (category_id) REFERENCES section_categories(id) ON DELETE SET NULL;

-- Section Guidelines (instructions shown to users)
CREATE TABLE IF NOT EXISTS section_guidelines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    guideline_type ENUM('submission', 'review', 'general') DEFAULT 'general',
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    INDEX idx_section_active (section_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Section Submission Permissions (who can submit forms/reports)
CREATE TABLE IF NOT EXISTS section_submission_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    can_submit BOOLEAN DEFAULT TRUE,
    allow_anonymous BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    UNIQUE KEY unique_section_role (section_id, role_name),
    INDEX idx_section_submit (section_id, can_submit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Section Review Permissions (who can view/manage submissions)
CREATE TABLE IF NOT EXISTS section_review_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    can_view BOOLEAN DEFAULT TRUE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_add_notes BOOLEAN DEFAULT TRUE,
    can_change_status BOOLEAN DEFAULT TRUE,
    can_assign BOOLEAN DEFAULT FALSE,
    can_export BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    UNIQUE KEY unique_section_role_review (section_id, role_name),
    INDEX idx_section_review (section_id, role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Section Notification Rules (who gets alerted and how)
CREATE TABLE IF NOT EXISTS section_notification_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    role_name VARCHAR(50) NOT NULL,
    notify_on_submission BOOLEAN DEFAULT TRUE,
    notify_on_status_change BOOLEAN DEFAULT FALSE,
    notify_on_assignment BOOLEAN DEFAULT TRUE,
    notify_on_comment BOOLEAN DEFAULT FALSE,
    email_enabled BOOLEAN DEFAULT TRUE,
    sms_enabled BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    UNIQUE KEY unique_section_role_notify (section_id, role_name),
    INDEX idx_section_notify_active (section_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Section Configuration (additional settings per section)
CREATE TABLE IF NOT EXISTS section_configuration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL UNIQUE,
    enable_status_tracking BOOLEAN DEFAULT TRUE,
    enable_priority_levels BOOLEAN DEFAULT TRUE,
    enable_attachments BOOLEAN DEFAULT FALSE,
    enable_follow_up_notes BOOLEAN DEFAULT TRUE,
    enable_assignment BOOLEAN DEFAULT TRUE,
    max_attachments INT DEFAULT 5,
    allowed_file_types TEXT, -- JSON array of extensions
    form_config TEXT, -- JSON for custom form fields
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example: Set up Bullying Reports section
-- Get section ID
SET @bullying_section_id = (SELECT id FROM sections WHERE slug = 'bullying-report' LIMIT 1);

-- Set category to 'reporting'
UPDATE sections 
SET category_id = (SELECT id FROM section_categories WHERE name = 'reporting')
WHERE slug = 'bullying-report';

-- Submission permissions (who can submit reports)
INSERT INTO section_submission_permissions (section_id, role_name, can_submit, allow_anonymous) VALUES
(@bullying_section_id, 'student', TRUE, TRUE),
(@bullying_section_id, 'staff', TRUE, FALSE),
(@bullying_section_id, 'parent', TRUE, TRUE),
(@bullying_section_id, 'teacher', TRUE, FALSE)
ON DUPLICATE KEY UPDATE can_submit=VALUES(can_submit), allow_anonymous=VALUES(allow_anonymous);

-- Review permissions (who can view/manage reports)
INSERT INTO section_review_permissions (section_id, role_name, can_view, can_edit, can_delete, can_add_notes, can_change_status, can_assign, can_export) VALUES
(@bullying_section_id, 'counselor', TRUE, TRUE, FALSE, TRUE, TRUE, TRUE, TRUE),
(@bullying_section_id, 'principal', TRUE, TRUE, FALSE, TRUE, TRUE, TRUE, TRUE),
(@bullying_section_id, 'admin', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE),
(@bullying_section_id, 'super_admin', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE)
ON DUPLICATE KEY UPDATE can_view=VALUES(can_view), can_edit=VALUES(can_edit);

-- Notification rules (who gets notified)
INSERT INTO section_notification_rules (section_id, role_name, notify_on_submission, notify_on_status_change, notify_on_assignment, email_enabled, sms_enabled) VALUES
(@bullying_section_id, 'counselor', TRUE, TRUE, TRUE, TRUE, FALSE),
(@bullying_section_id, 'principal', TRUE, TRUE, FALSE, TRUE, FALSE),
(@bullying_section_id, 'admin', TRUE, FALSE, FALSE, TRUE, FALSE)
ON DUPLICATE KEY UPDATE notify_on_submission=VALUES(notify_on_submission), email_enabled=VALUES(email_enabled);

-- Section configuration
INSERT INTO section_configuration (section_id, enable_status_tracking, enable_priority_levels, enable_attachments, enable_follow_up_notes, enable_assignment, max_attachments, allowed_file_types) VALUES
(@bullying_section_id, TRUE, TRUE, TRUE, TRUE, TRUE, 5, '["jpg","jpeg","png","pdf","doc","docx"]')
ON DUPLICATE KEY UPDATE enable_attachments=VALUES(enable_attachments), max_attachments=VALUES(max_attachments);

-- Guidelines
INSERT INTO section_guidelines (section_id, guideline_type, title, content, display_order) VALUES
(@bullying_section_id, 'submission', 'How to Report', 'Please provide as much detail as possible about the incident. Include dates, times, locations, and any witnesses. Your report is confidential and will only be shared with school counselors and administrators.', 1),
(@bullying_section_id, 'submission', 'Anonymous Reporting', 'You may report anonymously, but please note that we may not be able to follow up with you for additional information. If you want to be contacted, please provide your contact information.', 2),
(@bullying_section_id, 'review', 'Response Protocol', 'All bullying reports must be reviewed within 24 hours. High priority reports require immediate action. Document all follow-up actions in the notes section.', 1)
ON DUPLICATE KEY UPDATE content=VALUES(content);
