@extends('layouts.enterprise')

@php
use Hub\Database;

// Admin context configuration
$context = 'admin';

// Check for installed packages to determine if Configure is needed
$db = Database::getInstance();
$installedCount = $db->fetchOne(
    "SELECT COUNT(DISTINCT package_id) as count
     FROM section_installations
     WHERE status = 'installed'"
)['count'] ?? 0;

// Build Package Management menu item
$packageMenuItem = [];
if ($installedCount > 0) {
    // Has installed packages - expandable with Configure submenu
    $packageMenuItem = [
        'type' => 'expandable',
        'id' => 'packages',
        'label' => 'Package Management',
        'icon' => 'fas fa-box',
        'url' => '/admin/packages', // Clickable - goes to Package Management
        'submenu' => [
            [
                'id' => 'configure',
                'label' => 'Configure',
                'url' => '/admin/packages/configure'
            ]
        ]
    ];
} else {
    // No packages installed - simple link
    $packageMenuItem = ['type' => 'link', 'id' => 'packages', 'label' => 'Package Management', 'url' => '/admin/packages', 'icon' => 'fas fa-box'];
}

// Build admin nav items
$navItems = [
    ['type' => 'link', 'id' => 'home', 'label' => 'Home', 'url' => '/admin/', 'icon' => 'fas fa-home'],
    ['type' => 'link', 'id' => 'users', 'label' => 'Users', 'url' => '/admin/users', 'icon' => 'fas fa-users'],
    $packageMenuItem,
    ['type' => 'link', 'id' => 'settings', 'label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fas fa-cog'],
    ['type' => 'link', 'id' => 'logs', 'label' => 'Activity Logs', 'url' => '/admin/logs', 'icon' => 'fas fa-list-alt'],
    ['type' => 'link', 'id' => 'export', 'label' => 'Export Data', 'url' => '/admin/export', 'icon' => 'fas fa-download'],
];

// Determine active item based on current route
$currentPath = $_SERVER['REQUEST_URI'] ?? '/admin/';
$activeItem = 'home'; // default
if (strpos($currentPath, '/admin/users') !== false) $activeItem = 'users';
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
