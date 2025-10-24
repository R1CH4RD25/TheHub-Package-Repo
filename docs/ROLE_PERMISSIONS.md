# Role-Based Access Control (RBAC) - Onion Layer Architecture

## 🧅 The Onion Layers (Like Shrek!)

Each role has access to everything below it in the hierarchy. Higher roles see MORE, lower roles see LESS.

---

## Role Hierarchy

```
┌─────────────────────────────────────┐
│         🔴 SUPER ADMIN              │  ← SEES EVERYTHING
│  ┌──────────────────────────────┐   │
│  │       🟠 ADMIN               │   │  ← Platform Management
│  │  ┌───────────────────────┐   │   │
│  │  │    🟡 MANAGER         │   │   │  ← Section Oversight
│  │  │  ┌────────────────┐   │   │   │
│  │  │  │ 🟢 MAINTENANCE  │   │   │   │  ← Section Staff
│  │  │  │    DIRECTOR     │   │   │   │
│  │  │  │  ┌──────────┐  │   │   │   │
│  │  │  │  │🔵 STAFF  │  │   │   │   │  ← Basic Entry
│  │  │  │  └──────────┘  │   │   │   │
│  │  │  └────────────────┘   │   │   │
│  │  └───────────────────────┘   │   │
│  └──────────────────────────────┘   │
└─────────────────────────────────────┘
```

---

## Admin Dashboard Permissions

### 🔴 Super Admin (You - richard.sullivan@woodsonisd.net)
**Sees ALL tabs:**
- 📊 Fuel Records ✅
- 🚗 Vehicles ✅
- 👥 User Management ✅
- 🔐 Section Access ✅
- ⚙️ Manage Sections ✅
- 📥 Export Data ✅

**Can Do:**
- Everything below PLUS:
- Create/delete/toggle sections
- Grant section access to any user
- Switch roles to test views
- Manage system-wide settings

---

### 🟠 Admin
**Sees most tabs:**
- 📊 Fuel Records ✅
- 🚗 Vehicles ✅
- 👥 User Management ✅
- 🔐 Section Access ✅
- 📥 Export Data ✅

**Can Do:**
- Everything below PLUS:
- Manage all users (create, edit roles, deactivate)
- Grant section access (within their scope)
- View/edit all fuel records across all vehicles
- Manage all vehicles (add, edit, deactivate)
- Export all data

**Cannot Do:**
- Create/delete sections (Super Admin only)
- Toggle sections active/inactive

---

### 🟡 Manager
**Sees operational tabs:**
- 📊 Fuel Records ✅
- 🚗 Vehicles ✅
- 📥 Export Data ✅

**Can Do:**
- Everything below PLUS:
- View all fuel records (read-only oversight)
- View all vehicles (read-only)
- Export data for reporting

**Cannot Do:**
- Manage users or grant permissions
- Add/edit vehicles or fuel records
- Access system administration

---

### 🟢 Maintenance Director
**Sees section management tabs:**
- 📊 Fuel Records ✅
- 🚗 Vehicles ✅
- 📥 Export Data ✅

**Can Do:**
- View all fuel records in their section
- Edit fuel records (any driver)
- Add/edit/deactivate vehicles
- Export section data
- Manage day-to-day operations

**Cannot Do:**
- Manage users or grant permissions
- Access platform administration
- View other sections (unless granted separate access)

---

### 🔵 Maintenance / Staff
**Sees basic entry:**
- Only the Fuel Entry form
- View own records only

**Can Do:**
- Submit fuel entries for their assigned vehicles
- View their own submission history

**Cannot Do:**
- Edit others' records
- Manage vehicles
- Access admin dashboard
- Export data

---

## Future Section Examples

### 🚛 Substitute Request Section
**Super Admin / Admin:**
- All tabs (users, requests, reports, export)

**Substitute Manager:**
- 📋 Substitute Requests ✅
- 📊 Reports ✅
- 📥 Export Data ✅

**Staff:**
- Submit substitute request form only

---

### 🔧 Vehicle Maintenance Section
**Super Admin / Admin:**
- All tabs (users, maintenance records, parts, export)

**Maintenance Director:**
- 🔧 Maintenance Records ✅
- 🛠️ Parts Inventory ✅
- 📥 Export Data ✅

**Maintenance Staff:**
- Add maintenance records only

---

## Implementation Pattern

### In PHP (admin/index.php):
```php
// Define who sees what (onion layers)
$canSeeFuelRecords = in_array($userRole, ['super_admin', 'admin', 'maintenance_director']);
$canSeeVehicles = in_array($userRole, ['super_admin', 'admin', 'maintenance_director']);
$canSeeUserManagement = in_array($userRole, ['super_admin', 'admin']);
$canSeeSectionAccess = in_array($userRole, ['super_admin', 'admin']);
$canSeeManageSections = ($userRole === 'super_admin');
$canSeeExport = in_array($userRole, ['super_admin', 'admin', 'maintenance_director']);
```

### In HTML:
```php
<?php if ($canSeeFuelRecords): ?>
<li><a href="#" data-tab="records">📊 Fuel Records</a></li>
<?php endif; ?>
```

---

## Access Control Logic

1. **Page Level**: `Auth::requireRole(['allowed_roles'])`
2. **Tab Level**: `if ($canSeeTab)` conditional rendering
3. **API Level**: Role checks in each endpoint
4. **Data Level**: Filter queries by user permissions

---

## Testing Your Permissions

As **Super Admin**, you can use the **"View As"** dropdown in the navbar to test each role:

1. Select "Maintenance Director" → See 3 tabs (Fuel, Vehicles, Export)
2. Select "Admin" → See 5 tabs (no Manage Sections)
3. Select "Manager" → See 3 tabs (read-only)
4. Select "Staff" → Redirected to basic entry form

---

## Remember: **Ogres Are Like Onions!** 🧅

Each outer layer has everything the inner layers have, PLUS more capabilities. The further out you go, the more powerful you become.

- **Core**: Staff (basic entry)
- **Layer 2**: Maintenance (section tasks)
- **Layer 3**: Maintenance Director (section management)
- **Layer 4**: Manager (oversight)
- **Layer 5**: Admin (platform management)
- **Outer Layer**: Super Admin (system control)

