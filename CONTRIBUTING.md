# Contributing to The Hub Package Repository

Thank you for your interest in contributing packages to The Hub ecosystem! This guide will help you submit high-quality packages that benefit the entire community.

## � Package Architecture Overview

Every package is defined by a `package.json` manifest that describes **everything**: metadata, database connection, pages, components, layout, responsive behavior, and access policies. The Hub's rendering engine reads this JSON and builds the UI automatically — no custom HTML templates needed.

```
packages/[category]/[package-id]/
├── package.json                       # The manifest (required)
├── [PackageName]Handler.php           # Data handler (optional — GenericPackageHandler covers most cases)
├── README.md                          # Package documentation
├── CHANGELOG.md                       # Version history
├── migrations/                        # Database migrations (auto-run on install)
│   ├── 001_create_tables.sql          # DDL — CREATE TABLE IF NOT EXISTS
│   └── 002_seed_data.sql             # Seed data — INSERT IGNORE
├── database/
│   └── schema.sql                     # Fallback schema (if no migrations/ dir)
└── screenshots/                       # At least 2 screenshots
```

### Schema Version

Always use `"schemaVersion": "3.0.0"` at the root of your `package.json`.

---

## 🧩 Package.json Structure

### Top-Level Keys

```json
{
    "schemaVersion": "3.0.0",
    "package": { ... },
    "database": { ... },
    "presentation": { ... },
    "data": { ... },
    "policy": { ... },
    "access": { ... }
}
```

### `package` — Metadata

```json
"package": {
    "id": "district.student-directory",
    "display_name": "Student Directory",
    "description": "Student records management — search, view, edit...",
    "version": "1.0.0",
    "author": "Woodson ISD Technology",
    "icon": "lucide-graduation-cap",
    "category": "district",
    "base_url": "/p/district.student-directory"
}
```

### `database` — Connection

```json
"database": {
    "connection": "woodson_hub",
    "primaryTable": "vm_vehicles",
    "auditTable": "vm_audit_logs"
}
```

> **⚠️ RULE: All package tables live in `woodson_hub`.** Use a short prefix (`vm_`, `sd_`, `br_`) to namespace your tables. Do NOT create separate databases — the Hub's `GenericPackageHandler` and migration system expect tables in the primary database. This enables native foreign keys to `users.id`, single-backup coverage, and zero DBA intervention.
>
> If your package reads from an **existing external database** (e.g., `woodson_students` for student records), you may reference it — but all tables your package **creates** must go in `woodson_hub`.

### Database Migrations

Package tables are created automatically during installation. Place SQL files in your package directory:

```
packages/local/my-package/
├── migrations/                        # Preferred: numbered SQL files
│   ├── 001_create_tables.sql         # DDL — CREATE TABLE IF NOT EXISTS
│   └── 002_seed_data.sql             # Seed data — INSERT IGNORE
└── database/
    └── schema.sql                     # Fallback: single schema file
```

**Migration Rules:**
- `PackageManager::installPackage()` auto-runs migrations after metadata install
- Priority: `migrations/*.sql` files (sorted by filename) → `database/schema.sql` fallback
- All `CREATE TABLE` must use `IF NOT EXISTS`
- All seed `INSERT` must use `INSERT IGNORE`
- Execution is tracked in `package_migrations` table — will not re-run
- Use `CHAR(26)` ULID primary keys for portability
- Use `INT UNSIGNED` for foreign keys to `users.id`
- Always include `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`
- Use `is_deleted BOOLEAN DEFAULT FALSE` for soft deletes — **never hard delete operational data**
- For records that should never be deleted (fuel logs, audit entries), rely on `workflow_status` instead — no delete column needed
- *Legacy note:* `GenericPackageHandler` also checks `is_active` for backward compatibility with older tables. New packages should use `is_deleted`.

---

## 🎨 Icons

