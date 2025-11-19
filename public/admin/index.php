<?php

require_once __DIR__ . '/../../src/bootstrap.php';

use Hub\Auth;
use Hub\Database;
use Hub\User;
use Hub\Roles;
use Hub\SiteSettings;
use Hub\Layout;

// Allow access to admin and above
Auth::requireRole(['super_admin', 'admin', 'manager']);

$db = Database::getInstance();
$userModel = new User();

$user = Auth::getCurrentUser();
$userRole = Auth::getEffectiveRole();
$isSuperAdmin = Auth::isSuperAdmin();
$canManageUsers = Auth::canManageUsers();

// Onion Layer Access Control
$canSeeUserManagement = in_array($userRole, ['super_admin', 'admin']);
$canSeeSectionAccess = in_array($userRole, ['super_admin', 'admin']);

// Get customizable branding
$mgmtDisplayName = SiteSettings::get('cc_display_name', 'Management');
$mgmtIcon = SiteSettings::get('cc_icon', 'bi-kanban');
$canSeeManageSections = ($userRole === 'super_admin');
$canSeeExport = in_array($userRole, ['super_admin', 'admin']);

// Get data for dropdowns
$users = $userModel->getAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= SiteSettings::get('organization_name', 'Your Organization') ?>'s <?= SiteSettings::get('site_name', 'The Hub') ?></title>

    <!-- ✅ ENTERPRISE ADMIN BUNDLE (Microsoft 365 Style) -->
    <link rel="stylesheet" href="/assets/css/admin-bundle.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
