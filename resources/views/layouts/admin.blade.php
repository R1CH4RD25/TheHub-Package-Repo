@extends('layouts.enterprise')

@php
// Admin context configuration
$context = 'admin';

// Build admin nav items
$navItems = [
    ['type' => 'link', 'id' => 'home', 'label' => 'Home', 'url' => '/admin/', 'icon' => 'fas fa-home'],
    ['type' => 'link', 'id' => 'users', 'label' => 'Users', 'url' => '/admin/users', 'icon' => 'fas fa-users'],
    ['type' => 'link', 'id' => 'packages', 'label' => 'Packages', 'url' => '/admin/packages', 'icon' => 'fas fa-box'],
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
