# Command Center Package Integration Guide

**Status:** ✅ Phase 1 Complete (Core Infrastructure)  
**Date:** November 13, 2025  
**Next:** Update Package Specification for Command Center Support

---

## 📋 Overview

The **Command Center** is now live and operational, but packages need to be updated to work with it. This document explains:

1. How Command Center discovers and displays package data
2. What packages need to add to their manifests
3. How submissions flow from The Hub → Command Center
4. Testing checklist for package developers

---

## 🎯 How It Works (Current Implementation)

### Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                         THE HUB                                  │
│                    User submits form                             │
│                    (via package form)                            │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ↓ Package creates record in its own table
                      ↓ (e.g., br_reports, vr_requests, etc.)
                      │
                      ↓ **NEW**: Package ALSO creates record in
                      ↓         section_submissions table
                      │
┌─────────────────────┴───────────────────────────────────────────┐
│                   COMMAND CENTER                                 │
│                                                                  │
│  1. Dashboard shows ALL sections with submission counts         │
│  2. Click section → DataTables list of submissions              │
│  3. Click submission → Detail view with:                        │
│     - Package-specific data (via entity_link)                   │
│     - Comments thread                                            │
│     - Attachment uploads                                         │
│     - Status history timeline                                    │
│     - Bulk actions (assign, status change, delete)              │
└──────────────────────────────────────────────────────────────────┘
```

### Database Structure

Command Center uses **6 new tables**:

1. **`section_submissions`** - Master index of all submissions
   - Links to package data via `entity_type` + `entity_id`
   - Stores display_id (BR-2024-001), status, priority, assigned_to
   - Soft-delete via `is_draft` (0=active, 1=draft)

2. **`section_submission_statuses`** - Workflow states (per-section)
   - Default: Submitted, Under Review, Approved, Rejected, Completed, Archived
   - Each section can have custom statuses

3. **`section_submission_comments`** - Threaded comments
   - Internal notes (admin-only) or public comments
   - Supports parent_comment_id for threading

4. **`section_submission_attachments`** - File uploads
   - SHA-256 hash for duplicate detection
   - original_filename preserved for display

5. **`section_submission_history`** - Audit trail
   - Tracks status changes, assignments, priority changes
   - Severity levels (info/warning/critical)

6. **`tenants`** - Multi-tenant support (future-proofing)
   - Current: Woodson ISD (id=1)

### Section Configuration

The `sections` table now has a **`cc_prefix`** column:
```sql
cc_prefix VARCHAR(10) NULL COMMENT 'Display ID prefix (e.g., BR, VR, RR)'
```

This is set during package installation and used for display_id generation.

---

## 📦 What Packages Need to Do

### Minimum Requirements (Phase 1 - Current)

**Packages must create TWO records on submission:**

#### 1. Package's Own Table (existing)
```php
// Example: br_reports table
$reportId = $db->insert('br_reports', [
    'student_name' => $_POST['student_name'],
    'incident_date' => $_POST['incident_date'],
    'description' => $_POST['description'],
    'submitted_by' => $userId,
    'created_at' => date('Y-m-d H:i:s')
]);
```

#### 2. Command Center Index (NEW)
```php
// Create Command Center submission record
require_once __DIR__ . '/../../src/Submission.php';
$submission = new Hub\Submission();

$submissionId = $submission->create([
    'section_id' => $sectionId,         // From sections table
    'entity_type' => 'br_report',       // Package table name
    'entity_id' => $reportId,           // ID from step 1
    'submitted_by' => $userId,
    'title' => 'Bullying Report: ' . $_POST['student_name'], // Brief description
    'priority' => 'normal',             // low, normal, high, urgent
    'data_snapshot' => json_encode([    // Key fields for search/display
        'student_name' => $_POST['student_name'],
        'incident_date' => $_POST['incident_date'],
        'location' => $_POST['incident_location']
    ])
]);
```

**That's it!** Command Center will now:
- Show the submission in dashboard
- Display it in section list
- Allow comments, attachments, status changes
- Track full audit history

---

## 🔧 Package Manifest Updates (Phase 2 - Planned)

### New `command_center` Section

Add this to your package manifest.json:

```json
{
  "name": "bullying-report",
  "version": "1.0.0",
  
  "command_center": {
    "enabled": true,
    "display_id_prefix": "BR",
    "title": "Bullying Reports",
    "icon": "bi-shield-exclamation",
    
    "list_view": {
      "columns": [
        {"field": "display_id", "label": "ID", "sortable": true},
        {"field": "data_snapshot.student_name", "label": "Student", "sortable": true, "searchable": true},
        {"field": "data_snapshot.incident_date", "label": "Date", "sortable": true},
        {"field": "status_name", "label": "Status", "sortable": true},
        {"field": "priority", "label": "Priority", "sortable": true},
        {"field": "created_at", "label": "Submitted", "sortable": true}
      ],
      "filters": [
        {"field": "priority", "type": "select", "options": ["low", "normal", "high", "urgent"]},
        {"field": "status_id", "type": "select", "options": "dynamic"},
        {"field": "created_at", "type": "daterange"}
      ]
    },
    
    "detail_view": {
      "sections": [
        {
          "title": "Incident Details",
          "fields": ["incident_date", "incident_time", "incident_location", "description"]
        },
        {
          "title": "Student Information", 
          "fields": ["student_name", "student_grade", "student_id"]
        }
      ],
      "actions": [
        {"id": "approve", "label": "Approve", "requires_role": "admin", "new_status": "Approved"},
        {"id": "investigate", "label": "Start Investigation", "requires_role": "staff", "new_status": "Under Investigation"}
      ]
    },
    
    "notifications": {
      "on_submit": ["admin", "counselor"],
      "on_status_change": ["submitter", "assigned_user"]
    }
  }
}
```

### Migration Helper (CLI Tool)

We'll create `cli/pkg-add-command-center.php`:

```bash
# Add Command Center support to existing package
php cli/pkg-add-command-center.php packages/local/bullying-report/

