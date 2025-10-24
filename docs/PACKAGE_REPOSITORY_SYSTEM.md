# Package Repository Ecosystem

## 🏗️ Repository Structure

### **1. hub-packages** (Main Package Repository)
**URL:** `https://github.com/woodson-hub/hub-packages`  
**Purpose:** Official, verified section packages

```
hub-packages/
├── registry.json                    # Master package index
├── categories/
│   ├── maintenance/
│   │   ├── fuel-tracking/
│   │   │   ├── v1.0.0/
│   │   │   │   ├── package.json
│   │   │   │   ├── README.md
│   │   │   │   ├── CHANGELOG.md
│   │   │   │   ├── screenshots/
│   │   │   │   └── migrations/
│   │   │   ├── v1.1.0/
│   │   │   ├── v1.2.0/
│   │   │   └── latest -> v1.2.0/
│   │   └── vehicle-maintenance/
│   ├── education/
│   │   ├── bullying-report/
│   │   ├── counselor-request/
│   │   └── student-passes/
│   ├── administration/
│   │   ├── travel-reimbursement/
│   │   ├── substitute-request/
│   │   └── purchase-orders/
│   └── facilities/
│       ├── work-orders/
│       └── room-reservations/
└── .github/
    └── workflows/
        └── validate-packages.yml    # Auto-validate on PR
```

### **2. hub-templates** (Starter Templates)
**URL:** `https://github.com/woodson-hub/hub-templates`  
**Purpose:** Quick-start templates for common use cases

```
hub-templates/
├── basic-form/                      # Simple data entry
├── approval-workflow/               # Multi-step approvals
├── data-tracker/                    # Track metrics over time
├── request-system/                  # Request/response pattern
├── inventory/                       # Stock/asset tracking
├── scheduling/                      # Calendar/booking
└── reporting/                       # Data visualization
```

### **3. hub-core** (The Hub Application)
**URL:** `https://github.com/woodson-hub/hub-core`  
**Purpose:** Main application codebase

```
hub-core/
└── (this repository - /var/www/woodson/thehub)
```

---

## 📦 Package Format & Versioning

### **Semantic Versioning (semver)**

```
MAJOR.MINOR.PATCH
  │     │     └─ Bug fixes (backward compatible)
  │     └─────── New features (backward compatible)  
  └───────────── Breaking changes
```

**Examples:**
- `1.0.0` → Initial release
- `1.0.1` → Bug fix
- `1.1.0` → New feature (non-breaking)
- `2.0.0` → Breaking changes

### **Version Constraints**

```json
{
  "hub_version": ">=1.0.0 <2.0.0",     // 1.x.x only
  "dependencies": {
    "woodson.vehicles": "^1.0.0",      // 1.0.0 <= x < 2.0.0
    "woodson.users": "~1.2.0",         // 1.2.0 <= x < 1.3.0
    "woodson.reports": "*"             // Any version
  }
}
```

---

## 🔍 Compatibility Checks

### **Installation Validation Flow**

```
1. UPLOAD PACKAGE
   └─ Validate JSON structure
   
2. SYSTEM REQUIREMENTS
   ├─ Hub version match?
   ├─ PHP version >= required?
   ├─ MySQL version >= required?
   └─ PHP extensions available?
   
3. DEPENDENCIES
   ├─ Required packages installed?
   ├─ Required modules exist?
   ├─ Database tables present?
   └─ Version constraints met?
   
4. CONFLICTS
   ├─ Conflicting packages?
   ├─ Duplicate slugs?
   └─ Field name collisions?
   
5. DATA MIGRATION
   ├─ Upgrade from older version?
   ├─ Migration scripts available?
   └─ Rollback possible?
   
6. GENERATE REPORT
   ├─ ✅ All checks passed
   ├─ ⚠️  Warnings (can proceed)
   └─ ❌ Critical failures (cannot install)
   
7. USER DECISION
   ├─ Review report
   ├─ Approve → Install
   └─ Reject → Save for review
```

### **Compatibility Report Example**

