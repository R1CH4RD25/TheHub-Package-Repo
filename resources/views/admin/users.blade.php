@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
<div class="admin-tab active">
    <div class="tab-header">
        <div>
            <h1><i class="fas fa-users"></i> User Management</h1>
            <p class="text-muted">Manage users, invitations, and roles</p>
        </div>
        <button id="sendInvitation" class="btn btn-primary">
            <i class="fas fa-plus"></i> Send Invitation
        </button>
    </div>

    <div class="tab-content-scroll">
        <!-- User Sub-tabs -->
        <div class="user-subtabs">
            <button class="subtab-btn active" data-subtab="active-users">Active Users</button>
            <button class="subtab-btn" data-subtab="pending-users">Pending Approvals</button>
            <button class="subtab-btn" data-subtab="invitations">Invitations</button>
            @if($isSuperAdmin)
                <button class="subtab-btn" data-subtab="role-management">Role Management</button>
            @endif
        </div>

        <!-- Active Users Subtab -->
        <div id="subtab-active-users" class="user-subtab active">
            <div class="users-layout">
                <!-- Left Sidebar - Role Filter Panel -->
                <div class="role-filter-panel">
                    <div class="panel-header">
                        <h3>Filter by Role</h3>
                        <button class="collapse-btn" title="Collapse">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                    </div>

                    <div class="panel-content">
                        <!-- Role Selection Mode -->
                        <div class="role-selection-mode">
                            <label class="radio-option">
                                <input type="radio" name="roleMode" value="all" checked>
                                <span>Users from all roles</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="roleMode" value="selected">
                                <span>Users from selected roles</span>
                            </label>
                        </div>

                        <!-- Role Search -->
                        <div class="role-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="roleSearch" placeholder="Search roles">
                        </div>

                        <!-- Role Tree -->
                        <div class="role-tree">
                            <div class="role-category">
                                <!-- Administration -->
                                <div class="role-group">
                                    <div class="role-item parent" data-role="administration">
                                        <i class="fas fa-chevron-right toggle-icon"></i>
                                        <span class="role-name">Administration</span>
                                    </div>
                                    <div class="role-children" style="display: none;">
                                        <div class="role-item child" data-role="super_admin">
                                            <span class="role-name">Super Admin</span>
                                        </div>
                                        <div class="role-item child" data-role="admin">
                                            <span class="role-name">Admin</span>
                                        </div>
                                        <div class="role-item child" data-role="principal">
                                            <span class="role-name">Principal</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Maintenance -->
                                <div class="role-group">
                                    <div class="role-item parent" data-role="maintenance-dept">
                                        <i class="fas fa-chevron-right toggle-icon"></i>
                                        <span class="role-name">Maintenance</span>
                                    </div>
                                    <div class="role-children" style="display: none;">
                                        <div class="role-item child" data-role="maintenance_director">
                                            <span class="role-name">Maintenance Director</span>
                                        </div>
                                        <div class="role-item child" data-role="maintenance">
                                            <span class="role-name">Maintenance Staff</span>
                                        </div>
                                        <div class="role-item child" data-role="custodial">
                                            <span class="role-name">Custodial</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Support Staff -->
                                <div class="role-group">
                                    <div class="role-item parent" data-role="support">
                                        <i class="fas fa-chevron-right toggle-icon"></i>
                                        <span class="role-name">Support Staff</span>
                                    </div>
                                    <div class="role-children" style="display: none;">
                                        <div class="role-item child" data-role="counselor">
                                            <span class="role-name">Counselor</span>
                                        </div>
                                        <div class="role-item child" data-role="substitute_manager">
                                            <span class="role-name">Substitute Manager</span>
                                        </div>
                                        <div class="role-item child" data-role="cafeteria">
                                            <span class="role-name">Cafeteria</span>
                                        </div>
                                        <div class="role-item child" data-role="staff">
                                            <span class="role-name">General Staff</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Manage Roles Link -->
                        <div class="manage-roles-link">
                            <a href="#" class="manage-link">
                                <i class="fas fa-cog"></i> MANAGE ROLE HIERARCHY
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Content Area -->
                <div class="users-content">
                    <!-- Expand Button (shown when panel collapsed) -->
                    <button id="expandRolesBtn" class="expand-roles-btn" style="display: none;" title="Show roles">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    
                    <!-- Top Bar with Actions -->
                    <div class="content-header">
                        <div class="header-info">
                            <span class="header-title">Users</span>
                            <span class="header-subtitle">| Showing users from <span id="roleContext">all roles</span></span>
                        </div>
                        <div class="header-actions">
                            <button class="action-btn">Add new user</button>
                            <button class="action-btn">Bulk update users</button>
                            <button class="action-btn">Download users</button>
                            <button class="action-btn dropdown-toggle">
                                More options <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>

            <!-- Search and Filters -->
            <div class="table-controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="userSearch" placeholder="Search for users" class="search-input">
                    <button class="clear-search" id="clearSearch" style="display: none;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="status-filter">
                    <label>Status:</label>
                    <select id="statusFilter" class="filter-select">
                        <option value="all">All statuses</option>
                        <option value="active" selected>Active</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>
                        <!-- Users Table Container -->
            <div id="usersTable"></div>
            
            <!-- Sliding Action Panel -->
            <div id="actionPanel" class="action-panel">
                <div class="action-panel-header">
                    <span id="selectedCount">0 selected</span>
                    <button id="closePanel" class="close-panel-btn">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="action-panel-content">
                    <button class="panel-action-btn" id="bulkChangeRole">
                        <i class="fas fa-user-tag"></i>
                        <span>Change role</span>
                    </button>
                    <button class="panel-action-btn" id="bulkSuspend">
                        <i class="fas fa-ban"></i>
                        <span>Suspend</span>
                    </button>
                    <button class="panel-action-btn" id="bulkActivate">
                        <i class="fas fa-check-circle"></i>
                        <span>Activate</span>
                    </button>
                    <button class="panel-action-btn" id="bulkResetPassword">
                        <i class="fas fa-key"></i>
                        <span>Reset password</span>
                    </button>
                    <button class="panel-action-btn danger" id="bulkDelete">
                        <i class="fas fa-trash"></i>
                        <span>Delete</span>
                    </button>
                </div>
            </div>
                </div> <!-- .users-content -->
            </div> <!-- .users-layout -->
        </div> <!-- #subtab-active-users -->

        <!-- Pending Users Subtab -->
        <div id="subtab-pending-users" class="user-subtab"
            <div id="pendingTable" class="data-table-container">
                <p class="text-center">Loading pending approvals...</p>
            </div>
        </div>

        <!-- Invitations Subtab -->
        <div id="subtab-invitations" class="user-subtab">
            <div id="invitationsTable" class="data-table-container">
                <p class="text-center">Loading invitations...</p>
            </div>
        </div>

        <!-- Role Management Subtab (Super Admin Only) -->
        @if($isSuperAdmin)
            <div id="subtab-role-management" class="user-subtab">
                <div class="role-management">
                    <p class="info-text">
                        <strong>🎭 Role Management:</strong> Enable or disable roles system-wide.
                        Inactive roles are hidden from all dropdowns, section access, and user assignment forms.
                        <span style="color: #d32f2f; font-weight: 600;">⚠️ Cannot disable Super Admin or Staff roles (core system roles).</span>
                    </p>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 100px;">Status</th>
                                    <th style="min-width: 200px;">Role</th>
                                    <th style="min-width: 250px;">Description</th>
                                    <th style="width: 100px; text-align: center;">Hierarchy</th>
                                    <th style="width: 120px; text-align: center;">Active Users</th>
                                    <th style="min-width: 200px;">Notes</th>
                                    <th style="width: 120px; text-align: center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rolesManagementTableBody">
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px;">
                                        Loading roles...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
