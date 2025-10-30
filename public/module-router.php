<?php

/**
 * Package Module Router
 * 
 * Routes requests to package modules and renders them using
 * the appropriate module renderer.
 * 
 * Usage:
 *   include 'module-router.php';
 *   $result = renderModule($packageSlug, $moduleId);
 * 
 * @author The Hub Team
 * @version 1.0.0
 */

require_once __DIR__ . '/src/bootstrap.php';

use Hub\Database;
use Hub\PackageManager;
use Hub\Modules\ModuleFactory;
use Hub\Auth;

/**
 * Render a package module
 * 
 * @param string $packageSlug Package identifier
 * @param string $moduleId Module ID from manifest
 * @return string HTML output
 */
function renderModule(string $packageSlug, string $moduleId): string
{
    try {
        // Get package manifest
        $manifest = getPackageManifest($packageSlug);
        if (!$manifest) {
            return '<div class="alert alert-danger">Package not found</div>';
        }
        
        // Find module in manifest
        $moduleConfig = findModule($manifest, $moduleId);
        if (!$moduleConfig) {
            return '<div class="alert alert-danger">Module not found</div>';
        }
        
        // Check access
        if (!hasModuleAccess($packageSlug, $moduleId)) {
            return '<div class="alert alert-warning">You do not have permission to access this module</div>';
        }
        
        // Create module instance
        $module = ModuleFactory::create($moduleConfig);
        
        // Handle POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $module->handle($_POST);
            
            // Handle redirect
            if (!empty($result['redirect'])) {
                header('Location: ' . $result['redirect']);
                exit;
            }
            
            // Show message
            if (!empty($result['message'])) {
                $_SESSION['flash_message'] = $result['message'];
                $_SESSION['flash_type'] = $result['success'] ? 'success' : 'danger';
            }
        }
        
        // Render module
        return $module->render();
        
    } catch (Exception $e) {
        error_log("Module rendering error: " . $e->getMessage());
        return '<div class="alert alert-danger">An error occurred while loading this module</div>';
    }
}

/**
 * Get package manifest from database
 * 
 * @param string $packageSlug Package slug
 * @return array|null Manifest data
 */
function getPackageManifest(string $packageSlug): ?array
{
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        SELECT manifest
        FROM packages
        WHERE slug = ?
        AND is_active = 1
        AND tenant_id = ?
    ");
    
    $stmt->execute([$packageSlug, $_SESSION['tenant_id'] ?? 'default']);
    $result = $stmt->fetch();
    
    if (!$result) {
        return null;
    }
    
    return json_decode($result['manifest'], true);
}

/**
 * Find module in manifest
 * 
 * @param array $manifest Package manifest
 * @param string $moduleId Module ID
 * @return array|null Module config
 */
function findModule(array $manifest, string $moduleId): ?array
{
    $modules = $manifest['modules'] ?? [];
    
    foreach ($modules as $module) {
        if ($module['id'] === $moduleId) {
            return $module;
        }
    }
    
    return null;
}

/**
 * Check if user has access to module
 * 
 * @param string $packageSlug Package slug
 * @param string $moduleId Module ID
 * @return bool True if has access
 */
function hasModuleAccess(string $packageSlug, string $moduleId): bool
{
    // Must be logged in
    if (!Auth::isLoggedIn()) {
        return false;
    }
    
    $db = Database::getInstance();
    
    // Check if user has access to package
    $stmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM package_access
        WHERE package_slug = ?
        AND user_id = ?
        AND is_active = 1
    ");
    
    $stmt->execute([$packageSlug, Auth::getUserId()]);
    $result = $stmt->fetch();
    
    if (($result['count'] ?? 0) > 0) {
        return true;
    }
    
    // Check role-based access
    // TODO: Implement role-based module access from manifest
    
    // Default: admin and super_admin have access
    return Auth::hasRole('admin') || Auth::hasRole('super_admin');
}

/**
 * Render flash message if present
 * 
 * @return string HTML
 */
function renderFlashMessage(): string
{
    if (empty($_SESSION['flash_message'])) {
        return '';
    }
    
    $message = $_SESSION['flash_message'];
    $type = $_SESSION['flash_type'] ?? 'info';
    
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
    
    return '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show" role="alert">' .
           htmlspecialchars($message) .
           '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' .
           '</div>';
}

/**
 * Get all modules for a package (for navigation)
 * 
 * @param string $packageSlug Package slug
 * @return array List of modules
 */
function getPackageModules(string $packageSlug): array
{
    $manifest = getPackageManifest($packageSlug);
    if (!$manifest) {
        return [];
    }
    
    $modules = [];
    foreach ($manifest['modules'] ?? [] as $module) {
        if (hasModuleAccess($packageSlug, $module['id'])) {
            $modules[] = [
                'id' => $module['id'],
                'title' => $module['title'] ?? $module['id'],
                'type' => $module['type'],
                'icon' => $module['icon'] ?? 'bi-file',
                'description' => $module['description'] ?? ''
            ];
        }
    }
    
    return $modules;
}
