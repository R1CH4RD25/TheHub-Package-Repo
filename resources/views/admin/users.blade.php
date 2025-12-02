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
                        <h3>All roles</h3>
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
                                <div class="role-item active" data-role="all">
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                    <span class="role-name">All Roles</span>
                                </div>
                                
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
                        <div class="panel-footer">
                            <a href="#" class="manage-link">
                                <i class="fas fa-cog"></i> MANAGE ROLE HIERARCHY
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right Content Area -->
                <div class="users-content">
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
                <div class="role-filter">
                    <label>Role:</label>
                    <select id="roleFilter" class="filter-select">
                        <option value="">All roles</option>
                        <option value="super_admin">Super Admin</option>
                        <option value="admin">Admin</option>
                        <option value="principal">Principal</option>
                        <option value="maintenance_director">Maintenance Director</option>
                        <option value="counselor">Counselor</option>
                        <option value="substitute_manager">Substitute Manager</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="custodial">Custodial</option>
                        <option value="cafeteria">Cafeteria</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
            </div>
            <div id="usersTable" class="data-table-container">
                <p class="text-center">Loading active users...</p>
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
function renderUsersTable(users, containerId) {
    const html = `
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${users.map(u => `
                    <tr>
                        <td>${u.name}</td>
                        <td>${u.email}</td>
                        <td><span class="badge badge-${u.role}">${u.role}</span></td>
                        <td>${u.last_login ? new Date(u.last_login).toLocaleDateString() : 'Never'}</td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="changeUserRole(${u.id})">
                                <i class="fas fa-user-tag"></i> Change Role
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deactivateUser(${u.id})">
                                <i class="fas fa-ban"></i> Suspend
                            </button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    document.getElementById(containerId).innerHTML = html;
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
    const roleFilter = document.getElementById('roleFilter');

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

    if (roleFilter) {
        roleFilter.addEventListener('change', applyFilters);
    }
}

function applyFilters() {
    const searchTerm = document.getElementById('userSearch')?.value.toLowerCase() || '';
    const roleFilter = document.getElementById('roleFilter')?.value || '';

    filteredUsers = allUsers.filter(user => {
        const matchesSearch = !searchTerm ||
            user.name.toLowerCase().includes(searchTerm) ||
            user.email.toLowerCase().includes(searchTerm);

        const matchesRole = !roleFilter || user.role === roleFilter;

        return matchesSearch && matchesRole;
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