// CSRF token from Laravel
const csrfToken = '{{ csrf_token() }}';

// Role tree functionality
document.addEventListener('DOMContentLoaded', function() {
    // Collapse panel functionality
    const collapseBtn = document.querySelector('.collapse-btn');
    const panel = document.querySelector('.role-filter-panel');
    const usersLayout = document.querySelector('.users-layout');
    const expandBtn = document.getElementById('expandRolesBtn');
    
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            panel.classList.toggle('collapsed');
            const icon = this.querySelector('i');
            
            if (panel.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-right';
                usersLayout.style.gridTemplateColumns = '0 1fr';
                if (expandBtn) expandBtn.style.display = 'flex';
            } else {
                icon.className = 'fas fa-chevron-left';
                usersLayout.style.gridTemplateColumns = '280px 1fr';
                if (expandBtn) expandBtn.style.display = 'none';
            }
        });
    }
    
    // Expand button functionality
    if (expandBtn) {
        expandBtn.addEventListener('click', function() {
            panel.classList.remove('collapsed');
            const icon = collapseBtn?.querySelector('i');
            if (icon) icon.className = 'fas fa-chevron-left';
            usersLayout.style.gridTemplateColumns = '280px 1fr';
            expandBtn.style.display = 'none';
        });
    }

    // Toggle role groups
    document.querySelectorAll('.role-item.parent').forEach(item => {
        item.addEventListener('click', function(e) {
            e.stopPropagation();
            const children = this.nextElementSibling;
            const isExpanded = children.style.display !== 'none';
            
            children.style.display = isExpanded ? 'none' : 'block';
            this.classList.toggle('expanded', !isExpanded);
        });
    });
    
    // Handle role selection mode (all vs selected)
    const roleSearch = document.querySelector('.role-search');
    const roleTree = document.querySelector('.role-tree');
    const roleModeRadios = document.querySelectorAll('input[name="roleMode"]');
    
    roleModeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'all') {
                // Mute the search and tree when "all roles" is selected
                if (roleSearch) roleSearch.classList.add('muted');
                if (roleTree) roleTree.classList.add('muted');
                // Show all users
                renderUsersTable(allUsers, 'usersTable');
            } else {
                // Unmute the search and tree when "selected roles" is chosen
                if (roleSearch) roleSearch.classList.remove('muted');
                if (roleTree) roleTree.classList.remove('muted');
            }
        });
    });
    
    // Initialize as muted since "all" is checked by default
    if (roleSearch) {
        roleSearch.classList.add('muted');
        // Clicking the search when muted switches to "selected roles" mode
        roleSearch.addEventListener('click', function() {
            if (roleSearch.classList.contains('muted')) {
                const selectedRolesRadio = document.querySelector('input[name="roleMode"][value="selected"]');
                if (selectedRolesRadio) {
                    selectedRolesRadio.checked = true;
                    selectedRolesRadio.dispatchEvent(new Event('change'));
                }
            }
        });
    }
    if (roleTree) {
        roleTree.classList.add('muted');
        // Clicking the tree when muted switches to "selected roles" mode
        roleTree.addEventListener('click', function() {
            if (roleTree.classList.contains('muted')) {
                const selectedRolesRadio = document.querySelector('input[name="roleMode"][value="selected"]');
                if (selectedRolesRadio) {
                    selectedRolesRadio.checked = true;
                    selectedRolesRadio.dispatchEvent(new Event('change'));
                }
            }
        });
    }

    // Handle role selection
    document.querySelectorAll('.role-item').forEach(item => {
        item.addEventListener('click', function() {
            // Remove active from all items
            document.querySelectorAll('.role-item').forEach(i => i.classList.remove('active'));
            // Add active to clicked item
            this.classList.add('active');
            
            // Get the role value
            const role = this.getAttribute('data-role');
            const roleName = this.querySelector('.role-name').textContent;
            
            // Update the header subtitle
            document.getElementById('roleContext').textContent = roleName.toLowerCase();
            
            // Filter users by role
            filterUsersByRole(role);
        });
    });

    // Role search functionality
    document.getElementById('roleSearch')?.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.role-group').forEach(group => {
            const roleName = group.querySelector('.parent .role-name').textContent.toLowerCase();
            const hasMatch = roleName.includes(searchTerm);
            group.style.display = hasMatch ? 'block' : 'none';
            
            // Expand if search matches
            if (hasMatch && searchTerm) {
                group.querySelector('.role-children').style.display = 'block';
                group.querySelector('.parent').classList.add('expanded');
            }
        });
    });
});

