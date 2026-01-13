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
                
                <!-- Discover Packages from Repository -->
                <div style="background: var(--primary-color); border-radius: 6px; padding: 0.875rem 1rem; margin-bottom: 1rem; color: white;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div>
                            <h3 style="margin: 0; font-size: 1rem; font-weight: 600;">
                                <i class="bi bi-cloud-download"></i> Discover Community Packages
                            </h3>
                            <p style="margin: 0; font-size: 0.85rem; opacity: 0.9;">
                                Browse and download packages from the official repository
                            </p>
                        </div>
                        <button id="discoverPackagesBtn" class="btn" style="background: white; color: var(--primary-color); font-weight: 600; border: none; white-space: nowrap;">
                            <i class="bi bi-search"></i> Browse Repository
                        </button>
                    </div>
                </div>

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
<script src="{{ asset('assets/js/debug-helper.js') }}"></script>
<script src="{{ asset('assets/js/admin.js') }}"></script>
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
    debugLog('API', 'Loading installed packages');
    fetch('/admin/packages/list?type=installed')
        .then(r => {
            debugLog('RESPONSE', 'Installed packages response received', { status: r.status });
            return r.json();
        })
        .then(data => {
            debugLog('RESPONSE', 'Installed packages data', { count: data.packages?.length || 0 });
            if (data.success) {
                renderInstalledPackages(data.packages);
            } else {
                debugLog('ERROR', 'Failed to load packages', data);
                notyf.error('Failed to load packages');
            }
        })
        .catch(err => {
            debugLog('ERROR', 'Load installed packages error', err);
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
    debugLog('UI', `Rendering ${packages.length} installed packages`);
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
    const container = document.getElementById('installedPackagesTable');
    if (container) {
        container.innerHTML = html;
        debugLog('UI', 'Installed packages table updated successfully');
    } else {
        debugLog('ERROR', 'installedPackagesTable container not found!');
    }
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
    debugLog('ACTION', `User clicked INSTALL for package ID: ${packageId}`);
    Swal.fire({
        title: 'Install Package?',
        text: 'This will create a new section from this package',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, install'
    }).then((result) => {
        if (result.isConfirmed) {
            debugLog('ACTION', `User confirmed install for package ID: ${packageId}`);
            debugLog('API', `Sending POST request to /admin/packages/${packageId}/install`);
            fetch(`/admin/packages/${packageId}/install`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => {
                debugLog('RESPONSE', 'Install response received', { status: r.status });
                return r.json();
            })
            .then(data => {
                debugLog('RESPONSE', 'Install data', data);
                if (data.success) {
                    debugLog('SUCCESS', 'Package installed successfully');
                    notyf.success('Package installed successfully');
                    debugLog('UI', 'Reloading installed and available packages tables...');
                    loadInstalledPackages();
                    loadAvailablePackages();
                } else {
                    debugLog('ERROR', 'Installation failed', data);
                    notyf.error(data.error || 'Installation failed');
                }
            })
            .catch(err => {
                debugLog('ERROR', 'Install error', err);
                notyf.error('Failed to install package');
            });
        } else {
            debugLog('ACTION', 'User cancelled install');
        }
    });
}

function uninstallPackage(packageId) {
    debugLog('ACTION', `User clicked UNINSTALL for package: ${packageId}`);
    Swal.fire({
        title: 'Uninstall Package?',
        text: 'This will remove the section and all its data',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, uninstall',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            debugLog('ACTION', `User confirmed uninstall for: ${packageId}`);
            debugLog('API', `Sending DELETE request to /admin/packages/${packageId}/uninstall`);
            fetch(`/admin/packages/${packageId}/uninstall`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => {
                debugLog('RESPONSE', 'Uninstall response received', { status: r.status });
                return r.json();
            })
            .then(data => {
                debugLog('RESPONSE', 'Uninstall data', data);
                if (data.success) {
                    debugLog('SUCCESS', 'Package uninstalled successfully');
                    notyf.success('Package uninstalled');
                    debugLog('UI', 'Reloading installed packages table...');
                    loadInstalledPackages();
                } else {
                    debugLog('ERROR', 'Uninstall failed', data);
                    notyf.error(data.error || 'Uninstall failed');
                }
            })
            .catch(err => {
                debugLog('ERROR', 'Uninstall error', err);
                notyf.error('Failed to uninstall package');
            });
        } else {
            debugLog('ACTION', 'User cancelled uninstall');
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

// Package Discovery
let discoveryPackages = [];
let downloadQueue = new Set();
let currentSort = { field: 'name', direction: 'asc' };
let currentFilter = { categories: [], authors: [], status: 'all', search: '' };

document.getElementById('discoverPackagesBtn')?.addEventListener('click', async function() {
    Swal.fire({
        title: '<i class="bi bi-cloud-download"></i> Package Repository',
        html: `
            <div style="margin-bottom: 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem; color: var(--text-secondary);">
                            <i class="bi bi-search"></i> SEARCH
                        </label>
                        <input type="text" id="pkgSearch" placeholder="Search packages..." style="width: 100%; padding: 0.5rem; border: 2px solid var(--primary-color); border-radius: 6px; outline: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem; color: var(--text-secondary);">
                            <i class="bi bi-folder"></i> CATEGORIES
                        </label>
                        <select id="pkgCategory" multiple size="1" style="width: 100%; padding: 0.5rem; border: 2px solid var(--primary-color); border-radius: 6px; outline: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); background: white;">
                            <option value="" disabled>Select Categories...</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.25rem; color: var(--text-secondary);">
                            <i class="bi bi-person"></i> AUTHORS
                        </label>
                        <select id="pkgAuthor" multiple size="1" style="width: 100%; padding: 0.5rem; border: 2px solid var(--primary-color); border-radius: 6px; outline: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); background: white;">
                            <option value="" disabled>Select Authors...</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem; align-items: center;">
                    <button id="applyFiltersBtn" class="btn btn-sm btn-primary" style="font-size: 0.85rem;">
                        <i class="bi bi-check-circle"></i> Apply Filters
                    </button>
                    <button id="resetFiltersBtn" class="btn btn-sm btn-outline-secondary" style="font-size: 0.85rem;">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                    </button>
                </div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                    <div id="selectedCategories" style="display: flex; gap: 0.5rem; flex-wrap: wrap;"></div>
                    <div id="selectedAuthors" style="display: flex; gap: 0.5rem; flex-wrap: wrap;"></div>
                </div>
                <div style="display: flex; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <select id="pkgStatus" style="padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 4px;">
                        <option value="all">All Status</option>
                        <option value="available">Available</option>
                        <option value="downloaded">Downloaded</option>
                        <option value="installed">Installed</option>
                    </select>
                </div>
                <div id="queueInfo" style="display: none; background: var(--info-bg, #e3f2fd); padding: 0.5rem 0.75rem; border-radius: 4px; margin-bottom: 0.75rem;">
                    <span id="queueCount">0</span> package(s) queued for download
                    <button id="downloadQueueBtn" class="btn btn-sm btn-primary" style="margin-left: 1rem;">
                        <i class="bi bi-download"></i> Download Selected
                    </button>
                    <button id="clearQueueBtn" class="btn btn-sm btn-outline-secondary" style="margin-left: 0.5rem;">
                        Clear Queue
                    </button>
                </div>
            </div>
            <div id="discoveryResults" style="max-height: 55vh; overflow-y: auto;">
                <p class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading packages...</p>
            </div>
        `,
        width: '95%',
        showCloseButton: true,
        showConfirmButton: false,
        didOpen: () => {
            searchRepositoryPackages();
            
            // Attach event listeners
            document.getElementById('pkgSearch').addEventListener('input', (e) => {
                currentFilter.search = e.target.value.toLowerCase();
                renderDiscoveryPackages(discoveryPackages);
            });
            
            // Update filter arrays on change and show badges
            document.getElementById('pkgCategory').addEventListener('change', (e) => {
                const selectedOptions = Array.from(e.target.selectedOptions).map(opt => opt.value);
                currentFilter.categories = selectedOptions;
                updateFilterBadges();
            });
            
            document.getElementById('pkgAuthor').addEventListener('change', (e) => {
                const selectedOptions = Array.from(e.target.selectedOptions).map(opt => opt.value);
                currentFilter.authors = selectedOptions;
                updateFilterBadges();
            });
            
            // Apply filters button
            document.getElementById('applyFiltersBtn').addEventListener('click', () => {
                renderDiscoveryPackages(discoveryPackages);
            });
            
            // Reset filters button
            document.getElementById('resetFiltersBtn').addEventListener('click', () => {
                currentFilter.categories = [];
                currentFilter.authors = [];
                currentFilter.status = 'all';
                currentFilter.search = '';
                document.getElementById('pkgSearch').value = '';
                document.getElementById('pkgCategory').selectedIndex = -1;
                document.getElementById('pkgAuthor').selectedIndex = -1;
                document.getElementById('pkgStatus').value = 'all';
                updateFilterBadges();
                renderDiscoveryPackages(discoveryPackages);
            });
            
            document.getElementById('pkgStatus').addEventListener('change', (e) => {
                currentFilter.status = e.target.value;
                renderDiscoveryPackages(discoveryPackages);
            });
            
            document.getElementById('downloadQueueBtn')?.addEventListener('click', downloadQueuedPackages);
            document.getElementById('clearQueueBtn')?.addEventListener('click', clearDownloadQueue);
        },
        footer: '<a href="https://github.com/R1CH4RD25/TheHub-Package-Repo" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-github"></i> View on GitHub</a>'
    });
});

async function searchRepositoryPackages() {
    const resultsDiv = document.getElementById('discoveryResults');
    resultsDiv.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Searching repository...</p>';
    
    try {
        const response = await fetch('/admin/packages/discovery/search', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                owner: 'R1CH4RD25',
                repo: 'TheHub-Package-Repo'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            discoveryPackages = data.packages;
            
            // Populate category dropdown
            const categories = [...new Set(data.packages.map(p => p.category || 'other'))].sort();
            const catSelect = document.getElementById('pkgCategory');
            categories.forEach(cat => {
                const option = document.createElement('option');
                option.value = cat;
                option.textContent = cat.charAt(0).toUpperCase() + cat.slice(1);
                catSelect.appendChild(option);
            });
            
            // Populate author dropdown
            const authors = [...new Set(data.packages.map(p => p.author))].sort();
            const authorSelect = document.getElementById('pkgAuthor');
            authors.forEach(author => {
                const option = document.createElement('option');
                option.value = author;
                option.textContent = author;
                authorSelect.appendChild(option);
            });
            
            renderDiscoveryPackages(discoveryPackages);
        } else {
            resultsDiv.innerHTML = `<p class="text-center text-danger">Error: ${data.error}</p>`;
        }
    } catch (error) {
        console.error('Discovery error:', error);
        resultsDiv.innerHTML = '<p class="text-center text-danger">Failed to connect to repository</p>';
    }
}

function renderDiscoveryPackages(packages) {
    const resultsDiv = document.getElementById('discoveryResults');
    
    if (packages.length === 0) {
        resultsDiv.innerHTML = '<p class="text-center">No packages found in repository</p>';
        return;
    }
    
    // Filter packages
    let filtered = packages.filter(pkg => {
        // Category filter (multi-select)
        if (currentFilter.categories.length > 0 && !currentFilter.categories.includes(pkg.category || 'other')) {
            return false;
        }
        
        // Author filter (multi-select)
        if (currentFilter.authors.length > 0 && !currentFilter.authors.includes(pkg.author)) {
            return false;
        }
        
        // Status filter
        if (currentFilter.status !== 'all') {
            if (currentFilter.status === 'installed' && !pkg.is_installed) return false;
            if (currentFilter.status === 'downloaded' && !pkg.is_downloaded) return false;
            if (currentFilter.status === 'available' && (pkg.is_installed || pkg.is_downloaded)) return false;
        }
        
        // Search filter
        if (currentFilter.search) {
            const searchLower = currentFilter.search;
            const name = (pkg.display_name || pkg.name || '').toLowerCase();
            const desc = (pkg.description || '').toLowerCase();
            const author = (pkg.author || '').toLowerCase();
            if (!name.includes(searchLower) && !desc.includes(searchLower) && !author.includes(searchLower)) {
                return false;
            }
        }
        
        return true;
    });
    
    // Sort packages
    filtered.sort((a, b) => {
        let aVal = a[currentSort.field] || '';
        let bVal = b[currentSort.field] || '';
        
        if (currentSort.field === 'size') {
            aVal = parseInt(aVal) || 0;
            bVal = parseInt(bVal) || 0;
        } else {
            aVal = String(aVal).toLowerCase();
            bVal = String(bVal).toLowerCase();
        }
        
        if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
        if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
        return 0;
    });
    
    if (filtered.length === 0) {
        resultsDiv.innerHTML = '<p class="text-center text-muted">No packages match your filters</p>';
        return;
    }
    
    // Render table
    // Check if all queueable packages are in the queue
    const queueablePackages = filtered.filter(pkg => !pkg.is_installed && !pkg.is_downloaded);
    const allQueued = queueablePackages.length > 0 && queueablePackages.every(pkg => 
        downloadQueue.has(JSON.stringify({ downloadUrl: pkg.download_url, filename: pkg.filename }))
    );
    
    const html = `
        <table class="data-table" style="width: 100%;">
            <thead>
                <tr>
                    <th style="width: 40px;"><input type="checkbox" id="selectAllPkgs" ${allQueued ? 'checked' : ''} onchange="toggleSelectAll(this.checked)" style="width: 18px; height: 18px;"></th>
                    <th style="cursor: pointer;" onclick="sortPackages('name')">
                        Package Name ${currentSort.field === 'name' ? (currentSort.direction === 'asc' ? '▲' : '▼') : ''}
                    </th>
                    <th style="cursor: pointer;" onclick="sortPackages('category')">
                        Category ${currentSort.field === 'category' ? (currentSort.direction === 'asc' ? '▲' : '▼') : ''}
                    </th>
                    <th style="cursor: pointer;" onclick="sortPackages('version')">
                        Version ${currentSort.field === 'version' ? (currentSort.direction === 'asc' ? '▲' : '▼') : ''}
                    </th>
                    <th style="cursor: pointer;" onclick="sortPackages('author')">
                        Author ${currentSort.field === 'author' ? (currentSort.direction === 'asc' ? '▲' : '▼') : ''}
                    </th>
                    <th style="cursor: pointer;" onclick="sortPackages('size')">
                        Size ${currentSort.field === 'size' ? (currentSort.direction === 'asc' ? '▲' : '▼') : ''}
                    </th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                ${filtered.map((pkg, idx) => {
                    const isQueueable = !pkg.is_installed && !pkg.is_downloaded;
                    const inQueue = downloadQueue.has(JSON.stringify({ downloadUrl: pkg.download_url, filename: pkg.filename }));
                    return `
                    <tr>
                        <td onclick="event.stopPropagation();">
                            ${isQueueable ? 
                                `<input type="checkbox" ${inQueue ? 'checked' : ''} onchange="toggleQueue('${pkg.download_url}', '${pkg.filename}', this.checked)" style="width: 18px; height: 18px;">` :
                                '<span style="color: var(--text-muted);">—</span>'
                            }
                        </td>
                        <td style="cursor: pointer;" onclick="showPackageDetails(${idx}, ${JSON.stringify(pkg).replace(/"/g, '&quot;')})"><strong>${pkg.display_name || pkg.name}</strong></td>
                        <td style="cursor: pointer;" onclick="showPackageDetails(${idx}, ${JSON.stringify(pkg).replace(/"/g, '&quot;')})"><span class="badge" style="background: var(--primary-color); color: rgba(255, 255, 255, 0.95);">${pkg.category || 'other'}</span></td>
                        <td style="cursor: pointer;" onclick="showPackageDetails(${idx}, ${JSON.stringify(pkg).replace(/"/g, '&quot;')})"><span>${pkg.version}</span></td>
                        <td style="cursor: pointer;" onclick="showPackageDetails(${idx}, ${JSON.stringify(pkg).replace(/"/g, '&quot;')})"><span>${pkg.author}</span></td>
                        <td style="cursor: pointer;" onclick="showPackageDetails(${idx}, ${JSON.stringify(pkg).replace(/"/g, '&quot;')})"><span>${(pkg.size / 1024).toFixed(1)} KB</span></td>
                        <td style="cursor: pointer;" onclick="showPackageDetails(${idx}, ${JSON.stringify(pkg).replace(/"/g, '&quot;')})">
                            ${pkg.is_installed ? 
                                '<span class="badge" style="background: var(--success-color);"><i class="bi bi-check-circle"></i> Installed</span>' :
                                pkg.is_downloaded ?
                                    '<span class="badge" style="background: var(--info-color);">Downloaded</span>' :
                                    '<span class="badge" style="background: var(--text-muted);">Available</span>'
                            }
                        </td>
                    </tr>
                `}).join('')}
            </tbody>
        </table>
    `;
    
    resultsDiv.innerHTML = html;
}

