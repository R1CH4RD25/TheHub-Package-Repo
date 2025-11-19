#!/usr/bin/env php
<?php
/**
 * Command Center Database Migration
 *
 * Creates all tables required for the Command Center submission management system:
 * - tenants (future-proof multi-tenant support)
 * - section_submissions (core submission data)
 * - section_submission_statuses (workflow statuses)
 * - section_submission_comments (threaded comments)
 * - section_submission_attachments (file uploads)
 * - section_submission_history (audit trail)
 *
 * Also adds cc_prefix column to sections table for display_id generation.
 *
 * Based on: COMMAND_CENTER_ARCHITECTURE.md v1.0 (External Audit Applied)
 * Run: php cli/migrate-command-center.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Hub\Database;

$db = Database::getInstance();

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "  COMMAND CENTER MIGRATION - PHASE 1 FOUNDATION\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";

// Track migration status
$errors = [];
$warnings = [];
$created = [];

/**
 * Helper: Execute SQL with error handling
 */
function executeSql($db, $sql, $description) {
    global $errors, $created;

    echo "→ {$description}... ";

    try {
        $db->execute($sql);
        echo "✅ SUCCESS\n";
        $created[] = $description;
        return true;
    } catch (\Exception $e) {
        // Check if error is "table already exists"
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "⚠️  ALREADY EXISTS (skipping)\n";
            return true;
        }

        echo "❌ FAILED\n";
        echo "   Error: " . $e->getMessage() . "\n";
        $errors[] = [$description, $e->getMessage()];
        return false;
    }
}

/**
 * Helper: Insert default data with error handling
 */
function insertDefaults($db, $sql, $description) {
    global $errors, $warnings, $created;

    echo "→ {$description}... ";

    try {
        $db->execute($sql);
        echo "✅ SUCCESS\n";
        $created[] = $description;
        return true;
    } catch (\Exception $e) {
        // Check if error is duplicate entry (data already exists)
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "⚠️  ALREADY EXISTS (skipping)\n";
            $warnings[] = $description;
            return true;
        }

        echo "❌ FAILED\n";
        echo "   Error: " . $e->getMessage() . "\n";
        $errors[] = [$description, $e->getMessage()];
        return false;
    }
}

// ============================================================================
// STEP 1: CREATE TENANTS TABLE
// ============================================================================
echo "\n";
echo "STEP 1: Tenants Table (Future-Proof Multi-Tenancy)\n";
echo "-------------------------------------------------------------------\n";

$sql = "CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain (domain),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Multi-tenant support - single tenant (Woodson ISD) for v1.0'";

executeSql($db, $sql, "Create tenants table");

// Insert default tenant
$sql = "INSERT INTO tenants (id, name, domain, is_active)
        VALUES (1, 'Woodson ISD', 'woodsonisd.net', 1)";
insertDefaults($db, $sql, "Insert default tenant (Woodson ISD)");

// ============================================================================
// STEP 2: CREATE SECTION_SUBMISSION_STATUSES TABLE
// ============================================================================
echo "\n";
echo "STEP 2: Section Submission Statuses (Workflow States)\n";
echo "-------------------------------------------------------------------\n";

$sql = "CREATE TABLE IF NOT EXISTS section_submission_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    section_id INT NULL COMMENT 'NULL = global status, available to all sections',

    status_name VARCHAR(50) NOT NULL,
    status_color VARCHAR(7) NOT NULL COMMENT 'Hex color code, e.g., #28a745',
    status_icon VARCHAR(50) NULL COMMENT 'Bootstrap icon class, e.g., bi-check-circle',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE ON UPDATE CASCADE,

    UNIQUE KEY unique_tenant_section_status (tenant_id, section_id, status_name),
    INDEX idx_tenant (tenant_id),
    INDEX idx_section (section_id),
    INDEX idx_sort (sort_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Workflow statuses - global defaults + section-specific overrides'";

executeSql($db, $sql, "Create section_submission_statuses table");

// Insert default global statuses
$defaultStatuses = [
    [1, NULL, 'Submitted', '#6c757d', 'bi-inbox', 10],
    [1, NULL, 'Under Review', '#ffc107', 'bi-hourglass-split', 20],
    [1, NULL, 'Pending Info', '#17a2b8', 'bi-question-circle', 30],
    [1, NULL, 'Approved', '#28a745', 'bi-check-circle', 40],
    [1, NULL, 'Rejected', '#dc3545', 'bi-x-circle', 50],
    [1, NULL, 'Completed', '#007bff', 'bi-check-all', 60]
];

foreach ($defaultStatuses as $status) {
    $sql = "INSERT INTO section_submission_statuses
            (tenant_id, section_id, status_name, status_color, status_icon, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)";

    try {
        $db->execute($sql, $status);
        echo "→ Insert status '{$status[2]}'... ✅ SUCCESS\n";
        $created[] = "Default status: {$status[2]}";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo "→ Insert status '{$status[2]}'... ⚠️  ALREADY EXISTS\n";
            $warnings[] = "Status: {$status[2]}";
        } else {
            echo "→ Insert status '{$status[2]}'... ❌ FAILED\n";
            echo "   Error: " . $e->getMessage() . "\n";
            $errors[] = ["Status: {$status[2]}", $e->getMessage()];
        }
    }
}

