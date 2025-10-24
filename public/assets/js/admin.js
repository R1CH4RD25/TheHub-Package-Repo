// Admin Dashboard JavaScript

// Set initial tab IMMEDIATELY (before DOMContentLoaded) to prevent flash
(function() {
    const savedTab = localStorage.getItem('adminActiveTab');
    if (savedTab) {
        // Add style to hide non-active tabs immediately
        const style = document.createElement('style');
        style.id = 'initial-tab-state';
        style.textContent = `
            .admin-tab:not(#tab-${savedTab}) { display: none !important; }
            .admin-menu a:not([data-tab="${savedTab}"]) { opacity: 0.6; }
            .admin-menu a[data-tab="${savedTab}"] { 
                background: rgba(201, 151, 0, 0.05);
                border-left: 3px solid #C99700;
            }
        `;
        document.head.appendChild(style);
    }
})();

// Cache for roles loaded from API (must be declared at top level)
let rolesCache = null;

async function loadRolesCache() {
    if (!rolesCache) {
        try {
            const response = await fetch('/api/roles.php');
            rolesCache = await response.json();
        } catch (error) {
            console.error('Error loading roles cache:', error);
            rolesCache = []; // Set to empty to prevent repeated failed requests
        }
    }
    return rolesCache;
}

// Toggle visibility of OAuth configuration sections based on checkbox state
function toggleAuthSection(provider, isEnabled) {
    const sectionId = provider === 'google' ? 'googleAuthSection' : 'microsoftAuthSection';
    const section = document.getElementById(sectionId);
    
    if (section) {
        section.style.display = isEnabled ? 'block' : 'none';
    }
}

// Generic function to toggle dependent sections/fields based on checkbox state
// This supports cascading dependencies: Feature A → Feature B → Feature C
function toggleDependentSection(checkboxId, dependentElementId, shouldDisable = false) {
    const checkbox = document.getElementById(checkboxId);
    const element = document.getElementById(dependentElementId);
    
    if (!checkbox || !element) return;
    
    const isEnabled = checkbox.checked;
    
    // Show/hide the element
    element.style.display = isEnabled ? 'block' : 'none';
    
    // Optionally disable inputs within the element when parent is unchecked
    if (shouldDisable) {
        const inputs = element.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.disabled = !isEnabled;
            if (!isEnabled && input.type === 'checkbox') {
                input.checked = false;
                // Trigger any nested dependencies
                const onchangeAttr = input.getAttribute('onchange');
                if (onchangeAttr) {
                    input.dispatchEvent(new Event('change'));
                }
            }
        });
    }
}

