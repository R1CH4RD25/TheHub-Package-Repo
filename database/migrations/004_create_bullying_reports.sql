-- Bullying Reports Module Tables
SET NAMES utf8mb4;

-- ============================================================================
-- BULLYING REPORTS TABLE
-- ============================================================================

CREATE TABLE IF NOT EXISTS bullying_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Reporter Information (optional - can be anonymous)
    reporter_user_id INT NULL COMMENT 'If logged-in user submits, link to user',
    reporter_name VARCHAR(255) NULL COMMENT 'Name of reporter (optional)',
    reporter_email VARCHAR(255) NULL COMMENT 'Contact email (optional)',
    reporter_phone VARCHAR(20) NULL COMMENT 'Contact phone (optional)',
    is_anonymous BOOLEAN DEFAULT FALSE COMMENT 'Reporter chose to remain anonymous',
    reporter_relationship ENUM(
        'student',
        'parent',
        'teacher',
        'staff',
        'witness',
        'other'
    ) NULL COMMENT 'Reporter relationship to incident',
    
    -- Incident Details
    incident_date DATE NOT NULL COMMENT 'When did incident occur',
    incident_time TIME NULL COMMENT 'Approximate time of incident',
    incident_location VARCHAR(255) NOT NULL COMMENT 'Where did incident occur',
    
    -- Victim Information
    victim_name VARCHAR(255) NULL COMMENT 'Name of victim (if known)',
    victim_grade VARCHAR(20) NULL COMMENT 'Grade of victim',
    
    -- Alleged Bully Information
    bully_name VARCHAR(255) NULL COMMENT 'Name of alleged bully (if known)',
    bully_grade VARCHAR(20) NULL COMMENT 'Grade of alleged bully',
    
    -- Incident Description
    incident_description TEXT NOT NULL COMMENT 'Detailed description of what happened',
    incident_type ENUM(
        'physical',
        'verbal',
        'cyber',
        'social',
        'other'
    ) NOT NULL COMMENT 'Type of bullying',
    witnesses TEXT NULL COMMENT 'Names of witnesses (if any)',
    previous_incidents BOOLEAN DEFAULT FALSE COMMENT 'Has this happened before?',
    previous_incidents_details TEXT NULL COMMENT 'Details of previous incidents',
    
    -- Report Status & Follow-up
    status ENUM(
        'submitted',
        'under_review',
        'investigating',
        'resolved',
        'closed',
        'no_action_needed'
    ) DEFAULT 'submitted' COMMENT 'Current status of report',
    
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' COMMENT 'Priority level',
    
    assigned_to INT NULL COMMENT 'Counselor/Admin assigned to handle',
    
    -- Actions Taken
    action_taken TEXT NULL COMMENT 'What actions were taken',
    resolution_notes TEXT NULL COMMENT 'Final resolution notes',
    follow_up_required BOOLEAN DEFAULT TRUE COMMENT 'Does this need follow-up?',
    follow_up_date DATE NULL COMMENT 'When to follow up',
    
    -- Metadata
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL COMMENT 'When was report resolved',
    resolved_by INT NULL COMMENT 'Who resolved the report',
    
    -- Indexes
    FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_incident_date (incident_date),
    INDEX idx_assigned (assigned_to),
    INDEX idx_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bullying incident reports - CONFIDENTIAL';

-- ============================================================================
-- BULLYING REPORT ATTACHMENTS (optional - for future)
-- ============================================================================

CREATE TABLE IF NOT EXISTS bullying_report_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NULL COMMENT 'MIME type',
    file_size INT NULL COMMENT 'Size in bytes',
    uploaded_by INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES bullying_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_report (report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='File attachments for bullying reports (screenshots, evidence)';

-- ============================================================================
-- BULLYING REPORT COMMENTS/NOTES
-- ============================================================================

CREATE TABLE IF NOT EXISTS bullying_report_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    author_id INT NOT NULL COMMENT 'Counselor/admin who wrote note',
    note_type ENUM('internal', 'action', 'follow_up', 'resolution') DEFAULT 'internal',
    note_text TEXT NOT NULL,
    is_confidential BOOLEAN DEFAULT TRUE COMMENT 'Only visible to admin/counselor',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES bullying_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_report (report_id),
    INDEX idx_author (author_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Internal notes and actions on bullying reports';

-- ============================================================================
-- GRANT INITIAL ACCESS
-- ============================================================================

-- Note: Access control handled by application code
-- Only counselor, principal, admin, super_admin can view reports table
-- Anyone can submit a report via the form