function filterUsersByRole(role) {
    if (role === 'all' || role === 'administration' || role === 'maintenance-dept' || role === 'support') {
        // Show all users for parent categories
        filteredUsers = allUsers;
    } else {
        // Filter by specific role
        filteredUsers = allUsers.filter(user => user.role === role);
    }
    renderUsersTable(filteredUsers, 'usersTable');
}

// Subtab switching
document.querySelectorAll('.subtab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const subtab = this.getAttribute('data-subtab');

        // Update buttons
        document.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        // Update subtabs
        document.querySelectorAll('.user-subtab').forEach(s => s.classList.remove('active'));
        document.getElementById(`subtab-${subtab}`).classList.add('active');

        // Load data for the active subtab
        loadSubtabData(subtab);
    });
});

// Load subtab data
function loadSubtabData(subtab) {
    switch(subtab) {
        case 'active-users':
            loadActiveUsers();
            break;
        case 'pending-users':
            loadPendingUsers();
            break;
        case 'invitations':
            loadInvitations();
            break;
        case 'role-management':
            loadRoleManagement();
            break;
    }
}

// Load active users
function loadActiveUsers() {
    console.log('Loading active users...');
    fetch('/admin/users/list')
        .then(r => {
            console.log('Response received:', r.status);
            return r.json();
        })
        .then(users => {
            console.log('Users loaded:', users.length);
            allUsers = users;
            filteredUsers = users;
            renderUsersTable(users, 'usersTable');
            setupSearchAndFilter();
        })
        .catch(err => {
            notyf.error('Failed to load users');
            console.error('Error loading users:', err);
        });
}

