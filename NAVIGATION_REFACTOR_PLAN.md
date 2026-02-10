# Navigation Architecture Audit & Refactor Plan

**Date:** February 10, 2026  
**Issue:** 3-level navigation stack (Sidebar → Submenu → Tabs)  
**Goal:** Simplify to 2-level "Google feel" navigation  
**Status:** READY TO IMPLEMENT

---

## 🚨 Current Problem

### 3-Level Navigation Stack (Redundant)

**Level 1: Sidebar**
- Home
- **Users** (expandable)
- **Package Management** (expandable)
- **Settings** (expandable)
- Activity Logs
- Export Data

**Level 2: Submenu** (under Users)
- Active Users → `/admin/users`
- Pending Approvals → `/admin/users?tab=pending`
- Invitations → `/admin/users?tab=invitations`
- Organization Roles → `/admin/users?tab=roles`

**Level 3: On-Page Tabs** (`resources/views/admin/users.blade.php`)
- **SAME TABS AGAIN:**
  - Active Users (subtab)
  - Pending Approvals (subtab)
  - Invitations (subtab)
  - Organization Roles (subtab)

**Problem:** Sidebar submenu renders on-page tabs redundant. Users see the same navigation twice!

---

## ✅ Proposed Solution: "Google Feel" Navigation

### Rule: Max 2 Levels

**Level 1: Sidebar Groups**
- Expandable containers (Users, Packages, Settings)

**Level 2: Actual Pages** (not tabs)
- Each menu item is a real route
- Deep-linkable
- Permission-scoped
- Mobile-friendly

**NO Level 3** (remove on-page tabs entirely)

---

## 📋 Detailed Refactor Plan

### 1. Users Section

**Current (BAD):**
```
Sidebar: Users →
  Submenu: Active Users (/admin/users)
           Pending Approvals (/admin/users?tab=pending)
           Invitations (/admin/users?tab=invitations)
           Org Roles (/admin/users?tab=roles)

Page: /admin/users has DUPLICATE tabs for same 4 items
```

**Proposed (GOOD):**
```
Sidebar: Users →
  Active Users     → /admin/users              (new default view)
  Pending Users    → /admin/users/pending      (new route)
  Invitations      → /admin/users/invitations  (new route)
  ⚙ Org Roles      → /admin/users/roles        (new route, super admin only)
```

**Changes Required:**
- ✅ **Sidebar:** Already has submenu structure
- ✅ **Routes:** Create `/admin/users/pending`, `/admin/users/invitations`, `/admin/users/roles`
- ✅ **Views:** Extract each "subtab" into separate blade file
  - `resources/views/admin/users/index.blade.php` (active users)
  - `resources/views/admin/users/pending.blade.php` (pending approvals)
  - `resources/views/admin/users/invitations.blade.php` (invitations)
  - `resources/views/admin/users/roles.blade.php` (org roles)
- ❌ **Remove:** All on-page subtabs from `users.blade.php`

---

### 2. Package Management Section

**Current (MIXED):**
```
Sidebar: Package Management →
  Installed Packages (/admin/packages?view=installed)
  Available Packages (/admin/packages)
  Updates            (/admin/packages?view=updates)

Page: /admin/packages - uses query params, not great but no duplicate tabs
```

**Proposed (BETTER):**
```
Sidebar: Package Management →
  Installed   → /admin/packages/installed   (new route)
  Available   → /admin/packages/available   (new route)
  Updates     → /admin/packages/updates     (new route)
  Configure   → /admin/packages/configure   (new route if needed)
```

**Changes Required:**
- ✅ **Sidebar:** Update URLs to use routes not query params
- ✅ **Routes:** Create `/admin/packages/installed`, `/admin/packages/available`, `/admin/packages/updates`
- ✅ **Views:** Split packages.blade.php into separate files
  - `resources/views/admin/packages/installed.blade.php`
  - `resources/views/admin/packages/available.blade.php`
  - `resources/views/admin/packages/updates.blade.php`