// ============================================================================
// STEP 3: CREATE SECTION_SUBMISSIONS TABLE
// ============================================================================
echo "\n";
echo "STEP 3: Section Submissions (Core Submission Data)\n";
echo "-------------------------------------------------------------------\n";

$sql = "CREATE TABLE IF NOT EXISTS section_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    section_id INT NOT NULL,

    -- Human-friendly display ID (optional, auto-generated)
    display_id VARCHAR(50) UNIQUE NULL COMMENT 'e.g., BR-2024-001, VR-2024-042',

    -- Entity linking (for cross-referencing other records)
    entity_name VARCHAR(100) NULL COMMENT 'e.g., vehicles, users, fuel_records',
    entity_id INT UNSIGNED NULL COMMENT 'ID of linked entity',

    submitted_by INT NULL COMMENT 'NULL for anonymous submissions',
    status_id INT UNSIGNED NOT NULL COMMENT 'Default set via getDefaultStatusId() - never hardcode',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

    submission_data JSON NOT NULL COMMENT 'Dynamic form data from package',

    ip_address VARCHAR(45),
    user_agent TEXT,

    assigned_to INT NULL COMMENT 'Single assignment for v1.0 - future: multi-assignment table',
    due_date DATE NULL,
    is_draft TINYINT(1) DEFAULT 0 COMMENT 'Drafts excluded from default queries (WHERE is_draft = 0)',

    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES section_submission_statuses(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,

    INDEX idx_tenant (tenant_id),
    INDEX idx_section_status (section_id, status_id),
    INDEX idx_submitted_by (submitted_by),
    INDEX idx_created_at (created_at),
    INDEX idx_priority (priority),
    INDEX idx_display_id (display_id),
    INDEX idx_entity_link (entity_name, entity_id),
    INDEX idx_assigned_to (assigned_to),
    INDEX idx_is_draft (is_draft),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='All submissions from all sections - dynamic JSON storage'";

executeSql($db, $sql, "Create section_submissions table");

// ============================================================================
// STEP 4: CREATE SECTION_SUBMISSION_COMMENTS TABLE
// ============================================================================
echo "\n";
echo "STEP 4: Section Submission Comments (Threaded Discussion)\n";
echo "-------------------------------------------------------------------\n";

$sql = "CREATE TABLE IF NOT EXISTS section_submission_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    submission_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    parent_comment_id INT UNSIGNED NULL COMMENT 'For threading replies',

    comment_text TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0 COMMENT 'Internal staff notes vs. public comments',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (submission_id) REFERENCES section_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES section_submission_comments(id) ON DELETE CASCADE,

    INDEX idx_tenant (tenant_id),
    INDEX idx_submission (submission_id),
    INDEX idx_user (user_id),
    INDEX idx_parent (parent_comment_id),
    INDEX idx_created_at (created_at),
    INDEX idx_is_internal (is_internal),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Threaded comments on submissions - public and internal notes'";

executeSql($db, $sql, "Create section_submission_comments table");

// ============================================================================
// STEP 5: CREATE SECTION_SUBMISSION_ATTACHMENTS TABLE
// ============================================================================
echo "\n";
echo "STEP 5: Section Submission Attachments (File Uploads)\n";
echo "-------------------------------------------------------------------\n";

$sql = "CREATE TABLE IF NOT EXISTS section_submission_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    submission_id INT UNSIGNED NOT NULL,
    uploaded_by INT NOT NULL,

    original_filename VARCHAR(255) NOT NULL COMMENT 'User\\'s original filename for display',
    file_name VARCHAR(255) NOT NULL COMMENT 'Sanitized/hashed storage filename',
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL COMMENT 'bytes',
    mime_type VARCHAR(100),
    file_hash VARCHAR(64) COMMENT 'SHA-256 hash for duplicate detection',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (submission_id) REFERENCES section_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_tenant (tenant_id),
    INDEX idx_submission (submission_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_file_hash (file_hash),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='File attachments for submissions with duplicate detection'";

executeSql($db, $sql, "Create section_submission_attachments table");

// ============================================================================
// STEP 6: CREATE SECTION_SUBMISSION_HISTORY TABLE
// ============================================================================
echo "\n";
echo "STEP 6: Section Submission History (Audit Trail)\n";
echo "-------------------------------------------------------------------\n";

$sql = "CREATE TABLE IF NOT EXISTS section_submission_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    submission_id INT UNSIGNED NOT NULL,
    user_id INT NOT NULL,

    action VARCHAR(50) NOT NULL COMMENT 'status_change, priority_change, assigned, etc.',
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info' COMMENT 'Action severity for filtering/alerting',
    old_value TEXT,
    new_value TEXT,
    notes TEXT,

    ip_address VARCHAR(45) NULL COMMENT 'Capture for security-sensitive actions only',
    user_agent TEXT NULL COMMENT 'Capture for security-sensitive actions only',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (submission_id) REFERENCES section_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_tenant (tenant_id),
    INDEX idx_submission (submission_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit trail for all submission changes with severity tracking'";

executeSql($db, $sql, "Create section_submission_history table");

// ============================================================================
// STEP 7: ALTER SECTIONS TABLE (ADD CC_PREFIX)
// ============================================================================
echo "\n";
echo "STEP 7: Alter Sections Table (Add cc_prefix Column)\n";
echo "-------------------------------------------------------------------\n";

// Check if column already exists
$columnExists = false;
try {
    $result = $db->fetchAll("SHOW COLUMNS FROM sections LIKE 'cc_prefix'");
    $columnExists = !empty($result);
} catch (\Exception $e) {
    // Ignore error, proceed with ALTER
}

if ($columnExists) {
    echo "→ Add cc_prefix column to sections table... ⚠️  ALREADY EXISTS (skipping)\n";
    $warnings[] = "sections.cc_prefix column";
} else {
    $sql = "ALTER TABLE sections
            ADD COLUMN cc_prefix VARCHAR(10) NULL
            COMMENT 'Command Center display ID prefix (e.g., BR, VR, RR)'
            AFTER slug";

    echo "→ Add cc_prefix column to sections table... ";
    try {
        $db->execute($sql);
        echo "✅ SUCCESS\n";
        $created[] = "Add cc_prefix column to sections table";
    } catch (\Exception $e) {
        // Check if error is "duplicate column"
        if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "⚠️  ALREADY EXISTS (skipping)\n";
            $warnings[] = "sections.cc_prefix column (race condition detected)";
        } else {
            echo "❌ FAILED\n";
            echo "   Error: " . $e->getMessage() . "\n";
            $errors[] = ["Add cc_prefix column to sections table", $e->getMessage()];
        }
    }
}

// ============================================================================
// MIGRATION SUMMARY
// ============================================================================
echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "  MIGRATION SUMMARY\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";

echo "✅ Created: " . count($created) . " items\n";
if (!empty($created)) {
    foreach ($created as $item) {
        echo "   • {$item}\n";
    }
}

if (!empty($warnings)) {
    echo "\n⚠️  Warnings: " . count($warnings) . " items (already existed)\n";
    foreach ($warnings as $item) {
        echo "   • {$item}\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ Errors: " . count($errors) . "\n";
    foreach ($errors as $error) {
        echo "   • {$error[0]}: {$error[1]}\n";
    }
    echo "\n";
    echo "MIGRATION FAILED - Please review errors above.\n";
    echo "\n";
    exit(1);
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "  ✅ MIGRATION COMPLETED SUCCESSFULLY\n";
echo "═══════════════════════════════════════════════════════════════════\n";
echo "\n";

// Display next steps
echo "NEXT STEPS:\n";
echo "1. Verify tables created:\n";
echo "   mysql> USE woodson_hub;\n";
echo "   mysql> SHOW TABLES LIKE 'section_%';\n";
echo "\n";
echo "2. Verify default statuses:\n";
echo "   mysql> SELECT * FROM section_submission_statuses;\n";
echo "\n";
echo "3. Create PHP classes:\n";
echo "   • src/CommandCenter.php\n";
echo "   • src/Submission.php\n";
echo "   • src/SubmissionComment.php\n";
echo "   • src/SubmissionAttachment.php\n";
echo "\n";
echo "4. Build Command Center UI:\n";
echo "   • public/command/index.php (dashboard)\n";
echo "   • public/command/section.php (list view)\n";
echo "   • public/command/submission.php (detail view)\n";
echo "\n";

exit(0);
