# The Hub Package Repository

Official package repository for The Hub dynamic sections system. This repository contains validated `.hubpkg` packages that extend The Hub with custom forms, reporting tools, and workflows.

## 📦 Package Categories

Browse packages by category:

### 📊 [Reporting](packages/reporting/)
Incident reporting, data collection, and compliance documentation.
- **[Bullying Report](packages/reporting/bullying-report/)** `v1.0.0` - Comprehensive bullying incident reporting system

### �� [Analytics](packages/analytics/)
Data analysis, visualization, and reporting dashboards.
- _No packages available yet_

### 📝 [Forms](packages/forms/)
Custom form builders and data collection tools.
- _No packages available yet_

### 🔗 [Integrations](packages/integrations/)
Third-party integrations and API connectors.
- _No packages available yet_

### 🔀 [Redirects](packages/redirects/)
URL management, short links, and redirect tools.
- _No packages available yet_

### ⚙️ [Workflows](packages/workflows/)
Automated workflows, approval processes, and task management.
- _No packages available yet_

### 🏗️ [Operations](packages/operations/)
School district operations, facilities management, fleet tracking, and maintenance systems.
- **[Vehicle Maintenance & Fleet Tracking](packages/operations/fleet/vehicle-maintenance/)** `v1.0.0` - Comprehensive fleet management system

### 💰 [Finance](packages/finance/)
Financial management, reimbursements, budgeting, and expense tracking.
- **[Reimbursement Request & Fuel Tracking](packages/finance/reimbursement-request/)** `v1.0.0` - Unified monetary and fuel reimbursement system with workflow

### 👨‍🎓 [Student](packages/student/)
Student-facing tools and resources.
- **[Bullying Report](packages/student/safety/)** `v1.0.0` - Anonymous bullying incident reporting (also in Reporting category)

### 🏫 [District](packages/district/)
District-wide administration, records management, and institutional data tools.
- **[Student Directory](packages/district/student-directory/)** `v1.0.0` - Student records management with Google Workspace integration


## 📂 Repository Structure

```
packages/
├── analytics/          # Data visualization & dashboards
├── district/           # District administration & records
│   └── student-directory/
│       ├── student-directory_1.0.0.hubpkg
│       ├── README.md
│       ├── CHANGELOG.md
│       ├── LICENSE
│       └── screenshots/
├── forms/              # Form builders & surveys
├── integrations/       # Third-party connectors
├── operations/         # Fleet, facilities, operations management
│   └── fleet/
│       └── vehicle-maintenance/
│           ├── manifest.json
│           ├── README.md
│           ├── CHANGELOG.md
│           ├── LICENSE
│           └── screenshots/
├── redirects/          # URL management tools
├── reporting/          # Incident reporting & compliance
│   └── bullying-report/
├── student/            # Student-facing tools
│   └── safety/
│       └── bullying-report/
└── workflows/          # Automation & approval systems
```

Each package is self-contained with:
- ✅ Version folder (e.g., `1.0.0/`)
- ✅ Package file (`.hubpkg`)
- ✅ Documentation (`README.md`)
- ✅ Version history (`CHANGELOG.md`)

## 🚀 Quick Start

### Installing a Package

1. Download the `.hubpkg` file from this repository
2. Log in to The Hub as an administrator
3. Navigate to **Admin** → **Package Manager**
4. Click **Upload Package** and select the `.hubpkg` file
5. Click **Validate Package** to run compatibility checks
6. Review the validation report
7. Click **Install** if all checks pass
8. Configure section access in **Admin** → **Sections**

### Package Format

The Hub packages use the `.hubpkg` format — a JSON-based manifest (**Schema v3.0.0**) that declaratively defines the entire package UI:

- **Metadata** (`package`): ID, version, author, icon, category, base URL
- **Database** (`database`): Connection name, primary table, audit table
- **Presentation** (`presentation.pages`): Page definitions with route, layout, and component arrays
- **Components**: Dashboard (KPI cards), Table (data grids), Filters (search bars), Detail (record view), Form (create/edit)
- **Data** (`data`): Query and mutation handler mappings
- **Policy** (`policy`): RBAC rules, role hierarchy, permissions
- **Access** (`access`): Role requirements per page/action

> No custom HTML templates are needed — the rendering engine reads the JSON and builds the UI.

### Icons

Use **Lucide** naming (`lucide-*`) in your JSON for readability. The Hub's `IconMapper` automatically converts them to **FontAwesome 6.5** equivalents at render time (80+ mappings). You can also use `fas fa-*`, `far fa-*`, `fab fa-*`, or `bi bi-*` directly.

Common mappings: `lucide-users` → `fas fa-users`, `lucide-eye` → `fas fa-eye`, `lucide-graduation-cap` → `fas fa-graduation-cap`, `lucide-trash-2` → `fas fa-trash`.

### Responsive Design

Each package controls its own mobile behavior via column-level `responsive` properties:

