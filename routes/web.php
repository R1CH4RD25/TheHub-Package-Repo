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

// Admin routes (Laravel migration proof-of-concept)
// Requires authentication: admin, super_admin
Route::prefix('admin')->middleware(['auth:admin,super_admin'])->group(function () {
    // Admin Dashboard - shows overview with links to all sections
    Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

    // Users Management
    Route::get('/users', [UserController::class, 'index'])->name('admin.users');
    Route::get('/users/list', [UserController::class, 'list'])->name('admin.users.list');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('admin.users.update');
    Route::get('/invitations', [UserController::class, 'invitations'])->name('admin.invitations');
    Route::post('/invitations', [UserController::class, 'sendInvitation'])->name('admin.invitations.send');
    Route::delete('/invitations/{id}', [UserController::class, 'revokeInvitation'])->name('admin.invitations.revoke');

    // Role & Permission Management
    Route::get('/roles', [RoleController::class, 'index'])->name('admin.roles.index');
    Route::get('/roles/{id}', [RoleController::class, 'show'])->name('admin.roles.show');
    Route::post('/roles', [RoleController::class, 'store'])->name('admin.roles.store');
    Route::put('/roles/{id}', [RoleController::class, 'update'])->name('admin.roles.update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])->name('admin.roles.destroy');
    Route::get('/permissions', [RoleController::class, 'permissions'])->name('admin.permissions');
    Route::post('/roles/assign-user', [RoleController::class, 'assignToUser'])->name('admin.roles.assign');
    Route::delete('/roles/remove-user', [RoleController::class, 'removeFromUser'])->name('admin.roles.remove');
    Route::get('/users/{id}/permissions', [RoleController::class, 'userPermissions'])->name('admin.users.permissions');

    // Package Management
    Route::get('/packages', [PackageController::class, 'index'])->name('admin.packages');
    Route::get('/packages/configure', [PackageController::class, 'configure'])->name('admin.packages.configure');
    Route::get('/packages/list', [PackageController::class, 'list'])->name('admin.packages.list');
    Route::post('/packages/upload', [PackageController::class, 'upload'])->name('admin.packages.upload');
    Route::post('/packages/{id}/install', [PackageController::class, 'install'])->name('admin.packages.install');
    Route::delete('/packages/{id}', [PackageController::class, 'delete'])->name('admin.packages.delete');
    Route::delete('/packages/{packageId}/uninstall', [PackageController::class, 'uninstall'])->name('admin.packages.uninstall');
    Route::get('/packages/{id}/validation', [PackageController::class, 'validation'])->name('admin.packages.validation');
    Route::post('/packages/discovery/search', [PackageDiscoveryController::class, 'search'])->name('admin.packages.discovery.search');
    Route::post('/packages/discovery/download', [PackageDiscoveryController::class, 'download'])->name('admin.packages.discovery.download');

    // Site Settings (Super Admin Only)
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::get('/settings/get', [SettingsController::class, 'get'])->name('admin.settings.get');
    Route::post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');
    Route::post('/settings/reset', [SettingsController::class, 'reset'])->name('admin.settings.reset');

    // Activity Logs (Super Admin Only)
    Route::get('/logs', [LogsController::class, 'index'])->name('admin.logs');
    Route::get('/logs/list', [LogsController::class, 'list'])->name('admin.logs.list');

    // Export Data
    Route::get('/export', [ExportController::class, 'index'])->name('admin.export');
    Route::get('/export/download', [ExportController::class, 'export'])->name('admin.export.download');
    Route::post('/export', [ExportController::class, 'export'])->name('admin.export.process');
});
