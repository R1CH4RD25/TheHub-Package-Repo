@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<!-- Admin Modules Grid - Google Admin Console style -->
<div class="mgmt-modules-grid admin-responsive">

    <!-- User Management -->
    <div class="mgmt-module-card mgmt-rich-card">
        <div class="module-header">
            <div class="module-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="module-header-text">
                <h3>User Management</h3>
                <p class="module-subtitle">Add or manage users, roles, and permissions</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="module-manage-link">Manage</a>
        </div>
        <div class="module-quick-actions">
            <a href="{{ route('admin.users.index') }}?action=invite" class="module-action-link">Invite a user</a>
            <a href="{{ route('admin.users.index') }}?tab=roles" class="module-action-link">Manage roles</a>
            <a href="{{ route('admin.users.index') }}?tab=pending" class="module-action-link">Pending approvals</a>
        </div>
    </div>

    <!-- Package Management -->
    <div class="mgmt-module-card mgmt-rich-card">
        <div class="module-header">
            <div class="module-icon purple">
                <i class="fas fa-box"></i>
            </div>
            <div class="module-header-text">
                <h3>Package Management</h3>
                <p class="module-subtitle">Upload, install, and manage system packages</p>
            </div>
            <a href="{{ route('admin.packages.available') }}" class="module-manage-link">Manage</a>
        </div>
        <div class="module-quick-actions">
            <a href="{{ route('admin.packages.available') }}" class="module-action-link">Available packages</a>
            <a href="{{ route('admin.packages.installed') }}" class="module-action-link">Installed packages</a>
            <a href="{{ route('admin.packages.available') }}" class="module-action-link">Upload a package</a>
        </div>
    </div>

    <!-- Site Settings -->
    <div class="mgmt-module-card mgmt-rich-card">
        <div class="module-header">
            <div class="module-icon orange">
                <i class="fas fa-cog"></i>
            </div>
            <div class="module-header-text">
                <h3>Site Settings</h3>
                <p class="module-subtitle">Configure site-wide settings and branding</p>
            </div>
            <a href="{{ route('admin.settings.general') }}" class="module-manage-link">Manage</a>
        </div>
        <div class="module-quick-actions">
            <a href="{{ route('admin.settings.general') }}" class="module-action-link">General settings</a>
            <a href="{{ route('admin.settings.auth') }}" class="module-action-link">Authentication</a>
            <a href="{{ route('admin.settings.theme') }}" class="module-action-link">Branding & Theme</a>
        </div>
    </div>

    <!-- Activity Logs -->
    <div class="mgmt-module-card mgmt-rich-card">
        <div class="module-header">
            <div class="module-icon green">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="module-header-text">
                <h3>Activity Logs</h3>
                <p class="module-subtitle">View audit logs and system activity</p>
            </div>
            <a href="{{ route('admin.logs') }}" class="module-manage-link">View</a>
        </div>
    </div>

    <!-- Data Export -->
    <div class="mgmt-module-card mgmt-rich-card">
        <div class="module-header">
            <div class="module-icon teal">
                <i class="fas fa-file-export"></i>
            </div>
            <div class="module-header-text">
                <h3>Data Export</h3>
                <p class="module-subtitle">Export reports and system data</p>
            </div>
            <a href="{{ route('admin.export') }}" class="module-manage-link">Export</a>
        </div>
    </div>

</div>
@endsection
