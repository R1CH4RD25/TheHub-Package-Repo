<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{
    DashboardController,
    ExportController,
    UserController,
    PackageController,
    PackageDiscoveryController,
    SettingsController,
    LogsController,
    RoleController
};
use App\Http\Controllers\Management\DashboardController as ManagementDashboardController;

Route::get('/', function () {
    return redirect('/modules.php');
});

// Test route (no auth, no views - pure JSON)
Route::get('/admin/export/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel is working!',
        'framework' => 'Laravel 11.47.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
    ]);
});

// Health check
Route::get('/laravel/health', function () {
    return response()->json(['status' => 'ok']);
});

// Admin routes (Laravel migration - refactored to 2-level navigation)
// Requires authentication: admin, super_admin
Route::prefix('admin')->middleware(['web', 'auth:admin,super_admin'])->group(function () {

    // Admin Dashboard - shows overview with links to all sections
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // ========================================
    // USERS GROUP - Nested routes with naming
    // ========================================
    Route::prefix('users')->name('admin.users.')->group(function () {
        // User list pages (views)
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/pending', [UserController::class, 'pending'])->name('pending');
        Route::get('/invitations', [UserController::class, 'invitations'])->name('invitations');

        // User management APIs
        Route::get('/list', [UserController::class, 'list'])->name('list');
        Route::get('/invitations/list', [UserController::class, 'invitationsList'])->name('invitations.list');
        Route::put('/{id}', [UserController::class, 'update'])->name('update');
        Route::post('/invite', [UserController::class, 'sendInvitation'])->name('invite');
        Route::delete('/invitations/{id}', [UserController::class, 'revokeInvitation'])->name('invitations.revoke');
        Route::get('/{id}/permissions', [RoleController::class, 'userPermissions'])->name('permissions');
    });

    // ========================================
    // ROLES GROUP - Organization roles (Super Admin only for role CRUD)
    // ========================================
    Route::prefix('roles')->name('admin.roles.')->group(function () {
        // Role list page (accessible to all admins)
        Route::get('/', [UserController::class, 'roles'])->name('index');

        // Role CRUD (Super Admin only via middleware in controller)
        Route::get('/{id}', [RoleController::class, 'show'])->name('show');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{id}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');

        // Permission management
        Route::get('/permissions/list', [RoleController::class, 'permissions'])->name('permissions');
        Route::post('/assign-user', [RoleController::class, 'assignToUser'])->name('assign');
        Route::delete('/remove-user', [RoleController::class, 'removeFromUser'])->name('remove');
    });

    // ========================================
    // PACKAGES GROUP - Nested routes with naming
    // ========================================
    Route::prefix('packages')->name('admin.packages.')->group(function () {
        // Package list pages (views)
        Route::get('/available', [PackageController::class, 'available'])->name('available');
        Route::get('/installed', [PackageController::class, 'installed'])->name('installed');
        Route::get('/updates', [PackageController::class, 'updates'])->name('updates');

        // Package creator wizard
        Route::get('/create', [PackageController::class, 'create'])->name('create');

        // Package configuration
        Route::get('/configure', [PackageController::class, 'configure'])->name('configure');
        Route::get('/{packageId}/configure', [PackageController::class, 'configurePackage'])->name('configure.detail');
        Route::get('/{sectionId}/config-data', [PackageController::class, 'getConfigData'])->name('config.data');
        Route::post('/{sectionId}/role-mappings', [PackageController::class, 'updateRoleMappings'])->name('config.roleMappings');
        Route::post('/{sectionId}/toggle-active', [PackageController::class, 'toggleActive'])->name('config.toggleActive');

        // Package operations
        Route::get('/list', [PackageController::class, 'list'])->name('list');
        Route::post('/upload', [PackageController::class, 'upload'])->name('upload');
        Route::post('/{id}/install', [PackageController::class, 'install'])->name('install');
        Route::delete('/{id}', [PackageController::class, 'delete'])->name('delete');
        Route::get('/{packageId}/record-count', [PackageController::class, 'recordCount'])->name('recordCount');
        Route::post('/{packageId}/upgrade', [PackageController::class, 'upgrade'])->name('upgrade');
        Route::delete('/{packageId}/uninstall', [PackageController::class, 'uninstall'])->name('uninstall');
        Route::get('/{id}/validation', [PackageController::class, 'validation'])->name('validation');

        // Package discovery
        Route::post('/discovery/search', [PackageDiscoveryController::class, 'search'])->name('discovery.search');
        Route::post('/discovery/download', [PackageDiscoveryController::class, 'download'])->name('discovery.download');

        // Legacy redirect: /admin/packages → /admin/packages/available (default view)
        Route::get('/', fn() => redirect()->route('admin.packages.available', [], 301));
    });

    // ========================================
    // SETTINGS GROUP - Super Admin only
    // ========================================
    Route::prefix('settings')->name('admin.settings.')
        ->middleware('role:super_admin')
        ->group(function () {
            // Settings pages (views) - to be created in Phase 4
            Route::get('/general', [SettingsController::class, 'general'])->name('general');
            Route::get('/auth', [SettingsController::class, 'auth'])->name('auth');
            Route::get('/modules', [SettingsController::class, 'modules'])->name('modules');
            Route::get('/theme', [SettingsController::class, 'theme'])->name('theme');
            Route::get('/layout', [SettingsController::class, 'layout'])->name('layout');

            // Settings API
            Route::get('/get', [SettingsController::class, 'get'])->name('get');
            Route::post('/update', [SettingsController::class, 'update'])->name('update');
            Route::post('/reset', [SettingsController::class, 'reset'])->name('reset');

            // Legacy index route (temp compatibility until views created)
            Route::get('/', [SettingsController::class, 'index'])->name('index');
        });

    // ========================================
    // ACTIVITY LOGS - Super Admin only
    // ========================================
    Route::get('/logs', [LogsController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('admin.logs');
    Route::get('/logs/list', [LogsController::class, 'list'])
        ->middleware('role:super_admin')
        ->name('admin.logs.list');

    // ========================================
    // EXPORT DATA
    // ========================================
    Route::get('/export', [ExportController::class, 'index'])->name('admin.export');
    Route::get('/export/download', [ExportController::class, 'export'])->name('admin.export.download');
    Route::post('/export', [ExportController::class, 'export'])->name('admin.export.process');

    // ========================================
    // LEGACY REDIRECTS (301 Permanent)
    // ========================================
    Route::get('/users-legacy', function () {
        $tab = request()->query('tab');
        if ($tab === 'pending') return redirect()->route('admin.users.pending', [], 301);
        if ($tab === 'invitations') return redirect()->route('admin.users.invitations', [], 301);
        if ($tab === 'roles') return redirect()->route('admin.roles.index', [], 301);
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

// ============================================================================
// MANAGEMENT ROUTES
// Requires login + access to at least one installed package (any role that
// appears in section_role_access). Google Groups → role sync happens at
// login time, so user_global_roles is already populated.
// ============================================================================
Route::prefix('management')->middleware(['web', 'mgmt'])->group(function () {

    // Management Dashboard — Google Admin-style module card grid
    Route::get('/', [ManagementDashboardController::class, 'index'])
        ->name('management.dashboard');
});
