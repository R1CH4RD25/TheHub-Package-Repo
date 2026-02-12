# Contributing to The Hub Package Repository

Thank you for your interest in contributing packages to The Hub ecosystem! This guide will help you submit high-quality packages that benefit the entire community.

## � Package Architecture Overview

Every package is defined by a `package.json` manifest that describes **everything**: metadata, database connection, pages, components, layout, responsive behavior, and access policies. The Hub's rendering engine reads this JSON and builds the UI automatically — no custom HTML templates needed.

```
packages/[category]/[package-id]/
├── package.json                       # The manifest (required)
├── [PackageName]Handler.php           # Data handler (queries & mutations)
├── [PackageName]QueryHandler.php      # Query-only handler (optional split)
├── README.md                          # Package documentation
├── CHANGELOG.md                       # Version history
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
    "connection": "woodson_students",
    "primaryTable": "students",
    "auditTable": "audit_log"
}
```

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

---

## 📊 Dashboard Component

Renders KPI stat cards in a responsive grid.

```json
{
    "type": "dashboard",
    "config": {
        "columns": 4,
        "cards": [
            {
                "title": "Total Students",
                "dataKey": "total",
                "icon": "lucide-users",
                "color": "primary"
            },
            {
                "title": "Elementary",
                "dataKey": "elementary",
                "icon": "lucide-backpack",
                "color": "success"
            }
        ]
    },
    "dataQuery": "getStats"
}
```

### Card Properties

| Property | Type | Description |
|---|---|---|
| `title` | string | Card label displayed below the value |
| `dataKey` | string | Key to look up the numeric value from query results |
| `icon` | string | Icon name (lucide-* or fas fa-*) |
| `color` | string | `primary`, `success`, `warning`, `danger`, `info` |
| `link` | string | Optional route to navigate on click |
| `format` | string | `number` (default), `currency`, `percent` |

### Column Layout

| `columns` | Desktop | Tablet (≤768px) | Phone (≤480px) |
|---|---|---|---|
| `2` | 2 across | 2 across | 1 stacked |
| `3` | 3 across | 2 across | 1 stacked |
| `4` | 4 across | 2 across | 1 stacked |

---

## 🔍 Filters Component

Renders search bars and filter dropdowns above a table.

```json
{
    "type": "filters",
    "config": {
        "targetTable": "pkg-table-students",
        "filters": [
            {
                "name": "search",
                "type": "search",
                "placeholder": "Search by name, ID, or email...",
                "debounce": 300
            },
            {
                "name": "grade",
                "type": "select",
                "label": "Grade",
                "options": [
                    {"value": "PK", "label": "PK"},
                    {"value": "KG", "label": "KG"}
                ]
            },
            {
                "name": "graduation_year",
                "type": "select",
                "label": "Graduation Year",
                "optionsQuery": "getGraduationYears"
            }
        ]
    }
}
```

### Filter Types

| Type | Description | Special Props |
|---|---|---|
| `search` | Text input with search icon | `placeholder`, `debounce` (ms) |
| `select` | Dropdown with options | `options` array or `optionsQuery` |
| `date` | Date picker | — |
| `date_range` | Start/end date pair | `startParam`, `endParam` |

> **`optionsQuery`**: References a handler query method. The options are auto-populated from query results at render time.

---

## 📋 Table Component

Renders paginated, sortable data tables with row actions and bulk operations.

```json
{
    "type": "table",
    "config": {
        "id": "pkg-table-students",
        "columns": [ ... ],
        "actions": [ ... ],
        "bulkActions": [ ... ],
        "pagination": { "perPage": 50, "pageSizes": [25, 50, 100, 200] }
    },
    "dataQuery": "listStudents"
}
```

### Column Definition

```json
{
    "key": "full_name",
    "label": "Name",
    "sortable": true,
    "style": "text",
    "width": "25%",
    "responsive": "hide-mobile",
    "copyable": false
}
```

| Property | Type | Default | Description |
|---|---|---|---|
| `key` | string | **required** | Database column name |
| `label` | string | = key | Display header text |
| `sortable` | bool | `false` | Enable click-to-sort |
| `style` | string | `"text"` | Rendering style (see below) |
| `width` | string | auto | CSS width (`"25%"`, `"150px"`) — applied on desktop |
| `responsive` | string | (visible) | Responsive visibility rule (see below) |
| `copyable` | bool | `false` | Show copy-to-clipboard button |

### Column Styles

