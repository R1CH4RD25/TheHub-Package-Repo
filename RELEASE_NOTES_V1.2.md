# The Hub v1.2 Release Notes

**Release Date**: October 30, 2025  
**Focus**: Package Creation System & Developer Tooling

---

## 🎯 Overview

Version 1.2 introduces a **comprehensive package creation and validation system** that enables developers to build custom extensions for The Hub without modifying core code. This release provides the foundation for a Moodle/WordPress-style plugin ecosystem with strict quality standards.

---

## ✨ New Features

### 1. Package Specification v2.0

**Complete standard** for package development covering:

- **Naming Conventions**
  - Packages: `kebab-case` (e.g., `bullying-report`)
  - Namespaces: 2-5 lowercase letters + underscore (e.g., `br_`)
  - DB Tables: `snake_case` with namespace prefix (e.g., `br_reports`)
  - Fields: `snake_case` (e.g., `incident_date`)
  - Routes: `/pkg/<namespace>/<slug>` pattern

- **Database Standards**
  - Required columns in every table: `id`, `tenant_id`, `created_at`, `updated_at`, `created_by`, `updated_by`, `is_deleted`
  - ULID identifiers (26-char)
  - Soft delete with `is_deleted` flag
  - Multi-tenancy support via `tenant_id`
  - Required indexes on tenant, status, date fields

- **Module Types**
  - **Form**: Data entry interfaces with validation
  - **TableView**: Sortable, filterable record lists
  - **Workflow**: Multi-step approval processes
  - **Analytics**: Charts and metrics (future)

- **Security Requirements**
  - Input validation on all fields (minLength, maxLength, pattern)
  - Rate limiting on all forms
  - File upload restrictions (size, extensions, MIME types)
  - No SQL injection patterns
  - No code execution patterns (eval, exec, system)
  - Prepared statements only (enforced by Data API)

- **Documentation Standards**
  - README.md with installation, configuration, usage
  - CHANGELOG.md with semantic versioning
  - LICENSE file (MIT recommended)
  - Minimum 2 screenshots (1280×720, PNG/JPEG, <500KB)

**Location**: `docs/PACKAGE_SPECIFICATION_V2.md`

---

### 2. Package Linter (pkg-lint.php)

**Comprehensive validation tool** that checks packages against the v2.0 specification:

**Validation Checks**:
- ✅ JSON structure and schema version
- ✅ Package metadata completeness
- ✅ Naming conventions (kebab-case, snake_case, namespaces)
- ✅ Database schema (required columns, indexes, foreign keys)
- ✅ Field definitions (types, validation rules)
- ✅ Module configurations (Form, TableView, Workflow)
- ✅ Permission definitions (roles, role matrix, default access)
- ✅ Security scan (SQL injection, code execution patterns)
- ✅ Documentation (README, CHANGELOG, LICENSE)
- ✅ Screenshots (count, size, resolution)

**Usage**:
```bash
php cli/pkg-lint.php packages/local/my-package/
php cli/pkg-lint.php manifest.json --strict
php cli/pkg-lint.php . --json --ci  # For CI/CD pipelines
```

**Output Example**:
```
Package Linter v2.0
================================================================================

✓ Structure valid
✓ Package name valid: employee-evaluation
✓ Namespace valid: emp
✓ Version valid: 1.0.0
✓ Database entities validated
✓ 3 fields validated
✓ 3 roles validated
✓ Security scan complete
✓ README.md found
✓ CHANGELOG.md found

✗ [Screenshots] At least 2 screenshots required (found: 0)

================================================================================
✗ PACKAGE INVALID - Fix errors before submission
```

**Location**: `cli/pkg-lint.php`

---

### 3. Package Scaffolder (pkg-scaffold.php)

**Automated package generator** that creates complete package structure:

**Features**:
- Creates proper directory structure
- Generates manifest.json with all required sections
- Pre-configures database entities with required columns
- Includes sample fields (title, description, status)
- Generates README.md template
- Creates CHANGELOG.md with initial version
- Adds MIT LICENSE
- Sets up migrations/, modules/, seeds/ directories
- Enforces naming conventions at creation

