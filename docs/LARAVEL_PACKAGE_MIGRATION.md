# Laravel Package Migration Guide

> **📘 Critical Update:** The Hub is migrating from vanilla PHP to Laravel 11. This document outlines **required changes** for package developers to ensure compatibility.

**Status:** ✅ Active (as of January 2026)  
**Branch:** `laravel-migration`  
**Related Docs:** [PACKAGE_SPECIFICATION_V2.md](./PACKAGE_SPECIFICATION_V2.md), [PACKAGE_CREATION_GUIDE.md](./PACKAGE_CREATION_GUIDE.md)

---

## Table of Contents

1. [What's Changing](#whats-changing)
2. [Breaking Changes](#breaking-changes)
3. [Laravel Route Patterns](#laravel-route-patterns)
4. [Request/Response Updates](#requestresponse-updates)
5. [Cache Invalidation](#cache-invalidation)
6. [Validation Changes](#validation-changes)
7. [Database Query Updates](#database-query-updates)
8. [Migration Checklist](#migration-checklist)
9. [Updated Build Process](#updated-build-process)

---

## What's Changing

### Legacy (v1.1)
```php
// Bootstrap-based initialization
require_once __DIR__ . '/../src/bootstrap.php';

// Direct $_POST access
$name = $_POST['name'] ?? null;

// Manual JSON responses
header('Content-Type: application/json');
echo json_encode(['success' => true]);

// Direct routing
// /api/packages.php?action=install
```

### Laravel (v2.0+)
```php
// Laravel framework initialization
namespace App\Http\Controllers;
use Illuminate\Http\{Request, JsonResponse};

// Request object
$name = $request->input('name');

// Typed responses
return response()->json(['success' => true]);

// Named routes
// Route::post('/admin/packages/{id}/install', ...)
```

---

## Breaking Changes

### 1. **File Upload Handling**

**OLD (Vanilla PHP):**
```php
if (isset($_FILES['package'])) {
    $file = $_FILES['package'];
    $tmpPath = $file['tmp_name'];
    $filename = $file['name'];
    $size = $file['size'];
}
```

**NEW (Laravel):**
```php
if ($request->hasFile('package')) {
    $file = $request->file('package');
    $tmpPath = $file->getRealPath();
    $filename = $file->getClientOriginalName();
    $size = $file->getSize();
}
```

### 2. **Route Definitions**

**OLD (Direct Files):**
```
/api/packages.php?action=install&id=123
/api/packages.php?action=uninstall&package_id=abc
```

**NEW (Laravel Routes):**
```
POST   /admin/packages/{id}/install
DELETE /admin/packages/{packageId}/uninstall
GET    /admin/packages/list?type=installed
```

**Route Registration** (in `routes/web.php`):
```php
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/packages', [PackageController::class, 'index'])->name('admin.packages');
    Route::get('/packages/list', [PackageController::class, 'list']);
    Route::post('/packages/upload', [PackageController::class, 'upload']);
    Route::post('/packages/{id}/install', [PackageController::class, 'install']);
    Route::delete('/packages/{packageId}/uninstall', [PackageController::class, 'uninstall']);
    Route::delete('/packages/{id}', [PackageController::class, 'delete']);
});
```

### 3. **Response Formats**

**OLD:**
```php
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

jsonResponse(['error' => 'Not found'], 404);
```

**NEW:**
```php
return response()->json(['error' => 'Not found'], 404);
// OR with type hinting:
public function install(Request $request, int $id): JsonResponse
{
    return response()->json([
        'success' => true,
        'message' => 'Installed'
    ]);
}
```

### 4. **CSRF Token Handling**

**OLD:**
```php
// Manual CSRF check
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['error' => 'Invalid token'], 403);
}
```

**NEW:**
```php
// Automatic via middleware (VerifyCsrfToken)
// Frontend must include:
<meta name="csrf-token" content="{{ csrf_token() }}">

// JavaScript:
headers: {
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
}
```

### 5. **Authentication**

**OLD:**
```php
$user = Auth::requireLogin();
if ($user['role'] !== 'super_admin') {
    jsonResponse(['error' => 'Unauthorized'], 403);
}
```

**NEW:**
```php
// Handled by middleware
// In routes/web.php:
Route::middleware(['auth', 'role:super_admin'])->group(function () {
    // Protected routes
});

// In controllers:
$currentUser = $request->attributes->get('user');
// OR use Laravel auth:
$user = auth()->user();
```

---

## Laravel Route Patterns

### Package URL Structure

**Pattern:** `/admin/<resource>/<action>` or `/admin/<resource>/{id}/<action>`

✅ **Correct Examples:**
```
GET    /admin/packages                    # List page
GET    /admin/packages/list?type=installed # Get data
POST   /admin/packages/upload             # Upload file
POST   /admin/packages/123/install        # Install by ID
DELETE /admin/packages/travel-reimbursement/uninstall  # Uninstall by slug
GET    /admin/packages/123/validation     # Check validation
```

❌ **Avoid (Legacy Patterns):**
```
/api/packages.php?action=list
/pkg/br/report-form
/public/api/packages.php
```

### Route Parameter Binding

```php
// Auto-bind by ID
Route::post('/packages/{id}/install', function (int $id) {
    // $id is already validated as integer
});

// String parameters (package slugs)
Route::delete('/packages/{packageId}/uninstall', function (string $packageId) {
    // $packageId accepts strings like 'travel-reimbursement'
});
```

---

## Request/Response Updates

### Controller Method Signature

**Standard Pattern:**
```php
public function methodName(Request $request, ?int $id = null): JsonResponse
{
    // Get current user
    $currentUser = $request->attributes->get('user');
    
    // Validate role
    if ($currentUser['role'] !== 'super_admin') {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    
    try {
        // Business logic here
        
        return response()->json([
            'success' => true,
            'data' => $result
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}
```

### Query Parameters

**OLD:**
```php
$type = $_GET['type'] ?? 'installed';
```

**NEW:**
```php
$type = $request->query('type', 'installed');
// OR
$type = $request->input('type', 'installed');
```

---

## Cache Invalidation

### ⚠️ **CRITICAL: Always Invalidate Cache After Mutations**

The Hub caches package data for 5 minutes. **You must clear the cache** after install/uninstall/upgrade operations.

**Required Pattern:**
```php
use Hub\Cache;

public function installPackage(int $id, int $userId): array
{
    $this->db->beginTransaction();
    
    try {
        // ... installation logic ...
        
        $this->db->commit();
        
        // 🔴 REQUIRED: Clear cache
        Cache::delete('packages:installed');
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $this->db->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

**Cache Keys to Invalidate:**
- `packages:installed` - After install/uninstall/upgrade
- `sections:all` - After section changes
- `users:{id}:permissions` - After permission changes

**Bug Fixed:** Package list showing stale data after uninstall (Jan 2026) - cache was never being cleared.

---

## Validation Changes

### Laravel Validation (Optional)

You can use Laravel's validator alongside `PackageValidator`:

```php
use Illuminate\Support\Facades\Validator;

public function upload(Request $request): JsonResponse
{
    // Quick validation
    $validator = Validator::make($request->all(), [
        'package' => 'required|file|mimes:json,hubpkg|max:51200' // 50MB
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'error' => $validator->errors()->first()
        ], 400);
    }
    
    // Deep validation
    $packageManager = new PackageManager();
    $result = $packageManager->uploadPackage(
        $request->file('package')->getRealPath(),
        $request->file('package')->getClientOriginalName()
    );
    
    return response()->json($result);
}
```

### PackageValidator Still Required

The `Hub\PackageValidator` class still performs deep validation:
- System requirements
- Dependency checking
- Security scanning
- Schema validation

**Don't skip this** even if using Laravel validation.

---

## Database Query Updates

### No Changes to Hub\Database

The custom `Hub\Database` class remains unchanged:

```php
use Hub\Database;

$db = Database::getInstance();

// These still work:
$db->fetchOne("SELECT * FROM packages WHERE id = ?", [$id]);
$db->insert('packages', $data);
$db->update('packages', $id, $data);
$db->execute("DELETE FROM packages WHERE id = ?", [$id]);
```

### Laravel Query Builder (Optional)

You can use Eloquent models for new code:

```php
use Illuminate\Support\Facades\DB;

$packages = DB::table('section_packages')
    ->where('can_install', 1)
    ->whereIn('validation_status', ['validated', 'pass'])
    ->get();
```

**Recommendation:** Stick with `Hub\Database` for consistency with existing packages.

---

## Migration Checklist

### For Package Developers

- [ ] Update API calls from `/api/packages.php?action=X` to `/admin/packages/*`
- [ ] Replace `$_POST`/`$_GET` with `$request->input()`
- [ ] Replace `$_FILES` with `$request->file()`
- [ ] Update CSRF token meta tag in views
- [ ] Add cache invalidation after mutations
- [ ] Update file upload validation
- [ ] Test package install/uninstall/upgrade
- [ ] Verify routes in `routes/web.php`
- [ ] Check controller return types (`JsonResponse`)
- [ ] Update frontend JavaScript fetch URLs

### For Core Developers

- [x] Migrate PackageController to Laravel
- [x] Add cache invalidation to install/uninstall
- [x] Remove `deleted_at` Laravel convention (use `is_active`)
- [x] Fix audit logging in uninstall
- [ ] Update pkg-build.php for Laravel routes
- [ ] Update pkg-lint.php validation rules
- [ ] Add Laravel route tests
- [ ] Update PACKAGE_SPECIFICATION_V2.md
- [ ] Create migration examples

---

## Updated Build Process

### No Changes to Package Structure

The `.hubpkg` file format **remains unchanged**:
- Still JSON-based
- Still uses `manifest.json`
- Screenshots, README, CHANGELOG still required

### Build Command (Same)

```bash
php cli/pkg-build.php packages/local/my-package/
```

### What Changes During Build

The `pkg-build.php` tool will eventually:
1. Validate Laravel route compatibility
2. Check for legacy API patterns
3. Warn about missing cache invalidation
4. Verify CSRF token usage

**Current Status:** Build tool not yet updated. Manual validation required.

---

## Examples

### Complete Package Upload Flow

**Frontend (Blade/JavaScript):**
```html
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
async function uploadPackage(file) {
    const formData = new FormData();
    formData.append('package', file);
    
    const response = await fetch('/admin/packages/upload', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: formData
    });
    
    const result = await response.json();
    
    if (result.success) {
        console.log('Package uploaded:', result.package_id);
    } else {
        console.error('Upload failed:', result.error);
    }
}
</script>
```

**Backend (PackageController):**
```php
public function upload(Request $request): JsonResponse
{
    $currentUser = $request->attributes->get('user');
    
    if ($currentUser['role'] !== 'super_admin') {
        return response()->json(['error' => 'Only super admins can upload'], 403);
    }
    
    if (!$request->hasFile('package')) {
        return response()->json(['error' => 'No file provided'], 400);
    }
    
    $file = $request->file('package');
    
    if ($file->getSize() > 50 * 1024 * 1024) {
        return response()->json(['error' => 'File exceeds 50MB'], 400);
    }
    
    try {
        $packageManager = new PackageManager();
        $result = $packageManager->uploadPackage(
            $file->getRealPath(),
            $file->getClientOriginalName()
        );
        
        if ($result['success']) {
            AuditLogger::logCreate(
                'section_packages',
                $result['package_id'],
                null,
                ['filename' => $file->getClientOriginalName()],
                $currentUser['id']
            );
        }
        
        return response()->json($result);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Upload failed: ' . $e->getMessage()
        ], 500);
    }
}
```

---

## FAQ

### Q: Do I need to rewrite my entire package?
**A:** No. Only API endpoint URLs and request handling need updates. Package structure, manifest.json, and business logic remain the same.

### Q: Can I still use the old `Hub\Database` class?
**A:** Yes. It's still supported and recommended for consistency.

### Q: What about existing .hubpkg files?
**A:** They work without changes. The package format is backward compatible.

### Q: When will pkg-build.php be updated?
**A:** Q1 2026. Manual validation required until then.

### Q: How do I test my package on Laravel?
**A:** Install on a `laravel-migration` branch instance or local dev environment.

---

## Support

**Issues:** Create a GitHub issue with `[Package Migration]` prefix  
**Docs:** [PACKAGE_SPECIFICATION_V2.md](./PACKAGE_SPECIFICATION_V2.md)  
**Examples:** See `app/Http/Controllers/Admin/PackageController.php`

---

**Last Updated:** January 12, 2026  
**Version:** 2.0.0  
**Status:** Active Development