# This will:
# 1. Add command_center section to manifest
# 2. Create Submission.create() calls in form handlers
# 3. Generate example list/detail views
# 4. Update package version (1.0.0 → 1.1.0)
```

---

## ✅ Testing Checklist

### For Package Developers

- [ ] Package installs successfully
- [ ] Section appears in Command Center dashboard
- [ ] Submit form via The Hub
- [ ] Submission appears in Command Center section list
- [ ] Click submission → detail view loads
- [ ] Package-specific data displays correctly
- [ ] Can add comments
- [ ] Can change status
- [ ] Can upload attachments
- [ ] History timeline shows all changes
- [ ] Display ID generates correctly (PREFIX-YEAR-###)
- [ ] Bulk actions work (multi-select, change status)
- [ ] Export to CSV/Excel works

### For Administrators

- [ ] Dashboard shows all sections with counts
- [ ] Section cards are clickable
- [ ] DataTables sorting works
- [ ] DataTables search works
- [ ] Filters work (status, priority, date range)
- [ ] Can assign submissions to users
- [ ] Email notifications send correctly
- [ ] Permissions are respected (section_access)

---

## 📝 Current Status

### ✅ Completed (Phase 1)
- Core database schema (6 tables)
- Migration script (cli/migrate-command-center.php)
- PHP classes (Submission.php, CommandCenter.php)
- Dashboard UI (stats, sections grid, activity feed)
- Section list view (DataTables, filters, bulk actions)
- Submission detail view (full CRUD, comments, attachments)
- API endpoints (submissions, comments)
- Navigation integration (admin sidebar + header)
- Context-aware navigation (shows Dashboard when in CC)
- Theme consistency (same header/footer as rest of site)

### ⏳ In Progress (Phase 2)
- Package manifest specification updates
- Bullying Report package integration (test case)
- CLI migration helper for existing packages
- Documentation updates

### 📅 Planned (Phase 3)
- Analytics dashboard
- Bulk export interface
- Advanced search (cross-section)
- Custom status workflows per section
- Email notification templates
- Mobile responsive views

---

## 🚀 Next Steps

### 1. Update Package Specification
Add Command Center section to `PACKAGE_SPECIFICATION_V2.md`:
- Required fields
- Optional configurations
- Examples
- Migration path for existing packages

### 2. Test with Bullying Report
Update Bullying Report package to:
- Add `command_center` section to manifest
- Create `Submission::create()` call in form handler
- Set `cc_prefix = 'BR'` in sections table
- Test full workflow

### 3. Create CLI Helper
Build `cli/pkg-add-command-center.php` to automate:
- Manifest updates
- Code generation
- Version bumping

### 4. Document Migration Path
Guide for existing package maintainers:
- Backward compatibility considerations
- Gradual rollout strategy
- Testing procedures

---

## 💡 Design Decisions

### Why Separate Tables?
**Decision:** Use `section_submissions` as master index + package-specific tables  
**Rationale:**
- Packages own their data structures
- Command Center doesn't need to know schema details
- Easy to add CC support to existing packages
- Can link to ANY entity (not just package tables)

### Why JSON data_snapshot?
**Decision:** Store key fields in JSON for search/display  
**Rationale:**
- No need to JOIN package tables for list views
- Faster queries (indexed JSON in MySQL 5.7+)
- Flexible schema per package
- Full data still in package table (snapshot is cache)

### Why entity_type + entity_id?
**Decision:** Polymorphic relationship to package data  
**Rationale:**
- Packages can have multiple entity types (reports, requests, etc.)
- Flexible linking without schema changes
- Supports future non-package entities

---

## 📞 Questions?

Contact the Command Center dev team or see:
- `/docs/COMMAND_CENTER_ARCHITECTURE.md` - Full technical spec
- `/docs/PACKAGE_SPECIFICATION_V2.md` - Package requirements
- `/cli/migrate-command-center.php` - Database schema source

**Let's get those packages integrated!** 🎉