| Style | Renders As |
|---|---|
| `text` | Plain text (default) |
| `badge` | Colored badge. Color auto-inferred for `grade`, `ou_for_google`, etc. |
| `masked` | Dots with Show/Hide toggle button (for passwords, SSNs) |
| `link` | Clickable hyperlink |
| `date` | Formatted as `Mar 5, 2026` |
| `datetime` | Formatted as `Mar 5, 2026 2:30pm` |
| `currency` | Formatted as `$1,234.56` |
| `boolean` | Green check or red X icon |

### Responsive Column Visibility

Use the `responsive` property to control which columns show on smaller screens. Each package defines its **own** responsive rules — there is no global default.

| Value | Behavior |
|---|---|
| *(omitted)* | Always visible on all screen sizes |
| `"hide-mobile"` | Hidden at ≤ 768px (phones) |
| `"hide-tablet"` | Hidden at ≤ 1024px (tablets and phones) |

**Design guideline:** On mobile, show only the **essential** 3-4 columns plus the action button. For Student Directory, that's: Name, Grade, Password, View.

Bulk action checkboxes are automatically hidden on mobile.

### Column Width Guidelines

Define `width` as percentages for consistent desktop layout. Columns should roughly total ~95% (the action column takes the rest).

```json
"columns": [
    { "key": "student_id",       "width": "10%", "responsive": "hide-mobile" },
    { "key": "full_name",        "width": "25%" },
    { "key": "grade",            "width": "8%"  },
    { "key": "chromebook_login", "width": "20%", "responsive": "hide-mobile" },
    { "key": "password",         "width": "17%" },
    { "key": "ou_for_google",    "width": "15%", "responsive": "hide-mobile" }
]
```

### Row Actions

```json
"actions": [
    {
        "label": "View",
        "icon": "lucide-eye",
        "type": "route",
        "route": "/view/{student_id}",
        "variant": "warning"
    }
]
```

| Property | Description |
|---|---|
| `label` | Button text |
| `icon` | Icon name (lucide-* or fa-*) |
| `type` | `"route"` (navigate) or `"mutation"` (API call) |
| `route` | URL pattern — `{column_key}` is replaced with row data |
| `variant` | Bootstrap color: `warning`, `primary`, `danger`, `info`, `success` |

### Bulk Actions

```json
"bulkActions": [
    {
        "label": "Print Cards",
        "mutation": "printCards",
        "icon": "lucide-printer",
        "variant": "info"
    },
    {
        "label": "Delete Selected",
        "mutation": "bulkDelete",
        "icon": "lucide-trash-2",
        "variant": "danger",
        "confirm": "Are you sure you want to delete the selected students?"
    }
]
```

> Bulk actions are hidden on mobile. The `confirm` property shows a modal before executing.

---

## 📝 Detail Component

Renders a read-only detail view for a single record.

```json
{
    "type": "detail",
    "config": {
        "sections": [
            {
                "title": "Student Information",
                "icon": "lucide-user",
                "fields": [
                    { "key": "full_name", "label": "Name" },
                    { "key": "grade", "label": "Grade", "style": "badge" },
                    { "key": "password", "label": "Password", "style": "masked", "copyable": true }
                ]
            }
        ],
        "backRoute": "/",
        "actions": [
            { "label": "Edit", "icon": "lucide-pencil", "route": "/edit/{student_id}", "variant": "warning" }
        ]
    },
    "dataQuery": "getStudent"
}
```

---

## 📝 Form Component

Renders create/edit forms with validation.

```json
{
    "type": "form",
    "config": {
        "fields": [
            { "key": "first_name", "label": "First Name", "type": "text", "required": true },
            { "key": "grade", "label": "Grade", "type": "select", "options": [...] },
            { "key": "email", "label": "Email", "type": "email" }
        ],
        "submitMutation": "createStudent",
        "cancelRoute": "/",
        "submitLabel": "Save Student"
    }
}
```

---

## 🗃️ Data Handlers

Each package needs a PHP handler class that implements query methods referenced by `dataQuery` and mutation methods referenced by `mutation`.

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
- [ ] Package ID follows naming convention: `category-package-name`

## 🎯 Package Naming Convention

**Package ID Format:** `category-descriptive-name`

Examples:
- ✅ `bullying-report`
- ✅ `employee-evaluation`
- ✅ `maintenance-request`
- ❌ `MyPackage` (not descriptive)
- ❌ `bullying_report` (use hyphens, not underscores)
- ❌ `report` (too generic)

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
├── [package-id]_[version].hubpkg     # The package file
├── README.md                          # Package documentation
├── CHANGELOG.md                       # Version history
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
