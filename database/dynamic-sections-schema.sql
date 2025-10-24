-- ============================================================================
-- DYNAMIC SECTIONS SCHEMA
-- Enables users to create custom sections without coding
-- Version: 1.0.0
-- Created: October 22, 2025
-- ============================================================================

-- ============================================================================
-- SECTION PACKAGES (Import/Export)
-- ============================================================================

-- Store section packages/templates for import/export
CREATE TABLE IF NOT EXISTS section_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'Unique identifier (e.g., woodson.fuel-tracking)',
    name VARCHAR(100) NOT NULL COMMENT 'Internal name (slug format)',
    display_name VARCHAR(255) NOT NULL COMMENT 'User-friendly display name',
    description TEXT COMMENT 'What this section does',
    author VARCHAR(255) COMMENT 'Package creator name',
    author_email VARCHAR(255) COMMENT 'Contact email',
    author_organization VARCHAR(255) COMMENT 'School/District name',
    version VARCHAR(20) NOT NULL DEFAULT '1.0.0' COMMENT 'Semantic version',
    package_data JSON NOT NULL COMMENT 'Complete package definition',
    category VARCHAR(50) DEFAULT 'other' COMMENT 'Category for marketplace',
    tags JSON COMMENT 'Search tags',
    download_count INT DEFAULT 0 COMMENT 'Times downloaded',
    rating_avg DECIMAL(3,2) DEFAULT 0.00 COMMENT 'Average rating (0-5)',
    rating_count INT DEFAULT 0 COMMENT 'Number of ratings',
    is_public BOOLEAN DEFAULT FALSE COMMENT 'Available in marketplace',
    is_featured BOOLEAN DEFAULT FALSE COMMENT 'Featured in marketplace',
    requires_approval BOOLEAN DEFAULT FALSE COMMENT 'Needs admin approval workflow',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_package_id (package_id),
    INDEX idx_category (category),
    INDEX idx_public (is_public, is_featured),
    INDEX idx_search (name, display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Store section packages for import/export and marketplace';

-- ============================================================================
-- SECTION INSTALLATIONS
-- ============================================================================

-- Track which packages are installed and their versions
CREATE TABLE IF NOT EXISTS section_installations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Reference to sections table',
    package_id VARCHAR(100) COMMENT 'Source package identifier',
    installed_version VARCHAR(20) NOT NULL COMMENT 'Currently installed version',
    available_version VARCHAR(20) COMMENT 'Latest available version',
    auto_update BOOLEAN DEFAULT FALSE COMMENT 'Auto-update when new version available',
    installed_by INT COMMENT 'User who installed',
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (installed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_section (section_id),
    INDEX idx_updates (package_id, installed_version, available_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track installed sections and available updates';

-- ============================================================================
-- FIELD DEFINITIONS (Schema for Dynamic Forms)
-- ============================================================================

-- Define custom fields for each section
CREATE TABLE IF NOT EXISTS section_field_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Parent section',
    field_name VARCHAR(100) NOT NULL COMMENT 'Internal field identifier',
    field_type VARCHAR(50) NOT NULL COMMENT 'Data type (text, number, date, select, etc.)',
    field_label VARCHAR(255) NOT NULL COMMENT 'Display label for users',
    field_config JSON COMMENT 'Type-specific configuration (options, validation, etc.)',
    is_required BOOLEAN DEFAULT FALSE COMMENT 'Must be filled out',
    is_searchable BOOLEAN DEFAULT TRUE COMMENT 'Include in search index',
    is_exportable BOOLEAN DEFAULT TRUE COMMENT 'Include in exports',
    is_editable_by_creator BOOLEAN DEFAULT FALSE COMMENT 'Can creator edit their own entry',
    show_in_list BOOLEAN DEFAULT TRUE COMMENT 'Show in table view',
    show_in_detail BOOLEAN DEFAULT TRUE COMMENT 'Show in detail view',
    sort_order INT DEFAULT 0 COMMENT 'Display order',
    validation_rules JSON COMMENT 'Custom validation (min, max, regex, etc.)',
    help_text TEXT COMMENT 'Helper text shown to users',
    default_value TEXT COMMENT 'Default value for new entries',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    UNIQUE KEY unique_section_field (section_id, field_name),
    INDEX idx_section_order (section_id, sort_order),
    INDEX idx_type (field_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Define custom fields for dynamic sections';

-- ============================================================================
-- DYNAMIC DATA STORAGE
-- ============================================================================

-- Store actual data entries (flexible JSON storage)
CREATE TABLE IF NOT EXISTS section_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Parent section',
    record_uuid VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID for referencing',
    record_data JSON NOT NULL COMMENT 'All field values as JSON',
    status ENUM('draft', 'pending', 'approved', 'rejected', 'archived') DEFAULT 'approved' COMMENT 'Workflow status',
    is_deleted BOOLEAN DEFAULT FALSE COMMENT 'Soft delete flag',
    created_by INT COMMENT 'User who created',
    updated_by INT COMMENT 'User who last updated',
    approved_by INT COMMENT 'User who approved (if workflow)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL COMMENT 'When approved',
    deleted_at TIMESTAMP NULL COMMENT 'When soft deleted',
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_section_status (section_id, status, is_deleted),
    INDEX idx_uuid (record_uuid),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    FULLTEXT INDEX idx_record_search (record_data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Store dynamic section data as JSON';

-- ============================================================================
-- AUDIT TRAIL
-- ============================================================================

-- Complete audit history for all record changes
CREATE TABLE IF NOT EXISTS section_record_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL COMMENT 'Reference to section_records',
    action ENUM('create', 'update', 'delete', 'approve', 'reject', 'restore', 'export') NOT NULL,
    old_data JSON COMMENT 'Data before change',
    new_data JSON COMMENT 'Data after change',
    changed_fields JSON COMMENT 'List of fields that changed',
    change_summary TEXT COMMENT 'Human-readable summary',
    changed_by INT COMMENT 'User who made change',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) COMMENT 'IP address of user',
    user_agent TEXT COMMENT 'Browser/device info',
    FOREIGN KEY (record_id) REFERENCES section_records(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_record (record_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_action (action),
    INDEX idx_changed_by (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit trail for all record changes';

-- ============================================================================
-- SECTION ADMINISTRATORS
-- ============================================================================

-- Assign section-specific admin roles
CREATE TABLE IF NOT EXISTS section_administrators (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Section they manage',
    user_id INT NOT NULL COMMENT 'User with admin rights',
    permission_level ENUM('viewer', 'editor', 'admin', 'owner') DEFAULT 'admin' COMMENT 'Level of access',
    can_view_all BOOLEAN DEFAULT TRUE COMMENT 'View all records',
    can_edit_records BOOLEAN DEFAULT TRUE COMMENT 'Edit any record',
    can_delete_records BOOLEAN DEFAULT FALSE COMMENT 'Delete records',
    can_export BOOLEAN DEFAULT TRUE COMMENT 'Export data',
    can_manage_users BOOLEAN DEFAULT FALSE COMMENT 'Add/remove section users',
    can_manage_fields BOOLEAN DEFAULT FALSE COMMENT 'Modify section structure',
    granted_by INT COMMENT 'Who granted this access',
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL COMMENT 'Optional expiration date',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Active assignment',
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_section_user (section_id, user_id),
    INDEX idx_user (user_id),
    INDEX idx_section (section_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Section-specific administrator assignments';

-- ============================================================================
-- DASHBOARD MENU ITEMS
-- ============================================================================

-- Dynamic sidebar navigation for section admins
CREATE TABLE IF NOT EXISTS section_menu_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Parent section',
    parent_id INT NULL COMMENT 'Parent menu item (for sub-menus)',
    label VARCHAR(100) NOT NULL COMMENT 'Menu text',
    icon VARCHAR(10) COMMENT 'Emoji or icon',
    route VARCHAR(255) NOT NULL COMMENT 'URL path',
    required_permission VARCHAR(50) COMMENT 'Permission needed to see',
    sort_order INT DEFAULT 0 COMMENT 'Display order',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Show in menu',
    open_in_new_tab BOOLEAN DEFAULT FALSE COMMENT 'Open in new window',
    badge_count_query TEXT COMMENT 'SQL query for badge number',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES section_menu_items(id) ON DELETE CASCADE,
    INDEX idx_section_parent (section_id, parent_id, sort_order),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Dynamic menu items for section dashboards';

-- ============================================================================
-- FILE ATTACHMENTS
-- ============================================================================

-- Store file uploads associated with records
CREATE TABLE IF NOT EXISTS section_record_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL COMMENT 'Parent record',
    field_name VARCHAR(100) COMMENT 'Field this file belongs to',
    original_filename VARCHAR(255) NOT NULL COMMENT 'User uploaded filename',
    stored_filename VARCHAR(255) NOT NULL COMMENT 'Server storage filename',
    file_path VARCHAR(500) NOT NULL COMMENT 'Storage path',
    file_size INT NOT NULL COMMENT 'Size in bytes',
    mime_type VARCHAR(100) COMMENT 'File type',
    uploaded_by INT COMMENT 'User who uploaded',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT FALSE COMMENT 'Soft delete',
    FOREIGN KEY (record_id) REFERENCES section_records(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_record (record_id),
    INDEX idx_field (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='File attachments for section records';

-- ============================================================================
-- WORKFLOW DEFINITIONS
-- ============================================================================

-- Define approval workflows for sections
CREATE TABLE IF NOT EXISTS section_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL COMMENT 'Section using this workflow',
    workflow_name VARCHAR(100) NOT NULL COMMENT 'Workflow identifier',
    steps JSON NOT NULL COMMENT 'Workflow steps configuration',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Currently in use',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_section (section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Approval workflow definitions';

-- ============================================================================
-- WORKFLOW INSTANCES
-- ============================================================================

-- Track workflow progress for each record
CREATE TABLE IF NOT EXISTS section_workflow_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL COMMENT 'Record in workflow',
    workflow_id INT NOT NULL COMMENT 'Workflow definition',
    current_step INT DEFAULT 1 COMMENT 'Current step number',
    status ENUM('pending', 'in_progress', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (record_id) REFERENCES section_records(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_id) REFERENCES section_workflows(id) ON DELETE CASCADE,
    INDEX idx_record (record_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track workflow instances for records';

-- ============================================================================
-- WORKFLOW ACTIONS
-- ============================================================================

-- Log each workflow action/approval
CREATE TABLE IF NOT EXISTS section_workflow_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL COMMENT 'Workflow instance',
    step_number INT NOT NULL COMMENT 'Step in workflow',
    action ENUM('approve', 'reject', 'delegate', 'comment') NOT NULL,
    actor_id INT COMMENT 'User who took action',
    comments TEXT COMMENT 'Optional comments',
    acted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instance_id) REFERENCES section_workflow_instances(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_instance (instance_id),
    INDEX idx_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Log workflow actions and approvals';

-- ============================================================================
-- VIEWS FOR CONVENIENCE
-- ============================================================================

-- Easy access to section admin info
CREATE OR REPLACE VIEW section_admin_view AS
SELECT 
    sa.id,
    sa.section_id,
    s.display_name as section_name,
    s.slug as section_slug,
    sa.user_id,
    u.name as user_name,
    u.email as user_email,
    sa.permission_level,
    sa.can_view_all,
    sa.can_edit_records,
    sa.can_delete_records,
    sa.can_export,
    sa.can_manage_users,
    sa.granted_by,
    gb.name as granted_by_name,
    sa.granted_at,
    sa.is_active
FROM section_administrators sa
JOIN sections s ON sa.section_id = s.id
JOIN users u ON sa.user_id = u.id
LEFT JOIN users gb ON sa.granted_by = gb.id
WHERE sa.is_active = TRUE;

-- Easy record access with metadata
CREATE OR REPLACE VIEW section_records_view AS
SELECT 
    sr.id,
    sr.section_id,
    s.display_name as section_name,
    s.slug as section_slug,
    sr.record_uuid,
    sr.record_data,
    sr.status,
    sr.created_by,
    cu.name as creator_name,
    cu.email as creator_email,
    sr.updated_by,
    uu.name as updater_name,
    sr.approved_by,
    au.name as approver_name,
    sr.created_at,
    sr.updated_at,
    sr.approved_at,
    sr.is_deleted
FROM section_records sr
JOIN sections s ON sr.section_id = s.id
LEFT JOIN users cu ON sr.created_by = cu.id
LEFT JOIN users uu ON sr.updated_by = uu.id
LEFT JOIN users au ON sr.approved_by = au.id
WHERE sr.is_deleted = FALSE;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

-- Additional indexes for common queries
ALTER TABLE section_records ADD INDEX idx_status_created (status, created_at DESC);
ALTER TABLE section_record_history ADD INDEX idx_action_date (action, changed_at DESC);
ALTER TABLE section_field_definitions ADD INDEX idx_type_required (field_type, is_required);

-- ============================================================================
-- SCHEMA COMPLETE
-- ============================================================================
-- This schema supports:
-- ✅ Custom section creation
-- ✅ Import/Export packages
-- ✅ Dynamic fields (any data type)
-- ✅ Role-based section admins
-- ✅ Complete audit trail
-- ✅ Approval workflows
-- ✅ File attachments
-- ✅ Dashboard integration
-- ============================================================================
