@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<!-- Admin Modules Grid - Clean like Google Admin Console -->
<div class="mgmt-modules-grid admin-responsive">
    <!-- User Management Module -->
        <a href="{{ route('admin.users.index') }}" class="mgmt-module-card">
            <div class="module-header">
                <div class="module-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <h3>User Management</h3>
            </div>
            <p class="module-description">Manage users, roles, permissions, and invitations</p>
        </a>

        <!-- Package Management Module -->
        <a href="{{ route('admin.packages.available') }}" class="mgmt-module-card">
            <div class="module-header">
                <div class="module-icon purple">
                    <i class="fas fa-box"></i>
                </div>
                <h3>Package Management</h3>
            </div>
            <p class="module-description">Upload, install, and manage system packages</p>
        </a>

        <!-- Site Settings Module -->
        <a href="{{ route('admin.settings.general') }}" class="mgmt-module-card">
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
@endsection
