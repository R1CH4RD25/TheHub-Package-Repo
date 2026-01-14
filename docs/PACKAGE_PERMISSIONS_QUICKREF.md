# Package Permission System - Quick Reference

## 🎯 Overview

Three-tier architecture with section-aware permissions:
- **Hub**: Mobile-first data entry (students, parents, teachers)
- **Management**: Desktop reporting/analysis (directors, managers)  
- **Admin**: System configuration (super admin, org admin)

---

## 🗂️ Database Tables

```
org_roles                    # Organization-defined roles
user_org_roles               # User → Org Role assignments
package_roles                # Package-defined roles (immutable)
package_role_mappings        # Org Role → Package Role mappings
package_sections             # Package section configs
package_hub_items            # Hub menu items
package_management_tabs      # Management sidebar tabs
```

---

## 📝 Permission Format

```
<section>.<action>
```

### Hub Permissions
```
hub.submit_form
hub.view_own_records
hub.edit_own_records
hub.lookup_data
```

### Management Permissions
```
management.view_reports
management.export_data
management.edit_records
management.delete_records
management.change_settings
```

---

## 🔑 Key PHP Classes

### OrgRole
```php
$orgRole = new OrgRole();
$orgRole->create('Principal', 'School principal');
$orgRole->assignToUser(userId: 123, orgRoleIds: [1, 2], assignedBy: 1);
$roles = $orgRole->getUserRoles(userId: 123);
```

### PackageRole
```php
$pkgRole = new PackageRole();
$pkgRole->createFromManifest(packageId: 1, rolesData: $manifest['roles']);
$pkgRole->mapOrgRoles(packageId: 1, packageRoleId: 5, orgRoleIds: [1, 2], mappedBy: 1);
$mappings = $pkgRole->getPackageMappings(packageId: 1);
```

### PackageAccess
```php
// Check permission
if (PackageAccess::hasPermission(userId: 123, packageId: 1, permission: 'hub.submit_form')) {
    // Allow action
}

// Check section access
if (PackageAccess::hasAnySectionAccess(userId: 123, section: 'management')) {
    // Show Management link
}

// Get user's permissions in package
$permissions = PackageAccess::getUserPermissions(userId: 123, packageId: 1);

// Get visible Hub items
$items = PackageAccess::getHubItems(userId: 123, packageId: 1);

// Get visible Management tabs
$tabs = PackageAccess::getManagementTabs(userId: 123, packageId: 1);
```

---

## 🚀 Installation Flow

1. **Package installed** → Manifest parsed
2. **Package roles created** from manifest
3. **Hub items/tabs created** from sections config
4. **Org admin maps roles** via Admin Dashboard
5. **Users assigned org roles** via user management
6. **Permissions granted** through mapping chain

---

## 🔄 Permission Resolution Flow

```
User → Org Roles → Package Roles → Permissions
```

Example:
```
John Doe
  ├─ Org Roles: [Maintenance Director, Business Manager]
  │
  ├─ Package: Vehicle Maintenance
  │   ├─ Maintenance Director → director role
  │   │   └─ Permissions: [hub.submit_form, management.view_reports, management.edit_records]
  │   │
  │   └─ Business Manager → reporting_staff role
  │       └─ Permissions: [management.view_reports, management.export_data]
  │
  └─ UNION Permissions: [hub.submit_form, management.view_reports, management.edit_records, management.export_data]
```

**Permissions are additive** - users get union of all their package roles.

---

## 🎭 Example: Organization Setup

### 1. Create Org Roles
```sql
INSERT INTO org_roles (name, description) VALUES
('Principal', 'School principal'),
('Maintenance Director', 'Oversees maintenance operations'),
('Maintenance Staff', 'Performs maintenance work');
```

### 2. Install Package with Roles
```json
{
  "roles": [
    {
      "role_key": "administration",
      "role_name": "Administration",
      "permissions": ["hub.submit_form", "management.view_reports", "management.export_data"]
    },
    {
      "role_key": "director",
      "permissions": ["management.view_reports", "management.export_data"]
    },
    {
      "role_key": "staff",
      "permissions": ["hub.submit_form", "hub.view_own_records"]
    }
  ]
}
```

### 3. Map Org Roles to Package Roles
```sql
INSERT INTO package_role_mappings (package_id, package_role_id, org_role_id) VALUES
(1, 1, 1),  -- Principal → administration
(1, 2, 2),  -- Maintenance Director → director
(1, 3, 3);  -- Maintenance Staff → staff
```

### 4. Assign Org Roles to Users
```sql
INSERT INTO user_org_roles (user_id, org_role_id) VALUES
(123, 1),  -- John → Principal
(456, 2),  -- Sarah → Maintenance Director
(789, 3);  -- Bob → Maintenance Staff
```

---

## 🧪 Testing Permissions

```php
// Test user 123 (Principal → administration role)
$canSubmit = PackageAccess::hasPermission(123, 1, 'hub.submit_form');  // true
$canExport = PackageAccess::hasPermission(123, 1, 'management.export_data');  // true

// Test user 789 (Staff → staff role)
$canSubmit = PackageAccess::hasPermission(789, 1, 'hub.submit_form');  // true
$canExport = PackageAccess::hasPermission(789, 1, 'management.export_data');  // false
```

---

## 🔒 Super Admin Override

```php
// Super admins ALWAYS bypass package permission checks
Auth::isSuperAdmin($userId);  // Checked first in PackageAccess::hasPermission()
```

---

## 📦 Manifest Example

```json
{
  "package_key": "vehicle-maintenance",
  "name": "Vehicle Maintenance",
  "sections": {
    "hub": {
      "enabled": true,
      "items": [
        {
          "type": "form",
          "key": "work_order",
          "title": "Submit Work Order",
          "route": "/hub/vehicle-maintenance/work-order",
          "required_permission": "hub.submit_form"
        }
      ]
    },
    "management": {
      "enabled": true,
      "sidebar_label": "Vehicle Maintenance",
      "tabs": [
        {
          "key": "work_orders",
          "label": "Work Orders",
          "route": "/management/vehicle-maintenance/work-orders",
          "required_permission": "management.view_reports"
        }
      ]
    }
  },
  "roles": [...],
  "available_permissions": [...]
}
```

---

## 📋 Migration Commands

```bash
# Apply schema
php cli/migrate-package-permissions.php

# Verify tables
mysql -u root -p woodson_hub_test -e "SHOW TABLES LIKE '%package%'"
```

---

## 🎯 Next Implementation Steps

1. ✅ Schema created
2. ✅ PHP classes created
3. ✅ Migration script created
4. ⏭️ Update package installation flow to parse manifest
5. ⏭️ Build admin UI for role mapping
6. ⏭️ Update navigation builders (Hub/Management dynamic menus)
7. ⏭️ Add middleware for route protection
8. ⏭️ Create example package manifests

---

**Status**: Foundation complete, ready for integration  
**Files**: 
- [PACKAGE_SYSTEM_ARCHITECTURE.md](../PACKAGE_SYSTEM_ARCHITECTURE.md) - Full architecture docs
- [PACKAGE_CONTRIBUTING.md](../docs/PACKAGE_CONTRIBUTING.md) - Package creator guide
- [package-permissions-schema.sql](../database/package-permissions-schema.sql) - Database schema
- [OrgRole.php](../src/OrgRole.php), [PackageRole.php](../src/PackageRole.php), [PackageAccess.php](../src/PackageAccess.php)
