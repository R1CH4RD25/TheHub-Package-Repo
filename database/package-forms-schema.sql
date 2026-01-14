-- =============================================================================
-- Package Dynamic Forms Schema
-- =============================================================================
-- Enables packages to define custom forms with:
-- - Visual form builder
-- - Conditional alerts
-- - Flexible data storage
-- - Context-aware rendering (hub/management/admin)
-- =============================================================================

-- Form Definitions
-- Each package can have multiple forms for different contexts
CREATE TABLE IF NOT EXISTS package_forms (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    package_id INT NOT NULL,                    -- References sections.id
    name VARCHAR(100) NOT NULL,                 -- "Maintenance Request", "Vehicle Checkout"
    description TEXT,                           -- Help text shown to users
    context ENUM('hub', 'management', 'admin') NOT NULL DEFAULT 'hub',
    icon VARCHAR(50) DEFAULT 'bi-file-text',    -- Bootstrap icon
    success_message TEXT,                       -- Custom message after submission
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (package_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_package_forms (package_id, context),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Fields (The Builder Part)
-- Defines structure of each form dynamically
CREATE TABLE IF NOT EXISTS package_form_fields (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(50) NOT NULL,             -- Internal key: "priority", "building_id"
    label VARCHAR(200) NOT NULL,                -- Display label: "Priority Level"
    field_type ENUM('text', 'textarea', 'number', 'date', 'datetime', 'time', 
                    'email', 'tel', 'url', 'dropdown', 'radio', 'checkbox', 
                    'multi_select', 'file', 'user_select', 'vehicle_select') NOT NULL,
    placeholder VARCHAR(200),                   -- Input placeholder text
    help_text TEXT,                             -- Additional help/instructions
    options_json JSON,                          -- For dropdown/radio/checkbox: [{value, label, other_option: bool}]
    validation_rules JSON,                      -- {required, min, max, pattern, etc.}
    default_value VARCHAR(255),                 -- Default field value
    display_order INT DEFAULT 0,
    is_required TINYINT(1) DEFAULT 0,
    is_visible TINYINT(1) DEFAULT 1,
    conditional_logic JSON,                     -- Show field if: {field: "x", operator: "equals", value: "y"}
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES package_forms(id) ON DELETE CASCADE,
    INDEX idx_form_fields (form_id, display_order),
    UNIQUE KEY unique_field_key (form_id, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Submissions (Flexible JSON Storage)
-- Stores actual form data submitted by users
CREATE TABLE IF NOT EXISTS package_form_submissions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    submitted_by INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_json JSON NOT NULL,                    -- Actual form data: {field_key: value}
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to INT,                            -- For workflow/assignment
    notes TEXT,                                 -- Admin notes
    is_archived TINYINT(1) DEFAULT 0,
    archived_at TIMESTAMP NULL,
    ip_address VARCHAR(45),                     -- Track submission IP
    user_agent TEXT,                            -- Track browser/device
    FOREIGN KEY (form_id) REFERENCES package_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_submissions (form_id, submitted_at),
    INDEX idx_user_submissions (submitted_by, submitted_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert Rules (If/Then Notification Logic)
-- Defines who gets notified when certain conditions are met
CREATE TABLE IF NOT EXISTS package_form_alerts (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    alert_name VARCHAR(100) NOT NULL,           -- "Emergency Maintenance Alert"
    trigger_type ENUM('always', 'conditional') DEFAULT 'conditional',
    recipient_type ENUM('user', 'role', 'org_role', 'email') NOT NULL,
    recipient_id INT,                           -- User ID, role ID, or org_role ID
    recipient_email VARCHAR(255),               -- For direct email alerts
    notification_method ENUM('email', 'sms', 'both') DEFAULT 'email',
    email_subject VARCHAR(200),
    email_template TEXT,                        -- Can use placeholders: {field_key}
    sms_template VARCHAR(160),                  -- SMS message template
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES package_forms(id) ON DELETE CASCADE,
    INDEX idx_form_alerts (form_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert Conditions (The "If" Part of If/Then)
-- Multiple conditions can be AND/OR together
CREATE TABLE IF NOT EXISTS package_form_alert_conditions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    alert_id INT UNSIGNED NOT NULL,
    field_key VARCHAR(50) NOT NULL,             -- Which field to check
    operator ENUM('equals', 'not_equals', 'contains', 'not_contains', 
                  'greater_than', 'less_than', 'is_empty', 'is_not_empty') NOT NULL,
    value VARCHAR(255),                         -- Value to compare against
    logic_type ENUM('AND', 'OR') DEFAULT 'AND', -- How to combine with next condition
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES package_form_alerts(id) ON DELETE CASCADE,
    INDEX idx_alert_conditions (alert_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alert Log (Track All Notifications Sent)
-- Audit trail for all alerts sent
CREATE TABLE IF NOT EXISTS package_form_alert_log (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    submission_id INT UNSIGNED NOT NULL,
    alert_id INT UNSIGNED NOT NULL,
    recipient_type VARCHAR(20),
    recipient_id INT,
    recipient_email VARCHAR(255),
    notification_method VARCHAR(10),
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    retry_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES package_form_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (alert_id) REFERENCES package_form_alerts(id) ON DELETE CASCADE,
    INDEX idx_submission_alerts (submission_id),
    INDEX idx_alert_status (alert_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Access Control (Who Can Submit Forms)
-- Optionally restrict form access by role
CREATE TABLE IF NOT EXISTS package_form_access (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    form_id INT UNSIGNED NOT NULL,
    org_role_id INT UNSIGNED,                   -- NULL = all users can access
    can_submit TINYINT(1) DEFAULT 1,
    can_view_submissions TINYINT(1) DEFAULT 0,
    can_edit_submissions TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES package_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (org_role_id) REFERENCES org_roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_form_role (form_id, org_role_id),
    INDEX idx_form_access (form_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