</head>
<body class="admin-root">
    <div class="admin-shell">

    <div class="admin-shell">
        <!-- Left Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <span class="sidebar-title">Admin</span>
            </div>

            <nav class="admin-nav">
                <?php if ($isSuperAdmin || $userRole === 'admin'): ?>
                <a href="/command/" class="admin-nav-link">
                    <i class="<?= htmlspecialchars($mgmtIcon) ?>"></i>
                    <span><?= htmlspecialchars($mgmtDisplayName) ?></span>
                </a>
                <?php endif; ?>

                <?php if ($canSeeUserManagement): ?>
                <a href="#" data-tab="users" class="admin-nav-link active">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
                <?php endif; ?>

                <?php if ($canSeeSectionAccess || $canSeeManageSections || ($isSuperAdmin || in_array($userRole, ['admin']))): ?>
                <a href="#" data-tab="packages" class="admin-nav-link">
                    <i class="fas fa-box"></i>
                    <span>Package Management</span>
                    <span class="sidebar-badge" id="sidebarConfigBadge" style="display: none;"></span>
                </a>
                <?php endif; ?>

                <?php if ($isSuperAdmin): ?>
                <a href="#" data-tab="site-settings" class="admin-nav-link">
                    <i class="fas fa-cog"></i>
                    <span>Site Settings</span>
                </a>
                <a href="#" data-tab="logs" class="admin-nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Activity Logs</span>
                </a>
                <?php endif; ?>

                <?php if ($canSeeExport): ?>
                <a href="#" data-tab="export" class="admin-nav-link">
                    <i class="fas fa-download"></i>
                    <span>Export Data</span>
                </a>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Top Header Bar -->
        <header class="admin-header">
            <div class="breadcrumb">
                <a href="/hub.php">Home</a>
                <span>/</span>
                <span>Admin Dashboard</span>
            </div>
            <div class="header-actions">
                <button class="btn-icon" title="Notifications">
                    <i class="fas fa-bell"></i>
                </button>
                <div class="user-avatar" title="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>">
                    <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="admin-main">
        <!-- Main Content Area -->
        <main class="admin-main">
            <!-- Users Tab (with sub-tabs) -->
            <div id="tab-users" class="admin-tab active">
                <div class="nd-page-header">
                    <div>
                        <h1>User Management</h1>
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
                            <?php if ($isSuperAdmin): ?>
                            <button class="subtab-btn" data-subtab="role-management">Role Management</button>
                            <?php endif; ?>
                        </div>

                        <!-- Active Users Subtab -->
                        <div id="subtab-active-users" class="user-subtab active">
                            <div id="usersTable" class="data-table-container">
                                Loading...
                            </div>
                        </div>

                        <!-- Pending Users Subtab -->
                        <div id="subtab-pending-users" class="user-subtab">
                            <div id="pendingTable" class="data-table-container">
                                Loading...
                            </div>
                        </div>

                        <!-- Invitations Subtab -->
                        <div id="subtab-invitations" class="user-subtab">
                            <div id="invitationsTable" class="data-table-container">
                                Loading...
                            </div>
                        </div>

                        <!-- Role Management Subtab (Super Admin Only) -->
                        <?php if ($isSuperAdmin): ?>
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

                                <div class="info-panel" style="margin-top: 2rem; padding: 1.5rem; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 8px;">
                                    <h3 style="margin: 0 0 1rem 0; color: #1e40af; font-size: 1.1rem;">💡 How to Add New Roles</h3>
                                    <p style="margin: 0 0 0.75rem 0;">Roles are defined in the codebase for security and consistency. To add a new role:</p>
                                    <ol style="margin: 0; padding-left: 1.5rem;">
                                        <li style="margin-bottom: 0.5rem;">Edit <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">/src/Roles.php</code> and add your role to the <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">getAll()</code> method</li>
                                        <li style="margin-bottom: 0.5rem;">Set a unique <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">value</code>, descriptive <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">label</code>, and appropriate <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">hierarchy</code> (1-100)</li>
                                        <li style="margin-bottom: 0.5rem;">Run database migration: <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 0.9rem;">php cli/migrate.php</code></li>
                                        <li>Refresh this page - your new role will appear above and can be assigned to users</li>
                                    </ol>
                                    <p style="margin: 1rem 0 0 0; font-size: 0.9rem; color: #64748b;"><strong>Note:</strong> Higher hierarchy numbers = more permissions. Super Admin (100) has the highest level.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div><!-- end tab-content-scroll -->
                </div><!-- end tab-users -->

                <!-- Package Management Tab (Unified - 3 Subtabs) -->
                <div id="tab-packages" class="admin-tab">
                    <div class="tab-header">
                        <h1>Package Management</h1>
                        <div class="tab-actions">
                            <button id="savePackageConfigBtn" class="btn btn-primary" style="display: none;">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                            <button id="savePackagePermissionsBtn" class="btn btn-primary" style="display: none;">
                                <i class="fas fa-save"></i> Save Permissions
                            </button>
                            <?php if ($isSuperAdmin): ?>
                            <button id="addSection" class="btn btn-primary" style="display: none;">
                                <i class="fas fa-plus"></i> Add New Package
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tab-content-scroll">
                        <!-- Package Management Sub-tabs -->
                        <div class="user-subtabs">
                            <button class="subtab-btn active" data-subtab="package-config">
                                <i class="fas fa-cog"></i> Configuration
                            </button>
                            <button class="subtab-btn" data-subtab="package-permissions">
                                <i class="fas fa-user-shield"></i> Permissions
                            </button>
                            <?php if ($isSuperAdmin): ?>
                            <button class="subtab-btn" data-subtab="package-library">
                                <i class="fas fa-box"></i> Package Library
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Configuration Subtab -->
                        <div id="subtab-package-config" class="user-subtab active">
                            <?php include __DIR__ . '/package-config-subtab.php'; ?>
                        </div>

                        <!-- Permissions Subtab -->
                        <div id="subtab-package-permissions" class="user-subtab">
                            <?php include __DIR__ . '/package-permissions-subtab.php'; ?>
                        </div>

                        <!-- Package Library Subtab (Super Admin Only) -->
                        <?php if ($isSuperAdmin): ?>
                        <div id="subtab-package-library" class="user-subtab">
                            <?php include __DIR__ . '/package-library-subtab.php'; ?>
                        </div>
                        <?php endif; ?>
                    </div><!-- end tab-content-scroll -->
                </div><!-- end tab-packages -->

                <!-- DEPRECATED: Old sections tab (kept temporarily for migration) -->
                <div id="tab-sections" class="admin-tab" style="display: none;">
                    <!-- Legacy code preserved for reference during migration -->
                </div>

                <!-- DEPRECATED: Old config tab (kept temporarily for migration) -->
                <div id="tab-section-config" class="admin-tab" style="display: none;">
                    <!-- Legacy code preserved for reference during migration -->
                </div>

                <!-- Package Manager Tab (Super Admin Only) -->
                <?php if ($isSuperAdmin): ?>
                <div id="tab-packages" class="admin-tab">
                    <div class="tab-header">
                        <h1>Package Manager</h1>
                        <div class="tab-actions">
                            <button id="uploadPackageBtn" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Upload Package
                            </button>
                            <button id="checkUpdatesBtn" class="btn btn-secondary">
                                <i class="bi bi-arrow-repeat"></i> Check for Updates
                            </button>
                        </div>
                    </div>

                    <div class="tab-content-scroll">
                        <!-- Package Sub-tabs -->
                        <div class="user-subtabs">
                            <button class="subtab-btn active" data-subtab="installed-packages">
                                Installed Packages
                                <span class="notification-badge" id="installedPackagesBadge" style="display: none;"></span>
                            </button>
                            <button class="subtab-btn" data-subtab="available-packages">
                                Available Packages
                                <span class="notification-badge" id="availablePackagesBadge" style="display: none;"></span>
                            </button>
                            <button class="subtab-btn" data-subtab="package-updates">
                                Updates
                                <span class="notification-badge" id="updatesBadge" style="display: none;"></span>
                            </button>
                        </div>

                        <!-- Installed Packages Subtab -->
                        <div id="subtab-installed-packages" class="user-subtab active">
                            <p class="info-text">
                                <strong>📦 Installed Packages:</strong> Manage your installed section packages.
                                You can upgrade, downgrade, or uninstall packages from here.
                            </p>
                            <div id="installedPackagesTable" class="data-table-container">
                                Loading installed packages...
                            </div>
                        </div>

                        <!-- Available Packages Subtab -->
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
                                Loading available packages...
                            </div>
                        </div>

                        <!-- Package Updates Subtab -->
                        <div id="subtab-package-updates" class="user-subtab">
                            <p class="info-text">
                                <strong>🔄 Available Updates:</strong> Keep your packages up-to-date with the latest features and security fixes.
                            </p>
                            <div id="packageUpdatesTable" class="data-table-container">
                                Loading updates...
                            </div>
                        </div>

                    </div><!-- end tab-content-scroll -->
                </div><!-- end tab-packages -->
                <?php endif; ?>

                <!-- Export Tab -->
                <div id="tab-export" class="admin-tab">
                    <div class="tab-header">
                        <h1>Export Data</h1>
                    </div>
                    <div class="tab-content-scroll">
                        <p class="info-text">
                            <strong>Export Coming Soon:</strong> Data export functionality will be added in a future update.
                        </p>
                    </div><!-- end tab-content-scroll -->
                </div><!-- end tab-export -->

                <!-- Site Settings Tab (Super Admin Only) -->
                <?php if ($isSuperAdmin): ?>
                <div id="tab-site-settings" class="admin-tab">
                    <div class="tab-header">
                        <h1>Site Settings</h1>
                        <div class="tab-actions">
                            <button id="saveSiteSettings" class="btn btn-primary">Save Changes</button>
                            <button id="cancelSiteSettings" class="btn btn-secondary">Cancel</button>
                        </div>
                    </div>

                    <div class="tab-content-scroll">
                        <!-- Site Settings Sub-tabs -->
                        <div class="user-subtabs">
                            <button class="subtab-btn active" data-subtab="header-footer">Header & Footer</button>
                            <button class="subtab-btn" data-subtab="branding">Branding & Images</button>
                            <button class="subtab-btn" data-subtab="sidebar">Sidebar & Menu</button>
                            <button class="subtab-btn" data-subtab="colors">Color Scheme</button>
                            <button class="subtab-btn" data-subtab="themes">Themes</button>
                            <button class="subtab-btn" data-subtab="command-center"><?= htmlspecialchars($mgmtDisplayName) ?></button>
                            <button class="subtab-btn" data-subtab="advanced">Advanced</button>
                        </div>

                        <div class="site-settings-container">
                            <!-- Header & Footer Subtab -->
                            <div id="subtab-header-footer" class="user-subtab active">
                                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                    Click sections to expand and customize header and footer settings.
                                    </p>

                                <!-- Header Configuration -->
                                <div class="color-section">
                                    <div class="color-section-header" onclick="toggleColorSection(this)">
                                        <h3>
                                            Header Configuration
                                            <span class="color-section-badge">11</span>
                                        </h3>
                                        <span class="color-section-toggle collapsed">▼</span>
                                    </div>
                                    <div class="color-section-body collapsed">
                                        <div class="color-section-content">
                                            <div class="settings-grid">
                                                <div class="form-group">
                                                    <label for="headerHeight">Header Height (pixels)</label>
                                                    <input type="number" id="headerHeight" value="80" min="60" max="150" placeholder="80">
                                                    <small>Navbar height in pixels (60-150px)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="headerMatchLogoHeight">
                                                        <strong>Auto-adjust to Logo Height</strong>
                                                    </label>
                                                    <small>Header will automatically match uploaded logo height + padding</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="navbarSubtitle">Navbar Subtitle</label>
                                                    <input type="text" id="navbarSubtitle" value="The Hub" placeholder="The Hub">
                                                    <small>Shown under organization name in navbar</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerBgColor">Header Background Color</label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" id="headerBgColor" value="#000000">
                                                        <input type="text" id="headerBgColorHex" value="#000000" placeholder="#000000">
                                                    </div>
                                                    <small>Navbar background color</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerTextColor">Header Text Color</label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" id="headerTextColor" value="#FFFFFF">
                                                        <input type="text" id="headerTextColorHex" value="#FFFFFF" placeholder="#FFFFFF">
                                                    </div>
                                                    <small>Navbar text and link color</small>
                                                            </div>

                                                <div class="form-group">
                                                    <label for="headerSubtitleColor">Header Subtitle Color</label>
                                                    <div class="color-picker-wrapper">
                                                        <input type="color" id="headerSubtitleColor" value="#FFD700">
                                                        <input type="text" id="headerSubtitleColorHex" value="#FFD700" placeholder="#FFD700">
                                                    </div>
                                                    <small>Color for the subtitle under organization name</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerTitleFont">Header Title Font</label>
                                                    <select id="headerTitleFont">
                                                        <option value="Roboto">Roboto</option>
                                                        <option value="Open Sans">Open Sans</option>
                                                        <option value="Lato">Lato</option>
                                                        <option value="Montserrat">Montserrat</option>
                                                        <option value="Raleway">Raleway</option>
                                                        <option value="Poppins">Poppins</option>
                                                        <option value="Oswald">Oswald</option>
                                                        <option value="Merriweather">Merriweather</option>
                                                        <option value="Playfair Display">Playfair Display</option>
                                                        <option value="Ubuntu">Ubuntu</option>
                                                    </select>
                                                    <small>Google Font for organization name</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerSubtitleFont">Header Subtitle Font</label>
                                                    <select id="headerSubtitleFont">
                                                        <option value="Roboto">Roboto</option>
                                                        <option value="Open Sans">Open Sans</option>
                                                        <option value="Lato">Lato</option>
                                                        <option value="Montserrat">Montserrat</option>
                                                        <option value="Raleway">Raleway</option>
                                                        <option value="Poppins">Poppins</option>
                                                        <option value="Oswald">Oswald</option>
                                                        <option value="Merriweather">Merriweather</option>
                                                        <option value="Playfair Display">Playfair Display</option>
                                                        <option value="Ubuntu">Ubuntu</option>
                                                    </select>
                                                    <small>Google Font for subtitle text</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerTitleFontSize">Header Title Font Size (rem)</label>
                                                    <input type="number" id="headerTitleFontSize" value="1.3" min="0.5" max="3" step="0.05" placeholder="1.3">
                                                    <small>Font size for organization name (0.5-3rem)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="headerSubtitleFontSize">Header Subtitle Font Size (rem)</label>
                                                    <input type="number" id="headerSubtitleFontSize" value="0.85" min="0.5" max="2" step="0.05" placeholder="0.85">
                                                    <small>Font size for subtitle text (0.5-2rem)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="headerShowSubtitle" checked>
                                                        <strong>Show Header Subtitle</strong>
                                                    </label>
                                                    <small>Display subtitle below organization name</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer Configuration -->
                                <div class="color-section">
                                    <div class="color-section-header" onclick="toggleColorSection(this)">
                                        <h3>
                                            Footer Configuration
                                            <span class="color-section-badge">7</span>
                                        </h3>
                                        <span class="color-section-toggle collapsed">▼</span>
                                    </div>
                                    <div class="color-section-body collapsed">
                                        <div class="color-section-content">
                                            <div class="settings-grid">
                                                <div class="form-group">
                                                    <label for="footerHeight">Footer Height (pixels)</label>
                                                    <input type="number" id="footerHeight" value="40" min="30" max="80" placeholder="40">
                                                    <small>Footer height in pixels (30-80px)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="footerTextSize">Footer Text Size (rem)</label>
                                                    <input type="number" id="footerTextSize" value="0.875" min="0.5" max="1.5" step="0.05" placeholder="0.875">
                                                    <small>Font size for footer text (0.5-1.5rem)</small>
                                                </div>

                                                <div class="form-group">
                                                    <label>
                                                        <input type="checkbox" id="footerShowVersion" checked>
                                                        <strong>Show Version Number</strong>
                                                    </label>
                                                    <small>Display version info in footer</small>
                                                </div>

                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="footerShowUser" checked>
                                            <strong>Show Current User</strong>
                                        </label>
                                        <small>Display logged-in username in footer</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="footerCustomText">Custom Footer Text</label>
                                        <input type="text" id="footerCustomText" value="" placeholder="Optional custom footer message">
                                        <small>Additional text to display in footer (optional)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="footerBgColor">Footer Background Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="footerBgColor" value="#F3F4F6">
                                            <input type="text" id="footerBgColorHex" value="#F3F4F6" placeholder="#F3F4F6">
                                        </div>
                                        <small>Footer background color</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="footerTextColor">Footer Text Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="footerTextColor" value="#6B7280">
                                            <input type="text" id="footerTextColorHex" value="#6B7280" placeholder="#6B7280">
                                        </div>
                                        <small>Footer text color</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar & Menu Subtab -->
                        <div id="subtab-sidebar" class="user-subtab">
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections to expand and customize sidebar and menu settings.
                            </p>

                            <!-- Active Menu Item Styling -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Active Menu Item Styling
                                        <span class="color-section-badge">2</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group">
                                        <label for="activeMenuFontSize">Active Menu Item Font Size</label>
                                        <input type="number" id="activeMenuFontSize" value="16" min="12" max="24" placeholder="16">
                                        <small>Font size for active/selected menu items (12-24px)</small>
                                    </div>

                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="activeMenuBold">
                                            <strong>Bold Active Menu Items</strong>
                                        </label>
                                        <small>Make active menu item text bold</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Sidebar Colors
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group">
                                        <label for="sidebarBg">Sidebar Background Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarBg" value="#FFFFFF">
                                            <input type="text" id="sidebarBgHex" value="#FFFFFF" placeholder="#FFFFFF">
                                        </div>
                                        <small>Background color of the admin sidebar</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarTextColor">Sidebar Text Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarTextColor" value="#374151">
                                            <input type="text" id="sidebarTextColorHex" value="#374151" placeholder="#374151">
                                        </div>
                                        <small>Text color for regular menu items</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarActiveHighlight">Active Item Background</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarActiveHighlight" value="#FEF3C7">
                                            <input type="text" id="sidebarActiveHighlightHex" value="#FEF3C7" placeholder="#FEF3C7">
                                        </div>
                                        <small>Background color for active/selected menu item</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarActiveTextColor">Active Item Text Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarActiveTextColor" value="#C99700">
                                            <input type="text" id="sidebarActiveTextColorHex" value="#C99700" placeholder="#C99700">
                                        </div>
                                        <small>Text color for active menu item</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="sidebarHoverBg">Hover Background Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="sidebarHoverBg" value="#F9FAFB">
                                            <input type="text" id="sidebarHoverBgHex" value="#F9FAFB" placeholder="#F9FAFB">
                                        </div>
                                        <small>Background color when hovering over menu items</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Branding & Images Subtab -->
                        <div id="subtab-branding" class="user-subtab">
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections to expand and customize branding elements.
                            </p>

                            <!-- Site Identity -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Site Identity
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group">
                                        <label for="organizationName">Organization Name</label>
                                        <input type="text" id="organizationName" value="Your Organization" placeholder="Your Organization">
                                        <small>Your school/district name - displayed in navbar, footer, and login page</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="siteName">Site Name</label>
                                        <input type="text" id="siteName" value="The Hub" placeholder="The Hub">
                                        <small>Displayed in browser tabs and page headers</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="welcomeMessage">Welcome Message</label>
                                        <input type="text" id="welcomeMessage" value="Central hub for student services and resources" placeholder="Central hub for student services and resources">
                                        <small>Displayed on the hub landing page</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="landingPageTitle">Landing Page Title</label>
                                        <input type="text" id="landingPageTitle" value="The Hub" placeholder="The Hub">
                                        <small>Main title displayed on hub landing page</small>
                                    </div>

                                    <div class="form-group">
                                        <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                            <span>Show Hub Title Icon in Header</span>
                                            <input type="checkbox" id="landingPageShowIcon" checked>
                                        </label>
                                        <small>Display the uploaded Hub Title Icon above the landing page title</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Logo -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Logo
                                        <span class="color-section-badge">4</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <div class="logo-preview-container" style="margin-bottom: 1rem;">
                                            <img id="currentLogoPreview" src="/assets/images/branding/Branding_NoBG.png" alt="Current Logo" style="max-height: 100px; max-width: 300px; background: #f3f4f6; padding: 1rem; border-radius: 8px;">
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                                            <input type="file" id="logoUpload" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="display: none;">
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('logoUpload').click();">Upload New Logo</button>
                                            <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                                <span>Enable Logo Glow Effect</span>
                                                <input type="checkbox" id="logoGlowEnabled" checked>
                                            </label>
                                        </div>
                                        <small style="display: block; margin-top: 0.5rem;">Accepts PNG, JPG, SVG, WebP. Transparent background recommended.</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="logoGlowColor">Logo Glow Color</label>
                                        <div class="color-picker-wrapper">
                                            <input type="color" id="logoGlowColor" value="#FFD700">
                                            <input type="text" id="logoGlowColorHex" value="#FFD700" placeholder="#FFD700">
                                        </div>
                                        <small>Color for the logo glow effect when enabled</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="logoHeight">Logo Height (Desktop)</label>
                                        <input type="number" id="logoHeight" value="90" min="40" max="150" placeholder="90">
                                        <small>Logo height in pixels for desktop (40-150px)</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="logoHeightMobile">Logo Height (Mobile)</label>
                                        <input type="number" id="logoHeightMobile" value="50" min="30" max="100" placeholder="50">
                                        <small>Logo height in pixels for mobile (30-100px)</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Favicon -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Favicon
                                        <span class="color-section-badge">1</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <div class="logo-preview-container" style="margin-bottom: 1rem;">
                                            <img id="currentFaviconPreview" src="/assets/images/Cowboy_SM_favicon.png" alt="Current Favicon" style="max-height: 48px; max-width: 48px; background: #f3f4f6; padding: 0.5rem; border-radius: 8px;">
                                        </div>
                                        <div>
                                            <input type="file" id="faviconUpload" accept="image/png,image/x-icon,image/vnd.microsoft.icon" style="display: none;">
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('faviconUpload').click();">Upload New Favicon</button>
                                            <small style="display: block; margin-top: 0.5rem;">Accepts PNG, ICO. Recommended size: 32x32 or 48x48 pixels.</small>
                                        </div>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hub Title Icon -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Hub Title Icon
                                        <span class="color-section-badge">2</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                    <div class="form-group" style="grid-column: 1 / -1;">
                                        <div class="logo-preview-container" style="margin-bottom: 1rem;">
                                            <img id="currentHubTileIconPreview" src="/assets/images/branding/Branding_NoBG.png" alt="Current Hub Title Icon" style="max-height: 80px; max-width: 80px; background: #f3f4f6; padding: 0.5rem; border-radius: 8px;">
                                        </div>
                                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
                                            <input type="file" id="hubTileIconUpload" accept="image/png,image/jpeg,image/jpg,image/svg+xml,image/webp" style="display: none;">
                                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('hubTileIconUpload').click();">Upload Custom Icon</button>
                                            <label style="display: flex; align-items: center; gap: 0.5rem; margin: 0; cursor: pointer;">
                                                <span>Use Custom Icon</span>
                                                <input type="checkbox" id="hubTileIconCustomEnabled">
                                            </label>
                                        </div>
                                        <small style="display: block;">When enabled, this icon will appear in the landing page header (if enabled above) AND on all section tiles. Accepts PNG, JPG, SVG, WebP. Recommended size: 120x120 pixels.</small>
                                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Colors & Theme Subtab - COMPACT VERSION -->
                        <div id="subtab-colors" class="user-subtab">

                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections to expand and customize colors. Changes apply immediately to the site preview.
                            </p>

                            <!-- Main Theme Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Main Theme Colors
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="color-grid">
                                            <div class="color-item">
                                                <label>Primary Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="primaryColor" value="#C99700">
                                                    <input type="text" id="primaryColorHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Links, active states, focus rings</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Navbar Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="navbarColor" value="#000000">
                                                    <input type="text" id="navbarColorHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Top navigation bar</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Page Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="backgroundColor" value="#FFFFFF">
                                                    <input type="text" id="backgroundColorHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Main content area</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Accent Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="accentColor" value="#FFD700">
                                                    <input type="text" id="accentColorHex" value="#FFD700" maxlength="7">
                                                </div>
                                                <small>Hover highlights</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Hub Page Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubPageBg" value="#FFFFFF">
                                                    <input type="text" id="hubPageBgHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Hub landing page background</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hub Tiles & Effects -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Hub Tiles & Effects
                                        <span class="color-section-badge">23</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
                                            Customize the visual effects for section tiles on the Hub landing page.
                                        </p>
                                        <div class="color-grid">
                                            <!-- Base Hub Colors -->
                                            <div class="color-item">
                                                <label>Hub Tile Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubTileBg" value="#FFFFFF">
                                                    <input type="text" id="hubTileBgHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Section tile card background color</small>
                                            </div>

                                            <!-- Hover Effects -->
                                            <div class="color-item">
                                                <label>Card Hover Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverShadow" value="#C99700">
                                                    <input type="text" id="hubCardHoverShadowHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Glow shadow on tile hover (with opacity)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Hover Border</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverBorder" value="#C99700">
                                                    <input type="text" id="hubCardHoverBorderHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Border color on tile hover</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Hover Title</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverTitle" value="#C99700">
                                                    <input type="text" id="hubCardHoverTitleHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Title text color on hover</small>
                                            </div>

                                            <!-- Particle/Background Effects -->
                                            <div class="color-item">
                                                <label>Particle Glow 1</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubParticleGlow1" value="#C99700">
                                                    <input type="text" id="hubParticleGlow1Hex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>First animated background glow color</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Particle Glow 2</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubParticleGlow2" value="#FFD700">
                                                    <input type="text" id="hubParticleGlow2Hex" value="#FFD700" maxlength="7">
                                                </div>
                                                <small>Second animated background glow color</small>
                                            </div>

                                            <!-- Card Hover Overlay Effects -->
                                            <div class="color-item">
                                                <label>Card Hover Overlay Center</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardGlowCenter" value="#C99700">
                                                    <input type="text" id="hubCardGlowCenterHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Center color of hover gradient overlay</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Hover Overlay Edge</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardGlowEdge" value="#000000">
                                                    <input type="text" id="hubCardGlowEdgeHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Edge color of hover gradient (creates dark effect)</small>
                                            </div>

                                            <!-- Icon Effects -->
                                            <div class="color-item">
                                                <label>Icon Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubIconColor" value="#C99700">
                                                    <input type="text" id="hubIconColorHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Default tile icon color</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Icon Hover Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubIconHoverColor" value="#FFD700">
                                                    <input type="text" id="hubIconHoverColorHex" value="#FFD700" maxlength="7">
                                                </div>
                                                <small>Icon color on tile hover</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Icon Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubIconShadow" value="#C99700">
                                                    <input type="text" id="hubIconShadowHex" value="#C99700" maxlength="7">
                                                </div>
                                                <small>Glow shadow around tile icons</small>
                                            </div>

                                            <!-- Card Content -->
                                            <div class="color-item">
                                                <label>Card Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardShadow" value="#000000">
                                                    <input type="text" id="hubCardShadowHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Default card shadow (with opacity)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Border</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardBorder" value="#E5E7EB">
                                                    <input type="text" id="hubCardBorderHex" value="#E5E7EB" maxlength="7">
                                                </div>
                                                <small>Default card border color</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Description</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardDescription" value="#6B7280">
                                                    <input type="text" id="hubCardDescriptionHex" value="#6B7280" maxlength="7">
                                                </div>
                                                <small>Description text color (normal state)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Description Hover</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverDescription" value="#374151">
                                                    <input type="text" id="hubCardHoverDescriptionHex" value="#374151" maxlength="7">
                                                </div>
                                                <small>Description text color on hover</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Card Description Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubCardHoverDescriptionShadow" value="#000000">
                                                    <input type="text" id="hubCardHoverDescriptionShadowHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Text shadow on description hover</small>
                                            </div>

                                            <!-- No Sections Message -->
                                            <div class="color-item">
                                                <label>No Sections Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubNoSectionsBg" value="#F9FAFB">
                                                    <input type="text" id="hubNoSectionsBgHex" value="#F9FAFB" maxlength="7">
                                                </div>
                                                <small>"No sections available" message background</small>
                                            </div>
                                            <div class="color-item">
                                                <label>No Sections Shadow</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubNoSectionsShadow" value="#000000">
                                                    <input type="text" id="hubNoSectionsShadowHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Shadow for "no sections" message</small>
                                            </div>

                                            <!-- Particle Effect Controls Section -->
                                            <div style="grid-column: 1 / -1;">
                                                <h4 style="margin: 2rem 0 1rem; color: var(--text-secondary); font-size: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                                                    🌟 Particle Effect Settings
                                                </h4>
                                            </div>

                            <!-- 5-column grid for particle controls -->
                            <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem;">
                                <div class="form-group" style="margin: 0; text-align: center;">
                                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; justify-content: center;">
                                        <input type="checkbox" id="hubParticleEnabled" checked style="margin: 0;">
                                        Enable Effect
                                    </label>
                                    <small style="display: block; margin-top: 0.5rem; text-align: center;">Turn glow on/off</small>
                                </div>

                                <div class="form-group" style="margin: 0;">
                                    <label for="hubParticleSize" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Size (px)</label>
                                    <input type="number" id="hubParticleSize" value="600" min="200" max="1200" step="50" style="width: 100%;">
                                    <small style="display: block; margin-top: 0.25rem;">200-1200</small>
                                </div>                                                <div class="form-group" style="margin: 0;">
                                                    <label for="hubParticleBlur" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Blur (px)</label>
                                                    <input type="number" id="hubParticleBlur" value="150" min="50" max="400" step="10" style="width: 100%;">
                                                    <small style="display: block; margin-top: 0.25rem;">50-400</small>
                                                </div>

                                                <div class="form-group" style="margin: 0;">
                                                    <label for="hubParticleOpacity" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Opacity</label>
                                                    <input type="number" id="hubParticleOpacity" value="0.15" min="0.05" max="0.5" step="0.05" style="width: 100%;">
                                                    <small style="display: block; margin-top: 0.25rem;">0.05-0.5</small>
                                                </div>

                                                <div class="form-group" style="margin: 0;">
                                                    <label for="hubParticleSpeed" style="font-weight: 600; display: block; margin-bottom: 0.25rem;">Speed (s)</label>
                                                    <input type="number" id="hubParticleSpeed" value="20" min="5" max="60" step="5" style="width: 100%;">
                                                    <small style="display: block; margin-top: 0.25rem;">5-60 sec</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Text Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Text Colors
                                        <span class="color-section-badge">9</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="color-pairs-grid">
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Primary Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textPrimary" value="#111827">
                                                    <input type="text" id="textPrimaryHex" value="#111827" maxlength="7">
                                                </div>
                                                <small>Main headings & body</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Secondary Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textSecondary" value="#374151">
                                                    <input type="text" id="textSecondaryHex" value="#374151" maxlength="7">
                                                </div>
                                                <small>Subheadings</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Muted Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textMuted" value="#6B7280">
                                                    <input type="text" id="textMutedHex" value="#6B7280" maxlength="7">
                                                </div>
                                                <small>Helper text</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Disabled Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textDisabled" value="#D1D5DB">
                                                    <input type="text" id="textDisabledHex" value="#D1D5DB" maxlength="7">
                                                </div>
                                                <small>Inactive elements</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Inverse Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="textInverse" value="#FFFFFF">
                                                    <input type="text" id="textInverseHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                                <small>Text on dark backgrounds</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Link Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="linkColor" value="#3B82F6">
                                                    <input type="text" id="linkColorHex" value="#3B82F6" maxlength="7">
                                                </div>
                                                <small>Hyperlinks</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Hub Tile Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubTileText" value="#333333">
                                                    <input type="text" id="hubTileTextHex" value="#333333" maxlength="7">
                                                </div>
                                                <small>Text on Hub section tiles</small>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Hub Title Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubTitleColor" value="#000000">
                                                    <input type="text" id="hubTitleColorHex" value="#000000" maxlength="7">
                                                </div>
                                                <small>Hub page main title (Site Name)</small>
                                            </div>
                                            <div class="color-item">
                                                <label>Hub Subtitle Color</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="hubSubtitleColor" value="#666666">
                                                    <input type="text" id="hubSubtitleColorHex" value="#666666" maxlength="7">
                                                </div>
                                                <small>Hub page subtitle (Welcome Message)</small>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Button Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Button Colors
                                        <span class="color-section-badge">9</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <div class="color-pairs-grid">
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Primary Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonPrimaryBg" value="#C99700">
                                                    <input type="text" id="buttonPrimaryBgHex" value="#C99700" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Primary Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonPrimaryText" value="#FFFFFF">
                                                    <input type="text" id="buttonPrimaryTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Secondary Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSecondaryBg" value="#6B7280">
                                                    <input type="text" id="buttonSecondaryBgHex" value="#6B7280" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Secondary Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSecondaryText" value="#FFFFFF">
                                                    <input type="text" id="buttonSecondaryTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Danger Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonDangerBg" value="#DC2626">
                                                    <input type="text" id="buttonDangerBgHex" value="#DC2626" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Danger Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonDangerText" value="#FFFFFF">
                                                    <input type="text" id="buttonDangerTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="color-pair">
                                            <div class="color-item">
                                                <label>Success Button Background</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSuccessBg" value="#059669">
                                                    <input type="text" id="buttonSuccessBgHex" value="#059669" maxlength="7">
                                                </div>
                                            </div>
                                            <div class="color-item">
                                                <label>Success Button Text</label>
                                                <div class="color-input-group">
                                                    <input type="color" id="buttonSuccessText" value="#FFFFFF">
                                                    <input type="text" id="buttonSuccessTextHex" value="#FFFFFF" maxlength="7">
                                                </div>
                                            </div>
                                        </div>
                                        </div>

                                        <div class="color-item" style="margin-top: 1rem;">
                                            <label>Unsaved Changes Glow</label>
                                            <div class="color-input-group">
                                                <input type="color" id="unsavedChangesGlowColor" value="#C99700">
                                                <input type="text" id="unsavedChangesGlowColorHex" value="#C99700" maxlength="7">
                                            </div>
                                            <small>Save button glow animation</small>
                                        </div>

                                        <div class="color-preview-bar">
                                            <button class="btn btn-primary" style="pointer-events: none;">Primary</button>
                                            <button class="btn btn-secondary" style="pointer-events: none;">Secondary</button>
                                            <button class="btn btn-danger" style="pointer-events: none;">Danger</button>
                                            <button class="btn btn-success" style="pointer-events: none;">Success</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Role Badge Colors -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Role Badge Colors
                                        <span class="color-section-badge">14</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <!-- Staff -->
                                        <div class="role-badge-section">
                                            <?php
                                            $staffBg = SiteSettings::get('role_staff_bg', '#e0e7ff');
                                            $staffText = SiteSettings::get('role_staff_text', '#3730a3');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="staffBadgePreview" style="background: <?php echo htmlspecialchars($staffBg); ?>; color: <?php echo htmlspecialchars($staffText); ?>;">Staff</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleStaffBg" value="<?php echo htmlspecialchars($staffBg); ?>">
                                                        <input type="text" id="roleStaffBgHex" value="<?php echo htmlspecialchars($staffBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleStaffText" value="<?php echo htmlspecialchars($staffText); ?>">
                                                        <input type="text" id="roleStaffTextHex" value="<?php echo htmlspecialchars($staffText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Maintenance -->
                                        <div class="role-badge-section">
                                            <?php
                                            $maintenanceBg = SiteSettings::get('role_maintenance_bg', '#fef3c7');
                                            $maintenanceText = SiteSettings::get('role_maintenance_text', '#92400e');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="maintenanceBadgePreview" style="background: <?php echo htmlspecialchars($maintenanceBg); ?>; color: <?php echo htmlspecialchars($maintenanceText); ?>;">Maintenance</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceBg" value="<?php echo htmlspecialchars($maintenanceBg); ?>">
                                                        <input type="text" id="roleMaintenanceBgHex" value="<?php echo htmlspecialchars($maintenanceBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceText" value="<?php echo htmlspecialchars($maintenanceText); ?>">
                                                        <input type="text" id="roleMaintenanceTextHex" value="<?php echo htmlspecialchars($maintenanceText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Maintenance Director -->
                                        <div class="role-badge-section">
                                            <?php
                                            $directorBg = SiteSettings::get('role_maintenance_director_bg', '#fed7aa');
                                            $directorText = SiteSettings::get('role_maintenance_director_text', '#9a3412');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="directorBadgePreview" style="background: <?php echo htmlspecialchars($directorBg); ?>; color: <?php echo htmlspecialchars($directorText); ?>;">Maintenance Director</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceDirectorBg" value="<?php echo htmlspecialchars($directorBg); ?>">
                                                        <input type="text" id="roleMaintenanceDirectorBgHex" value="<?php echo htmlspecialchars($directorBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleMaintenanceDirectorText" value="<?php echo htmlspecialchars($directorText); ?>">
                                                        <input type="text" id="roleMaintenanceDirectorTextHex" value="<?php echo htmlspecialchars($directorText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Manager -->
                                        <div class="role-badge-section">
                                            <?php
                                            $managerBg = SiteSettings::get('role_manager_bg', '#ddd6fe');
                                            $managerText = SiteSettings::get('role_manager_text', '#5b21b6');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="managerBadgePreview" style="background: <?php echo htmlspecialchars($managerBg); ?>; color: <?php echo htmlspecialchars($managerText); ?>;">Manager</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleManagerBg" value="<?php echo htmlspecialchars($managerBg); ?>">
                                                        <input type="text" id="roleManagerBgHex" value="<?php echo htmlspecialchars($managerBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleManagerText" value="<?php echo htmlspecialchars($managerText); ?>">
                                                        <input type="text" id="roleManagerTextHex" value="<?php echo htmlspecialchars($managerText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Admin -->
                                        <div class="role-badge-section">
                                            <?php
                                            $adminBg = SiteSettings::get('role_admin_bg', '#fce7f3');
                                            $adminText = SiteSettings::get('role_admin_text', '#9f1239');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="adminBadgePreview" style="background: <?php echo htmlspecialchars($adminBg); ?>; color: <?php echo htmlspecialchars($adminText); ?>;">Admin</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleAdminBg" value="<?php echo htmlspecialchars($adminBg); ?>">
                                                        <input type="text" id="roleAdminBgHex" value="<?php echo htmlspecialchars($adminBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleAdminText" value="<?php echo htmlspecialchars($adminText); ?>">
                                                        <input type="text" id="roleAdminTextHex" value="<?php echo htmlspecialchars($adminText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Super Admin -->
                                        <div class="role-badge-section">
                                            <?php
                                            $superAdminBg = SiteSettings::get('role_super_admin_bg', '#fee2e2');
                                            $superAdminText = SiteSettings::get('role_super_admin_text', '#991b1b');
                                            ?>
                                            <h4>
                                                <span class="role-badge-preview" id="superAdminBadgePreview" style="background: <?php echo htmlspecialchars($superAdminBg); ?>; color: <?php echo htmlspecialchars($superAdminText); ?>;">Super Admin</span>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleSuperAdminBg" value="<?php echo htmlspecialchars($superAdminBg); ?>">
                                                        <input type="text" id="roleSuperAdminBgHex" value="<?php echo htmlspecialchars($superAdminBg); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="roleSuperAdminText" value="<?php echo htmlspecialchars($superAdminText); ?>">
                                                        <input type="text" id="roleSuperAdminTextHex" value="<?php echo htmlspecialchars($superAdminText); ?>" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Success Badge -->
                                        <div class="role-badge-section">
                                            <h4>
                                                <span class="role-badge-preview" id="successBadgePreview" style="background: #059669; color: white;">Success</span>
                                                <small style="font-weight: normal; margin-left: 0.5rem;">Used for active status, successful actions</small>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSuccessBg" value="#059669">
                                                        <input type="text" id="badgeSuccessBgHex" value="#059669" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSuccessText" value="#FFFFFF">
                                                        <input type="text" id="badgeSuccessTextHex" value="#FFFFFF" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- System Badge -->
                                        <div class="role-badge-section">
                                            <h4>
                                                <span class="role-badge-preview" id="systemBadgePreview" style="background: #6366f1; color: white;">SYSTEM</span>
                                                <small style="font-weight: normal; margin-left: 0.5rem;">System themes and automated actions</small>
                                            </h4>
                                            <div class="color-pair">
                                                <div class="color-item">
                                                    <label>Background</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSystemBg" value="#6366F1">
                                                        <input type="text" id="badgeSystemBgHex" value="#6366F1" maxlength="7">
                                                    </div>
                                                </div>
                                                <div class="color-item">
                                                    <label>Text</label>
                                                    <div class="color-input-group">
                                                        <input type="color" id="badgeSystemText" value="#FFFFFF">
                                                        <input type="text" id="badgeSystemTextHex" value="#FFFFFF" maxlength="7">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <!-- End subtab-colors -->

                        <!-- Themes Subtab -->
                        <div id="subtab-themes" class="user-subtab">
                            <div class="settings-section">
                                <h2>Theme Management</h2>
                                <p style="color: #6B7280; margin-bottom: 1rem;">Save current color scheme as a theme, load saved themes, or update existing themes with current settings</p>

                                <!-- Save or Update Theme -->
                                <div style="background: #F9FAFB; padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; border: 2px solid #E5E7EB;">
                                    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; font-weight: 600;">Save Current Color Scheme</h3>
                                    <div class="settings-grid">
                                        <div class="form-group">
                                            <label for="newThemeName">Theme Name *</label>
                                            <input type="text" id="newThemeName" placeholder="My Custom Theme">
                                        </div>
                                        <div class="form-group">
                                            <label for="newThemeDescription">Description</label>
                                            <input type="text" id="newThemeDescription" placeholder="Optional description">
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <button id="saveCurrentTheme" class="btn btn-primary">Save as New Theme</button>
                                        <button id="updateSelectedTheme" class="btn btn-secondary" style="display: none;">Update Selected Theme</button>
                                    </div>
                                    <div id="updateThemeNotice" style="display: none; margin-top: 1rem; padding: 0.75rem; background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 4px;">
                                        <strong>Update Mode:</strong> <span id="updateThemeName"></span>
                                        <button onclick="cancelUpdateMode()" style="float: right; background: none; border: none; cursor: pointer; font-weight: bold;">✕</button>
                                    </div>
                                </div>

                                <!-- Saved Themes List -->
                                <div id="themesList">
                                    <h3 style="font-size: 1.1rem; margin-bottom: 1rem; font-weight: 600;">Saved Themes</h3>
                                    <div id="themesContainer" style="display: grid; gap: 1rem;">
                                        Loading themes...
                                    </div>
                                </div>
                            </div>
                        </div><!-- end subtab-themes -->

                        <!-- Management System Subtab -->
                        <div id="subtab-command-center" class="user-subtab">
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Customize how the Management system appears to users. Change the display name, icon, and description.
                            </p>

                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Management System Branding
                                        <span class="color-section-badge">3</span>
                                    </h3>
                                    <span class="color-section-toggle">▼</span>
                                </div>
                                <div class="color-section-body">
                                    <div class="color-section-content">
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="cc_display_name">
                                                    Display Name
                                                    <span class="info-tooltip" title="The public-facing name shown in navigation and page titles">ⓘ</span>
                                                </label>
                                                <input type="text"
                                                       id="cc_display_name"
                                                       name="cc_display_name"
                                                       value="<?php echo e(SiteSettings::get('cc_display_name', 'Management')); ?>"
                                                       class="form-control"
                                                       placeholder="e.g., Management, Administration, Command Center">
                                                <small class="form-text text-muted">Examples: Management, Administration, Operations, Command Center</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="cc_icon">
                                                    Navigation Icon
                                                    <span class="info-tooltip" title="Bootstrap icon class for navigation links">ⓘ</span>
                                                </label>
                                                <div style="display: flex; gap: 0.75rem; align-items: center;">
                                                    <!-- Compact Live Preview -->
                                                    <div style="width: 40px; height: 40px; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); display: flex; align-items: center; justify-content: center; background: var(--card-bg); font-size: 20px; flex-shrink: 0;">
                                                        <i id="icon-preview" class="<?php echo e(SiteSettings::get('cc_icon', 'bi-kanban')); ?>"></i>
                                                    </div>

                                                    <!-- Input Field -->
                                                    <div style="flex: 1;">
                                                        <input type="text"
                                                               id="cc_icon"
                                                               name="cc_icon"
                                                               value="<?php echo e(SiteSettings::get('cc_icon', 'bi-kanban')); ?>"
                                                               class="form-control"
                                                               placeholder="e.g., bi-kanban, bi-gear, bi-building"
                                                               oninput="updateIconPreview(this.value)">
                                                    </div>

                                                    <!-- Dropdown Toggle Button -->
                                                    <button type="button"
                                                            class="btn btn-sm btn-secondary"
                                                            onclick="toggleIconDropdown()"
                                                            style="white-space: nowrap; display: flex; align-items: center; gap: 0.5rem;">
                                                        <i class="bi bi-grid-3x3-gap"></i>
                                                        <span id="icon-dropdown-text">Browse Icons</span>
                                                        <i id="icon-dropdown-arrow" class="bi bi-chevron-down" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                </div>

                                                <small class="form-text text-muted">
                                                    Click "Browse Icons" to select from popular options, or
                                                    <a href="https://icons.getbootstrap.com/" target="_blank">browse all Bootstrap Icons</a>
                                                </small>

                                                <!-- Collapsible Icon Selector -->
                                                <div id="icon-selector-dropdown" style="display: none; margin-top: 0.75rem; padding: 0.75rem; background: var(--hover-bg); border: 1px solid var(--border-color); border-radius: var(--border-radius); max-height: 200px; overflow-y: auto;">
                                                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 0.5rem;">
                                                        <?php
                                                        $quickIcons = [
                                                            ['icon' => 'bi-kanban', 'label' => 'Kanban'],
                                                            ['icon' => 'bi-gear-fill', 'label' => 'Settings'],
                                                            ['icon' => 'bi-building', 'label' => 'Building'],
                                                            ['icon' => 'bi-clipboard-data', 'label' => 'Data'],
                                                            ['icon' => 'bi-speedometer2', 'label' => 'Dashboard'],
                                                            ['icon' => 'bi-list-check', 'label' => 'Checklist'],
                                                            ['icon' => 'bi-folder', 'label' => 'Folder'],
                                                            ['icon' => 'bi-graph-up', 'label' => 'Analytics'],
                                                            ['icon' => 'bi-pencil-square', 'label' => 'Edit'],
                                                            ['icon' => 'bi-shield-check', 'label' => 'Security'],
                                                            ['icon' => 'bi-people', 'label' => 'People'],
                                                            ['icon' => 'bi-file-earmark-text', 'label' => 'Document'],
                                                            ['icon' => 'bi-briefcase', 'label' => 'Briefcase'],
                                                            ['icon' => 'bi-box', 'label' => 'Box'],
                                                            ['icon' => 'bi-calendar', 'label' => 'Calendar'],
                                                            ['icon' => 'bi-chat', 'label' => 'Chat']
                                                        ];
                                                        foreach ($quickIcons as $qIcon): ?>
                                                            <button type="button"
                                                                    class="icon-selector-btn-compact"
                                                                    onclick="selectIcon('<?php echo $qIcon['icon']; ?>')"
                                                                    title="<?php echo $qIcon['label']; ?>">
                                                                <i class="<?php echo $qIcon['icon']; ?>"></i>
                                                                <span><?php echo $qIcon['label']; ?></span>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group" style="grid-column: span 2;">
                                                <label for="cc_description">
                                                    Description
                                                    <span class="info-tooltip" title="Brief description shown to users">ⓘ</span>
                                                </label>
                                                <textarea id="cc_description"
                                                          name="cc_description"
                                                          class="form-control"
                                                          rows="3"
                                                          placeholder="Brief description of what this system does..."><?php echo e(SiteSettings::get('cc_description', 'Centralized management system for tracking and processing submissions')); ?></textarea>
                                                <small class="form-text text-muted">Optional: Shown in selector page and help documentation</small>
                                            </div>
                                        </div>

                                        <div class="form-actions">
                                            <button type="button" class="btn btn-primary" onclick="saveCommandCenterSettings()">
                                                <i class="bi bi-check-circle"></i> Save Command Center Settings
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- end subtab-command-center -->

                        <!-- Advanced Subtab -->
                        <div id="subtab-advanced" class="user-subtab">

                            <!-- Critical Warning Banner -->
                            <div class="settings-section" style="border: 2px solid #DC2626; background: #FEF2F2; margin-bottom: 1.5rem;">
                                <h2 style="color: #DC2626;">Super Admin Only - System Configuration</h2>
                                <p style="color: #991B1B; margin-bottom: 0; font-weight: 600;">
                                    WARNING: These settings control core system functionality. Unauthorized changes can break the application.
                                    All modifications are logged and require your Super Admin credentials.
                                </p>
                            </div>

                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                                Click sections below to expand and configure system settings. Changes are saved to your <code>.env</code> file with automatic backup.
                            </p>

                            <!-- Authentication & Login -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Authentication & Login
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Configure how users authenticate and access the system
                                        </p>

                                        <h4 style="margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Authentication Methods</h4>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="allowLocalUsers" style="width: auto; margin-right: 0.5rem;">
                                                    <span>Local User Accounts</span>
                                                </label>
                                                <small>Allow users to sign in with username and password stored in the database.</small>
                                            </div>

                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableGoogleLogin" checked style="width: auto; margin-right: 0.5rem;" onchange="toggleAuthSection('google', this.checked)">
                                                    <span>Google OAuth</span>
                                                </label>
                                                <small>Allow users to sign in with their Google accounts. Configure details in "Google OAuth & Groups" below.</small>
                                            </div>

                                            <div class="form-group">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableMicrosoftLogin" style="width: auto; margin-right: 0.5rem;" onchange="toggleAuthSection('microsoft', this.checked)">
                                                    <span>Microsoft OAuth</span>
                                                </label>
                                                <small>Allow users to sign in with their Microsoft accounts. Configure details in "Microsoft OAuth & Groups" below.</small>
                                            </div>
                                        </div>

                                        <h4 style="margin: 2rem 0 1rem; font-size: 1.1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Access Restrictions</h4>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" id="requireDomainMatch" style="width: auto; margin-right: 0.5rem;">
                                                    Require Domain Match
                                                </label>
                                                <small>Only allow Google/Microsoft accounts from specific domain(s). Configure domains below.</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="allowedDomains">Allowed Email Domains</label>
                                                <input type="text" id="allowedDomains" placeholder="example.com, yourdomain.org">
                                                <small>Comma-separated list of allowed email domains for OAuth login</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="sessionTimeout">Session Timeout (hours)</label>
                                                <input type="number" id="sessionTimeout" min="1" max="168" value="2">
                                                <small>How long users stay logged in before requiring re-authentication (1-168 hours)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Google OAuth & Groups -->
                            <div class="color-section" id="googleAuthSection">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Google OAuth & Groups
                                        <span class="color-section-badge">7</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Configure Google OAuth login and auto-role assignment based on Google Workspace group membership.
                                            <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color: var(--primary-color);">
                                                → Open Google Cloud Console
                                            </a>
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="googleClientId">Google Client ID</label>
                                                <input type="text" id="googleClientId" placeholder="xxxxx.apps.googleusercontent.com">
                                                <small>From Google Cloud Console → Credentials → OAuth 2.0 Client ID</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="googleClientSecret">Google Client Secret</label>
                                                <input type="password" id="googleClientSecret" placeholder="GOCSPX-xxxxx">
                                                <small>Secret key from Google Cloud Console OAuth client</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="googleRedirectUri">Google Redirect URI</label>
                                                <input type="text" id="googleRedirectUri" placeholder="https://yourdomain.com/google_login.php">
                                                <small>Must match exactly what's configured in Google Cloud Console</small>
                                            </div>

                                            <!-- Google Groups Auto-Role Assignment -->
                                            <div class="form-group" style="grid-column: 1 / -1; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                                                <h4 style="margin-bottom: 0.5rem; font-size: 1.1rem;">Google Workspace Groups Integration</h4>
                                                <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
                                                    Automatically assign roles based on Google Workspace group membership. Requires a service account.
                                                </p>
                                            </div>

                                            <div class="form-group" style="grid-column: 1 / -1;">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableGoogleGroups" onchange="toggleDependentSection('enableGoogleGroups', 'googleGroupsFields', true)">
                                                    <span>Enable Google Groups Auto-Role Assignment</span>
                                                </label>
                                                <small>Automatically grant roles based on user's Google Workspace group membership</small>
                                            </div>

                                            <!-- Dependent Google Groups Fields -->
                                            <div id="googleGroupsFields" style="display: none; grid-column: 1 / -1;">
                                                <div class="settings-grid" style="padding-left: 1.5rem; border-left: 3px solid var(--primary-color); margin-top: 1rem;">
                                                    <?php
                                                    // Get primary domain from ALLOWED_DOMAINS for dynamic placeholders
                                                    $allowedDomains = $_ENV['ALLOWED_DOMAINS'] ?? 'yourdomain.com';
                                                    $domains = array_map('trim', explode(',', $allowedDomains));
                                                    $primaryDomain = $domains[0] ?? 'yourdomain.com';
                                                    ?>
                                                    <div class="form-group">
                                                        <label for="googleAdminEmail">Google Admin Email</label>
                                                        <input type="text" id="googleAdminEmail" placeholder="admin@<?php echo htmlspecialchars($primaryDomain); ?>">
                                                        <small>Google Workspace admin email with domain delegation permissions</small>
                                                    </div>

                                                    <div class="form-group" style="grid-column: 1 / -1;">
                                                        <label for="googleGroupRoleAssociations">Group-to-Role Associations</label>
                                                        <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                                            <input type="text" id="googleGroupRoleAssociations" placeholder="seniors*@<?php echo htmlspecialchars($primaryDomain); ?>:student|teachers@<?php echo htmlspecialchars($primaryDomain); ?>:staff" style="flex: 1;">
                                                            <button type="button" class="btn-sm" onclick="openGoogleGroupModal()" style="white-space: nowrap;">
                                                                Add Group
                                                            </button>
                                                        </div>
                                                        <small>Format: group@domain.com:role1,role2 | Use * for wildcards (e.g., seniors*@<?php echo htmlspecialchars($primaryDomain); ?> matches seniors2026@<?php echo htmlspecialchars($primaryDomain); ?>, seniors2027@<?php echo htmlspecialchars($primaryDomain); ?>) | Multiple roles: comma-separated | Multiple groups: pipe-separated | Or use "Add Group" button</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Microsoft OAuth & Groups -->
                            <div class="color-section" id="microsoftAuthSection">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Microsoft OAuth & Groups
                                        <span class="color-section-badge">6</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Enable Microsoft/Azure AD login and auto-role assignment based on Azure AD group membership.
                                            <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" style="color: var(--primary-color);">
                                                → Open Azure Portal App Registrations
                                            </a>
                                        </p>

                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="microsoftClientId">Microsoft Application (Client) ID</label>
                                                <input type="text" id="microsoftClientId" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                                <small>From Azure Portal → App Registrations → Your App → Application (client) ID</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="microsoftClientSecret">Microsoft Client Secret</label>
                                                <input type="password" id="microsoftClientSecret" placeholder="Client secret value">
                                                <small>From Azure Portal → Certificates & secrets → Client secrets</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="microsoftTenantId">Microsoft Tenant ID</label>
                                                <input type="text" id="microsoftTenantId" placeholder="common">
                                                <small>Use "common" for multi-tenant, or your organization's tenant ID for single-tenant</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="microsoftRedirectUri">Microsoft Redirect URI</label>
                                                <input type="text" id="microsoftRedirectUri" placeholder="https://hub.example.com/microsoft_login.php">
                                                <small>Must match exactly what's configured in Azure Portal redirect URIs</small>
                                            </div>
                                        </div>

                                        <!-- Azure AD Groups Auto-Role Assignment -->
                                        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                                            <h4 style="margin-bottom: 0.5rem; font-size: 1.1rem;">Azure AD Groups Integration</h4>
                                            <p style="color: var(--text-muted); margin-bottom: 1rem; font-size: 0.9rem;">
                                                Automatically assign roles based on Azure AD group membership (coming soon).
                                            </p>

                                            <div class="settings-grid">
                                                <div class="form-group" style="grid-column: 1 / -1;">
                                                    <label class="checkbox-label">
                                                        <input type="checkbox" id="enableMicrosoftGroups" disabled>
                                                        <span>Enable Azure AD Groups Auto-Role Assignment</span>
                                                    </label>
                                                    <small>Feature in development - Auto-grant roles based on Azure AD group membership</small>
                                                </div>

                                                <div class="form-group">
                                                    <label for="microsoftAdminEmail">Microsoft Admin Email</label>
                                                    <input type="text" id="microsoftAdminEmail" placeholder="admin@<?php echo htmlspecialchars($primaryDomain); ?>" disabled>
                                                    <small>Admin email for Azure AD API access</small>
                                                </div>

                                                <div class="form-group" style="grid-column: 1 / -1;">
                                                    <label for="microsoftGroupRoleAssociations">Group-to-Role Associations</label>
                                                    <div style="display: flex; gap: 0.5rem; align-items: flex-start;">
                                                        <input type="text" id="microsoftGroupRoleAssociations" placeholder="group-id-1:student|group-id-2:staff" disabled style="flex: 1;">
                                                        <button type="button" class="btn-sm" onclick="openMicrosoftGroupModal()" disabled style="white-space: nowrap;">
                                                            Add Group
                                                        </button>
                                                    </div>
                                                    <small>Format: azure-group-id:role1,role2 | Use Azure AD Group IDs (not names) | Multiple roles: comma-separated | Multiple groups: pipe-separated | Or use "Add Group" button when enabled</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #EFF6FF; border-left: 4px solid #3B82F6; border-radius: 4px; font-size: 0.875rem;">
                                            <strong>Setup Instructions:</strong>
                                            <ol style="margin: 0.5rem 0 0 1.5rem; padding: 0;">
                                                <li>Create app in <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank">Azure Portal</a></li>
                                                <li>Add redirect URI in "Authentication" section</li>
                                                <li>Create client secret in "Certificates & secrets"</li>
                                                <li>Grant "User.Read" API permission (Microsoft Graph)</li>
                                                <li>Copy Application ID and Client Secret here</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Database Configuration -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Database Configuration
                                        <span class="color-section-badge">4</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            MySQL/MariaDB connection settings. <strong>Changes require application refresh to take effect.</strong>
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="dbHost">Database Host</label>
                                                <input type="text" id="dbHost" placeholder="localhost">
                                                <small>MySQL server hostname or IP address</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="dbName">Database Name</label>
                                                <input type="text" id="dbName" placeholder="thehub">
                                                <small>Name of the database to connect to</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="dbUser">Database Username</label>
                                                <input type="text" id="dbUser" placeholder="db_user">
                                                <small>Database user with full permissions on the database</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="dbPassword">Database Password</label>
                                                <input type="password" id="dbPassword" placeholder="••••••••">
                                                <small>Password for database user (leave empty to keep current)</small>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 4px;">
                                            <strong>Database Connection Test:</strong>
                                            <button id="testDbConnection" class="btn btn-sm btn-secondary" style="margin-left: 1rem;">Test Connection</button>
                                            <div id="dbTestResult" style="margin-top: 0.5rem;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Application Settings -->
                            <div class="color-section">
                                <div class="color-section-header" id="appSettingsHeader" onclick="toggleColorSection(this)">
                                    <h3>
                                        Application Settings
                                        <span class="color-section-badge">5</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Core application configuration
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="appUrl">Application URL</label>
                                                <input type="text" id="appUrl" placeholder="https://hub.example.com">
                                                <small>Full URL where The Hub is accessed (used for OAuth callbacks)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="appEnvironment">Environment</label>
                                                <select id="appEnvironment">
                                                    <option value="production">Production</option>
                                                    <option value="development">Development</option>
                                                    <option value="staging">Staging</option>
                                                </select>
                                                <small>Current environment mode (affects debugging and error display)</small>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" id="debugMode" style="width: auto; margin-right: 0.5rem;">
                                                    Enable Debug Mode
                                                </label>
                                                <small>Show detailed error messages (WARNING: disable in production!)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="maxUploadSize">Max Upload Size (MB)</label>
                                                <input type="number" id="maxUploadSize" min="1" max="100" value="10">
                                                <small>Maximum file size for logo and branding uploads (1-100 MB)</small>
                                            </div>

                                            <div class="form-group">
                                                <label>
                                                    <input type="checkbox" id="maintenanceMode" style="width: auto; margin-right: 0.5rem;">
                                                    Maintenance Mode
                                                </label>
                                                <small>WARNING: Temporarily disable access for non-admin users</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Configuration -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Email Configuration (SMTP)
                                        <span class="color-section-badge">6</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            SMTP settings for sending system emails (invitation notifications, password resets, etc.)
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <label for="smtpHost">SMTP Host</label>
                                                <input type="text" id="smtpHost" placeholder="smtp.gmail.com">
                                                <small>SMTP server hostname</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpPort">SMTP Port</label>
                                                <input type="number" id="smtpPort" placeholder="587">
                                                <small>Usually 587 (TLS) or 465 (SSL)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpUsername">SMTP Username</label>
                                                <input type="text" id="smtpUsername" placeholder="noreply@example.com">
                                                <small>Email account for sending</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpPassword">SMTP Password</label>
                                                <input type="password" id="smtpPassword" placeholder="••••••••">
                                                <small>Password or app-specific password (leave empty to keep current)</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpFromEmail">From Email Address</label>
                                                <input type="email" id="smtpFromEmail" placeholder="noreply@example.com">
                                                <small>Email address shown as sender</small>
                                            </div>

                                            <div class="form-group">
                                                <label for="smtpFromName">From Name</label>
                                                <input type="text" id="smtpFromName" placeholder="The Hub">
                                                <small>Name shown as sender</small>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #FEF3C7; border-left: 4px solid #F59E0B; border-radius: 4px;">
                                            <strong>Test SMTP:</strong>
                                            <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; flex-wrap: wrap;">
                                                <input type="email" id="testEmailAddress" placeholder="your@email.com" style="flex: 1; min-width: 200px;">
                                                <button id="testSmtpConfig" class="btn btn-sm btn-secondary">Test Connection</button>
                                                <button id="sendTestEmail" class="btn btn-sm btn-primary">Send Test Email</button>
                                            </div>
                                            <div id="smtpTestResult" style="margin-top: 0.5rem;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!--
                            ============================================================
                            EXAMPLE: Email Notifications (Future Feature)
                            ============================================================
                            This is a template showing cascading dependencies pattern.
                            Uncomment and adapt when implementing email notifications.

                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)">
                                    <h3>
                                        Email Notifications
                                        <span class="color-section-badge">4+</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: var(--text-muted); margin-bottom: 1rem;">
                                            Configure automated email notifications for system events.
                                        </p>

                                        <div class="settings-grid">
                                            LEVEL 1: Top-level feature toggle
                                            <div class="form-group" style="grid-column: 1 / -1;">
                                                <label class="checkbox-label">
                                                    <input type="checkbox" id="enableEmailNotifications"
                                                           onchange="toggleDependentSection('enableEmailNotifications', 'emailNotificationFields', true)">
                                                    <span>Enable Email Notifications</span>
                                                </label>
                                                <small>Send automated emails for system events</small>
                                            </div>

                                            LEVEL 2: Dependent fields appear when Level 1 is checked
                                            <div id="emailNotificationFields" style="display: none; grid-column: 1 / -1;">
                                                <div class="settings-grid" style="padding-left: 2rem; border-left: 3px solid var(--primary-color);">

                                                    <div class="form-group">
                                                        <label class="checkbox-label">
                                                            <input type="checkbox" id="notifyOnNewUser">
                                                            <span>New User Registrations</span>
                                                        </label>
                                                        <small>Notify admins when new users register</small>
                                                    </div>

                                                    LEVEL 3: Email Digest depends on Email Notifications
                                                    <div class="form-group" style="grid-column: 1 / -1; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                                                        <label class="checkbox-label">
                                                            <input type="checkbox" id="enableEmailDigest"
                                                                   onchange="toggleDependentSection('enableEmailDigest', 'emailDigestFields', true)">
                                                            <span>Enable Daily Email Digest</span>
                                                        </label>
                                                        <small>Send summary emails instead of individual notifications</small>
                                                    </div>

                                                    LEVEL 4: Digest configuration depends on Digest being enabled
                                                    <div id="emailDigestFields" style="display: none; grid-column: 1 / -1;">
                                                        <div class="settings-grid" style="padding-left: 2rem; border-left: 3px solid var(--secondary-color);">
                                                            <div class="form-group">
                                                                <label for="digestTime">Send Time</label>
                                                                <input type="time" id="digestTime" value="08:00">
                                                                <small>Daily digest send time (server timezone)</small>
                                                            </div>

                                                            <div class="form-group">
                                                                <label class="checkbox-label">
                                                                    <input type="checkbox" id="digestIncludeUsers">
                                                                    <span>Include User Activity</span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div style="margin-top: 1rem; padding: 1rem; background: #EFF6FF; border-left: 4px solid #3B82F6; border-radius: 4px;">
                                            <strong>💡 Cascading Dependencies Example:</strong><br>
                                            This section demonstrates 4 levels of dependencies:<br>
                                            1. Enable Email Notifications (top-level)<br>
                                            &nbsp;&nbsp;→ 2. Event Types (checkboxes)<br>
                                            &nbsp;&nbsp;&nbsp;&nbsp;→ 3. Enable Email Digest<br>
                                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;→ 4. Digest Options<br>
                                            <br>
                                            Unchecking Level 1 cascades down and disables/hides all children.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            -->

                            <!-- Danger Zone -->
                            <div class="color-section">
                                <div class="color-section-header" onclick="toggleColorSection(this)" style="border-color: #DC2626;">
                                    <h3 style="color: #DC2626;">
                                        Danger Zone
                                        <span class="color-section-badge" style="background: #DC2626;">3</span>
                                    </h3>
                                    <span class="color-section-toggle collapsed" style="color: #DC2626;">▼</span>
                                </div>
                                <div class="color-section-body collapsed">
                                    <div class="color-section-content">
                                        <p style="color: #991B1B; margin-bottom: 1rem; font-weight: 600;">
                                            WARNING: These actions cannot be undone. Proceed with extreme caution.
                                        </p>
                                        <div class="settings-grid">
                                            <div class="form-group">
                                                <button id="resetToDefaults" class="btn btn-danger">Reset All Color Settings to Defaults</button>
                                                <small>WARNING: This will restore all color/branding settings to their original values</small>
                                            </div>

                                            <div class="form-group">
                                                <button id="clearAllSessions" class="btn btn-danger">Clear All Active Sessions</button>
                                                <small>WARNING: Force logout all users (including yourself)</small>
                                            </div>

                                            <div class="form-group">
                                                <button id="regenerateEnv" class="btn btn-danger">Regenerate .env File</button>
                                                <small>WARNING: Re-create .env from current settings (backup created automatically)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Save Advanced Settings -->
                            <div class="advanced-settings-footer">
                                <div class="advanced-settings-footer-content">
                                    <span id="unsavedChangesIndicator" class="unsaved-indicator">Unsaved changes</span>
                                    <button id="saveAdvancedSettings" class="btn btn-primary">Save Configuration</button>
                                    <button id="reloadAdvancedSettings" class="btn btn-secondary">Reload</button>
                                </div>
                            </div>
                        </div><!-- end subtab-advanced -->
                    </div><!-- end site-settings-container -->
                </div><!-- end tab-content-scroll -->
            </div><!-- end tab-site-settings -->
            <?php endif; ?>

            <!-- Activity Logs Tab (Super Admin Only) -->
            <?php if ($isSuperAdmin): ?>
            <div id="tab-logs" class="admin-tab">
                <div class="tab-header">
                    <h1>Activity Logs</h1>
                    <div class="tab-actions">
                        <button id="refreshLogs" class="btn btn-secondary">Refresh</button>
                        <button id="clearLogFilters" class="btn btn-secondary">Clear Filters</button>
                    </div>
                </div>

                <div class="tab-content-scroll">
                    <div class="logs-filters" style="background: #f9fafb; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.875rem; font-weight: 600;">Action</label>
                            <select id="filterAction" style="width: 100%;">
                                <option value="">All Actions</option>
                                <option value="create">Create</option>
                                <option value="update">Update</option>
                                <option value="delete">Delete</option>
                                <option value="approve">Approve</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="grant_access">Grant Access</option>
                                <option value="revoke_access">Revoke Access</option>
                                <option value="login_success">Login Success</option>
                                <option value="login_failed">Login Failed</option>
                                <option value="logout">Logout</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.875rem; font-weight: 600;">Table</label>
                            <select id="filterTable" style="width: 100%;">
                                <option value="">All Tables</option>
                                <option value="users">Users</option>
                                <option value="user_global_roles">User Roles</option>
                                <option value="sections">Packages</option>
                                <option value="section_access">Package Access</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label style="font-size: 0.875rem; font-weight: 600;">Limit</label>
                            <select id="filterLimit" style="width: 100%;">
                                <option value="50">50 records</option>
                                <option value="100" selected>100 records</option>
                                <option value="250">250 records</option>
                                <option value="500">500 records</option>
                            </select>
                        </div>
                    </div>
                    <button id="applyLogFilters" class="btn btn-primary" style="margin-top: 1rem;">Apply Filters</button>
                </div>

                <div id="logsTable" class="data-table-container">
                    Loading...
                </div>

                <div id="logsPagination" style="display: flex; justify-content: center; gap: 1rem; margin-top: 1.5rem;">
                    <!-- Pagination will be added by JavaScript -->
                </div>
                </div><!-- end tab-content-scroll -->
            </div><!-- end tab-logs -->
            <?php endif; ?>
        </main><!-- end admin-main -->
    </div><!-- end admin-shell -->

    <?php include __DIR__ . '/partials/modals.php'; ?>
    <?php include __DIR__ . '/partials/package-setup-wizard.php'; ?>
    <?php include __DIR__ . '/partials/capability-preview-modal.php'; ?>


    <script nonce="<?php echo CSP_NONCE; ?>">
        window.isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
        window.canManageUsers = <?php echo $canManageUsers ? 'true' : 'false'; ?>;
        window.userRole = '<?php echo $user['role']; ?>';
        window.csrfToken = '<?php echo generateCsrfToken(); ?>';
        window.APP_DEBUG_MODE = <?php echo (($_ENV['DEBUG_MODE'] ?? 'false') === 'true') ? 'true' : 'false'; ?>;

        // Toggle collapsible menu groups with accordion behavior
        function toggleMenuGroup(header) {
            const menuGroup = header.parentElement;
            const isCurrentlyCollapsed = menuGroup.classList.contains('collapsed');

            // Close all other menu groups (accordion behavior)
            document.querySelectorAll('.menu-group').forEach(group => {
                if (group !== menuGroup) {
                    group.classList.add('collapsed');
                }
            });

            // Toggle current group
            menuGroup.classList.toggle('collapsed');

            // Save state to localStorage
            const groupName = header.textContent.trim();
            const isCollapsed = menuGroup.classList.contains('collapsed');
            localStorage.setItem('menuGroup_' + groupName, isCollapsed ? 'collapsed' : 'expanded');
        }

        // Initialize menu groups - start all collapsed by default
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.menu-group').forEach(group => {
                const header = group.querySelector('.menu-group-header');
                const groupName = header.textContent.trim();
                const savedState = localStorage.getItem('menuGroup_' + groupName);

                // Start collapsed by default, or use saved state
                if (savedState === 'expanded') {
                    group.classList.remove('collapsed');
                } else {
                    group.classList.add('collapsed');
                }
            });
        });

        // Toggle user dropdown menu
        function toggleUserDropdown(event) {
            event.stopPropagation();
            const menu = document.getElementById('userDropdownMenu');
            menu.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.nav-user-dropdown');
            const menu = document.getElementById('userDropdownMenu');
            if (dropdown && !dropdown.contains(event.target)) {
                menu?.classList.remove('show');
            }
        });
    </script>
    <script src="/assets/js/modal-renderer.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/admin.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/site-settings.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/section-config.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/modern-ui-helpers.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/form-validation-animations.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/admin-animations.js?v=<?php echo time(); ?>"></script>
</body>
</html>