function sortPackages(field) {
    if (currentSort.field === field) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort.field = field;
        currentSort.direction = 'asc';
    }
    renderDiscoveryPackages(discoveryPackages);
}

function showPackageDetails(index, pkg) {
    const inQueue = downloadQueue.has(JSON.stringify({ downloadUrl: pkg.download_url, filename: pkg.filename }));
    
    // Create custom modal overlay to avoid closing browse modal
    const modalOverlay = document.createElement('div');
    modalOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: var(--surface-color, white);
        border-radius: 8px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    `;
    
    modalContent.innerHTML = `
        <div style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.25rem;">${pkg.display_name || pkg.name}</h3>
                <button onclick="this.closest('[style*=\\'z-index: 10000\\']').remove()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div style="text-align: left;">
                <p style="color: var(--text-secondary); margin-bottom: 1rem;">${pkg.description}</p>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; margin-bottom: 1.5rem;">
                    <div>
                        <strong>Version:</strong> ${pkg.version}
                    </div>
                    <div>
                        <strong>Author:</strong> ${pkg.author}
                    </div>
                    <div>
                        <strong>Category:</strong> ${pkg.category || 'other'}
                    </div>
                    <div>
                        <strong>Size:</strong> ${(pkg.size / 1024).toFixed(1)} KB
                    </div>
                </div>
                
                ${pkg.is_installed ? 
                    '<div class="alert alert-success"><i class="bi bi-check-circle"></i> This package is already installed</div>' :
                    pkg.is_downloaded ?
                        '<div class="alert alert-info"><i class="bi bi-info-circle"></i> This package is downloaded and ready to install</div>' :
                        `<div style="background: var(--surface-color); border: 1px solid var(--border-color); border-radius: 4px; padding: 1rem; text-align: center; margin-bottom: 1rem;">
                            <label style="display: inline-flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" id="queueCheckbox_${index}" ${inQueue ? 'checked' : ''} 
                                       onchange="toggleQueueFromDetail('${pkg.download_url}', '${pkg.filename}', this.checked)"
                                       style="margin-right: 0.5rem; width: 18px; height: 18px;">
                                <span>Add to download queue</span>
                            </label>
                        </div>`
                }
                
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;">
                    ${!pkg.is_installed && !pkg.is_downloaded ? 
                        `<button onclick="downloadSinglePackageFromModal('${pkg.download_url}', '${pkg.filename}', this)" class="btn btn-primary" style="flex: 1 1 auto; min-width: 140px;">
                            <i class="bi bi-download"></i> Download Now
                        </button>` : ''
                    }
                    <button onclick="this.closest('[style*=\\'z-index: 10000\\']').remove()" class="btn btn-outline-secondary" style="flex: 1 1 auto; min-width: 100px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    
    modalOverlay.appendChild(modalContent);
    
    // Close modal when clicking overlay (but not content)
    modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) {
            modalOverlay.remove();
        }
    });
    
    document.body.appendChild(modalOverlay);
}