**Usage**:
```bash
php cli/pkg-scaffold.php --name=my-package --namespace=mp
php cli/pkg-scaffold.php --name=bullying-report --namespace=br --category=education --author="Your Name"
```

**Templates**:
- `simple`: Minimal package (manifest, docs only)
- `standard`: Form + TableView (default)
- `workflow`: Form + TableView + Workflow

**Output**:
```
Package Scaffolder v2.0
================================================================================

Creating package: employee-evaluation
Namespace: emp
Category: hr
Template: standard

✓ Created: manifest.json
✓ Created: README.md
✓ Created: CHANGELOG.md
✓ Created: LICENSE
✓ Created: screenshots/
✓ Created: migrations/
✓ Created: modules/

✓ Package scaffolded successfully!
```

**Location**: `cli/pkg-scaffold.php`

---

### 4. Package Builder (pkg-build.php)

**Build tool** that creates `.hubpkg` files from package source:

**Features**:
- Validates package before building (via pkg-lint)
- Creates versioned .hubpkg file (e.g., `my-package_1.0.0.hubpkg`)
- Displays build summary and file size
- Can skip validation with `--no-validate`
- Supports custom output directory

**Usage**:
```bash
php cli/pkg-build.php packages/local/my-package/
php cli/pkg-build.php . --output=dist/
php cli/pkg-build.php . --no-validate
```

**Output**:
```
Package Builder v2.0
================================================================================

Building package: employee-evaluation v1.0.0
Source: packages/local/employee-evaluation
Output: packages/local/employee-evaluation

Running validation...
✓ Validation passed

✓ Created: employee-evaluation_1.0.0.hubpkg
Size: 12.5 KB

✓ Package built successfully!
```

**Location**: `cli/pkg-build.php`

---

### 5. Package Creation Guide

**Complete developer documentation** with quick start, patterns, and troubleshooting:

**Contents**:
- 5-minute quick start tutorial
- Component explanations (manifest, entities, fields, modules, permissions)
- Common patterns & recipes
  - Anonymous submissions
  - Status workflows
  - File attachments
  - Related records
- Security best practices
- Troubleshooting guide
- Advanced topics (migrations, workflows)
- Quick reference checklist

**Location**: `docs/PACKAGE_CREATION_GUIDE.md`

---

### 6. Sample Package

**Example employee-evaluation package** demonstrating proper structure:

- Complete manifest.json with all sections
- Proper database schema with namespace prefix
- Field definitions with validation
- Form and TableView modules
- Permission system with 3 roles
- README, CHANGELOG, LICENSE files
- Directory structure (migrations/, modules/, screenshots/)

**Location**: `packages/local/employee-evaluation/`

---

## 🔧 Technical Details

### Manifest Schema

The manifest.json is the single source of truth for a package:

```json
{
  "schemaVersion": 1,
  "package": { /* metadata */ },
  "compatibility": { /* system requirements */ },
  "capabilities": [ /* features */ ],
  "db": {
    "entities": [ /* tables */ ],
    "migrations": [ /* upgrade scripts */ ]
  },
  "modules": [ /* Form, TableView, Workflow */ ],
  "fields": [ /* field definitions */ ],
  "permissions": {
    "roles": [ /* custom roles */ ],
    "roleMatrix": { /* role → permissions */ },
    "defaultAccess": { /* system role → package role */ }
  },
  "menu_items": [ /* navigation */ ]
}
```

### Validation Pipeline

```
1. Scaffold Package
   └─ pkg-scaffold.php --name=my-package --namespace=mp

2. Edit Manifest
   └─ Customize fields, modules, permissions

3. Validate
   └─ pkg-lint.php packages/local/my-package/

4. Fix Issues
   └─ Address errors and warnings

5. Build
   └─ pkg-build.php packages/local/my-package/

6. Test
   └─ Install on staging Hub instance

7. Submit
   └─ Upload to package repository
```