```
Package: Vehicle Fuel Tracking v1.2.0
Status: ⚠️  CAN INSTALL WITH WARNINGS

SYSTEM REQUIREMENTS
✅ Hub Version: 1.0.0 (required: >=1.0.0)
✅ PHP Version: 8.2.0 (required: >=8.0)
✅ MySQL Version: 10.11.13 (required: >=5.7)
✅ PHP Extensions: json, pdo_mysql

DEPENDENCIES
✅ woodson.vehicles (v1.0.0) - Installed
⚠️  woodson.vehicle-maintenance (v1.0.0) - Optional, not installed

CONFLICTS
✅ No conflicts detected

DATA MIGRATION
⚠️  Upgrading from v1.0.0 → v1.2.0
    - Migration 1.0→1.1: Add GPS field
    - Migration 1.1→1.2: Update indexes
    ✅ Rollback available

RECOMMENDATIONS
• Install optional dependency "vehicle-maintenance" for enhanced features
• Backup data before migration
• Test on staging environment first

[Cancel] [Install Anyway] [Install]
```

---

## 📝 Package Manifest (Complete Example)

```json
{
  "format_version": "1.0.0",
  "package": {
    "id": "woodson.fuel-tracking",
    "name": "fuel-tracking",
    "display_name": "Vehicle Fuel & Mileage Tracking",
    "description": "Track fuel consumption, mileage, and trip purposes for fleet vehicles",
    "version": "1.2.0",
    "author": "Woodson ISD",
    "author_email": "tech@woodsonisd.net",
    "author_organization": "Woodson ISD",
    "license": "MIT",
    "category": "maintenance",
    "tags": ["vehicles", "fuel", "fleet", "tracking", "mileage"],
    "icon": "🚗",
    "repository": "https://github.com/woodson-hub/hub-packages/tree/main/maintenance/fuel-tracking",
    "homepage": "https://hub.woodsonisd.net/packages/fuel-tracking",
    "support": "https://github.com/woodson-hub/hub-packages/issues"
  },
  "compatibility": {
    "hub_version": ">=1.0.0 <2.0.0",
    "php_version": ">=8.0",
    "mysql_version": ">=5.7",
    "tested_up_to": "1.5.0",
    "deprecated": false,
    "deprecation_reason": null,
    "breaking_changes": {
      "2.0.0": "Field names standardized, legacy names removed"
    }
  },
  "requirements": {
    "core_modules": ["vehicles"],
    "php_extensions": ["json", "pdo_mysql"],
    "database_tables": ["vehicles", "users"],
    "packages": {
      "woodson.vehicle-maintenance": {
        "version": "^1.0.0",
        "optional": true,
        "reason": "Enhanced integration with maintenance records"
      }
    }
  },
  "conflicts": {
    "woodson.legacy-fuel-system": "*"
  },
  "migration": {
    "from": {
      "1.0.0": {
        "sql": "migrations/1.0-to-1.1.sql",
        "php": "migrations/migrate-1.0-1.1.php",
        "description": "Add GPS tracking field"
      },
      "1.1.0": {
        "sql": "migrations/1.1-to-1.2.sql",
        "description": "Update indexes for performance"
      }
    },
    "rollback": {
      "1.1.0": "migrations/rollback-1.2-to-1.1.sql",
      "1.0.0": "migrations/rollback-1.1-to-1.0.sql"
    }
  },
  "changelog": [
    {
      "version": "1.2.0",
      "date": "2025-10-22",
      "type": "minor",
      "changes": [
        "Added GPS location tracking",
        "Fixed date export formatting",
        "Improved mobile UI responsiveness",
        "Added bulk import from CSV"
      ],
      "security_fixes": []
    },
    {
      "version": "1.1.0",
      "date": "2025-09-15",
      "type": "minor",
      "changes": [
        "Added trip purpose categories",
        "Export to Excel feature"
      ]
    },
    {
      "version": "1.0.0",
      "date": "2025-08-01",
      "type": "major",
      "changes": [
        "Initial release"
      ]
    }
  ],
  "screenshots": [
    "screenshots/form-entry.png",
    "screenshots/data-table.png",
    "screenshots/export.png"
  ],
  "fields": [
    // ... field definitions
  ],
  "permissions": {
    // ... permission settings
  }
}
```

