# Role-Based Section Access System

## Overview

The Woodson ISD Maintenance platform uses a **role-based access control (RBAC)** system for managing access to different platform sections. Instead of granting access to individual users, you grant access to **roles**, and any user with that role automatically gets access.

## How It Works

### Access by Role, Not by User

**Old Way (User-Specific):**
- ❌ "Give John access to Travel Reimbursement"
- ❌ "Give Mary access to Travel Reimbursement"
- ❌ Have to manage 30 individual users

**New Way (Role-Based):**
- ✅ "Give the **Manager** role access to Travel Reimbursement"
- ✅ Anyone who is a Manager automatically sees it
- ✅ Change one user's role, they instantly get/lose access

### Available Roles (In Order of Hierarchy)

1. **Super Admin** - Full system access (ALWAYS has access to everything)
2. **Admin** - High-level administrative access
3. **Manager** - Department managers, supervisors
4. **Maintenance Director** - Oversees maintenance operations
5. **Maintenance** - Maintenance staff
6. **Staff** - General district staff

## Platform Sections

Current sections in the system:

| Section | Icon | Description | Default Access |
|---------|------|-------------|----------------|
| **Maintenance Fuel & Travel** | 🚗 | Fuel consumption & mileage tracking | All Roles |
| **Vehicle Maintenance** | 🔧 | Service history & work orders | Maintenance, Directors, Admins |
| **Travel Reimbursement** | 💰 | Submit/track travel reimbursements | Managers, Admins |
| **Substitute Request** | 👥 | Request substitute staff | Managers, Admins |
| **Travel Request** | ✈️ | Submit travel requests | Managers, Admins |

## Managing Section Access

### For Super Admins

1. Go to **Admin Dashboard**
2. Click **📋 Sections** tab
3. Select **🔐 Section Access** subtab
4. You'll see a table with:
   - **Rows**: Each platform section
   - **Columns**: Each role (rotated 45° to save space)
   - **Checkboxes**: Check to grant access, uncheck to remove

5. Check/uncheck boxes as needed
6. Click **💾 Save All Changes**

### Visual Design

The section access table uses **rotated column headers** (45-degree angle) to fit all 6 roles without making the table too wide. This is especially helpful on smaller screens.

```
┌─────────────────────────┬───────┬───────┬───────┬───────┬───────┬───────┐
│                         │ Super │ Admin │ Mgr   │ Main  │ Main  │ Staff │
│ Section                 │ Admin │       │       │ Dir   │       │       │
├─────────────────────────┼───────┼───────┼───────┼───────┼───────┼───────┤
│ 🚗 Fuel & Travel        │  [✓]  │  [ ]  │  [ ]  │  [ ]  │  [ ]  │  [ ]  │
│ 🔧 Vehicle Maintenance  │  [✓]  │  [ ]  │  [ ]  │  [ ]  │  [ ]  │  [ ]  │
│ 💰 Travel Reimbursement │  [✓]  │  [ ]  │  [ ]  │  [ ]  │  [ ]  │  [ ]  │
└─────────────────────────┴───────┴───────┴───────┴───────┴───────┴───────┘
```

*Note: Super Admin checkboxes are always checked and disabled - they can't be unchecked.*

## Adding New Sections

When you want to add a new section (e.g., "Facilities Requests", "Work Orders", "Inventory Management"):

### 1. Create the Section in Admin Dashboard

1. Go to **Sections** → **⚙️ Manage Sections**
2. Click **➕ Add New Section**
3. Fill in:
   - **Name**: Internal name (e.g., `facilities-requests`)
   - **Display Name**: What users see (e.g., `Facilities Requests`)
   - **Icon**: Choose an emoji (e.g., 🏢)
   - **Description**: Brief explanation
   - **Base URL**: Where the section lives (e.g., `/modules/facilities-requests/`)
   - **Sort Order**: Display order (lower = first)
   - **Active**: Check to enable

4. Click **Save Section**

### 2. Configure Role Access

1. Go to **Section Access** subtab
2. Your new section appears in the table
3. Check which roles should have access
4. Click **Save All Changes**

### 3. Build the Actual Feature

**This is where you need developer help!**

The section entry just creates the database record and navigation link. The actual functionality needs to be coded:

1. **Create module directory**: `/var/www/woodson/maintenance/public/modules/your-section-name/`
2. **Build the interface**: Create `index.php` with forms, tables, etc.
3. **Create API endpoints**: Build backend logic in `/public/api/your-section-api.php`
4. **Add database tables**: Create schema for storing your section's data
5. **Integrate audit logging**: Use `AuditLogger` to track all changes
6. **Test thoroughly**: Verify permissions work correctly

