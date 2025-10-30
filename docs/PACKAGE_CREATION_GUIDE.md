# Package Creation Quick Start Guide

**Welcome to The Hub Package Development!** This guide will get you creating packages in minutes.

> **📘 Documentation Reference:**
> - [PACKAGE_SPECIFICATION_V2.md](./PACKAGE_SPECIFICATION_V2.md) - Complete technical specification
> - [MODULE_CATALOG_V2.md](./MODULE_CATALOG_V2.md) - **All 12+ module types with rules**

---

## 📋 Prerequisites

Before you begin, ensure you have:

- ✅ Access to a Hub development environment
- ✅ PHP 8.0+ installed
- ✅ Basic understanding of JSON
- ✅ Familiarity with database concepts (tables, fields, relationships)
- ✅ Text editor or IDE (VS Code recommended)
- ✅ Read the [MODULE_CATALOG_V2.md](./MODULE_CATALOG_V2.md) for module types

---

## 🚀 Create Your First Package (5 Minutes)

### Step 1: Scaffold the Package

```bash
cd /var/www/woodson/thehub
php cli/pkg-scaffold.php --name=my-package --namespace=mp --category=education
```

**Options explained:**
- `--name`: Package name in **kebab-case** (lowercase, hyphen-separated)
- `--namespace`: 2-5 character prefix for database tables (**must be unique**)
- `--category`: One of: `education`, `maintenance`, `administration`, `facilities`, `hr`, `finance`, `other`

This creates:
```
packages/local/my-package/
├── manifest.json          # Package definition
├── README.md             # Documentation
├── CHANGELOG.md          # Version history
├── LICENSE               # MIT license
├── screenshots/          # UI screenshots
├── migrations/           # Database upgrades
├── modules/              # Module definitions (see MODULE_CATALOG_V2.md)
├── templates/            # Email/PDF templates
└── seeds/                # Test data
```

### Step 2: Choose Your Module Types

**Consult [MODULE_CATALOG_V2.md](./MODULE_CATALOG_V2.md) to select the right module types for your use case:**