---

## 🔄 Update Management

### **Update Types**

| Type | Example | Description | Auto-Update? |
|------|---------|-------------|--------------|
| **Patch** | 1.0.0 → 1.0.1 | Bug fixes only | ✅ Safe |
| **Minor** | 1.0.1 → 1.1.0 | New features, backward compatible | ⚠️ Review |
| **Major** | 1.5.0 → 2.0.0 | Breaking changes | ❌ Manual |
| **Security** | Any | Security patch | 🔴 Urgent |

### **Auto-Update Policy**

```php
// In system settings
'auto_update_policy' => [
    'patch' => true,          // Auto-update patch versions
    'minor' => false,         // Require review for minor
    'major' => false,         // Require review for major
    'security' => 'notify',   // Notify but don't auto-update
]
```

---

## 🔐 Repository Access

### **Official Repository (Read-Only)**
```php
[
    'name' => 'official',
    'url' => 'https://api.github.com/repos/woodson-hub/hub-packages',
    'type' => 'github',
    'is_official' => true,
    'requires_auth' => false,
    'priority' => 100
]
```

### **Private Organizational Repository**
```php
[
    'name' => 'woodson-private',
    'url' => 'https://gitlab.com/woodson/hub-packages',
    'type' => 'gitlab',
    'is_official' => false,
    'requires_auth' => true,
    'auth_token' => env('GITLAB_TOKEN'),
    'priority' => 90
]
```

### **Local/Network Repository**
```php
[
    'name' => 'local-dev',
    'url' => 'file:///var/www/shared/hub-packages',
    'type' => 'local',
    'is_official' => false,
    'requires_auth' => false,
    'priority' => 50
]
```

---

## 📊 Failure Reporting

### **Installation Failure Report**

```
PACKAGE INSTALLATION FAILED
============================
Package: woodson.advanced-analytics v2.0.0
Attempted: 2025-10-22 14:35:21
Duration: 3.2 seconds
User: Richard Sullivan

CRITICAL FAILURES (2)
─────────────────────
❌ Hub Version Incompatible
   Required: >=2.0.0
   Current: 1.0.0
   Resolution: Upgrade Hub to v2.0.0 or use package v1.x

❌ Missing Dependency
   Required: woodson.data-warehouse ^1.0.0
   Status: Not installed
   Resolution: Install woodson.data-warehouse first

WARNINGS (1)
────────────
⚠️  PHP Extension Recommended
   Extension: gd (for chart generation)
   Status: Not installed
   Impact: Charts will not render
   Resolution: sudo apt install php8.2-gd

SYSTEM INFO
───────────
Hub Version: 1.0.0
PHP Version: 8.2.0
MySQL Version: 10.11.13
Server: Apache 2.4

NEXT STEPS
──────────
1. Upgrade Hub to v2.0.0, or
2. Install compatible version (v1.5.0), or
3. Wait for package update

[Save Report] [Try Again] [Close]
```

### **Report Storage**

Failed installations saved to:
- Database: `section_package_installs` (status='failed')
- File: `/var/www/woodson/thehub/logs/package-failures/`
- Admin UI: Viewable in Package Manager

---

## 🎯 Next Steps

1. **Create GitHub Organizations**
   - `woodson-hub` (or your org name)
   - Set up repositories

2. **Build Package Manager UI**
   - Browse available packages
   - Upload custom packages
   - View installation history
   - Manage updates

3. **Implement Compatibility Checker**
   - PHP class: `PackageValidator.php`
   - Run all checks before install
   - Generate detailed reports

4. **Build Repository Sync**
   - Fetch package lists from GitHub
   - Cache locally
   - Check for updates daily

5. **Create First Official Package**
   - Export existing section as package
   - Upload to hub-packages repo
   - Test installation from marketplace

---

**Ready to build the package upload/install system?** 🚀
