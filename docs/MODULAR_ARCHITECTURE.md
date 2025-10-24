# Modular Platform Architecture

## Overview
The Woodson ISD platform has been restructured to support multiple independent modules/apps that users can access based on their permissions.

## Current Modules
1. **Vehicle Maintenance** (`/vehicles`) - Track fuel consumption, mileage, and maintenance
2. **Fuel Reimbursement** (`/fuel-reimbursement`) - *Coming soon* - Submit and process fuel reimbursements

## Architecture

### Database Tables
- **`modules`** - Defines all available modules (apps/sections)
- **`user_module_access`** - Maps users to modules with specific roles per module
- **`user_module_access_view`** - Convenient view for querying user access

### Key Features
- ✅ **Module Selector** - Users see available modules on login at `/modules.php`
- ✅ **Auto-redirect** - If user only has one module, auto-redirects to it
- ✅ **Per-module roles** - Users can be "staff" in one module, "admin" in another
- ✅ **Super Admin Access** - Super admins automatically have access to all modules
- ✅ **Extensible** - Easy to add new modules without touching core code

### User Flow
1. User logs in via Google OAuth
2. System checks their module access
3. If 1 module: redirect directly to it
4. If multiple modules: show module selector
5. If no modules: show "contact admin" message

### Adding a New Module

#### 1. Database Entry
```sql
INSERT INTO modules (name, display_name, description, icon, slug, base_url, sort_order) 
VALUES ('new_module', 'New Module', 'Description here', '🆕', 'new-module', '/new-module', 3);
```

#### 2. Grant User Access
```sql
INSERT INTO user_module_access (user_id, module_id, role, granted_by) 
VALUES (user_id, module_id, 'staff', 1);
```

#### 3. Create Module Files
- Create `/public/new-module/` directory
- Add `index.php` and any needed files
- Use `Module::hasAccess($userId, 'new-module')` to check permissions

### Module Class Methods

```php
$module = new Module();

// Get all modules
$modules = $module->getAll();

// Get user's accessible modules
$userModules = $module->getUserModules($userId);

// Check access
$hasAccess = $module->hasAccess($userId, 'vehicles');
$isAdmin = $module->hasAccess($userId, 'vehicles', 'admin');

// Grant access
$module->grantAccess($userId, $moduleId, 'manager', $grantedBy);

// Revoke access
$module->revokeAccess($userId, $moduleId);
```

## Migration Applied
✅ Module tables created
✅ Initial modules inserted (vehicles, fuel_reimbursement)
✅ All existing users granted access to Vehicle Maintenance module
✅ Module selector page created at `/modules.php`

## Backward Compatibility
All existing URLs still work:
- `/fuel-entry.php` → Vehicle fuel entry form
- `/admin` → Vehicle maintenance admin dashboard
- `/vehicles` → Also works (symlinked to vehicle module)

## Future Expansion Ideas
- Facility Maintenance
- Work Orders
- Asset Tracking
- Time & Attendance
- Purchase Requisitions
- Transportation Routing
- And more...

Each can be a separate module with its own permissions!