async function downloadSinglePackageFromModal(downloadUrl, filename, button) {
    debugLog('ACTION', `🚀 MODAL DOWNLOAD: ${filename}`);
    debugLog('UI', 'Disabling button and showing spinner');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
    
    try {
        debugLog('API', `📥 Calling downloadSinglePackage for: ${filename}`);
        // Download without notifications/refreshes
        await downloadSinglePackage(downloadUrl, filename, false);
        debugLog('SUCCESS', `✅ Download complete for: ${filename}`);
        
        debugLog('UI', '🎯 Step 1: Finding modal element');
        const modal = button.closest('[style*="z-index: 10000"]');
        if (modal) {
            debugLog('UI', '🗑️ Step 2: Removing modal from DOM');
            modal.remove();
            debugLog('UI', '✅ Step 3: Modal removed successfully');
        } else {
            debugLog('ERROR', '❌ Modal element not found in DOM!');
        }
        
        debugLog('UI', '🔔 Step 4: Showing success notification');
        notyf.success('Package downloaded successfully!');
        
        debugLog('API', '🔄 Step 5: Refreshing available packages table');
        await loadAvailablePackages();
        debugLog('SUCCESS', '✅ Step 6: Available packages refreshed');
        
        debugLog('API', '🔄 Step 7: Refreshing repository search results');
        await searchRepositoryPackages();
        debugLog('SUCCESS', '✨ COMPLETE: All steps finished, modal should be gone');
        
    } catch (error) {
        debugLog('ERROR', `❌ DOWNLOAD FAILED for: ${filename}`, error);
        notyf.error('Download failed: ' + (error.message || 'Unknown error'));
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-download"></i> Download Now';
    }
}

