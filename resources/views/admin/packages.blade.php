@extends('layouts.admin')

@section('title', 'Package Management')

@section('content')
<div class="admin-tab active">
    <div class="tab-header">
        <div>
            <h1><i class="fas fa-box"></i> Package Management</h1>
            <p class="text-muted">Install and manage section packages</p>
        </div>
        @if($isSuperAdmin)
            <button id="uploadPackageBtn" class="btn btn-primary">
                <i class="bi bi-upload"></i> Upload Package
            </button>
        @endif
    </div>

    <div class="tab-content-scroll">
        <!-- Package Sub-tabs -->
        <div class="user-subtabs">
            <button class="subtab-btn active" data-subtab="installed-packages">
                <i class="fas fa-check-circle"></i> Installed Packages
            </button>
            @if($isSuperAdmin)
                <button class="subtab-btn" data-subtab="available-packages">
                    <i class="fas fa-cloud-download-alt"></i> Available Packages
                </button>
                <button class="subtab-btn" data-subtab="package-updates">
                    <i class="fas fa-arrow-circle-up"></i> Updates
                </button>
            @endif
        </div>

        <!-- Installed Packages Subtab -->
        <div id="subtab-installed-packages" class="user-subtab active">
            <p class="info-text">
                <strong>📦 Installed Packages:</strong> Manage your installed section packages.
                You can upgrade, downgrade, or uninstall packages from here.
            </p>
            <div id="installedPackagesTable" class="data-table-container">
                <p class="text-center">Loading installed packages...</p>
            </div>
        </div>

        <!-- Available Packages Subtab (Super Admin Only) -->
        @if($isSuperAdmin)
            <div id="subtab-available-packages" class="user-subtab">
                <p class="info-text">
                    <strong>🆕 Available Packages:</strong> Upload and review new packages before installation.
                    All packages are validated for compatibility before they can be installed.
                </p>

                <!-- Upload Area -->
                <div style="background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 8px; padding: 1.25rem 1rem; text-align: center; margin-bottom: 1.5rem;">
                    <input type="file" id="packageFileInput" accept=".hubpkg" style="display: none;">
                    <div id="uploadDropzone" style="cursor: pointer;">
                        <i class="bi bi-cloud-upload" style="font-size: 2rem; color: #6c757d; display: block; margin-bottom: 0.5rem;"></i>
                        <h3 style="color: #495057; margin-bottom: 0.35rem; font-size: 1.1rem;">Drop .hubpkg file here or click to browse</h3>
                        <p style="color: #6c757d; margin: 0; font-size: 0.9rem;">Maximum file size: 50MB</p>
                    </div>
                    <div id="uploadProgress" style="display: none; margin-top: 1rem;">
                        <div style="background: #e9ecef; border-radius: 4px; height: 8px; overflow: hidden;">
                            <div id="uploadProgressBar" style="background: linear-gradient(90deg, #4CAF50, #81C784); height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <p id="uploadProgressText" style="margin-top: 0.5rem; color: #495057;">Uploading...</p>
                    </div>
                </div>

                <div id="availablePackagesTable" class="data-table-container">
                    <p class="text-center">Loading available packages...</p>
                </div>
            </div>

            <!-- Package Updates Subtab -->
            <div id="subtab-package-updates" class="user-subtab">
                <p class="info-text">
                    <strong>🔄 Available Updates:</strong> Keep your packages up-to-date with the latest features and security fixes.
                </p>
                <div id="packageUpdatesTable" class="data-table-container">
                    <p class="text-center">Loading updates...</p>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
const isSuperAdmin = {{ $isSuperAdmin ? 'true' : 'false' }};

// Subtab switching
document.querySelectorAll('.subtab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const subtab = this.getAttribute('data-subtab');

        document.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        document.querySelectorAll('.user-subtab').forEach(s => s.classList.remove('active'));
        document.getElementById(`subtab-${subtab}`).classList.add('active');

        loadSubtabData(subtab);
    });
});

function loadSubtabData(subtab) {
    switch(subtab) {
        case 'installed-packages':
            loadInstalledPackages();
            break;
        case 'available-packages':
            loadAvailablePackages();
            break;
        case 'package-updates':
            loadPackageUpdates();
            break;
    }
}

function loadInstalledPackages() {
    fetch('/admin/packages/list?type=installed')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderInstalledPackages(data.packages);
            } else {
                notyf.error('Failed to load packages');
            }
        });
}

function loadAvailablePackages() {
    fetch('/admin/packages/list?type=available')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderAvailablePackages(data.packages);
            } else {
                notyf.error('Failed to load packages');
            }
        });
}

function loadPackageUpdates() {
    fetch('/admin/packages/list?type=updates')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                renderPackageUpdates(data.packages);
            } else {
                notyf.error('Failed to load updates');
            }
        });
}

