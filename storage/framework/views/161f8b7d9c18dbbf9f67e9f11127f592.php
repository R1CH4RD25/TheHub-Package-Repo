<?php
/**
 * Management Layout — mirrors Admin layout but for management context.
 *
 * Inherits the enterprise shell (sidebar + header + main) and injects:
 *   - context = 'management' (so enterprise.blade picks mgmt-bundle.css)
 *   - Sidebar built from installed packages, not hardcoded admin sections
 *   - Breadcrumbs: Home → Management → [current page]
 */

use Hub\SiteSettings;

// Context tells the enterprise layout to use mgmt-bundle.css
$context = 'management';

$mgmtDisplayName = $mgmtDisplayName ?? SiteSettings::get('mgmt_display_name', 'Management');
$mgmtIcon        = $mgmtIcon        ?? SiteSettings::get('mgmt_icon', 'bi-kanban');

// Sidebar config
$sidebarTitle = $mgmtDisplayName;
$sidebarIcon  = $mgmtIcon;
$logoUrl      = '/management';

// Breadcrumbs — child views can override via @section('breadcrumbs')
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Home', 'url' => '/hub.php'],
    ['label' => $mgmtDisplayName],
];

// Active item — child views set this
$activeItem = $activeItem ?? 'home';
?>

<?php $__env->startSection('title', ($pageTitle ?? $mgmtDisplayName . ' Console')); ?>

<?php echo $__env->make('layouts.enterprise', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/woodson/thehub/resources/views/layouts/management.blade.php ENDPATH**/ ?>