function updateFilterBadges() {
    const catBadges = document.getElementById('selectedCategories');
    const authBadges = document.getElementById('selectedAuthors');
    
    catBadges.innerHTML = currentFilter.categories.map(cat => 
        `<span class="badge" style="background: var(--primary-color); color: rgba(255, 255, 255, 0.95); cursor: pointer;" onclick="removeFilter('category', '${cat}')">
            ${cat} <i class="bi bi-x"></i>
        </span>`
    ).join('');
    
    authBadges.innerHTML = currentFilter.authors.map(author => 
        `<span class="badge" style="background: var(--info-color); cursor: pointer;" onclick="removeFilter('author', '${author}')">
            ${author} <i class="bi bi-x"></i>
        </span>`
    ).join('');
}

function removeFilter(type, value) {
    if (type === 'category') {
        currentFilter.categories = currentFilter.categories.filter(c => c !== value);
        const select = document.getElementById('pkgCategory');
        Array.from(select.options).forEach(opt => {
            if (opt.value === value) opt.selected = false;
        });
    } else if (type === 'author') {
        currentFilter.authors = currentFilter.authors.filter(a => a !== value);
        const select = document.getElementById('pkgAuthor');
        Array.from(select.options).forEach(opt => {
            if (opt.value === value) opt.selected = false;
        });
    }
    updateFilterBadges();
    renderDiscoveryPackages(discoveryPackages);
}

