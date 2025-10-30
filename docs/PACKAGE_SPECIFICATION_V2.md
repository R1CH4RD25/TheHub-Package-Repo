# The Hub Package Specification v2.0

> **Unified standard** for creating, validating, and distributing packages in The Hub ecosystem. This document combines development rules, technical requirements, and submission guidelines into one authoritative source.

---

## Table of Contents

1. [Purpose & Philosophy](#1-purpose--philosophy)
2. [Package Overview](#2-package-overview)
3. [Naming Conventions](#3-naming-conventions)
4. [Directory Structure](#4-directory-structure)
5. [Manifest Schema](#5-manifest-schema)
6. [Database Conventions](#6-database-conventions)
7. [Module Types](#7-module-types)
8. [Field Types & Validation](#8-field-types--validation)
9. [Permissions & RBAC](#9-permissions--rbac)
10. [Security Requirements](#10-security-requirements)
11. [Documentation Standards](#11-documentation-standards)
12. [Testing Requirements](#12-testing-requirements)
13. [Submission Process](#13-submission-process)
14. [Review & Approval](#14-review--approval)
15. [Versioning & Updates](#15-versioning--updates)

---

## 1. Purpose & Philosophy

The Hub is designed to be an **extensible, secure, and maintainable** platform where:

- **Developers** can create custom packages without modifying core code
- **Administrators** can install packages with confidence in security and compatibility
- **End users** experience consistent UI/UX across all packages
- **Themes** work universally without per-package CSS

### Core Principles

1. **🔒 Security First**: All inputs validated, no code injection, prepared statements only
2. **🎨 Theme Compliance**: Use CSS variables, no custom stylesheets
3. **📦 Self-Contained**: Packages include everything needed (schema, migrations, assets)
4. **🔄 Version Safe**: Semantic versioning with upgrade/rollback paths
5. **📚 Well-Documented**: README, CHANGELOG, field definitions required

---

## 2. Package Overview

### What is a Package?

A **package (.hubpkg)** is a JSON file containing:
- Metadata (name, version, author)
- Database schema definitions
- Module configurations (forms, tables, workflows)
- Field definitions with validation rules
- Permission mappings
- Migration scripts (for updates)

### What are Modules?

**Modules** are functional UI components within a package:

| Module Type | Purpose | Example |
|-------------|---------|---------|
| **Form** | Data entry interface | Bullying report submission form |
| **TableView** | Display & filter records | List all reports with search |
| **Workflow** | Multi-step processes | Report → Review → Action → Close |
| **Analytics** | Charts & metrics | Monthly incident trends |

### Package Lifecycle

```
CREATE → VALIDATE → BUILD → TEST → SUBMIT → REVIEW → APPROVE → PUBLISH
   ↓         ↓         ↓       ↓       ↓        ↓         ↓        ↓
 Source   pkg-lint  .hubpkg  Local  PR/Issue  Checks   Merge   Registry
```

---

## 3. Naming Conventions

### 3.1 Package Names

**Format**: `kebab-case` (lowercase, hyphen-separated)

✅ **Valid Examples**:
- `bullying-report`
- `employee-evaluation`
- `maintenance-request`
- `student-pass-system`

❌ **Invalid Examples**:
- `BullyingReport` (camelCase not allowed)
- `bullying_report` (underscores not allowed)
- `report` (too generic)
- `BR` (abbreviations not allowed)

**Rules**:
- Must be **descriptive** and **unique**
- 3-50 characters
- Only lowercase letters, numbers, hyphens
- Must start with a letter
- No consecutive hyphens

### 3.2 Namespace Prefixes

Each package requires a **unique 2-5 character prefix** for database tables.

✅ **Valid Examples**:
- `br_` for Bullying Report → `br_reports`, `br_actions`
- `emp_` for Employee Evaluation → `emp_reviews`, `emp_ratings`
- `maint_` for Maintenance → `maint_requests`, `maint_parts`

❌ **Invalid Examples**:
- `b_` (too short, not descriptive)
- `bullying_` (too long)
- `BR_` (uppercase not allowed)

**Rules**:
- Must be unique across all packages
- 2-5 lowercase letters
- Must end with underscore
- Should hint at package purpose

### 3.3 Database Tables & Columns

**Format**: `snake_case` (lowercase, underscore-separated)

✅ **Examples**:
- Tables: `br_reports`, `br_remediation_actions`, `emp_peer_reviews`
- Columns: `incident_location`, `created_by`, `is_anonymous`

**Rules**:
- All table names **must** start with namespace prefix
- Use descriptive names (avoid abbreviations)
- Singular for entity tables (`br_report` not `br_reports`)
- Junction tables can be plural (`br_report_witnesses`)

### 3.4 Routes & URLs

**Format**: `/pkg/<namespace>/<module-slug>`

✅ **Examples**:
- `/pkg/br/report-form`
- `/pkg/br/view-reports`
- `/pkg/emp/evaluation-dashboard`

**Rules**:
- All package routes **must** start with `/pkg/`
- Use namespace (not full package name)
- Module slugs use `kebab-case`
- No query strings in route definitions (add at runtime)

### 3.5 Field Names

**Format**: `snake_case` (lowercase, underscores)

✅ **Examples**:
- `incident_date`
- `reporter_name`
- `is_anonymous`
- `follow_up_required`

❌ **Invalid**:
- `IncidentDate` (camelCase)
- `incident-date` (hyphens)
- `date` (too generic, needs context)

---

## 4. Directory Structure

### 4.1 Source Package Structure (Development)

```
/packages/
├── local/                           # Locally developed packages
│   └── bullying-report/
│       ├── manifest.json            # Package definition (REQUIRED)
│       ├── README.md                # Documentation (REQUIRED)
│       ├── CHANGELOG.md             # Version history (REQUIRED)
│       ├── LICENSE                  # License file (REQUIRED)
│       ├── screenshots/             # UI screenshots (REQUIRED)
│       │   ├── main-view.png
│       │   ├── admin-view.png
│       │   └── form-example.png
│       ├── migrations/              # Database migrations (OPTIONAL)
│       │   ├── 0001_init.sql
│       │   ├── 0002_add_status_field.sql
│       │   └── rollback/
│       │       ├── 0001_rollback.sql
│       │       └── 0002_rollback.sql
│       ├── modules/                 # Module definitions (OPTIONAL)
│       │   ├── report-form.module.json
│       │   ├── reports-table.module.json
│       │   └── workflow.module.json
│       ├── seeds/                   # Test/demo data (OPTIONAL)
│       │   └── sample-data.json
│       ├── i18n/                    # Translations (OPTIONAL)
│       │   ├── en.json
│       │   └── es.json
│       ├── assets/                  # Package assets (OPTIONAL)
│       │   ├── icons/
│       │   └── images/
│       └── tests/                   # Unit tests (RECOMMENDED)
│           └── validation.test.php
│
├── imported/                        # Packages from repository
│   └── (same structure)
│
├── marketplace/                     # Downloaded from marketplace
│   └── (same structure)
│
└── temp/                           # Temp extraction directory
```

### 4.2 Built Package (.hubpkg)

A `.hubpkg` file is simply the `manifest.json` renamed. The package manager extracts metadata from this single JSON file and creates all necessary database records.

**Example**: `bullying-report_1.0.0.hubpkg`

```json
{
  "schemaVersion": 1,
  "package": { ... },
  "db": { "entities": [...], "migrations": [...] },
  "modules": [ ... ],
  "fields": [ ... ],
  "permissions": { ... }
}
```

---

## 5. Manifest Schema

The `manifest.json` is the **single source of truth** for your package.

### 5.1 Full Schema Example

```json
{
  "schemaVersion": 1,
  "package": {
    "id": "com.woodson.bullying-report",
    "name": "bullying-report",
    "namespace": "br",
    "display_name": "Bullying & Harassment Reporting",
    "description": "Confidential system for reporting and tracking bullying incidents",
    "version": "1.0.0",
    "author": "Woodson ISD Tech Department",
    "author_email": "tech@woodsonisd.net",
    "license": "MIT",
    "category": "education",
    "tags": ["bullying", "reporting", "safety", "counseling"],
    "icon": "bi-shield-exclamation",
    "base_url": "/pkg/br/",
    "repository": "https://github.com/woodson-hub/bullying-report",
    "homepage": "https://hub.woodsonisd.net/packages/bullying-report",
    "support": "https://github.com/woodson-hub/bullying-report/issues"
  },
  
  "compatibility": {
    "hub_version": ">=1.0.0 <2.0.0",
    "php_version": ">=8.0",
    "mysql_version": ">=5.7",
    "tested_up_to": "1.5.0"
  },
  
  "capabilities": [
    "forms",
    "tables",
    "workflows",
    "file-uploads",
    "notifications"
  ],
  
  "db": {
    "entities": [
      {
        "name": "br_report",
        "displayName": "Bullying Report",
        "fields": [
          "id CHAR(26) PRIMARY KEY",
          "tenant_id CHAR(26) NOT NULL",
          "incident_date DATE NOT NULL",
          "incident_location VARCHAR(255)",
          "reporter_name VARCHAR(255)",
          "is_anonymous BOOLEAN DEFAULT FALSE",
          "incident_description TEXT NOT NULL",
          "status ENUM('submitted', 'reviewing', 'action_taken', 'closed') DEFAULT 'submitted'",
          "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
          "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
          "created_by CHAR(26)",
          "updated_by CHAR(26)",
          "is_deleted BOOLEAN DEFAULT FALSE"
        ],
        "indexes": [
          "INDEX idx_tenant (tenant_id)",
          "INDEX idx_status (status, created_at)",
          "INDEX idx_date (incident_date)"
        ]
      },
      {
        "name": "br_remediation",
        "displayName": "Remediation Actions",
        "fields": [
          "id CHAR(26) PRIMARY KEY",
          "report_id CHAR(26) NOT NULL",
          "action_taken TEXT NOT NULL",
          "action_date DATE NOT NULL",
          "taken_by CHAR(26) NOT NULL",
          "notes TEXT",
          "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ],
        "indexes": [
          "INDEX idx_report (report_id)"
        ],
        "foreignKeys": [
          "FOREIGN KEY (report_id) REFERENCES br_report(id) ON DELETE RESTRICT"
        ]
      }
    ],
    "migrations": []
  },
  
  "modules": [
    {
      "type": "Form",
      "slug": "report-form",
      "displayName": "Submit Report",
      "entity": "br_report",
      "route": "/pkg/br/report-form",
      "icon": "bi-file-earmark-plus",
      "allowAnonymous": true,
      "fields": [
        {
          "key": "is_anonymous",
          "fieldType": "checkbox",
          "label": "Submit Anonymously",
          "helpText": "Check this box if you wish to remain anonymous",
          "defaultValue": false
        },
        {
          "key": "reporter_name",
          "fieldType": "text",
          "label": "Your Name",
          "required": true,
          "showIf": {"is_anonymous": false},
          "maxLength": 255
        },
        {
          "key": "incident_date",
          "fieldType": "date",
          "label": "When did this happen?",
          "required": true,
          "maxDate": "today"
        },
        {
          "key": "incident_location",
          "fieldType": "select",
          "label": "Where did this happen?",
          "required": true,
          "options": [
            {"value": "classroom", "label": "Classroom"},
            {"value": "hallway", "label": "Hallway"},
            {"value": "cafeteria", "label": "Cafeteria"},
            {"value": "bus", "label": "School Bus"},
            {"value": "online", "label": "Online/Social Media"},
            {"value": "other", "label": "Other"}
          ]
        },
        {
          "key": "incident_description",
          "fieldType": "textarea",
          "label": "Please describe what happened",
          "required": true,
          "minLength": 20,
          "maxLength": 5000,
          "rows": 6
        }
      ],
      "validation": {
        "rateLimit": {
          "perUser": 5,
          "perMinute": 10
        }
      },
      "onSubmit": {
        "notify": ["counselor", "principal"],
        "redirect": "/pkg/br/confirmation"
      }
    },
    {
      "type": "TableView",
      "slug": "reports-table",
      "displayName": "View Reports",
      "entity": "br_report",
      "route": "/pkg/br/reports",
      "icon": "bi-table",
      "columns": [
        {"key": "incident_date", "label": "Date", "sortable": true},
        {"key": "incident_location", "label": "Location", "filterable": true},
        {"key": "status", "label": "Status", "filterable": true, "badge": true},
        {"key": "created_at", "label": "Submitted", "sortable": true, "format": "datetime"}
      ],
      "filters": [
        {"key": "status", "type": "select", "label": "Status"},
        {"key": "incident_date", "type": "dateRange", "label": "Date Range"}
      ],
      "actions": [
        {"key": "view", "label": "View Details", "icon": "bi-eye"},
        {"key": "edit", "label": "Update Status", "icon": "bi-pencil", "permission": "br_manage"}
      ],
      "defaultSort": {"field": "created_at", "direction": "DESC"},
      "pagination": {
        "enabled": true,
        "perPage": 25,
        "options": [10, 25, 50, 100]
      }
    },
    {
      "type": "Workflow",
      "slug": "review-workflow",
      "displayName": "Report Review Process",
      "entity": "br_report",
      "steps": [
        {
          "id": "submitted",
          "label": "Submitted",
          "description": "Report has been submitted and awaiting review",
          "nextSteps": ["reviewing"],
          "requiredRole": null
        },
        {
          "id": "reviewing",
          "label": "Under Review",
          "description": "Counselor or admin is reviewing the report",
          "nextSteps": ["action_taken"],
          "requiredRole": "br_manage",
          "requiredFields": ["assigned_to"]
        },
        {
          "id": "action_taken",
          "label": "Action Taken",
          "description": "Remediation action has been completed",
          "nextSteps": ["closed"],
          "requiredRole": "br_manage",
          "requiredFields": ["remediation_notes"]
        },
        {
          "id": "closed",
          "label": "Closed",
          "description": "Report has been resolved and closed",
          "nextSteps": [],
          "requiredRole": "br_admin"
        }
      ]
    }
  ],
  
  "fields": [
    {
      "name": "incident_date",
      "type": "date",
      "label": "Incident Date",
      "required": true,
      "order": 1,
      "searchable": true,
      "visible_in_list": true,
      "validation": {
        "maxDate": "today"
      }
    },
    {
      "name": "incident_location",
      "type": "select",
      "label": "Location",
      "required": true,
      "order": 2,
      "searchable": true,
      "visible_in_list": true,
      "options": [
        {"value": "classroom", "label": "Classroom"},
        {"value": "hallway", "label": "Hallway"},
        {"value": "cafeteria", "label": "Cafeteria"},
        {"value": "bus", "label": "School Bus"},
        {"value": "online", "label": "Online/Social Media"},
        {"value": "other", "label": "Other"}
      ]
    },
    {
      "name": "reporter_name",
      "type": "text",
      "label": "Reporter Name",
      "required": false,
      "order": 3,
      "searchable": true,
      "visible_in_list": false,
      "validation": {
        "maxLength": 255
      },
      "pii": true
    },
    {
      "name": "is_anonymous",
      "type": "checkbox",
      "label": "Anonymous Report",
      "required": false,
      "order": 4,
      "default_value": false,
      "visible_in_list": true
    },
    {
      "name": "incident_description",
      "type": "textarea",
      "label": "Description",
      "required": true,
      "order": 5,
      "searchable": true,
      "visible_in_list": false,
      "validation": {
        "minLength": 20,
        "maxLength": 5000
      },
      "help_text": "Please provide as much detail as possible"
    },
    {
      "name": "status",
      "type": "select",
      "label": "Status",
      "required": true,
      "order": 6,
      "visible_in_list": true,
      "default_value": "submitted",
      "options": [
        {"value": "submitted", "label": "Submitted", "color": "info"},
        {"value": "reviewing", "label": "Under Review", "color": "warning"},
        {"value": "action_taken", "label": "Action Taken", "color": "primary"},
        {"value": "closed", "label": "Closed", "color": "success"}
      ]
    }
  ],
  
  "permissions": {
    "roles": [
      {
        "key": "br_view",
        "displayName": "View Reports",
        "description": "Can view submitted bullying reports"
      },
      {
        "key": "br_manage",
        "displayName": "Manage Reports",
        "description": "Can review, update, and take action on reports"
      },
      {
        "key": "br_admin",
        "displayName": "Administrator",
        "description": "Full control including closing reports and accessing analytics"
      }
    ],
    "roleMatrix": {
      "br_view": [
        "module:reports-table:read"
      ],
      "br_manage": [
        "module:reports-table:read",
        "module:reports-table:update",
        "module:review-workflow:advance"
      ],
      "br_admin": [
        "module:reports-table:*",
        "module:review-workflow:*",
        "module:analytics:read"
      ]
    },
    "defaultAccess": {
      "counselor": ["br_manage"],
      "principal": ["br_admin"],
      "super_admin": ["br_admin"]
    }
  },
  
  "menu_items": [
    {
      "label": "Submit Report",
      "route": "/pkg/br/report-form",
      "icon": "bi-file-earmark-plus",
      "order": 1,
      "minimum_role": null
    },
    {
      "label": "View Reports",
      "route": "/pkg/br/reports",
      "icon": "bi-table",
      "order": 2,
      "minimum_role": "br_view"
    },
    {
      "label": "Analytics",
      "route": "/pkg/br/analytics",
      "icon": "bi-graph-up",
      "order": 3,
      "minimum_role": "br_admin"
    }
  ]
}
```

### 5.2 Required Fields

The following fields are **mandatory** in every manifest:

| Section | Field | Description |
|---------|-------|-------------|
| `package` | `id` | Unique identifier (reverse-DNS format) |
| `package` | `name` | Package name (kebab-case) |
| `package` | `namespace` | 2-5 character prefix for DB tables |
| `package` | `display_name` | Human-readable name |
| `package` | `version` | Semantic version (MAJOR.MINOR.PATCH) |
| `db.entities` | Array | At least one database entity |
| `fields` | Array | At least one field definition |
| `permissions.roles` | Array | At least one role |

---

## 6. Database Conventions

### 6.1 Required Columns

**Every** entity table must include these columns:

```sql
id CHAR(26) PRIMARY KEY,              -- ULID identifier
tenant_id CHAR(26) NOT NULL,          -- Multi-tenancy support
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
created_by CHAR(26),                  -- User ID who created
updated_by CHAR(26),                  -- User ID who last updated
is_deleted BOOLEAN DEFAULT FALSE      -- Soft delete flag
```

### 6.2 Indexes

Always add indexes for:
- `tenant_id` (multi-tenancy queries)
- Foreign keys
- Fields used in `WHERE` clauses frequently
- Status/state fields
- Date fields used for filtering

```sql
INDEX idx_tenant (tenant_id),
INDEX idx_status (status, created_at),
INDEX idx_date_range (start_date, end_date)
```

### 6.3 Foreign Keys

Use `RESTRICT` for deletion to prevent data loss:

```sql
FOREIGN KEY (report_id) REFERENCES br_report(id) ON DELETE RESTRICT
```

**Never** use `CASCADE` unless explicitly required and documented.

### 6.4 Data Types

| Type | Use Case | Example |
|------|----------|---------|
| `CHAR(26)` | IDs (ULID) | `id`, `user_id` |
| `VARCHAR(n)` | Short text | `name` (255), `email` (320) |
| `TEXT` | Long text | `description`, `notes` |
| `BOOLEAN` | True/false | `is_active`, `is_anonymous` |
| `INT` | Numbers | `quantity`, `age` |
| `DECIMAL(10,2)` | Money | `amount`, `price` |
| `DATE` | Date only | `incident_date` |
| `TIMESTAMP` | Date + time | `created_at` |
| `ENUM` | Fixed options | `status ('pending', 'approved')` |

---

## 7. Module Types

> **📘 For complete module specifications, see [MODULE_CATALOG_V2.md](./MODULE_CATALOG_V2.md)**
>
> The Module Catalog provides comprehensive definitions for all 12+ module types, including:
> - Detailed field requirements and optional properties
> - Hub infrastructure integration points
> - Validation rules with rule IDs (e.g., `[FRM-R01]`, `[TBL-R03]`)
> - Security requirements and best practices
> - Complete examples with Hub integration code
>
> **Available Module Types**:
> - **Core**: `Form`, `TableView`, `Workflow`, `Analytics`, `Dashboard`
> - **Communication**: `EmailNotification`, `PDFGenerator`
> - **Operations**: `Action`, `FileManager`, `Computation`
> - **Education**: `StudentEvaluation`, `EmployeeEvaluation`

### 7.1 Module Structure Overview

All modules in a package manifest follow this base structure:

```json
{
  "type": "Form|TableView|Workflow|Analytics|...",
  "slug": "kebab-case-slug",
  "displayName": "Human Readable Name",
  "entity": "namespace_entity_name",
  "route": "/pkg/namespace/slug",
  "icon": "bi-icon-name",
  "access": ["permission_key_1", "permission_key_2"],
  "...additionalTypeSpecificFields": "..."
}
```

### 7.2 Base Module Rules

These rules apply to **all module types** ([MOD-R01] through [MOD-R11]):

| Rule ID | Rule | Description |
|---------|------|-------------|
| **[MOD-R01]** | `type` required | Must match approved type from Module Catalog |
| **[MOD-R02]** | `slug` required | Unique within package, kebab-case only |
| **[MOD-R03]** | `displayName` required | Human-readable title (max 80 chars) |
| **[MOD-R04]** | `entity` optional | Database entity reference (required for data-bound modules) |
| **[MOD-R05]** | `route` required | Must follow `/pkg/<namespace>/<slug>` pattern |
| **[MOD-R06]** | `icon` optional | Bootstrap Icons or FontAwesome class name |
| **[MOD-R07]** | `access` optional | Array of permission keys from manifest |
| **[MOD-R08]** | `layout` optional | Grid configuration for responsive display |
| **[MOD-R09]** | `a11y` optional | Accessibility metadata (ARIA labels, shortcuts) |
| **[MOD-R10]** | `validation` optional | Module-level validation rules |
| **[MOD-R11]** | `audit` optional | Custom audit event types |

### 7.3 Quick Reference by Module Type

#### Form Module (`type: "Form"`)
- **Purpose**: Data entry, validation, submission
- **Hub Integration**: Database writes, CSRF protection, email notifications, audit logging
- **Key Rules**: [FRM-R01] through [FRM-R08]
- **See**: [MODULE_CATALOG_V2.md § 1. Form Module](./MODULE_CATALOG_V2.md#-1-form-module-type-form)

#### TableView Module (`type: "TableView"`)
- **Purpose**: Display, sort, filter, export records
- **Hub Integration**: Database reads, permissions, export (CSV/XLSX/PDF), pagination
- **Key Rules**: [TBL-R01] through [TBL-R07]
- **See**: [MODULE_CATALOG_V2.md § 2. TableView Module](./MODULE_CATALOG_V2.md#-2-tableview-module-type-tableview)

#### Workflow Module (`type: "Workflow"`)
- **Purpose**: Multi-step state machines with role-based transitions
- **Hub Integration**: Auth roles, audit trail, email notifications, state validation
- **Key Rules**: [WF-R01] through [WF-R08]
- **See**: [MODULE_CATALOG_V2.md § 3. Workflow Module](./MODULE_CATALOG_V2.md#-3-workflow-module-type-workflow)

#### Analytics Module (`type: "Analytics"`)
- **Purpose**: Data visualization, charts, metrics
- **Hub Integration**: Chart.js, data aggregation, caching, PII handling
- **Key Rules**: [ANL-R01] through [ANL-R07]
- **See**: [MODULE_CATALOG_V2.md § 4. Analytics Module](./MODULE_CATALOG_V2.md#-4-analytics-module-type-analytics)

#### EmailNotification Module (`type: "EmailNotification"`)
- **Purpose**: Event-driven automated emails
- **Hub Integration**: PHPMailer, SMTP config, template engine, audit logging
- **Key Rules**: [NTF-R01] through [NTF-R07]
- **See**: [MODULE_CATALOG_V2.md § 5. Email Notification Module](./MODULE_CATALOG_V2.md#-5-email-notification-module-type-emailnotification)

#### PDFGenerator Module (`type: "PDFGenerator"`)
- **Purpose**: Generate formatted PDF documents
- **Hub Integration**: mPDF library, file storage, signed URLs, branding
- **Key Rules**: [PDF-R01] through [PDF-R07]
- **See**: [MODULE_CATALOG_V2.md § 6. PDF Generation Module](./MODULE_CATALOG_V2.md#-6-pdf-generation-module-type-pdfgenerator)

#### EmployeeEvaluation Module (`type: "EmployeeEvaluation"`)
- **Purpose**: Staff performance reviews with workflow and email
- **Hub Integration**: Workflow engine, weighted scoring, PDF generation, digital signatures, customizable email fields
- **Key Features**: Admin-selected email fields, multi-step approval, audit trail
- **See**: [MODULE_CATALOG_V2.md § 8. Employee Evaluation Module](./MODULE_CATALOG_V2.md#-8-hr-specific-employee-evaluation-module-type-employeeevaluation)

#### Other Module Types
For complete specifications of all 12+ module types, consult the **[Module Catalog v2.1](./MODULE_CATALOG_V2.md)**.

---

## 8. Field Types & Validation

### 8.1 Text Fields

```json
{
  "key": "field_name",
  "fieldType": "text",
  "label": "Field Label",
  "required": true,
  "validation": {
    "minLength": 3,
    "maxLength": 255,
    "pattern": "^[A-Za-z ]+$",
    "patternMessage": "Only letters and spaces allowed"
  },
  "helpText": "Additional guidance for user"
}
```

### 8.2 Select Fields

```json
{
  "key": "priority",
  "fieldType": "select",
  "label": "Priority",
  "required": true,
  "options": [
    {"value": "low", "label": "Low", "color": "success"},
    {"value": "medium", "label": "Medium", "color": "warning"},
    {"value": "high", "label": "High", "color": "danger"}
  ],
  "defaultValue": "medium"
}
```

### 8.3 Date Fields

```json
{
  "key": "event_date",
  "fieldType": "date",
  "label": "Event Date",
  "required": true,
  "validation": {
    "minDate": "today",
    "maxDate": "2025-12-31"
  }
}
```

### 8.4 File Upload Fields

```json
{
  "key": "attachment",
  "fieldType": "file",
  "label": "Upload Document",
  "validation": {
    "maxSize": 10485760,
    "allowedExtensions": ["pdf", "doc", "docx", "jpg", "png"],
    "allowedMimeTypes": ["application/pdf", "image/jpeg", "image/png"]
  }
}
```

### 8.5 Conditional Fields

```json
{
  "key": "other_details",
  "fieldType": "textarea",
  "label": "Please explain",
  "required": true,
  "showIf": {
    "location": "other"
  }
}
```

---

## 9. Permissions & RBAC

### 9.1 Define Roles

```json
"permissions": {
  "roles": [
    {
      "key": "namespace_view",
      "displayName": "Viewer",
      "description": "Can view records only"
    },
    {
      "key": "namespace_edit",
      "displayName": "Editor",
      "description": "Can create and edit records"
    },
    {
      "key": "namespace_admin",
      "displayName": "Administrator",
      "description": "Full control over all features"
    }
  ]
}
```

### 9.2 Role Matrix (Module Permissions)

```json
"roleMatrix": {
  "namespace_view": [
    "module:table-view:read"
  ],
  "namespace_edit": [
    "module:table-view:read",
    "module:form:create",
    "module:form:update"
  ],
  "namespace_admin": [
    "module:*:*"
  ]
}
```

### 9.3 Default Access Mapping

Map package roles to system roles:

```json
"defaultAccess": {
  "staff": ["namespace_view"],
  "admin": ["namespace_edit"],
  "super_admin": ["namespace_admin"]
}
```

---

## 10. Security Requirements

### 10.1 Input Validation

**All fields must**:
- Define `required: true/false`
- Set `maxLength` for text fields
- Use `allowedExtensions` and `maxSize` for file uploads
- Specify data type validation (email, url, number, date)

### 10.2 SQL Injection Prevention

✅ **Allowed**:
- Using field definitions to generate schema
- Parameterized queries via Hub's Data API

❌ **Forbidden**:
- Raw SQL strings in manifest
- `eval()`, `exec()`, `shell_exec()` patterns
- Dynamic SQL construction

### 10.3 XSS Protection

✅ **Allowed**:
- Plain text content
- Markdown (will be sanitized)
- HTML entities in labels

❌ **Forbidden**:
- Raw HTML in field values
- `<script>` tags anywhere
- Event handlers (`onclick`, etc.)

### 10.4 File Upload Security

**Required Validations**:
```json
{
  "maxSize": 10485760,
  "allowedExtensions": ["pdf", "jpg", "png"],
  "allowedMimeTypes": ["application/pdf", "image/jpeg", "image/png"],
  "scanForViruses": true
}
```

### 10.5 Rate Limiting

**All forms must define**:
```json
"validation": {
  "rateLimit": {
    "perUser": 10,
    "perMinute": 5,
    "perHour": 20
  }
}
```

### 10.6 Secrets & Credentials

❌ **Never** include:
- API keys
- Passwords
- Database credentials
- Private keys
- Access tokens

---

## 11. Documentation Standards

### 11.1 README.md Structure

```markdown
# Package Name

Brief description (2-3 sentences)

## Features

- Feature 1
- Feature 2
- Feature 3

## Requirements

- Hub Version: >= 1.0.0
- PHP: >= 8.0
- MySQL: >= 5.7

## Installation

1. Download `package-name_1.0.0.hubpkg`
2. Navigate to Admin → Package Manager
3. Click "Upload Package"
4. Select the .hubpkg file
5. Review compatibility report
6. Click "Install"

## Configuration

### Initial Setup

1. Go to Sections → Section Access
2. Grant roles:
   - `namespace_view` → Staff, Teachers
   - `namespace_manage` → Counselors, Principals

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `field_name` | Text | Yes | Description |

## Usage

### For End Users

Step-by-step instructions...

### For Administrators

Admin-specific tasks...

## Permissions

| Role | Access Level |
|------|--------------|
| `namespace_view` | View reports only |
| `namespace_manage` | Create and edit |
| `namespace_admin` | Full control |

## Screenshots

![Main View](screenshots/main-view.png)
![Admin Panel](screenshots/admin-view.png)

## Changelog

See [CHANGELOG.md](CHANGELOG.md)

## Support

- Issues: https://github.com/org/repo/issues
- Email: support@domain.com

## License

MIT License - See [LICENSE](LICENSE)
```

### 11.2 CHANGELOG.md Format

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-11-15

### Added
- New field for tracking follow-up actions
- Export to Excel feature

### Changed
- Improved mobile responsiveness
- Updated status workflow

### Fixed
- Date validation bug in Safari
- Permission check for anonymous users

### Security
- Added rate limiting to submission form

## [1.0.0] - 2025-10-01

### Added
- Initial release
- Basic reporting form
- Admin review table
- Email notifications
```

### 11.3 Screenshots

**Requirements**:
- Minimum **2 screenshots** required
- Format: PNG or JPEG
- Resolution: **1280×720** or higher
- Max file size: **500KB** each
- Show **actual data** (not empty forms)
- Include:
  - Main user interface
  - Admin/management view
  - (Optional) Mobile view
  - (Optional) Workflow states

---

## 12. Testing Requirements

### 12.1 Pre-Submission Checklist

Before submitting a package, verify:

- ☑ `pkg-lint` validation passes with no critical errors
- ☑ Package installs successfully on fresh Hub instance
- ☑ All forms submit data correctly
- ☑ Table views display and filter records
- ☑ Workflows advance through all steps
- ☑ Permissions restrict access appropriately
- ☑ File uploads work and validate extensions
- ☑ No console errors in browser
- ☑ Mobile responsive (test on 320px, 768px, 1024px widths)
- ☑ README and CHANGELOG are complete
- ☑ Screenshots show actual functionality

### 12.2 Test Data

Include sample/seed data for testing:

```json
{
  "seeds": [
    {
      "entity": "namespace_entity",
      "records": [
        {
          "field1": "value1",
          "field2": "value2",
          "created_at": "2025-10-01 10:00:00"
        }
      ]
    }
  ]
}
```

---

## 13. Submission Process

### 13.1 Prepare Package

1. **Validate manifest**:
   ```bash
   php cli/pkg-lint.php packages/local/your-package/manifest.json
   ```

2. **Build .hubpkg**:
   ```bash
   php cli/pkg-build.php packages/local/your-package/
   ```

3. **Test installation**:
   - Install on local/staging Hub instance
   - Test all features
   - Verify permissions
   - Check mobile responsiveness

### 13.2 Submit to Repository

**Option A: GitHub Pull Request**

1. Fork `https://github.com/woodson-hub/hub-packages`
2. Create branch: `add-package-name`
3. Add package directory:
   ```
   packages/[category]/[package-name]/
   ├── [package-name]_[version].hubpkg
   ├── README.md
   ├── CHANGELOG.md
   └── screenshots/
   ```
4. Update main `README.md` with your package
5. Submit Pull Request

**Option B: Direct Upload (for approved contributors)**

1. Login to Hub Package Registry
2. Navigate to "Submit Package"
3. Upload `.hubpkg` file
4. Fill in metadata form
5. Upload screenshots
6. Submit for review

### 13.3 Review Timeline

- **Initial Review**: Within 3 business days
- **Feedback**: Via PR comments or email
- **Approval**: Within 7 business days (if no issues)
- **Publication**: Immediate upon approval

---

## 14. Review & Approval

### 14.1 Automated Checks

✅ **Pass/Fail Checks**:
- Manifest validates against JSON schema
- No security vulnerabilities detected
- Required fields present
- Naming conventions followed
- No duplicate package ID

⚠️ **Warning Checks**:
- Documentation quality
- Screenshot quality
- Test coverage

### 14.2 Manual Review Criteria

| Area | Criteria |
|------|----------|
| **Functionality** | Features work as described |
| **Security** | No vulnerabilities, input validation present |
| **UI/UX** | Theme-compliant, responsive, accessible |
| **Documentation** | Clear, complete, accurate |
| **Code Quality** | Well-structured manifest, logical field organization |
| **Permissions** | Least-privilege, appropriate role separation |

### 14.3 Rejection Reasons

Common reasons for rejection:

- ❌ Security vulnerabilities
- ❌ Naming convention violations
- ❌ Incomplete documentation
- ❌ Missing screenshots
- ❌ Package doesn't install/function
- ❌ Breaks theme consistency
- ❌ Includes proprietary/licensed content without permission

---

## 15. Versioning & Updates

### 15.1 Semantic Versioning

```
MAJOR.MINOR.PATCH
```

**When to increment**:

| Version | Increment When | Example |
|---------|----------------|---------|
| **MAJOR** | Breaking changes (schema, API, permissions) | 1.x.x → 2.0.0 |
| **MINOR** | New features (backward-compatible) | 1.0.x → 1.1.0 |
| **PATCH** | Bug fixes, docs, performance | 1.0.0 → 1.0.1 |

### 15.2 Update Process

1. **Make changes** to package source
2. **Update version** in `manifest.json`
3. **Update CHANGELOG.md** with changes
4. **Run pkg-lint** validation
5. **Build new .hubpkg** with updated version
6. **Test upgrade path** from previous version
7. **Submit update** (same process as new package)

### 15.3 Breaking Changes

If releasing a breaking change (MAJOR version):

- ✅ Document migration steps
- ✅ Provide data migration scripts
- ✅ Give advance notice (30 days recommended)
- ✅ Support old version for transition period

---

## Appendix A: CLI Tools

### pkg-lint

Validate package manifest:
```bash
php cli/pkg-lint.php packages/local/my-package/manifest.json
```

### pkg-build

Build .hubpkg from source:
```bash
php cli/pkg-build.php packages/local/my-package/
```

### pkg-scaffold

Generate new package template:
```bash
php cli/pkg-scaffold.php --name=my-package --namespace=mp
```

---

## Appendix B: Example Packages

### Simple Package (Minimal)

See: `examples/simple-form/`

### Medium Package (Forms + Table)

See: `examples/employee-evaluation/`

### Complex Package (Workflow + Analytics)

See: `examples/maintenance-request/`

---

## Appendix C: Field Type Reference

| Type | Description | Validation Options |
|------|-------------|-------------------|
| `text` | Single-line text | minLength, maxLength, pattern |
| `textarea` | Multi-line text | minLength, maxLength, rows |
| `email` | Email address | format validation automatic |
| `url` | Website URL | format validation automatic |
| `tel` | Phone number | pattern for format |
| `number` | Numeric input | minValue, maxValue, step |
| `currency` | Money amount | minValue, maxValue, precision |
| `date` | Date picker | minDate, maxDate |
| `time` | Time picker | minTime, maxTime |
| `datetime` | Date + time | minDateTime, maxDateTime |
| `checkbox` | Single checkbox | none |
| `radio` | Radio buttons | options required |
| `select` | Dropdown | options required |
| `multi_select` | Multiple selection | options, minSelections, maxSelections |
| `file` | File upload | maxSize, allowedExtensions |
| `image` | Image upload | maxSize, maxWidth, maxHeight |
| `user_select` | Reference to user | filterByRole |
| `vehicle_select` | Reference to vehicle | filterByType |

---

## Appendix D: Common Patterns

### Anonymous Submissions

```json
{
  "allowAnonymous": true,
  "fields": [
    {"key": "is_anonymous", "fieldType": "checkbox", "label": "Submit Anonymously"},
    {"key": "reporter_name", "required": true, "showIf": {"is_anonymous": false}}
  ]
}
```

### Status Workflow

```sql
status ENUM('draft', 'submitted', 'approved', 'rejected', 'archived')
```

### Audit Trail

```sql
-- Include in every entity
created_by CHAR(26),
updated_by CHAR(26),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

---

**Ready to build your first package?** Start with `php cli/pkg-scaffold.php`!