function renderInstalledPackages(packages) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Package Name</th>
                    <th>Version</th>
                    <th>Installed</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${packages.length === 0 ? '<tr><td colspan="5" class="text-center">No packages installed</td></tr>' : ''}
                ${packages.map(p => `
                    <tr>
                        <td><strong>${p.display_name || p.slug || p.package_id || 'Unknown'}</strong></td>
                        <td>${p.installed_version || p.version || '1.0.0'}</td>
                        <td>${new Date(p.installed_at).toLocaleDateString()}</td>
                        <td><span class="badge badge-success">Active</span></td>
                        <td>
                            ${isSuperAdmin ? `
                                <button class="btn btn-sm btn-danger" onclick="uninstallPackage('${p.package_id}')">
                                    <i class="fas fa-trash"></i> Uninstall
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('installedPackagesTable').innerHTML = html;
}

function renderAvailablePackages(packages) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Package Name</th>
                    <th>Version</th>
                    <th>Uploaded</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${packages.length === 0 ? '<tr><td colspan="5" class="text-center">No packages available</td></tr>' : ''}
                ${packages.map(p => `
                    <tr>
                        <td><strong>${p.display_name || p.slug || p.package_id || 'Unknown'}</strong></td>
                        <td>${p.version || '1.0.0'}</td>
                        <td>${new Date(p.created_at).toLocaleDateString()}</td>
                        <td><span class="badge badge-${p.can_install ? 'success' : 'warning'}">${p.validation_status || 'Pending'}</span></td>
                        <td>
                            ${p.can_install ? `
                                <button class="btn btn-sm btn-success" onclick="installPackage(${p.id})">
                                    <i class="fas fa-download"></i> Install
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-info" onclick="viewValidation(${p.id})">
                                    <i class="fas fa-eye"></i> View Validation
                                </button>
                            `}
                            <button class="btn btn-sm btn-danger" onclick="deletePackage(${p.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('availablePackagesTable').innerHTML = html;
}

function renderPackageUpdates(updates) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Current</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${updates.length === 0 ? '<tr><td colspan="4" class="text-center">All packages up to date</td></tr>' : ''}
                ${updates.map(u => `
                    <tr>
                        <td><strong>${u.name}</strong></td>
                        <td>${u.current_version}</td>
                        <td>${u.available_version}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="upgradePackage(${u.id})">
                                <i class="fas fa-arrow-up"></i> Upgrade
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('packageUpdatesTable').innerHTML = html;
}

function installPackage(packageId) {
    Swal.fire({
        title: 'Install Package?',
        text: 'This will create a new section from this package',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, install'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/admin/packages/${packageId}/install`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success('Package installed successfully');
                    loadInstalledPackages();
                    loadAvailablePackages();
                } else {
                    notyf.error(data.error || 'Installation failed');
                }
            });
        }
    });
}

function uninstallPackage(packageId) {
    Swal.fire({
        title: 'Uninstall Package?',
        text: 'This will remove the section and all its data',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, uninstall',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/admin/packages/${packageId}/uninstall`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success('Package uninstalled');
                    loadInstalledPackages();
                } else {
                    notyf.error(data.error || 'Uninstall failed');
                }
            });
        }
    });
}

function deletePackage(packageId) {
    Swal.fire({
        title: 'Delete Package?',
        text: 'This will remove the uploaded package file',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/admin/packages/${packageId}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success('Package deleted');
                    loadAvailablePackages();
                } else {
                    notyf.error(data.error || 'Delete failed');
                }
            });
        }
    });
}

// Upload handling
if (isSuperAdmin) {
    const uploadBtn = document.getElementById('uploadPackageBtn');
    const fileInput = document.getElementById('packageFileInput');
    const dropzone = document.getElementById('uploadDropzone');

    uploadBtn?.addEventListener('click', () => fileInput.click());
    dropzone?.addEventListener('click', () => fileInput.click());

    fileInput?.addEventListener('change', function() {
        if (this.files.length > 0) {
            uploadPackageFile(this.files[0]);
        }
    });

    dropzone?.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.style.background = '#e9ecef';
    });

    dropzone?.addEventListener('dragleave', () => {
        dropzone.style.background = '#f8f9fa';
    });

    dropzone?.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.style.background = '#f8f9fa';
        if (e.dataTransfer.files.length > 0) {
            uploadPackageFile(e.dataTransfer.files[0]);
        }
    });
}

function uploadPackageFile(file) {
    const formData = new FormData();
    formData.append('package', file);

    document.getElementById('uploadProgress').style.display = 'block';
    document.getElementById('uploadProgressBar').style.width = '0%';

    fetch('/admin/packages/upload', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('uploadProgress').style.display = 'none';
        if (data.success) {
            notyf.success('Package uploaded successfully');
            loadAvailablePackages();
        } else {
            notyf.error(data.error || 'Upload failed');
        }
    })
    .catch(err => {
        document.getElementById('uploadProgress').style.display = 'none';
        notyf.error('Upload failed');
        console.error(err);
    });
}

// Load initial data
loadInstalledPackages();
</script>
@endpush
@endsection