function toggleSelectAll(checked) {
    const filtered = discoveryPackages.filter(pkg => {
        if (currentFilter.categories.length > 0 && !currentFilter.categories.includes(pkg.category || 'other')) return false;
        if (currentFilter.authors.length > 0 && !currentFilter.authors.includes(pkg.author)) return false;
        if (currentFilter.status !== 'all') {
            if (currentFilter.status === 'installed' && !pkg.is_installed) return false;
            if (currentFilter.status === 'downloaded' && !pkg.is_downloaded) return false;
            if (currentFilter.status === 'available' && (pkg.is_installed || pkg.is_downloaded)) return false;
        }
        if (currentFilter.search) {
            const searchLower = currentFilter.search;
            const name = (pkg.display_name || pkg.name || '').toLowerCase();
            const desc = (pkg.description || '').toLowerCase();
            const author = (pkg.author || '').toLowerCase();
            if (!name.includes(searchLower) && !desc.includes(searchLower) && !author.includes(searchLower)) return false;
        }
        return !pkg.is_installed && !pkg.is_downloaded;
    });
    
    if (checked) {
        filtered.forEach(pkg => {
            downloadQueue.add(JSON.stringify({ downloadUrl: pkg.download_url, filename: pkg.filename }));
        });
    } else {
        downloadQueue.clear();
    }
    
    updateQueueDisplay();
    renderDiscoveryPackages(discoveryPackages);
}

