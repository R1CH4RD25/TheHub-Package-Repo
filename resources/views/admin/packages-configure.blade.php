@extends('layouts.admin')

@section('title', 'Configure Packages')

@section('content')
<div class="content-wrapper">
    <div class="content-header">
        <div class="header-left">
            <h1><i class="fas fa-cog"></i> Configure Packages</h1>
            <p class="text-muted">Manage settings for installed packages</p>
        </div>
    </div>

    <div class="content-body">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Installed Packages</h3>
            </div>
            <div class="card-body">
                <div id="configurePackagesTable" class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Package</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="configurePackagesBody">
                            <tr>
                                <td colspan="4" class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading packages...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadConfigurablePackages();
});

async function loadConfigurablePackages() {
    try {
        const response = await fetch('/admin/packages/list?type=installed');
        const data = await response.json();
        
        const tbody = document.getElementById('configurePackagesBody');
        
        if (!data.success || !data.packages || data.packages.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No installed packages found</td></tr>';
            return;
        }
        
        tbody.innerHTML = data.packages.map(pkg => `
            <tr>
                <td>
                    <strong>${pkg.display_name || pkg.name}</strong>
                    <br>
                    <small class="text-muted">${pkg.description || 'No description'}</small>
                </td>
                <td>${pkg.version || 'Unknown'}</td>
                <td>
                    <span class="badge badge-success">
                        <i class="fas fa-check-circle"></i> Installed
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="configurePackage('${pkg.package_id}', '${pkg.name}')">
                        <i class="fas fa-cog"></i> Configure
                    </button>
                </td>
            </tr>
        `).join('');
        
    } catch (error) {
        console.error('Error loading packages:', error);
        document.getElementById('configurePackagesBody').innerHTML = 
            '<tr><td colspan="4" class="text-center text-danger">Error loading packages</td></tr>';
    }
}

function configurePackage(packageId, packageName) {
    // TODO: Open configuration interface for this specific package
    // For now, show a message
    Swal.fire({
        title: 'Configure Package',
        html: `<p>Configuration interface for <strong>${packageName}</strong> will be available soon.</p>
               <p class="text-muted">This will allow you to manage package settings, permissions, and features.</p>`,
        icon: 'info',
        confirmButtonText: 'OK'
    });
}
</script>
@endsection
