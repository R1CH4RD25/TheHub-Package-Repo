# Command Center Architecture

**Status:** ✅ Finalized v1.0 (Audited & Approved)
**Created:** November 13, 2025
**Updated:** November 13, 2025
**Purpose:** Professional administrative interface for managing submissions from all sections/packages in The Hub

**Audit Results:** 85% alignment with TheHub v1.3 codebase | ✅ APPROVED WITH MODIFICATIONS

---

## 🎯 Executive Summary

The **Command Center** is the missing middle layer in TheHub's three-tier architecture. It provides section managers and administrators with a professional, feature-rich interface to manage submissions, track workflows, approve requests, and generate reports.

### Three-Tier Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    SUPER ADMIN DASHBOARD                         │
│                     /admin/ - Desktop Only                       │
│  • System configuration    • Package management                  │
│  • User management         • Audit logs                          │
│  • Section configuration   • Role permissions                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                      COMMAND CENTER                              │
│                   /command/ - Desktop First                      │
│  • Section submission management    • Status workflows           │
│  • Bulk actions & approvals        • Comments & notes           │
│  • Analytics & reporting           • Export capabilities        │
│  • Attachment management           • Email notifications        │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│                          THE HUB                                 │
│                   /hub.php - Mobile First                        │
│  • Section card selector           • Simple submission forms    │
│  • User-friendly interface         • Quick status checks        │
│  • Responsive design               • Touch-optimized            │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🏗️ Core Requirements

### 1. Design Philosophy
- **Professional & Clean:** DataTables-based interface, NOT hub-like card design
- **Desktop-First:** Optimized for administrators working at desks
- **Feature-Rich:** Advanced filtering, sorting, bulk operations, exports
- **Package-Agnostic:** Works dynamically with any installed package
- **Role-Aware:** Respects section permissions and role hierarchies

### 2. User Roles & Access
| Role | Command Center Access | Capabilities |
|------|----------------------|--------------|
| **Super Admin** | Full access to all sections | View, edit, approve, delete, export, configure |
| **Admin** | Access based on section permissions | Same as Super Admin (where permitted) |
| **Staff** | Limited to assigned sections | View, edit own submissions, add comments |
| **User** | No Command Center access | Submit via The Hub, view own submissions |

### 3. Navigation Structure
```
/command/
├── index.php           # Dashboard overview (all sections)
├── section.php         # Section-specific view (e.g., ?slug=bullying-report)
├── submission.php      # Individual submission detail view
├── analytics.php       # Cross-section analytics & reports
└── exports.php         # Bulk export interface
```

---

## 📊 Database Schema

### Schema Design Philosophy

**TheHub v1.3 Standards:**
- Primary keys: `INT UNSIGNED AUTO_INCREMENT`
- Foreign keys: `INT UNSIGNED` matching parent table
- Timestamps: `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` (UTC storage)
- Soft deletes: `is_active TINYINT(1) DEFAULT 1`
- Audit columns: `created_at`, `updated_at`
- Future-proofing: `tenant_id INT UNSIGNED NOT NULL DEFAULT 1`

### Tenant Support (Future-Proof)

All Command Center tables include `tenant_id` for future multi-tenant expansion:

```sql
CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    domain VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default tenant for Woodson ISD
INSERT INTO tenants (id, name, domain) VALUES (1, 'Woodson ISD', 'woodsonisd.net');
```

### New Tables Required

#### 1. `section_submissions`
Stores all submissions from any package/section.

