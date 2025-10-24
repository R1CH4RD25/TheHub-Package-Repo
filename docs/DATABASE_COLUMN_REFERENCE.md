# Database Schema Reference
## Package Management Tables - Column Mapping

**Last Audit:** October 23, 2025  
**Status:** ✅ All columns verified

---

## section_packages
**Purpose:** Store uploaded package files and metadata

| Column | Type | Nullable | Default | Usage |
|--------|------|----------|---------|-------|
| `id` | int(11) | NO | auto | Primary key |
| `package_id` | varchar(100) | NO | - | Unique package identifier (e.g., "com.woodson.bullying-report") |
| `name` | varchar(100) | NO | - | Short name for files |
| `display_name` | varchar(255) | NO | - | Human-readable name |
| `description` | text | YES | NULL | Package description |
| `author` | varchar(255) | YES | NULL | Package author name |
| `author_email` | varchar(255) | YES | NULL | Author email |
| `author_organization` | varchar(255) | YES | NULL | Author organization |
| `license` | varchar(100) | YES | proprietary | License type |
| `version` | varchar(20) | NO | 1.0.0 | Semantic version |
| `package_data` | longtext | NO | - | JSON package contents |
| `uploaded_by` | int(11) | YES | NULL | User ID who uploaded |
| `uploaded_at` | timestamp | YES | NULL | Upload timestamp |
| `file_path` | varchar(255) | YES | NULL | Path to .hubpkg file |
| `file_size` | bigint(20) | YES | NULL | File size in bytes |
| `validation_status` | varchar(50) | YES | pending | pending/pass/fail |
| `can_install` | tinyint(1) | YES | 0 | 1 if validated successfully |
| `category` | varchar(50) | YES | other | reporting/forms/workflows/etc |
| `tags` | longtext | YES | NULL | JSON array of tags |
| `download_count` | int(11) | YES | 0 | Download counter |
| `rating_avg` | decimal(3,2) | YES | 0.00 | Average rating |
| `rating_count` | int(11) | YES | 0 | Number of ratings |
| `is_public` | tinyint(1) | YES | 0 | Public visibility |
| `is_featured` | tinyint(1) | YES | 0 | Featured package |
| `requires_approval` | tinyint(1) | YES | 0 | Requires admin approval |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Creation time |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Last update time |
| `hub_version_min` | varchar(20) | YES | NULL | Minimum Hub version |
| `hub_version_max` | varchar(20) | YES | NULL | Maximum Hub version |
| `php_version_min` | varchar(20) | YES | NULL | Minimum PHP version |
| `mysql_version_min` | varchar(20) | YES | NULL | Minimum MySQL version |
| `dependencies` | longtext | YES | NULL | JSON array of dependencies |
| `conflicts` | longtext | YES | NULL | JSON array of conflicts |
| `tested_up_to` | varchar(20) | YES | NULL | Tested up to version |
| `is_deprecated` | tinyint(1) | YES | 0 | Deprecated flag |
| `deprecation_reason` | text | YES | NULL | Why deprecated |
| `changelog` | longtext | YES | NULL | Version changelog |
| `screenshots` | longtext | YES | NULL | JSON array of screenshot URLs |
| `repository_url` | varchar(255) | YES | NULL | GitHub/Git repository URL |
| `demo_url` | varchar(255) | YES | NULL | Demo site URL |
| `support_url` | varchar(255) | YES | NULL | Support/documentation URL |

---

## section_installations
**Purpose:** Track which packages are installed and active

| Column | Type | Nullable | Default | Usage |
|--------|------|----------|---------|-------|
| `id` | int(11) | NO | auto | Primary key |
| `section_id` | int(11) | NO | - | FK to sections.id |
| `package_id` | varchar(100) | YES | NULL | Package identifier |
| `package_record_id` | int(11) | YES | NULL | FK to section_packages.id |
| `installed_version` | varchar(20) | NO | - | Currently installed version |
| `available_version` | varchar(20) | YES | NULL | Latest available version |
| `auto_update` | tinyint(1) | YES | 0 | Auto-update enabled |
| `status` | varchar(50) | YES | installed | installed/upgrading/failed |
| `installed_by` | int(11) | YES | NULL | FK to users.id |
| `installed_at` | timestamp | YES | CURRENT_TIMESTAMP | Installation time |
| `upgraded_at` | timestamp | YES | NULL | Last upgrade time |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Last update time |

