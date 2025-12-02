@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<!-- Page Header -->
<div class="nd-page-header">
    <div>
        <h1>Admin Dashboard</h1>
        <p>Centralized administration and system management</p>
    </div>
</div>

<!-- Overview Metrics -->
<div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: var(--space-8);">
    <div class="metric-card">
        <div class="metric-icon">
            <i class="fas fa-users"></i>
        </div>
        <div class="metric-content">
            <div class="metric-value">5</div>
            <div class="metric-label">Admin Modules</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon gold">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="metric-content">
            <div class="metric-value">Active</div>
            <div class="metric-label">System Status</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon" style="background: var(--primary-light); color: var(--primary);">
            <i class="fas fa-cogs"></i>
        </div>
        <div class="metric-content">
            <div class="metric-value">Ready</div>
            <div class="metric-label">Configuration</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="metric-content">
            <div class="metric-value">Online</div>
            <div class="metric-label">Services</div>
        </div>
    </div>
</div>

<!-- Admin Modules Grid -->
<div>
    <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--gray-900); margin: 0 0 var(--space-1) 0;">
        Administration Modules
    </h2>
    <p style="font-size: var(--text-sm); color: var(--gray-600); margin: 0 0 var(--space-4) 0;">
        Select a module to manage system settings and resources
    </p>

    <div class="mgmt-modules-grid">
        <!-- User Management Module -->
        <a href="{{ route('admin.users') }}" class="mgmt-module-card">
            <div class="module-header">
                <div class="module-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <h3>User Management</h3>
            </div>
            <p class="module-description">Manage users, roles, permissions, and invitations</p>
        </a>

        <!-- Package Management Module -->
        <a href="{{ route('admin.packages') }}" class="mgmt-module-card">
            <div class="module-header">
                <div class="module-icon purple">
                    <i class="fas fa-box"></i>
                </div>
                <h3>Package Management</h3>
            </div>
            <p class="module-description">Upload, install, and manage system packages</p>
        </a>

        <!-- Site Settings Module -->
        <a href="{{ route('admin.settings') }}" class="mgmt-module-card">
            <div class="module-header">
                <div class="module-icon orange">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Site Settings</h3>
            </div>
            <p class="module-description">Configure site-wide settings and branding</p>
        </a>

        <!-- Activity Logs Module -->
        <a href="{{ route('admin.logs') }}" class="mgmt-module-card">
            <div class="module-header">
                <div class="module-icon green">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Activity Logs</h3>
            </div>
            <p class="module-description">View audit logs and system activity</p>
        </a>

        <!-- Data Export Module -->
        <a href="{{ route('admin.export') }}" class="mgmt-module-card">
            <div class="module-header">
                <div class="module-icon teal">
                    <i class="fas fa-file-export"></i>
                </div>
                <h3>Data Export</h3>
            </div>
            <p class="module-description">Export reports and system data</p>
        </a>
    </div>
</div>
@endsection
