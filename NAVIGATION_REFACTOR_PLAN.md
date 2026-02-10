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

## 🛠️ Implementation Steps

### Phase 1: Backup & Preparation (DONE)
- ✅ Git snapshot created: `snapshot-20260210-171238`
- ✅ Current state committed
- ✅ Navigation audit complete (this document)

### Phase 2: Create New Routes
1. Add routes to `routes/web.php`:
   ```php
   // Users routes
   Route::get('/admin/users', [AdminController::class, 'users']);
   Route::get('/admin/users/pending', [AdminController::class, 'usersPending']);
   Route::get('/admin/users/invitations', [AdminController::class, 'usersInvitations']);
   Route::get('/admin/users/roles', [AdminController::class, 'usersRoles']);
   
   // Packages routes
   Route::get('/admin/packages/installed', [PackageController::class, 'installed']);
   Route::get('/admin/packages/available', [PackageController::class, 'available']);
   Route::get('/admin/packages/updates', [PackageController::class, 'updates']);
   
   // Settings routes
   Route::get('/admin/settings/general', [AdminController::class, 'settingsGeneral']);
   Route::get('/admin/settings/auth', [AdminController::class, 'settingsAuth']);
   Route::get('/admin/settings/modules', [AdminController::class, 'settingsModules']);
   Route::get('/admin/settings/theme', [AdminController::class, 'settingsTheme']);
   Route::get('/admin/settings/layout', [AdminController::class, 'settingsLayout']);
   ```

### Phase 3: Extract Views from Tabs
1. **Users:**
   - Copy "Active Users" content → `resources/views/admin/users/index.blade.php`
   - Copy "Pending Approvals" content → `resources/views/admin/users/pending.blade.php`
   - Copy "Invitations" content → `resources/views/admin/users/invitations.blade.php`
   - Copy "Org Roles" content → `resources/views/admin/users/roles.blade.php`

2. **Packages:**
   - Copy "Installed" content → `resources/views/admin/packages/installed.blade.php`
   - Copy "Available" content → `resources/views/admin/packages/available.blade.php`
   - Copy "Updates" content → `resources/views/admin/packages/updates.blade.php`

3. **Settings:**
   - Merge appearance + behavior → `resources/views/admin/settings/general.blade.php`
   - Copy auth tab → `resources/views/admin/settings/auth.blade.php`
   - Copy modules tab → `resources/views/admin/settings/modules.blade.php`
   - Copy theme tab → `resources/views/admin/settings/theme.blade.php`
   - Merge header + footer → `resources/views/admin/settings/layout.blade.php`

### Phase 4: Update Sidebar Navigation
1. Edit `resources/views/layouts/admin.blade.php`
2. Update submenu URLs to use new routes (not query params)
3. Update active item detection logic

### Phase 5: Remove On-Page Tabs
1. Remove `.user-subtabs` and subtab switching JavaScript from users.blade.php
2. Remove tab navigation from packages.blade.php
3. Remove tab navigation from settings.blade.php

### Phase 6: Add Backward Compatibility Redirects
1. Redirect old `/admin/users?tab=pending` → `/admin/users/pending`
2. Redirect old `/admin/packages?view=installed` → `/admin/packages/installed`
3. Redirect old `/admin/settings?tab=auth` → `/admin/settings/auth`

### Phase 7: Test & Verify
1. Test all navigation paths
2. Test permissions for each route
3. Test deep linking
4. Test mobile drawer navigation
5. Test breadcrumbs

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
