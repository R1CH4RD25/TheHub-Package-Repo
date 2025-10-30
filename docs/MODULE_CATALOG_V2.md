# The Hub Module Catalog v2.1

**Authoritative reference for all module types in The Hub ecosystem**

> **Last Updated**: October 30, 2025  
> **Specification Version**: 2.1  
> **Compatible with**: Hub v1.2+

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Base Module Rules](#base-module-rules)
3. [Core Modules](#core-modules)
4. [Specialized Modules](#specialized-modules)
5. [Education-Specific Modules](#education-specific-modules)
6. [Integration Modules](#integration-modules)
7. [Hub Infrastructure Hooks](#hub-infrastructure-hooks)
8. [Module Examples](#module-examples)

---

## 🧭 Overview

**Modules** are the functional units of a package — reusable, configurable components that define what a package does. Each module is represented by a JSON configuration declared in the package manifest.

A package can contain multiple modules, e.g.:

```
modules/
├── report-form.module.json          # Data entry
├── reports-table.module.json        # Display records
├── remediation-workflow.module.json # State machine
├── analytics-dashboard.module.json  # Charts
└── email-notification.module.json   # Automated emails
```

### Architecture Integration

All modules integrate with The Hub's core infrastructure:

- **Database**: Via `Hub\Database` singleton
- **Authentication**: Via `Hub\Auth` (getCurrentUser, hasRole, etc.)
- **Audit Logging**: All actions automatically logged via `Hub\AuditLogger`
- **Permissions**: Via `Hub\Module::hasAccess()` and `SectionRoleAccess`
- **Theme**: CSS variables from `public/assets/css/` automatically applied
- **Sessions**: PHP sessions managed by `src/bootstrap.php`
- **CSRF Protection**: `verifyCsrfToken()` on all POST/PUT/DELETE
- **Multi-tenancy**: `tenant_id` column enforced on all entities

---

## ⚙️ Base Module Rules (applies to all)

| Rule ID | Rule | Description |
|---------|------|-------------|
| **[MOD-R01]** | `type` required | Must match one of the approved types below |
| **[MOD-R02]** | `slug` required | Unique within package, **kebab-case** only |
| **[MOD-R03]** | `displayName` required | Human-readable title (max 80 chars) |
| **[MOD-R04]** | `entity` optional | References database entity (if data-bound) |
| **[MOD-R05]** | `route` required | `/pkg/<namespace>/<slug>` pattern |
| **[MOD-R06]** | `icon` optional | Bootstrap Icons or FontAwesome key |
| **[MOD-R07]** | `access` optional | Array of permission keys (declared in manifest) |
| **[MOD-R08]** | `layout` optional | `{ columns: 1-4, responsiveBreakpoints: [] }` |
| **[MOD-R09]** | `a11y` optional | Accessibility metadata (aria labels, shortcuts) |
| **[MOD-R10]** | `validation` optional | Standardized structure (see below) |
| **[MOD-R11]** | `audit` optional | Custom audit event types (logged to `audit_log`) |

### Hub Infrastructure Hooks

Every module automatically has access to:

```php
// Database
$db = Database::getInstance();
$records = $db->fetchAll("SELECT * FROM {$entity} WHERE tenant_id = ?", [$tenantId]);

// Authentication
$user = Auth::getCurrentUser();
$hasAccess = Auth::hasRole('counselor');

// Audit Logging
$auditLogger = new AuditLogger();
$auditLogger->log('record_created', 'br_reports', $recordId, null, $newData, $userId);

// Permissions
$canAccess = Module::hasAccess($userId, $moduleSlug, 'staff');

// CSRF Protection (automatic on all forms)
verifyCsrfToken($_POST['csrf_token']);
```

---

## 🧾 1. Form Module (`type: "Form"`)

**Purpose**: Capture and validate user input with full Hub integration.

**Examples**: Bullying Report, Employee Evaluation, Maintenance Request, Leave Request

### Required Fields

```json
{
  "type": "Form",
  "slug": "report-form",
  "displayName": "Submit Report",
  "entity": "br_report",
  "route": "/pkg/br/report-form",
  "fields": [
    {
      "key": "incident_date",
      "fieldType": "date",
      "label": "Incident Date",
      "required": true
    }
  ],
  "onSubmit": {
    "redirect": "/pkg/br/confirmation",
    "notify": ["counselor", "principal"],
    "auditAction": "incident_reported"
  }
}
```

### Optional Fields

| Field | Type | Description | Hub Integration |
|-------|------|-------------|-----------------|
| `allowAnonymous` | boolean | Permit submissions without login | Uses session-based tracking |
| `prefill` | object | Map default values from user profile | Via `Auth::getCurrentUser()` |
| `rateLimit` | object | `{ perUser: 10, perMinute: 5 }` | Enforced via session/IP checks |
| `emailOnSubmit` | object | Auto-send email notifications | Uses `PHPMailer` configured in `.env` |
| `webhookOnSubmit` | string | POST to external URL | Signed with `WEBHOOK_SECRET` |
| `captcha` | boolean | Show Google reCAPTCHA | Uses `RECAPTCHA_SITE_KEY` from `.env` |

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[FRM-R01]** | Each `field.key` must map to DB column | Validated against `section_field_definitions` |
| **[FRM-R02]** | Anonymous forms must omit PII unless consented | `pii: true` fields hidden if anonymous |
| **[FRM-R03]** | Must include anti-spam measure | reCAPTCHA or honeypot field |
| **[FRM-R04]** | Validation must define `required`, `maxLength` | Enforced client + server side |
| **[FRM-R05]** | `onSubmit.redirect` must be within `/pkg/` namespace | Security check in router |
| **[FRM-R06]** | CSRF token required on all submissions | `verifyCsrfToken()` in `src/helpers.php` |
| **[FRM-R07]** | All submissions logged to `audit_log` | `AuditLogger::log('form_submit', ...)` |
| **[FRM-R08]** | Rate limiting enforced per user/IP | Checked before form processing |

### Email Notification Example

```json
{
  "emailOnSubmit": {
    "enabled": true,
    "recipients": [
      {"type": "role", "value": "counselor"},
      {"type": "email", "value": "principal@school.com"},
      {"type": "field", "value": "supervisor_email"}
    ],
    "template": "incident-reported",
    "subject": "New Incident Report: {incident_location}",
    "includeFields": ["incident_date", "incident_location", "description"],
    "attachPDF": true
  }
}
```

**Hub Integration**: Uses `PHPMailer` with SMTP settings from `.env`:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=noreply@woodsonisd.net
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=noreply@woodsonisd.net
SMTP_FROM_NAME=The Hub - Woodson ISD
```

---

## 📋 2. TableView Module (`type: "TableView"`)

**Purpose**: Display, search, and filter records with Hub's permission system.

**Examples**: Incident List, Expense Records, User Directory, Employee Evaluations List

### Required

```json
{
  "type": "TableView",
  "slug": "reports-table",
  "displayName": "View Reports",
  "entity": "br_report",
  "route": "/pkg/br/reports",
  "columns": [
    {"key": "incident_date", "label": "Date", "sortable": true},
    {"key": "status", "label": "Status", "badge": true}
  ],
  "pagination": {
    "enabled": true,
    "perPage": 25,
    "options": [10, 25, 50, 100]
  }
}
```

### Optional

| Field | Type | Hub Integration |
|-------|------|-----------------|
| `filters[]` | array | Server-side WHERE clauses |
| `actions[]` | array | Permission-checked via `Module::hasAccess()` |
| `defaultSort` | object | Applied to initial query |
| `export` | object | `{ csv: true, pdf: true, xlsx: true }` via PHPSpreadsheet |
| `bulkActions` | array | Multi-select with CSRF protection |
| `rowColors` | object | Conditional formatting based on status |

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[TBL-R01]** | At least one column must be sortable | Enforced by validator |
| **[TBL-R02]** | Actions must declare permission key | `Module::hasAccess($userId, $slug, $permission)` |
| **[TBL-R03]** | Export formats respect field-level PII flags | Fields with `pii: true` masked/excluded |
| **[TBL-R04]** | Pagination defaults to 25 per page | Configurable in manifest |
| **[TBL-R05]** | Must handle empty state gracefully | Default "No records" message |
| **[TBL-R06]** | All queries filtered by `tenant_id` | Automatic multi-tenancy enforcement |
| **[TBL-R07]** | View access logged to audit | `AuditLogger::log('records_viewed', ...)` |

### Export Example

```json
{
  "export": {
    "formats": ["csv", "xlsx", "pdf"],
    "filename": "incident-reports-{date}.{ext}",
    "includeColumns": ["incident_date", "location", "status"],
    "excludePII": true,
    "permission": "br_admin",
    "auditAction": "reports_exported"
  }
}
```

**Hub Integration**: Uses `PhpOffice\PhpSpreadsheet` (already in `composer.json`), logs export to `audit_log`.

---

## 🔄 3. Workflow Module (`type: "Workflow"`)

**Purpose**: Guide items through states with role-based transitions and audit trail.

**Examples**: Report Review, Employee Evaluation Approval, Purchase Order Approval

### Required

```json
{
  "type": "Workflow",
  "slug": "review-workflow",
  "displayName": "Review Process",
  "entity": "br_report",
  "statusField": "status",
  "steps": [
    {
      "id": "submitted",
      "label": "Submitted",
      "nextSteps": ["reviewing"],
      "requiredRole": null,
      "color": "info"
    },
    {
      "id": "reviewing",
      "label": "Under Review",
      "nextSteps": ["action_taken", "rejected"],
      "requiredRole": "br_manage",
      "requiredFields": ["reviewer_notes"],
      "emailOnEnter": {
        "recipients": [{"type": "role", "value": "counselor"}],
        "template": "review-started"
      }
    }
  ]
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[WF-R01]** | Each step must have unique `id` | Validated by pkg-lint |
| **[WF-R02]** | Must define at least one transition path | Graph validation |
| **[WF-R03]** | Each step must include `requiredRole` or null | Checked via `Auth::hasRole()` |
| **[WF-R04]** | Transitions emit audit events automatically | `AuditLogger::log('workflow_transition', ...)` |
| **[WF-R05]** | Steps cannot skip unless explicitly in `nextSteps[]` | Enforced by workflow engine |
| **[WF-R06]** | All transitions log timestamp and user | `created_by` and `created_at` recorded |
| **[WF-R07]** | Status field must be ENUM or VARCHAR in DB | Validated against schema |
| **[WF-R08]** | Email notifications use Hub's mail config | PHPMailer via SMTP settings |

### Email on State Change

```json
{
  "emailOnEnter": {
    "recipients": [
      {"type": "role", "value": "counselor"},
      {"type": "field", "value": "assigned_to_email"}
    ],
    "subject": "Review Required: {entity_title}",
    "template": "review-notification",
    "includeLink": true,
    "priority": "normal"
  }
}
```

---

## 📊 4. Analytics Module (`type: "Analytics"`)

**Purpose**: Visualize metrics and trends from entity data.

**Examples**: Reports by Month, Student Incident Dashboard, Expense Chart

### Required

```json
{
  "type": "Analytics",
  "slug": "incident-analytics",
  "displayName": "Incident Trends",
  "entity": "br_report",
  "charts": [
    {
      "type": "line",
      "title": "Incidents Over Time",
      "xAxis": "incident_date",
      "yAxis": "COUNT(*)",
      "groupBy": "MONTH"
    }
  ]
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[ANL-R01]** | Must use host chart components | Chart.js (already included) |
| **[ANL-R02]** | Queries pass through Hub Data API | No raw SQL from packages |
| **[ANL-R03]** | Must handle no-data gracefully | Default "No data available" message |
| **[ANL-R04]** | Must include at least one visualization | Validated by pkg-lint |
| **[ANL-R05]** | PII fields must be aggregated/anonymized | `pii: true` fields excluded |
| **[ANL-R06]** | Cache results for 15 minutes | Redis or file cache |
| **[ANL-R07]** | All queries filtered by `tenant_id` | Automatic multi-tenancy |

**Hub Integration**: Uses Chart.js (included in layout), queries filtered through `Database` class with automatic tenant_id scoping.

---

## 💌 5. Email Notification Module (`type: "EmailNotification"`)

**Purpose**: Automated email delivery with templates and role-based recipients.

**Examples**: "Report Submitted", "Evaluation Completed", "Review Overdue Reminder"

### Required

```json
{
  "type": "EmailNotification",
  "slug": "report-notification",
  "displayName": "Report Submitted Email",
  "triggers": [
    {
      "event": "record_created",
      "entity": "br_report",
      "conditions": {
        "status": "submitted"
      }
    }
  ],
  "recipients": [
    {"type": "role", "value": "counselor"},
    {"type": "field", "value": "assigned_to_email"},
    {"type": "email", "value": "principal@school.com"}
  ],
  "template": {
    "subject": "New Incident Report: {incident_location}",
    "body": "templates/email-report-submitted.html",
    "plainText": "templates/email-report-submitted.txt"
  }
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[NTF-R01]** | Each trigger must map to valid audit event | Checked against `AuditLogger` events |
| **[NTF-R02]** | No plain-text PII in outbound messages | PII fields masked with `***` |
| **[NTF-R03]** | Email templates use approved placeholders only | Sanitized via template engine |
| **[NTF-R04]** | All emails logged to `audit_log` | `AuditLogger::log('email_sent', ...)` |
| **[NTF-R05]** | Use SMTP settings from `.env` | PHPMailer configuration |
| **[NTF-R06]** | Respect user notification preferences | Check `users.notification_preferences` |
| **[NTF-R07]** | Rate limit: max 100 emails/hour per package | Enforced by notification service |

### Email Template Placeholders

Available placeholders (auto-replaced):

```
{user.name}              - Current user's name
{user.email}             - Current user's email
{record.field_name}      - Any field from the entity
{date}                   - Current date
{time}                   - Current time
{hub.url}                - Hub base URL
{hub.name}               - Hub instance name
{link}                   - Deep link to record
{tenant.name}            - Tenant/organization name
```

**Hub Integration**: 
- Uses `PHPMailer` configured in `src/bootstrap.php`
- Templates stored in `packages/<namespace>/templates/`
- Sends via SMTP (Gmail, Office365, etc.) configured in `.env`
- Automatically includes Hub logo and footer

---

## 📄 6. PDF Generation Module (`type: "PDFGenerator"`)

**Purpose**: Generate formatted PDF documents from records.

**Examples**: Employee Evaluation Report, Incident Summary, Certificate

### Required

```json
{
  "type": "PDFGenerator",
  "slug": "evaluation-pdf",
  "displayName": "Evaluation Report PDF",
  "entity": "emp_evaluation",
  "template": "templates/evaluation-report.html",
  "filename": "evaluation-{employee_name}-{date}.pdf",
  "header": {
    "logo": true,
    "title": "Employee Evaluation Report",
    "date": true
  },
  "footer": {
    "pageNumbers": true,
    "confidential": true,
    "text": "Woodson ISD - Confidential"
  }
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[PDF-R01]** | Must use approved PDF library | mPDF or TCPDF (add to composer) |
| **[PDF-R02]** | Templates must be HTML-based | No PHP execution |
| **[PDF-R03]** | Include generation timestamp | Automatic in footer |
| **[PDF-R04]** | Respect `pii: true` field flags | Conditional rendering |
| **[PDF-R05]** | Store generated PDFs with tenant isolation | `/uploads/pdf/{tenant_id}/{file}` |
| **[PDF-R06]** | Log generation to audit | `AuditLogger::log('pdf_generated', ...)` |
| **[PDF-R07]** | Delete generated files after 30 days | Cron job cleanup |

**Hub Integration**:
- Install: `composer require mpdf/mpdf`
- Store in `/uploads/pdf/{tenant_id}/`
- Serve with expiring signed URLs
- Include Hub branding automatically

---

## 🎓 7. Education-Specific: Student Evaluation Module (`type: "StudentEvaluation"`)

**Purpose**: Teacher evaluations, progress reports, behavior assessments.

### Required

```json
{
  "type": "StudentEvaluation",
  "slug": "student-evaluation",
  "displayName": "Student Progress Evaluation",
  "entity": "edu_student_evaluation",
  "gradingScale": {
    "type": "letter",
    "values": ["A", "B", "C", "D", "F"],
    "gpa": true
  },
  "categories": [
    {"key": "academic", "label": "Academic Performance", "weight": 0.6},
    {"key": "behavior", "label": "Behavior", "weight": 0.2},
    {"key": "participation", "label": "Class Participation", "weight": 0.2}
  ],
  "parentNotification": {
    "enabled": true,
    "method": "email",
    "template": "student-evaluation-parent"
  }
}
```

### Hub Integration

- Integrates with existing student roster (if available)
- Auto-calculates weighted grades
- Sends parent notifications via email
- Tracks evaluation history over semesters
- Export to transcript-friendly formats

---

## 👔 8. HR-Specific: Employee Evaluation Module (`type: "EmployeeEvaluation"`)

**Purpose**: Staff performance reviews, peer evaluations, supervisor assessments.

### Full Example

```json
{
  "type": "EmployeeEvaluation",
  "slug": "employee-evaluation",
  "displayName": "Employee Performance Evaluation",
  "entity": "hr_employee_evaluation",
  "route": "/pkg/hr/evaluation",
  "evaluationPeriod": {
    "frequency": "annual",
    "midYearReview": true,
    "fiscalYearStart": "09-01"
  },
  "sections": [
    {
      "key": "performance",
      "label": "Job Performance",
      "weight": 0.40,
      "criteria": [
        {"key": "quality", "label": "Quality of Work", "scale": "1-5"},
        {"key": "efficiency", "label": "Efficiency", "scale": "1-5"},
        {"key": "initiative", "label": "Initiative", "scale": "1-5"}
      ]
    },
    {
      "key": "professional",
      "label": "Professionalism",
      "weight": 0.30,
      "criteria": [
        {"key": "punctuality", "label": "Attendance & Punctuality", "scale": "1-5"},
        {"key": "teamwork", "label": "Teamwork", "scale": "1-5"},
        {"key": "communication", "label": "Communication", "scale": "1-5"}
      ]
    },
    {
      "key": "goals",
      "label": "Goal Achievement",
      "weight": 0.30,
      "freeform": true
    }
  ],
  "workflow": {
    "steps": [
      {
        "id": "draft",
        "label": "Draft",
        "assignedTo": "supervisor",
        "nextSteps": ["submitted"]
      },
      {
        "id": "submitted",
        "label": "Submitted",
        "assignedTo": "supervisor",
        "nextSteps": ["reviewed"],
        "emailNotification": {
          "recipients": [{"type": "field", "value": "employee_email"}],
          "subject": "Your Performance Evaluation is Ready for Review",
          "attachPDF": true
        }
      },
      {
        "id": "reviewed",
        "label": "Employee Reviewed",
        "assignedTo": "employee",
        "nextSteps": ["acknowledged"],
        "allowComments": true
      },
      {
        "id": "acknowledged",
        "label": "Acknowledged",
        "nextSteps": ["approved"],
        "requireSignature": true
      },
      {
        "id": "approved",
        "label": "Approved & Finalized",
        "assignedTo": "hr_admin",
        "finalPDF": true,
        "emailNotification": {
          "recipients": [
            {"type": "field", "value": "employee_email"},
            {"type": "field", "value": "supervisor_email"}
          ],
          "subject": "Final Evaluation Report",
          "attachPDF": true,
          "includeFields": ["overall_rating", "supervisor_comments", "employee_comments"]
        }
      }
    ]
  },
  "emailSettings": {
    "employeeCanReceive": true,
    "adminCanChoose": true,
    "autoSendOnFinalize": false,
    "includeAllFields": false,
    "selectableFields": [
      "overall_rating",
      "performance_score",
      "professional_score",
      "goals_score",
      "supervisor_comments",
      "employee_comments",
      "improvement_plan",
      "next_review_date"
    ]
  },
  "scoring": {
    "method": "weighted_average",
    "scale": "1-5",
    "ratingLabels": {
      "1": "Needs Improvement",
      "2": "Below Expectations",
      "3": "Meets Expectations",
      "4": "Exceeds Expectations",
      "5": "Outstanding"
    },
    "passingScore": 3.0
  },
  "pdf": {
    "template": "templates/employee-evaluation.html",
    "header": {
      "logo": true,
      "title": "Employee Performance Evaluation",
      "confidential": true
    },
    "sections": [
      "employee_info",
      "evaluation_period",
      "performance_scores",
      "supervisor_comments",
      "employee_comments",
      "signatures",
      "next_steps"
    ],
    "footer": {
      "pageNumbers": true,
      "confidential": true,
      "date": true
    }
  },
  "permissions": {
    "create": ["hr_admin", "supervisor"],
    "view_own": ["employee"],
    "view_all": ["hr_admin", "superintendent"],
    "edit": ["hr_admin", "supervisor"],
    "finalize": ["hr_admin"]
  }
}
```

### Key Features for Employee Evaluation

1. ✅ Weighted scoring system (customizable criteria)
2. ✅ Multi-step workflow (Draft → Submit → Review → Acknowledge → Finalize)
3. ✅ Email notifications at each step
4. ✅ **Admin can choose which fields to send to employee**
5. ✅ PDF generation with signatures
6. ✅ Comments section for supervisor and employee
7. ✅ Historical tracking (view past evaluations)
8. ✅ File attachments (supporting documents)
9. ✅ Audit trail (every action logged)
10. ✅ Digital signatures with timestamp

### Hub Integration

```php
// Auto-send email when admin finalizes
if ($admin_chooses_to_send_email) {
    $emailService = new EmailService();
    $selectedFields = $_POST['selected_fields']; // From admin UI
    
    $emailService->send([
        'to' => $employee->email,
        'subject' => 'Your Performance Evaluation Report',
        'template' => 'employee-evaluation-final',
        'data' => $evaluation->only($selectedFields),
        'attachments' => [
            'evaluation-report.pdf' => $pdfGenerator->generate($evaluation)
        ]
    ]);
    
    // Log to audit
    $auditLogger->log('evaluation_emailed', 'hr_employee_evaluation', 
        $evaluation->id, null, ['fields_sent' => $selectedFields], $admin->id);
}
```

---

## 🔔 9. Action Module (`type: "Action"`)

**Purpose**: Perform single or bulk operations on records.

**Examples**: Mark as Closed, Approve Request, Assign Owner, Bulk Archive

### Required

```json
{
  "type": "Action",
  "slug": "mark-closed",
  "displayName": "Mark as Closed",
  "entity": "br_report",
  "operation": "update",
  "fields": {
    "status": "closed",
    "closed_at": "{now}",
    "closed_by": "{current_user_id}"
  },
  "confirmation": {
    "enabled": true,
    "message": "Are you sure you want to close this report?"
  },
  "permission": "br_manage",
  "audit": {
    "action": "report_closed",
    "logFields": ["status", "closed_by", "closed_at"]
  }
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[ACT-R01]** | Must declare permission required | `Module::hasAccess()` check |
| **[ACT-R02]** | Actions must log in audit trail | `AuditLogger::log()` automatic |
| **[ACT-R03]** | Should support bulk and single modes | Checkbox selection in TableView |
| **[ACT-R04]** | Destructive actions require confirmation | Modal dialog shown |
| **[ACT-R05]** | Return consistent JSON response | `{success: true/false, message: ""}` |
| **[ACT-R06]** | CSRF token required | Verified before execution |
| **[ACT-R07]** | Rate limit: max 100 actions/minute per user | Session-based throttle |

---

## 🗂️ 10. File Manager Module (`type: "FileManager"`)

**Purpose**: Manage uploads, attachments, or package-specific documents.

**Examples**: Student Documents, Well Logs, Staff Certifications, Evaluation Attachments

### Required

```json
{
  "type": "FileManager",
  "slug": "evaluation-attachments",
  "displayName": "Evaluation Attachments",
  "entity": "hr_evaluation_attachment",
  "storage": {
    "provider": "local",
    "path": "/uploads/{tenant_id}/evaluations/{record_id}/",
    "maxFileSize": 10485760,
    "allowedExtensions": ["pdf", "doc", "docx", "jpg", "png"],
    "allowedMimeTypes": [
      "application/pdf",
      "application/msword",
      "image/jpeg",
      "image/png"
    ]
  },
  "virus_scan": true,
  "permissions": {
    "upload": ["hr_admin", "supervisor"],
    "view": ["hr_admin", "supervisor", "employee"],
    "delete": ["hr_admin"]
  }
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[FIL-R01]** | Must define storage provider | local, s3 (future) |
| **[FIL-R02]** | Must enforce `maxFileSize` | PHP `upload_max_filesize` |
| **[FIL-R03]** | All uploads scanned for malware | ClamAV integration (optional) |
| **[FIL-R04]** | Files stored with tenant isolation | `/uploads/{tenant_id}/` |
| **[FIL-R05]** | No public URLs without expiring tokens | Signed URLs with 1-hour expiry |
| **[FIL-R06]** | Log all file operations | `AuditLogger::log('file_uploaded', ...)` |
| **[FIL-R07]** | Auto-delete orphaned files after 90 days | Cron job cleanup |

**Hub Integration**:
- Store in `/var/www/woodson/thehub/uploads/{tenant_id}/`
- Serve via `public/api/files.php?id={file_id}&token={signed_token}`
- Generate thumbnails for images automatically
- Track file metadata in `section_record_attachments` table

---

## 🧮 11. Calculation Module (`type: "Computation"`)

**Purpose**: Run derived field logic, scoring, or data transformations.

**Examples**: GPA Calculator, Risk Assessment Score, Evaluation Average

### Required

```json
{
  "type": "Computation",
  "slug": "evaluation-overall-score",
  "displayName": "Overall Evaluation Score",
  "entity": "hr_employee_evaluation",
  "resultField": "overall_score",
  "formula": {
    "expression": "(performance * 0.4) + (professional * 0.3) + (goals * 0.3)",
    "dependsOn": ["performance", "professional", "goals"],
    "precision": 2,
    "round": "half_up"
  },
  "triggers": ["field_change", "manual"],
  "validation": {
    "min": 1.0,
    "max": 5.0
  }
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[CAL-R01]** | Must define formula in safe expression syntax | No `eval()`, use math parser |
| **[CAL-R02]** | All dependencies declared in `dependsOn[]` | Validated against fields |
| **[CAL-R03]** | Result fields must be read-only | Cannot be manually edited |
| **[CAL-R04]** | Define precision and rounding | PHP `round()` with specified mode |
| **[CAL-R05]** | Host validates expression tree before runtime | AST parsing, no dangerous functions |
| **[CAL-R06]** | Recalculate on dependency change | Automatic trigger |
| **[CAL-R07]** | Log calculation errors | `AuditLogger::log('calculation_error', ...)` |

**Hub Integration**:
- Use `MathParser` library (add to composer)
- Calculations run server-side only
- Results cached until dependencies change
- Audit trail for every calculation

---

## 📊 12. Dashboard Module (`type: "Dashboard"`)

**Purpose**: Combine multiple modules into a single interface.

**Examples**: Admin Overview, Supervisor Dashboard, HR Analytics

### Required

```json
{
  "type": "Dashboard",
  "slug": "admin-dashboard",
  "displayName": "Administrative Dashboard",
  "widgets": [
    {
      "module": "reports-table",
      "size": "full",
      "title": "Recent Reports",
      "filter": {"status": "submitted"},
      "limit": 10
    },
    {
      "module": "incident-analytics",
      "size": "half",
      "title": "Incident Trends"
    },
    {
      "module": "pending-actions",
      "size": "half",
      "title": "Pending Reviews"
    }
  ],
  "layout": "responsive",
  "refreshInterval": 300
}
```

### Rules

| Rule ID | Rule | Hub Implementation |
|---------|------|-------------------|
| **[DSH-R01]** | Must reference existing modules in same package | Validated by pkg-lint |
| **[DSH-R02]** | Cannot modify child module configuration | Read-only embedding |
| **[DSH-R03]** | Supports up to 8 widgets per dashboard | UI performance limit |
| **[DSH-R04]** | Must support responsive reflow | 2-col → 1-col on mobile |
| **[DSH-R05]** | No data sources allowed directly | Only embedded modules |
| **[DSH-R06]** | Respect individual widget permissions | Check access per widget |

---

## ✅ Module Type Summary

| Type | Primary Function | Example | Entity Required? | Hub Integration |
|------|------------------|---------|------------------|-----------------|
| **Form** | Input data | Bullying report | ✅ | Database, Auth, CSRF, Audit |
| **TableView** | Display/filter | Reports table | ✅ | Database, Permissions, Export |
| **Workflow** | Review process | Report review | ✅ | Auth, Audit, Email |
| **Analytics** | Visualization | Trend graphs | ✅ | Database, Chart.js, Cache |
| **EmailNotification** | Event alerts | Report submitted | ⚙️ | PHPMailer, AuditLogger |
| **PDFGenerator** | Document export | Evaluation report | ✅ | mPDF, FileStorage |
| **StudentEvaluation** | Student assessment | Progress report | ✅ | Email, PDF, GPA calc |
| **EmployeeEvaluation** | Staff review | Performance review | ✅ | Workflow, Email, PDF, Signatures |
| **Action** | Operations | Mark closed | ✅ | Permissions, Audit, CSRF |
| **FileManager** | File storage | Attachments | ✅ | FileSystem, Virus scan, Signed URLs |
| **Computation** | Formula logic | Score average | ✅ | MathParser, Cache |
| **Dashboard** | Composite view | Admin overview | ⚙️ | Multi-module aggregation |

---

## 🔗 Hub Infrastructure Integration Points

Every module automatically integrates with:

### 1. Database Layer
```php
$db = Database::getInstance();
// All queries automatically filtered by tenant_id
// Prepared statements enforced
// Connection pooling managed
```

### 2. Authentication & Authorization
```php
$user = Auth::getCurrentUser();
$hasAccess = Auth::hasRole('counselor');
$canAccessModule = Module::hasAccess($userId, $moduleSlug, 'staff');
```

### 3. Audit Logging
```php
$auditLogger = new AuditLogger();
// Automatically logs: user_id, ip_address, user_agent, timestamp
// All form submissions, record changes, file operations tracked
```

### 4. Email Service
```php
// Uses PHPMailer with SMTP configuration from .env
// Supports: Gmail, Office365, custom SMTP
// Templates stored in packages/{namespace}/templates/
```

### 5. File Storage
```php
// Organized: /uploads/{tenant_id}/{package_namespace}/{record_id}/
// Signed URLs with expiry
// Automatic thumbnail generation for images
```

### 6. Permission System
```php
// Package roles defined in manifest
// Mapped to system roles (staff, admin, super_admin)
// Checked on every route, action, file access
```

### 7. CSRF Protection
```php
// Automatic token generation in forms
// verifyCsrfToken() called on all POST/PUT/DELETE
// Tokens tied to session, expire in 2 hours
```

### 8. Multi-Tenancy
```php
// tenant_id column enforced on all tables
// Queries auto-filtered by current tenant
// Data isolation at database level
```

---

## 📝 Complete Example: Employee Evaluation Package

**Package Structure**:
```
packages/local/employee-evaluation/
├── manifest.json
├── modules/
│   ├── evaluation-form.module.json
│   ├── evaluations-table.module.json
│   ├── evaluation-workflow.module.json
│   ├── evaluation-pdf.module.json
│   └── email-notification.module.json
├── templates/
│   ├── employee-evaluation.html
│   ├── email-evaluation-ready.html
│   └── email-evaluation-final.html
└── README.md
```

**Key Features**:
1. ✅ Weighted scoring system (customizable criteria)
2. ✅ Multi-step workflow (Draft → Submit → Review → Acknowledge → Finalize)
3. ✅ Email notifications at each step
4. ✅ **Admin can choose which fields to send to employee**
5. ✅ PDF generation with signatures
6. ✅ Comments section for supervisor and employee
7. ✅ Historical tracking (view past evaluations)
8. ✅ File attachments (supporting documents)
9. ✅ Audit trail (every action logged)
10. ✅ Digital signatures with timestamp

**Email Field Selection (Admin UI)**:
```html
<form id="finalize-evaluation">
  <h3>Finalize & Send to Employee</h3>
  
  <div class="field-selection">
    <label><input type="checkbox" name="fields[]" value="overall_rating"> Overall Rating</label>
    <label><input type="checkbox" name="fields[]" value="performance_score"> Performance Score</label>
    <label><input type="checkbox" name="fields[]" value="supervisor_comments" checked> Supervisor Comments</label>
    <label><input type="checkbox" name="fields[]" value="improvement_plan"> Improvement Plan</label>
    <label><input type="checkbox" name="fields[]" value="next_review_date" checked> Next Review Date</label>
  </div>
  
  <label>
    <input type="checkbox" name="send_email" checked>
    Send email notification to employee
  </label>
  
  <label>
    <input type="checkbox" name="attach_pdf" checked>
    Attach PDF report to email
  </label>
  
  <button type="submit">Finalize Evaluation</button>
</form>
```

This module catalog provides everything you need to build comprehensive packages that fully leverage The Hub's infrastructure!