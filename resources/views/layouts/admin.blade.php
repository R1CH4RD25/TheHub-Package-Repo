@extends('layouts.enterprise')

@php
use Hub\Database;

// Admin context configuration
$context = 'admin';

// Determine current path for active state detection
$currentPath = $_SERVER['REQUEST_URI'] ?? '/admin/';
$currentPath = strtok($currentPath, '?'); // strip query string

// Check for installed packages to determine if Configure is needed
$db = Database::getInstance();
$installedCount = $db->fetchOne(
    "SELECT COUNT(DISTINCT package_id) as count
     FROM section_installations
     WHERE status = 'installed'"
)['count'] ?? 0;

// Check for pending user counts for badge
$pendingCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM users WHERE is_active = 0 AND google_id IS NOT NULL"
)['count'] ?? 0;

// =============================================
// BUILD GOOGLE ADMIN-STYLE NAV ITEMS
// Each section with children = expandable dropdown
// Each child = real route (no tabs!)
// =============================================

$userRole = ($user['role'] ?? $_SESSION['role'] ?? 'user');
$isSuperAdmin = ($userRole === 'super_admin');

// --- Users Section (expandable with sub-pages) ---
$usersSubmenu = [
    ['id' => 'users-active', 'label' => 'Active Users', 'url' => '/admin/users',
     'active' => ($currentPath === '/admin/users' || $currentPath === '/admin/users/')],
    ['id' => 'users-pending', 'label' => 'Pending Approvals', 'url' => '/admin/users/pending',
     'active' => (strpos($currentPath, '/admin/users/pending') === 0)],
    ['id' => 'users-invitations', 'label' => 'Invitations', 'url' => '/admin/users/invitations',
     'active' => (strpos($currentPath, '/admin/users/invitations') === 0)],
];
if ($isSuperAdmin) {
    $usersSubmenu[] = ['id' => 'users-roles', 'label' => 'Organization Roles', 'url' => '/admin/roles',
                       'active' => (strpos($currentPath, '/admin/roles') === 0)];
}

// Badge for pending users
$usersBadge = ($pendingCount > 0) ? ['count' => $pendingCount, 'color' => 'var(--warning)'] : null;

$usersNavItem = [
    'type' => 'expandable',
    'id' => 'users',
    'label' => 'Users',
    'icon' => 'fas fa-users',
    'url' => '/admin/users',
    'submenu' => $usersSubmenu,
    'badge' => $usersBadge,
];

// --- Package Management Section (expandable with sub-pages) ---
$packagesSubmenu = [
    ['id' => 'packages-available', 'label' => 'Available', 'url' => '/admin/packages/available',
     'active' => (strpos($currentPath, '/admin/packages/available') === 0 || $currentPath === '/admin/packages' || $currentPath === '/admin/packages/')],
    ['id' => 'packages-installed', 'label' => 'Installed', 'url' => '/admin/packages/installed',
     'active' => (strpos($currentPath, '/admin/packages/installed') === 0)],
    ['id' => 'packages-updates', 'label' => 'Updates', 'url' => '/admin/packages/updates',
     'active' => (strpos($currentPath, '/admin/packages/updates') === 0)],
];
if ($installedCount > 0) {
    $packagesSubmenu[] = ['id' => 'packages-configure', 'label' => 'Configure', 'url' => '/admin/packages/configure',
                          'active' => (strpos($currentPath, '/admin/packages/configure') === 0)];
}

$packagesNavItem = [
    'type' => 'expandable',
    'id' => 'packages',
    'label' => 'Package Management',
    'icon' => 'fas fa-box',
    'url' => '/admin/packages',
    'submenu' => $packagesSubmenu,
];

// --- Settings Section (expandable with sub-pages, super admin only) ---
$settingsSubmenu = [
    ['id' => 'settings-general', 'label' => 'General', 'url' => '/admin/settings/general',
     'active' => (strpos($currentPath, '/admin/settings/general') === 0 || $currentPath === '/admin/settings' || $currentPath === '/admin/settings/')],
    ['id' => 'settings-auth', 'label' => 'Authentication', 'url' => '/admin/settings/auth',
     'active' => (strpos($currentPath, '/admin/settings/auth') === 0)],
    ['id' => 'settings-modules', 'label' => 'Modules', 'url' => '/admin/settings/modules',
     'active' => (strpos($currentPath, '/admin/settings/modules') === 0)],
    ['id' => 'settings-theme', 'label' => 'Theme', 'url' => '/admin/settings/theme',
     'active' => (strpos($currentPath, '/admin/settings/theme') === 0)],
    ['id' => 'settings-layout', 'label' => 'Layout', 'url' => '/admin/settings/layout',
     'active' => (strpos($currentPath, '/admin/settings/layout') === 0)],
];

$settingsNavItem = [
    'type' => 'expandable',
    'id' => 'settings',
    'label' => 'Settings',
    'icon' => 'fas fa-cog',
    'url' => '/admin/settings',
    'submenu' => $settingsSubmenu,
    'permission' => ['super_admin'],
];

// --- Build final nav items array ---
$navItems = [
    ['type' => 'link', 'id' => 'home', 'label' => 'Home', 'url' => '/admin/', 'icon' => 'fas fa-home'],
    $usersNavItem,
    $packagesNavItem,
    $settingsNavItem,
    ['type' => 'link', 'id' => 'logs', 'label' => 'Activity Logs', 'url' => '/admin/logs', 'icon' => 'fas fa-list-alt',
     'permission' => ['super_admin']],
    ['type' => 'link', 'id' => 'export', 'label' => 'Export Data', 'url' => '/admin/export', 'icon' => 'fas fa-download'],
];

// Determine active top-level item based on current route
$activeItem = 'home'; // default
if (strpos($currentPath, '/admin/users') !== false || strpos($currentPath, '/admin/roles') !== false) $activeItem = 'users';
elseif (strpos($currentPath, '/admin/packages') !== false) $activeItem = 'packages';
elseif (strpos($currentPath, '/admin/settings') !== false) $activeItem = 'settings';
elseif (strpos($currentPath, '/admin/logs') !== false) $activeItem = 'logs';
elseif (strpos($currentPath, '/admin/export') !== false) $activeItem = 'export';

// Sidebar configuration
$sidebarTitle = 'Admin';
$sidebarIcon = 'fas fa-shield-alt';
$logoUrl = '/admin/';

// Breadcrumbs
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/hub.php'],
    ['label' => 'Admin']
];
@endphp