- ⚠️ **Keep:** Package configure page (it's a detail view, not a tab)

---

### 3. Settings Section

**Current (TAB-HEAVY):**
```
Sidebar: Settings →
  Appearance       (/admin/settings?tab=appearance)
  Behavior & Access (/admin/settings?tab=behavior)
  System           (/admin/settings?tab=system)

Page: /admin/settings has 7 TABS (appearance, behavior, auth, modules, theme, header, footer)
```

**Proposed (CLEAN):**
```
Sidebar: Settings →
  General       → /admin/settings/general     (appearance + behavior merged)
  Authentication → /admin/settings/auth       (auth settings)
  Modules       → /admin/settings/modules     (module config)
  Theme         → /admin/settings/theme       (colors, branding)
  Layout        → /admin/settings/layout      (header, footer, sidebar)
```

**Changes Required:**
- ✅ **Sidebar:** Reorganize into 5 clean routes
- ✅ **Routes:** Create routes for each settings area
- ✅ **Views:** Split settings.blade.php (currently 7 tabs) into focused pages
  - `resources/views/admin/settings/general.blade.php`
  - `resources/views/admin/settings/auth.blade.php`
  - `resources/views/admin/settings/modules.blade.php`
  - `resources/views/admin/settings/theme.blade.php`
  - `resources/views/admin/settings/layout.blade.php`
- ❌ **Remove:** All tab navigation from settings

---

### 4. Other Pages (Already Clean)

**No changes needed:**
- **Home** (`/admin/`) - single page, no tabs ✅
- **Activity Logs** (`/admin/logs`) - single page, no tabs ✅
- **Export Data** (`/admin/export`) - single page, no tabs ✅

---

## ✅ Auditor-Approved Requirements

Before implementation, these 6 requirements MUST be met:

1. **Use named routes + route groups** - Stop hardcoding URLs in Blade
2. **Enforce permissions per route** - Use middleware/policies, not "hidden menu items"
3. **Make redirects explicit + permanent (301)** - For legacy `?tab=` / `?view=` links
4. **Avoid view duplication** - Extract shared partials (`_table`, `_filters`, `_actions`)
5. **Ensure default routes redirect** - `/admin/users` → canonical child or render default view
6. **Add route-level tests** - 200/403/302 behavior + deep link coverage

**Auditor Verdict:** "Green light with the above tightening. Your IA decisions are correct."

---

## 🛠️ Implementation Steps

### Phase 1: Backup & Preparation (DONE)
- ✅ Git snapshot created: `snapshot-20260210-171238`
- ✅ Current state committed
- ✅ Navigation audit complete (this document)
- ✅ Auditor requirements documented

### Phase 2: Create New Route Groups (Named Routes + Middleware)
### Phase 2: Create New Route Groups (Named Routes + Middleware)

**File:** `routes/web.php`

**Current (FLAT, HARDCODED):**
```php
Route::prefix('admin')->middleware(['auth:admin,super_admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('admin.users');
    Route::get('/packages', [PackageController::class, 'index'])->name('admin.packages');
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
    // ...
});
```

**New (GROUPED, NESTED, ENFORCED):**
```php
Route::prefix('admin')->middleware(['web', 'auth', 'role:admin,super_admin'])->group(function () {
    
    // Admin Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');
    
    // Users Group - nested naming + permissions
    Route::prefix('users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/pending', [UserController::class, 'pending'])->name('pending');
        Route::get('/invitations', [UserController::class, 'invitations'])->name('invitations');
        Route::get('/roles', [UserController::class, 'roles'])
            ->middleware('can:manage-org-roles')
            ->name('roles');
        
        // User management APIs
        Route::get('/list', [UserController::class, 'list'])->name('list');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::post('/invite', [UserController::class, 'sendInvitation'])->name('invite');
        Route::delete('/invitations/{id}', [UserController::class, 'revokeInvitation'])->name('invitations.revoke');
    });
    
    // Package Management Group
    Route::prefix('packages')->name('admin.packages.')->group(function () {
        Route::get('/available', [PackageController::class, 'available'])->name('available');
        Route::get('/installed', [PackageController::class, 'installed'])->name('installed');
        Route::get('/updates', [PackageController::class, 'updates'])->name('updates');
        
        // Package operations
        Route::get('/configure', [PackageController::class, 'configure'])->name('configure');
        Route::get('/{packageId}/configure', [PackageController::class, 'configurePackage'])->name('configure.detail');
        Route::post('/upload', [PackageController::class, 'upload'])->name('upload');
        Route::post('/{id}/install', [PackageController::class, 'install'])->name('install');
        Route::delete('/{id}', [PackageController::class, 'delete'])->name('delete');
        Route::delete('/{packageId}/uninstall', [PackageController::class, 'uninstall'])->name('uninstall');
        
        // Package discovery
        Route::post('/discovery/search', [PackageDiscoveryController::class, 'search'])->name('discovery.search');
        Route::post('/discovery/download', [PackageDiscoveryController::class, 'download'])->name('discovery.download');
    });
    
    // Settings Group - Super Admin only
    Route::prefix('settings')->name('admin.settings.')
        ->middleware('role:super_admin')
        ->group(function () {
            Route::get('/general', [SettingsController::class, 'general'])->name('general');
            Route::get('/auth', [SettingsController::class, 'auth'])->name('auth');
            Route::get('/modules', [SettingsController::class, 'modules'])->name('modules');
            Route::get('/theme', [SettingsController::class, 'theme'])->name('theme');
            Route::get('/layout', [SettingsController::class, 'layout'])->name('layout');
            
            // Settings API
            Route::get('/get', [SettingsController::class, 'get'])->name('get');
            Route::post('/update', [SettingsController::class, 'update'])->name('update');
            Route::post('/reset', [SettingsController::class, 'reset'])->name('reset');
            
            // Default redirect to general (canonical)
            Route::get('/', fn() => redirect()->route('admin.settings.general', [], 301));
        });
    
    // Activity Logs - Super Admin only
    Route::get('/logs', [LogsController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('admin.logs');
    Route::get('/logs/list', [LogsController::class, 'list'])
        ->middleware('role:super_admin')
        ->name('admin.logs.list');
    
    // Export Data
    Route::get('/export', [ExportController::class, 'index'])->name('admin.export');
    Route::get('/export/download', [ExportController::class, 'export'])->name('admin.export.download');
    Route::post('/export', [ExportController::class, 'export'])->name('admin.export.process');
    
    // Legacy redirects (301 permanent for canonicalization)
    Route::permanentRedirect('/users', '/admin/users');
    Route::get('/users-legacy', function () {
        // Redirect old ?tab= URLs
        $tab = request()->query('tab');
        if ($tab === 'pending') return redirect()->route('admin.users.pending', [], 301);
        if ($tab === 'invitations') return redirect()->route('admin.users.invitations', [], 301);
        if ($tab === 'roles') return redirect()->route('admin.users.roles', [], 301);
        return redirect()->route('admin.users.index', [], 301);
    })->name('admin.users.legacy');
    
    Route::get('/packages-legacy', function () {
        $view = request()->query('view');
        if ($view === 'installed') return redirect()->route('admin.packages.installed', [], 301);
        if ($view === 'updates') return redirect()->route('admin.packages.updates', [], 301);
        return redirect()->route('admin.packages.available', [], 301);
    })->name('admin.packages.legacy');
    
    Route::get('/settings-legacy', function () {
        $tab = request()->query('tab');
        if ($tab === 'appearance' || $tab === 'behavior') return redirect()->route('admin.settings.general', [], 301);
        if ($tab === 'auth') return redirect()->route('admin.settings.auth', [], 301);
        if ($tab === 'modules') return redirect()->route('admin.settings.modules', [], 301);
        if ($tab === 'theme') return redirect()->route('admin.settings.theme', [], 301);
        if ($tab === 'header' || $tab === 'footer') return redirect()->route('admin.settings.layout', [], 301);
        return redirect()->route('admin.settings.general', [], 301);
    })->name('admin.settings.legacy');
});
```

**Changes:**
- ✅ Named route groups (`admin.users.*`, `admin.packages.*`, `admin.settings.*`)
- ✅ Permission enforcement via middleware (`can:manage-org-roles`, `role:super_admin`)
- ✅ 301 permanent redirects for legacy query params
- ✅ Default route redirects to canonical child page
- ✅ Clean, testable structure

---

### Phase 3: Update Sidebar to Use Named Routes

**File:** `resources/views/layouts/admin.blade.php`

**Current (HARDCODED URLs, STRING MATCHING):**
```php
$navItems = [
    ['type' => 'link', 'id' => 'home', 'label' => 'Home', 'url' => '/admin/', 'icon' => 'fas fa-home'],
    [
        'type' => 'expandable',
        'id' => 'users',
        'label' => 'Users',
        'icon' => 'fas fa-users',
        'submenu' => [
            ['id' => 'users-active', 'label' => 'Active Users', 'url' => '/admin/users'],
            ['id' => 'users-pending', 'label' => 'Pending Approvals', 'url' => '/admin/users?tab=pending'],
            // ...
        ]
    ],
    // ...
];

// Brittle active state detection
if (strpos($currentPath, '/admin/users') !== false) {
    $activeItem = 'users';
}
```

**New (NAMED ROUTES, ROUTE MATCHING):**
```php
@php
use Illuminate\Support\Facades\Route;

$navItems = [
    // Home - clickable link
    [
        'type' => 'link',
        'id' => 'home',
        'label' => 'Home',
        'url' => route('admin.dashboard'),
        'icon' => 'fas fa-home',
        'active' => request()->routeIs('admin.dashboard')
    ],
    
    // Users - expandable container
    [
        'type' => 'expandable',
        'id' => 'users',
        'label' => 'Users',
        'icon' => 'fas fa-users',
        'active' => request()->routeIs('admin.users.*'),
        'submenu' => [
            [
                'id' => 'users-active',
                'label' => 'Active Users',
                'url' => route('admin.users.index'),
                'active' => request()->routeIs('admin.users.index')
            ],
            [
                'id' => 'users-pending',
                'label' => 'Pending Approvals',
                'url' => route('admin.users.pending'),
                'active' => request()->routeIs('admin.users.pending')
            ],
            [
                'id' => 'users-invitations',
                'label' => 'Invitations',
                'url' => route('admin.users.invitations'),
                'active' => request()->routeIs('admin.users.invitations')
            ],
            [
                'id' => 'users-roles',
                'label' => 'Organization Roles',
                'url' => route('admin.users.roles'),
                'active' => request()->routeIs('admin.users.roles'),
                'permission' => fn($user, $role) => $role === 'super_admin'
            ]
        ]
    ],
    
    // Package Management - expandable container
    [
        'type' => 'expandable',
        'id' => 'packages',
        'label' => 'Package Management',
        'icon' => 'fas fa-box',
        'active' => request()->routeIs('admin.packages.*'),
        'submenu' => [
            [
                'id' => 'packages-available',
                'label' => 'Available Packages',
                'url' => route('admin.packages.available'),
                'active' => request()->routeIs('admin.packages.available')
            ],
            [
                'id' => 'packages-installed',
                'label' => 'Installed Packages',
                'url' => route('admin.packages.installed'),
                'active' => request()->routeIs('admin.packages.installed')
            ],
            [
                'id' => 'packages-updates',
                'label' => 'Updates',
                'url' => route('admin.packages.updates'),
                'active' => request()->routeIs('admin.packages.updates')
            ]
        ]
    ],
    
    // Settings - expandable container (super admin only)
    [
        'type' => 'expandable',
        'id' => 'settings',
        'label' => 'Settings',
        'icon' => 'fas fa-cog',
        'active' => request()->routeIs('admin.settings.*'),
        'permission' => fn($user, $role) => $role === 'super_admin',
        'submenu' => [
            [
                'id' => 'settings-general',
                'label' => 'General',
                'url' => route('admin.settings.general'),
                'active' => request()->routeIs('admin.settings.general')
            ],
            [
                'id' => 'settings-auth',
                'label' => 'Authentication',
                'url' => route('admin.settings.auth'),
                'active' => request()->routeIs('admin.settings.auth')
            ],
            [
                'id' => 'settings-modules',
                'label' => 'Modules',
                'url' => route('admin.settings.modules'),
                'active' => request()->routeIs('admin.settings.modules')
            ],
            [
                'id' => 'settings-theme',
                'label' => 'Theme',
                'url' => route('admin.settings.theme'),
                'active' => request()->routeIs('admin.settings.theme')
            ],
            [
                'id' => 'settings-layout',
                'label' => 'Layout',
                'url' => route('admin.settings.layout'),
                'active' => request()->routeIs('admin.settings.layout')
            ]
        ]
    ],
    
    // Activity Logs - clickable link (super admin only)
    [
        'type' => 'link',
        'id' => 'logs',
        'label' => 'Activity Logs',
        'url' => route('admin.logs'),
        'icon' => 'fas fa-list-alt',
        'active' => request()->routeIs('admin.logs'),
        'permission' => fn($user, $role) => $role === 'super_admin'
    ],
    
    // Export Data - clickable link
    [
        'type' => 'link',
        'id' => 'export',
        'label' => 'Export Data',
        'url' => route('admin.export'),
        'icon' => 'fas fa-download',
        'active' => request()->routeIs('admin.export')
    ],
];

// Active item detection (now uses first-level route matching)
$activeItem = null;
if (request()->routeIs('admin.users.*')) $activeItem = 'users';
elseif (request()->routeIs('admin.packages.*')) $activeItem = 'packages';
elseif (request()->routeIs('admin.settings.*')) $activeItem = 'settings';
elseif (request()->routeIs('admin.logs')) $activeItem = 'logs';
elseif (request()->routeIs('admin.export')) $activeItem = 'export';
else $activeItem = 'home';
@endphp
```

**Changes:**
- ✅ Uses `route()` helper, not hardcoded URLs
- ✅ Uses `request()->routeIs()` for active state
- ✅ No string matching on URLs
- ✅ Permission functions work with route middleware

---

### Phase 4: Extract Views from Tabs (Avoid Duplication)

**Strategy:** Split existing tab content into separate files. Extract shared partials to avoid duplication (filters, tables, actions).

#### Users: Split into 4 Files + Shared Partials

**Create:** `resources/views/admin/users/index.blade.php` (Active Users)
- Use shared `_filters.blade.php` partial for role/search filters
- Use shared `_table.blade.php` partial with endpoint `route('admin.users.list', ['status' => 'active'])`
- Show invite user button in header

**Create:** `resources/views/admin/users/pending.blade.php` (Pending Approvals)
- Use shared `_table.blade.php` partial with endpoint `route('admin.users.list', ['status' => 'pending'])`
- Enable `showApprovalActions` parameter for approve/reject buttons

**Create:** `resources/views/admin/users/invitations.blade.php` (Invitations)
- Use separate `_invitations_table.blade.php` (different structure than users)
- Show send invitation button in header

**Create:** `resources/views/admin/users/roles.blade.php` (Organization Roles)
- Use `_roles_matrix.blade.php` for role assignment grid

**Create Shared Partials:**
- `_filters.blade.php` - Reusable role/search filters with parameters
- `_table.blade.php` - DataTables base with configurable endpoint and columns
- `_invitations_table.blade.php` - Invitation-specific table
- `_roles_matrix.blade.php` - Organization roles grid

---

#### Packages: Split into 3 Files + Shared Grid

**Create:** `resources/views/admin/packages/available.blade.php`
- Use shared `_grid.blade.php` with `showInstallButton = true`
- Show "Discover Packages" and "Upload Package" buttons in header

**Create:** `resources/views/admin/packages/installed.blade.php`
- Use shared `_grid.blade.php` with `showUninstallButton = true`, `showConfigureButton = true`

**Create:** `resources/views/admin/packages/updates.blade.php`
- Use shared `_grid.blade.php` with `showUpdateButton = true`

**Create Shared Partial:**
- `_grid.blade.php` - Package cards grid with configurable action buttons

---

#### Settings: Split into 5 Files + Shared Form

**Create:** `resources/views/admin/settings/general.blade.php`
- Merge appearance + behavior content
- Use shared `_form.blade.php` with group = 'general'
- Fields: site_name, site_description, auto_approve_users, timezone, etc.

**Create:** `resources/views/admin/settings/auth.blade.php`
- Use shared `_form.blade.php` with group = 'auth'
- Fields: allowed_domains, google_client_id, google_client_secret, oauth settings

**Create:** `resources/views/admin/settings/modules.blade.php`
- Use shared `_form.blade.php` with group = 'modules'
- Module enable/disable toggles

**Create:** `resources/views/admin/settings/theme.blade.php`
- Use shared `_form.blade.php` with group = 'theme'
- Color scheme, brand settings

**Create:** `resources/views/admin/settings/layout.blade.php`
- Merge header + footer content
- Use shared `_form.blade.php` with group = 'layout'
- Header/footer HTML, navigation visibility

**Create Shared Partial:**
- `_form.blade.php` - Generic settings form with field array parameter

---

### Phase 5: Remove On-Page Tabs

**File:** `resources/views/admin/users.blade.php` (if still exists as monolith)
- Delete: `.user-subtabs` div with buttons `[data-subtab="active-users"]`, etc.
- Delete: JavaScript event handlers for `.user-subtabs button[data-subtab]`
- **Result:** This file may no longer exist after Phase 4 extraction

**File:** `resources/views/admin/packages.blade.php`
- Delete: Tab navigation HTML (`.package-tabs`)
- Delete: JavaScript tab switchers
- **Result:** May not exist after Phase 4

**File:** `resources/views/admin/settings.blade.php`
- Delete: Tab navigation HTML (`.settings-tabs`)
- Delete: JavaScript tab switchers
- **Result:** May not exist after Phase 4

**File:** `public/assets/js/admin.js` (if exists)
- Remove: Tab switching logic for `data-tab` and `data-subtab` attributes
- Keep: Legitimate entity-detail tabs (e.g., "Details" vs "History" for a single package)

**Verification:**
```bash
# Search for remaining legacy tab code
grep -r "data-subtab" resources/views/admin/
grep -r "data-tab=" resources/views/admin/
# Should return ONLY entity-detail tabs, not section navigation tabs
```

---

### Phase 6: Add Backward Compatibility Redirects (301 Permanent)

**Already included in Phase 2 routes code, but emphasis here:**

Redirect old query param URLs to new clean routes with **301 Permanent** status:

```php
// In routes/web.php inside admin group

// Users legacy redirects
Route::get('/users-legacy', function () {
    $tab = request()->query('tab');
    if ($tab === 'pending') return redirect()->route('admin.users.pending', [], 301);
    if ($tab === 'invitations') return redirect()->route('admin.users.invitations', [], 301);
    if ($tab === 'roles') return redirect()->route('admin.users.roles', [], 301);
    return redirect()->route('admin.users.index', [], 301);
})->name('admin.users.legacy');

// Packages legacy redirects
Route::get('/packages-legacy', function () {
    $view = request()->query('view');
    if ($view === 'installed') return redirect()->route('admin.packages.installed', [], 301);
    if ($view === 'updates') return redirect()->route('admin.packages.updates', [], 301);
    return redirect()->route('admin.packages.available', [], 301);
})->name('admin.packages.legacy');

// Settings legacy redirects
Route::get('/settings-legacy', function () {
    $tab = request()->query('tab');
    if ($tab === 'appearance' || $tab === 'behavior') return redirect()->route('admin.settings.general', [], 301);
    if ($tab === 'auth') return redirect()->route('admin.settings.auth', [], 301);
    if ($tab === 'modules') return redirect()->route('admin.settings.modules', [], 301);
    if ($tab === 'theme') return redirect()->route('admin.settings.theme', [], 301);
    if ($tab === 'header' || $tab === 'footer') return redirect()->route('admin.settings.layout', [], 301);
    return redirect()->route('admin.settings.general', [], 301);
})->name('admin.settings.legacy');
```

**Why 301 instead of 302?**
- Search engines update their indexes
- Browsers cache the redirect
- Clear signal that old URL is permanently replaced

---

### Phase 7: Add Route-Level Tests

**Create:** `tests/Feature/AdminNavigationTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function super_admin_can_access_all_user_routes()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.users.pending'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.users.invitations'))
            ->assertStatus(200);
            
        $this->actingAs($user)
            ->get(route('admin.users.roles'))
            ->assertStatus(200);
    }
    
    /** @test */
    public function admin_cannot_access_org_roles()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->actingAs($user)
            ->get(route('admin.users.roles'))
            ->assertStatus(403); // Forbidden due to middleware can:manage-org-roles
    }
    
    /** @test */
    public function admin_cannot_access_settings()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $this->actingAs($user)
            ->get(route('admin.settings.general'))
            ->assertStatus(403); // Forbidden - super admin only
    }
    
    /** @test */
    public function legacy_user_tab_urls_redirect_permanently()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        // Old URL: /admin/users?tab=pending
        $response = $this->actingAs($user)->get('/admin/users-legacy?tab=pending');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.users.pending'));
        
        // Old URL: /admin/packages?view=installed
        $response = $this->actingAs($user)->get('/admin/packages-legacy?view=installed');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.packages.installed'));
        
        // Old URL: /admin/settings?tab=auth
        $response = $this->actingAs($user)->get('/admin/settings-legacy?tab=auth');
        $response->assertStatus(301);
        $response->assertRedirect(route('admin.settings.auth'));
    }
    
    /** @test */
    public function deep_linking_works_correctly()
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        
        // Direct access to deep URL should work
        $this->actingAs($user)
            ->get(route('admin.users.invitations'))
            ->assertStatus(200)
            ->assertSee('Invitations'); // Verify correct page loaded
        
        // Sidebar should show "Users" expanded with "Invitations" active
        $this->actingAs($user)
            ->get(route('admin.users.invitations'))
            ->assertStatus(200)
            ->assertSee('class="active"', false); // Active class on submenu item
    }
    
    /** @test */
    public function named_routes_are_defined()
    {
        $this->assertTrue(route('admin.dashboard') !== null);
        $this->assertTrue(route('admin.users.index') !== null);
        $this->assertTrue(route('admin.users.pending') !== null);
        $this->assertTrue(route('admin.packages.available') !== null);
        $this->assertTrue(route('admin.settings.general') !== null);
    }
}
```

**Run Tests:**
```bash
php artisan test --filter AdminNavigationTest
```

---

### Phase 7 Verification Checklist

**Manual Testing:**
- [ ] All sidebar links use `route()` helper (inspect HTML)
- [ ] All pages load without JavaScript errors (check browser console)
- [ ] Active state highlights correct sidebar item when navigating
- [ ] Deep linking works: `/admin/users/invitations` loads correct page
- [ ] Permissions enforced: Admin cannot access Settings or Org Roles
- [ ] Super Admin can access all admin routes
- [ ] Mobile drawer navigation expands/collapses correctly
- [ ] Breadcrumbs show correct path (if implemented)
- [ ] No duplicate navigation visible (sidebar + on-page tabs)

**Automated Testing:**
- [ ] All AdminNavigationTest tests pass (green)
- [ ] No 404 errors for named routes
- [ ] Legacy URLs return 301 redirects (not 404)
- [ ] Protected routes return 403 for unauthorized users (not 404 or 500)

**Code Quality:**
- [ ] No hardcoded URLs in sidebar (`/admin/users?tab=pending` removed)
- [ ] No string matching for active state (`strpos($url)` removed)
- [ ] All routes have names (`->name('admin.users.pending')`)
- [ ] All routes in groups with middleware (`role:super_admin`, `can:manage-org-roles`)
- [ ] Shared partials used (no view duplication)

**Documentation:**
- [ ] NAVIGATION_REFACTOR_PLAN.md updated with completion status
- [ ] GOVERNANCE.md updated with new navigation rules
- [ ] STATUS.md updated (navigation refactor moved to Done)
- [ ] Git commit with emoji: `✨ Refactor admin navigation to 2-level hierarchy`

---

## 📏 New Navigation Rules (For GOVERNANCE.md)

### Sidebar Navigation Standards

**Allowed:**
- Sidebar groups (expandable containers)
- Direct page links

**Forbidden:**
- On-page tabs that duplicate sidebar submenu items
- More than 2 levels of navigation (sidebar → page is max)
- Query parameter-based navigation (`?tab=` is legacy)

**Exception:**
- Tabs are allowed ONLY for single-entity views (e.g., user detail page with Profile/Roles/Activity tabs)
- These tabs MUST be deep-linkable routes (e.g., `/admin/users/:id/roles`)

---

## 📊 Benefits of This Refactor

### User Experience
- ✅ Clear navigation hierarchy (sidebar = menu, no duplicate tabs)
- ✅ Deep-linkable URLs (shareable, bookmarkable)
- ✅ Mobile-friendly (less nav clutter)
- ✅ Consistent with "Google feel" (simple, shallow hierarchy)

### Developer Experience
- ✅ Single responsibility per view (no giant multi-tab files)
- ✅ Easier permission scoping (per route, not per tab)
- ✅ Better code organization (users/index.blade.php vs users.blade.php with 4 tabs)
- ✅ Cleaner routing (RESTful routes, not query params)

### Maintainability
- ✅ Easier to add new features (just add route + view)
- ✅ Easier to test (each route is isolated)
- ✅ Easier to document (route = page, 1:1 mapping)
- ✅ Less JavaScript complexity (no tab switching logic)

---

## 🔄 Rollback Plan

If refactor causes issues:

**Option 1: Git Revert**
```bash
git checkout snapshot-20260210-171238
```

**Option 2: Keep Backward Compatibility**
- Old query param URLs redirect to new routes
- Users can still access via old bookmarks
- Gradual migration with no breaking changes

---

## 🚦 Ready to Proceed?

**Snapshot created:** ✅ `snapshot-20260210-171238`  
**Current state committed:** ✅  
**Audit complete:** ✅  
**Implementation plan:** ✅  

**Next step:** Implement Phase 2 (Create New Routes)

Confirm to proceed, or review this plan first.