// Load pending users
function loadPendingUsers() {
    fetch('/admin/users/list?pending=true')
        .then(r => r.json())
        .then(users => {
            renderPendingTable(users);
        })
        .catch(err => {
            notyf.error('Failed to load pending users');
            console.error(err);
        });
}

// Load invitations
function loadInvitations() {
    fetch('/admin/invitations')
        .then(r => r.json())
        .then(invites => {
            renderInvitationsTable(invites);
        })
        .catch(err => {
            notyf.error('Failed to load invitations');
            console.error(err);
        });
}

// Render users table
// Helper function to get initials
function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
}

// Helper function to generate avatar color based on name
function getAvatarColor(name) {
    const colors = [
        '#1e88e5', '#43a047', '#e53935', '#fb8c00', 
        '#8e24aa', '#00acc1', '#7cb342', '#f4511e'
    ];
    const index = name.charCodeAt(0) % colors.length;
    return colors[index];
}

function renderUsersTable(users, containerId) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th class="checkbox-col">
                        <input type="checkbox" id="selectAll" class="user-checkbox">
                    </th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Last Login</th>
                </tr>
            </thead>
            <tbody>
                ${users.map(u => {
                    const initials = getInitials(u.name);
                    const avatarColor = getAvatarColor(u.name);
                    const isSuspended = !u.is_active;
                    const statusText = isSuspended ? 'Suspended' : 'Active';
                    const statusClass = isSuspended ? 'status-suspended' : 'status-active';
                    
                    // Show Google profile picture ONLY if active, otherwise always show initials with strikethrough
                    const avatarHtml = (!isSuspended && u.picture)
                        ? `<img src="${u.picture}" alt="${u.name}" class="user-avatar-img">`
                        : `<div class="user-avatar ${isSuspended ? 'suspended' : ''}" style="background-color: ${avatarColor}">
                            ${initials}
                           </div>`;
                    
                    return `
                    <tr data-user-id="${u.id}" class="${isSuspended ? 'user-suspended' : ''}">
                        <td class="checkbox-col">
                            <input type="checkbox" class="user-checkbox" data-user-id="${u.id}">
                        </td>
                        <td>
                            <div class="user-cell">
                                ${avatarHtml}
                                <div class="user-info">
                                    <div class="user-name">${u.name}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge ${statusClass}">
                                <i class="fas fa-circle"></i> ${statusText}
                            </span>
                        </td>
                        <td>${u.email}</td>
                        <td><span class="badge badge-${u.role}">${u.role}</span></td>
                        <td>${u.last_login ? new Date(u.last_login).toLocaleDateString() : 'Never'}</td>
                    </tr>
                `}).join('')}
            </tbody>
        </table>
    `;
    document.getElementById(containerId).innerHTML = html;
    
    // Setup checkbox handlers
    setupCheckboxHandlers();
}

// Setup checkbox handlers for selection
function setupCheckboxHandlers() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.user-checkbox:not(#selectAll)');
    const actionPanel = document.getElementById('actionPanel');
    const selectedCount = document.getElementById('selectedCount');
    const closePanel = document.getElementById('closePanel');
    
    function updateSelectionUI() {
        const checked = document.querySelectorAll('.user-checkbox:not(#selectAll):checked');
        const count = checked.length;
        
        if (count > 0) {
            actionPanel.classList.add('active');
            selectedCount.textContent = `${count} selected`;
            
            // Determine if selected users are active or suspended
            const selectedUsers = Array.from(checked).map(cb => {
                const userId = cb.dataset.userId;
                return allUsers.find(u => u.id == userId);
            });
            
            const hasActive = selectedUsers.some(u => u && u.is_active);
            const hasSuspended = selectedUsers.some(u => u && !u.is_active);
            
            // Show/hide suspend/activate buttons based on selection
            const suspendBtn = document.getElementById('bulkSuspend');
            const activateBtn = document.getElementById('bulkActivate');
            
            if (suspendBtn) {
                suspendBtn.style.display = hasActive ? 'flex' : 'none';
            }
            if (activateBtn) {
                activateBtn.style.display = hasSuspended ? 'flex' : 'none';
            }
        } else {
            actionPanel.classList.remove('active');
        }
        
        // Update select all checkbox state
        if (selectAll) {
            selectAll.checked = count > 0 && count === checkboxes.length;
            selectAll.indeterminate = count > 0 && count < checkboxes.length;
        }
    }
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateSelectionUI();
        });
    }
    
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectionUI);
    });
    
    if (closePanel) {
        closePanel.addEventListener('click', function() {
            checkboxes.forEach(cb => cb.checked = false);
            if (selectAll) selectAll.checked = false;
            actionPanel.classList.remove('active');
        });
    }
    
    // Setup action buttons
    document.getElementById('bulkChangeRole')?.addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.user-checkbox:not(#selectAll):checked'))
            .map(cb => cb.dataset.userId);
        if (selected.length > 0) {
            bulkChangeRole(selected);
        }
    });
    
    document.getElementById('bulkSuspend')?.addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.user-checkbox:not(#selectAll):checked'))
            .map(cb => cb.dataset.userId);
        if (selected.length > 0) {
            bulkSuspendUsers(selected);
        }
    });
    
    document.getElementById('bulkActivate')?.addEventListener('click', function() {
        const selected = Array.from(document.querySelectorAll('.user-checkbox:not(#selectAll):checked'))
            .map(cb => cb.dataset.userId);
        if (selected.length > 0) {
            bulkActivateUsers(selected);
        }
    });
}

// Bulk action functions
function bulkChangeRole(userIds) {
    Swal.fire({
        title: `Change Role for ${userIds.length} User(s)`,
        input: 'select',
        inputOptions: {
            'staff': 'Staff',
            'maintenance': 'Maintenance',
            'custodial': 'Custodial',
            'counselor': 'Counselor',
            'admin': 'Admin',
            'principal': 'Principal',
            'super_admin': 'Super Admin'
        },
        showCancelButton: true,
        confirmButtonText: 'Update Roles'
    }).then((result) => {
        if (result.isConfirmed) {
            // TODO: Implement bulk role change API
            notyf.success(`Updated ${userIds.length} user(s)`);
            document.getElementById('actionPanel').classList.remove('active');
            loadActiveUsers();
        }
    });
}

function bulkSuspendUsers(userIds) {
    Swal.fire({
        title: 'Suspend Users?',
        text: `Are you sure you want to suspend ${userIds.length} user(s)?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Yes, suspend them'
    }).then((result) => {
        if (result.isConfirmed) {
            // Call API for each user
            const promises = userIds.map(userId => 
                fetch(`/admin/users/${userId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ action: 'deactivate' })
                }).then(r => r.json())
            );
            
            Promise.all(promises)
                .then(() => {
                    notyf.success(`Suspended ${userIds.length} user(s)`);
                    document.getElementById('actionPanel').classList.remove('active');
                    loadActiveUsers();
                })
                .catch(err => {
                    notyf.error('Failed to suspend users');
                    console.error(err);
                });
        }
    });
}

function bulkActivateUsers(userIds) {
    Swal.fire({
        title: 'Activate Users?',
        text: `Are you sure you want to activate ${userIds.length} user(s)?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Yes, activate them'
    }).then((result) => {
        if (result.isConfirmed) {
            // Call API for each user
            const promises = userIds.map(userId => 
                fetch(`/admin/users/${userId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ action: 'reactivate' })
                }).then(r => r.json())
            );
            
            Promise.all(promises)
                .then(() => {
                    notyf.success(`Activated ${userIds.length} user(s)`);
                    document.getElementById('actionPanel').classList.remove('active');
                    loadActiveUsers();
                })
                .catch(err => {
                    notyf.error('Failed to activate users');
                    console.error(err);
                });
        }
    });
}

// Render pending table
function renderPendingTable(users) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${users.length === 0 ? '<tr><td colspan="4" class="text-center">No pending approvals</td></tr>' : ''}
                ${users.map(u => `
                    <tr>
                        <td>${u.name}</td>
                        <td>${u.email}</td>
                        <td>${new Date(u.created_at).toLocaleDateString()}</td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="approveUser(${u.id})">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id})">
                                <i class="fas fa-trash"></i> Reject
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('pendingTable').innerHTML = html;
}

// Render invitations table
function renderInvitationsTable(invites) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Sent</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${invites.length === 0 ? '<tr><td colspan="5" class="text-center">No invitations</td></tr>' : ''}
                ${invites.map(inv => `
                    <tr>
                        <td>${inv.email}</td>
                        <td><span class="badge badge-${inv.role}">${inv.role}</span></td>
                        <td>${new Date(inv.created_at).toLocaleDateString()}</td>
                        <td><span class="badge badge-${inv.used_at ? 'success' : 'warning'}">${inv.used_at ? 'Accepted' : 'Pending'}</span></td>
                        <td>
                            ${!inv.used_at ? `<button class="btn btn-sm btn-danger" onclick="revokeInvitation(${inv.id})">
                                <i class="fas fa-times"></i> Revoke
                            </button>` : ''}
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById('invitationsTable').innerHTML = html;
}

// User actions
function changeUserRole(userId) {
    // TODO: Show modal with role dropdown
    Swal.fire({
        title: 'Change User Role',
        input: 'select',
        inputOptions: {
            'staff': 'Staff',
            'maintenance': 'Maintenance',
            'admin': 'Admin',
            'super_admin': 'Super Admin'
        },
        showCancelButton: true,
        confirmButtonText: 'Update Role'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'change_role', { role: result.value });
        }
    });
}

function approveUser(userId) {
    Swal.fire({
        title: 'Approve User?',
        text: 'User will receive an approval email',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'approve');
        }
    });
}

function deactivateUser(userId) {
    Swal.fire({
        title: 'Deactivate User?',
        text: 'User will lose access immediately',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, deactivate',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'deactivate');
        }
    });
}

function deleteUser(userId) {
    Swal.fire({
        title: 'Delete User?',
        text: 'This cannot be undone',
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            updateUser(userId, 'delete');
        }
    });
}

function updateUser(userId, action, extraData = {}) {
    fetch(`/admin/users/${userId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            action: action,
            csrf_token: csrfToken,
            ...extraData
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notyf.success(data.message);
            // Reload current subtab
            const activeSubtab = document.querySelector('.subtab-btn.active').getAttribute('data-subtab');
            loadSubtabData(activeSubtab);
        } else {
            notyf.error(data.error || 'Operation failed');
        }
    })
    .catch(err => {
        notyf.error('Request failed');
        console.error(err);
    });
}

function revokeInvitation(invitationId) {
    Swal.fire({
        title: 'Revoke Invitation?',
        text: 'Token will be invalidated',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, revoke'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/admin/invitations/${invitationId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success(data.message);
                    loadInvitations();
                } else {
                    notyf.error(data.error || 'Failed to revoke');
                }
            });
        }
    });
}

