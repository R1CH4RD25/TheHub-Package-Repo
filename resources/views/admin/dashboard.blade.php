@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('content')
<div class="admin-content-wrapper">
    <div class="admin-header">
        <div>
            <h1 class="admin-title">Admin Dashboard</h1>
            <p class="admin-subtitle">Manage your site from here</p>
        </div>
    </div>

    <div class="dashboard-cards">
        <div class="dashboard-card">
            <div class="card-icon card-icon-blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-content">
                <h3>User Management</h3>
                <p>Manage users, roles, and invitations</p>
                <a href="{{ route('admin.users') }}" class="card-link">Go to Users <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon card-icon-purple">
                <i class="fas fa-box"></i>
            </div>
            <div class="card-content">
                <h3>Package Management</h3>
                <p>Upload, install, and manage packages</p>
                <a href="{{ route('admin.packages') }}" class="card-link">Go to Packages <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon card-icon-orange">
                <i class="fas fa-cog"></i>
            </div>
            <div class="card-content">
                <h3>Site Settings</h3>
                <p>Configure site-wide settings and branding</p>
                <a href="{{ route('admin.settings') }}" class="card-link">Go to Settings <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon card-icon-green">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="card-content">
                <h3>Activity Logs</h3>
                <p>View audit logs and system activity</p>
                <a href="{{ route('admin.logs') }}" class="card-link">Go to Logs <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="card-icon card-icon-teal">
                <i class="fas fa-file-export"></i>
            </div>
            <div class="card-content">
                <h3>Data Export</h3>
                <p>Export reports and data</p>
                <a href="{{ route('admin.export') }}" class="card-link">Go to Export <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.dashboard-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;
    display: flex;
    gap: 1rem;
}

.dashboard-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.card-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.card-icon-blue { background: #dbeafe; color: #1e40af; }
.card-icon-purple { background: #ede9fe; color: #6d28d9; }
.card-icon-orange { background: #fed7aa; color: #c2410c; }
.card-icon-green { background: #d1fae5; color: #065f46; }
.card-icon-teal { background: #ccfbf1; color: #115e59; }

.card-content {
    flex: 1;
}

.card-content h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #111827;
}

.card-content p {
    margin: 0 0 1rem 0;
    font-size: 0.875rem;
    color: #6b7280;
}

.card-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #2563eb;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
}

.card-link:hover {
    color: #1d4ed8;
}

.card-link i {
    font-size: 0.75rem;
    transition: transform 0.2s;
}

.card-link:hover i {
    transform: translateX(4px);
}
</style>
@endsection
