# Contributing to The Hub Package Ecosystem

## 📦 Package Structure & Requirements

Every package for The Hub must follow this structure and include all required components to integrate with the three-tier architecture (Hub, Management, Admin).

---

## 🔒 **Critical: Immutable Roles & Permissions**

### Key Principles

1. **Package roles are PERMANENT** - Once you define roles, they cannot be changed by organizations
2. **Permissions are HARDCODED** - Organizations cannot create custom permissions
3. **Organizations map their roles to your roles** - Your role definitions remain constant
4. **Think carefully before publishing** - Role names and permissions are set in stone

### Why This Matters

Organizations install packages to solve specific problems. They need **predictable behavior** and **consistent permissions** across all installations. If every org could modify your package's permissions, you'd have:

- ❌ Support nightmares (every install is different)
- ❌ Security issues (orgs accidentally granting too much access)
- ❌ Broken updates (permission changes break existing configs)

Instead, you define the roles once, and each org maps their "Principal," "Director," "Teacher" roles to your "Administration," "Manager," "Staff" roles.

---

## 📋 Required Manifest Structure

Every package **MUST** include a `manifest.json` file in its root:

```json
{
  "package_key": "your-package-slug",
  "name": "Your Package Display Name",
  "version": "1.0.0",
  "description": "Clear description of what this package does",
  "author": "Your Name or Organization",
  "repository": "https://github.com/yourusername/your-package",
  
  "sections": {
    "hub": { ... },
    "management": { ... },
    "admin": { ... }
  },
  
  "roles": [ ... ],
  "available_permissions": [ ... ]
}
```

---

## 🎭 Defining Roles

### Role Structure

```json
{
  "role_key": "administration",
  "role_name": "Administration",
  "tier_level": 1,
  "description": "Full access to all features, settings, and user management",
  "permissions": [
    "hub.submit_form",
    "hub.view_own_records",
    "management.view_reports",
    "management.export_data",
    "management.edit_records",
    "management.delete_records",
    "management.change_settings"
  ]
}
```

### Required Fields

| Field | Type | Description |
|-------|------|-------------|
| `role_key` | string | Unique identifier (snake_case, no spaces) |
| `role_name` | string | Display name shown to org admins |
| `tier_level` | int | Display hierarchy (1=highest, 3=lowest) - **visual only** |
| `description` | string | Help text explaining this role's purpose |
| `permissions` | array | List of permission strings (see below) |

### Tier Levels (Display Only)

Tiers help organize roles visually but **do not enforce inheritance**:

- **Tier 1**: Top-level administrators (usually all permissions)
- **Tier 2**: Mid-level managers (subset of permissions)
- **Tier 3**: Front-line staff (limited permissions)

Multiple roles can share the same tier with different permissions.

---

## 🔐 Permission Naming Convention

Permissions follow this pattern:

```
<section>.<action>
```

### Hub Permissions (Mobile-First, End Users)

```json
"hub.submit_form"          // Submit new records via forms
"hub.view_own_records"     // View own submitted data
"hub.edit_own_records"     // Modify own submissions
"hub.lookup_data"          // Search/filter public datasets
"hub.delete_own_records"   // Delete own submissions (rare)
```

### Management Permissions (Desktop, Directors/Managers)

```json
"management.view_reports"      // View tabular data/reports
"management.export_data"       // Export CSV/Excel/PDF
"management.edit_records"      // Modify any record
"management.delete_records"    // Delete records
"management.change_settings"   // Modify package settings
"management.view_analytics"    // View charts/dashboards
"management.manage_users"      // Edit package role mappings (advanced)
```

### Custom Permissions (Package-Specific)

You can create package-specific permissions if needed:

```json
"hub.submit_anonymous"             // Bullying reports: anonymous submission
"management.approve_requests"      // Maintenance: approve work orders
"management.schedule_maintenance"  // Fleet: schedule preventive maintenance
"management.view_financials"       // Budgeting: view cost data
```

**Rules for custom permissions:**

