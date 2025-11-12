// Admin Dashboard JavaScript

// Set initial tab IMMEDIATELY (before DOMContentLoaded) to prevent flash
(function () {
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
        enableGoogleGroups.addEventListener('change', function () {
            toggleDependentSection('enableGoogleGroups', 'googleGroupsFields', true);
        });
    }

    // Example: Microsoft Groups depends on Microsoft OAuth (when implemented)
    const enableMicrosoftGroups = document.getElementById('enableMicrosoftGroups');
    if (enableMicrosoftGroups) {
        enableMicrosoftGroups.addEventListener('change', function () {
            toggleDependentSection('enableMicrosoftGroups', 'microsoftGroupsFields', true);
        });
    }
}

document.addEventListener('DOMContentLoaded', async function () {
    // Activity Logs state (must be declared at top of scope)
    let currentLogOffset = 0;
    let currentLogLimit = 100;

    // Load roles cache immediately on page load
    await loadRolesCache();

    // Initialize cascading dependencies for optional features
    initializeDependencies();

    // Check for package alerts on dashboard load (for super admins)
    // Always check to update badges, but only show alert banners if packages tab is active
    if (window.isSuperAdmin) {
        const packagesTabActive = document.getElementById('tab-packages')?.classList.contains('active');
        checkPackageAlerts(packagesTabActive);
    }

    // Hamburger Menu for Admin Sidebar (Responsive)
    const hamburgerMenu = document.querySelector('.hamburger-menu');
    const adminSidebar = document.querySelector('.admin-sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');

    if (hamburgerMenu && adminSidebar) {
        hamburgerMenu.addEventListener('click', function () {
            hamburgerMenu.classList.toggle('active');
            adminSidebar.classList.toggle('open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
        });

        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                hamburgerMenu.classList.remove('active');
                adminSidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
            });
        }

        // Close sidebar when clicking a menu item on mobile
        const sidebarLinks = adminSidebar.querySelectorAll('.sidebar-menu a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function () {
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
        navToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            navLinks.classList.toggle('active');
            console.log('Nav toggled, active:', navLinks.classList.contains('active'));
        });

        document.addEventListener('click', function (e) {
            if (!navToggle.contains(e.target) && !navLinks.contains(e.target)) {
                navLinks.classList.remove('active');
            }
        });

        // Close nav when clicking a link
        const navLinksElements = navLinks.querySelectorAll('.nav-link');
        navLinksElements.forEach(link => {
            link.addEventListener('click', function () {
                navLinks.classList.remove('active');
            });
        });
    }

    // Tab switching (both sidebar and mobile menu)
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(tab => {
        tab.addEventListener('click', function (e) {
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
        console.log('🔄 Restoring saved tab:', savedTab);
        switchTab(savedTab);
    } else {
        // No saved tab, load default User Management data
        loadUsers();
    }

    // Additional check for packages tab loading after a brief delay
    setTimeout(() => {
        const packagesTab = document.getElementById('tab-packages');
        if (packagesTab && packagesTab.classList.contains('active')) {
            console.log('📦 Packages tab is active on page load, ensuring content is loaded...');
            const activeSubtab = document.querySelector('#tab-packages .subtab-nav a.active');
            if (activeSubtab) {
                const subtabName = activeSubtab.dataset.subtab;
                console.log('📋 Loading initial packages subtab:', subtabName);

                if (subtabName === 'installed-packages') {
                    loadInstalledPackages();
                } else if (subtabName === 'available-packages') {
                    loadAvailablePackages();
                } else if (subtabName === 'package-updates') {
                    loadPackageUpdates();
                }
            } else {
                console.log('📋 No active subtab found, loading installed packages as default');
                // Click the first subtab to activate it and load content
                const firstSubtab = document.querySelector('#tab-packages .subtab-nav a[data-subtab="installed-packages"]');
                if (firstSubtab) {
                    firstSubtab.click();
                }
            }
        }
    }, 200);

    // After tab initialization, ensure packages content is loaded if packages tab is active
    setTimeout(() => {
        const packagesTab = document.getElementById('tab-packages');
        if (packagesTab && packagesTab.classList.contains('active')) {
            const activeSubtab = document.querySelector('#tab-packages .subtab-nav a.active');
            if (activeSubtab) {
                const subtabName = activeSubtab.dataset.subtab;
                console.log('Loading initial packages subtab:', subtabName);
                if (subtabName === 'installed-packages') {
                    loadInstalledPackages();
                } else if (subtabName === 'available-packages') {
                    loadAvailablePackages();
                } else if (subtabName === 'package-updates') {
                    loadPackageUpdates();
                }
            }
        }
    }, 100); // Small delay to ensure DOM is ready

    // Modal controls - Bootstrap API (no need for manual click handlers, Bootstrap handles via data-bs-dismiss)
    // Close modals when clicking outside - Bootstrap handles this automatically

    // Close modals when clicking outside (legacy support for dynamically created modals)
    window.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
            const modalInstance = bootstrap.Modal.getInstance(e.target);
            if (modalInstance) modalInstance.hide();
        }
    });

    // Sub-tabs (User Management & Vehicles)
    document.querySelectorAll('.subtab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
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

        // If switching to section-config tab, load section configuration
        if (tabName === 'section-config') {
            if (typeof loadSectionConfig === 'function' && typeof sectionsConfigData !== 'undefined') {
                if (sectionsConfigData.length === 0) {
                    loadSectionConfig();
                }
            }
        }

        // If switching to packages tab, check and show alerts
        if (tabName === 'packages' && window.isSuperAdmin) {
            checkPackageAlerts(true);

            // Load content for the currently active packages subtab
            const activePackagesSubtab = document.querySelector('#tab-packages .subtab-nav a.active');
            if (activePackagesSubtab) {
                const subtabName = activePackagesSubtab.dataset.subtab;
                if (subtabName === 'installed-packages') {
                    loadInstalledPackages();
                } else if (subtabName === 'available-packages') {
                    loadAvailablePackages();
                } else if (subtabName === 'package-updates') {
                    loadPackageUpdates();
                }
            } else {
                // No active subtab found, default to installed packages
                loadInstalledPackages();
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

    function closeModal() {
        // Close all Bootstrap modals using Bootstrap API
        document.querySelectorAll('.modal').forEach(modalElement => {
            const modalInstance = bootstrap.Modal.getInstance(modalElement);
            if (modalInstance) {
                modalInstance.hide();
            }
        });

        // Reset forms
        document.getElementById('invitationForm')?.reset();
        document.getElementById('sectionAccessForm')?.reset();
        document.getElementById('sectionForm')?.reset();
        document.getElementById('userRolesForm')?.reset();

        // Clear hidden fields
        const sectionIdField = document.getElementById('sectionId');
        if (sectionIdField) sectionIdField.value = '';
    }

    window.changeUserRole = function (id, currentRole) {
        const newRole = prompt(`Change role for user (current: ${currentRole}):\nOptions: staff, manager, admin, super_admin`, currentRole);
        if (newRole && ['staff', 'manager', 'admin', 'super_admin'].includes(newRole)) {
            // Implementation here
            alert('Role change functionality coming soon');
        }
    };

    window.toggleUser = function (id, isActive) {
        const action = isActive ? 'deactivate' : 'activate';
        if (!confirm(`Are you sure you want to ${action} this user?`)) return;

        // Implementation here
        alert(`${action} functionality coming soon`);
    };

    // Edit User Global Roles
    window.editUserRoles = async function (userId) {
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

            // Show modal using Bootstrap API
            const modalElement = document.getElementById('userRolesModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } catch (error) {
            console.error('Error loading user roles:', error);
            showToast('Failed to load user roles', 'error');
        }
    };

    // User Roles Form Submit Handler
    document.getElementById('userRolesForm')?.addEventListener('submit', async function (e) {
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

    window.approveUser = async function (id) {
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

    window.denyUser = async function (id) {
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
        const modalElement = document.getElementById('invitationModal');
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
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
                const modalElement = document.getElementById('invitationModal');
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) modalInstance.hide();
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
                // Render icon - check if it's a Bootstrap Icons class or emoji
                let iconHtml = '';
                if (section.icon && section.icon.startsWith('bi-')) {
                    // Bootstrap Icons class
                    iconHtml = `<i class="bi ${section.icon}"></i>`;
                } else {
                    // Emoji or other text icon
                    iconHtml = escapeHtml(section.icon || '📦');
                }

                // Truncate description to 60 characters
                let descriptionHtml = '';
                let nameWithIcon = escapeHtml(section.display_name);

                if (section.description) {
                    const fullDesc = section.description;
                    const truncatedDesc = fullDesc.length > 60 ? fullDesc.substring(0, 60) + '...' : fullDesc;
                    const needsIcon = fullDesc.length > 60;

                    if (needsIcon) {
                        // Add icon next to section name with properly escaped tooltip
                        // Use double escaping for data attribute: once for HTML, once for attribute value
                        const tooltipText = fullDesc.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        nameWithIcon = `${escapeHtml(section.display_name)} <i class="bi bi-info-circle section-description-icon" data-full-text="${tooltipText}"></i>`;
                    }

                    descriptionHtml = `<div class="section-description">
                        <span class="section-description-text">${escapeHtml(truncatedDesc)}</span>
                    </div>`;
                }

                html += `<tr>
                    <td class="sticky-col">
                        <div class="section-name-cell">
                            <span class="section-icon">${iconHtml}</span>
                            <div>
                                <div class="section-name">${nameWithIcon}</div>
                                ${descriptionHtml}
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
            const updates = Object.entries(sections)
                .filter(([sectionId, roles]) => {
                    // Only update sections that have valid IDs
                    const id = parseInt(sectionId);
                    return id > 0 && !isNaN(id);
                })
                .map(([sectionId, roles]) => {
                    const formData = new FormData();
                    formData.append('csrf_token', window.csrfToken);
                    formData.append('section_id', sectionId);
                    roles.forEach(role => formData.append('roles[]', role));

                    return fetch('/api/section-role-access.php', {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (!response.ok) {
                            console.error(`Failed to update section ${sectionId}:`, response.status);
                        }
                        return response;
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

    window.toggleRole = async function (roleValue, enable) {
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

    window.updateRoleNotes = async function (roleValue, notes) {
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
        if (!container) {
            console.error('❌ sectionsManagementTable container not found!');
            return;
        }

        console.log('📋 Loading sections management table...');

        try {
            const response = await fetch('/api/sections.php');
            const sections = await response.json();

            console.log('📋 Loaded sections:', sections.length, 'sections');
            console.log('🔍 Section data:', sections);

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

                    // Render icon - check if it's a Bootstrap Icons class or emoji
                    let iconDisplay = '';
                    if (section.icon && section.icon.startsWith('bi-')) {
                        iconDisplay = `<i class="bi ${section.icon}" style="font-size: 2rem;"></i>`;
                    } else {
                        iconDisplay = escapeHtml(section.icon || '📦');
                    }

                    html += `<tr>
                        <td class="sticky-col" style="font-size: 2rem;">${iconDisplay}</td>
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
            console.log('📝 HTML length:', html.length, 'characters');
            console.log('🎯 Setting innerHTML on container:', container.id);
            container.innerHTML = html;
            console.log('✅ Sections management table rendered successfully');
            console.log('👀 Container now has', container.querySelectorAll('tr').length - 1, 'data rows');
        } catch (error) {
            console.error('❌ Error loading sections:', error);
            container.innerHTML = '<p class="error">Failed to load sections.</p>';
        }
    }

    window.editSection = async function (sectionId) {
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

            const modalElement = document.getElementById('sectionModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        } catch (error) {
            console.error('Error loading section:', error);
            showToast('Failed to load section', 'error');
        }
    };

    window.toggleSection = async function (sectionId, isActive) {
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

                // Force reload the sections table
                console.log('🔄 Reloading sections management table...');
                await loadSectionsManagement();
                console.log('✅ Sections table reloaded');
            } else {
                showToast(result.error || 'Failed to update section', 'error');
            }
        } catch (error) {
            console.error('Error toggling section:', error);
            showToast('Failed to update section: ' + error.message, 'error');
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
        document.getElementById('addSection')?.addEventListener('click', function () {
            document.getElementById('sectionModalTitle').textContent = 'Add New Section';
            document.getElementById('sectionForm').reset();
            document.getElementById('sectionId').value = '';

            const modalElement = document.getElementById('sectionModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            // Reset manual edit flag when opening for new section
            if (window.sectionSlugManuallyEdited !== undefined) {
                window.sectionSlugManuallyEdited = false;
            }
        });

        document.getElementById('refreshSections')?.addEventListener('click', loadSectionsManagement);
        document.getElementById('sectionForm')?.addEventListener('submit', handleSectionFormSubmit);

        // Modal close handlers are handled by Bootstrap via data-bs-dismiss attribute

        // Auto-generate section slug from display name
        const sectionDisplayNameInput = document.getElementById('sectionDisplayName');
        const sectionNameInput = document.getElementById('sectionName');
        const sectionBaseUrlInput = document.getElementById('sectionBaseUrl');

        if (sectionDisplayNameInput && sectionNameInput && sectionBaseUrlInput) {
            window.sectionSlugManuallyEdited = false;

            // Track if user manually edited the slug
            sectionNameInput.addEventListener('input', function () {
                window.sectionSlugManuallyEdited = true;
            });

            // Auto-generate slug from display name
            sectionDisplayNameInput.addEventListener('input', function () {
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
    document.getElementById('clearLogFilters')?.addEventListener('click', function () {
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
            // const response = await fetch('/api/themes.php?_=' + Date.now());
            const response = await fetch('/api/themes.php');

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
    document.getElementById('saveCurrentTheme')?.addEventListener('click', async function () {
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
    window.activateTheme = async function (themeId) {
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

    window.selectThemeForUpdate = function (themeId, themeName) {
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

    window.cancelUpdateMode = function () {
        window.selectedThemeForUpdate = null;
        document.getElementById('updateThemeNotice').style.display = 'none';
        document.getElementById('updateSelectedTheme').style.display = 'none';
        document.getElementById('saveCurrentTheme').style.display = 'inline-block';
        document.getElementById('newThemeName').value = '';
        document.getElementById('newThemeName').disabled = false;
    };

    // Update selected theme button click
    document.getElementById('updateSelectedTheme')?.addEventListener('click', async function () {
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

    // Delete theme
    window.deleteTheme = async function (themeId, themeName) {
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
    window.exportTheme = async function (themeId) {
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
        themesSubtabBtn.addEventListener('click', function () {
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
    window.toggleColorSection = function (header) {
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
        colorInput.addEventListener('input', function () {
            const value = this.value.toUpperCase();
            hexInput.value = value;
            if (previewCallback) previewCallback(value);
        });

        // Update color picker when hex changes
        hexInput.addEventListener('input', function () {
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
        hexInput.addEventListener('blur', function () {
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
        colorsSubtabBtn.addEventListener('click', function () {
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
                role_associations: (function () {
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
        saveAdvancedBtn.addEventListener('click', async function () {
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
        reloadAdvancedBtn.addEventListener('click', function () {
            if (confirm('Discard all unsaved changes and reload settings from .env file?')) {
                loadAdvancedSettings();
            }
        });
    }

    // Test database connection
    const testDbBtn = document.getElementById('testDbConnection');
    if (testDbBtn) {
        testDbBtn.addEventListener('click', async function () {
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
        clearSessionsBtn.addEventListener('click', async function () {
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
        regenerateEnvBtn.addEventListener('click', async function () {
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

    window.updateRoleAssociation = function (index, field, value) {
        roleAssociationsData[index][field] = value;
    };

    window.removeRoleAssociation = function (index) {
        roleAssociationsData.splice(index, 1);
        renderRoleAssociations();
    };

    const addRoleAssociationBtn = document.getElementById('addRoleAssociation');
    if (addRoleAssociationBtn) {
        addRoleAssociationBtn.addEventListener('click', function () {
            roleAssociationsData.push({ group: '', role: 'viewer' });
            renderRoleAssociations();
        });
    }

    // ============================================
    // SERVICE ACCOUNT UPLOAD
    // ============================================

    const serviceAccountUpload = document.getElementById('serviceAccountUpload');
    if (serviceAccountUpload) {
        serviceAccountUpload.addEventListener('change', async function (e) {
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
        testSmtpBtn.addEventListener('click', async function () {
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
        sendTestEmailBtn.addEventListener('click', async function () {
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
        testGoogleGroupsBtn.addEventListener('click', async function () {
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
        advancedSubtabBtn.addEventListener('click', function () {
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
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('googleGroupForm');
    const modal = document.getElementById('googleGroupModal');

    if (form && modal) {
        form.addEventListener('submit', function (e) {
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
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('microsoftGroupForm');
    const modal = document.getElementById('microsoftGroupModal');

    if (form && modal) {
        form.addEventListener('submit', function (e) {
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
document.addEventListener('DOMContentLoaded', function () {
    const packageTab = document.querySelector('[data-tab="packages"]');

    if (packageTab) {
        packageTab.addEventListener('click', function () {
            loadInstalledPackages();
            loadAvailablePackages();
            loadPackageUpdates();
            checkPackageAlerts(true); // Show alerts when tab is clicked
        });
    }

    // Upload button
    const uploadBtn = document.getElementById('uploadPackageBtn');
    const fileInput = document.getElementById('packageFileInput');
    const dropzone = document.getElementById('uploadDropzone');

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function () {
            if (this.files.length > 0) {
                uploadPackageFile(this.files[0]);
            }
        });
    }

    // Drag and drop
    if (dropzone) {
        dropzone.addEventListener('click', () => fileInput.click());

        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.style.borderColor = '#4CAF50';
            this.style.background = '#e8f5e9';
        });

        dropzone.addEventListener('dragleave', function (e) {
            e.preventDefault();
            this.style.borderColor = '#dee2e6';
            this.style.background = '#f8f9fa';
        });

        dropzone.addEventListener('drop', function (e) {
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
    if (!container) {
        console.error('❌ installedPackagesTable container not found!');
        return;
    }

    console.log('🔄 Loading installed packages...');

    try {
        container.innerHTML = '<p style="text-align: center; padding: 2rem;">Loading...</p>';

        const timestamp = Date.now();
        const response = await fetch(`/api/packages.php?action=installed&_=${timestamp}`);
        const result = await response.json();

        if (!result.success) throw new Error(result.error);

        const packages = result.packages;
        console.log('📦 Loaded', packages.length, 'installed packages');

        if (packages.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem 1rem; color: #6c757d;">
                    <i class="bi bi-box-seam" style="font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                    <h4 style="margin-bottom: 0.75rem; color: #495057; font-size: 1.1rem;">No Packages Installed</h4>
                    <p style="margin-bottom: 1.25rem; font-size: 0.95em;">You haven't installed any packages yet.</p>
                    <p style="font-size: 0.9rem;">
                        <button class="btn btn-primary btn-sm" id="browsePackagesBtn">
                            <i class="bi bi-arrow-right-circle"></i> View Available Packages
                        </button>
                    </p>
                    <p style="margin-top: 0.5rem; font-size: 0.85em; color: #999;">
                        Switches to the Available Packages tab
                    </p>
                </div>
            `;

            // Add click handler to switch to available packages tab
            setTimeout(() => {
                const browseBtn = document.getElementById('browsePackagesBtn');
                if (browseBtn) {
                    browseBtn.addEventListener('click', () => {
                        const availableTab = document.querySelector('[data-subtab="available-packages"]');
                        if (availableTab) availableTab.click();
                    });
                }
            }, 100);

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

        // Re-trigger table row animations (fix for packages appearing but not animating)
        if (window.AdminAnimations && packages.length > 0) {
            console.log('🎬 Re-triggering table animations for', packages.length, 'installed packages');
            setTimeout(() => {
                window.AdminAnimations.animateTableRows('#installedPackagesTable table', 60);
            }, 100);
        }

    } catch (error) {
        console.error('Error loading installed packages:', error);
        container.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: #d32f2f;">
                <i class="bi bi-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.7;"></i>
                <h4 style="margin-bottom: 1rem;">Error Loading Packages</h4>
                <p style="margin-bottom: 1.5rem;">Unable to load installed packages: ${error.message}</p>
                <button class="btn btn-outline-primary btn-sm" onclick="loadInstalledPackages()">
                    <i class="bi bi-arrow-clockwise"></i> Try Again
                </button>
            </div>
        `;
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

        // Fetch both packages and dismissed alerts (add cache-busting timestamp)
        const timestamp = Date.now();
        const [packagesResponse, alertsResponse] = await Promise.all([
            fetch(`/api/packages.php?_=${timestamp}`),
            fetch(`/api/package-alerts.php?action=check&_=${timestamp}`)
        ]);

        const [packagesResult, alertsResult] = await Promise.all([
            packagesResponse.json(),
            alertsResponse.json()
        ]);

        console.log('📦 Packages API response:', packagesResult);
        console.log('📦 Alerts API response:', alertsResult);

        if (!packagesResult.success) throw new Error(packagesResult.error);

        const packages = packagesResult.packages;
        const dismissedPackages = new Set();

        // Track dismissed packages
        if (alertsResult.success && alertsResult.alerts.validation.packages) {
            // All packages not in the validation packages list are dismissed
            const validationPackageIds = new Set(alertsResult.alerts.validation.packages.map(p => p.id));
            packages.forEach(pkg => {
                if (!validationPackageIds.has(pkg.id) &&
                    (pkg.validation_status === 'pending' || !pkg.validation_status)) {
                    dismissedPackages.add(pkg.id);
                }
            });
        }

        // Filter out installed packages for the Available tab
        const availablePackages = packages.filter(pkg => !pkg.is_installed);

        console.log('📦 Found', packages.length, 'total packages,', availablePackages.length, 'available for installation');
        console.log('📦 Dismissed packages:', Array.from(dismissedPackages));

        if (availablePackages.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem 1rem;">
                    <div style="color: #6c757d; font-size: 1.1em; margin-bottom: 0.75rem;">
                        <i class="bi bi-box-seam" style="font-size: 1.5rem; display: block; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                        No packages available for installation
                    </div>
                    <p style="color: #6c757d; margin-bottom: 1.5rem; font-size: 0.95em;">
                        ${packages.length > 0 ? 'All uploaded packages are already installed.' : 'Upload a .hubpkg file to get started.'}
                    </p>
                    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                        <button class="btn btn-primary" onclick="document.querySelector('input[type=file]').click()">
                            <i class="bi bi-upload"></i> Upload Package
                        </button>
                        <button class="btn btn-outline-primary" onclick="showPackageDiscovery()">
                            <i class="bi bi-search"></i> Search Package Repository
                        </button>
                    </div>
                </div>
            `;
            return;
        }

        let html = '<table class="data-table"><thead><tr>';
        html += '<th>Package Name</th>';
        html += '<th>Version</th>';
        html += '<th>Status</th>';
        html += '<th>Uploaded</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';

        availablePackages.forEach(pkg => {
            const canInstall = pkg.can_install;
            // isInstalled is always false since we filtered them out

            html += '<tr>';
            html += `<td><strong>${escapeHtml(pkg.display_name)}</strong><br><small style="color: #6c757d;">${escapeHtml(pkg.package_id)}</small></td>`;
            html += `<td><span class="badge badge-info">${escapeHtml(pkg.version)}</span></td>`;
            html += '<td>';

            if (pkg.validation_status === 'pending' || !pkg.validation_status) {
                html += '<span class="badge badge-warning">🔍 Awaiting Validation</span>';
            } else if (canInstall) {
                html += '<span class="badge badge-success">✓ Validated - Ready</span>';
            } else {
                html += '<span class="badge badge-danger">✗ Validation Failed</span>';
            }

            html += '</td>';
            html += `<td>${formatDate(pkg.uploaded_at)}</td>`;
            html += '<td>';

            // Validate button (primary action for unvalidated packages)
            if (pkg.validation_status === 'pending' || !pkg.validation_status) {
                html += `<button class="btn btn-sm btn-primary" onclick="validatePackage(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                    Validate Package
                </button>`;
            }
            // View Report button (for already validated packages)
            else {
                html += `<button class="btn btn-sm ${canInstall ? 'btn-success' : 'btn-warning'}" onclick="showValidationDetails(${pkg.id})">
                    ${canInstall ? 'View Report' : 'View Issues'}
                </button>`;
            }

            if (canInstall && pkg.validation_status !== 'pending') {
                html += `<button class="btn btn-sm btn-primary" onclick="installPackage(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                    Install
                </button>`;
            }

            if (!pkg.is_installed) {
                html += `<button class="btn btn-sm btn-danger" onclick="deletePackage(${pkg.id}, '${escapeHtml(pkg.display_name)}')">
                    Delete
                </button>`;

                // Add dismiss button only for packages needing validation that haven't been dismissed
                if ((pkg.validation_status === 'pending' || !pkg.validation_status) && !dismissedPackages.has(pkg.id)) {
                    html += `<button class="btn btn-sm btn-secondary" onclick="dismissPackageRow(${pkg.id}, '${escapeHtml(pkg.display_name)}', 'package_validation', event)" title="Don't show this alert again">
                        <i class="bi bi-x-circle"></i> Dismiss Alert
                    </button>`;
                }
            }

            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        // Add "Search Repository" button after the table
        html += `
            <div class="find-more-packages-section" style="text-align: center; margin-top: 1.5rem; padding: 1.25rem;">
                <button class="btn btn-outline-primary" onclick="showPackageDiscovery()">
                    <i class="bi bi-search"></i> Search Package Repository
                </button>
                <div style="margin-top: 0.75rem; color: #6c757d; font-size: 0.875rem;">
                    <i class="bi bi-cloud-download"></i> Find and download additional packages from the online repository
                </div>
            </div>
        `;

        container.innerHTML = html;

        // Force reflow/repaint to ensure content is visible
        void container.offsetHeight;

        console.log('✅ Available packages table rendered with', availablePackages.length, 'packages (', packages.length - availablePackages.length, 'installed packages filtered out)');

        // Update notification badge for packages needing validation
        // Only show if not dismissed (respect user's dismiss action)
        const validationCount = alertsResult.success && !alertsResult.alerts.validation.dismissed
            ? alertsResult.alerts.validation.count
            : 0;
        updatePackageBadge('availablePackagesBadge', validationCount);

        // Re-trigger table row animations (fix for packages appearing but not animating)
        if (window.AdminAnimations && availablePackages.length > 0) {
            console.log('🎬 Re-triggering table animations for', availablePackages.length, 'packages');
            setTimeout(() => {
                window.AdminAnimations.animateTableRows('#availablePackagesTable table', 60);
            }, 100);
        }

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

        const timestamp = Date.now();
        const response = await fetch(`/api/packages.php?action=updates&_=${timestamp}`);
        const result = await response.json();

        if (!result.success) throw new Error(result.error);

        const updates = result.updates;

        if (updates.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 2rem 1rem; color: #4CAF50;">
                    <i class="bi bi-check-circle" style="font-size: 1.5rem; margin-bottom: 0.75rem; opacity: 0.7;"></i>
                    <p style="margin: 0; font-size: 1rem;">✓ All packages are up to date!</p>
                </div>
            `;
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
        console.log('📦 Installing package:', packageId, packageName);
        console.log('🔍 CSRF token available:', !!window.csrfToken, window.csrfToken?.substring(0, 10) + '...');

        showMessage('Installing package...', 'info');

        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);

        const response = await fetch(`/api/packages.php?action=install&id=${packageId}`, {
            method: 'POST',
            body: formData
        });

        console.log('🔍 Install response status:', response.status);

        let result;
        try {
            result = await response.json();
            console.log('🔍 Install response:', result);
        } catch (parseError) {
            const responseText = await response.text();
            console.error('🔍 Failed to parse response as JSON:', responseText);
            throw new Error('Server returned invalid response: ' + responseText);
        }

        if (result.success) {
            showMessage(result.message, 'success');
            // Small delay to ensure DB has updated before refreshing lists
            console.log('📦 Package installed, refreshing lists in 500ms...');
            setTimeout(() => {
                console.log('📦 Calling loadInstalledPackages() and loadAvailablePackages() now...');
                loadInstalledPackages();
                loadAvailablePackages();

                // Switch to Installed Packages subtab so user can see the newly installed package
                const installedSubtab = document.querySelector('[data-subtab="installed-packages"]');
                if (installedSubtab) {
                    installedSubtab.click();
                    console.log('📦 Switched to Installed Packages subtab');
                }
            }, 500);
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
            // Small delay to ensure DB has updated before refreshing lists
            console.log('🔄 Package upgraded, refreshing lists in 500ms...');
            setTimeout(() => {
                console.log('🔄 Calling loadInstalledPackages() and loadPackageUpdates() now...');
                loadInstalledPackages();
                loadPackageUpdates();
            }, 500);
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
    let validationAborted = false;

    try {
        console.log('🎯 validatePackage START - packageId:', packageId, 'packageName:', packageName);
        console.log('🔍 DEBUG: Function called, about to create modal');

        // Check if modal already exists
        const existingModal = document.getElementById('validationModal');
        if (existingModal) {
            console.log('🔍 DEBUG: Removing existing modal');
            existingModal.remove();
        }

        // Create MicroModal structure (matches our new standard)
        const modalHtml = `
            <div class="modal micromodal-slide" id="validationModal" aria-hidden="true">
                <div class="modal__overlay" tabindex="-1" data-micromodal-close>
                    <div class="modal__container modal-compact" role="dialog" aria-modal="true" aria-labelledby="validationModalLabel">
                        <header class="modal__header">
                            <h5 class="modal-title" id="validationModalLabel" style="display: flex; align-items: center; gap: 8px;">
                                <i class="bi bi-clipboard-check"></i> <span id="validationTitleText">Validating ${escapeHtml(packageName)}</span>
                            </h5>
                            <button type="button" class="modal__close" data-micromodal-close aria-label="Close">
                                <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </header>
                        <main class="modal__content">
                            <!-- Compact Progress Section -->
                            <div class="validation-progress-compact">
                                <div class="progress-bar-wrapper">
                                    <div class="progress-bar" id="validationProgressBar"></div>
                                </div>
                                <div class="validation-status" id="validationLiveStats">
                                    <i class="bi bi-hourglass-split spin-icon"></i>
                                    <span>Running validation...</span>
                                </div>
                            </div>

                            <!-- Compact Checks Grid (4 columns for better space usage) -->
                            <div class="validation-checks-compact" id="validationChecksContainer">
                                <div class="checks-grid" id="validationChecksList">
                                    <!-- Checks will be pre-populated here in grid -->
                                </div>
                            </div>
                        </main>
                        <footer class="modal__footer">
                            <button type="button" class="btn btn-secondary" data-micromodal-close>
                                <i class="bi bi-x-circle"></i> Close
                            </button>
                        </footer>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        console.log('🔍 DEBUG: Modal HTML added to DOM');

        const modalElement = document.getElementById('validationModal');
        if (!modalElement) {
            console.error('❌ DEBUG: Modal not found in DOM after insertion!');
            return;
        }

        // Track validation completion state
        let validationComplete = false;

        // Initialize MicroModal (not Bootstrap!)
        MicroModal.show('validationModal', {
            disableScroll: true,
            awaitCloseAnimation: true,
            onClose: (modal) => {
                console.log('🔍 DEBUG: Modal closed via MicroModal');

                // Show toast if validation not complete
                if (!validationComplete && !validationAborted) {
                    console.log('⚠️ Validation still running when modal closed');
                    if (window.notyf) {
                        window.notyf.open({
                            type: 'info',
                            message: '⏳ Validation is still running in the background...',
                            duration: 4000
                        });
                    } else {
                        showMessage('Validation is still running in the background...', 'info');
                    }
                }

                // Remove focus from any modal elements before closing to prevent ARIA warning
                if (document.activeElement && modal.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
                setTimeout(() => modal.remove(), 100);
            }
        });

        console.log('✅ Modal shown with MicroModal');

        // Helper function to check if modal still exists
        const isModalOpen = () => {
            const modal = document.getElementById('validationModal');
            if (!modal || !document.body.contains(modal)) {
                console.log('⚠️ Modal was closed, aborting validation updates');
                validationAborted = true;
                return false;
            }
            return true;
        };

        const progressBar = document.getElementById('validationProgressBar');
        const liveStats = document.getElementById('validationLiveStats');
        const checksContainer = document.getElementById('validationChecksContainer');
        const checksList = document.getElementById('validationChecksList');

        // Header/footer close buttons - prevent closing during validation
        const closeButtons = modalElement.querySelectorAll('.modal__close, button[data-micromodal-close]');

        closeButtons.forEach(btn => {
            // Store the original close attribute so we can restore it
            if (btn.hasAttribute('data-micromodal-close')) {
                btn.setAttribute('data-original-close', 'true');
            }

            // Remove the close attribute to prevent MicroModal from closing
            btn.removeAttribute('data-micromodal-close');

            // Add visual indicator that close is disabled
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
            btn.title = 'Please wait for validation to complete';
        });

        // Helper function to re-enable close buttons
        const enableCloseButtons = () => {
            closeButtons.forEach(btn => {
                // Restore the close attribute if it was originally there
                if (btn.hasAttribute('data-original-close')) {
                    btn.setAttribute('data-micromodal-close', '');
                    btn.removeAttribute('data-original-close');
                }

                // Restore visual state
                btn.style.opacity = '';
                btn.style.cursor = '';
                btn.title = '';
                btn.disabled = false;
            });
        };

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
                    <span class="check-icon"></span>
                    <span class="check-label">${escapeHtml(checkName)}</span>
                </div>
            `;
            checksList.insertAdjacentHTML('beforeend', checkHtml);
        });
        console.log(`✅ Pre-populated ${standardChecks.length} standard checks`);

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
        console.log('🔍 DEBUG: About to send validation request to API...');
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);

        console.log('🔍 DEBUG: FormData created, making fetch request...');
        const response = await fetch(`/api/packages.php?action=validate&id=${packageId}`, {
            method: 'POST',
            body: formData
        });

        console.log('🔍 DEBUG: Fetch completed, response status:', response.status);

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
            enableCloseButtons();
            validationComplete = true; // Mark as complete so toast doesn't show
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
            enableCloseButtons();
            validationComplete = true; // Mark as complete so toast doesn't show
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
            enableCloseButtons();
            validationComplete = true; // Mark as complete so toast doesn't show
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
            enableCloseButtons();
            validationComplete = true; // Mark as complete so toast doesn't show
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
            enableCloseButtons();
            validationComplete = true; // Mark as complete so toast doesn't show
            return;
        }

        if (detailsResult.success) {
            console.log('Processing validation details...', detailsResult);
            const summary = detailsResult.summary;
            const checks = detailsResult.all_checks || [];

            console.log('Summary:', summary);
            console.log('Checks count:', checks.length);

            // Update header with result - check if modal still exists
            if (!isModalOpen()) return;
            const titleElement = document.querySelector('.validation-report-title');
            if (titleElement) {
                titleElement.innerHTML = `
                    <i class="bi bi-clipboard-check"></i>
                    Validating Package...
                `;
                console.log('Header updated');
            }

            // Don't clear - keep the pre-populated checkboxes
            console.log('Using pre-populated checks');

            // Show initial progress message - check if modal still exists
            if (!isModalOpen()) return;
            if (liveStats) {
                liveStats.innerHTML = `
                    <span class="stat-item stat-running">
                        <i class="bi bi-hourglass-split"></i>
                        Running validation checks...
                    </span>
                `;
            }

            // Map API results to pre-populated checkboxes
            const allChecks = checks;

            // Now animate checking them one by one
            let checkIndex = 0;

            // Show checks progressively with delay
            const showNextCheck = async () => {
                // Check if modal was closed
                if (!isModalOpen()) {
                    console.log('⚠️ Modal closed during validation, stopping check animation');
                    return;
                }

                if (checkIndex >= allChecks.length) {
                    // All checks shown, update final state with celebration
                    if (!isModalOpen()) return;
                    if (progressBar) progressBar.style.width = '100%';

                    // Show "Finalizing..." message first
                    if (!isModalOpen()) return;
                    if (liveStats) {
                        liveStats.innerHTML = `
                            <span class="stat-item stat-running">
                                <i class="bi bi-stars" style="color: #ffc107;"></i>
                                Finalizing validation...
                            </span>
                        `;
                    }

                    // Wait 2 seconds for dramatic effect
                    await new Promise(resolve => setTimeout(resolve, 2000));

                    // Check again after delay
                    if (!isModalOpen()) return;

                    // Now show final results with celebration
                    if (liveStats) {
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
                    }

                    // Wait another 1.5 seconds before showing install button
                    await new Promise(resolve => setTimeout(resolve, 1500));

                    // Check again after delay
                    if (!isModalOpen()) return;

                    // Add install button if validation passed
                    if (summary.failed === 0 && summary.critical === 0) {
                        console.log('Adding install button with animation');
                        const modalFooter = document.querySelector('#validationModal .modal__footer');
                        if (modalFooter) {
                            modalFooter.innerHTML = `
                                <button type="button" class="btn btn-primary" id="modalInstallBtn" style="animation: buttonPulse 0.6s ease;">
                                    <i class="bi bi-download"></i> Install Package
                                </button>
                                <button type="button" class="btn btn-secondary" data-micromodal-close>
                                    <i class="bi bi-x-circle"></i> Close
                                </button>
                            `;

                            // Add event listener to install button
                            const installButton = document.getElementById('modalInstallBtn');
                            if (installButton) {
                                installButton.onclick = function () {
                                    console.log('Install clicked');
                                    closeValidationModal();
                                    installPackage(packageId, packageName);
                                };
                            }
                        }
                    }

                    // Re-enable close buttons after validation completes
                    enableCloseButtons();

                    // Mark validation as complete (prevents toast on close)
                    validationComplete = true;
                    console.log('✅ Validation marked as complete');

                    // Update final header with animation - check if modal still exists
                    if (!isModalOpen()) return;
                    const titleElement = document.querySelector('#validationModalLabel span');
                    if (titleElement) {
                        titleElement.innerHTML = `${summary.failed === 0 ? 'Package Validated - Ready to Install! 🚀' : 'Validation Failed'}`;
                    }

                    // Reload packages list IMMEDIATELY to update button states
                    if (!isModalOpen()) return;
                    console.log('🔄 Reloading packages list to show updated status...');
                    await loadAvailablePackages();

                    // Force rows to be visible immediately (CSS has opacity: 0 by default)
                    setTimeout(() => {
                        if (!isModalOpen()) return;
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
                        if (!isModalOpen()) return;
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
        console.error('🔍 DEBUG: validatePackage caught error:', error);
        console.error('🔍 DEBUG: Error stack:', error.stack);
        console.log('🔍 DEBUG: Error occurred, modal should still be visible');

        showMessage('Validation error: ' + error.message, 'error');

        validationComplete = true; // Mark as complete so toast doesn't show

        // Update modal to show error state
        const liveStats = document.getElementById('validationLiveStats');
        if (liveStats) {
            liveStats.innerHTML = `
                <span class="stat-item stat-error">
                    <i class="bi bi-x-circle"></i>
                    Error: ${error.message}
                </span>
            `;
        }
    }
}

function closeValidationModal() {
    console.log('🔍 DEBUG: closeValidationModal called');

    const modalElement = document.getElementById('validationModal');
    if (modalElement) {
        console.log('🔍 DEBUG: Closing modal with MicroModal');

        // Use MicroModal API to close
        MicroModal.close('validationModal');

        // Clean up after animation completes
        setTimeout(() => {
            if (modalElement && document.body.contains(modalElement)) {
                modalElement.remove();
            }
        }, 300); // Match MicroModal animation time

        console.log('🔍 DEBUG: Modal close initiated');
    } else {
        console.log('🔍 DEBUG: No modal found to close');
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
            // Small delay to ensure DB has updated before refreshing lists
            console.log('🗑️  Package uninstalled, refreshing lists in 500ms...');
            setTimeout(() => {
                console.log('🗑️  Calling loadInstalledPackages() and loadAvailablePackages() now...');
                loadInstalledPackages();
                loadAvailablePackages();

                // Switch to Available Packages subtab so user can see the uninstalled package
                const availableSubtab = document.querySelector('[data-subtab="available-packages"]');
                if (availableSubtab) {
                    availableSubtab.click();
                    console.log('🗑️  Switched to Available Packages subtab');
                }
            }, 500);
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
            showMessage('Package deleted successfully', 'success');
            // Small delay to ensure filesystem/DB has updated
            console.log('🗑️  Package deleted, refreshing lists in 500ms...');
            setTimeout(() => {
                console.log('🗑️  Reloading both installed and available packages...');
                loadInstalledPackages();
                loadAvailablePackages();
            }, 500);
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
        // Show loading state
        ModalRenderer.show('packageValidationModal', {
            title: '<i class="bi bi-hourglass-split"></i> Loading Validation Report...',
            body: `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Fetching package validation data...</p>
                </div>
            `,
            footer: `
                <button type="button" class="btn btn-secondary" data-micromodal-close>
                    <i class="bi bi-x-circle"></i> Close
                </button>
            `
        });

        // Fetch validation data with cache-busting
        const timestamp = Date.now();
        const response = await fetch(`/api/packages.php?action=validation&id=${packageId}&_=${timestamp}`);
        const result = await response.json();

        if (!result.success) throw new Error(result.error);

        const pkg = result.package;
        const summary = result.summary;
        const checks = result.all_checks;

        const summaryClass = pkg.can_install ? 'success' : (pkg.validation_status === 'pending' ? 'pending' : 'failure');
        const summaryIcon = pkg.can_install ? '✓' : (pkg.validation_status === 'pending' ? '⏳' : '✗');
        const summaryText = pkg.can_install ? 'Package Validated - Ready to Install' :
            (pkg.validation_status === 'pending' ? 'Running Complete Validation...' : 'Validation Failed - Installation Blocked');

        // Group checks by type
        const groupedChecks = {};
        checks.forEach(check => {
            if (!groupedChecks[check.check_type]) {
                groupedChecks[check.check_type] = [];
            }
            groupedChecks[check.check_type].push(check);
        });

        // Build compact grid HTML for checks
        let checksHTML = '<div class="row g-3">';

        Object.keys(groupedChecks).forEach(checkType => {
            const typeChecks = groupedChecks[checkType];
            const typePassed = typeChecks.filter(c => c.status === 'pass').length;
            const typeFailed = typeChecks.filter(c => c.status === 'fail').length;
            const typeIcon = typeFailed > 0 ? '✗' : typePassed === typeChecks.length ? '✓' : '⚠';
            const typeColor = typeFailed > 0 ? 'danger' : typePassed === typeChecks.length ? 'success' : 'warning';

            // Each check type gets its own column (responsive: 1 col mobile, 2 col tablet, 3 col desktop)
            checksHTML += `
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 border-${typeColor}">
                        <div class="card-header bg-${typeColor} bg-opacity-10 py-2">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-${typeColor} me-2">${typeIcon}</span>
                                <strong class="small">${escapeHtml(checkType.toUpperCase().replace(/_/g, ' '))}</strong>
                                <small class="ms-auto text-muted">${typePassed}/${typeChecks.length}</small>
                            </div>
                        </div>
                        <div class="card-body p-2 validation-card-body">`;

            typeChecks.forEach(check => {
                const icon = check.status === 'fail' ? '✗' : check.status === 'warning' ? '⚠' : '✓';
                const statusColor = check.status === 'fail' ? 'danger' : check.status === 'warning' ? 'warning' : 'success';

                checksHTML += `
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex align-items-start">
                            <span class="badge bg-${statusColor} me-2 flex-shrink-0" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">${icon}</span>
                            <div class="small">
                                <div class="fw-bold">${escapeHtml(check.check_name)}</div>
                                <div class="text-muted" style="font-size: 0.85rem;">${escapeHtml(check.message)}</div>
                                ${check.resolution ? `<div class="text-success mt-1" style="font-size: 0.85rem;">
                                    <strong>Fix:</strong> ${escapeHtml(check.resolution)}
                                </div>` : ''}
                            </div>
                        </div>
                    </div>`;
            });

            checksHTML += `
                        </div>
                    </div>
                </div>`;
        });

        checksHTML += '</div>';

        // Build modal body HTML
        const bodyHTML = `
            <!-- Package Header -->
            <div class="row mb-3">
                <div class="col">
                    <h4 class="mb-0">${escapeHtml(pkg.display_name)} <span class="badge bg-secondary">${escapeHtml(pkg.version)}</span></h4>
                </div>
            </div>

            ${pkg.validation_status === 'pending' ? `
            <div class="alert alert-info d-flex align-items-center mb-3 py-2">
                <span class="me-3" style="font-size: 1.5rem;">⏳</span>
                <div>
                    <strong>Running Complete Validation...</strong>
                    <div class="mt-1 small">All ${summary.total_checks || 0} checks are being performed.</div>
                </div>
            </div>
            ` : ''}

            <!-- Validation Summary -->
            <div class="alert alert-${summaryClass === 'success' ? 'success' : summaryClass === 'failure' ? 'danger' : 'warning'} d-flex align-items-center mb-3 py-2">
                <span class="me-3" style="font-size: 1.5rem;">${summaryIcon}</span>
                <div class="flex-grow-1">
                    <strong>${summaryText}</strong>
                    <div class="mt-1 small">
                        <span class="badge bg-success me-1">${summary.passed || 0} passed</span>
                        <span class="badge bg-danger me-1">${summary.failed || 0} failed</span>
                        <span class="badge bg-warning text-dark">${summary.warnings || 0} warnings</span>
                        ${(summary.critical || 0) > 0 ? `<span class="badge bg-danger ms-1">⚠️ ${summary.critical} critical</span>` : ''}
                    </div>
                </div>
            </div>

            <!-- Compatibility Checks Grid -->
            <h6 class="mb-2">
                <i class="bi bi-list-check text-primary me-2"></i>
                Compatibility Checks <small class="text-muted">(${checks.length} total)</small>
            </h6>

            ${checksHTML}
        `;

        // Build footer HTML
        const footerHTML = `
            <button type="button" class="btn btn-secondary" data-micromodal-close>
                <i class="bi bi-x-circle"></i> Close
            </button>
            ${pkg.can_install && !result.package.is_installed ?
                `<button type="button" class="btn btn-primary" onclick="installPackage(${pkg.id}, '${escapeHtml(pkg.display_name)}'); ModalRenderer.hide('packageValidationModal');">
                <i class="bi bi-download"></i> Install Package
            </button>` : ''}
        `;

        // Update modal with full content
        ModalRenderer.update('packageValidationModal', {
            title: `<i class="bi bi-clipboard-check"></i> ${pkg.validation_status === 'pending' ? '⏳ Validation In Progress' : 'Package Validation Report'}`,
            body: bodyHTML,
            footer: footerHTML
        });

    } catch (error) {
        showMessage('Error loading validation: ' + error.message, 'error');
        ModalRenderer.hide('packageValidationModal');
    }
}// Show modal with custom content (DEPRECATED - keeping for backwards compatibility)
function showModalWithContent(htmlContent) {
    // Remove existing modal if any
    let modal = document.getElementById('dynamicModal');
    if (modal) modal.remove();

    // Create modal backdrop
    modal = document.createElement('div');
    modal.id = 'dynamicModal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 1.5rem; transition: background 0.3s ease; opacity: 0;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background: white; border-radius: 12px; max-width: 900px; width: 100%; max-height: 90vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3); transform: scale(0.9); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); opacity: 0;';
    modalContent.innerHTML = htmlContent;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Trigger animations
    requestAnimationFrame(() => {
        modal.style.background = 'rgba(0,0,0,0.5)';
        modal.style.opacity = '1';
        modalContent.style.transform = 'scale(1)';
        modalContent.style.opacity = '1';
    });

    // Add hover effect to close button
    setTimeout(() => {
        const closeBtn = modalContent.querySelector('.modal-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('mouseenter', function () {
                this.style.background = 'rgba(255,255,255,0.3)';
                this.style.transform = 'rotate(90deg)';
            });
            closeBtn.addEventListener('mouseleave', function () {
                this.style.background = 'rgba(255,255,255,0.2)';
                this.style.transform = 'rotate(0deg)';
            });
        }
    }, 100);

    // Close on background click
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    // Close on ESC key
    const escHandler = (e) => {
        if (e.key === 'Escape') {
            closeModal();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

// Close modal with animation
function closeModal() {
    const modal = document.getElementById('dynamicModal');
    if (!modal) return;

    const modalContent = modal.querySelector('div');

    // Animate out
    modal.style.background = 'rgba(0,0,0,0)';
    modal.style.opacity = '0';
    if (modalContent) {
        modalContent.style.transform = 'scale(0.9)';
        modalContent.style.opacity = '0';
    }

    // Remove after animation
    setTimeout(() => {
        if (modal.parentNode) {
            modal.remove();
        }
    }, 300);
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
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// ============================================================================
// PACKAGE ALERTS SYSTEM
// ============================================================================

// Check for package alerts and display if not dismissed
async function checkPackageAlerts(showAlerts = true) {
    try {
        const response = await fetch('/api/package-alerts.php?action=check'/*, {
            credentials: 'same-origin'
        }*/);
        const result = await response.json();

        if (!result.success) return;

        const { validation, installed, updates } = result.alerts;

        // Always update sidebar badge and subtab badges
        updatePackageAlertBadge(validation, updates);
        updateSubtabBadges(validation, installed, updates);

        // Only show alert banners if requested
        if (!showAlerts) return;

        // Alert banners are no longer used - badges are sufficient
        // The alerts are now shown through the subtab badges and sidebar badge

    } catch (error) {
        console.error('Error checking package alerts:', error);
    }
}

// Update the sidebar badge with alert counts
function updatePackageAlertBadge(validation, updates) {
    const sidebarBadge = document.getElementById('sidebarPackageBadge');
    if (sidebarBadge) {
        const totalAlerts = (validation.dismissed ? 0 : validation.count) + (updates.dismissed ? 0 : updates.count);

        if (totalAlerts > 0) {
            // Show red dot indicator (no number) on sidebar
            sidebarBadge.textContent = '';  // Empty - just a dot
            sidebarBadge.classList.add('dot');
            sidebarBadge.style.display = 'inline-block';
        } else {
            sidebarBadge.textContent = '';
            sidebarBadge.classList.remove('dot');
            sidebarBadge.style.display = 'none';
        }
    }
}

// Update the subtab badges with counts
function updateSubtabBadges(validation, installed, updates) {
    // Installed Packages badge - NEVER show a badge (it's just informational)
    const installedBadge = document.getElementById('installedPackagesBadge');
    if (installedBadge) {
        installedBadge.style.display = 'none';
    }

    // Available Packages badge (shows validation count, can be dismissed)
    const availableBadge = document.getElementById('availablePackagesBadge');
    if (availableBadge) {
        if (validation.count > 0 && !validation.dismissed) {
            availableBadge.textContent = validation.count;
            availableBadge.style.display = 'inline-block';
        } else {
            availableBadge.style.display = 'none';
        }
    }

    // Updates badge (can be dismissed)
    const updatesBadge = document.getElementById('updatesBadge');
    if (updatesBadge) {
        if (updates.count > 0 && !updates.dismissed) {
            updatesBadge.textContent = updates.count;
            updatesBadge.style.display = 'inline-block';
        } else {
            updatesBadge.style.display = 'none';
        }
    }
}

// Dismiss a package alert
async function dismissPackageAlert(alertType) {
    try {
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        formData.append('alert_type', alertType);

        const response = await fetch('/api/package-alerts.php?action=dismiss', {
            method: 'POST',
            // credentials: 'same-origin',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Fade out and remove the alert banner
            const alert = document.querySelector(`.package-alert[data-alert-type="${alertType}"]`);
            if (alert) {
                alert.classList.add('hiding');
                setTimeout(() => alert.remove(), 300);
            }

            // Update badges to hide dismissed alerts
            if (alertType === 'package_validation') {
                const badge = document.getElementById('availablePackagesBadge');
                if (badge) badge.style.display = 'none';
            } else if (alertType === 'package_updates') {
                const badge = document.getElementById('updatesBadge');
                if (badge) badge.style.display = 'none';
            }

            // Update sidebar badge
            const sidebarBadge = document.getElementById('sidebarPackageBadge');
            if (sidebarBadge) {
                const currentCount = parseInt(sidebarBadge.textContent) || 0;
                const newCount = currentCount - 1;
                if (newCount > 0) {
                    sidebarBadge.textContent = newCount;
                } else {
                    sidebarBadge.style.display = 'none';
                }
            }

            showMessage('Alert dismissed for 7 days', 'success');
        }
    } catch (error) {
        console.error('Error dismissing alert:', error);
    }
}

// Dismiss a specific package row alert
async function dismissPackageRow(packageId, packageName, alertType, event) {
    // Prevent event bubbling
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }

    try {
        const formData = new FormData();
        formData.append('csrf_token', window.csrfToken);
        formData.append('alert_type', alertType);
        formData.append('package_id', packageId);

        const response = await fetch('/api/package-alerts.php?action=dismiss', {
            method: 'POST',
            // credentials: 'same-origin',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            // Simply hide the dismiss button for this package
            const dismissBtn = event.target.closest('button');
            if (dismissBtn) {
                dismissBtn.style.display = 'none';
            }

            // Update the available packages badge
            const availableBadge = document.getElementById('availablePackagesBadge');
            if (availableBadge) {
                const currentCount = parseInt(availableBadge.textContent) || 0;
                const newCount = currentCount - 1;
                if (newCount > 0) {
                    availableBadge.textContent = newCount;
                } else {
                    availableBadge.style.display = 'none';
                }
            }

            // Update sidebar badge
            const sidebarBadge = document.getElementById('sidebarPackageBadge');
            if (sidebarBadge) {
                const currentCount = parseInt(sidebarBadge.textContent) || 0;
                const newCount = currentCount - 1;
                if (newCount > 0) {
                    sidebarBadge.textContent = newCount;
                } else {
                    sidebarBadge.style.display = 'none';
                }
            }

            if (typeof showMessage === 'function') {
                showMessage(`Alert dismissed for "${packageName}"`, 'success');
            } else {
                console.log(`Alert dismissed for package "${packageName}" (ID: ${packageId})`);
            }
        } else {
            const errorMsg = 'Failed to dismiss alert: ' + (result.error || 'Unknown error');
            if (typeof showMessage === 'function') {
                showMessage(errorMsg, 'error');
            } else {
                console.error(errorMsg);
            }
        }
    } catch (error) {
        console.error('Error dismissing package row alert:', error);
        if (typeof showMessage === 'function') {
            showMessage('Failed to dismiss alert', 'error');
        }
    }
}

// ============================================================================
// Package Discovery Functions
// ============================================================================

function showPackageDiscovery() {
    // Initialize global state
    window.discoveredPackages = [];
    window.selectedPackages = new Set();

    // Build content HTML
    const bodyHTML = buildPackageDiscoveryBodyHTML();
    const footerHTML = buildPackageDiscoveryFooterHTML();

    // Show modal using ModalRenderer
    ModalRenderer.show('packageDiscoveryModal', {
        title: '<i class="bi bi-box-seam text-primary"></i> Browse Available Packages',
        body: bodyHTML,
        footer: footerHTML,
        onShow: () => {
            // Attach event listeners after content is rendered
            attachPackageDiscoveryListeners();
            // Start loading packages (increased delay for MicroModal DOM rendering)
            setTimeout(() => searchPackages(), 300);
        },
        onHide: () => {
            // Cleanup global state
            window.discoveredPackages = [];
            window.selectedPackages = new Set();
        }
    });
}

// Build the modal body HTML
function buildPackageDiscoveryBodyHTML() {
    return `
        <!-- Compact Filters & Search -->
        <div class="package-filters mb-2" style="background: #f8f9fa; padding: 0.75rem; border-radius: 0.375rem;">
            <!-- Search Bar -->
            <div class="mb-2">
                <input
                    type="text"
                    id="packageSearchInput"
                    class="form-control form-control-sm"
                    placeholder="🔍 Search packages..."
                    style="font-size: 0.875rem;">
            </div>
            <!-- Compact Tag Filters -->
            <div class="d-flex align-items-center justify-content-between" style="min-height: 28px;">
                <div class="d-flex align-items-center flex-grow-1 gap-2">
                    <small class="text-muted fw-bold" style="font-size: 0.75rem; white-space: nowrap;">Tags:</small>
                    <div id="tagFilters" class="d-flex flex-wrap gap-1 flex-grow-1">
                        <small class="text-muted" style="font-size: 0.75rem;">Loading...</small>
                    </div>
                </div>
                <button id="clearTagFilters" class="btn btn-sm btn-link text-decoration-none p-0 ms-2" style="font-size: 0.75rem; display: none; white-space: nowrap;">
                    Clear All
                </button>
            </div>
        </div>

        <!-- Results -->
        <div id="packageSearchResults" class="package-discovery-results">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-2">Loading available packages...</div>
            </div>
        </div>
    `;
}

// Build the modal footer HTML
function buildPackageDiscoveryFooterHTML() {
    return `
        <div class="me-auto">
            <span id="selectedPackageCount" style="display: none;">
                <strong>0</strong> package(s) selected
            </span>
        </div>
        <button type="button" id="downloadSelectedBtn" class="btn btn-primary" style="display: none;">
            <i class="bi bi-download"></i> Download Selected (<span id="downloadCount">0</span>)
        </button>
        <button type="button" class="btn btn-secondary" data-micromodal-close>
            <i class="bi bi-x-circle"></i> Close
        </button>
    `;
}

// Attach event listeners to modal elements
function attachPackageDiscoveryListeners() {
    const searchInput = document.getElementById('packageSearchInput');
    const downloadBtn = document.getElementById('downloadSelectedBtn');

    if (searchInput) {
        searchInput.addEventListener('input', filterDiscoveredPackages);
    }

    if (downloadBtn) {
        downloadBtn.addEventListener('click', downloadSelectedPackages);
    }
}

async function searchPackages() {
    // Use the default Hub package repository
    const repositoryUrl = 'https://github.com/R1CH4RD25/TheHub-Package-Repo';
    const owner = 'R1CH4RD25';
    const repo = 'TheHub-Package-Repo';

    const resultsContainer = document.getElementById('packageSearchResults');

    // Safety check: element should exist after ModalRenderer injects content
    if (!resultsContainer) {
        console.error('❌ packageSearchResults not found! ModalRenderer may have failed to inject body content.');
        return;
    }

    try {
        resultsContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="mt-2">Loading available packages...</div>
            </div>
        `;

        const response = await fetch('/api/package-discovery.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({
                action: 'search',
                repository_url: repositoryUrl,
                owner: owner,
                repo: repo
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load packages');
        }

        renderPackageSearchResults(result.packages);

    } catch (error) {
        console.error('Error loading packages:', error);
        resultsContainer.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> Error loading packages: ${error.message}
                <div class="mt-2 small">
                    Please ensure you have internet connectivity and try again.
                </div>
            </div>
        `;
    }
}

function renderPackageSearchResults(packages) {
    const container = document.getElementById('packageSearchResults');

    // Store packages globally for filtering
    window.discoveredPackages = packages || [];

    if (!packages || packages.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-box-seam"></i> <strong>Package Repository Is Empty</strong>
                <div class="mt-3">
                    <p class="mb-2">The package repository hasn't been populated yet. You have two options:</p>
                    <div class="ms-3">
                        <p class="mb-1"><strong>1. Upload Local Packages:</strong></p>
                        <p class="text-muted small mb-3">Use the "Upload Package" section above to install .hubpkg files directly</p>

                        <p class="mb-1"><strong>2. Contribute to the Repository:</strong></p>
                        <p class="text-muted small mb-1">Help build the community package library at:</p>
                        <a href="https://github.com/R1CH4RD25/TheHub-Package-Repo" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-github"></i> View Repository
                        </a>
                    </div>
                </div>
            </div>
        `;
        return;
    }

    // Filter out already installed or downloaded packages
    const availablePackages = packages.filter(pkg => !pkg.is_installed && !pkg.is_downloaded);
    const installedCount = packages.filter(pkg => pkg.is_installed).length;
    const downloadedCount = packages.filter(pkg => pkg.is_downloaded && !pkg.is_installed).length;

    if (availablePackages.length === 0) {
        let message = 'All available packages are already ';
        if (installedCount > 0 && downloadedCount > 0) {
            message += 'installed or downloaded!';
        } else if (installedCount > 0) {
            message += 'installed!';
        } else {
            message += 'downloaded!';
        }

        container.innerHTML = `
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> ${message}
                <div class="mt-2 small text-muted">
                    ${installedCount > 0 ? `${installedCount} installed` : ''}
                    ${installedCount > 0 && downloadedCount > 0 ? ' • ' : ''}
                    ${downloadedCount > 0 ? `${downloadedCount} downloaded (available in "Available Packages" tab)` : ''}
                </div>
            </div>
        `;
        return;
    }

    let html = `
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1">
                    <i class="bi bi-download text-primary"></i>
                    ${availablePackages.length} Package${availablePackages.length !== 1 ? 's' : ''} Available
                </h6>`;

    if (installedCount > 0 || downloadedCount > 0) {
        html += `<small class="text-muted">`;
        if (installedCount > 0) {
            html += `<i class="bi bi-check-lg"></i> ${installedCount} installed`;
        }
        if (installedCount > 0 && downloadedCount > 0) {
            html += ` • `;
        }
        if (downloadedCount > 0) {
            html += `<i class="bi bi-download"></i> ${downloadedCount} already downloaded`;
        }
        html += `</small>`;
    }

    html += `
            </div>
        </div>

        <!-- Package Table - Compact Design -->
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <tr>
                        <th style="width: 40px; padding: 0.5rem 0.25rem; text-align: center;">
                            <input type="checkbox" class="form-check-input" id="selectAllPackages" title="Select All" style="margin: 0;">
                        </th>
                        <th style="width: 20%; padding: 0.5rem;">Package</th>
                        <th style="width: 68%; padding: 0.5rem;">Description</th>
                        <th style="width: 12%; padding: 0.5rem;">Tags</th>
                    </tr>
                </thead>
                <tbody>
    `;

    availablePackages.forEach(pkg => {
        // Use tags from API or extract from path as fallback
        const tags = pkg.tags || [];
        const author = pkg.author || 'WISD';
        const version = pkg.version || 'Unknown';
        const description = pkg.description || 'No description available';
        const isSelected = window.selectedPackages.has(pkg.download_url);

        html += `
            <tr class="package-row" data-tags="${tags.join(',')}" data-package-name="${escapeHtml(pkg.name.toLowerCase())}" style="border-bottom: 1px solid #e9ecef;">
                <td style="padding: 0.4rem 0.25rem; text-align: center; vertical-align: middle;">
                    <input
                        class="form-check-input package-checkbox"
                        type="checkbox"
                        id="pkg_${escapeHtml(pkg.id || pkg.name)}"
                        data-download-url="${escapeHtml(pkg.download_url)}"
                        data-package-name="${escapeHtml(pkg.name)}"
                        ${isSelected ? 'checked' : ''}
                        onchange="togglePackageSelection(this)"
                        style="margin: 0;"
                    >
                </td>
                <td style="padding: 0.4rem 0.5rem;">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-box text-primary me-2" style="font-size: 1.1rem;"></i>
                        <div style="flex-grow: 1;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.15rem;">
                                <strong style="font-size: 0.9rem; line-height: 1.3;">${escapeHtml(pkg.name)}</strong>
                                <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.65rem; padding: 0.2em 0.5em;">
                                    v${escapeHtml(version)}
                                </span>
                            </div>
                            <small class="text-muted d-block" style="font-size: 0.7rem;">
                                <i class="bi bi-person" style="font-size: 0.65rem;"></i> ${escapeHtml(author)}
                            </small>
                        </div>
                    </div>
                </td>
                <td style="padding: 0.4rem 0.5rem;">
                    <div style="font-size: 0.8rem; line-height: 1.3; color: #495057;" title="${escapeHtml(description)}">
                        ${escapeHtml(description.length > 120 ? description.substring(0, 120) + '...' : description)}
                    </div>
                </td>
                <td style="padding: 0.4rem 0.5rem;">
                    <div class="d-flex flex-wrap gap-1">
                        ${tags.slice(0, 2).map(tag => `
                            <span class="badge bg-${getTagColor(tag)}" style="font-size: 0.65rem; padding: 0.25em 0.45em;">
                                ${getTagIcon(tag)} ${tag}
                            </span>
                        `).join('')}
                        ${tags.length > 2 ? `<span class="badge bg-secondary" style="font-size: 0.65rem; padding: 0.25em 0.45em; cursor: help;" title="${tags.slice(2).join(', ')}">+${tags.length - 2}</span>` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;

    // Add select all functionality
    setTimeout(() => {
        const selectAllCheckbox = document.getElementById('selectAllPackages');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.package-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                    togglePackageSelection(cb);
                });
            });
        }

        // Add click handler to rows to toggle checkboxes
        const packageRows = document.querySelectorAll('.package-row');
        packageRows.forEach(row => {
            row.addEventListener('click', function (e) {
                // Don't trigger if clicking the checkbox itself
                if (e.target.type === 'checkbox') {
                    return;
                }

                // Find the checkbox in this row
                const checkbox = this.querySelector('.package-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    togglePackageSelection(checkbox);
                }
            });

            // Add cursor pointer style to indicate clickability
            row.style.cursor = 'pointer';
        });
    }, 100);

    // Populate tag filters
    populateTagFilters(packages);

    // Attach event listeners
    attachPackageFilterListeners();
}

// Populate dynamic tag filters based on discovered packages
function populateTagFilters(packages) {
    const tagCounts = {};

    // Count occurrences of each tag
    packages.forEach(pkg => {
        const tags = pkg.tags || [];
        tags.forEach(tag => {
            tagCounts[tag] = (tagCounts[tag] || 0) + 1;
        });
    });

    // Sort tags by count (most common first)
    const sortedTags = Object.entries(tagCounts)
        .sort((a, b) => b[1] - a[1])
        .map(([tag, count]) => ({ tag, count }));

    const tagContainer = document.getElementById('tagFilters');

    if (sortedTags.length === 0) {
        tagContainer.innerHTML = '<small class="text-muted">No tags available</small>';
        return;
    }

    // Store active filters globally
    window.activeTagFilters = window.activeTagFilters || new Set();

    let html = sortedTags.map(({ tag, count }) => {
        const isActive = window.activeTagFilters.has(tag);
        return `
            <button
                class="btn btn-sm tag-filter-btn ${isActive ? 'btn-primary' : 'btn-outline-secondary'}"
                data-tag="${tag}"
                onclick="toggleTagFilter('${tag}')"
                title="${count} package${count !== 1 ? 's' : ''}"
            >
                ${getTagIcon(tag)} ${tag} <span class="badge bg-secondary">${count}</span>
            </button>
        `;
    }).join('');

    tagContainer.innerHTML = html;

    // Show/hide clear button
    document.getElementById('clearTagFilters').style.display =
        window.activeTagFilters.size > 0 ? 'inline-block' : 'none';
}

// Toggle tag filter
function toggleTagFilter(tag) {
    window.activeTagFilters = window.activeTagFilters || new Set();

    if (window.activeTagFilters.has(tag)) {
        window.activeTagFilters.delete(tag);
    } else {
        window.activeTagFilters.add(tag);
    }

    // Update button states
    document.querySelectorAll('.tag-filter-btn').forEach(btn => {
        const btnTag = btn.dataset.tag;
        if (window.activeTagFilters.has(btnTag)) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-secondary');
        }
    });

    // Show/hide clear button
    document.getElementById('clearTagFilters').style.display =
        window.activeTagFilters.size > 0 ? 'inline-block' : 'none';

    // Apply filters
    filterPackages();
}

// Clear all tag filters
function clearAllTagFilters() {
    window.activeTagFilters.clear();

    // Reset all filter buttons
    document.querySelectorAll('.tag-filter-btn').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-secondary');
    });

    document.getElementById('clearTagFilters').style.display = 'none';

    // Apply filters (show all)
    filterPackages();
}

// Filter packages based on search and tags
function filterPackages() {
    const searchTerm = (document.getElementById('packageSearchInput')?.value || '').toLowerCase();
    const activeFilters = window.activeTagFilters || new Set();

    document.querySelectorAll('.package-row').forEach(row => {
        const packageName = row.dataset.packageName || '';
        const packageTags = (row.dataset.tags || '').split(',').filter(t => t);

        // Search filter
        const matchesSearch = !searchTerm || packageName.includes(searchTerm);

        // Tag filter (if any tags are active, package must have at least one matching tag)
        const matchesTags = activeFilters.size === 0 ||
            [...activeFilters].some(filter => packageTags.includes(filter));

        // Show/hide row
        if (matchesSearch && matchesTags) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    // Update visible count
    const visibleCount = document.querySelectorAll('.package-row:not([style*="display: none"])').length;
    const totalCount = document.querySelectorAll('.package-row').length;

    // Could add a status message here if desired
}

// Attach event listeners for filtering
function attachPackageFilterListeners() {
    // Search input
    const searchInput = document.getElementById('packageSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', filterPackages);
    }

    // Clear filters button
    const clearBtn = document.getElementById('clearTagFilters');
    if (clearBtn) {
        clearBtn.onclick = clearAllTagFilters;
    }
}

// Get icon for tag
function getTagIcon(tag) {
    const icons = {
        'student': '🎓',
        'staff': '👥',
        'parent': '👨‍👩‍👧',
        'maintenance': '🔧',
        'safety': '🛡️',
        'reporting': '📋',
        'schoolwide': '🏫',
        'communication': '💬',
        'facilities': '🏢',
        'forms': '📝',
        'workflows': '⚙️',
        'analytics': '📊'
    };
    return icons[tag] || '🏷️';
}

// Get color for tag
function getTagColor(tag) {
    const colors = {
        'student': 'primary',
        'staff': 'info',
        'parent': 'success',
        'maintenance': 'warning',
        'safety': 'danger',
        'reporting': 'secondary',
        'schoolwide': 'dark',
        'communication': 'info',
        'facilities': 'warning',
        'forms': 'success',
        'workflows': 'secondary',
        'analytics': 'primary'
    };
    return colors[tag] || 'secondary';
}

function getCategoryIcon(category) {
    const icons = {
        'analytics': '📊',
        'forms': '📝',
        'integrations': '🔌',
        'redirects': '🔀',
        'reporting': '📋',
        'workflows': '⚙️'
    };
    return icons[category] || '📦';
}

function getCategoryColor(category) {
    const colors = {
        'analytics': 'info',
        'forms': 'success',
        'integrations': 'warning',
        'redirects': 'secondary',
        'reporting': 'primary',
        'workflows': 'dark'
    };
    return colors[category] || 'secondary';
}

function togglePackageSelection(checkbox) {
    if (!checkbox) return;

    const url = checkbox.dataset.downloadUrl;
    const name = checkbox.dataset.packageName;

    if (checkbox.checked) {
        window.selectedPackages.add(url);
        const row = checkbox.closest('tr.package-row') || checkbox.closest('.package-card') || checkbox.closest('.package-row');
        if (row) row.classList.add('table-primary');
    } else {
        window.selectedPackages.delete(url);
        const row = checkbox.closest('tr.package-row') || checkbox.closest('.package-card') || checkbox.closest('.package-row');
        if (row) row.classList.remove('table-primary');
    }

    updateSelectedCount();
}

function updateSelectedCount() {
    const count = window.selectedPackages.size;
    const countSpan = document.getElementById('selectedPackageCount');
    const downloadBtn = document.getElementById('downloadSelectedBtn');
    const downloadCount = document.getElementById('downloadCount');

    if (count > 0) {
        countSpan.style.display = 'inline';
        countSpan.querySelector('strong').textContent = count;
        downloadBtn.style.display = 'inline-block';
        downloadCount.textContent = count;
    } else {
        countSpan.style.display = 'none';
        downloadBtn.style.display = 'none';
    }
}

// NOTE: Select All functionality removed - using individual package selection
// function toggleSelectAll() { ... }

function filterDiscoveredPackages() {
    const searchTerm = document.getElementById('packageSearchInput').value.toLowerCase();

    const filtered = window.discoveredPackages.filter(pkg => {
        // Filter by search term
        if (searchTerm) {
            const matchesName = pkg.name.toLowerCase().includes(searchTerm);
            const matchesDesc = pkg.description && pkg.description.toLowerCase().includes(searchTerm);
            const matchesFilename = pkg.filename.toLowerCase().includes(searchTerm);
            return matchesName || matchesDesc || matchesFilename;
        }

        return true;
    });

    renderPackageSearchResults(filtered);
}

// NOTE: Category filter removed - using tag-based filtering instead
// function populatePackageCategories(packages) { ... }

async function downloadSelectedPackages() {
    const selectedUrls = Array.from(window.selectedPackages);

    if (selectedUrls.length === 0) {
        showMessage('Please select at least one package', 'warning');
        return;
    }

    const btn = document.getElementById('downloadSelectedBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;

    // Create progress overlay
    const progressOverlay = document.createElement('div');
    progressOverlay.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
            <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <div style="display: inline-block; padding: 1rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; margin-bottom: 1rem;">
                        <i class="bi bi-download" style="font-size: 2rem; color: white;"></i>
                    </div>
                    <h4 style="margin: 0; color: #1e293b; font-weight: 600;">Downloading Packages</h4>
                    <p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.9rem;">Please wait while we fetch your selected packages...</p>
                </div>

                <!-- Progress bar -->
                <div style="background: #e2e8f0; border-radius: 8px; height: 8px; overflow: hidden; margin-bottom: 1rem;">
                    <div id="downloadProgressBar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>

                <!-- Progress text -->
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <span id="downloadProgressText" style="color: #64748b; font-size: 0.875rem; font-weight: 500;">Preparing download...</span>
                </div>

                <!-- Package list -->
                <div id="downloadPackageList" style="max-height: 200px; overflow-y: auto; padding: 0.5rem 0;">
                    <!-- Package items will be added here -->
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(progressOverlay);

    const progressBar = document.getElementById('downloadProgressBar');
    const progressText = document.getElementById('downloadProgressText');
    const packageList = document.getElementById('downloadPackageList');

    let successCount = 0;
    let failCount = 0;
    const total = selectedUrls.length;

    for (let i = 0; i < selectedUrls.length; i++) {
        const url = selectedUrls[i];
        const checkbox = document.querySelector(`[data-download-url="${url}"]`);
        const packageName = checkbox?.dataset.packageName || 'Unknown';

        // Add package item to list
        const packageItem = document.createElement('div');
        packageItem.id = `pkg-status-${i}`;
        packageItem.style.cssText = 'display: flex; align-items: center; padding: 0.75rem; background: #f8fafc; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.3s ease;';
        packageItem.innerHTML = `
            <div class="spinner-border spinner-border-sm text-primary" style="width: 1.25rem; height: 1.25rem; margin-right: 0.75rem;"></div>
            <div style="flex-grow: 1;">
                <div style="font-weight: 500; color: #1e293b; font-size: 0.875rem;">${escapeHtml(packageName)}</div>
                <div style="font-size: 0.75rem; color: #64748b;">Downloading...</div>
            </div>
        `;
        packageList.appendChild(packageItem);

        // Update progress
        const percent = ((i + 1) / total) * 100;
        progressBar.style.width = `${percent}%`;
        progressText.textContent = `Processing ${i + 1} of ${total} packages...`;

        try {
            await downloadPackageFromRepo(url, packageName, true);
            successCount++;

            // Update to success state
            packageItem.style.background = '#dcfce7';
            packageItem.innerHTML = `
                <div style="width: 1.25rem; height: 1.25rem; margin-right: 0.75rem; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-check-circle" style="color: #16a34a; font-size: 1.25rem;"></i>
                </div>
                <div style="flex-grow: 1;">
                    <div style="font-weight: 500; color: #15803d; font-size: 0.875rem;">${escapeHtml(packageName)}</div>
                    <div style="font-size: 0.75rem; color: #16a34a;">Downloaded successfully</div>
                </div>
            `;
        } catch (error) {
            console.error(`Failed to download ${packageName}:`, error);
            failCount++;

            // Update to error state
            packageItem.style.background = '#fee2e2';
            packageItem.innerHTML = `
                <div style="width: 1.25rem; height: 1.25rem; margin-right: 0.75rem; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-x-circle-circle" style="color: #dc2626; font-size: 1.25rem;"></i>
                </div>
                <div style="flex-grow: 1;">
                    <div style="font-weight: 500; color: #991b1b; font-size: 0.875rem;">${escapeHtml(packageName)}</div>
                    <div style="font-size: 0.75rem; color: #dc2626;">Download failed</div>
                </div>
            `;
        }

        // Small delay for visual feedback
        await new Promise(resolve => setTimeout(resolve, 300));
    }

    // Update final progress
    progressBar.style.width = '100%';
    progressText.textContent = 'Download complete!';

    // Wait a moment to show completion
    await new Promise(resolve => setTimeout(resolve, 800));

    // Remove overlay
    progressOverlay.remove();

    btn.disabled = false;
    btn.innerHTML = originalText;

    // Show summary with Notyf (if available) or fallback to showMessage
    if (successCount > 0) {
        if (window.notyf) {
            window.notyf.success({
                message: `🎉 Successfully downloaded ${successCount} package${successCount > 1 ? 's' : ''}!`,
                duration: 4000
            });
        } else {
            showMessage(`Successfully downloaded ${successCount} package(s)!`, 'success');
        }

        // Close modal and refresh
        ModalRenderer.hide('packageDiscoveryModal');

        // Reload packages with delay to ensure DB/filesystem updated
        console.log('📦 Packages downloaded, reloading lists in 500ms...');
        setTimeout(() => {
            console.log('📦 Reloading available packages (includes alerts and badges)...');
            loadAvailablePackages(); // This already fetches alerts and updates badges
        }, 500);

        // Switch to Available Packages subtab
        const availableSubtab = document.querySelector('#tab-packages .subtab-nav a[data-subtab="available-packages"]');
        if (availableSubtab) {
            availableSubtab.click();
        }
    }

    if (failCount > 0) {
        if (window.notyf) {
            window.notyf.error({
                message: `❌ Failed to download ${failCount} package${failCount > 1 ? 's' : ''}`,
                duration: 5000
            });
        } else {
            showMessage(`Failed to download ${failCount} package(s)`, 'error');
        }
    }
}

async function downloadPackageFromRepo(downloadUrl, packageName, silent = false) {
    try {
        const response = await fetch('/api/package-discovery.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.csrfToken || ''
            },
            body: JSON.stringify({
                action: 'download',
                download_url: downloadUrl,
                package_name: packageName
            })
        });

        const result = await response.json();

        if (result.success) {
            if (!silent) {
                showMessage(`Package "${packageName}" downloaded successfully! Check the Available Packages tab to install it.`, 'success');

                // Close the discovery modal
                ModalRenderer.hide('packageDiscoveryModal');

                // Small delay to ensure filesystem/DB has updated
                console.log('📦 Package downloaded, refreshing list in 500ms...');
                setTimeout(() => {
                    console.log('📦 Calling loadAvailablePackages() now...');
                    loadAvailablePackages();
                }, 500);
            }
            return true;
        } else {
            throw new Error(result.error || 'Download failed');
        }

    } catch (error) {
        console.error('Error downloading package:', error);
        if (!silent) {
            showMessage(`Failed to download package: ${error.message}`, 'error');
        }
        throw error;
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
