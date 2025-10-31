<?php
use Hub\Layout;

// Determine page type based on current script
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$isAdminPage = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false);

if ($isAdminPage) {
    // Build mobile menu items for dashboard
    $mobileMenuItems = [];
    if (isset($canSeeUserManagement) && $canSeeUserManagement) {
        $mobileMenuItems[] = ['tab' => 'users', 'label' => 'User Management', 'active' => true];
    }
    if ((isset($canSeeSectionAccess) && $canSeeSectionAccess) || (isset($canSeeManageSections) && $canSeeManageSections)) {
        $mobileMenuItems[] = ['tab' => 'sections', 'label' => 'Sections', 'active' => false];
    }
    if (isset($isSuperAdmin) && $isSuperAdmin) {
        $mobileMenuItems[] = ['tab' => 'site-settings', 'label' => 'Site Settings', 'active' => false];
        $mobileMenuItems[] = ['tab' => 'logs', 'label' => 'Activity Logs', 'active' => false];
    }
    if (isset($canSeeExport) && $canSeeExport) {
        $mobileMenuItems[] = ['tab' => 'export', 'label' => 'Export Data', 'active' => false];
    }
    
    Layout::renderHeader($user, $userRole, 'dashboard', ['mobileMenuItems' => $mobileMenuItems]);
} else {
    Layout::renderHeader($user, $userRole, 'hub');
}