1. Must start with section prefix (`hub.` or `management.`)
2. Use lowercase with underscores: `snake_case`
3. Be specific and descriptive
4. List in `available_permissions` array

---

## 📱 Hub Section Configuration

The Hub is where **end users** (students, parents, teachers, staff) interact with your package on mobile devices.

### Example Hub Configuration

```json
"hub": {
  "enabled": true,
  "items": [
    {
      "type": "form",
      "key": "incident_report",
      "title": "Report Incident",
      "icon": "alert-triangle",
      "route": "/hub/bullying-reports/submit",
      "required_permission": "hub.submit_form"
    },
    {
      "type": "lookup",
      "key": "my_reports",
      "title": "My Reports",
      "icon": "list",
      "route": "/hub/bullying-reports/my-reports",
      "required_permission": "hub.view_own_records"
    }
  ]
}
```

### Hub Item Types

| Type | Purpose | Example |
|------|---------|---------|
| `form` | Data entry | Submit work order, report incident |
| `lookup` | Simple search/filter | View my submissions, find password |
| `card` | Dashboard widget | Quick stats, status indicators |
| `custom` | Unique UI | Custom package-specific interface |

### Hub Item Fields

| Field | Required | Description |
|-------|----------|-------------|
| `type` | ✅ | Item type (form/lookup/card/custom) |
| `key` | ✅ | Unique identifier (snake_case) |
| `title` | ✅ | Display title for button/card |
| `icon` | ❌ | Icon name (Feather/Font Awesome) |
| `route` | ✅ | URL path to this item |
| `required_permission` | ❌ | Permission needed to see this item |
| `config` | ❌ | Additional configuration (JSON) |

---

## 💼 Management Section Configuration

The Management section is where **directors, counselors, managers** work with data on desktop computers.

### Example Management Configuration

```json
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
      "description": "View and manage all work orders",
      "icon": "wrench"
    },
    {
      "key": "export",
      "label": "Export Data",
      "route": "/management/vehicle-maintenance/export",
      "required_permission": "management.export_data",
      "description": "Generate CSV/Excel reports"
    }
  ]
}
```

### Management Tab Fields

| Field | Required | Description |
|-------|----------|-------------|
| `key` | ✅ | Unique identifier (snake_case) |
| `label` | ✅ | Tab label in sidebar |
| `route` | ✅ | URL path to this tab |
| `required_permission` | ❌ | Permission needed to see this tab |
| `description` | ❌ | Tooltip/help text |
| `icon` | ❌ | Icon for tab (optional) |
| `sort_order` | ❌ | Display order (default: alphabetical) |

### Navigation Behavior

- **Management link only appears** if user has ANY `management.*` permission
- **Sidebar shows package** if user can see at least one tab
- **Tabs show** only if user has required permission

---

## 🔧 Admin Section Configuration

Most packages **do not need custom admin features**. The Hub provides standard package configuration tools:

```json
"admin": {
  "enabled": false,
  "notes": "Uses standard package configuration"
}
```

Only set `"enabled": true` if your package needs **custom admin pages** beyond:

- Installing/uninstalling
- Mapping org roles to package roles
- Viewing activity logs

---

## ✅ Complete Example: Bullying Report Package

