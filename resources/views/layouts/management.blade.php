@extends('layouts.enterprise')

@php
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
@endphp

@section('title', ($pageTitle ?? $mgmtDisplayName . ' Console'))