The Hub uses **FontAwesome 6.5** (`fas fa-*`, `far fa-*`, `fab fa-*`) and **Bootstrap Icons** (`bi bi-*`) via CDN.

You may also reference icons using the **Lucide** naming convention (`lucide-*`). These are automatically mapped to FontAwesome equivalents at render time via `IconMapper`.

### Common Lucide → FontAwesome Mappings

| Lucide Name | Renders As |
|---|---|
| `lucide-users` | `fas fa-users` |
| `lucide-graduation-cap` | `fas fa-graduation-cap` |
| `lucide-eye` | `fas fa-eye` |
| `lucide-trash-2` | `fas fa-trash` |
| `lucide-printer` | `fas fa-print` |
| `lucide-backpack` | `fas fa-school` |
| `lucide-book-open` | `fas fa-book-open` |
| `lucide-pencil` | `fas fa-pencil` |
| `lucide-plus` | `fas fa-plus` |
| `lucide-download` | `fas fa-download` |
| `lucide-upload` | `fas fa-upload` |
| `lucide-search` | `fas fa-search` |
| `lucide-settings` | `fas fa-cog` |

Full mapping: `src/Package/IconMapper.php` (~80+ entries).

> **Tip:** Use `lucide-*` names in your JSON for readability. The renderer handles the conversion.

---

## 📄 Pages & Components

Each package defines pages under `presentation.pages`. Every page has a `route`, optional `layout`, and an array of `components`.

```json
"presentation": {
    "pages": {
        "index": {
            "title": "Student Directory",
            "route": "/",
            "layout": "full",
            "components": [ ... ]
        },
        "view": {
            "title": "Student Details",
            "route": "/view/{student_id}",
            "components": [ ... ]
        }
    }
}
```

### Page `layout` Options

| Value | Container | Max Width | Use Case |
|---|---|---|---|
| `"full"` | `container-fluid` | 1600px | Data-heavy pages with wide tables |
| `"standard"` (default) | `container` | ~1140px | Forms, detail views, simple pages |
| `"sidebar"` | Side panel layout | ~1140px | Master-detail views |
| `"narrow"` | Narrow centered | ~720px | Simple forms, settings |
| `"split"` | Split columns | ~1140px | Compare / dual-panel views |