| Value | Behavior |
|---|---|
| *(omitted)* | Always visible |
| `"hide-mobile"` | Hidden at ≤ 768px |
| `"hide-tablet"` | Hidden at ≤ 1024px |

Set `"layout": "full"` on a page to use a wide container (1600px) for data-heavy views, or `"standard"` (default, ~1140px) for forms.

## 📋 Package Specification

### Semantic Versioning

All packages follow [Semantic Versioning 2.0.0](https://semver.org/):

- **MAJOR** version: Incompatible API changes
- **MINOR** version: New functionality (backwards-compatible)
- **PATCH** version: Bug fixes (backwards-compatible)

### Schema v3.0.0 Structure

```json
{
  "schemaVersion": "3.0.0",
  "package": {
    "id": "category.package-name",
    "display_name": "Human Readable Name",
    "description": "What the package does",
    "version": "1.0.0",
    "author": "Author Name",
    "icon": "lucide-icon-name",
    "category": "district",
    "base_url": "/p/category.package-name"
  },
  "database": {
    "connection": "database_name",
    "primaryTable": "main_table",
    "auditTable": "audit_log"
  },
  "presentation": {
    "pages": {
      "index": {
        "title": "Page Title",
        "route": "/",
        "layout": "full",
        "components": [
          {
            "type": "dashboard",
            "config": {
              "columns": 4,
              "cards": [
                { "title": "Total", "dataKey": "total", "icon": "lucide-users", "color": "primary" }
              ]
            },
            "dataQuery": "getStats"
          },
          {
            "type": "filters",
            "config": {
              "targetTable": "pkg-table-main",
              "filters": [
                { "name": "search", "type": "search", "placeholder": "Search..." },
                { "name": "status", "type": "select", "label": "Status", "options": [...] }
              ]
            }
          },
          {
            "type": "table",
            "config": {
              "id": "pkg-table-main",
              "columns": [
                { "key": "name",  "label": "Name",  "sortable": true, "width": "25%" },
                { "key": "grade", "label": "Grade", "style": "badge", "width": "8%" },
                { "key": "pass",  "label": "Password", "style": "masked", "responsive": "hide-mobile" }
              ],
              "actions": [
                { "label": "View", "icon": "lucide-eye", "type": "route", "route": "/view/{id}", "variant": "warning" }
              ],
              "bulkActions": [
                { "label": "Delete", "mutation": "bulkDelete", "icon": "lucide-trash-2", "variant": "danger", "confirm": "Are you sure?" }
              ],
              "pagination": { "perPage": 50 }
            },
            "dataQuery": "listRecords"
          }
        ]
      }
    }
  },
  "data": { ... },
  "policy": { ... },
  "access": { ... }
}
```

### Column Styles

| Style | Renders As |
|---|---|
| `text` | Plain text (default) |
| `badge` | Colored badge (auto-colors for grade, school, status) |
| `masked` | Bullet dots with Show/Hide toggle (passwords, SSNs) |
| `link` | Clickable hyperlink |
| `date` | Formatted as `Mar 5, 2026` |
| `datetime` | `Mar 5, 2026 2:30pm` |
| `currency` | `$1,234.56` |
| `boolean` | Green check / red X icon |

### Dashboard Card Colors

`primary` (blue), `success` (green), `warning` (amber), `danger` (red), `info` (sky blue)

### Row Action Variants

`primary`, `warning`, `danger`, `info`, `success` — maps to Bootstrap button colors.

## 🔒 Security & Validation

All packages in this repository have passed The Hub's comprehensive validation system:

- ✅ **Structure Validation**: Required fields, format compliance
- ✅ **System Requirements**: PHP version, MySQL version, extensions
- ✅ **Dependency Resolution**: Required packages, conflict detection
- ✅ **Field Validation**: Data types, constraints, validation rules
- ✅ **Permission Analysis**: Role requirements, access controls
- ✅ **Security Scan**: SQL injection, XSS, path traversal checks
- ✅ **Database Impact**: Schema changes, migration safety
- ✅ **Menu Integration**: Navigation structure, conflicts
- ✅ **Version Compatibility**: Hub version requirements
- ✅ **Performance Impact**: Query complexity, resource usage

## 🛠️ Creating Your Own Package

### Package Directory Structure

```
packages/[category]/[package-id]/
├── package.json                       # Source manifest (schema v3.0.0)
├── [package-id]_[version].hubpkg      # Distributable copy of package.json
├── [PackageName]Handler.php           # Data query/mutation handler (optional)
├── README.md                          # Package documentation
├── CHANGELOG.md                       # Version history
├── LICENSE                            # License file
└── screenshots/                       # At least 2 screenshots
    ├── main-view.png
    └── detail-view.png
```

### Quick Example

Here's a minimal package with a dashboard + table:

```json
{
  "schemaVersion": "3.0.0",
  "package": {
    "id": "district.staff-directory",
    "display_name": "Staff Directory",
    "version": "1.0.0",
    "author": "Your Name",
    "icon": "lucide-users",
    "category": "district",
    "base_url": "/p/district.staff-directory"
  },
  "database": {
    "connection": "your_database",
    "primaryTable": "staff"
  },
  "presentation": {
    "pages": {
      "index": {
        "title": "Staff Directory",
        "route": "/",
        "layout": "full",
        "components": [
          {
            "type": "dashboard",
            "config": {
              "columns": 3,
              "cards": [
                { "title": "Total Staff", "dataKey": "total", "icon": "lucide-users", "color": "primary" },
                { "title": "Teachers", "dataKey": "teachers", "icon": "lucide-book-open", "color": "success" },
                { "title": "Support", "dataKey": "support", "icon": "lucide-briefcase", "color": "info" }
              ]
            },
            "dataQuery": "getStats"
          },
          {
            "type": "filters",
            "config": {
              "targetTable": "pkg-table-staff",
              "filters": [
                { "name": "search", "type": "search", "placeholder": "Search by name or email..." },
                { "name": "department", "type": "select", "label": "Department", "optionsQuery": "getDepartments" }
              ]
            }
          },
          {
            "type": "table",
            "config": {
              "id": "pkg-table-staff",
              "columns": [
                { "key": "name", "label": "Name", "sortable": true, "width": "30%" },
                { "key": "email", "label": "Email", "width": "25%", "responsive": "hide-mobile" },
                { "key": "department", "label": "Department", "style": "badge", "width": "20%" },
                { "key": "phone", "label": "Phone", "width": "15%", "responsive": "hide-mobile" }
              ],
              "actions": [
                { "label": "View", "icon": "lucide-eye", "type": "route", "route": "/view/{id}", "variant": "warning" }
              ],
              "pagination": { "perPage": 50 }
            },
            "dataQuery": "listStaff"
          }
        ]
      }
    }
  }
}
```

### Design Guidelines

- **Dashboard**: Use `"columns": 4` for 4 KPI cards, `3` for 3, etc. Cards auto-stack to 2-col on tablet, 1-col on phone.
- **Tables**: Set `width` as percentages (~95% total). Mark non-essential columns `"responsive": "hide-mobile"`. Show only 3-4 columns + action on mobile.
- **Masked fields**: Use `"style": "masked"` for passwords/SSNs. Users click Show/Hide — works on desktop and mobile.
- **Icons**: Use `lucide-*` for readability (auto-mapped to FontAwesome). See `IconMapper.php` for the full 80+ mapping list.
- **Layout**: Use `"layout": "full"` for data-heavy index pages. Use `"standard"` for forms and detail views.

### Submit Your Package

1. Create your `package.json` following the schema above
2. Copy it to `[package-id]_[version].hubpkg`
3. Test thoroughly in your Hub instance
4. Fork this repository
5. Add your package to the appropriate category folder
6. Update the categories list in this README
7. Submit a pull request with:
   - Package file + README + CHANGELOG + screenshots
   - Installation/configuration notes
   - Any special database requirements

## 📖 Documentation

- [Package JSON Schema Reference (CONTRIBUTING.md)](https://github.com/R1CH4RD25/TheHub/blob/laravel-migration/CONTRIBUTING.md) - Complete schema v3.0.0 docs
- [Package Architecture Spec](https://github.com/R1CH4RD25/TheHub/blob/laravel-migration/PACKAGE_ARCHITECTURE_SPEC.md) - Deep architecture details
- [Icon Mapper Source](https://github.com/R1CH4RD25/TheHub/blob/laravel-migration/src/Package/IconMapper.php) - Full Lucide → FontAwesome mapping

## 🤝 Contributing

Contributions are welcome! Please read our contributing guidelines before submitting packages.

### Package Review Criteria

Submitted packages will be reviewed for:
- Code quality and security
- Documentation completeness
- User experience design
- Compatibility with latest Hub version
- Test coverage (if applicable)

## 📜 License

This repository is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Individual packages may have their own licenses - check package documentation.

## 🏫 About The Hub

The Hub is a modular web application built for Woodson ISD to manage various administrative functions including vehicle management, fuel tracking, and custom dynamic sections for reporting and data collection.

**System Requirements:**
- PHP 8.2+
- MySQL 10.11+
- Apache/Nginx with mod_rewrite

## 📞 Support

For issues, questions, or feature requests:
- Open an issue in this repository
- Contact The Hub administrators at your institution

## 🔄 Version History

### Repository v1.1.0 (February 2026)
- Updated to Schema v3.0.0 (presentation pages, components, responsive columns)
- Student Directory v1.0.0 updated with responsive mobile config, column widths
- README & CONTRIBUTING updated with full schema reference
- Icon system: Lucide → FontAwesome automatic mapping

### Repository v1.0.0 (October 2025)
- Initial repository setup
- Package validation system
- Bullying Report v1.0.0 package

---

**Maintained by Woodson ISD Technology Department**