**Example: Adding "Facilities Requests"**

```php
// In /public/modules/facilities-requests/index.php
<?php
require_once __DIR__ . '/../../src/bootstrap.php';

use WoodsonISD\Maintenance\Auth;
use WoodsonISD\Maintenance\SectionRoleAccess;

Auth::requireLogin();

// Check if user has access to this section
$sectionAccess = new SectionRoleAccess();
if (!$sectionAccess->hasAccess($_SESSION['user_id'], 'facilities-requests')) {
    die('Access Denied: You do not have permission to view this section.');
}

// Rest of your page code...
?>
```

## Permission Checking in Code

### Check if User Has Access

```php
use WoodsonISD\Maintenance\SectionRoleAccess;

$access = new SectionRoleAccess();

// Check specific section
if ($access->hasAccess($userId, 'travel-reimbursement')) {
    // User has access
}

// Get all sections user can see
$userSections = $access->getUserSections($userId);
foreach ($userSections as $section) {
    echo $section['icon'] . ' ' . $section['display_name'];
}
```

### Require Access in Page

```php
// At top of any section page
Auth::requireLogin();

$sectionAccess = new SectionRoleAccess();
if (!$sectionAccess->hasAccess($_SESSION['user_id'], 'your-section-slug')) {
    header('HTTP/1.1 403 Forbidden');
    die('Access Denied');
}
```

## Benefits of Role-Based Access

### Scalability
- **Add 100 new users?** Just assign them roles, they automatically get appropriate access
- **New section?** Configure role access once, applies to all users with those roles

### Simplicity
- **Promote user to Manager?** They instantly see all Manager-accessible sections
- **Demote user to Staff?** Access automatically restricted
- **No individual permission management** - role changes handle everything

### Security
- **Super admins always have access** - can't accidentally lock yourself out
- **Audit logged** - All access changes tracked with who made them and when
- **Centralized control** - One place to manage all section permissions

### Flexibility
- **Users can have multiple roles** - Via the multi-role system
- **Section-specific access** - Not all Managers need to see everything
- **Easy testing** - Create test account, assign role, verify access

## Database Schema

### Tables

**`sections`**
```sql
- id (Primary Key)
- name (Internal slug)
- slug (URL identifier)
- display_name (User-facing name)
- icon (Emoji)
- description
- base_url (Path to section)
- sort_order (Display order)
- is_active (Enabled/disabled)
```

**`section_role_access`**
```sql
- id (Primary Key)
- section_id (Foreign Key → sections)
- role (ENUM: staff, maintenance, maintenance_director, manager, admin, super_admin)
- granted_by (Foreign Key → users, who granted access)
- granted_at (Timestamp)
```

### Relationships

- One section → Many roles (one-to-many)
- Access granted at **role level**, not user level
- Users inherit access through their assigned role(s)

## Troubleshooting

### User can't see a section they should have access to

1. **Check user's role**: Admin Dashboard → User Management → View user
2. **Check section access**: Sections → Section Access → Verify role is checked
3. **Check section is active**: Sections → Manage Sections → Verify "Active" checkbox
4. **Clear browser cache**: Force refresh (Ctrl+F5 / Cmd+Shift+R)
5. **Check logs**: Activity Logs → Filter by user to see their actions

### Section appears but shows "Access Denied"

The section is active and user's role is granted access, but the **section code** itself might have additional permission checks. Check the section's `index.php` for custom access logic.

### Changes not saving

1. **Verify super admin access**: Only super admins can change section access
2. **Check browser console**: Look for JavaScript errors (F12 → Console)
3. **Check PHP errors**: `/var/log/apache2/error.log`
4. **Verify CSRF token**: Page might need refresh to get new token

## Future Enhancements

Planned improvements:

1. **Section Templates** - Pre-built section types (forms, lists, dashboards)
2. **Permission Inheritance** - Child sections inherit parent permissions
3. **Time-Based Access** - Grant temporary access (expires after X days)
4. **Conditional Access** - Access based on user attributes (department, building, etc.)
5. **API Keys** - Programmatic access for external integrations

## Support

For help with section access:
- **Super Admin**: richard.sullivan@woodsonisd.net
- **Documentation**: This file
- **Activity Logs**: Track who changed what and when

---

**Remember**: Sections are just navigation entries and access control. You still need to build the actual functionality for each section!
