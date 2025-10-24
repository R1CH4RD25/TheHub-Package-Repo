<?php
use Hub\SiteSettings;
use Hub\Layout;

// Build mobile menu items for dashboard
$mobileMenuItems = [];
if ($canSeeUserManagement) {
    $mobileMenuItems[] = ['tab' => 'users', 'label' => 'User Management', 'active' => true];
}
if ($canSeeSectionAccess || $canSeeManageSections) {
    $mobileMenuItems[] = ['tab' => 'sections', 'label' => 'Sections', 'active' => false];
}
if ($isSuperAdmin) {
    $mobileMenuItems[] = ['tab' => 'site-settings', 'label' => 'Site Settings', 'active' => false];
    $mobileMenuItems[] = ['tab' => 'logs', 'label' => 'Activity Logs', 'active' => false];
}
if ($canSeeExport) {
    $mobileMenuItems[] = ['tab' => 'export', 'label' => 'Export Data', 'active' => false];
}

Layout::renderHeader($user, $userRole, 'dashboard', ['mobileMenuItems' => $mobileMenuItems]);
?>