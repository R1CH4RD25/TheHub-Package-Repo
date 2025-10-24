# User Role System - Woodson ISD Vehicle Maintenance

## Role Hierarchy

### 1. **Super Admin** (richard.sullivan@woodsonisd.net)
**Full system control**

Permissions:
- ✅ View all fuel records
- ✅ Edit any fuel record
- ✅ Add/edit/delete vehicles
- ✅ Add/edit/delete users (except cannot delete self)
- ✅ Invite users via email
- ✅ Approve pending user requests
- ✅ Export data
- ✅ Access admin dashboard

### 2. **Admin**
**Can do everything except delete Super Admin**

Permissions:
- ✅ View all fuel records
- ✅ Edit any fuel record  
- ✅ Add/edit/delete vehicles
- ✅ View users (cannot manage)
- ❌ Cannot invite users
- ❌ Cannot approve pending users
- ❌ Cannot delete Super Admin
- ✅ Export data
- ✅ Access admin dashboard

### 3. **Manager**
**Can adjust mistaken entries but cannot manage vehicles**

Permissions:
- ✅ View all fuel records
- ✅ Edit any fuel record (to correct mistakes)
- ❌ Cannot add/edit/delete vehicles
- ❌ Cannot manage users
- ❌ Cannot invite users
- ✅ Export data
- ✅ Access admin dashboard (limited view)

### 4. **Staff**
**Can enter own entries and adjust their mistakes**

Permissions:
- ✅ View own fuel records
- ✅ Add new fuel entries
- ✅ Edit own fuel entries (to correct mistakes)
- ❌ Cannot edit other users' entries
- ❌ Cannot manage vehicles
- ❌ Cannot manage users
- ❌ Cannot access admin dashboard
- ✅ Access fuel entry form

## Permission Matrix

| Action | Staff | Manager | Admin | Super Admin |
|--------|-------|---------|-------|-------------|
| Add fuel entry | ✅ | ✅ | ✅ | ✅ |
| Edit own entry | ✅ | ✅ | ✅ | ✅ |
| Edit any entry | ❌ | ✅ | ✅ | ✅ |
| View all entries | ❌ | ✅ | ✅ | ✅ |
| Add vehicle | ❌ | ❌ | ✅ | ✅ |
| Edit vehicle | ❌ | ❌ | ✅ | ✅ |
| Delete vehicle | ❌ | ❌ | ✅ | ✅ |
| View users | ❌ | ❌ | ✅ | ✅ |
| Edit user role | ❌ | ❌ | ❌ | ✅ |
| Invite users | ❌ | ❌ | ❌ | ✅ |
| Approve users | ❌ | ❌ | ❌ | ✅ |
| Delete users | ❌ | ❌ | ❌ | ✅* |
| Export data | ❌ | ✅ | ✅ | ✅ |
| Admin dashboard | ❌ | ✅ | ✅ | ✅ |

*Super Admin cannot delete themselves

## Database Structure

```sql
users table:
- role ENUM('staff', 'manager', 'admin', 'super_admin') DEFAULT 'staff'

invitations table:
- role ENUM('staff', 'manager', 'admin', 'super_admin') DEFAULT 'staff'
```

## Code Implementation

### Auth Helper Methods

```php
// Check specific role
Auth::isSuperAdmin()        // Only super_admin
Auth::isAdmin()             // super_admin or admin
Auth::isManager()           // super_admin, admin, or manager
Auth::isStaff()             // Any role

// Check permissions
Auth::canEditAnyRecord()    // super_admin, admin, manager
Auth::canManageVehicles()   // super_admin, admin
Auth::canManageUsers()      // super_admin only
Auth::canDeleteUser($id)    // super_admin only, cannot delete self
```

### Route Protection

```php
// Fuel entry page (all roles)
Auth::requireLogin();

// Admin dashboard (manager+)
Auth::requireRole(['super_admin', 'admin', 'manager']);

// Vehicle management API (admin+)
Auth::requireRole(['super_admin', 'admin']);

// User management API (super admin only)
Auth::requireRole(['super_admin']);
```

## Admin Dashboard Views

### Staff Users
- No access to admin dashboard
- Only see fuel entry form
- Can only view/edit their own entries

### Manager View
Dashboard shows:
- 📊 Fuel Records (all entries, can edit any)
- 📥 Export Data

Hidden from managers:
- 🚗 Vehicles tab
- 👥 Users tab
- ⏳ Pending Users tab
- ✉️ Invitations tab

### Admin View
Dashboard shows:
- 📊 Fuel Records (all entries, can edit any)
- 🚗 Vehicles (full CRUD)
- 📥 Export Data

Hidden from admins:
- 👥 Users tab (view only, no edit)
- ⏳ Pending Users tab
- ✉️ Invitations tab

### Super Admin View
Dashboard shows:
- 📊 Fuel Records
- 🚗 Vehicles
- 👥 Users
- ⏳ Pending Users
- ✉️ Invitations
- 📥 Export Data

## User Invitation Workflow

When inviting a user, Super Admin can assign:
- **Staff** - Default for most users
- **Manager** - For those who need to review/correct entries
- **Admin** - For full vehicle and data management
- **Super Admin** - Rarely needed (you're the only one)

## Self-Registration Default

When users sign in without an invitation:
- Automatically created with **Staff** role
- Account is **inactive** (pending approval)
- Super Admin must approve and can change role if needed

## Migration Notes

Old roles have been updated:
- `user` → `staff`
- `maintenance_director` → `admin` or `manager` (depending on needs)
- `super_admin` → `super_admin` (unchanged)

## Recommendations

1. **Most Users → Staff**: Regular drivers and maintenance staff
2. **Department Heads → Manager**: Can review and correct everyone's entries
3. **Maintenance Director → Admin**: Full vehicle and data control
4. **You → Super Admin**: Complete system control

## Testing Checklist

- [x] Super Admin can access all features
- [x] Admin can manage vehicles but not users
- [x] Manager can edit records but not manage vehicles/users
- [x] Staff can only see fuel entry form
- [x] Role-based menu items show/hide correctly
- [x] API endpoints enforce role permissions
- [x] Cannot delete Super Admin
- [x] Default invitation role is "staff"

---

**Updated**: December 2024  
**Version**: 2.0