---

## section_compatibility_checks
**Purpose:** Store validation check results

| Column | Type | Nullable | Default | Usage |
|--------|------|----------|---------|-------|
| `id` | int(11) | NO | auto | Primary key |
| `package_record_id` | int(11) | YES | NULL | FK to section_packages.id |
| `install_id` | int(11) | YES | NULL | FK to section_package_installs.id |
| `check_type` | varchar(50) | NO | - | Category of check (validation/system/etc) |
| `check_name` | varchar(100) | NO | - | Name of specific check |
| `required_value` | varchar(100) | YES | NULL | What was required |
| `actual_value` | varchar(100) | YES | NULL | What was found |
| `status` | enum | NO | - | pass/fail/warning |
| `severity` | enum | YES | error | critical/error/warning/info |
| `message` | text | YES | NULL | Human-readable message |
| `resolution` | text | YES | NULL | How to fix if failed |
| `checked_at` | timestamp | YES | CURRENT_TIMESTAMP | Check timestamp |

**⚠️ NOTE:** Use `resolution` column for details, NOT `details` (doesn't exist)

---

## section_package_installs
**Purpose:** Installation attempt history log

| Column | Type | Nullable | Default | Usage |
|--------|------|----------|---------|-------|
| `id` | int(11) | NO | auto | Primary key |
| `package_id` | varchar(100) | NO | - | Package identifier |
| `package_version` | varchar(20) | NO | - | Version being installed |
| `status` | enum | NO | - | pending/success/failed/rolled_back |
| `installation_type` | enum | YES | new | new/upgrade/downgrade/reinstall |
| `attempted_by` | int(11) | YES | NULL | FK to users.id |
| `attempted_at` | timestamp | YES | CURRENT_TIMESTAMP | When attempt started |
| `completed_at` | timestamp | YES | NULL | When attempt finished |
| `section_id` | int(11) | YES | NULL | FK to sections.id |
| `error_message` | text | YES | NULL | Error if failed |
| `compatibility_report` | longtext | YES | NULL | JSON validation report |
| `installation_log` | text | YES | NULL | Installation log output |

---

## sections
**Purpose:** Installed dynamic sections

| Column | Type | Nullable | Default | Usage |
|--------|------|----------|---------|-------|
| `id` | int(11) | NO | auto | Primary key |
| `slug` | varchar(100) | NO | UNIQUE | URL-safe identifier |
| `name` | varchar(255) | NO | - | Display name |
| `icon` | varchar(100) | YES | bi-folder | Bootstrap icon class (100 chars) |
| `description` | text | YES | NULL | Section description |
| `is_active` | tinyint(1) | YES | 1 | 0=disabled, 1=enabled |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Creation time |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Last update |

**⚠️ NOTE:** Icon column is VARCHAR(100) to support long icon names like "bi-shield-exclamation"

---

## Common Column Mismatches to Avoid

### ❌ DON'T USE:
- `section_compatibility_checks.details` → Use `resolution` instead
- Short VARCHAR for icons → Use VARCHAR(100)
- `installation_id` in WHERE clauses → Check table schema first

### ✅ DO USE:
- `section_compatibility_checks.resolution` for detailed info
- `package_record_id` for linking to section_packages
- `section_id` for linking to sections
- `package_id` (string) for package identifier
- `id` for record IDs

---

## Quick Reference for Common Queries

### Get package with validation results:
```sql
SELECT p.*, 
       (SELECT COUNT(*) FROM section_compatibility_checks WHERE package_record_id = p.id) as check_count
FROM section_packages p
WHERE p.id = ?
```

### Get installed packages:
```sql
SELECT s.*, i.installed_version, i.installed_at
FROM sections s
JOIN section_installations i ON s.id = i.section_id
WHERE s.is_active = 1
```

### Get validation checks for a package:
```sql
SELECT * FROM section_compatibility_checks
WHERE package_record_id = ?
ORDER BY check_type, check_name
```

---

**Last Verified:** October 23, 2025
**All Columns:** ✅ Verified
**No Mismatches:** ✅ Confirmed