// Attach event listeners for cascading dependencies
function initializeDependencies() {
    // Example: Google Groups depends on Google OAuth
    const enableGoogleGroups = document.getElementById('enableGoogleGroups');
    if (enableGoogleGroups) {
        enableGoogleGroups.addEventListener('change', function() {
            toggleDependentSection('enableGoogleGroups', 'googleGroupsFields', true);
        });
    }
    
    // Example: Microsoft Groups depends on Microsoft OAuth (when implemented)
    const enableMicrosoftGroups = document.getElementById('enableMicrosoftGroups');
    if (enableMicrosoftGroups) {
        enableMicrosoftGroups.addEventListener('change', function() {
            toggleDependentSection('enableMicrosoftGroups', 'microsoftGroupsFields', true);
        });
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    // Activity Logs state (must be declared at top of scope)
    let currentLogOffset = 0;
    let currentLogLimit = 100;
    
    // Load roles cache immediately on page load
    await loadRolesCache();
    
    // Initialize cascading dependencies for optional features
    initializeDependencies();
    
    // Hamburger Menu for Admin Sidebar (Responsive)
    const hamburgerMenu = document.querySelector('.hamburger-menu');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    if (hamburgerMenu && adminSidebar) {
        hamburgerMenu.addEventListener('click', function() {
            hamburgerMenu.classList.toggle('active');
            adminSidebar.classList.toggle('open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
        });
        
        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                hamburgerMenu.classList.remove('active');
                adminSidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
            });
        }
        
        // Close sidebar when clicking a menu item on mobile
        const sidebarLinks = adminSidebar.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    hamburgerMenu.classList.remove('active');
                    adminSidebar.classList.remove('open');
                    if (sidebarOverlay) {
                        sidebarOverlay.classList.remove('active');
                    }
                }
            });
        });
    }
    
    // Mobile navigation toggle
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            navLinks.classList.toggle('active');
            console.log('Nav toggled, active:', navLinks.classList.contains('active'));
        });

        document.addEventListener('click', function(e) {
            if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('active');
            }
        });

        // Close nav when clicking a link
        const navLinksElements = navLinks.querySelectorAll('.nav-link');
        navLinksElements.forEach(link => {
            link.addEventListener('click', function() {
                navLinks.classList.remove('active');
            });
        });
    }

    // Tab switching (both sidebar and mobile menu)
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.dataset.tab;
            switchTab(tabName);
            
            // Close mobile nav when clicking a tab link
            if (navLinks && this.classList.contains('mobile-tab-link')) {
                navLinks.classList.remove('active');
            }
        });
    });
    
    // Restore last active tab from localStorage FIRST (before loading data)
    const savedTab = localStorage.getItem('adminActiveTab');
    
    // Remove initial tab state style now that we're ready to handle tabs properly
    const initialStyle = document.getElementById('initial-tab-state');
    if (initialStyle) {
        initialStyle.remove();
    }
    
    if (savedTab && document.getElementById(`tab-${savedTab}`)) {
        // Switch to saved tab (this will load the appropriate data)
        switchTab(savedTab);
    } else {
        // No saved tab, load default User Management data
        loadUsers();
    }

    // Modal controls - Attach to ALL modals
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', closeModal);
    });
    document.querySelectorAll('.modal-cancel').forEach(btn => {
        btn.addEventListener('click', closeModal);
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal();
        }
    });

    // Sub-tabs (User Management & Vehicles)
    document.querySelectorAll('.subtab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const subtabName = this.dataset.subtab;
            const parentTab = this.closest('.admin-tab');
            
            // Remove active from subtab buttons and content within this parent tab
            parentTab.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
            parentTab.querySelectorAll('.user-subtab').forEach(t => t.classList.remove('active'));
            
            // Add active to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById(`subtab-${subtabName}`).classList.add('active');
            
            // Show/hide Save All Changes button for Section Access
            const saveSectionAccessBtn = document.getElementById('saveSectionAccessBtn');
            const addSectionBtn = document.getElementById('addSection');
            if (saveSectionAccessBtn) {
                if (subtabName === 'section-access') {
                    saveSectionAccessBtn.style.display = 'inline-flex';
                    if (addSectionBtn) addSectionBtn.style.display = 'none';
                } else if (subtabName === 'manage-sections') {
                    saveSectionAccessBtn.style.display = 'none';
                    if (addSectionBtn) addSectionBtn.style.display = 'inline-flex';
                }
            }
            
            // Load data for the subtab if needed
            if (subtabName === 'pending-users' && !document.getElementById('pendingTable').dataset.loaded) {
                loadPendingUsers();
            } else if (subtabName === 'invitations' && !document.getElementById('invitationsTable').dataset.loaded) {
                loadInvitations();
            } else if (subtabName === 'section-access' && !document.getElementById('sectionAccessTable').dataset.loaded) {
                loadSectionAccess();
            } else if (subtabName === 'role-management' && !document.getElementById('rolesManagementTableBody').dataset.loaded) {
                loadRoleManagement();
            } else if (subtabName === 'manage-sections' && !document.getElementById('sectionsManagementTable').dataset.loaded) {
                loadSectionsManagement();
            } else if (subtabName === 'installed-packages') {
                loadInstalledPackages();
            } else if (subtabName === 'available-packages') {
                loadAvailablePackages();
            } else if (subtabName === 'package-updates') {
                loadPackageUpdates();
            }
        });
    });

    function switchTab(tabName) {
        // Remove active from all menu links (both sidebar and mobile)
        document.querySelectorAll('.admin-menu a, .mobile-tab-link').forEach(a => a.classList.remove('active'));
        document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
        
        // Add active to matching tab links
        document.querySelectorAll(`[data-tab="${tabName}"]`).forEach(link => link.classList.add('active'));
        document.getElementById(`tab-${tabName}`).classList.add('active');
        
        // Save active tab to localStorage
        localStorage.setItem('adminActiveTab', tabName);
        
        // If switching to users tab, make sure data is loaded
        if (tabName === 'users') {
            const usersTable = document.getElementById('usersTable');
            const pendingTable = document.getElementById('pendingTable');
            const invitationsTable = document.getElementById('invitationsTable');
            
            if (!usersTable.dataset.loaded || usersTable.querySelector('tbody tr').length === 0) {
                loadUsers();
            }
            if (!pendingTable.dataset.loaded || pendingTable.querySelector('tbody tr').length === 0) {
                loadPendingUsers();
            }
            if (!invitationsTable.dataset.loaded || invitationsTable.querySelector('table')) {
                loadInvitations();
            }
        }

        // If switching to sections tab, load appropriate data
        if (tabName === 'sections') {
            if (window.canManageUsers) {
                const sectionAccessTable = document.getElementById('sectionAccessTable');
                if (!sectionAccessTable.dataset.loaded || sectionAccessTable.querySelector('tbody tr').length === 0) {
                    loadSectionAccess();
                }
            }
            if (window.isSuperAdmin) {
                const sectionsManagementTable = document.getElementById('sectionsManagementTable');
                if (!sectionsManagementTable.dataset.loaded || sectionsManagementTable.querySelector('tbody tr').length === 0) {
                    loadSectionsManagement();
                }
            }
        }

        // If switching to logs tab, load logs (always check if content exists)
        if (tabName === 'logs' && window.isSuperAdmin) {
            const logsTable = document.getElementById('logsTable');
            // Check if table actually has content, not just the loaded flag
            const hasContent = logsTable.querySelector('table tbody tr');
            if (!logsTable.dataset.loaded || !hasContent) {
                loadAuditLogs();
            }
        }
        
        // Trigger animations for the new tab (if animation controller is loaded)
        if (window.AdminAnimations && typeof window.AdminAnimations.onTabChange === 'function') {
            window.AdminAnimations.onTabChange(tabName);
        }
    }

    async function loadUsers() {
        try {
            const response = await fetch('/api/users.php');
            const users = await response.json();

            // Fetch all users' roles in parallel
            const rolesPromises = users.map(user => 
                fetch(`/api/user-roles.php?user_id=${user.id}`)
                    .then(r => r.json())
                    .then(data => ({ userId: user.id, roles: data.roles }))
                    .catch(() => ({ userId: user.id, roles: [] }))
            );
            const allRoles = await Promise.all(rolesPromises);
            const rolesMap = {};
            allRoles.forEach(r => rolesMap[r.userId] = r.roles);

            let html = `<div class="table-responsive"><table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Global Roles</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        ${window.isSuperAdmin ? '<th>Actions</th>' : ''}
                    </tr>
                </thead>
                <tbody>`;

            users.forEach(user => {
                const status = user.is_active ? '✅ Active' : '❌ Inactive';
                const lastLogin = user.last_login ? formatDateTime(user.last_login) : 'Never';
                
                // Display all roles as badges
                const userRoles = rolesMap[user.id] || [];
                let rolesHtml = '';
                if (userRoles.length === 0) {
                    rolesHtml = '<span style="color: #999; font-style: italic;">No roles</span>';
                } else {
                    rolesHtml = userRoles.map(r => `<span class="role-badge role-badge-${r.role}">${formatRole(r.role)}</span>`).join(' ');
                }
                
                html += `<tr data-id="${user.id}">
                    <td>${escapeHtml(user.name)}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${rolesHtml}</td>
                    <td>${status}</td>
                    <td>${lastLogin}</td>`;
                
                if (window.isSuperAdmin) {
                    html += `<td>
                        <button onclick="editUserRoles(${user.id})" class="btn-icon" title="Edit Global Roles">🎭</button>
                        <button onclick="toggleUser(${user.id}, ${user.is_active})" class="btn-icon" title="Toggle Active">🔄</button>
                    </td>`;
                }
                
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            const usersTable = document.getElementById('usersTable');
            usersTable.innerHTML = html;
            usersTable.dataset.loaded = 'true';
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    function clearFilters() {
        document.getElementById('filterStartDate').value = '';
        document.getElementById('filterEndDate').value = '';
        document.getElementById('filterVehicle').value = '';
        document.getElementById('filterPurpose').value = '';
        loadRecords();
    }

    function showVehicleModal(vehicleId = null) {
        const modal = document.getElementById('vehicleModal');
        const form = document.getElementById('vehicleForm');
        
        form.reset();
        document.getElementById('vehicleModalTitle').textContent = vehicleId ? 'Edit Vehicle' : 'Add Vehicle';
        document.getElementById('vehicleId').value = vehicleId || '';
        
        if (vehicleId) {
            // Load vehicle data
            fetch(`/api/vehicles.php?id=${vehicleId}`)
                .then(r => r.json())
                .then(vehicle => {
                    Object.keys(vehicle).forEach(key => {
                        const input = form.querySelector(`[name="${key}"]`);
                        if (input) input.value = vehicle[key] || '';
                    });
                });
        }
        
        modal.style.display = 'block';
    }

    function closeModal() {
        // Close all modals
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        
        // Reset forms
        document.getElementById('invitationForm')?.reset();
        document.getElementById('sectionAccessForm')?.reset();
        document.getElementById('sectionForm')?.reset();
        document.getElementById('userRolesForm')?.reset();
        
        // Clear hidden fields
        document.getElementById('sectionId').value = '';
    }

    window.changeUserRole = function(id, currentRole) {
        const newRole = prompt(`Change role for user (current: ${currentRole}):\nOptions: staff, manager, admin, super_admin`, currentRole);
        if (newRole && ['staff', 'manager', 'admin', 'super_admin'].includes(newRole)) {
            // Implementation here
            alert('Role change functionality coming soon');
        }
    };

    window.toggleUser = function(id, isActive) {
        const action = isActive ? 'deactivate' : 'activate';
        if (!confirm(`Are you sure you want to ${action} this user?`)) return;
        
        // Implementation here
        alert(`${action} functionality coming soon`);
    };

    // Edit User Global Roles
    window.editUserRoles = async function(userId) {
        try {
            // Load user info
            const usersResponse = await fetch('/api/users.php');
            const users = await usersResponse.json();
            const user = users.find(u => u.id === userId);

            if (!user) {
                showToast('User not found', 'error');
                return;
            }

            // Load current roles
            const rolesResponse = await fetch(`/api/user-roles.php?user_id=${userId}`);
            const rolesData = await rolesResponse.json();
            const currentRoles = rolesData.roles.map(r => r.role);

            // Set user info
            document.getElementById('roleUserId').value = userId;
            document.getElementById('roleUserName').textContent = `${user.name} (${user.email})`;

            // Uncheck all checkboxes first
            document.querySelectorAll('#userRolesForm input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });

            // Check current roles
            currentRoles.forEach(role => {
                const checkbox = document.querySelector(`#userRolesForm input[value="${role}"]`);
                if (checkbox) checkbox.checked = true;
            });

            // Show modal
            document.getElementById('userRolesModal').style.display = 'block';
        } catch (error) {
            console.error('Error loading user roles:', error);
            showToast('Failed to load user roles', 'error');
        }
    };

    // User Roles Form Submit Handler
    document.getElementById('userRolesForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/user-roles.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (response.ok) {
                showToast('User roles updated successfully', 'success');
                closeModal();
                loadUsers(); // Refresh users list
            } else {
                showToast(result.error || 'Failed to update roles', 'error');
            }
        } catch (error) {
            console.error('Error updating user roles:', error);
            showToast('Network error. Please try again.', 'error');
        }
    });

    // Pending Users Functions
    async function loadPendingUsers() {
        const pendingTable = document.getElementById('pendingTable');
        if (!pendingTable) return;

        try {
            const response = await fetch('/api/users.php?pending=true');
            const users = await response.json();

            let html = `<div class="table-responsive"><table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>`;

            if (users.length === 0) {
                html += '<tr><td colspan="4" style="text-align:center;">No pending users</td></tr>';
            } else {
                users.forEach(user => {
                    html += `<tr>
                        <td>${escapeHtml(user.name)}</td>
                        <td>${escapeHtml(user.email)}</td>
                        <td>${formatDateTime(user.created_at)}</td>
                        <td>
                            <button onclick="approveUser(${user.id})" class="btn btn-primary btn-sm">✓ Approve</button>
                            <button onclick="denyUser(${user.id})" class="btn btn-danger btn-sm">✗ Deny</button>
                        </td>
                    </tr>`;
                });
            }

            html += '</tbody></table></div>';
            const pendingTable = document.getElementById('pendingTable');
            pendingTable.innerHTML = html;
            pendingTable.dataset.loaded = 'true';
        } catch (error) {
            console.error('Error loading pending users:', error);
            document.getElementById('pendingTable').innerHTML = '<p class="error">Failed to load pending users.</p>';
        }
    }

    window.approveUser = async function(id) {
        if (!confirm('Approve this user?')) return;

        try {
            const response = await fetch(`/api/users.php?id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'approve',
                    csrf_token: window.csrfToken
                })
            });

            const result = await response.json();

            if (response.ok) {
                showToast('User approved successfully! They will receive an email notification.', 'success');
                loadPendingUsers();
                loadUsers(); // Refresh main users list
            } else {
                showToast('Error: ' + (result.error || 'Failed to approve user'), 'error');
            }
        } catch (error) {
            console.error('Error approving user:', error);
            showToast('Network error. Please try again.', 'error');
        }
    };

    window.denyUser = async function(id) {
        if (!confirm('Permanently delete this user request? This cannot be undone.')) return;

        try {
            const response = await fetch(`/api/users.php?id=${id}`, {
                method: 'DELETE'
            });

            if (response.ok) {
                showToast('✓ User request denied and deleted.', 'success');
                loadPendingUsers();
            } else {
                showToast('Failed to delete user', 'error');
            }
        } catch (error) {
            console.error('Error denying user:', error);
            showToast('Network error. Please try again.', 'error');
        }
    };

    // Invitations Functions
    async function loadInvitations() {
        const invitationsTable = document.getElementById('invitationsTable');
        if (!invitationsTable) return;

        try {
            const response = await fetch('/api/invitations.php');
            const invitations = await response.json();

            let html = `<div class="table-responsive"><table class="data-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Sent By</th>
                        <th>Sent Date</th>
                        <th>Expires</th>
                    </tr>
                </thead>
                <tbody>`;

            if (invitations.length === 0) {
                html += '<tr><td colspan="6" style="text-align:center;">No invitations sent</td></tr>';
            } else {
                invitations.forEach(inv => {
                    const status = inv.accepted_at ? '✓ Accepted' : 
                                  (new Date(inv.expires_at) < new Date() ? '⏰ Expired' : '⏳ Pending');
                    html += `<tr>
                        <td>${escapeHtml(inv.email)}</td>
                        <td>${formatRole(inv.role)}</td>
                        <td>${status}</td>
                        <td>${escapeHtml(inv.invited_by_name || 'System')}</td>
                        <td>${formatDateTime(inv.created_at)}</td>
                        <td>${formatDateTime(inv.expires_at)}</td>
                    </tr>`;
                });
            }

            html += '</tbody></table></div>';
            document.getElementById('invitationsTable').innerHTML = html;
            invitationsTable.dataset.loaded = 'true';
        } catch (error) {
            console.error('Error loading invitations:', error);
            document.getElementById('invitationsTable').innerHTML = '<p class="error">Failed to load invitations.</p>';
        }
    }

    function showInvitationModal() {
        document.getElementById('invitationModal').style.display = 'block';
    }

    async function handleInvitationSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('/api/invitations.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (response.ok) {
                showToast('✓ Invitation sent successfully! The user will receive an email.', 'success');
                document.getElementById('invitationModal').style.display = 'none';
                e.target.reset();
                loadInvitations();
            } else {
                showToast('Error: ' + (result.error || 'Failed to send invitation'), 'error');
            }
        } catch (error) {
            console.error('Error sending invitation:', error);
            showToast('Network error. Please try again.', 'error');
        }
    }

    // Toast notification function
    function showToast(message, type = 'success') {
        // Remove existing toast if any
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️'
        };
        
        toast.innerHTML = `
            <span class="toast-icon">${icons[type] || '✅'}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;
        
        document.body.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    // Event listeners for new features
    if (window.canManageUsers) {
        // Tables are now loaded via subtab system (see subtab-btn click handlers above)
        
        document.getElementById('refreshPending')?.addEventListener('click', loadPendingUsers);
        document.getElementById('sendInvitation')?.addEventListener('click', showInvitationModal);
        document.getElementById('invitationForm')?.addEventListener('submit', handleInvitationSubmit);
        
        // Add close handlers for invitation modal
        const invModal = document.getElementById('invitationModal');
        if (invModal) {
            invModal.querySelector('.modal-close')?.addEventListener('click', closeModal);
            invModal.querySelector('.modal-cancel')?.addEventListener('click', closeModal);
        }
    }

    // Helper functions
    function formatDate(dateString) {
        const date = new Date(dateString + 'T00:00:00');
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleString('en-US');
    }

    function formatNumber(num, decimals = 0) {
        return Number(num).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function formatRole(role) {
        // If we have cached roles, use them
        if (rolesCache && Array.isArray(rolesCache)) {
            const roleData = rolesCache.find(r => r.value === role);
            if (roleData) {
                return roleData.label;
            }
        }
        
        // Fallback to capitalizing the role value
        return role.split('_').map(word => 
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Section Access Management
    async function loadSectionAccess() {
        const container = document.getElementById('sectionAccessTable');
        if (!container) return;

        try {
            // Fetch roles and sections in parallel
            const [rolesResponse, sectionsResponse] = await Promise.all([
                fetch('/api/roles.php'),
                fetch('/api/section-role-access.php')
            ]);
            
            const roles = await rolesResponse.json();
            const data = await sectionsResponse.json();

            if (!data.sections || data.sections.length === 0) {
                container.innerHTML = '<p class="info-text">No sections available.</p>';
                return;
            }

            let html = `
                <form id="sectionRoleAccessForm">
                    <input type="hidden" name="csrf_token" value="${window.csrfToken}">
                    <div class="table-responsive">
                    <table class="section-role-access-table table-sticky-first-col">
                        <thead>
                            <tr>
                                <th class="sticky-col">Section</th>`;

            // Add rotated headers for each role
            roles.forEach(role => {
                html += `<th><div class="rotated-header">${escapeHtml(role.label)}</div></th>`;
            });

            html += `</tr></thead><tbody>`;

            // Add row for each section
            data.sections.forEach(section => {
                html += `<tr>
                    <td class="sticky-col">
                        <div class="section-name-cell">
                            <span class="section-icon">${section.icon}</span>
                            <div>
                                <div class="section-name">${escapeHtml(section.display_name)}</div>
                                ${section.description ? `<div class="section-description">${escapeHtml(section.description)}</div>` : ''}
                            </div>
                        </div>
                    </td>`;

                // Add checkbox for each role
                roles.forEach(role => {
                    const isChecked = section.roles && section.roles.includes(role.value);
                    const isDisabled = role.value === 'super_admin'; // Super admin always has access
                    
                    html += `<td class="checkbox-cell">
                        <input 
                            type="checkbox" 
                            name="section_${section.id}_roles[]" 
                            value="${role.value}"
                            ${isChecked ? 'checked' : ''}
                            ${isDisabled ? 'checked disabled' : ''}
                            data-section-id="${section.id}"
                        >
                    </td>`;
                });

                html += `</tr>`;
            });

            html += `</tbody></table>
                </div>
            </form>`;

            container.innerHTML = html;

            // Add form submit handler
            document.getElementById('sectionRoleAccessForm').addEventListener('submit', handleSectionRoleAccessSubmit);
            
            // Show the Save All Changes button in header
            const saveBtn = document.getElementById('saveSectionAccessBtn');
            if (saveBtn) {
                saveBtn.style.display = 'inline-flex';
                // Remove any existing listeners
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                // Add click handler to submit the form
                newSaveBtn.addEventListener('click', () => {
                    document.getElementById('sectionRoleAccessForm').dispatchEvent(new Event('submit'));
                });
            }

        } catch (error) {
            console.error('Error loading section access:', error);
            container.innerHTML = '<p class="error">Failed to load section access data.</p>';
        }
    }

    async function handleSectionRoleAccessSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const sections = {};

        // Group checkboxes by section
        const checkboxes = form.querySelectorAll('input[type="checkbox"]:not([disabled])');
        checkboxes.forEach(cb => {
            const sectionId = cb.dataset.sectionId;
            if (!sections[sectionId]) {
                sections[sectionId] = [];
            }
            if (cb.checked) {
                sections[sectionId].push(cb.value);
            }
        });

        // Super admin is always included
        Object.keys(sections).forEach(sectionId => {
            if (!sections[sectionId].includes('super_admin')) {
                sections[sectionId].push('super_admin');
            }
        });

        try {
            // Update each section
            const updates = Object.entries(sections).map(([sectionId, roles]) => {
                const formData = new FormData();
                formData.append('csrf_token', window.csrfToken);
                formData.append('section_id', sectionId);
                roles.forEach(role => formData.append('roles[]', role));

                return fetch('/api/section-role-access.php', {
                    method: 'POST',
                    body: formData
                });
            });

            const results = await Promise.all(updates);
            const allSuccessful = results.every(r => r.ok);

            if (allSuccessful) {
                showToast('✅ Section access updated successfully!', 'success');
                loadSectionAccess(); // Reload to show updated state
            } else {
                showToast('⚠️ Some updates failed. Please try again.', 'warning');
            }
        } catch (error) {
            console.error('Error updating section access:', error);
            showToast('❌ Failed to update section access', 'error');
        }
    }

    // Event listeners for section access
    if (window.canManageUsers) {
        document.getElementById('refreshSectionAccess')?.addEventListener('click', loadSectionAccess);
        // Note: Section access is now role-based, not user-based
        // The sectionRoleAccessForm handler is attached dynamically in loadSectionAccess()
    }

    // Role Management (Super Admin Only)
    async function loadRoleManagement() {
        const tbody = document.getElementById('rolesManagementTableBody');
        if (!tbody) return;

        try {
            const response = await fetch('/api/role-management.php');
            const data = await response.json();

            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color: red;">Error loading roles</td></tr>';
                return;
            }

            tbody.dataset.loaded = 'true';

            if (data.roles.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No roles found</td></tr>';
                return;
            }

            tbody.innerHTML = data.roles.map(role => {
                const statusBadge = role.is_active ? 
                    '<span class="badge badge-success">✓ Active</span>' : 
                    '<span class="badge badge-inactive">✗ Inactive</span>';
                
                const toggleBtn = role.can_disable ? 
                    `<button class="btn btn-sm ${role.is_active ? 'btn-warning' : 'btn-success'}" 
                             onclick="toggleRole('${role.value}', ${!role.is_active})">
                        ${role.is_active ? '🔒 Disable' : '✓ Enable'}
                    </button>` :
                    '<span class="badge badge-locked" title="Core system role - cannot be disabled">🔒 Locked</span>';

                return `<tr>
                    <td>${statusBadge}</td>
                    <td><strong>${escapeHtml(role.label)}</strong><br><code style="font-size: 0.8rem; color: #666;">${role.value}</code></td>
                    <td style="font-size: 0.9rem;">${escapeHtml(role.description)}</td>
                    <td style="text-align: center;">${role.hierarchy}</td>
                    <td style="text-align: center;">
                        <span class="badge ${role.active_users > 0 ? 'badge-info' : 'badge-inactive'}">
                            ${role.active_users} user${role.active_users !== 1 ? 's' : ''}
                        </span>
                    </td>
                    <td>
                        <input type="text" 
                               id="notes_${role.value}" 
                               value="${escapeHtml(role.notes || '')}" 
                               placeholder="Add notes..."
                               style="width: 100%; padding: 4px; font-size: 0.85rem;"
                               onblur="updateRoleNotes('${role.value}', this.value)">
                    </td>
                    <td style="text-align: center;">
                        ${toggleBtn}
                    </td>
                </tr>`;
            }).join('');
        } catch (error) {
            console.error('Error loading role management:', error);
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color: red;">Failed to load roles</td></tr>';
        }
    }

    window.toggleRole = async function(roleValue, enable) {
        if (!confirm(`Are you sure you want to ${enable ? 'enable' : 'disable'} the "${roleValue}" role?\n\n${!enable ? '⚠️ Users with this role will still exist but the role will be hidden from all selection menus.' : ''}`)) {
            return;
        }

        try {
            const response = await fetch('/api/role-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    role_value: roleValue,
                    is_active: enable
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast(`✓ Role ${enable ? 'enabled' : 'disabled'} successfully`, 'success');
                loadRoleManagement(); // Reload the table
                
                // Also reload roles cache for the rest of the app
                rolesCache = null;
                await loadRolesCache();
            } else {
                showToast('Error: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error toggling role:', error);
            showToast('Network error toggling role', 'error');
        }
    };

    window.updateRoleNotes = async function(roleValue, notes) {
        try {
            const response = await fetch('/api/role-management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    role_value: roleValue,
                    notes: notes
                })
            });

            const result = await response.json();

            if (result.success) {
                // Silent success - no toast for note updates
                console.log('Role notes updated');
            } else {
                showToast('Error updating notes: ' + result.error, 'error');
            }
        } catch (error) {
            console.error('Error updating role notes:', error);
        }
    };

    // Section Management (Super Admin Only)
    async function loadSectionsManagement() {
        const container = document.getElementById('sectionsManagementTable');
        if (!container) return;

        try {
            const response = await fetch('/api/sections.php');
            const sections = await response.json();

            let html = `<div class="table-responsive">
                <table class="data-table table-sticky-first-col">
                <thead>
                    <tr>
                        <th class="sticky-col">Icon</th>
                        <th>Section Name</th>
                        <th>Display Name</th>
                        <th>Base URL</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>`;

            if (sections.length === 0) {
                html += '<tr><td colspan="7" style="text-align:center;">No sections found</td></tr>';
            } else {
                sections.forEach(section => {
                    const statusBadge = section.is_active ? 
                        '<span class="badge badge-success">Active</span>' : 
                        '<span class="badge badge-inactive">Inactive</span>';

                    html += `<tr>
                        <td class="sticky-col" style="font-size: 2rem;">${section.icon}</td>
                        <td><code>${escapeHtml(section.name)}</code></td>
                        <td>${escapeHtml(section.display_name)}</td>
                        <td><small>${escapeHtml(section.base_url)}</small></td>
                        <td>${section.sort_order}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="editSection(${section.id})">Edit</button>
                            <button class="btn btn-sm ${section.is_active ? 'btn-warning' : 'btn-success'}" 
                                    onclick="toggleSection(${section.id}, ${section.is_active ? 0 : 1})">
                                ${section.is_active ? 'Deactivate' : 'Activate'}
                            </button>
                        </td>
                    </tr>`;
                });
            }

            html += '</tbody></table></div>';
            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading sections:', error);
            container.innerHTML = '<p class="error">Failed to load sections.</p>';
        }
    }

    window.editSection = async function(sectionId) {
        try {
            const response = await fetch('/api/sections.php');
            const sections = await response.json();
            const section = sections.find(s => s.id === sectionId);

            if (!section) {
                showToast('Section not found', 'error');
                return;
            }

            document.getElementById('sectionModalTitle').textContent = 'Edit Section';
            document.getElementById('sectionId').value = section.id;
            document.getElementById('sectionName').value = section.name;
            document.getElementById('sectionDisplayName').value = section.display_name;
            document.getElementById('sectionIcon').value = section.icon;
            document.getElementById('sectionDescription').value = section.description || '';
            document.getElementById('sectionBaseUrl').value = section.base_url;
            document.getElementById('sectionSortOrder').value = section.sort_order;
            document.getElementById('sectionIsActive').checked = section.is_active;

            document.getElementById('sectionModal').style.display = 'block';
        } catch (error) {
            console.error('Error loading section:', error);
            showToast('Failed to load section', 'error');
        }
    };

    window.toggleSection = async function(sectionId, isActive) {
        const action = isActive ? 'activate' : 'deactivate';
        if (!confirm(`Are you sure you want to ${action} this section? ${!isActive ? 'It will be hidden from all users.' : 'Users with access will see it.'}`)) {
            return;
        }

        try {
            const response = await fetch('/api/sections.php', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    id: sectionId,
                    is_active: isActive,
                    csrf_token: window.csrfToken
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast(`Section ${action}d successfully`, 'success');
                loadSectionsManagement();
            } else {
                showToast(result.error || 'Failed to update section', 'error');
            }
        } catch (error) {
            console.error('Error toggling section:', error);
            showToast('Failed to update section', 'error');
        }
    };

    async function handleSectionFormSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);

        try {
            const response = await fetch('/api/sections.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                closeModal();
                loadSectionsManagement();
            } else {
                showToast(result.error || 'Failed to save section', 'error');
            }
        } catch (error) {
            console.error('Error saving section:', error);
            showToast('Failed to save section', 'error');
        }
    }

    // Event listeners for section management
    if (window.isSuperAdmin) {
        document.getElementById('addSection')?.addEventListener('click', function() {
            document.getElementById('sectionModalTitle').textContent = 'Add New Section';
            document.getElementById('sectionForm').reset();
            document.getElementById('sectionId').value = '';
            document.getElementById('sectionModal').style.display = 'block';
            
            // Reset manual edit flag when opening for new section
            if (window.sectionSlugManuallyEdited !== undefined) {
                window.sectionSlugManuallyEdited = false;
            }
        });

        document.getElementById('refreshSections')?.addEventListener('click', loadSectionsManagement);
        document.getElementById('sectionForm')?.addEventListener('submit', handleSectionFormSubmit);

        // Add modal close handlers
        const sectionModal = document.getElementById('sectionModal');
        if (sectionModal) {
            sectionModal.querySelector('.modal-close')?.addEventListener('click', closeModal);
            sectionModal.querySelector('.modal-cancel')?.addEventListener('click', closeModal);
        }
        
        // Auto-generate section slug from display name
        const sectionDisplayNameInput = document.getElementById('sectionDisplayName');
        const sectionNameInput = document.getElementById('sectionName');
        const sectionBaseUrlInput = document.getElementById('sectionBaseUrl');
        
        if (sectionDisplayNameInput && sectionNameInput && sectionBaseUrlInput) {
            window.sectionSlugManuallyEdited = false;
            
            // Track if user manually edited the slug
            sectionNameInput.addEventListener('input', function() {
                window.sectionSlugManuallyEdited = true;
            });
            
            // Auto-generate slug from display name
            sectionDisplayNameInput.addEventListener('input', function() {
                if (!window.sectionSlugManuallyEdited || sectionNameInput.value === '') {
                    const displayName = this.value;
                    const slug = displayName
                        .toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '') // Remove special chars
                        .replace(/\s+/g, '-')          // Replace spaces with hyphens
                        .replace(/-+/g, '-')           // Replace multiple hyphens with single
                        .replace(/^-|-$/g, '');        // Remove leading/trailing hyphens
                    
                    sectionNameInput.value = slug;
                    sectionBaseUrlInput.value = `/modules/${slug}/`;
                }
            });
        }
    }

    // Activity Logs Functions
    async function loadAuditLogs(offset = 0) {
        currentLogOffset = offset;
        currentLogLimit = parseInt(document.getElementById('filterLimit')?.value || 100);
        
        const action = document.getElementById('filterAction')?.value || '';
        const table = document.getElementById('filterTable')?.value || '';
        
        const params = new URLSearchParams({
            limit: currentLogLimit,
            offset: currentLogOffset
        });
        
        if (action) params.append('action', action);
        if (table) params.append('table', table);
        
        try {
            const response = await fetch(`/api/audit-logs.php?${params}`);
            const data = await response.json();
            
            if (data.error) {
                document.getElementById('logsTable').innerHTML = '<p class="error">' + escapeHtml(data.error) + '</p>';
                return;
            }
            
            displayAuditLogs(data);
        } catch (error) {
            console.error('Error loading audit logs:', error);
            document.getElementById('logsTable').innerHTML = '<p class="error">Failed to load audit logs.</p>';
        }
    }

    function displayAuditLogs(data) {
        const container = document.getElementById('logsTable');
        const { logs, total, limit, offset } = data;
        
        if (!logs || logs.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #666; padding: 2rem;">No activity logs found. Actions will be logged here as users make changes.</p>';
            document.getElementById('logsPagination').innerHTML = '';
            container.dataset.loaded = 'true';
            return;
        }
        
        let html = `<div class="table-responsive"><table class="data-table">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record ID</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>`;
        
        logs.forEach(log => {
            const actionBadge = getActionBadge(log.action);
            const details = formatLogDetails(log);
            
            html += `<tr>
                <td style="white-space: nowrap;">${formatDateTime(log.created_at)}</td>
                <td>
                    <strong>${escapeHtml(log.user_name || 'System')}</strong><br>
                    <small style="color: #666;">${escapeHtml(log.user_email || 'N/A')}</small>
                </td>
                <td>${actionBadge}</td>
                <td><code>${escapeHtml(log.table_name)}</code></td>
                <td>${log.record_id}</td>
                <td style="max-width: 300px; font-size: 0.875rem;">${details}</td>
                <td><small>${escapeHtml(log.ip_address || 'N/A')}</small></td>
            </tr>`;
        });
        
        html += '</tbody></table></div>';
        container.innerHTML = html;
        container.dataset.loaded = 'true';
        
        // Update pagination
        updateLogsPagination(total, limit, offset);
    }

    function getActionBadge(action) {
        const badges = {
            'create': '<span class="badge badge-success">CREATE</span>',
            'update': '<span class="badge" style="background: #3b82f6; color: white;">UPDATE</span>',
            'delete': '<span class="badge" style="background: #ef4444; color: white;">DELETE</span>',
            'approve': '<span class="badge" style="background: #10b981; color: white;">APPROVE</span>',
            'activate': '<span class="badge" style="background: #059669; color: white;">ACTIVATE</span>',
            'deactivate': '<span class="badge" style="background: #f59e0b; color: white;">DEACTIVATE</span>',
            'grant_access': '<span class="badge" style="background: #8b5cf6; color: white;">GRANT ACCESS</span>',
            'revoke_access': '<span class="badge" style="background: #dc2626; color: white;">REVOKE ACCESS</span>',
            'login_success': '<span class="badge" style="background: #6366f1; color: white;">LOGIN ✓</span>',
            'login_failed': '<span class="badge" style="background: #dc2626; color: white;">LOGIN ✗</span>',
            'logout': '<span class="badge" style="background: #6b7280; color: white;">LOGOUT</span>'
        };
        
        return badges[action] || `<span class="badge" style="background: #9ca3af; color: white;">${escapeHtml(action.toUpperCase())}</span>`;
    }

    function formatLogDetails(log) {
        if (log.new_values) {
            try {
                const newVals = JSON.parse(log.new_values);
                const keys = Object.keys(newVals).slice(0, 3); // Show first 3 fields
                return keys.map(k => `<strong>${k}:</strong> ${escapeHtml(String(newVals[k]))}`).join('<br>');
            } catch (e) {
                return escapeHtml(log.new_values).substring(0, 100);
            }
        }
        if (log.old_values) {
            return 'Record deleted';
        }
        return '-';
    }

    function updateLogsPagination(total, limit, offset) {
        const pagination = document.getElementById('logsPagination');
        const currentPage = Math.floor(offset / limit) + 1;
        const totalPages = Math.ceil(total / limit);
        
        let html = `<div style="display: flex; align-items: center; gap: 1rem;">`;
        html += `<span>Page ${currentPage} of ${totalPages} (${total} total records)</span>`;
        
        if (currentPage > 1) {
            html += `<button class="btn btn-sm btn-secondary" onclick="loadAuditLogs(${offset - limit})">← Previous</button>`;
        }
        if (currentPage < totalPages) {
            html += `<button class="btn btn-sm btn-secondary" onclick="loadAuditLogs(${offset + limit})">Next →</button>`;
        }
        
        html += `</div>`;
        pagination.innerHTML = html;
    }

    // Audit Logs Event Listeners
    document.getElementById('refreshLogs')?.addEventListener('click', () => loadAuditLogs(0));
    document.getElementById('applyLogFilters')?.addEventListener('click', () => loadAuditLogs(0));
    document.getElementById('clearLogFilters')?.addEventListener('click', function() {
        document.getElementById('filterAction').value = '';
        document.getElementById('filterTable').value = '';
        document.getElementById('filterLimit').value = '100';
        loadAuditLogs(0);
    });

    // Expose loadAuditLogs globally for pagination
    window.loadAuditLogs = loadAuditLogs;

    // =========================================================================
    // THEME MANAGEMENT
    // =========================================================================

    async function loadThemes() {
        try {
            // Add cache-busting parameter to avoid stale cached responses
            const response = await fetch('/api/themes.php?_=' + Date.now());
            
            if (!response.ok) {
                console.error('Themes API error:', response.status, response.statusText);
                const text = await response.text();
                console.error('Response body:', text);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const themes = await response.json();
            
            const container = document.getElementById('themesContainer');
            if (!themes || themes.length === 0) {
                container.innerHTML = '<p style="color: #6B7280;">No saved themes yet.</p>';
                return;
            }
            
            let html = '';
            const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim() || '#C99700';
            
            themes.forEach(theme => {
                const isActive = theme.is_active == 1;
                const isSystem = theme.is_system == 1;
                const settings = typeof theme.settings === 'string' ? JSON.parse(theme.settings) : theme.settings;
                
                html += `
                <div class="theme-card" style="background: white; border: 2px solid ${isActive ? primaryColor : '#E5E7EB'}; border-radius: 8px; padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                        <div>
                            <h4 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                                ${escapeHtml(theme.name)}
                                ${isActive ? '<span class="badge badge-success">ACTIVE</span>' : ''}
                                ${isSystem ? '<span class="badge badge-system">SYSTEM</span>' : ''}
                            </h4>
                            ${theme.description ? `<p style="color: #6B7280; font-size: 0.875rem; margin: 0.25rem 0 0 0;">${escapeHtml(theme.description)}</p>` : ''}
                        </div>
                    </div>
                    
                    <!-- Color Preview -->
                    <div style="display: flex; gap: 4px; margin: 0.75rem 0;">
                        <div style="width: 24px; height: 24px; background: ${settings.primary_color || '#C99700'}; border-radius: 4px; border: 1px solid #ddd;"></div>
                        <div style="width: 24px; height: 24px; background: ${settings.navbar_color || '#000000'}; border-radius: 4px; border: 1px solid #ddd;"></div>
                        <div style="width: 24px; height: 24px; background: ${settings.accent_color || '#FFD700'}; border-radius: 4px; border: 1px solid #ddd;"></div>
                        <div style="width: 24px; height: 24px; background: ${settings.header_bg_color || '#000000'}; border-radius: 4px; border: 1px solid #ddd;"></div>
                        <div style="width: 24px; height: 24px; background: ${settings.button_primary_bg || '#C99700'}; border-radius: 4px; border: 1px solid #ddd;"></div>
                    </div>
                    
                    <div style="font-size: 0.75rem; color: #9CA3AF; margin-bottom: 0.75rem;">
                        Created ${theme.created_at ? new Date(theme.created_at).toLocaleDateString() : 'Unknown'}
                        ${theme.creator_name ? ` by ${escapeHtml(theme.creator_name)}` : ''}
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        ${!isActive ? `<button class="btn btn-sm btn-primary" onclick="activateTheme(${theme.id})">Load Theme</button>` : ''}
                        ${!isSystem ? `<button class="btn btn-sm btn-secondary" onclick="selectThemeForUpdate(${theme.id}, '${escapeHtml(theme.name).replace(/'/g, "\\'")}')">Overwrite with Current</button>` : ''}
                        <button class="btn btn-sm btn-secondary" onclick="exportTheme(${theme.id})">Export</button>
                        ${!isActive && !isSystem ? `<button class="btn btn-sm btn-danger" onclick="deleteTheme(${theme.id}, '${escapeHtml(theme.name).replace(/'/g, "\\'")}')">Delete</button>` : ''}
                    </div>
                </div>`;
            });
            
            container.innerHTML = html;
        } catch (error) {
            console.error('Error loading themes:', error);
            showMessage('Error loading themes', 'error');
        }
    }

    // Save current settings as new theme
    document.getElementById('saveCurrentTheme')?.addEventListener('click', async function() {
        const name = document.getElementById('newThemeName').value.trim();
        const description = document.getElementById('newThemeDescription').value.trim();
        
        if (!name) {
            showMessage('Please enter a theme name', 'error');
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'save_current');
            formData.append('name', name);
            formData.append('description', description);
            formData.append('csrf_token', window.csrfToken);
            
            const response = await fetch('/api/themes.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showMessage('Theme saved successfully!', 'success');
                document.getElementById('newThemeName').value = '';
                document.getElementById('newThemeDescription').value = '';
                loadThemes();
            } else {
                showMessage(result.error || 'Failed to save theme', 'error');
            }
        } catch (error) {
            console.error('Error saving theme:', error);
            showMessage('Error saving theme', 'error');
        }
    });

    // Activate theme (load its settings)
    window.activateTheme = async function(themeId) {
        if (!confirm('Load this theme? Current settings will be replaced.')) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'activate');
            formData.append('id', themeId);
            formData.append('csrf_token', window.csrfToken);
            
            const response = await fetch('/api/themes.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData).toString()
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showMessage('Theme activated! Page will reload...', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showMessage(result.error || 'Failed to activate theme', 'error');
            }
        } catch (error) {
            console.error('Error activating theme:', error);
            showMessage('Error activating theme', 'error');
        }
    };

    // Select a theme for updating (puts form in update mode)
    window.selectedThemeForUpdate = null;
    
    window.selectThemeForUpdate = function(themeId, themeName) {
        window.selectedThemeForUpdate = { id: themeId, name: themeName };
        
        // Show update mode UI
        document.getElementById('updateThemeNotice').style.display = 'block';
        document.getElementById('updateThemeName').textContent = themeName;
        document.getElementById('updateSelectedTheme').style.display = 'inline-block';
        document.getElementById('saveCurrentTheme').style.display = 'none';
        
        // Populate the form with the theme name
        document.getElementById('newThemeName').value = themeName;
        document.getElementById('newThemeName').disabled = true;
        
        // Scroll to the form
        document.querySelector('#subtab-themes .settings-section').scrollIntoView({ behavior: 'smooth' });
        
        showMessage(`Click "Update Selected Theme" to overwrite "${themeName}" with current color scheme`, 'info');
    };
    
    window.cancelUpdateMode = function() {
        window.selectedThemeForUpdate = null;
        document.getElementById('updateThemeNotice').style.display = 'none';
        document.getElementById('updateSelectedTheme').style.display = 'none';
        document.getElementById('saveCurrentTheme').style.display = 'inline-block';
        document.getElementById('newThemeName').value = '';
        document.getElementById('newThemeName').disabled = false;
    };
    
    // Update selected theme button click
    document.getElementById('updateSelectedTheme')?.addEventListener('click', async function() {
        if (!window.selectedThemeForUpdate) {
            showMessage('No theme selected for update', 'error');
            return;
        }
        
        const themeId = window.selectedThemeForUpdate.id;
        const themeName = window.selectedThemeForUpdate.name;
        
        if (!confirm(`Overwrite "${themeName}" with current color scheme? This cannot be undone.`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('id', themeId);
            formData.append('csrf_token', window.csrfToken);
            
            const response = await fetch('/api/themes.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData).toString()
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showMessage('Theme overwritten successfully!', 'success');
                cancelUpdateMode();
                loadThemes();
            } else {
                showMessage(result.error || 'Failed to update theme', 'error');
            }
        } catch (error) {
            console.error('Error updating theme:', error);
            showMessage('Error updating theme', 'error');
        }
    });

    // Overwrite Active Theme button - shows when active theme exists and settings have changed
    let activeThemeData = null;
    
    async function updateOverwriteActiveButton() {
        try {
            const response = await fetch('/api/themes.php?_=' + Date.now());
            if (response.ok) {
                const themes = await response.json();
                const activeTheme = themes.find(t => t.is_active == 1);
                activeThemeData = activeTheme;
                
                const btn = document.getElementById('overwriteActiveTheme');
                if (btn && activeTheme && !activeTheme.is_system) {
                    btn.style.display = 'inline-block';
                    btn.title = `Overwrite "${activeTheme.name}" with current settings`;
                } else if (btn) {
                    btn.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Error checking active theme:', error);
        }
    }
    
    // Call on load
    updateOverwriteActiveButton();
    
    // Overwrite active theme button click
    document.getElementById('overwriteActiveTheme')?.addEventListener('click', async function() {
        if (!activeThemeData) {
            showMessage('No active theme found', 'error');
            return;
        }
        
        if (activeThemeData.is_system) {
            showMessage('Cannot overwrite system themes', 'error');
            return;
        }
        
        if (!confirm(`Overwrite "${activeThemeData.name}" with current color settings? This cannot be undone.`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('id', activeThemeData.id);
            formData.append('csrf_token', window.csrfToken);
            
            const response = await fetch('/api/themes.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData).toString()
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showMessage(`Theme "${activeThemeData.name}" overwritten successfully!`, 'success');
                loadThemes();
                updateOverwriteActiveButton();
            } else {
                showMessage(result.error || 'Failed to update theme', 'error');
            }
        } catch (error) {
            console.error('Error overwriting active theme:', error);
            showMessage('Error overwriting active theme', 'error');
        }
    });

    // Delete theme
    window.deleteTheme = async function(themeId, themeName) {
        if (!confirm(`Delete theme "${themeName}"? This cannot be undone.`)) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('id', themeId);
            formData.append('csrf_token', window.csrfToken);
            
            const response = await fetch('/api/themes.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(formData).toString()
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                showMessage('Theme deleted successfully!', 'success');
                loadThemes();
            } else {
                showMessage(result.error || 'Failed to delete theme', 'error');
            }
        } catch (error) {
            console.error('Error deleting theme:', error);
            showMessage('Error deleting theme', 'error');
        }
    };

    // Export theme as JSON
    window.exportTheme = async function(themeId) {
        try {
            const response = await fetch(`/api/themes.php?action=export&id=${themeId}`);
            const themeData = await response.json();
            
            if (response.ok) {
                const blob = new Blob([JSON.stringify(themeData, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${themeData.name.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_theme.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showMessage('Theme exported!', 'success');
            } else {
                showMessage(themeData.error || 'Failed to export theme', 'error');
            }
        } catch (error) {
            console.error('Error exporting theme:', error);
            showMessage('Error exporting theme', 'error');
        }
    };

    // Load themes when Themes subtab is shown
    const themesSubtabBtn = document.querySelector('[data-subtab="themes"]');
    if (themesSubtabBtn) {
        themesSubtabBtn.addEventListener('click', function() {
            // Small delay to ensure subtab is visible
            setTimeout(() => {
                if (document.getElementById('themesContainer')) {
                    loadThemes();
                }
            }, 100);
        });
    }

    // ===== COMPACT COLOR SCHEME FUNCTIONS =====
    
    // Toggle collapsible color sections
    window.toggleColorSection = function(header) {
        const toggle = header.querySelector('.color-section-toggle');
        const body = header.nextElementSibling;
        
        if (body.classList.contains('collapsed')) {
            // Expand
            body.classList.remove('collapsed');
            toggle.classList.remove('collapsed');
        } else {
            // Collapse
            body.classList.add('collapsed');
            toggle.classList.add('collapsed');
        }
    };

    // Sync color picker with hex input
    function setupColorSync(colorId, hexId, previewCallback) {
        const colorInput = document.getElementById(colorId);
        const hexInput = document.getElementById(hexId);
        
        if (!colorInput || !hexInput) return;
        
        // Update hex when color picker changes
        colorInput.addEventListener('input', function() {
            const value = this.value.toUpperCase();
            hexInput.value = value;
            if (previewCallback) previewCallback(value);
        });
        
        // Update color picker when hex changes
        hexInput.addEventListener('input', function() {
            let value = this.value.trim();
            // Add # if missing
            if (value && !value.startsWith('#')) {
                value = '#' + value;
            }
            // Validate hex color
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                colorInput.value = value;
                if (previewCallback) previewCallback(value);
            }
        });
        
        // Format on blur
        hexInput.addEventListener('blur', function() {
            let value = this.value.trim().toUpperCase();
            if (value && !value.startsWith('#')) {
                value = '#' + value;
            }
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                this.value = value;
            } else if (colorInput.value) {
                // Reset to color picker value if invalid
                this.value = colorInput.value.toUpperCase();
            }
        });
    }

    // Setup all color sync pairs
    function initializeColorSync() {
        // Main theme colors
        setupColorSync('primaryColor', 'primaryColorHex');
        setupColorSync('navbarColor', 'navbarColorHex');
        setupColorSync('backgroundColor', 'backgroundColorHex');
        setupColorSync('accentColor', 'accentColorHex');
        
        // Text colors
        setupColorSync('textPrimary', 'textPrimaryHex');
        setupColorSync('textSecondary', 'textSecondaryHex');
        setupColorSync('textMuted', 'textMutedHex');
        setupColorSync('textDisabled', 'textDisabledHex');
        setupColorSync('textInverse', 'textInverseHex');
        setupColorSync('linkColor', 'linkColorHex');
        
        // Button colors
        setupColorSync('buttonPrimaryBg', 'buttonPrimaryBgHex');
        setupColorSync('buttonPrimaryText', 'buttonPrimaryTextHex');
        setupColorSync('buttonSecondaryBg', 'buttonSecondaryBgHex');
        setupColorSync('buttonSecondaryText', 'buttonSecondaryTextHex');
        setupColorSync('buttonDangerBg', 'buttonDangerBgHex');
        setupColorSync('buttonDangerText', 'buttonDangerTextHex');
        setupColorSync('buttonSuccessBg', 'buttonSuccessBgHex');
        setupColorSync('buttonSuccessText', 'buttonSuccessTextHex');
        setupColorSync('unsavedChangesGlowColor', 'unsavedChangesGlowColorHex');
        
        // Role badge colors with live preview
        setupColorSync('roleStaffBg', 'roleStaffBgHex', (bg) => {
            updateRoleBadgePreview('staffBadgePreview', bg, document.getElementById('roleStaffText').value);
        });
        setupColorSync('roleStaffText', 'roleStaffTextHex', (text) => {
            updateRoleBadgePreview('staffBadgePreview', document.getElementById('roleStaffBg').value, text);
        });
        
        setupColorSync('roleMaintenanceBg', 'roleMaintenanceBgHex', (bg) => {
            updateRoleBadgePreview('maintenanceBadgePreview', bg, document.getElementById('roleMaintenanceText').value);
        });
        setupColorSync('roleMaintenanceText', 'roleMaintenanceTextHex', (text) => {
            updateRoleBadgePreview('maintenanceBadgePreview', document.getElementById('roleMaintenanceBg').value, text);
        });
        
        setupColorSync('roleMaintenanceDirectorBg', 'roleMaintenanceDirectorBgHex', (bg) => {
            updateRoleBadgePreview('directorBadgePreview', bg, document.getElementById('roleMaintenanceDirectorText').value);
        });
        setupColorSync('roleMaintenanceDirectorText', 'roleMaintenanceDirectorTextHex', (text) => {
            updateRoleBadgePreview('directorBadgePreview', document.getElementById('roleMaintenanceDirectorBg').value, text);
        });
        
        setupColorSync('roleManagerBg', 'roleManagerBgHex', (bg) => {
            updateRoleBadgePreview('managerBadgePreview', bg, document.getElementById('roleManagerText').value);
        });
        setupColorSync('roleManagerText', 'roleManagerTextHex', (text) => {
            updateRoleBadgePreview('managerBadgePreview', document.getElementById('roleManagerBg').value, text);
        });
        
        setupColorSync('roleAdminBg', 'roleAdminBgHex', (bg) => {
            updateRoleBadgePreview('adminBadgePreview', bg, document.getElementById('roleAdminText').value);
        });
        setupColorSync('roleAdminText', 'roleAdminTextHex', (text) => {
            updateRoleBadgePreview('adminBadgePreview', document.getElementById('roleAdminBg').value, text);
        });
        
        setupColorSync('roleSuperAdminBg', 'roleSuperAdminBgHex', (bg) => {
            updateRoleBadgePreview('superAdminBadgePreview', bg, document.getElementById('roleSuperAdminText').value);
        });
        setupColorSync('roleSuperAdminText', 'roleSuperAdminTextHex', (text) => {
            updateRoleBadgePreview('superAdminBadgePreview', document.getElementById('roleSuperAdminBg').value, text);
        });
        
        // Success Badge
        setupColorSync('badgeSuccessBg', 'badgeSuccessBgHex', (bg) => {
            updateRoleBadgePreview('successBadgePreview', bg, document.getElementById('badgeSuccessText').value);
        });
        setupColorSync('badgeSuccessText', 'badgeSuccessTextHex', (text) => {
            updateRoleBadgePreview('successBadgePreview', document.getElementById('badgeSuccessBg').value, text);
        });
        
        // System Badge
        setupColorSync('badgeSystemBg', 'badgeSystemBgHex', (bg) => {
            updateRoleBadgePreview('systemBadgePreview', bg, document.getElementById('badgeSystemText').value);
        });
        setupColorSync('badgeSystemText', 'badgeSystemTextHex', (text) => {
            updateRoleBadgePreview('systemBadgePreview', document.getElementById('badgeSystemBg').value, text);
        });
    }

    // Update role badge preview
    function updateRoleBadgePreview(previewId, bgColor, textColor) {
        const preview = document.getElementById(previewId);
        if (preview) {
            preview.style.background = bgColor;
            preview.style.color = textColor;
        }
    }

    // Initialize color sync on Colors tab load
    const colorsSubtabBtn = document.querySelector('[data-subtab="colors"]');
    if (colorsSubtabBtn) {
        colorsSubtabBtn.addEventListener('click', function() {
            setTimeout(() => {
                initializeColorSync();
            }, 100);
        });
    }
    
    // Initialize immediately if on colors tab
    if (document.getElementById('subtab-colors')) {
        setTimeout(() => {
            initializeColorSync();
        }, 500);
    }

    // ============================================
    // ADVANCED SETTINGS (System Configuration)
    // ============================================
    
    let advancedSettingsOriginal = null;
    
    // Load advanced settings
    async function loadAdvancedSettings() {
        try {
            const response = await fetch('/api/system-config.php?action=load');
            const result = await response.json();
            
            if (response.ok && result.success) {
                populateAdvancedSettings(result.config);
                advancedSettingsOriginal = JSON.parse(JSON.stringify(result.config)); // Deep copy
            } else {
                showMessage(result.message || 'Failed to load system configuration', 'error');
            }
        } catch (error) {
            console.error('Error loading advanced settings:', error);
            showMessage('Error loading system configuration', 'error');
        }
    }
    
    // Populate form fields with config data
    function populateAdvancedSettings(config) {
        // Authentication
        document.getElementById('allowLocalUsers').checked = config.auth.allow_local_users;
        document.getElementById('enableGoogleLogin').checked = config.google_oauth?.enabled !== false;
        document.getElementById('enableMicrosoftLogin').checked = config.microsoft_oauth?.enabled || false;
        document.getElementById('requireDomainMatch').checked = config.auth.require_domain_match;
        document.getElementById('allowedDomains').value = config.auth.allowed_domains;
        document.getElementById('sessionTimeout').value = config.auth.session_timeout;
        
        // Initialize section visibility based on checkbox states
        toggleAuthSection('google', config.google_oauth?.enabled !== false);
        toggleAuthSection('microsoft', config.microsoft_oauth?.enabled || false);
        
        // Google OAuth
        document.getElementById('googleClientId').value = config.google_oauth.client_id;
        document.getElementById('googleClientSecret').value = config.google_oauth.client_secret;
        document.getElementById('googleRedirectUri').value = config.google_oauth.redirect_uri;
        
        // Microsoft OAuth
        document.getElementById('microsoftClientId').value = config.microsoft_oauth?.client_id || '';
        document.getElementById('microsoftClientSecret').value = config.microsoft_oauth?.client_secret || '';
        document.getElementById('microsoftTenantId').value = config.microsoft_oauth?.tenant_id || 'common';
        document.getElementById('microsoftRedirectUri').value = config.microsoft_oauth?.redirect_uri || '';
        
        // Google Groups
        const enableGoogleGroupsEl = document.getElementById('enableGoogleGroups');
        if (enableGoogleGroupsEl) {
            enableGoogleGroupsEl.checked = config.google_groups.enabled;
            toggleDependentSection('enableGoogleGroups', 'googleGroupsFields', true); // Initialize visibility
        }
        
        const googleAdminEmailEl = document.getElementById('googleAdminEmail');
        if (googleAdminEmailEl) googleAdminEmailEl.value = config.google_groups.admin_email;
        
        const googleServiceAccountEmailEl = document.getElementById('googleServiceAccountEmail');
        if (googleServiceAccountEmailEl) googleServiceAccountEmailEl.value = config.google_groups.service_account_email;
        
        const googleServiceAccountKeyPathEl = document.getElementById('googleServiceAccountKeyPath');
        if (googleServiceAccountKeyPathEl) googleServiceAccountKeyPathEl.value = config.google_groups.key_path;
        
        // Google Group Role Associations - Convert array back to pipe-separated string
        const googleGroupRoleAssociationsEl = document.getElementById('googleGroupRoleAssociations');
        if (googleGroupRoleAssociationsEl) {
            const roleAssociations = config.google_groups.role_associations || [];
            // Convert from array format [{group: 'x', role: 'y'}] to string "x:y|a:b"
            const associationString = roleAssociations
                .map(assoc => `${assoc.group}:${assoc.role}`)
                .join('|');
            googleGroupRoleAssociationsEl.value = associationString;
        }
        
        // Database
        document.getElementById('dbHost').value = config.database.host;
        document.getElementById('dbName').value = config.database.name;
        document.getElementById('dbUser').value = config.database.user;
        document.getElementById('dbPassword').value = ''; // Don't populate password
        document.getElementById('dbPassword').placeholder = config.database.password ? '••••••••' : '';
        
        // Application
        document.getElementById('appUrl').value = config.app.url;
        document.getElementById('appEnvironment').value = config.app.environment;
        document.getElementById('debugMode').checked = config.app.debug_mode;
        document.getElementById('maxUploadSize').value = config.app.max_upload_size;
        document.getElementById('maintenanceMode').checked = config.app.maintenance_mode;
        
        // Email
        document.getElementById('smtpHost').value = config.email.smtp_host;
        document.getElementById('smtpPort').value = config.email.smtp_port;
        document.getElementById('smtpUsername').value = config.email.smtp_username;
        document.getElementById('smtpPassword').value = ''; // Don't populate password
        document.getElementById('smtpPassword').placeholder = config.email.smtp_password ? '••••••••' : '';
        document.getElementById('smtpFromEmail').value = config.email.from_email;
        document.getElementById('smtpFromName').value = config.email.from_name;
    }
    
    // Gather current form values
    function gatherAdvancedSettings() {
        return {
            auth: {
                allow_local_users: document.getElementById('allowLocalUsers').checked,
                require_domain_match: document.getElementById('requireDomainMatch').checked,
                allowed_domains: document.getElementById('allowedDomains').value,
                session_timeout: parseInt(document.getElementById('sessionTimeout').value)
            },
            google_oauth: {
                enabled: document.getElementById('enableGoogleLogin').checked,
                client_id: document.getElementById('googleClientId').value,
                client_secret: document.getElementById('googleClientSecret').value,
                redirect_uri: document.getElementById('googleRedirectUri').value
            },
            microsoft_oauth: {
                enabled: document.getElementById('enableMicrosoftLogin').checked,
                client_id: document.getElementById('microsoftClientId').value,
                client_secret: document.getElementById('microsoftClientSecret').value,
                tenant_id: document.getElementById('microsoftTenantId').value,
                redirect_uri: document.getElementById('microsoftRedirectUri').value
            },
            google_groups: {
                enabled: document.getElementById('enableGoogleGroups')?.checked || false,
                service_account_email: document.getElementById('googleServiceAccountEmail')?.value || '',
                admin_email: document.getElementById('googleAdminEmail')?.value || '',
                key_path: document.getElementById('googleServiceAccountKeyPath')?.value || '',
                // Parse the text field into array format for API
                // Keep roles as-is (comma-separated if multiple)
                role_associations: (function() {
                    const field = document.getElementById('googleGroupRoleAssociations');
                    if (!field || !field.value) return [];
                    
                    return field.value.split('|')
                        .map(mapping => mapping.trim())
                        .filter(mapping => mapping.includes(':'))
                        .map(mapping => {
                            const [group, role] = mapping.split(':', 2).map(s => s.trim());
                            return { group, role }; // role might be "staff,manager,admin"
                        });
                })()
            },
            database: {
                host: document.getElementById('dbHost').value,
                name: document.getElementById('dbName').value,
                user: document.getElementById('dbUser').value,
                password: document.getElementById('dbPassword').value || '********'
            },
            app: {
                url: document.getElementById('appUrl').value,
                environment: document.getElementById('appEnvironment').value,
                debug_mode: document.getElementById('debugMode').checked,
                max_upload_size: parseInt(document.getElementById('maxUploadSize').value),
                maintenance_mode: document.getElementById('maintenanceMode').checked
            },
            email: {
                smtp_host: document.getElementById('smtpHost').value,
                smtp_port: parseInt(document.getElementById('smtpPort').value),
                smtp_username: document.getElementById('smtpUsername').value,
                smtp_password: document.getElementById('smtpPassword').value || '********',
                from_email: document.getElementById('smtpFromEmail').value,
                from_name: document.getElementById('smtpFromName').value
            }
        };
    }
    
    // Save advanced settings
    const saveAdvancedBtn = document.getElementById('saveAdvancedSettings');
    if (saveAdvancedBtn) {
        saveAdvancedBtn.addEventListener('click', async function() {
            if (!confirm('⚠️ Changing system configuration can break the application.\n\nAre you sure you want to save these changes?')) {
                return;
            }
            
            const config = gatherAdvancedSettings();
            
            try {
                const response = await fetch('/api/system-config.php?action=save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.csrfToken
                    },
                    body: JSON.stringify(config)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    showMessage(result.message, 'success');
                    advancedSettingsOriginal = JSON.parse(JSON.stringify(config)); // Update original
                    
                    // If maintenance mode was enabled, warn user
                    if (config.app.maintenance_mode) {
                        showMessage('⚠️ Maintenance Mode is now ACTIVE. Non-admin users cannot access the system.', 'warning');
                    }
                } else {
                    showMessage(result.message || 'Failed to save configuration', 'error');
                }
            } catch (error) {
                console.error('Error saving advanced settings:', error);
                showMessage('Error saving system configuration', 'error');
            }
        });
    }
    
    // Reload advanced settings
    const reloadAdvancedBtn = document.getElementById('reloadAdvancedSettings');
    if (reloadAdvancedBtn) {
        reloadAdvancedBtn.addEventListener('click', function() {
            if (confirm('Discard all unsaved changes and reload settings from .env file?')) {
                loadAdvancedSettings();
            }
        });
    }
    
    // Test database connection
    const testDbBtn = document.getElementById('testDbConnection');
    if (testDbBtn) {
        testDbBtn.addEventListener('click', async function() {
            const dbTestResult = document.getElementById('dbTestResult');
            dbTestResult.innerHTML = '<em>Testing connection...</em>';
            
            const config = {
                host: document.getElementById('dbHost').value,
                name: document.getElementById('dbName').value,
                user: document.getElementById('dbUser').value,
                password: document.getElementById('dbPassword').value || advancedSettingsOriginal?.database?.password
            };
            
            if (!config.host || !config.name || !config.user) {
                dbTestResult.innerHTML = '<span style="color: #DC2626;">❌ Please fill in all database fields</span>';
                return;
            }
            
            try {
                const response = await fetch('/api/system-config.php?action=test-db', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(config)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    dbTestResult.innerHTML = `<span style="color: #16A34A;">✅ Connected successfully to <strong>${result.database}</strong>! MySQL ${result.version}</span>`;
                } else {
                    dbTestResult.innerHTML = `<span style="color: #DC2626;">❌ ${result.message}</span>`;
                }
            } catch (error) {
                console.error('Error testing database:', error);
                dbTestResult.innerHTML = '<span style="color: #DC2626;">❌ Connection test failed</span>';
            }
        });
    }
    
    // Clear all sessions
    const clearSessionsBtn = document.getElementById('clearAllSessions');
    if (clearSessionsBtn) {
        clearSessionsBtn.addEventListener('click', async function() {
            if (!confirm('⚠️ This will log out ALL users, including yourself.\n\nYou will need to log back in. Continue?')) {
                return;
            }
            
            try {
                const response = await fetch('/api/system-config.php?action=clear-sessions', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`${result.message}\n\nYou will now be redirected to the login page.`);
                    window.location.href = '/logout.php';
                } else {
                    showMessage(result.message || 'Failed to clear sessions', 'error');
                }
            } catch (error) {
                console.error('Error clearing sessions:', error);
                showMessage('Error clearing sessions', 'error');
            }
        });
    }
    
    // Regenerate .env file
    const regenerateEnvBtn = document.getElementById('regenerateEnv');
    if (regenerateEnvBtn) {
        regenerateEnvBtn.addEventListener('click', async function() {
            if (!confirm('⚠️ This will regenerate the .env file from current database settings.\n\nThe current .env will be backed up. Continue?')) {
                return;
            }
            
            try {
                const response = await fetch('/api/system-config.php?action=regenerate-env', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message, 'success');
                } else {
                    showMessage(result.message || 'Failed to regenerate .env', 'error');
                }
            } catch (error) {
                console.error('Error regenerating .env:', error);
                showMessage('Error regenerating .env file', 'error');
            }
        });
    }
    
    // ============================================
    // ROLE ASSOCIATIONS MANAGEMENT
    // ============================================
    
    let roleAssociationsData = [];
    
    function renderRoleAssociations() {
        const container = document.getElementById('roleAssociationsContainer');
        if (!container) return;
        
        if (roleAssociationsData.length === 0) {
            container.innerHTML = '<p style="color: var(--text-muted); font-style: italic; margin: 0;">No group associations configured. Click "Add Group Association" below.</p>';
            return;
        }
        
        container.innerHTML = roleAssociationsData.map((assoc, index) => `
            <div style="display: grid; grid-template-columns: 1fr 150px auto; gap: 0.5rem; margin-bottom: 0.5rem; align-items: center;">
                <input type="text" 
                       placeholder="students@example.com" 
                       value="${assoc.group || ''}" 
                       onchange="updateRoleAssociation(${index}, 'group', this.value)"
                       style="margin: 0;">
                <select onchange="updateRoleAssociation(${index}, 'role', this.value)" style="margin: 0;">
                    <option value="viewer" ${assoc.role === 'viewer' ? 'selected' : ''}>Viewer</option>
                    <option value="user" ${assoc.role === 'user' ? 'selected' : ''}>User</option>
                    <option value="driver" ${assoc.role === 'driver' ? 'selected' : ''}>Driver</option>
                    <option value="admin" ${assoc.role === 'admin' ? 'selected' : ''}>Admin</option>
                    <option value="super_admin" ${assoc.role === 'super_admin' ? 'selected' : ''}>Super Admin</option>
                </select>
                <button type="button" onclick="removeRoleAssociation(${index})" class="btn btn-sm btn-danger" style="margin: 0; padding: 0.25rem 0.5rem;">
                    ✕
                </button>
            </div>
        `).join('');
    }
    
    window.updateRoleAssociation = function(index, field, value) {
        roleAssociationsData[index][field] = value;
    };
    
    window.removeRoleAssociation = function(index) {
        roleAssociationsData.splice(index, 1);
        renderRoleAssociations();
    };
    
    const addRoleAssociationBtn = document.getElementById('addRoleAssociation');
    if (addRoleAssociationBtn) {
        addRoleAssociationBtn.addEventListener('click', function() {
            roleAssociationsData.push({ group: '', role: 'viewer' });
            renderRoleAssociations();
        });
    }
    
    // ============================================
    // SERVICE ACCOUNT UPLOAD
    // ============================================
    
    const serviceAccountUpload = document.getElementById('serviceAccountUpload');
    if (serviceAccountUpload) {
        serviceAccountUpload.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            if (!file.name.endsWith('.json')) {
                showMessage('Please upload a JSON file', 'error');
                e.target.value = '';
                return;
            }
            
            const formData = new FormData();
            formData.append('serviceAccountFile', file);
            
            try {
                showMessage('Uploading service account...', 'info');
                
                const response = await fetch('/api/system-config.php?action=upload-service-account', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message, 'success');
                    // Update form fields
                    document.getElementById('googleServiceAccountKeyPath').value = result.path;
                    document.getElementById('googleServiceAccountEmail').value = result.service_account_email;
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                console.error('Error uploading service account:', error);
                showMessage('Error uploading file', 'error');
            }
            
            // Clear file input
            e.target.value = '';
        });
    }
    
    // ============================================
    // TEST SMTP
    // ============================================
    
    const testSmtpBtn = document.getElementById('testSmtpConfig');
    if (testSmtpBtn) {
        testSmtpBtn.addEventListener('click', async function() {
            const resultDiv = document.getElementById('smtpTestResult');
            resultDiv.innerHTML = '<em style="color: var(--text-muted);">Testing SMTP connection...</em>';
            
            const testEmail = document.getElementById('testEmailAddress').value;
            
            const config = {
                host: document.getElementById('smtpHost').value,
                port: parseInt(document.getElementById('smtpPort').value) || 587,
                username: document.getElementById('smtpUsername').value,
                password: document.getElementById('smtpPassword').value || (advancedSettingsOriginal?.email?.smtp_password !== '********' ? '********' : ''),
                test_email: testEmail
            };
            
            if (!config.host || !config.username) {
                resultDiv.innerHTML = '<span style="color: #DC2626;">❌ Please configure SMTP host and username</span>';
                return;
            }
            
            try {
                const response = await fetch('/api/system-config.php?action=test-smtp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(config)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `<span style="color: #16A34A;">✅ ${result.message}</span>`;
                } else {
                    resultDiv.innerHTML = `<span style="color: #DC2626;">❌ ${result.message}</span>`;
                }
            } catch (error) {
                console.error('Error testing SMTP:', error);
                resultDiv.innerHTML = '<span style="color: #DC2626;">❌ Connection test failed</span>';
            }
        });
    }
    
    // ============================================
    // SEND TEST EMAIL
    // ============================================
    
    const sendTestEmailBtn = document.getElementById('sendTestEmail');
    if (sendTestEmailBtn) {
        sendTestEmailBtn.addEventListener('click', async function() {
            const resultDiv = document.getElementById('smtpTestResult');
            resultDiv.innerHTML = '<em style="color: var(--text-muted);">Sending test email...</em>';
            
            const testEmail = document.getElementById('testEmailAddress').value;
            
            if (!testEmail) {
                resultDiv.innerHTML = '<span style="color: #DC2626;">❌ Please enter an email address</span>';
                return;
            }
            
            const config = {
                host: document.getElementById('smtpHost').value,
                port: parseInt(document.getElementById('smtpPort').value) || 587,
                username: document.getElementById('smtpUsername').value,
                password: document.getElementById('smtpPassword').value || (advancedSettingsOriginal?.email?.smtp_password !== '********' ? '********' : ''),
                from_email: document.getElementById('smtpFromEmail').value,
                from_name: document.getElementById('smtpFromName').value,
                test_email: testEmail
            };
            
            if (!config.host || !config.username || !config.from_email) {
                resultDiv.innerHTML = '<span style="color: #DC2626;">❌ Please configure SMTP host, username, and from email</span>';
                return;
            }
            
            try {
                const response = await fetch('/api/system-config.php?action=send-test-email', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(config)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `<span style="color: #16A34A;">${result.message}</span>`;
                } else {
                    resultDiv.innerHTML = `<span style="color: #DC2626;">❌ ${result.message}</span>`;
                }
            } catch (error) {
                console.error('Error sending test email:', error);
                resultDiv.innerHTML = '<span style="color: #DC2626;">❌ Failed to send test email</span>';
            }
        });
    }
    
    // ============================================
    // TEST GOOGLE GROUPS
    // ============================================
    
    const testGoogleGroupsBtn = document.getElementById('testGoogleGroups');
    if (testGoogleGroupsBtn) {
        testGoogleGroupsBtn.addEventListener('click', async function() {
            const resultDiv = document.getElementById('googleGroupsTestResult');
            resultDiv.innerHTML = '<em style="color: var(--text-muted);">Testing Google Groups connection...</em>';
            
            const config = {
                key_path: document.getElementById('googleServiceAccountKeyPath').value,
                admin_email: document.getElementById('googleAdminEmail').value,
                test_group: document.getElementById('testGroupEmail').value
            };
            
            if (!config.key_path || !config.admin_email) {
                resultDiv.innerHTML = '<span style="color: #DC2626;">❌ Please configure service account key and admin email</span>';
                return;
            }
            
            try {
                const response = await fetch('/api/system-config.php?action=test-google-groups', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(config)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `<span style="color: #16A34A;">✅ ${result.message}</span>`;
                } else {
                    resultDiv.innerHTML = `<span style="color: #DC2626;">❌ ${result.message}</span>`;
                }
            } catch (error) {
                console.error('Error testing Google Groups:', error);
                resultDiv.innerHTML = '<span style="color: #DC2626;">❌ Connection test failed</span>';
            }
        });
    }
    
    // Load advanced settings when Advanced subtab is clicked
    const advancedSubtabBtn = document.querySelector('[data-subtab="advanced"]');
    if (advancedSubtabBtn) {
        advancedSubtabBtn.addEventListener('click', function() {
            if (!advancedSettingsOriginal) {
                loadAdvancedSettings();
            }
            
            // Check if we should open a specific section (from maintenance banner click)
            const openSection = localStorage.getItem('openAdvancedSection');
            if (openSection && openSection === 'app') {
                localStorage.removeItem('openAdvancedSection');
                setTimeout(() => {
                    const appHeader = document.getElementById('appSettingsHeader');
                    if (appHeader) {
                        const toggle = appHeader.querySelector('.color-section-toggle');
                        // Only click if it's collapsed
                        if (toggle && toggle.classList.contains('collapsed')) {
                            appHeader.click();
                        }
                        // Scroll to maintenance mode checkbox
                        setTimeout(() => {
                            const maintenanceCheckbox = document.getElementById('maintenanceMode');
                            if (maintenanceCheckbox) {
                                maintenanceCheckbox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                // Briefly highlight it
                                maintenanceCheckbox.parentElement.style.background = '#FEF3C7';
                                setTimeout(() => {
                                    maintenanceCheckbox.parentElement.style.background = '';
                                }, 2000);
                            }
                        }, 400);
                    }
                }, 500);
            }
        });
    }
});

// ============================================================================
// Google Groups Modal Management
// ============================================================================

/**
 * Open the Google Groups mapping modal
 */
function openGoogleGroupModal() {
    const modal = document.getElementById('googleGroupModal');
    const form = document.getElementById('googleGroupForm');
    
    if (modal && form) {
        // Reset form
        form.reset();
        
        // Show modal
        modal.style.display = 'flex';
    }
}

/**
 * Parse pipe-separated list into array of objects
 * Format: email:role1,role2,role3|email2:role1
 */
function parseGoogleGroups(pipeString) {
    if (!pipeString || pipeString.trim() === '') {
        return [];
    }
    
    return pipeString.split('|')
        .map(mapping => mapping.trim())
        .filter(mapping => mapping.includes(':'))
        .map(mapping => {
            const [email, rolesStr] = mapping.split(':').map(s => s.trim());
            const roles = rolesStr.split(',').map(r => r.trim()).filter(r => r);
            return { email, roles };
        });
}

/**
 * Convert array of objects back to pipe-separated string
 * Format: email:role1,role2,role3|email2:role1
 */
function serializeGoogleGroups(groups) {
    return groups
        .map(g => `${g.email}:${g.roles.join(',')}`)
        .join('|');
}

// Handle Google Groups form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('googleGroupForm');
    const modal = document.getElementById('googleGroupModal');
    
    if (form && modal) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('googleGroupEmail').value.trim();
            const roleCheckboxes = document.querySelectorAll('input[name="googleGroupRoles"]:checked');
            
            if (!email) {
                showMessage('Please enter a group email', 'error');
                return;
            }
            
            if (roleCheckboxes.length === 0) {
                showMessage('Please select at least one role', 'error');
                return;
            }
            
            const roles = Array.from(roleCheckboxes).map(cb => cb.value);
            
            // Get current associations field
            const associationsField = document.getElementById('googleGroupRoleAssociations');
            if (!associationsField) {
                showMessage('Could not find associations field', 'error');
                return;
            }
            
            // Parse existing associations
            const currentGroups = parseGoogleGroups(associationsField.value);
            
            // Check if this email already exists
            const existingIndex = currentGroups.findIndex(g => g.email.toLowerCase() === email.toLowerCase());
            
            if (existingIndex >= 0) {
                // Update existing
                currentGroups[existingIndex].roles = roles;
                showMessage(`Updated ${email} to roles: ${roles.join(', ')}`, 'success');
            } else {
                // Add new
                currentGroups.push({ email, roles });
                showMessage(`Added ${email} with roles: ${roles.join(', ')}`, 'success');
            }
            
            // Update the field
            associationsField.value = serializeGoogleGroups(currentGroups);
            
            // Close modal
            modal.style.display = 'none';
            form.reset();
        });
    }
});

// Make function globally available
window.openGoogleGroupModal = openGoogleGroupModal;

// ============================================================================
// Microsoft Groups Modal Management (Same pattern as Google Groups)
// ============================================================================

/**
 * Open the Microsoft Groups mapping modal
 */
function openMicrosoftGroupModal() {
    const modal = document.getElementById('microsoftGroupModal');
    const form = document.getElementById('microsoftGroupForm');
    
    if (modal && form) {
        form.reset();
        modal.style.display = 'flex';
    }
}

// Handle Microsoft Groups form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('microsoftGroupForm');
    const modal = document.getElementById('microsoftGroupModal');
    
    if (form && modal) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const groupId = document.getElementById('microsoftGroupId').value.trim();
            const roleCheckboxes = document.querySelectorAll('input[name="microsoftGroupRoles"]:checked');
            
            if (!groupId) {
                showMessage('Please enter an Azure AD Group ID', 'error');
                return;
            }
            
            if (roleCheckboxes.length === 0) {
                showMessage('Please select at least one role', 'error');
                return;
            }
            
            const roles = Array.from(roleCheckboxes).map(cb => cb.value);
            
            // Get current associations field
            const associationsField = document.getElementById('microsoftGroupRoleAssociations');
            if (!associationsField) {
                showMessage('Could not find associations field', 'error');
                return;
            }
            
            // Parse existing associations (same format as Google Groups)
            const currentGroups = parseGoogleGroups(associationsField.value); // Reuse parser
            
            // Check if this group ID already exists
            const existingIndex = currentGroups.findIndex(g => g.email.toLowerCase() === groupId.toLowerCase());
            
            if (existingIndex >= 0) {
                // Update existing
                currentGroups[existingIndex].roles = roles;
                showMessage(`Updated ${groupId} to roles: ${roles.join(', ')}`, 'success');
            } else {
                // Add new (use 'email' key to match parser format)
                currentGroups.push({ email: groupId, roles });
                showMessage(`Added ${groupId} with roles: ${roles.join(', ')}`, 'success');
            }
            
            // Update the field (reuse serializer)
            associationsField.value = serializeGoogleGroups(currentGroups);
            
            // Close modal
            modal.style.display = 'none';
            form.reset();
        });
    }
});

// Make function globally available
window.openMicrosoftGroupModal = openMicrosoftGroupModal;

// ============================================================================
// PACKAGE MANAGER
// ============================================================================

// Load Package Manager when tab is opened
document.addEventListener('DOMContentLoaded', function() {
    const packageTab = document.querySelector('[data-tab="packages"]');
    
    if (packageTab) {
        packageTab.addEventListener('click', function() {
            loadInstalledPackages();
            loadAvailablePackages();
            loadPackageUpdates();
        });
    }
    
    // Upload button
    const uploadBtn = document.getElementById('uploadPackageBtn');
    const fileInput = document.getElementById('packageFileInput');
    const dropzone = document.getElementById('uploadDropzone');
    
    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', () => fileInput.click());
        
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                uploadPackageFile(this.files[0]);
            }
        });
    }
    
    // Drag and drop
    if (dropzone) {
        dropzone.addEventListener('click', () => fileInput.click());
        
        dropzone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#4CAF50';
            this.style.background = '#e8f5e9';
        });
        
        dropzone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#dee2e6';
            this.style.background = '#f8f9fa';
        });
        
        dropzone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#dee2e6';
            this.style.background = '#f8f9fa';
            
            const file = e.dataTransfer.files[0];
            if (file && file.name.endsWith('.hubpkg')) {
                uploadPackageFile(file);
            } else {
                showMessage('Please upload a .hubpkg file', 'error');
            }
        });
    }
    
    // Check updates button
    const checkUpdatesBtn = document.getElementById('checkUpdatesBtn');
    if (checkUpdatesBtn) {
        checkUpdatesBtn.addEventListener('click', loadPackageUpdates);
    }
});

// Upload package file
async function uploadPackageFile(file) {
    const progressDiv = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    const dropzone = document.getElementById('uploadDropzone');
    
    try {
        // Show progress
        dropzone.style.display = 'none';
        progressDiv.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = 'Uploading...';
        
        const formData = new FormData();
        formData.append('package', file);
        formData.append('csrf_token', window.csrfToken);
        
        // Simulate progress (since fetch doesn't support upload progress easily)
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 5;
            if (progress > 90) progress = 90;
            progressBar.style.width = progress + '%';
        }, 100);
        
        const response = await fetch('/api/packages.php', {
            method: 'POST',
            body: formData
        });
        
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        const result = await response.json();
        
        if (result.success) {
            progressText.textContent = 'Upload complete!';
            showMessage(result.message, result.validation.can_install ? 'success' : 'warning');
            
            // Show validation results
            if (!result.validation.can_install) {
                setTimeout(() => {
                    showValidationModal(result);
                }, 500);
            }
            
            // Reload available packages
            setTimeout(() => {
                dropzone.style.display = 'block';
                progressDiv.style.display = 'none';
                loadAvailablePackages();
            }, 2000);
        } else {
            throw new Error(result.error || 'Upload failed');
        }
        
    } catch (error) {
        progressDiv.style.display = 'none';
        dropzone.style.display = 'block';
        showMessage('Upload failed: ' + error.message, 'error');
    }
}

// Load installed packages
async function loadInstalledPackages() {
    const container = document.getElementById('installedPackagesTable');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="text-align: center; padding: 2rem;">Loading...</p>';
        
        const response = await fetch('/api/packages.php?action=installed');
        const result = await response.json();
        
        if (!result.success) throw new Error(result.error);
        
        const packages = result.packages;
        
        if (packages.length === 0) {
            container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #6c757d;">No packages installed yet.</p>';
            return;
        }
        
        let html = '<table class="data-table"><thead><tr>';
        html += '<th>Package Name</th>';
        html += '<th>Version</th>';
        html += '<th>Installed</th>';
        html += '<th>Status</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        packages.forEach(pkg => {
            const hasUpdate = pkg.latest_available_version && 
                             pkg.latest_available_version !== pkg.installed_version;
            const isEnabled = pkg.is_active == 1;
            
            html += '<tr>';
            html += `<td><strong>${escapeHtml(pkg.display_name || pkg.package_id)}</strong><br><small style="color: #6c757d;">${escapeHtml(pkg.package_id)}</small></td>`;
            html += `<td><span class="badge badge-info">${escapeHtml(pkg.installed_version)}</span></td>`;
            html += `<td>${formatDate(pkg.installed_at)}</td>`;
            html += '<td>';
            
            if (isEnabled) {
                html += '<span class="badge badge-success">✓ Enabled</span>';
            } else {
                html += '<span class="badge badge-secondary">○ Disabled</span>';
            }
            
            if (hasUpdate) {
                html += '<br><small style="color: #ff9800;">Update available!</small>';
            }
            
            html += '</td>';
            html += '<td>';
            
            // Enable/Disable toggle
            if (isEnabled) {
                html += `<button class="btn btn-sm btn-secondary" onclick="toggleSectionStatus('${pkg.package_id}', '${escapeHtml(pkg.display_name)}', false)">
                    <i class="bi bi-pause-circle"></i> Disable
                </button>`;
            } else {
                html += `<button class="btn btn-sm btn-success" onclick="toggleSectionStatus('${pkg.package_id}', '${escapeHtml(pkg.display_name)}', true)">
                    <i class="bi bi-play-circle"></i> Enable
                </button>`;
            }
            
            if (hasUpdate) {
                html += `<button class="btn btn-sm btn-warning" onclick="upgradePackage('${pkg.package_id}', '${escapeHtml(pkg.latest_available_version)}')">
                    <i class="bi bi-arrow-up-circle"></i> Upgrade
                </button>`;
            }
            
            html += `<button class="btn btn-sm btn-danger" onclick="uninstallPackagePrompt('${pkg.package_id}', '${escapeHtml(pkg.display_name)}')">
                Uninstall
            </button>`;
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
        
    } catch (error) {
        container.innerHTML = `<p style="text-align: center; padding: 2rem; color: #d32f2f;">Error: ${error.message}</p>`;
    }
}

// Load available packages
async function loadAvailablePackages() {
    const container = document.getElementById('availablePackagesTable');
    if (!container) {
        console.error('❌ availablePackagesTable container not found!');
        return;
    }
    
    console.log('📦 Loading available packages...');
    
    try {
        container.innerHTML = '<p style="text-align: center; padding: 2rem;">Loading...</p>';
        
        const response = await fetch('/api/packages.php');
        const result = await response.json();
        
        console.log('📦 Packages API response:', result);
        
        if (!result.success) throw new Error(result.error);
        
        const packages = result.packages;
        console.log('📦 Found', packages.length, 'packages');
        
        if (packages.length === 0) {
            container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #6c757d;">No packages available. Upload a .hubpkg file to get started.</p>';
            return;
        }
        
        let html = '<table class="data-table"><thead><tr>';
        html += '<th>Package Name</th>';
        html += '<th>Version</th>';
        html += '<th>Status</th>';
        html += '<th>Uploaded</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        packages.forEach(pkg => {
            const canInstall = pkg.can_install;
            const isInstalled = pkg.is_installed;
            
            html += '<tr>';
            html += `<td><strong>${escapeHtml(pkg.display_name)}</strong><br><small style="color: #6c757d;">${escapeHtml(pkg.package_id)}</small></td>`;
            html += `<td><span class="badge badge-info">${escapeHtml(pkg.version)}</span></td>`;
            html += '<td>';
            
            if (isInstalled) {
                html += '<span class="badge badge-success">Installed</span>';
            } else if (pkg.validation_status === 'pending' || !pkg.validation_status) {
                html += '<span class="badge badge-warning">⏳ Awaiting Validation</span>';
            } else if (canInstall) {
                html += '<span class="badge badge-success">✓ Validated - Ready</span>';
            } else {
                html += '<span class="badge badge-danger">✗ Validation Failed</span>';
            }
            
            html += '</td>';
            html += `<td>${formatDate(pkg.uploaded_at)}</td>`;
            html += '<td>';
            
            // Validate button (primary action for unvalidated packages)
            if (!isInstalled && (pkg.validation_status === 'pending' || !pkg.validation_status)) {
                html += `<button class="btn btn-sm btn-primary" onclick="validatePackage(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                    Validate Package
                </button>`;
            }
            // View Report button (for already validated packages)
            else if (!isInstalled) {
                html += `<button class="btn btn-sm ${canInstall ? 'btn-success' : 'btn-warning'}" onclick="showValidationDetails(${pkg.id})">
                    ${canInstall ? 'View Report' : 'View Issues'}
                </button>`;
            }
            
            if (!isInstalled && canInstall && pkg.validation_status !== 'pending') {
                html += `<button class="btn btn-sm btn-primary" onclick="installPackage(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                    Install
                </button>`;
            } else if (isInstalled && pkg.has_update) {
                html += `<button class="btn btn-sm btn-warning" onclick="upgradePackageById(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                    Upgrade
                </button>`;
            }
            
            if (!isInstalled) {
                html += `<button class="btn btn-sm btn-danger" onclick="deletePackage(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                    Delete
                </button>`;
            }
            
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
        console.log('✅ Packages table rendered with', packages.length, 'packages');
        
        // Update notification badge for packages needing validation
        const needingValidation = packages.filter(pkg => !pkg.is_installed && (!pkg.validation_status || pkg.validation_status === 'pending')).length;
        updatePackageBadge('availablePackagesBadge', needingValidation);
        
    } catch (error) {
        console.error('❌ Error loading packages:', error);
        container.innerHTML = `<p style="text-align: center; padding: 2rem; color: #d32f2f;">Error: ${error.message}</p>`;
    }
}

// Update package notification badges
function updatePackageBadge(badgeId, count) {
    const badge = document.getElementById(badgeId);
    if (!badge) return;
    
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-block';
    } else {
        badge.style.display = 'none';
    }
}

// Load package updates
async function loadPackageUpdates() {
    const container = document.getElementById('packageUpdatesTable');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="text-align: center; padding: 2rem;">Checking for updates...</p>';
        
        const response = await fetch('/api/packages.php?action=updates');
        const result = await response.json();
        
        if (!result.success) throw new Error(result.error);
        
        const updates = result.updates;
        
        if (updates.length === 0) {
            container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #4CAF50;">✓ All packages are up to date!</p>';
            return;
        }
        
        let html = '<table class="data-table"><thead><tr>';
        html += '<th>Package Name</th>';
        html += '<th>Current Version</th>';
        html += '<th>Available Version</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        updates.forEach(update => {
            html += '<tr>';
            html += `<td><strong>${escapeHtml(update.display_name)}</strong><br><small style="color: #6c757d;">${escapeHtml(update.package_id)}</small></td>`;
            html += `<td><span class="badge badge-secondary">${escapeHtml(update.current_version)}</span></td>`;
            html += `<td><span class="badge badge-success">${escapeHtml(update.available_version)}</span></td>`;
            html += `<td><button class="btn btn-sm btn-primary" onclick="upgradePackage('${update.package_id}', '${escapeHtml(update.available_version)}')">
                <i class="bi bi-arrow-up-circle"></i> Upgrade Now
            </button></td>`;
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
        
        // Update notification badge for available updates
        updatePackageBadge('updatesBadge', updates.length);
        
    } catch (error) {
        container.innerHTML = `<p style="text-align: center; padding: 2rem; color: #d32f2f;">Error: ${error.message}</p>`;
        updatePackageBadge('updatesBadge', 0);
    }
}

// Install package
async function installPackage(packageId, packageName) {
    if (!confirm(`Install package "${packageName}"?\n\nThis will create a new section with all defined fields and permissions.`)) {
        return;
    }
    
    try {
        showMessage('Installing package...', 'info');
        
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        
        const response = await fetch(`/api/packages.php?action=install&id=${packageId}`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(result.message, 'success');
            loadInstalledPackages();
            loadAvailablePackages();
        } else {
            showMessage('Installation failed: ' + result.error, 'error');
        }
        
    } catch (error) {
        showMessage('Installation error: ' + error.message, 'error');
    }
}

// Upgrade package
async function upgradePackageById(packageId, packageName) {
    if (!confirm(`Upgrade package "${packageName}"?\n\nThis will update the package to the latest version. Data will be preserved.`)) {
        return;
    }
    
    try {
        showMessage('Upgrading package...', 'info');
        
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        
        const response = await fetch(`/api/packages.php?action=upgrade&id=${packageId}`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(result.message, 'success');
            loadInstalledPackages();
            loadPackageUpdates();
        } else {
            showMessage('Upgrade failed: ' + result.error, 'error');
        }
        
    } catch (error) {
        showMessage('Upgrade error: ' + error.message, 'error');
    }
}

// Uninstall package
// Validate a package (runs full validation and shows report)
async function validatePackage(packageId, packageName) {
    try {
        console.log('🎯 validatePackage START - packageId:', packageId, 'packageName:', packageName);
        
        // Open modal immediately with progress state
        const modalHtml = `
            <div class="modal-overlay show" id="validationModal">
                <div class="modal-content modal-large" onclick="event.stopPropagation()">
                    <span class="modal-close" onclick="closeValidationModal()" style="position: absolute; top: 1rem; right: 1rem; font-size: 2rem; cursor: pointer; z-index: 10;">&times;</span>
                    <div class="validation-report-header">
                        <h2 class="validation-report-title">
                            <i class="bi bi-clipboard-check"></i>
                            Validating ${escapeHtml(packageName)}
                        </h2>
                    </div>
                    
                    <div class="validation-progress-section">
                        <div class="progress-bar-container">
                            <div class="progress-bar" id="validationProgressBar"></div>
                        </div>
                        <div class="validation-live-stats" id="validationLiveStats">
                            <span class="stat-item stat-running">
                                <i class="bi bi-hourglass-split"></i>
                                Running validation...
                            </span>
                        </div>
                    </div>
                    
                    <div class="validation-checks-container" id="validationChecksContainer">
                        <div class="validation-checks-list" id="validationChecksList" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 24px;">
                            <!-- Checks will be pre-populated here -->
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button class="btn btn-secondary" disabled id="closeValidationBtn" type="button">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        console.log('✅ Modal HTML added to DOM');
        
        const modal = document.getElementById('validationModal');
        console.log('✅ Modal found in DOM:', modal !== null, 'Computed style:', modal ? window.getComputedStyle(modal).display : 'N/A');
        
        const progressBar = document.getElementById('validationProgressBar');
        const liveStats = document.getElementById('validationLiveStats');
        const checksContainer = document.getElementById('validationChecksContainer');
        const checksList = document.getElementById('validationChecksList');
        const closeBtn = document.getElementById('closeValidationBtn');
        
        // Reset progress bar to 0% at start
        progressBar.style.width = '0%';
        progressBar.style.transition = 'width 0.5s ease';
        console.log('✅ Progress bar initialized at 0%');
        
        // Pre-populate standard checks IMMEDIATELY (before API call)
        const standardChecks = [
            'Package Format Version',
            'Hub Version',
            'PHP Version',
            'MySQL Version',
            'PHP Extension: json',
            'PHP Extension: pdo',
            'Core Module: users',
            'Field Definitions',
            'Security Scan',
            'Disk Space'
        ];
        
        standardChecks.forEach((checkName, index) => {
            const checkHtml = `
                <div class="validation-check-checkbox" id="check-${index}" data-check-name="${escapeHtml(checkName)}">
                    <span class="check-icon" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #ccc; border-radius: 4px; margin-right: 12px; text-align: center; line-height: 18px; color: #ccc; font-weight: bold;">
                    </span>
                    <span class="check-label" style="color: #666;">
                        ${escapeHtml(checkName)}
                    </span>
                </div>
            `;
            checksList.insertAdjacentHTML('beforeend', checkHtml);
        });
        console.log(`✅ Pre-populated ${standardChecks.length} standard checks`);
        
        // Add click handler to close button (IMPORTANT: must enable first)
        closeBtn.disabled = false;
        closeBtn.onclick = function() {
            console.log('Close button clicked via onclick');
            closeValidationModal();
        };
        
        // Add click handler to overlay (close when clicking outside modal)
        modal.onclick = function(e) {
            if (e.target === modal) {
                console.log('Overlay clicked, closing modal');
                closeValidationModal();
            }
        };
        
        console.log('✅ All modal elements found, event handlers attached');
        
        // Add CSS for spinning animation if not already present
        if (!document.getElementById('validation-spin-style')) {
            const style = document.createElement('style');
            style.id = 'validation-spin-style';
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }
        
        // Show spinning animation while waiting
        liveStats.innerHTML = `
            <span class="stat-item stat-running">
                <i class="bi bi-arrow-repeat" style="display: inline-block; animation: spin 1s linear infinite;"></i>
                Preparing validation environment...
            </span>
        `;
        
        console.log('Modal opened with pre-populated checks, waiting 3s for effect...');
        await new Promise(resolve => setTimeout(resolve, 3000)); // 3 second dramatic pause
        
        // Show progress simulation
        const checkTypes = [
            'Analyzing package structure...',
            'Verifying system requirements...',
            'Checking dependencies...',
            'Running security scans...',
            'Validating field definitions...',
            'Checking resource availability...'
        ];
        
        // Cycle through check types during validation
        let checkTypeIndex = 0;
        const progressInterval = setInterval(() => {
            liveStats.innerHTML = `
                <span class="stat-item stat-running">
                    <i class="bi bi-arrow-repeat" style="display: inline-block; animation: spin 1s linear infinite;"></i>
                    ${checkTypes[checkTypeIndex]}
                </span>
            `;
            checkTypeIndex = (checkTypeIndex + 1) % checkTypes.length;
        }, 1200); // Change message every 1.2 seconds
        
        console.log('Starting validation API call...');
        
        // Add artificial delay before API call
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Trigger actual validation
        console.log('Sending validation request to API...');
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        
        const response = await fetch(`/api/packages.php?action=validate&id=${packageId}`, {
            method: 'POST',
            body: formData
        });
        
        // Clear progress simulation
        clearInterval(progressInterval);
        
        console.log('Validation API responded with status:', response.status);
        
        // Add pause after validation completes to show "Processing results..."
        liveStats.innerHTML = `
            <span class="stat-item stat-running">
                <i class="bi bi-check-circle" style="color: #28a745;"></i>
                Processing validation results...
            </span>
        `;
        await new Promise(resolve => setTimeout(resolve, 2500)); // 2.5s pause after validation
        
        // Check if response is OK
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Validation request failed:', errorText);
            liveStats.innerHTML = `
                <span class="stat-item stat-error">
                    <i class="bi bi-x-circle"></i>
                    Server error: ${response.status}
                </span>
            `;
            closeBtn.disabled = false;
            return;
        }
        
        // Parse JSON with error handling
        let result;
        try {
            const responseText = await response.text();
            console.log('Validation response:', responseText);
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('JSON parse error:', e);
            liveStats.innerHTML = `
                <span class="stat-item stat-error">
                    <i class="bi bi-x-circle"></i>
                    Invalid response from server
                </span>
            `;
            closeBtn.disabled = false;
            return;
        }
        
        // Don't set progress to 100% here - let the animated check display handle it
        
        if (!result.success) {
            liveStats.innerHTML = `
                <span class="stat-item stat-error">
                    <i class="bi bi-x-circle"></i>
                    Validation failed: ${escapeHtml(result.error || 'Unknown error')}
                </span>
            `;
            closeBtn.disabled = false;
            return;
        }
        
        // Now fetch the actual validation results
        const detailsResponse = await fetch(`/api/packages.php?action=validation&id=${packageId}`);
        
        if (!detailsResponse.ok) {
            const errorText = await detailsResponse.text();
            console.error('Validation details request failed:', errorText);
            liveStats.innerHTML = `
                <span class="stat-item stat-error">
                    <i class="bi bi-x-circle"></i>
                    Failed to load validation details
                </span>
            `;
            closeBtn.disabled = false;
            return;
        }
        
        let detailsResult;
        try {
            const detailsText = await detailsResponse.text();
            console.log('Validation details response:', detailsText);
            detailsResult = JSON.parse(detailsText);
        } catch (e) {
            console.error('JSON parse error on details:', e);
            liveStats.innerHTML = `
                <span class="stat-item stat-error">
                    <i class="bi bi-x-circle"></i>
                    Invalid validation details response
                </span>
            `;
            closeBtn.disabled = false;
            return;
        }
        
        if (detailsResult.success) {
            console.log('Processing validation details...', detailsResult);
            const summary = detailsResult.summary;
            const checks = detailsResult.all_checks || [];
            
            console.log('Summary:', summary);
            console.log('Checks count:', checks.length);
            
            // Update header with result
            document.querySelector('.validation-report-title').innerHTML = `
                <i class="bi bi-clipboard-check"></i>
                Validating Package...
            `;
            console.log('Header updated');
            
            // Don't clear - keep the pre-populated checkboxes
            console.log('Using pre-populated checks');
            
            // Show initial progress message
            liveStats.innerHTML = `
                <span class="stat-item stat-running">
                    <i class="bi bi-hourglass-split"></i>
                    Running validation checks...
                </span>
            `;
            
            // Map API results to pre-populated checkboxes
            const allChecks = checks;
            
            // Now animate checking them one by one
            let checkIndex = 0;
            
            // Show checks progressively with delay
            const showNextCheck = async () => {
                if (checkIndex >= allChecks.length) {
                    // All checks shown, update final state with celebration
                    progressBar.style.width = '100%';
                    
                    // Show "Finalizing..." message first
                    liveStats.innerHTML = `
                        <span class="stat-item stat-running">
                            <i class="bi bi-stars" style="color: #ffc107;"></i>
                            Finalizing validation...
                        </span>
                    `;
                    
                    // Wait 2 seconds for dramatic effect
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    
                    // Now show final results with celebration
                    liveStats.innerHTML = `
                        <span class="stat-item ${summary.failed > 0 ? 'stat-error' : 'stat-success'}">
                            <i class="bi bi-${summary.failed > 0 ? 'x-circle' : 'check-circle'}"></i>
                            Validation complete! 🎉
                        </span>
                        <span class="stat-item" style="background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 4px; font-weight: 600;">
                            ${summary.passed} passed
                        </span>
                        <span class="stat-item" style="background: #f8d7da; color: #721c24; padding: 6px 12px; border-radius: 4px; font-weight: 600;">
                            ${summary.failed} failed
                        </span>
                        <span class="stat-item" style="background: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 4px; font-weight: 600;">
                            ${summary.warnings} warnings
                        </span>
                    `;
                    
                    // Wait another 1.5 seconds before showing install button
                    await new Promise(resolve => setTimeout(resolve, 1500));
                    
                    // Add install button if validation passed
                    if (summary.failed === 0 && summary.critical === 0) {
                        console.log('Adding install button with animation');
                        const modalActions = document.querySelector('.modal-actions');
                        modalActions.innerHTML = `
                            <button class="btn btn-secondary" id="modalCloseBtn" type="button">
                                Close
                            </button>
                            <button class="btn btn-primary" id="modalInstallBtn" type="button" style="animation: buttonPulse 0.6s ease;">
                                <i class="bi bi-download"></i> Install Package
                            </button>
                        `;
                        
                        // Add event listeners to new buttons using onclick for reliability
                        document.getElementById('modalCloseBtn').onclick = function() {
                            console.log('Modal close clicked');
                            closeValidationModal();
                        };
                        document.getElementById('modalInstallBtn').onclick = function() {
                            console.log('Install clicked');
                            closeValidationModal();
                            installPackage(packageId, packageName);
                        };
                    } else {
                        console.log('Validation had failures, enabling close button');
                        // Enable close button after failures
                        const closeBtn = document.getElementById('closeValidationBtn');
                        if (closeBtn) closeBtn.disabled = false;
                    }
                    
                    // Update final header with animation
                    document.querySelector('.validation-report-title').innerHTML = `
                        <i class="bi bi-${summary.failed === 0 ? 'check-circle-fill' : 'x-circle-fill'}" style="color: ${summary.failed === 0 ? '#28a745' : '#dc3545'};"></i>
                        ${summary.failed === 0 ? 'Package Validated - Ready to Install! 🚀' : 'Validation Failed'}
                    `;
                    
                    // Reload packages list IMMEDIATELY to update button states
                    console.log('🔄 Reloading packages list to show updated status...');
                    await loadAvailablePackages();
                    
                    // Force rows to be visible immediately (CSS has opacity: 0 by default)
                    setTimeout(() => {
                        const container = document.getElementById('availablePackagesTable');
                        const rows = container ? container.querySelectorAll('tbody tr') : [];
                        console.log('🎨 Forcing', rows.length, 'rows visible (container found:', !!container, ')');
                        rows.forEach((row, index) => {
                            // Force visibility - override CSS opacity: 0
                            row.style.opacity = '1';
                            row.style.transform = 'translateY(0)';
                            row.classList.add('animate-in');
                            console.log('  ✅ Row', index + 1, 'made visible');
                        });
                    }, 150); // Increased delay to ensure DOM is ready
                    
                    // Highlight the updated package row with a flash effect
                    setTimeout(() => {
                        const rows = document.querySelectorAll('#availablePackagesTable tbody tr');
                        console.log('🔍 Found', rows.length, 'rows to check for highlight');
                        rows.forEach(row => {
                            const nameCell = row.querySelector('td:first-child');
                            if (nameCell && nameCell.textContent.includes(packageName)) {
                                row.style.animation = 'rowHighlight 2s ease-out forwards';
                                console.log('✨ Highlighted updated package row');
                            }
                        });
                    }, 200);
                    
                    return;
                }
                
                const check = allChecks[checkIndex];
                
                // Find the existing checkbox by matching check name
                const checkDiv = Array.from(document.querySelectorAll('.validation-check-checkbox')).find(div => {
                    const checkName = div.getAttribute('data-check-name');
                    return checkName === check.check_name;
                });
                
                if (!checkDiv) {
                    console.warn(`Checkbox not found for: ${check.check_name}`);
                    checkIndex++;
                    setTimeout(showNextCheck, 300);
                    return;
                }
                
                const checkIcon = checkDiv.querySelector('.check-icon');
                const labelSpan = checkDiv.querySelector('.check-label');
                
                // Animate the check with color change and checkmark
                if (check.status === 'pass') {
                    checkIcon.textContent = '✓';
                    checkIcon.style.backgroundColor = '#28a745';
                    checkIcon.style.borderColor = '#28a745';
                    checkIcon.style.color = 'white';
                    checkIcon.style.transition = 'all 0.3s ease';
                    labelSpan.style.color = '#333';
                } else if (check.status === 'fail') {
                    checkIcon.textContent = '✗';
                    checkIcon.style.backgroundColor = '#dc3545';
                    checkIcon.style.borderColor = '#dc3545';
                    checkIcon.style.color = 'white';
                    checkIcon.style.transition = 'all 0.3s ease';
                    labelSpan.style.color = '#dc3545';
                    labelSpan.style.fontWeight = 'bold';
                } else {
                    checkIcon.textContent = '⚠';
                    checkIcon.style.backgroundColor = '#ffc107';
                    checkIcon.style.borderColor = '#ffc107';
                    checkIcon.style.color = 'white';
                    checkIcon.style.transition = 'all 0.3s ease';
                    labelSpan.style.color = '#856404';
                }
                
                // Update progress
                const percent = ((checkIndex + 1) / allChecks.length) * 100;
                progressBar.style.width = percent + '%';
                
                // Update live stats
                const currentPassed = allChecks.slice(0, checkIndex + 1).filter(c => c.status === 'pass').length;
                const currentFailed = allChecks.slice(0, checkIndex + 1).filter(c => c.status === 'fail').length;
                liveStats.innerHTML = `
                    <span class="stat-item stat-running">
                        <i class="bi bi-hourglass-split"></i>
                        Checking ${checkIndex + 1} of ${allChecks.length}...
                    </span>
                `;
                
                checkIndex++;
                
                // Show next check after delay (800ms for dramatic effect)
                setTimeout(showNextCheck, 800);
            };
            
            // Start showing checks with initial delay
            setTimeout(showNextCheck, 500); // Wait 500ms before first check appears
            
            console.log('Starting animated check display');
        } else {
            console.error('Validation details request failed:', detailsResult);
        }
        
    } catch (error) {
        showMessage('Validation error: ' + error.message, 'error');
        document.getElementById('closeValidationBtn').disabled = false;
    }
}

function closeValidationModal() {
    const modal = document.getElementById('validationModal');
    if (modal) {
        // Add hiding class for exit animation
        modal.classList.add('hiding');
        modal.classList.remove('show');
        
        // Wait for animation before removing
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Toggle section enabled/disabled status
async function toggleSectionStatus(packageId, packageName, enable) {
    const action = enable ? 'enable' : 'disable';
    const actionLabel = enable ? 'Enable' : 'Disable';
    
    if (!confirm(`${actionLabel} section "${packageName}"?`)) {
        return;
    }
    
    try {
        showMessage(`${actionLabel.slice(0, -1)}ing section...`, 'info');
        
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        
        const response = await fetch(`/api/packages.php?action=${action}&package_id=${encodeURIComponent(packageId)}`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(result.message, 'success');
            loadInstalledPackages();
        } else {
            showMessage(`${actionLabel} failed: ` + result.error, 'error');
        }
        
    } catch (error) {
        showMessage(`${actionLabel} error: ` + error.message, 'error');
    }
}

async function uninstallPackagePrompt(packageId, packageName) {
    const keepData = confirm(
        `Uninstall package "${packageName}"?\n\n` +
        `Click OK to KEEP data (section becomes inactive)\n` +
        `Click Cancel to abort\n\n` +
        `To DELETE all data, click OK then confirm in the next dialog.`
    );
    
    if (keepData === null) return; // User cancelled
    
    let deleteData = false;
    if (keepData) {
        deleteData = confirm(
            `Do you want to DELETE all data from this section?\n\n` +
            `⚠️ WARNING: This cannot be undone!\n\n` +
            `OK = Delete all data\nCancel = Keep data`
        );
    }
    
    try {
        showMessage('Uninstalling package...', 'info');
        
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        
        const keepDataParam = deleteData ? '0' : '1';
        const response = await fetch(`/api/packages.php?action=uninstall&package_id=${encodeURIComponent(packageId)}&keep_data=${keepDataParam}`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage(result.message, 'success');
            loadInstalledPackages();
            loadAvailablePackages();
        } else {
            showMessage('Uninstall failed: ' + result.error, 'error');
        }
        
    } catch (error) {
        showMessage('Uninstall error: ' + error.message, 'error');
    }
}

// Delete package file
async function deletePackage(packageId, packageName) {
    if (!confirm(`Delete package "${packageName}"?\n\nThis will remove the uploaded package file. This action cannot be undone.`)) {
        return;
    }
    
    try {
        const response = await fetch(`/api/packages.php?id=${packageId}&csrf_token=${window.csrfToken}`, {
            method: 'DELETE'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showMessage('Package deleted', 'success');
            loadAvailablePackages();
        } else {
            showMessage('Delete failed: ' + result.error, 'error');
        }
        
    } catch (error) {
        showMessage('Delete error: ' + error.message, 'error');
    }
}

// Show validation details modal
async function showValidationDetails(packageId) {
    try {
        const response = await fetch(`/api/packages.php?action=validation&id=${packageId}`);
        const result = await response.json();
        
        if (!result.success) throw new Error(result.error);
        
        const pkg = result.package;
        const summary = result.summary;
        const checks = result.all_checks;
        
        const summaryClass = pkg.can_install ? 'success' : (pkg.validation_status === 'pending' ? 'pending' : 'failure');
        const summaryIcon = pkg.can_install ? '✓' : (pkg.validation_status === 'pending' ? '⏳' : '✗');
        const summaryText = pkg.can_install ? 'Package Validated - Ready to Install' : 
                           (pkg.validation_status === 'pending' ? 'Running Complete Validation...' : 'Validation Failed - Installation Blocked');
        
        let html = `
            <div class="validation-report">
                <div class="validation-report-header" style="padding: 24px 32px; background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                    <h2 class="validation-report-title" style="margin: 0 0 8px 0; font-size: 1.5rem; font-weight: 600; color: #111827;">
                        ${pkg.validation_status === 'pending' ? 'Package Validation In Progress' : 'Package Validation Report'}
                    </h2>
                    <h3 class="validation-report-subtitle" style="margin: 0; font-size: 1.1rem; font-weight: 400; color: #6b7280;">
                        ${escapeHtml(pkg.display_name)} <small style="color: #9ca3af;">v${escapeHtml(pkg.version)}</small>
                    </h3>
                </div>
                
                ${pkg.validation_status === 'pending' ? `
                <div class="validation-summary pending">
                    <span class="validation-summary-icon">⏳</span>
                    <div class="validation-summary-content">
                        <strong>Running Complete Validation...</strong>
                        <div class="validation-summary-stats">
                            This is a comprehensive audit of the package.<br>
                            All ${summary.total_checks || 0} checks are being performed.
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <div class="validation-summary ${summaryClass}" style="padding: 16px 24px;">
                    <span class="validation-summary-icon">${summaryIcon}</span>
                    <div class="validation-summary-content">
                        <strong>${summaryText}</strong>
                        <div class="validation-summary-stats">
                            <strong>Complete Audit:</strong> ${summary.total_checks || 0} checks performed<br>
                            <span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-weight: bold;">${summary.passed || 0} passed</span> • 
                            <span style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-weight: bold;">${summary.failed || 0} failed</span> • 
                            <span style="background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 4px; font-weight: bold;">${summary.warnings || 0} warnings</span>
                            ${(summary.critical || 0) > 0 ? `<br><span style="color: #d32f2f; font-weight: bold;">⚠️ ${summary.critical} critical issues</span>` : ''}
                        </div>
                    </div>
                </div>
                
                <h3 class="validation-checks-header" style="padding: 16px 32px; margin: 0; background: #f3f4f6; border-bottom: 1px solid #e5e7eb; font-size: 1rem; color: #374151;">
                    All Compatibility Checks
                    <small style="color: #9ca3af;">(showing all ${checks.length} checks)</small>
                </h3>
                <div class="validation-checks-container">
        `;
        
        // Group checks by type
        const groupedChecks = {};
        checks.forEach(check => {
            if (!groupedChecks[check.check_type]) {
                groupedChecks[check.check_type] = [];
            }
            groupedChecks[check.check_type].push(check);
        });
        
        // Display checks by group
        Object.keys(groupedChecks).forEach(checkType => {
            const typeChecks = groupedChecks[checkType];
            const typePassed = typeChecks.filter(c => c.status === 'pass').length;
            const typeFailed = typeChecks.filter(c => c.status === 'fail').length;
            const typeIcon = typeFailed > 0 ? '✗' : typePassed === typeChecks.length ? '✓' : '⚠';
            const typeIconClass = typeFailed > 0 ? 'fail' : typePassed === typeChecks.length ? 'pass' : 'warning';
            
            html += `
                <div class="validation-check-group">
                    <div class="validation-check-group-header">
                        <span class="validation-check-icon ${typeIconClass}">${typeIcon}</span>
                        ${escapeHtml(checkType.toUpperCase().replace(/_/g, ' '))}
                        <small>${typePassed}/${typeChecks.length} passed</small>
                    </div>
            `;
            
            typeChecks.forEach(check => {
                const icon = check.status === 'fail' ? '✗' : check.status === 'warning' ? '⚠' : '✓';
                
                html += `
                    <div class="validation-check-item ${check.status}">
                        <span class="validation-check-icon ${check.status}">${icon}</span>
                        <div class="validation-check-content">
                            <strong>${escapeHtml(check.check_name)}</strong>
                            <div class="validation-check-message">${escapeHtml(check.message)}</div>
                            ${check.resolution ? `<div class="validation-check-resolution">
                                <strong>Fix:</strong> ${escapeHtml(check.resolution)}
                            </div>` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += `</div>`;
        });
        
        html += `
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                    ${pkg.can_install && !result.package.is_installed ? 
                        `<button class="btn btn-primary" onclick="closeModal(); installPackage(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                            Install Package
                        </button>` : ''}
                </div>
            </div>
        `;
        
        showModalWithContent(html);
        
    } catch (error) {
        showMessage('Error loading validation: ' + error.message, 'error');
    }
}

// Show modal with custom content
function showModalWithContent(htmlContent) {
    // Remove existing modal if any
    let modal = document.getElementById('dynamicModal');
    if (modal) modal.remove();
    
    // Create modal
    modal = document.createElement('div');
    modal.id = 'dynamicModal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 1rem;';
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 8px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);';
    modalContent.innerHTML = htmlContent;
    
    modal.appendChild(modalContent);
    document.body.appendChild(modal);
    
    // Close on background click
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
}

// Close modal
function closeModal() {
    const modal = document.getElementById('dynamicModal');
    if (modal) modal.remove();
}

// Helper: Escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper: Format date
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
}