function toggleQueue(downloadUrl, filename, add) {
    if (add) {
        downloadQueue.add(JSON.stringify({ downloadUrl, filename }));
    } else {
        downloadQueue.delete(JSON.stringify({ downloadUrl, filename }));
    }
    updateQueueDisplay();
}

function toggleQueueFromDetail(downloadUrl, filename, add) {
    toggleQueue(downloadUrl, filename, add);
    // Refresh the table to show updated checkbox states
    renderDiscoveryPackages(discoveryPackages);
}

function updateQueueDisplay() {
    const queueInfo = document.getElementById('queueInfo');
    const queueCount = document.getElementById('queueCount');
    
    if (downloadQueue.size > 0) {
        queueInfo.style.display = 'block';
        queueCount.textContent = downloadQueue.size;
    } else {
        queueInfo.style.display = 'none';
    }
}

function clearDownloadQueue() {
    downloadQueue.clear();
    updateQueueDisplay();
    notyf.success('Download queue cleared');
}

async function downloadQueuedPackages() {
    if (downloadQueue.size === 0) {
        notyf.error('No packages in queue');
        return;
    }
    
    const packages = Array.from(downloadQueue).map(item => JSON.parse(item));
    debugLog('ACTION', `🚀 QUEUE DOWNLOAD: Starting batch of ${packages.length} packages`);
    let successful = 0;
    let failed = 0;
    
    notyf.success(`Downloading ${packages.length} package(s)...`);
    
    for (const pkg of packages) {
        try {
            debugLog('API', `📥 Downloading: ${pkg.filename}`);
            await downloadSinglePackage(pkg.downloadUrl, pkg.filename, false);
            successful++;
            debugLog('SUCCESS', `✅ Downloaded: ${pkg.filename}`);
        } catch (error) {
            failed++;
            debugLog('ERROR', `❌ Failed: ${pkg.filename}`, error);
        }
    }
    
    debugLog('UI', `📊 Queue complete: ${successful} success, ${failed} failed`);
    
    // Close ALL modals BEFORE refreshing (this prevents flash)
    debugLog('UI', '🗑️ Closing any open modals');
    const modals = document.querySelectorAll('[style*="z-index: 10000"]');
    modals.forEach(modal => {
        debugLog('UI', `🗑️ Removing modal from DOM`);
        modal.remove();
    });
    debugLog('UI', `✅ ${modals.length} modal(s) closed`);
    
    if (successful > 0) {
        debugLog('UI', '🔔 Showing success notification');
        notyf.success(`Downloaded ${successful} package(s) successfully`);
        
        debugLog('UI', '🧹 Clearing queue');
        downloadQueue.clear();
        updateQueueDisplay();
        
        debugLog('API', '🔄 Refreshing available packages table');
        await loadAvailablePackages();
        debugLog('SUCCESS', '✅ Available packages refreshed');
        
        debugLog('API', '🔄 Refreshing repository search results');
        await searchRepositoryPackages();
        debugLog('SUCCESS', '✨ QUEUE COMPLETE: All tables refreshed, modals closed');
    }
    
    if (failed > 0) {
        notyf.error(`${failed} package(s) failed to download`);
    }
}

