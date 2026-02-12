# The Hub - Package System Architecture

## 🎯 Overview

The Hub is a modular platform with three distinct sections, each serving different user roles and device contexts. Packages define what appears in each section and what permissions control access.

---

## 📱 Section 1: Hub (Mobile-First, End Users)

### Purpose
- **Primary Use**: Data entry, form submission, simple lookups
- **Device Target**: Mobile devices (phones, tablets)
- **User Audience**: All end users (students, parents, teachers, staff)
- **Complexity**: Simplified, task-focused interfaces

### Examples
- Bullying incident report form
- Maintenance work order submission
- Password lookup/reset requests
- Quick status checks (where's my bus?)

### Package Responsibilities
Packages define:
- **Forms**: What data entry forms appear
- **Lookups**: Simple table filtering/search views
- **Permissions**: `hub.submit_form`, `hub.view_own_records`, `hub.lookup_data`

### Navigation
- Top-level menu items or dashboard cards
- Always accessible to users with hub-level permissions
- Simple, touch-friendly interfaces

---

## 💼 Section 2: Management (Desktop, Directors/Managers)

### Purpose
- **Primary Use**: Data analysis, reporting, bulk exports, record management
- **Device Target**: Desktop computers
- **User Audience**: Directors, counselors, managers, business staff
- **Complexity**: Rich data tables, filters, charts, exports

### Examples
- View all maintenance records with advanced filtering
- Export bullying reports for board meetings
- Update incident action status and add follow-up notes
- Generate fleet utilization reports
- Bulk edit/delete records

### Package Responsibilities
Packages define:
- **Sidebar Tabs**: What tabs appear in Management sidebar (per package)
- **Tab Content**: Tabular views, charts, forms for each tab
- **Permissions**: What permissions are needed to see each tab
  - `management.view_reports`
  - `management.export_data`
  - `management.edit_records`
  - `management.delete_records`

### Navigation & Access Control
- **Management link only appears** if user has ANY management permission from ANY package
- **Sidebar is dynamic**: Built from installed packages
- Each sidebar item shows only if user has required permissions
- Example sidebar:
  ```
  📦 Management
  ├─ Vehicle Maintenance
  │  ├─ Work Orders (if has management.view_reports)
  │  ├─ Fleet Reports (if has management.export_data)
  │  └─ Settings (if has management.change_settings)
  ├─ Bullying Reports
  │  ├─ Incidents (if has management.view_reports)
  │  ├─ Action Tracking (if has management.edit_records)
  │  └─ Export (if has management.export_data)
  ```

### Key Differences from Hub
- Desktop-optimized layouts
- Complex data grids with sorting/filtering
- Bulk operations
- Advanced reporting and exports
- Some package-specific settings

---

## 🔧 Section 3: Admin Dashboard (Desktop, System Administrators)

### Purpose
- **Primary Use**: System configuration, user management, package installation
- **Device Target**: Desktop computers
- **User Audience**: Super Admins, Organization Admins
- **Complexity**: System-level controls, security, auditing

### Capabilities
- **User Management**: Create, edit, delete users; assign org roles
- **Package Management**: Install, configure, uninstall packages
- **Role Mapping**: Map org roles to package roles
- **Site-Wide Settings**: System config, branding, integrations
- **Activity Logs**: Audit trails across all packages
- **Data Exports**: Unrestricted access to all data (for backups, etc.)

### Access Control
- **Super Admin**: God mode, bypass all checks
- **Organization Admin**: Subset of admin capabilities (defined by global role)
- Always accessible via top navigation for authorized users

### Separation from Management
- **Management**: Package-specific data operations
- **Admin**: System-wide configuration and governance

---

## 🔐 Permission Model (Section-Aware)

### Permission Naming Convention
```
<section>.<action>
```

### Hub Permissions
```
hub.submit_form          # Submit new records via forms
hub.view_own_records     # View own submitted data
hub.lookup_data          # Search/filter public datasets
hub.edit_own_records     # Modify own submissions
```

### Management Permissions
```
management.view_reports      # View tabular data/reports
management.export_data       # Export CSV/Excel/PDF
management.edit_records      # Modify any record
management.delete_records    # Delete records
management.change_settings   # Modify package settings (user-level)
management.manage_users      # Edit package role mappings (if allowed)
```

### Admin Permissions
```
admin.manage_users           # System-wide user CRUD
admin.install_packages       # Add/remove packages
admin.configure_packages     # Backend package settings
admin.view_audit_logs        # Access activity logs
admin.export_all_data        # Unrestricted data export
admin.change_site_settings   # System-level config
```

**Note**: Admin permissions are checked against global roles, not package roles.

---

## 📦 Package Manifest Structure

### Complete Example: Vehicle Maintenance Package

```json
{
  "package_key": "vehicle-maintenance",
  "name": "Vehicle Maintenance & Fleet Tracking",
  "version": "1.0.0",
  "description": "Track work orders, maintenance schedules, and fleet utilization",
  
  "sections": {
    "hub": {
      "enabled": true,
      "items": [
        {
          "type": "form",
          "key": "work_order",
          "title": "Submit Work Order",
          "icon": "wrench",
          "route": "/hub/vehicle-maintenance/work-order",
          "required_permission": "hub.submit_form"
        },
        {
          "type": "lookup",
          "key": "my_orders",
          "title": "My Work Orders",
          "icon": "list",
          "route": "/hub/vehicle-maintenance/my-orders",
          "required_permission": "hub.view_own_records"
        }
      ]
    },
    
    "management": {
      "enabled": true,
      "sidebar_label": "Vehicle Maintenance",
      "icon": "truck",
      "tabs": [
        {
          "key": "work_orders",
          "label": "Work Orders",
          "route": "/management/vehicle-maintenance/work-orders",
          "required_permission": "management.view_reports",
          "description": "View and manage all work orders"
        },
        {
          "key": "fleet_reports",
          "label": "Fleet Reports",
          "route": "/management/vehicle-maintenance/fleet-reports",
          "required_permission": "management.export_data",
          "description": "Generate fleet utilization and cost reports"
        },
        {
          "key": "maintenance_schedule",
          "label": "Maintenance Schedule",
          "route": "/management/vehicle-maintenance/schedule",
          "required_permission": "management.edit_records",
          "description": "Schedule preventive maintenance"
        },
        {
          "key": "settings",
          "label": "Settings",
          "route": "/management/vehicle-maintenance/settings",
          "required_permission": "management.change_settings",
          "description": "Configure maintenance reminders and thresholds"
        }
      ]
    },
    
    "admin": {
      "enabled": false,
      "notes": "Package-specific admin features not needed; uses standard package config"
    }
  },
  
  "roles": [
    {
      "role_key": "administration",
      "role_name": "Administration",
      "tier_level": 1,
      "description": "Full access to all features across Hub and Management",
      "permissions": [
        "hub.submit_form",
        "hub.view_own_records",
        "hub.edit_own_records",
        "hub.lookup_data",
        "management.view_reports",
        "management.export_data",
        "management.edit_records",
        "management.delete_records",
        "management.change_settings",
        "management.manage_users"
      ]
    },
    {
      "role_key": "director",
      "role_name": "Director",
      "tier_level": 2,
      "description": "Manage and report on all data, configure settings",
      "permissions": [
        "hub.submit_form",
        "hub.view_own_records",
        "management.view_reports",
        "management.export_data",
        "management.edit_records",
        "management.change_settings"
      ]
    },
    {
      "role_key": "maintenance_staff",
      "role_name": "Maintenance Staff",
      "tier_level": 3,
      "description": "Record maintenance activities via Hub",
      "permissions": [
        "hub.submit_form",
        "hub.view_own_records",
        "hub.edit_own_records"
      ]
    },
    {
      "role_key": "reporting_staff",
      "role_name": "Reporting Staff",
      "tier_level": 3,
      "description": "View and export reports only",
      "permissions": [
        "management.view_reports",
        "management.export_data"
      ]
    }
  ],
  
  "available_permissions": [
    "hub.submit_form",
    "hub.view_own_records",
    "hub.edit_own_records",
    "hub.lookup_data",
    "management.view_reports",
    "management.export_data",
    "management.edit_records",
    "management.delete_records",
    "management.change_settings",
    "management.manage_users"
  ]
}
```

---

## 🔄 User Flow Examples

### Example 1: Maintenance Worker (Hub Only)
**User**: John (Maintenance Staff)  
**Org Role**: Maintenance Staff  
**Package Role**: maintenance_staff  
**Permissions**: `hub.submit_form`, `hub.view_own_records`

**Navigation**:
- Sees "Hub" link in top nav
- Sees "Submit Work Order" form
- Can view "My Work Orders" list
- Does NOT see "Management" link (no management permissions)

---

### Example 2: Maintenance Director (Hub + Management)
**User**: Sarah (Maintenance Director)  
**Org Role**: Maintenance Director  
**Package Role**: director  
**Permissions**: hub.* + management.* (except delete/manage_users)

**Navigation**:
- Sees both "Hub" and "Management" links
- In Hub: Can submit work orders
- In Management: Sees "Vehicle Maintenance" sidebar
  - Work Orders tab ✓
  - Fleet Reports tab ✓
  - Maintenance Schedule tab ✓
  - Settings tab ✓

---

### Example 3: Business Manager (Management Export Only)
**User**: Tom (Business Manager)  
**Org Role**: Business Manager  
**Package Role**: reporting_staff  
**Permissions**: `management.view_reports`, `management.export_data`

**Navigation**:
- Sees "Management" link (has management permissions)
- Does NOT see "Hub" link (no hub permissions)
- In Management sidebar:
  - Work Orders tab ✓ (view only)
  - Fleet Reports tab ✓ (can export)
  - Maintenance Schedule tab ✗ (requires edit_records)
  - Settings tab ✗ (requires change_settings)

---

### Example 4: Super Admin
**User**: Admin (Super Admin)  
**Permissions**: ALL (bypass)

**Navigation**:
- Sees "Hub", "Management", AND "Admin Dashboard"
- In Management: Sees ALL tabs from ALL installed packages
- In Admin: Can install/configure packages, manage users, view logs

---

## 🎯 Implementation Checklist

### Phase 1: Foundation
- [ ] Database schema for org roles, package roles, mappings
- [ ] PHP classes: `OrgRole`, `PackageRole`, `PackageAccess`
- [ ] Permission checker: `PackageAccess::hasPermission($userId, $packageId, $permission)`

### Phase 2: Package Integration
- [ ] Package manifest parser (validate `sections`, `roles`, `permissions`)
- [ ] Migration script to seed package roles from manifest
- [ ] Admin UI: Map org roles → package roles per package

### Phase 3: Section Rendering
- [ ] Hub: Dynamic menu builder from installed packages (hub.* permissions)
- [ ] Management: Dynamic sidebar builder (management.* permissions)
- [ ] Navigation visibility: Show links only if user has section permissions

### Phase 4: Access Control
- [ ] Hub route middleware: Check `hub.*` permissions
- [ ] Management route middleware: Check `management.*` permissions
- [ ] Admin route middleware: Check global admin role
- [ ] Super admin bypass in all checks

### Phase 5: Testing
- [ ] Unit tests for permission union logic
- [ ] Integration tests for multi-package scenarios
- [ ] UI tests for dynamic navigation

---

## 🚀 Next Steps

1. **Finalize permission naming** (hub.*, management.*, admin.*)
2. **Create database schema** with section-aware permissions
3. **Build manifest validator** to ensure packages define sections correctly
4. **Implement navigation builders** for Hub and Management
5. **Document for package creators** (CONTRIBUTING.md in package repo)

---

**Status**: Architecture defined, ready for implementation  
**Last Updated**: 2026-01-14  
**Owner**: R1CH4RD25