// Send invitation button
document.getElementById('sendInvitation').addEventListener('click', function() {
    Swal.fire({
        title: 'Send Invitation',
        html: `
            <input id="swal-email" class="swal2-input" placeholder="Email address">
            <select id="swal-role" class="swal2-input">
                <option value="staff">Staff</option>
                <option value="maintenance">Maintenance</option>
                <option value="admin">Admin</option>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Send Invitation',
        preConfirm: () => {
            const email = document.getElementById('swal-email').value;
            const role = document.getElementById('swal-role').value;

            if (!email) {
                Swal.showValidationMessage('Email is required');
                return false;
            }

            return { email, role };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/admin/invitations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(result.value)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success(data.message);
                    loadInvitations();
                } else {
                    notyf.error(data.error || 'Failed to send invitation');
                }
            });
        }
    });
});

// Search and filter functionality
let allUsers = [];
let filteredUsers = [];

function setupSearchAndFilter() {
    const searchInput = document.getElementById('userSearch');
    const clearBtn = document.getElementById('clearSearch');
    const statusFilter = document.getElementById('statusFilter');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            if (e.target.value) {
                clearBtn.style.display = 'block';
            } else {
                clearBtn.style.display = 'none';
            }
            applyFilters();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            applyFilters();
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }
}

function applyFilters() {
    const searchTerm = document.getElementById('userSearch')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';

    filteredUsers = allUsers.filter(user => {
        const matchesSearch = !searchTerm ||
            user.name.toLowerCase().includes(searchTerm) ||
            user.email.toLowerCase().includes(searchTerm);

        let matchesStatus = true;
        if (statusFilter === 'active') {
            matchesStatus = user.is_active;
        } else if (statusFilter === 'suspended') {
            matchesStatus = !user.is_active;
        }
        // 'all' shows both active and suspended

        return matchesSearch && matchesStatus;
    });

    renderUsersTable(filteredUsers, 'usersTable');
}

// Load active users on page load
loadActiveUsers();

// Setup search and filter after DOM is ready
setTimeout(setupSearchAndFilter, 100);
</script>
@endpush
@endsection