```json
{
  "package_key": "bullying-reports",
  "name": "Bullying & Incident Reporting",
  "version": "1.0.0",
  "description": "Anonymous and attributed incident reporting with action tracking",
  "author": "The Hub Team",
  "repository": "https://github.com/R1CH4RD25/TheHub-Package-BullyingReports",
  
  "sections": {
    "hub": {
      "enabled": true,
      "items": [
        {
          "type": "form",
          "key": "submit_report",
          "title": "Report Incident",
          "icon": "alert-triangle",
          "route": "/hub/bullying-reports/submit",
          "required_permission": "hub.submit_form"
        },
        {
          "type": "lookup",
          "key": "my_reports",
          "title": "My Reports",
          "icon": "file-text",
          "route": "/hub/bullying-reports/my-reports",
          "required_permission": "hub.view_own_records"
        }
      ]
    },
    
    "management": {
      "enabled": true,
      "sidebar_label": "Bullying Reports",
      "icon": "shield",
      "tabs": [
        {
          "key": "all_incidents",
          "label": "All Incidents",
          "route": "/management/bullying-reports/incidents",
          "required_permission": "management.view_reports",
          "description": "View all reported incidents"
        },
        {
          "key": "action_tracking",
          "label": "Action Tracking",
          "route": "/management/bullying-reports/actions",
          "required_permission": "management.edit_records",
          "description": "Record actions taken on incidents"
        },
        {
          "key": "export",
          "label": "Export Reports",
          "route": "/management/bullying-reports/export",
          "required_permission": "management.export_data",
          "description": "Generate reports for board meetings"
        }
      ]
    },
    
    "admin": {
      "enabled": false
    }
  },
  
  "roles": [
    {
      "role_key": "administration",
      "role_name": "Administration",
      "tier_level": 1,
      "description": "Full access to all incident reports and system settings",
      "permissions": [
        "hub.submit_form",
        "hub.view_own_records",
        "management.view_reports",
        "management.edit_records",
        "management.delete_records",
        "management.export_data",
        "management.change_settings"
      ]
    },
    {
      "role_key": "action_staff",
      "role_name": "Action Staff",
      "tier_level": 2,
      "description": "Counselors and administrators who respond to incidents",
      "permissions": [
        "management.view_reports",
        "management.edit_records",
        "management.export_data"
      ]
    },
    {
      "role_key": "reporting_agents",
      "role_name": "Reporting Agents",
      "tier_level": 3,
      "description": "Students, teachers, parents who submit incident reports",
      "permissions": [
        "hub.submit_form",
        "hub.view_own_records"
      ]
    }
  ],
  
  "available_permissions": [
    "hub.submit_form",
    "hub.view_own_records",
    "management.view_reports",
    "management.edit_records",
    "management.delete_records",
    "management.export_data",
    "management.change_settings"
  ]
}
```

---

## ❌ Common Mistakes to Avoid

### 1. Empty Permissions Array
```json
// ❌ BAD
{
  "role_key": "viewer",
  "permissions": []  // Roles must have at least one permission
}
```

### 2. Permission Not in Available List
```json
// ❌ BAD
{
  "role_key": "staff",
  "permissions": ["hub.submit_form", "nuclear_launch"]  // nuclear_launch not defined!
}
```

### 3. Wrong Section Prefix
```json
// ❌ BAD
{
  "permissions": [
    "admin.delete_everything"  // Only use hub.* or management.* 
  ]
}
```

### 4. Duplicate Role Keys
```json
// ❌ BAD
{
  "roles": [
    { "role_key": "staff", ... },
    { "role_key": "staff", ... }  // Duplicate!
  ]
}
```

---

## 🧪 Testing Your Package

Before submitting, verify:

1. ✅ Manifest validates against schema
2. ✅ All permissions in roles exist in `available_permissions`
3. ✅ All Hub items and Management tabs have valid routes
4. ✅ Role descriptions are clear for org admins
5. ✅ At least one role exists
6. ✅ Section prefixes are correct (`hub.*`, `management.*`)

---

## 📤 Submitting Your Package

1. Create repository on GitHub
2. Include `manifest.json` in root
3. Add README with setup instructions
4. Tag release version (e.g., `v1.0.0`)
5. Submit pull request to [TheHub-Package-Repo](https://github.com/R1CH4RD25/TheHub-Package-Repo)

---

## 🆘 Getting Help

- 📖 [Package Architecture Docs](../PACKAGE_SYSTEM_ARCHITECTURE.md)
- 💬 [Discussion Forum](https://github.com/R1CH4RD25/TheHub/discussions)
- 🐛 [Report Issues](https://github.com/R1CH4RD25/TheHub/issues)

---

**Remember**: Your package's roles and permissions are permanent. Design carefully! 🎯