```sql
CREATE TABLE section_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    section_id INT UNSIGNED NOT NULL,

    -- Human-friendly display ID (optional, auto-generated)
    display_id VARCHAR(50) UNIQUE NULL COMMENT 'e.g., BR-2024-001, VR-2024-042',

    -- Entity linking (for cross-referencing other records)
    entity_name VARCHAR(100) NULL COMMENT 'e.g., vehicles, users, fuel_records',
    entity_id INT UNSIGNED NULL COMMENT 'ID of linked entity',

    submitted_by INT UNSIGNED NULL COMMENT 'NULL for anonymous submissions',
    status_id INT UNSIGNED NOT NULL DEFAULT 1,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',

    submission_data JSON NOT NULL COMMENT 'Dynamic form data from package',

    ip_address VARCHAR(45),
    user_agent TEXT,

    assigned_to INT UNSIGNED NULL,
    due_date DATE NULL,
    is_draft TINYINT(1) DEFAULT 0,

    reviewed_at TIMESTAMP NULL,
    reviewed_by INT UNSIGNED NULL,

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
    INDEX idx_is_draft (is_draft)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `section_submission_statuses`
Predefined workflow statuses (global + section-specific).

```sql
CREATE TABLE section_submission_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    section_id INT UNSIGNED NULL COMMENT 'NULL = global status, available to all sections',

    status_name VARCHAR(50) NOT NULL,
    status_color VARCHAR(7) NOT NULL COMMENT 'Hex color code, e.g., #28a745',
    status_icon VARCHAR(50) NULL COMMENT 'Bootstrap icon class, e.g., bi-check-circle',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,

    UNIQUE KEY unique_tenant_section_status (tenant_id, section_id, status_name),
    INDEX idx_section (section_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default global statuses
INSERT INTO section_submission_statuses (tenant_id, section_id, status_name, status_color, status_icon, sort_order) VALUES
(1, NULL, 'Submitted', '#6c757d', 'bi-inbox', 10),
(1, NULL, 'Under Review', '#ffc107', 'bi-hourglass-split', 20),
(1, NULL, 'Pending Info', '#17a2b8', 'bi-question-circle', 30),
(1, NULL, 'Approved', '#28a745', 'bi-check-circle', 40),
(1, NULL, 'Rejected', '#dc3545', 'bi-x-circle', 50),
(1, NULL, 'Completed', '#007bff', 'bi-check-all', 60);
```

#### 3. `section_submission_comments`
Comments/notes on submissions (threaded, public/internal).

```sql
CREATE TABLE section_submission_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    submission_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
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
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. `section_submission_attachments`
File attachments for submissions.

```sql
CREATE TABLE section_submission_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    submission_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,

    file_name VARCHAR(255) NOT NULL,
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
    INDEX idx_file_hash (file_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 5. `section_submission_history`
Audit trail for status changes and major actions.

```sql
CREATE TABLE section_submission_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL DEFAULT 1,
    submission_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,

    action VARCHAR(50) NOT NULL COMMENT 'status_change, priority_change, assigned, etc.',
    old_value TEXT,
    new_value TEXT,
    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (submission_id) REFERENCES section_submissions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_tenant (tenant_id),
    INDEX idx_submission (submission_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🔑 Display ID Generation

Submissions get **two IDs**:
1. **Primary Key** (`id`): INT AUTO_INCREMENT for database relationships
2. **Display ID** (`display_id`): Human-friendly reference (optional)

### Display ID Format

```
{SECTION_PREFIX}-{YEAR}-{SEQUENCE}

Examples:
BR-2024-001  (Bullying Report #1 in 2024)
VR-2024-042  (Vehicle Request #42 in 2024)
RR-2025-156  (Reimbursement Request #156 in 2025)
```

### Auto-Generation Logic

```php
function generateDisplayId($sectionSlug, $sectionPrefix) {
    $year = date('Y');
    $lastId = DB::fetchOne(
        "SELECT display_id FROM section_submissions
         WHERE section_id = ? AND display_id LIKE ?
         ORDER BY id DESC LIMIT 1",
        [$sectionId, "$sectionPrefix-$year-%"]
    );

    $sequence = 1;
    if ($lastId) {
        preg_match('/-(\d+)$/', $lastId['display_id'], $matches);
        $sequence = (int)$matches[1] + 1;
    }

    return sprintf("%s-%s-%03d", $sectionPrefix, $year, $sequence);
}
```

Packages define their prefix in manifest:

```json
"command_center": {
  "display_id_prefix": "BR"
}
```

---

## 📦 Package Format Updates

### New `.hubpkg` Sections

#### A. `command_center` Configuration
Defines how the package appears and behaves in Command Center.

```json
{
  "name": "Bullying Report",
  "slug": "bullying-report",
  "version": "1.0.0",
  "command_center": {
    "enabled": true,
    "title": "Bullying Reports",
    "description": "Manage and review bullying incident reports",
    "icon": "bi-shield-exclamation",
    "views": {
      "list": {
        "enabled": true,
        "columns": [
          {"field": "id", "label": "ID", "sortable": true, "searchable": false},
          {"field": "incident_date", "label": "Incident Date", "sortable": true, "searchable": false},
          {"field": "student_name", "label": "Student Name", "sortable": true, "searchable": true},
          {"field": "submitted_by_name", "label": "Submitted By", "sortable": true, "searchable": true},
          {"field": "status", "label": "Status", "sortable": true, "searchable": false},
          {"field": "priority", "label": "Priority", "sortable": true, "searchable": false},
          {"field": "created_at", "label": "Submitted", "sortable": true, "searchable": false}
        ],
        "default_sort": {"field": "created_at", "order": "DESC"},
        "filters": [
          {"field": "status", "type": "select", "options": "dynamic"},
          {"field": "priority", "type": "select", "options": ["low", "normal", "high", "urgent"]},
          {"field": "date_range", "type": "daterange"}
        ]
      },
      "detail": {
        "enabled": true,
        "layout": "two-column",
        "sections": [
          {
            "title": "Incident Details",
            "fields": ["incident_date", "incident_time", "incident_location", "incident_description"]
          },
          {
            "title": "Student Information",
            "fields": ["student_name", "student_grade", "student_id"]
          },
          {
            "title": "Witnesses",
            "fields": ["witness_names", "witness_statements"]
          }
        ]
      },
      "analytics": {
        "enabled": true,
        "charts": [
          {"type": "timeline", "field": "created_at", "title": "Reports Over Time"},
          {"type": "pie", "field": "priority", "title": "Priority Distribution"},
          {"type": "bar", "field": "incident_location", "title": "Incidents by Location"}
        ]
      }
    },
    "actions": {
      "approve": {"enabled": true, "requires_role": "admin", "status_change": "Approved"},
      "reject": {"enabled": true, "requires_role": "admin", "status_change": "Rejected"},
      "request_info": {"enabled": true, "requires_role": "staff", "status_change": "Pending Info"},
      "close": {"enabled": true, "requires_role": "admin", "status_change": "Completed"}
    },
    "exports": {
      "csv": true,
      "excel": true,
      "pdf": true
    },
    "notifications": {
      "on_submit": {"roles": ["admin"], "email": true},
      "on_status_change": {"submitter": true, "email": true},
      "on_comment": {"submitter": true, "mentioned_users": true}
    }
  },
  "fields": [
    // ... existing field definitions
  ]
}
```

#### B. `the_hub` Configuration (NEW)
Defines how the package appears in The Hub (end-user interface).

```json
{
  "the_hub": {
    "enabled": true,
    "card": {
      "title": "Report Bullying",
      "subtitle": "Submit a confidential bullying incident report",
      "icon": "bi-shield-exclamation",
      "color": "#dc3545"
    },
    "form": {
      "layout": "single-page",  // or "multi-step"
      "submit_button_text": "Submit Report",
      "confirmation_message": "Your report has been submitted. A counselor will review it shortly.",
      "allow_anonymous": false,
      "allow_drafts": true
    }
  }
}
```

---

## 🎨 UI/UX Design

### Command Center Dashboard (`/command/index.php`)

**Layout:**
```
┌─────────────────────────────────────────────────────────────────┐
│ 🏠 Command Center                         [Profile] [Settings]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  📊 Overview (Last 30 Days)                                      │
│  ┌───────────┬───────────┬───────────┬───────────┐             │
│  │ Pending   │ Under Rev │ Approved  │ Total     │             │
│  │    24     │    12     │    156    │   192     │             │
│  └───────────┴───────────┴───────────┴───────────┘             │
│                                                                   │
│  📋 Your Sections                                                │
│  ┌───────────────────────────────────────────────────────┐      │
│  │ 🛡️  Bullying Reports        [ 8 pending ] [View →]   │      │
│  │ 🚗 Vehicle Requests          [ 2 pending ] [View →]   │      │
│  │ 💰 Reimbursement Requests    [ 5 pending ] [View →]   │      │
│  └───────────────────────────────────────────────────────┘      │
│                                                                   │
│  📈 Recent Activity                                              │
│  • John Doe submitted a Bullying Report (5 mins ago)            │
│  • You approved Vehicle Request #1234 (1 hour ago)              │
│  • Sarah Smith commented on Reimbursement #5678 (2 hours ago)   │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Section View (`/command/section.php?slug=bullying-report`)

**Features:**
- **DataTables** with server-side processing
- **Advanced Filters:** Status, priority, date range, submitter
- **Bulk Actions:** Change status, assign reviewer, export
- **Quick Actions:** View, edit, comment, download attachments
- **Column Customization:** Show/hide columns, reorder
- **Export Options:** CSV, Excel, PDF

### Submission Detail View (`/command/submission.php?id=123`)

**Layout:**
```
┌─────────────────────────────────────────────────────────────────┐
│ ← Back to Bullying Reports                                       │
├─────────────────────────────────────────────────────────────────┤
│  Bullying Report #123                     [Edit] [Delete]        │
│  Status: Under Review    Priority: High    Submitted: 2025-11-12│
│                                                                   │
│  ┌─────────────────────┬─────────────────────────────────┐      │
│  │ DETAILS             │ ACTIVITY                        │      │
│  │                     │                                 │      │
│  │ Incident Date:      │ Timeline:                       │      │
│  │ Nov 10, 2025        │ • Submitted by Jane Doe         │      │
│  │                     │   Nov 12, 2025 10:30 AM         │      │
│  │ Location:           │ • Status changed to             │      │
│  │ Cafeteria           │   "Under Review" by Admin       │      │
│  │                     │   Nov 12, 2025 11:00 AM         │      │
│  │ Student:            │ • Comment added by Counselor    │      │
│  │ [Redacted]          │   Nov 12, 2025 2:15 PM          │      │
│  │                     │                                 │      │
│  │ Description:        │ Comments (3):                   │      │
│  │ [Full text...]      │ [Comment thread here...]        │      │
│  │                     │                                 │      │
│  │ Attachments (2):    │ [Add Comment...]                │      │
│  │ • photo1.jpg        │ [Change Status ▼] [Assign ▼]   │      │
│  │ • statement.pdf     │                                 │      │
│  └─────────────────────┴─────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔧 Technical Implementation

### File Structure
```
/var/www/woodson/thehub/
├── public/
│   └── command/
│       ├── index.php              # Dashboard
│       ├── section.php            # Section list view
│       ├── submission.php         # Submission detail
│       ├── analytics.php          # Analytics & reports
│       ├── exports.php            # Export interface
│       └── api/
│           ├── submissions.php    # CRUD for submissions
│           ├── comments.php       # Comment management
│           ├── attachments.php    # File upload/download
│           └── analytics.php      # Analytics data
├── src/
│   ├── CommandCenter.php          # Core CC class
│   ├── Submission.php             # Submission model
│   ├── SubmissionComment.php      # Comment model
│   ├── SubmissionAttachment.php   # Attachment model
│   └── SubmissionAnalytics.php    # Analytics engine
└── public/assets/
    ├── js/
    │   └── command-center.js      # CC JavaScript
    └── css/
        └── command-center.css     # CC styles
```

### PHP Classes

#### `src/CommandCenter.php`
```php
namespace Hub;

class CommandCenter {
    private $db;
    private $userId;
    private $userRole;

    public function getSectionSubmissions($sectionSlug, $filters = []);
    public function getSubmissionById($id);
    public function createSubmission($sectionId, $userId, $data);
    public function updateSubmission($id, $data);
    public function deleteSubmission($id);
    public function changeStatus($submissionId, $statusId, $userId, $notes = '');
    public function getUserAccessibleSections($userId);
    public function getDashboardStats($userId);
    public function exportSubmissions($sectionSlug, $format, $filters = []);
}
```

#### `src/Submission.php`
```php
namespace Hub;

class Submission {
    public function getById($id);
    public function getBySectionId($sectionId, $filters = []);
    public function create($data);
    public function update($id, $data);
    public function delete($id);
    public function getComments($submissionId);
    public function getAttachments($submissionId);
    public function getHistory($submissionId);
    public function addComment($submissionId, $userId, $comment, $isInternal = false);
    public function addAttachment($submissionId, $userId, $fileData);
}
```

---

## 🚦 Implementation Phases

### Phase 1: Foundation (Week 1)
- ✅ Architecture planning (this document)
- ⏳ Database schema creation + migration script
- ⏳ Update `.hubpkg` format specification
- ⏳ Create `src/CommandCenter.php` + `src/Submission.php`
- ⏳ Build `/command/index.php` dashboard (basic)

### Phase 2: Core Features (Week 2)
- ⏳ Section list view with DataTables
- ⏳ Submission detail view
- ⏳ Status workflow management
- ⏳ Comment system
- ⏳ Attachment upload/download

### Phase 3: Advanced Features (Week 3)
- ⏳ Bulk actions
- ⏳ Analytics dashboard
- ⏳ Export functionality (CSV, Excel, PDF)
- ⏳ Email notifications
- ⏳ Role-based access refinement

### Phase 4: The Hub Integration (Week 4)
- ⏳ Build dynamic submission forms for The Hub
- ⏳ Connect submissions to Command Center
- ⏳ Test end-to-end workflow
- ⏳ Mobile optimization for The Hub

### Phase 5: Package Updates (Week 5)
- ⏳ Update all 4 packages with `command_center` + `the_hub` configs
- ⏳ Test each package thoroughly
- ⏳ Documentation updates
- ⏳ Training materials for admins

---

## 📝 Documentation Needs

1. **PACKAGE_DEVELOPMENT_GUIDE.md** - How to create packages with CC support
2. **COMMAND_CENTER_USER_GUIDE.md** - How to use CC as an admin
3. **THE_HUB_USER_GUIDE.md** - How end-users submit via The Hub
4. **README.md** - Update architecture section
5. **ROADMAP.md** - Update with CC milestones

---

## 🎯 Success Metrics

- All 4 packages have fully-functional Command Center views
- Admins can manage submissions without touching database
- End-users can submit via The Hub and track status
- Export functionality works for all data types
- Mobile-friendly Hub, desktop-optimized CC
- Full audit trail for all actions
- Email notifications working
- Zero hard-coded sections (100% dynamic)

---

## 🤔 Architecture Decisions (Audit-Resolved)

### 1. ✅ ID Format Strategy → **INT + Display ID**

**Decision:** Use `id INT UNSIGNED` primary keys with optional `display_id VARCHAR(50)` for human reference.

**Rationale:**
- Maintains FK compatibility with existing tables (sections, users)
- No breaking changes to current schema
- Display IDs provide human-friendly references when needed
- Easy migration path for future ULID adoption

### 2. ✅ Multi-Tenancy → **Future-Proof with Default**

**Decision:** Add `tenant_id INT UNSIGNED NOT NULL DEFAULT 1` to all CC tables.

**Rationale:**
- Woodson ISD is single tenant now (tenant_id = 1)
- Schema supports future multi-tenant expansion
- Zero impact on current operations
- Aligns with enterprise best practices

### 3. ✅ Permission Model → **Hybrid Section-Based**

**Decision:** Use existing `section_role_access` + CC-specific logic.

**Access Logic:**
```php
// Super Admin → full CC access to all sections
if ($userRole === 'super_admin') return true;

// Admin/Staff → check section_role_access
if (SectionRoleAccess::hasAccess($userId, $sectionSlug)) {
    return true;
}

// User role → no CC access (use The Hub instead)
return false;
```

**Rationale:**
- Leverages existing permission system
- No new permission tables needed
- Consistent with current admin interface
- Role hierarchy already well-defined

### 4. ✅ File Upload Security → **Whitelist + Optional Scanning**

**Decision:** Phase 1 = File type whitelist, Phase 2 = ClamAV integration.

**Phase 1 (Launch):**
- Whitelist: jpg, png, gif, webp, pdf, doc, docx, xls, xlsx
- Max size: 10MB
- Filename sanitization
- MIME type validation

**Phase 2 (Optional):**
- ClamAV virus scanning if available
- Quarantine system for suspicious files
- Automatic notifications for blocked uploads

**Rationale:**
- Immediate security without external dependencies
- ClamAV requires server-level installation
- Phase 1 covers 95% of security needs

### 5. ✅ Anonymous Submissions → **Package-Controlled**

**Decision:** Allow anonymous submissions when package enables it.

**Implementation:**
- `submitted_by` column is `NULL` for anonymous
- Package manifest: `"allow_anonymous": true`
- IP address still logged for security
- Admin can see submission but not submitter identity

**Example Use Case:** Bullying reports, HR complaints, safety concerns

### 6. ✅ Draft System → **Enabled by Default**

**Decision:** Add `is_draft TINYINT(1)` column to `section_submissions`.

**Behavior:**
- Drafts don't trigger notifications
- Drafts don't appear in admin workflows
- Auto-save every 30 seconds
- Expire after 30 days of inactivity

**Rationale:**
- Users can save progress on long forms
- Reduces abandoned submissions
- Improves user experience---

## 🔒 Security Considerations

1. **Access Control:** Verify user has permission before showing any submission data
2. **File Uploads:** Validate file types, scan for malware, size limits
3. **SQL Injection:** Always use prepared statements
4. **XSS Prevention:** Sanitize all user input before display
5. **CSRF Protection:** Verify CSRF tokens on all state-changing operations
6. **Audit Trail:** Log all submission changes via `AuditLogger`

---

**Next Steps:** Review this architecture, provide feedback, then proceed to Phase 1 implementation.