async function downloadSinglePackage(downloadUrl, packageName, showNotification = true) {
    try {
        debugLog('API', `Sending download request to server for: ${packageName}`);
        const response = await fetch('/admin/packages/discovery/download', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                download_url: downloadUrl,
                package_name: packageName
            })
        });
        
        debugLog('RESPONSE', `Download response received for: ${packageName}`, { status: response.status });
        const data = await response.json();
        debugLog('RESPONSE', `Download data for: ${packageName}`, data);
        
        if (data.success) {
            if (showNotification) {
                debugLog('SUCCESS', `Package downloaded: ${packageName}`);
                notyf.success('Package downloaded successfully!');
                loadAvailablePackages();
                await searchRepositoryPackages();
            }
            return true;
        } else {
            debugLog('ERROR', `Download failed: ${packageName}`, data.error);
            if (showNotification) {
                notyf.error('Download failed: ' + data.error);
            }
            throw new Error(data.error);
        }
    } catch (error) {
        debugLog('ERROR', `Download exception for: ${packageName}`, error);
        console.error('Download error:', error);
        if (showNotification) {
            notyf.error('Failed to download package');
        }
        throw error;
    }
}

// MODAL FLASH DETECTIVE - Track when modals are created/destroyed
const modalObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1 && node.style && node.style.zIndex === '10000') {
                debugLog('UI', '🚨 MODAL CREATED IN DOM', { 
                    id: node.id, 
                    className: node.className,
                    preview: node.innerHTML.substring(0, 150) + '...'
                });
                console.trace('📍 Modal creation stack trace');
            }
        });
        mutation.removedNodes.forEach((node) => {
            if (node.nodeType === 1 && node.style && node.style.zIndex === '10000') {
                debugLog('UI', '🗑️ MODAL REMOVED FROM DOM', { 
                    id: node.id, 
                    className: node.className 
                });
            }
        });
    });
});
modalObserver.observe(document.body, { childList: true, subtree: false });
debugLog('UI', '👁️ Modal detective activated - watching for all modal creation/removal');
</script>
@endpush