> **For complete component rules, valid properties, and field types, see the [Component Stacks](#-component-stacks--the-rules-engine) section below.**

---

## 🗃️ Data Handlers

### GenericPackageHandler (No Code Required!)

As of v3.0.0, packages **do not need custom PHP handler classes**. The Hub's `GenericPackageHandler` automatically generates SQL from your `package.json`. It reads your `database`, `data.queries`, `data.mutations`, and component column definitions to build all the queries your package needs.

**How it works:**
- Query names starting with `list` → paginated list with search, sort, filter
- Query names starting with `get` and ending in `Stats` → aggregate COUNT queries
- Query names starting with `get` → single-record SELECT
- Query names containing `options` → DISTINCT value queries for dropdowns
- Mutations named `create*` → INSERT
- Mutations named `update*` → UPDATE
- Mutations named `delete*` → soft DELETE (`is_deleted = 1` if column exists, else `is_active = 0` for legacy tables)

**Query-level overrides** — add these inside any query definition:
```json
"data": {
    "queries": {
        "listStudents": {
            "table": "students",
            "select": "s.*, c.campus_name",
            "searchColumns": ["first_name", "last_name", "email"]
        }
    }
}
```

### Custom Handlers (Optional)

For complex logic that GenericPackageHandler can't cover, you can still create a custom PHP handler:

```php
// packages/district/student-directory/StudentQueryHandler.php
class StudentQueryHandler {
    public function listStudents(array $params): array {
        // Return ['data' => [...rows], 'meta' => ['total' => N, 'page' => 1, 'perPage' => 50]]
    }
    public function getStats(): array {
        // Return ['total' => 173, 'elementary' => 78, ...]
    }
    public function getStudent(array $params): array {
        // Return single student record
    }
}
```

---

## 🏗️ Component Stacks — The Rules Engine

The Hub uses a **"Stacks"** architecture. Each component type (called a **stack**) has a defined set of rules and boundaries. Package creators build within those constraints — and the system validates that every component follows the rules before it's allowed into production.

Think of it like building blocks: you pick a stack type, configure it with the allowed properties, and the rendering engine handles the rest.

### The 5 Stack Types

| Stack | Purpose | Required Config | Key Properties |
|-------|---------|-----------------|----------------|
| **dashboard** | KPI cards, charts, queues, quick links | `cards` | `columns`, `charts`, `queues`, `quickLinks` |
| **table** | Paginated, sortable data table | `columns` | `actions`, `bulkActions`, `pagination`, `emptyMessage` |
| **form** | Create/edit forms with sections | `mutation` | `layout`, `submitLabel`, `cancelUrl`, `sections`, `fields` |
| **detail** | Single-record detail view | *(none)* | `sections`, `actions`, `backUrl` |
| **filters** | Search and filter bar | `filters` | `targetTable` |

---

### Dashboard Stack

KPI summary panels with multiple sub-component types.

**Cards** (required):
```json
{
    "type": "dashboard",
    "config": {
        "columns": 4,
        "cards": [
            {
                "title": "Total Records",
                "icon": "fas fa-database",
                "dataKey": "total",
                "color": "primary"
            }
        ]
    }
}
```

| Card Property | Required | Values |
|---------------|----------|--------|
| `title` | ✅ | Any string |
| `icon` | ✅ | FontAwesome/Bootstrap icon class |
| `dataKey` | ⚠️ | Key in stats query response |
| `color` | ❌ | `primary`, `success`, `warning`, `danger`, `info`, `secondary`, `dark` |
| `format` | ❌ | `number`, `currency`, `percent` |
| `link` | ❌ | URL to navigate on click |

**Charts** (optional):
```json
"charts": [
    {
        "type": "bar",
        "title": "Records by Status",
        "dataKey": "byStatus",
        "labelKey": "status",
        "valueKey": "count"
    }
]
```

**Queues** (optional) — action queue panels showing pending items:
```json
"queues": [
    {
        "title": "Pending Approvals",
        "dataQuery": "getPendingApprovals",
        "emptyMessage": "All caught up!",
        "columns": ["title", "submitted_by", "date"]
    }
]
```

**Quick Links** (optional):
```json
"quickLinks": [
    {
        "label": "Add New Record",
        "url": "add",
        "icon": "fas fa-plus",
        "color": "success"
    }
]
```

---

### Table Stack

Paginated, sortable data tables with row-level and bulk actions.

```json
{
    "type": "table",
    "dataQuery": "listRecords",
    "config": {
        "columns": [
            {
                "key": "name",
                "label": "Name",
                "sortable": true,
                "searchable": true
            },
            {
                "key": "status",
                "label": "Status",
                "type": "badge",
                "badgeMap": {
                    "active": "success",
                    "inactive": "danger"
                }
            },
            {
                "key": "created_at",
                "label": "Created",
                "type": "date",
                "format": "M d, Y"
            }
        ],
        "actions": [
            { "type": "view", "label": "View", "icon": "fas fa-eye" },
            { "type": "edit", "label": "Edit", "icon": "fas fa-edit" },
            { "type": "delete", "label": "Delete", "icon": "fas fa-trash", "confirm": true }
        ]
    }
}
```

**Column types:** `text` (default), `badge`, `date`, `currency`, `link`, `email`, `boolean`, `image`

| Column Property | Required | Description |
|-----------------|----------|-------------|
| `key` | ✅ | Database column name |
| `label` | ✅ | Display header |
| `sortable` | ❌ | Enable column sorting (default: false) |
| `searchable` | ❌ | Include in search (default: false) |
| `type` | ❌ | Render type (see above) |
| `format` | ❌ | Date format string |
| `badgeMap` | ❌ | Value → color mapping for badge type |
| `width` | ❌ | CSS width (e.g., `"120px"`) |

---

### Form Stack

Create/edit forms with sections, grid layout, and field validation.

```json
{
    "type": "form",
    "config": {
        "mutation": "createRecord",
        "submitLabel": "Save Record",
        "layout": "standard",
        "sections": [
            {
                "title": "Basic Information",
                "columns": 2,
                "fields": [
                    {
                        "key": "name",
                        "label": "Full Name",
                        "type": "text",
                        "required": true,
                        "placeholder": "Enter full name"
                    },
                    {
                        "key": "email",
                        "label": "Email Address",
                        "type": "email",
                        "required": true
                    },
                    {
                        "key": "department",
                        "label": "Department",
                        "type": "select",
                        "optionsQuery": "getDepartments",
                        "required": true
                    }
                ]
            }
        ]
    }
}
```

### 14 Field Types

| Type | Rendered As | Special Properties |
|------|------------|-------------------|
| `text` | `<input type="text">` | `placeholder`, `maxlength`, `pattern` |
| `textarea` | `<textarea>` | `rows`, `placeholder`, `maxlength` |
| `number` | `<input type="number">` | `min`, `max`, `step` |
| `currency` | `<input>` with $ prefix | `min`, `max`, `step` (default 0.01) |
| `email` | `<input type="email">` | `placeholder` |
| `date` | Date picker | `min`, `max` |
| `datetime` | DateTime picker | `min`, `max` |
| `select` | `<select>` dropdown | `options` or `optionsQuery`, `multiple` |
| `radio` | Radio button group | `options` (required) |
| `checkbox` | Single checkbox | `checkedValue`, `uncheckedValue` |
| `file` | File upload | `accept`, `maxSize` |
| `password` | Masked input | `minlength` |
| `url` | `<input type="url">` | `placeholder` |
| `tel` | `<input type="tel">` | `placeholder`, `pattern` |
| `hidden` | Not rendered visually | `value` (default value) |

**Field properties that apply to ALL types:**
- `key` (required) — database column name
- `label` (required) — display label
- `type` (required) — one of the 14 types above
- `required` — boolean, is this field mandatory?
- `helpText` — tooltip or description below field
- `defaultValue` — pre-fill value
- `colSpan` — grid column span (1 or 2)
- `showIf` — conditional visibility rule

**Select/Radio `options` format:**
```json
"options": [
    { "value": "active", "label": "Active" },
    { "value": "inactive", "label": "Inactive" }
]
```
Or use `optionsQuery` to load from a data query:
```json
"optionsQuery": "getDepartments"
```

---

### Detail Stack

Single-record detail views with sections.

```json
{
    "type": "detail",
    "dataQuery": "getRecord",
    "config": {
        "sections": [
            {
                "title": "Record Details",
                "fields": [
                    { "key": "name", "label": "Name" },
                    { "key": "status", "label": "Status", "type": "badge" },
                    { "key": "created_at", "label": "Created", "type": "date" }
                ]
            }
        ],
        "actions": [
            { "label": "Edit", "url": "edit/{id}", "icon": "fas fa-edit", "color": "primary" },
            { "label": "Delete", "mutation": "deleteRecord", "icon": "fas fa-trash", "color": "danger", "confirm": true }
        ]
    }
}
```

---

### Filters Stack

Search and filter controls that target a table component on the same page.

```json
{
    "type": "filters",
    "config": {
        "targetTable": "recordsTable",
        "filters": [
            { "type": "search", "placeholder": "Search records..." },
            {
                "type": "select",
                "key": "status",
                "label": "Status",
                "options": [
                    { "value": "active", "label": "Active" },
                    { "value": "inactive", "label": "Inactive" }
                ]
            },
            {
                "type": "date_range",
                "key": "created_at",
                "label": "Date Range"
            }
        ]
    }
}
```

**Filter types:** `search`, `select`, `date`, `date_range`, `checkbox`

---

## 🔐 Policy & Access

### Policy Block

Define package-specific roles with **inheritance** and map them to district system roles:

```json
"policy": {
    "roles": {
        "user": {
            "label": "User",
            "description": "View data and submit records",
            "queries": ["listRecords", "getRecord"],
            "mutations": ["createRecord"]
        },
        "manager": {
            "label": "Manager",
            "inherits": "user",
            "description": "Review, approve, and configure",
            "queries": ["getStats"],
            "mutations": ["updateRecord", "approveRecord", "rejectRecord"]
        },
        "admin": {
            "label": "Administrator",
            "inherits": "manager",
            "description": "Full control including settings",
            "queries": ["listAll"],
            "mutations": ["deleteRecord", "updateSettings"]
        }
    },
    "defaultRole": "user",
    "globalRoleMapping": {
        "super_admin": "admin",
        "admin": "manager",
        "principal": "manager",
        "business_manager": "manager",
        "maintenance_director": "manager",
        "substitute_manager": "user",
        "counselor": "user",
        "maintenance_staff": "user",
        "custodial_manager": "user",
        "custodial": "user",
        "cafeteria": "user",
        "student": "user",
        "staff": "user"
    }
}
```

**Key concepts:**
- `roles` — package-specific roles with inheritance chains. `inherits` means the role gets ALL permissions from its parent + its own.
- `defaultRole` — fallback for unmapped system roles
- `globalRoleMapping` — maps district system roles to package roles

> **⚠️ RULE: Map ALL known district roles.** The resolver checks BOTH the user's primary role (`users.role`) AND any additional roles from Google Groups (`user_global_roles`). If a user has multiple roles from different sources, the **highest-privilege matching package role wins**. Unmapped roles fall back to `defaultRole`.
>
> **Available system roles:** `super_admin`, `admin`, `principal`, `business_manager`, `counselor`, `substitute_manager`, `maintenance_director`, `custodial_manager`, `maintenance_staff`, `custodial`, `cafeteria`, `student`, `staff`

### Dual-Path Permissions (Google Groups + Direct Roles)

The Hub supports two independent permission sources:

| Source | How It's Set | Storage |
|--------|-------------|--------|
| **Direct role** | Admin assigns via Users tab | `users.role` column |
| **Google Groups** | Auto-synced at login via Google Admin SDK | `user_global_roles` table |

`PackageAccessResolver` checks **both sources** and picks the highest-tier package role. This means:
- A `staff` user in a Google Group that maps to `admin` gets the `admin`-mapped package role
- A `principal` without any Google Group roles still gets their mapped package role
- `super_admin` always gets the highest-tier package role regardless of mappings

### Access Block (Layer 2 Workflow)

For packages that require manager approval of submitted records:

```json
"access": {
    "layer2": {
        "workflow": {
            "states": ["submitted", "in_review", "needs_correction", "resubmitted", "approved", "rejected"],
            "transitions": [
                {"from": "submitted", "to": "in_review", "actor": ["manager", "admin"]},
                {"from": "in_review", "to": "approved", "actor": ["manager", "admin"]},
                {"from": "in_review", "to": "needs_correction", "actor": ["manager", "admin"]},
                {"from": "in_review", "to": "rejected", "actor": ["manager", "admin"]},
                {"from": "needs_correction", "to": "resubmitted", "actor": ["user", "manager", "admin"]},
                {"from": "resubmitted", "to": "in_review", "actor": ["manager", "admin"]}
            ]
        },
        "editBoundaries": {
            "records": {
                "editable": ["field1", "field2"],
                "immutable": ["id", "created_at", "logged_by"],
                "requiresReason": true,
                "allowedStates": ["in_review", "needs_correction"]
            }
        },
        "auditEvents": [
            "RECORD_SUBMITTED",
            "RECORD_APPROVED",
            "RECORD_REJECTED"
        ]
    }
}
```

**Layer 2 database requirements:** Tables with workflow must include these columns:
```sql
workflow_status ENUM('draft','submitted','in_review','needs_correction','resubmitted','approved','rejected') DEFAULT 'draft',
reviewed_by INT UNSIGNED DEFAULT NULL,
reviewed_at TIMESTAMP NULL DEFAULT NULL,
review_notes TEXT DEFAULT NULL,
correction_reason TEXT DEFAULT NULL
```

**Workflow state semantics:**
| State | Meaning | Who Transitions |
|-------|---------|----------------|
| `draft` | Not yet submitted | User |
| `submitted` | Awaiting manager review | User |
| `in_review` | Manager is actively reviewing | Manager/Admin |
| `needs_correction` | Manager returned for fixes | Manager/Admin |
| `resubmitted` | User fixed and resubmitted | User |
| `approved` | Accepted — record is immutable | Manager/Admin |
| `rejected` | Denied — requires new submission | Manager/Admin |

---

## ✅ Validation Rules

The Hub validates every `package.json` before it's installed. The validator checks:

1. **Structure** — all required top-level keys present
2. **Package metadata** — valid ID format (`category.package-name`), semver version, valid category
3. **Database** — connection and primaryTable defined
4. **Pages** — every page has a valid route, layout, and at least one component
5. **Components** — each component matches its stack type rules (see above)
6. **Data** — all queries and mutations referenced by components are defined
7. **Policy** — roles array and defaultRole present, globalRoleMapping uses valid system roles
8. **Cross-references** — no dangling references (e.g., a table's `dataQuery` that doesn't exist in `data.queries`)

**Valid categories:** `district`, `operations`, `campus`, `custom`

**Valid layouts:** `full`, `standard`, `sidebar`, `narrow`, `split`

**Valid system roles for globalRoleMapping:** `super_admin`, `admin`, `principal`, `business_manager`, `counselor`, `substitute_manager`, `maintenance_director`, `custodial_manager`, `maintenance_staff`, `custodial`, `cafeteria`, `student`, `staff`

> These are the 13 roles defined in the `users.role` and `user_global_roles.role` ENUM columns. The validator will warn on any role not in this list. Do **not** invent roles like `district_admin`, `campus_admin`, or `viewer` — they don't exist in the database.

### Running Validation

Use the Package Schema API:
```bash
curl -X POST /api/package-schema.php?action=validate \
  -H "Content-Type: application/json" \
  -d @packages/district/my-package/package.json
```

Response:
```json
{
    "valid": true,
    "errors": [],
    "warnings": [
        {
            "path": "$.policy.globalRoleMapping",
            "message": "Role 'custom_role' not in system roles",
            "fix": "Use standard system roles"
        }
    ],
    "summary": { "errors": 0, "warnings": 1 }
}
```

---

## 🧙 Package Creator Wizard

Don't want to write JSON by hand? Use the **Package Creator Wizard** at `/admin/packages/create`. It walks you through 6 steps:

1. **Package Info** — name, category, icon, description
2. **Database** — connection name, primary table
3. **Fields** — define your columns (type, required, sortable, searchable)
4. **Pages & Components** — add pages and pick stack types
5. **Policy** — set roles and permissions
6. **Preview & Validate** — see the generated JSON, fix any issues, download

The wizard generates a valid `package.json` that works with GenericPackageHandler out of the box.

---

## �📋 Before You Submit

### Pre-Submission Checklist

- [ ] Package passes all validation checks in The Hub
- [ ] Package has been tested in a production-like environment
- [ ] All fields have appropriate validation rules
- [ ] Permissions are properly configured
- [ ] Package follows semantic versioning
- [ ] README.md is complete with screenshots
- [ ] CHANGELOG.md documents all features
- [ ] No sensitive data (API keys, passwords, etc.) in package
- [ ] Package ID follows naming convention: `category.package-name`
- [ ] **Database: tables use `woodson_hub` with a unique prefix** (e.g., `vm_`, `sd_`)
- [ ] **Database: migration files in `migrations/` use `CREATE TABLE IF NOT EXISTS`**
- [ ] **Database: seed data uses `INSERT IGNORE`**
- [ ] **Policy: `globalRoleMapping` maps ALL known district roles** (not just `staff`, `admin`, `super_admin`)
- [ ] **Policy: roles define `inherits` chains for proper permission inheritance**
- [ ] **Layer 2: workflow tables include `workflow_status`, `reviewed_by`, `reviewed_at` columns**
- [ ] **Layer 2: uses correct workflow states** (`needs_correction` for send-back, `resubmitted` for re-submit)
- [ ] **Layer 2: edit boundaries use actual SQL column names** (not presentation aliases)
- [ ] **Soft deletes: new tables use `is_deleted` flag** (legacy `is_active` also supported) — **no hard DELETE of operational data**

## 🎯 Package Naming Convention

**Package ID Format:** `category.descriptive-name`

Examples:
- ✅ `district.bullying-report`
- ✅ `operations.vehicle-maintenance`
- ✅ `campus.employee-evaluation`
- ❌ `bullying-report` (missing category prefix)
- ❌ `MyPackage` (not descriptive, no category)
- ❌ `district/bullying_report` (use dots + hyphens, not slashes + underscores)
- ❌ `report` (too generic)

The `category` prefix must match the `category` field in your `package.json` and the folder structure under `packages/`.

## 📦 Submission Process

### 1. Fork the Repository
```bash
git clone https://github.com/woodsonisd/TheHub-Package-Repo.git
cd TheHub-Package-Repo
git checkout -b add-your-package-name
```

### 2. Create Package Directory
```bash
mkdir -p packages/[category]/[package-id]/
cd packages/[category]/[package-id]/
```

### 3. Add Required Files

```
packages/[category]/[package-id]/
├── package.json                       # The manifest (required)
├── [package-id]_[version].hubpkg      # Built package file
├── README.md                          # Package documentation
├── CHANGELOG.md                       # Version history
├── migrations/                        # Database migrations (auto-run on install)
│   ├── 001_create_tables.sql          # DDL — CREATE TABLE IF NOT EXISTS
│   └── 002_seed_data.sql             # Seed data — INSERT IGNORE
└── screenshots/                       # At least 2 screenshots
    ├── main-view.png
    └── admin-view.png
```

### 4. Update Main README.md

Add your package to the "Available Packages" table in the main README.md:

```markdown
| **Your Package** | 1.0.0 | Brief description | [Download](packages/category/package-id/package-id_1.0.0.hubpkg) |
```

### 5. Submit Pull Request

```bash
git add .
git commit -m "Add [Package Name] v[version]"
git push origin add-your-package-name
```

Then open a pull request with:
- Clear title: "Add [Package Name] v[version]"
- Description of what the package does
- Screenshots demonstrating functionality
- Any special installation notes

## ✅ Package Quality Standards

### Required Documentation

#### README.md Must Include:
1. **Package name and version**
2. **Clear description** (2-3 sentences)
3. **Feature list** (bullet points)
4. **System requirements**
5. **Installation instructions**
6. **Permission requirements**
7. **Field definitions** (all fields explained)
8. **Screenshots** (minimum 2)
9. **Configuration steps** (if applicable)
10. **Support information**

#### CHANGELOG.md Must Include:
1. Version number and date
2. **Added** - New features
3. **Changed** - Changes in existing functionality
4. **Deprecated** - Soon-to-be removed features
5. **Removed** - Removed features
6. **Fixed** - Bug fixes
7. **Security** - Vulnerability fixes

### Code Quality

#### Security Requirements
- ✅ All user input must be validated
- ✅ No SQL injection vulnerabilities (use prepared statements)
- ✅ XSS protection on all text fields
- ✅ File uploads must validate type and size
- ✅ No hardcoded credentials or API keys
- ✅ Sensitive data must use proper encryption

#### Field Validation
- ✅ All required fields marked as `required: true`
- ✅ Text fields have `max_length` limits
- ✅ Email fields use `type: "email"`
- ✅ Dates use `type: "date"` or `type: "datetime"`
- ✅ Numbers have `min_value` and `max_value` when applicable
- ✅ File uploads have `allowed_extensions` and `max_file_size`

#### Permission Best Practices
- ✅ Use principle of least privilege
- ✅ Separate view/create/edit/delete permissions
- ✅ Document which roles should have access
- ✅ Consider anonymous access only when necessary

### User Experience

#### Form Design
- Clear, descriptive field labels
- Helpful `help_text` for complex fields
- Logical field ordering (related fields grouped)
- Required fields clearly marked
- Validation errors provide actionable feedback

#### Screenshots
- High resolution (at least 1280x720)
- Show actual usage, not just empty forms
- Include both user and admin views
- Annotate if helpful (arrows, highlights)
- PNG format preferred

## 🔍 Review Process

### What We Check

1. **Security Review**
   - No vulnerabilities detected
   - Input validation proper
   - File upload restrictions appropriate

2. **Code Quality**
   - Package validates in The Hub
   - All required fields present
   - Semantic versioning followed
   - No conflicts with existing packages

3. **Documentation**
   - README complete and clear
   - CHANGELOG follows format
   - Screenshots demonstrate functionality
   - Installation steps accurate

4. **Testing**
   - Package installs successfully
   - All fields function as documented
   - Permissions work correctly
   - No errors in browser console

### Review Timeline

- Initial review within 3 business days
- Feedback provided via pull request comments
- Approval/merge within 1 week for compliant submissions

## 🐛 Reporting Issues

### Found a Bug in a Package?

Open an issue with:
- **Package name and version**
- **Expected behavior**
- **Actual behavior**
- **Steps to reproduce**
- **The Hub version**
- **PHP/MySQL versions**
- **Error messages** (if any)

### Security Vulnerabilities

**DO NOT** open public issues for security vulnerabilities. Email security@woodsonisd.net with:
- Package name and version
- Vulnerability description
- Proof of concept (if possible)
- Suggested fix (if known)

## 🆕 Updating Existing Packages

### Version Guidelines

**MAJOR** version (x.0.0):
- Incompatible field changes (renamed/removed fields)
- Breaking permission changes
- Requires manual migration

**MINOR** version (0.x.0):
- New fields added
- New features
- Enhanced functionality
- Backwards-compatible

**PATCH** version (0.0.x):
- Bug fixes
- Documentation updates
- Performance improvements
- No new features

### Update Process

1. Test update path in The Hub (upgrade from previous version)
2. Update version in package file
3. Add new `.hubpkg` file to package directory
4. Update CHANGELOG.md
5. Update README.md version references
6. Update main README.md table
7. Submit pull request titled "Update [Package Name] to v[version]"

## 📜 License

By submitting a package, you agree to:
- License your package under MIT License (or compatible)
- Grant permission for The Hub instances to use/modify your package
- Provide ongoing support (bug fixes, security updates)
- Allow others to fork and extend your package

## 🤝 Community

### Get Help

- **Questions?** Open a discussion in the repository
- **Need guidance?** Review existing packages as examples
- **Technical issues?** Check The Hub documentation

### Package Ideas

Not sure what to build? Check the "Requested Packages" discussion for community needs.

## 📞 Contact

- **General questions:** Open a discussion
- **Bug reports:** Open an issue
- **Security concerns:** security@woodsonisd.net
- **Package submissions:** Pull request

---

Thank you for contributing to The Hub ecosystem! 🎉