| Use Case | Recommended Module Type | Catalog Reference |
|----------|------------------------|-------------------|
| Data entry form | `Form` | [§ 1. Form Module](./MODULE_CATALOG_V2.md#-1-form-module-type-form) |
| Display records | `TableView` | [§ 2. TableView Module](./MODULE_CATALOG_V2.md#-2-tableview-module-type-tableview) |
| Approval workflow | `Workflow` | [§ 3. Workflow Module](./MODULE_CATALOG_V2.md#-3-workflow-module-type-workflow) |
| Charts/graphs | `Analytics` | [§ 4. Analytics Module](./MODULE_CATALOG_V2.md#-4-analytics-module-type-analytics) |
| Automated emails | `EmailNotification` | [§ 5. Email Notification](./MODULE_CATALOG_V2.md#-5-email-notification-module-type-emailnotification) |
| Generate PDFs | `PDFGenerator` | [§ 6. PDF Generator](./MODULE_CATALOG_V2.md#-6-pdf-generation-module-type-pdfgenerator) |
| Employee reviews | `EmployeeEvaluation` | [§ 8. Employee Evaluation](./MODULE_CATALOG_V2.md#-8-hr-specific-employee-evaluation-module-type-employeeevaluation) |
| Bulk operations | `Action` | [§ 9. Action Module](./MODULE_CATALOG_V2.md#-9-action-module-type-action) |
| File uploads | `FileManager` | [§ 10. File Manager](./MODULE_CATALOG_V2.md#-10-file-manager-module-type-filemanager) |
| Calculated fields | `Computation` | [§ 11. Calculation Module](./MODULE_CATALOG_V2.md#-11-calculation-module-type-computation) |
| Admin dashboard | `Dashboard` | [§ 12. Dashboard Module](./MODULE_CATALOG_V2.md#-12-dashboard-module-type-dashboard) |

### Step 3: Customize the Manifest

Open `packages/local/my-package/manifest.json` and edit:

**Package Metadata** (lines 3-16):
```json
{
  "package": {
    "id": "com.woodson.my-package",
    "name": "my-package",
    "namespace": "mp",
    "display_name": "My Package Name",
    "description": "Brief description of what it does",
    "version": "1.0.0",
    "icon": "bi-star"  // Bootstrap Icon name
  }
}
```

**Database Schema** (lines 30-50):
```json
{
  "db": {
    "entities": [
      {
        "name": "mp_record",  // Must start with namespace
        "fields": [
          "id CHAR(26) PRIMARY KEY",
          "tenant_id CHAR(26) NOT NULL",
          "title VARCHAR(255) NOT NULL",
          // Add your custom fields here
          "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
        ]
      }
    ]
  }
}
```

**Field Definitions** (lines 90-140):
```json
{
  "fields": [
    {
      "name": "title",
      "type": "text",
      "label": "Title",
      "required": true,
      "validation": {
        "minLength": 3,
        "maxLength": 255
      }
    }
  ]
}
```

### Step 3: Validate the Package

```bash
php cli/pkg-lint.php packages/local/my-package/
```

**Expected Output:**
```
Package Linter v2.0
================================================================================

✓ Structure valid
✓ Package name valid: my-package
✓ Namespace valid: mp
✓ Version valid: 1.0.0
✓ Database entities validated
✓ Fields validated
✓ Permissions validated
✓ Security scan complete

✗ [Screenshots] At least 2 screenshots required (found: 0)

================================================================================
✗ PACKAGE INVALID - Fix errors before submission
```

### Step 4: Add Screenshots

Take screenshots of your package UI:

1. **Create a test instance** of your package (or mock it up)
2. **Capture screenshots** (1280×720 or higher)
3. **Save as PNG** in `packages/local/my-package/screenshots/`
   - `main-view.png` - Main user interface
   - `admin-view.png` - Administrative view

**Quick tip**: Use browser dev tools (F12 → Toggle device toolbar) to simulate different screen sizes.

### Step 5: Build the Package

```bash
php cli/pkg-build.php packages/local/my-package/
```

**Output:**
```
Package Builder v2.0
================================================================================

Building package: my-package v1.0.0
Source: packages/local/my-package
Output: packages/local/my-package

Running validation...
✓ Validation passed

✓ Created: my-package_1.0.0.hubpkg
Size: 12.5 KB

✓ Package built successfully!
```

### Step 6: Test Installation

1. Navigate to **Admin → Package Manager**
2. Click **"Upload Package"**
3. Select `my-package_1.0.0.hubpkg`
4. Review the compatibility report
5. Click **"Install"**
6. Enable the package in **Sections → Section Access**

**Done!** 🎉 Your package is now installed and ready to use.

---

## 📚 Package Components Explained

### 1. Manifest.json - The Brain

The manifest is a JSON file that defines everything about your package:

| Section | Purpose | Required |
|---------|---------|----------|
| `package` | Metadata (name, version, author) | ✅ |
| `compatibility` | System requirements | ✅ |
| `db` | Database schema | ✅ |
| `fields` | Field definitions with validation | ✅ |
| `permissions` | Roles and access control | ✅ |
| `modules` | UI components (forms, tables) | ⚠️ Recommended |
| `menu_items` | Navigation entries | ⚠️ Recommended |

### 2. Database Entities - The Storage

Define tables to store your data:

```json
{
  "db": {
    "entities": [
      {
        "name": "mp_report",           // Table name (with namespace prefix)
        "displayName": "Report",       // Human-readable name
        "fields": [                    // Column definitions
          "id CHAR(26) PRIMARY KEY",
          "title VARCHAR(255) NOT NULL",
          "status ENUM('draft', 'submitted') DEFAULT 'draft'"
        ],
        "indexes": [                   // Performance indexes
          "INDEX idx_status (status)",
          "INDEX idx_created (created_at DESC)"
        ]
      }
    ]
  }
}
```

**Required Columns (every table must have these):**
```sql
id CHAR(26) PRIMARY KEY              -- Unique identifier (ULID)
tenant_id CHAR(26) NOT NULL          -- Multi-tenancy
created_at TIMESTAMP
updated_at TIMESTAMP
created_by CHAR(26)
updated_by CHAR(26)
is_deleted BOOLEAN DEFAULT FALSE     -- Soft delete
```

### 3. Fields - The Data Definition

Define how users interact with data:

```json
{
  "fields": [
    {
      "name": "incident_date",        // Database column name (snake_case)
      "type": "date",                 // Field type
      "label": "Incident Date",       // Label shown to user
      "required": true,               // Validation
      "order": 1,                     // Display order
      "searchable": true,             // Can be searched
      "visible_in_list": true,        // Show in table views
      "validation": {
        "minDate": "2020-01-01",
        "maxDate": "today"
      },
      "help_text": "When did the incident occur?"
    }
  ]
}
```

**Available Field Types:**

| Type | Use For | Validation Options |
|------|---------|-------------------|
| `text` | Short text | minLength, maxLength, pattern |
| `textarea` | Long text | minLength, maxLength, rows |
| `email` | Email address | Auto-validates format |
| `url` | Website URL | Auto-validates format |
| `number` | Numeric input | minValue, maxValue, step |
| `date` | Date picker | minDate, maxDate |
| `select` | Dropdown | options (required) |
| `checkbox` | Single yes/no | none |
| `file` | File upload | maxSize, allowedExtensions |

### 4. Modules - The UI

Modules are functional components users interact with:

#### Form Module
Allows users to submit data:

```json
{
  "type": "Form",
  "slug": "submit-report",
  "displayName": "Submit Report",
  "entity": "mp_report",
  "route": "/pkg/mp/submit",
  "fields": [
    {
      "key": "title",
      "fieldType": "text",
      "label": "Report Title",
      "required": true
    }
  ],
  "validation": {
    "rateLimit": {
      "perUser": 10,
      "perMinute": 5
    }
  }
}
```

#### TableView Module
Displays records in a table:

```json
{
  "type": "TableView",
  "slug": "view-reports",
  "displayName": "View Reports",
  "entity": "mp_report",
  "columns": [
    {"key": "title", "label": "Title", "sortable": true},
    {"key": "status", "label": "Status", "badge": true},
    {"key": "created_at", "label": "Date", "format": "datetime"}
  ],
  "filters": [
    {"key": "status", "type": "select", "label": "Status"}
  ],
  "pagination": {
    "enabled": true,
    "perPage": 25
  }
}
```

### 5. Permissions - The Security

Define who can access what:

```json
{
  "permissions": {
    "roles": [
      {
        "key": "mp_view",              // Role identifier
        "displayName": "Viewer",       // Display name
        "description": "Can view reports"
      },
      {
        "key": "mp_manage",
        "displayName": "Manager",
        "description": "Can create and edit"
      }
    ],
    "roleMatrix": {
      "mp_view": [
        "module:view-reports:read"     // What this role can do
      ],
      "mp_manage": [
        "module:view-reports:read",
        "module:submit-report:create"
      ]
    },
    "defaultAccess": {
      "staff": ["mp_view"],            // Auto-assign roles
      "admin": ["mp_manage"]
    }
  }
}
```

---

## 🎯 Common Patterns & Recipes

### Pattern 1: Anonymous Submissions

Allow users to submit without identifying themselves:

```json
{
  "fields": [
    {
      "name": "is_anonymous",
      "type": "checkbox",
      "label": "Submit Anonymously"
    },
    {
      "name": "reporter_name",
      "type": "text",
      "label": "Your Name",
      "required": true,
      "showIf": {"is_anonymous": false}  // Only show if not anonymous
    }
  ]
}
```

### Pattern 2: Status Workflow

Track items through different states:

```json
{
  "name": "status",
  "type": "select",
  "label": "Status",
  "default_value": "draft",
  "options": [
    {"value": "draft", "label": "Draft", "color": "secondary"},
    {"value": "submitted", "label": "Submitted", "color": "info"},
    {"value": "approved", "label": "Approved", "color": "success"},
    {"value": "rejected", "label": "Rejected", "color": "danger"}
  ]
}
```

### Pattern 3: File Attachments

Allow users to upload files:

```json
{
  "name": "attachment",
  "type": "file",
  "label": "Upload Document",
  "required": false,
  "validation": {
    "maxSize": 10485760,              // 10MB
    "allowedExtensions": ["pdf", "doc", "docx", "jpg", "png"],
    "allowedMimeTypes": ["application/pdf", "image/jpeg", "image/png"]
  }
}
```

### Pattern 4: Related Records

Link to other entities (like users or vehicles):

```json
{
  "name": "assigned_to",
  "type": "user_select",
  "label": "Assign To",
  "required": false,
  "filterByRole": "counselor"  // Only show users with this role
}
```

---

## 🛡️ Security Best Practices

### ✅ DO:
- **Validate all inputs** with `minLength`, `maxLength`, `pattern`
- **Set rate limits** on all forms
- **Use least-privilege permissions** (give minimum access needed)
- **Define allowed file types** explicitly
- **Set maximum file sizes**
- **Use prepared statements** (automatic in Hub's Data API)

### ❌ DON'T:
- Include secrets, API keys, or passwords in manifest
- Use `eval()`, `exec()`, or dynamic code execution
- Allow unlimited file uploads
- Grant `*:*` (all permissions) to non-admin roles
- Skip input validation

---

## 🐛 Troubleshooting

### Issue: "Package name must be kebab-case"
**Solution**: Use lowercase letters and hyphens only. ✅ `my-package` ❌ `MyPackage` ❌ `my_package`

### Issue: "Table must start with namespace prefix"
**Solution**: All table names must begin with your namespace. ✅ `mp_reports` ❌ `reports`

### Issue: "Missing required column: tenant_id"
**Solution**: Every table must include standard columns (see [Database Entities](#2-database-entities---the-storage))

### Issue: "Field name must be snake_case"
**Solution**: Use lowercase with underscores. ✅ `incident_date` ❌ `incidentDate` ❌ `incident-date`

### Issue: "At least 2 screenshots required"
**Solution**: Add PNG or JPEG screenshots (1280×720+) to `screenshots/` directory

### Issue: "Package already exists"
**Solution**: Choose a different namespace or package name

---

## 📖 Advanced Topics

### Custom Migrations

For package updates, create migration files:

**packages/local/my-package/migrations/0002_add_priority_field.sql**
```sql
ALTER TABLE mp_report ADD COLUMN priority ENUM('low', 'medium', 'high') DEFAULT 'medium';
```

**packages/local/my-package/migrations/rollback/0002_rollback.sql**
```sql
ALTER TABLE mp_report DROP COLUMN priority;
```

Then reference in manifest:
```json
{
  "db": {
    "migrations": [
      {
        "version": "1.1.0",
        "up": "migrations/0002_add_priority_field.sql",
        "down": "migrations/rollback/0002_rollback.sql"
      }
    ]
  }
}
```

### Workflow Modules

Create multi-step approval processes:

```json
{
  "type": "Workflow",
  "slug": "approval-workflow",
  "entity": "mp_report",
  "steps": [
    {
      "id": "submitted",
      "label": "Submitted",
      "nextSteps": ["reviewing"],
      "requiredRole": null
    },
    {
      "id": "reviewing",
      "label": "Under Review",
      "nextSteps": ["approved", "rejected"],
      "requiredRole": "mp_manage",
      "requiredFields": ["review_notes"]
    },
    {
      "id": "approved",
      "label": "Approved",
      "nextSteps": [],
      "requiredRole": "mp_admin"
    }
  ]
}
```

---

## 📦 Package Lifecycle

```
1. DEVELOP
   ├── Scaffold with pkg-scaffold.php
   ├── Edit manifest.json
   ├── Add fields, modules, permissions
   └── Test locally

2. VALIDATE
   ├── Run pkg-lint.php
   ├── Fix errors and warnings
   └── Add screenshots

3. BUILD
   ├── Run pkg-build.php
   └── Creates .hubpkg file

4. TEST
   ├── Install on staging Hub
   ├── Test all features
   └── Verify permissions

5. SUBMIT
   ├── Fork hub-packages repo
   ├── Create pull request
   └── Wait for review

6. PUBLISH
   ├── Package approved
   ├── Merged to main branch
   └── Available in package registry
```

---

## 🎓 Learning Resources

- **Package Specification**: See [PACKAGE_SPECIFICATION_V2.md](PACKAGE_SPECIFICATION_V2.md)
- **Example Packages**: Browse `packages/local/employee-evaluation/`
- **Bootstrap Icons**: https://icons.getbootstrap.com/
- **Semantic Versioning**: https://semver.org/

---

## 💬 Get Help

- **Questions**: Open an issue on GitHub
- **Bug Reports**: Use GitHub Issues with `[BUG]` prefix
- **Feature Requests**: Use GitHub Issues with `[FEATURE]` prefix
- **Email**: tech@woodsonisd.net

---

## ✅ Quick Reference Checklist

Before submitting a package:

- [ ] Package name is kebab-case
- [ ] Namespace is 2-5 lowercase letters
- [ ] All table names start with namespace prefix
- [ ] All required columns present in each table
- [ ] Field names are snake_case
- [ ] All fields have validation rules
- [ ] Rate limiting on all forms
- [ ] At least 2 screenshots (1280×720+)
- [ ] README.md is complete
- [ ] CHANGELOG.md has version entry
- [ ] pkg-lint.php passes with no errors
- [ ] Package installs successfully
- [ ] All features tested

**Ready to create amazing packages!** 🚀