### Naming Convention Enforcement

| Element | Format | Example | Validation |
|---------|--------|---------|------------|
| Package Name | kebab-case | `bullying-report` | Regex: `/^[a-z][a-z0-9-]*[a-z0-9]$/` |
| Namespace | 2-5 lowercase + _ | `br_` | Regex: `/^[a-z]{2,5}_$/` |
| Table Name | namespace + snake_case | `br_reports` | Must start with namespace |
| Field Name | snake_case | `incident_date` | Regex: `/^[a-z][a-z0-9_]*$/` |
| Route | /pkg/ns/slug | `/pkg/br/report-form` | Pattern enforced |

---

## 📊 Statistics

- **Lines of Code**: ~4,000 new lines
- **New Files**: 13
- **CLI Tools**: 3 (lint, scaffold, build)
- **Documentation Pages**: 2 (Specification, Quick Start)
- **Validation Checks**: 50+
- **Field Types Supported**: 15+
- **Module Types**: 3 (Form, TableView, Workflow)

---

## 🚀 Getting Started

### For Developers

1. **Read the Quick Start Guide**:
   ```bash
   cat docs/PACKAGE_CREATION_GUIDE.md
   ```

2. **Create your first package**:
   ```bash
   php cli/pkg-scaffold.php --name=my-package --namespace=mp
   ```

3. **Validate it**:
   ```bash
   php cli/pkg-lint.php packages/local/my-package/
   ```

4. **Build it**:
   ```bash
   php cli/pkg-build.php packages/local/my-package/
   ```

### For Administrators

No changes required. Package management system from v1.0 remains unchanged. New packages created with these tools will install via existing Package Manager interface.

---

## 📝 Migration Notes

### From v1.1 to v1.2

- **No breaking changes**
- All existing functionality preserved
- Package Manager UI unchanged
- Existing packages continue to work
- New validation tools are optional (recommended for new packages)

### Upgrading

```bash
git checkout main
git pull origin main
git merge v1.2
```

Or via GitHub:
1. Create Pull Request: v1.2 → main
2. Review changes
3. Merge

---

## 🐛 Known Issues

1. **Screenshot Validation**: Currently checks file count only, not actual resolution
   - **Workaround**: Manually verify screenshots are 1280×720+

2. **Module Rendering**: Module types defined but rendering not yet implemented
   - **Workaround**: Package Manager installs packages; modules will render in future update

3. **Migration Runner**: Migration scripts defined but execution not yet implemented
   - **Workaround**: Initial install creates schema from `db.entities`; upgrades coming in v1.3

---

## 🔮 Future Enhancements (v1.3+)

- [ ] Package repository integration (fetch from GitHub)
- [ ] Module rendering engine (Form, TableView, Workflow UIs)
- [ ] Migration runner for upgrades/downgrades
- [ ] Package marketplace UI
- [ ] Analytics module type
- [ ] Package dependencies resolution
- [ ] Automated testing framework
- [ ] Package signing and verification
- [ ] i18n (internationalization) support
- [ ] Theme customization per package

---

## 📚 Documentation

- [Package Specification v2.0](docs/PACKAGE_SPECIFICATION_V2.md) - Complete technical standard
- [Package Creation Guide](docs/PACKAGE_CREATION_GUIDE.md) - Quick start and patterns
- [Package Repository System](docs/PACKAGE_REPOSITORY_SYSTEM.md) - Repository and versioning

---

## 🙏 Acknowledgments

This package system was designed to provide:
- **For developers**: Easy, standardized way to extend The Hub
- **For administrators**: Confidence in package quality and security
- **For end users**: Consistent, theme-compliant experience

Inspired by successful plugin ecosystems like WordPress, Moodle, and npm.

---

## 💬 Support & Contributing

- **Issues**: https://github.com/R1CH4RD25/TheHub/issues
- **Discussions**: https://github.com/R1CH4RD25/TheHub/discussions
- **Email**: tech@woodsonisd.net

**Ready to build amazing packages!** 🚀