<style>
.category-section {
    margin-bottom: 2rem;
}

.category-title {
    font-size: 1.3rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--border-color, #dee2e6);
}

.packages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
}

.package-card {
    background: var(--surface-color, white);
    border: 1px solid var(--border-color, #dee2e6);
    border-radius: 8px;
    padding: 1.25rem;
    transition: all 0.2s;
}

.package-card:hover {
    box-shadow: 0 4px 12px var(--shadow-color, rgba(0,0,0,0.1));
    transform: translateY(-2px);
}

.package-card.installed {
    background: var(--success-bg, #e8f5e9);
    border-color: var(--success-color, #4caf50);
}

.package-card.downloaded {
    background: var(--info-bg, #e3f2fd);
    border-color: var(--info-color, #2196f3);
}

.package-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 0.75rem;
}

.package-header h4 {
    margin: 0;
    font-size: 1.1rem;
}

.package-version {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.85rem;
}

.package-description {
    color: var(--text-secondary, #666);
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}

.package-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: var(--text-muted, #888);
    margin-bottom: 1rem;
}

.package-actions {
    display: flex;
    justify-content: flex-end;
}

/* Responsive modal buttons */
@media (max-width: 576px) {
    [style*="z-index: 10000"] [style*="justify-content: flex-end"] {
        justify-content: center !important;
    }
    
    [style*="z-index: 10000"] .btn {
        flex: 1 1 100% !important;
        min-width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

@endsection
