# Adding New Roles - Centralized System Guide

## Overview

The platform now uses a **centralized role management system**. Adding a new role requires updating **only ONE file** (`src/Roles.php`), and it will automatically propagate everywhere.

## How It Works

### Single Source of Truth: `src/Roles.php`

All role definitions are centralized in the `Roles` class. This class provides:

- Role values (e.g., `nurse`, `teacher`)
- Display labels (e.g., "Nurse", "Teacher")
- Descriptions (shown in UI)
- Hierarchy levels (determines permission precedence)
- Colors (optional, for visual distinction)

### Automatic Propagation

Once you add a role to `Roles.php`, it automatically appears in:

1. ✅ **Admin Dashboard → Global Roles Modal** (PHP-generated checkboxes)
2. ✅ **Section Access Table** (JavaScript-generated column headers)
3. ✅ **User Management Tables** (formatted role badges)
4. ✅ **API Validation** (accepted values for role assignments)
5. ✅ **Database Queries** (ENUM validation)

## Step-by-Step: Adding a New Role

### Step 1: Update `src/Roles.php`

Open `/var/www/woodson/maintenance/src/Roles.php` and add your new role to the `getAll()` method:

```php
public static function getAll(): array {
    return [
        // ... existing roles ...
        
        'nurse' => [
            'value' => 'nurse',
            'label' => 'Nurse',
            'description' => 'School nurse access to health services',
            'hierarchy' => 55, // Set appropriate level (higher = more privileges)
            'color' => '#4caf50' // Optional: hex color for UI
        ],
        
        // ... more roles ...
    ];
}
```

**Hierarchy Guidelines:**
- 100 = Super Admin (full control)
- 90 = Admin (manage users/sections)
- 80-60 = Department heads (Principal, Counselor, Substitute Manager)
- 50-40 = Directors and managers (Maintenance Director, Custodial Manager)
- 30-20 = Staff and students
- 10 = Basic staff

### Step 2: Update Database ENUM Columns

Run the following SQL commands to add the role to database tables:

```sql
-- Update users table
ALTER TABLE users 
MODIFY COLUMN role ENUM(
    'staff', 'student', 'maintenance_staff', 'custodial', 'cafeteria',
    'custodial_manager', 'maintenance_director', 'substitute_manager',
    'counselor', 'principal', 'nurse', 'admin', 'super_admin'
) NOT NULL DEFAULT 'staff';

-- Update user_global_roles table
ALTER TABLE user_global_roles 
MODIFY COLUMN role ENUM(
    'staff', 'student', 'maintenance_staff', 'custodial', 'cafeteria',
    'custodial_manager', 'maintenance_director', 'substitute_manager',
    'counselor', 'principal', 'nurse', 'admin', 'super_admin'
) NOT NULL;

-- Update section_role_access table
ALTER TABLE section_role_access 
MODIFY COLUMN role ENUM(
    'staff', 'student', 'maintenance_staff', 'custodial', 'cafeteria',
    'custodial_manager', 'maintenance_director', 'substitute_manager',
    'counselor', 'principal', 'nurse', 'admin', 'super_admin'
) NOT NULL;
```

**Important:** List roles in hierarchical order (lowest to highest) for consistency.

### Step 3: Test

1. **Refresh Admin Dashboard** → Navigate to "User Management" → "Global Roles"
   - You should see the new "Nurse" checkbox with description
   
2. **Check Section Access** → Go to "Section Access" subtab
   - The table should have a new column header for "Nurse"
   
3. **Assign the Role** → Try assigning the nurse role to a user
   - Should save without errors
   
4. **Verify in Database:**
   ```sql
   SELECT email, role FROM users WHERE role = 'nurse';
   SELECT user_id, role FROM user_global_roles WHERE role = 'nurse';
   ```

## That's It! 🎉

No other files need updating. The system automatically:

- Generates UI elements from `Roles::getOrdered()`
- Validates API requests using `Roles::getValues()`
- Formats display names using `Roles::getLabel()`
- Checks hierarchy with `Roles::getHierarchy()`

## Files That Use Centralized Roles

These files **do NOT need manual updates** when adding roles:

### PHP Files
- ✅ `public/admin/index.php` - Uses `Roles::getOrdered()` loop
- ✅ `public/api/section-role-access.php` - Uses `Roles::getValues()` validation
- ✅ `public/api/user-roles.php` - Should use `Roles::getValues()` validation
- ✅ `public/api/users.php` - Should use `Roles::getValues()` validation

### JavaScript Files
- ✅ `public/assets/js/admin.js` - Fetches from `/api/roles.php` dynamically
  - `loadSectionAccess()` - Builds table columns from API
  - `formatRole()` - Uses cached roles for display labels

### API Endpoints
- ✅ `/api/roles.php` - Returns `Roles::getForJavaScript()` JSON

## Advanced: Role Hierarchy

The hierarchy system allows automatic permission precedence:

```php
// Find the highest role from a user's role array
$highestRole = Roles::getHighest(['staff', 'counselor', 'admin']);
// Returns: 'admin' (hierarchy 90 > counselor 70 > staff 10)

// Check if role is valid
if (Roles::isValid('nurse')) {
    // Role exists in Roles::getAll()
}

// Get hierarchy value for comparison
$nurseLevel = Roles::getHierarchy('nurse'); // Returns: 55
$adminLevel = Roles::getHierarchy('admin'); // Returns: 90

if ($adminLevel > $nurseLevel) {
    // Admin has higher privileges than nurse
}
```

## Troubleshooting

### Role not appearing in UI?

1. **Clear browser cache** - Hard refresh (Ctrl+Shift+R / Cmd+Shift+R)
2. **Check browser console** - Look for JavaScript errors
3. **Verify API response:**
   ```bash
   curl http://localhost/api/roles.php | json_pp
   ```

### Database errors when assigning role?

- Make sure you ran the ALTER TABLE commands for all 3 tables
- Verify ENUM includes the new role:
  ```sql
  SHOW COLUMNS FROM users LIKE 'role';
  SHOW COLUMNS FROM user_global_roles LIKE 'role';
  SHOW COLUMNS FROM section_role_access LIKE 'role';
  ```

### Role validation failing in API?

- Ensure `Roles::getValues()` is used for validation (not hardcoded arrays)
- Check that APIs are calling `require_once __DIR__ . '/../../src/bootstrap.php'`

## Migration Helper (Future Enhancement)

Consider creating a migration script to auto-sync database ENUMs:

```php
// cli/sync-roles.php (concept)
$rolesEnum = Roles::getSqlEnum();

$tables = ['users', 'user_global_roles', 'section_role_access'];
foreach ($tables as $table) {
    $column = ($table === 'users') ? 'role' : 'role';
    $sql = "ALTER TABLE {$table} MODIFY COLUMN {$column} {$rolesEnum}";
    // Execute $sql...
}
```

This would make adding roles completely automatic (no manual SQL).

## Summary

**Before Centralized System:**
- Add role to `Roles.php`
- Update database ENUMs (3 tables)
- Update `admin/index.php` HTML (add checkbox manually)
- Update `admin.js` (add to hardcoded array)
- Update `admin.js` formatRole() (add to mapping object)
- Update API validation (3-4 files with hardcoded arrays)
- Update CSS if needed for role-badge colors

**After Centralized System:**
- ✅ Add role to `Roles.php` (1 file)
- ✅ Update database ENUMs (3 SQL commands)
- ✅ Done! Everything else is automatic.

**Maintenance Reduced:** 8 manual steps → 2 steps (75% reduction)
