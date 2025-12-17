@extends('layouts.admin')

@section('title', 'Site Settings')

@section('content')
<div class="admin-tab active">
    <div class="tab-header">
        <div>
            <h1><i class="fas fa-cog"></i> Site Settings</h1>
            <p class="text-muted">Configure branding, colors, and site-wide preferences</p>
        </div>
        <div class="tab-actions">
            <button id="saveSiteSettings" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button id="cancelSiteSettings" class="btn btn-secondary">Cancel</button>
        </div>
    </div>

    <div class="tab-content-scroll">
        <!-- Settings Sub-tabs -->
        <div class="user-subtabs">
            <button class="subtab-btn active" data-subtab="header-footer">Header & Footer</button>
            <button class="subtab-btn" data-subtab="branding">Branding & Images</button>
            <button class="subtab-btn" data-subtab="sidebar">Sidebar & Menu</button>
            <button class="subtab-btn" data-subtab="colors">Color Scheme</button>
            <button class="subtab-btn" data-subtab="themes">Themes</button>
            <button class="subtab-btn" data-subtab="management">Management</button>
            <button class="subtab-btn" data-subtab="advanced">Advanced</button>
        </div>

        <div class="site-settings-container">
            <!-- Header & Footer Subtab -->
            <div id="subtab-header-footer" class="user-subtab active">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Customize header and footer appearance and behavior.
                </p>

                <div class="settings-grid">
                    <div class="form-group">
                        <label for="headerHeight">Header Height (pixels)</label>
                        <input type="number" id="headerHeight" class="setting-input" data-key="header_height" value="80" min="60" max="150">
                        <small>Navbar height in pixels (60-150px)</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="headerMatchLogoHeight" class="setting-input" data-key="header_match_logo_height">
                            <strong>Auto-adjust to Logo Height</strong>
                        </label>
                        <small>Header will automatically match uploaded logo height + padding</small>
                    </div>

                    <div class="form-group">
                        <label for="navbarSubtitle">Navbar Subtitle</label>
                        <input type="text" id="navbarSubtitle" class="setting-input" data-key="navbar_subtitle" value="The Hub">
                        <small>Shown under organization name in navbar</small>
                    </div>

                    <div class="form-group">
                        <label for="headerBgColor">Header Background Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="headerBgColor" class="setting-input" data-key="header_bg_color" value="#000000">
                            <input type="text" id="headerBgColorHex" value="#000000">
                        </div>
                        <small>Navbar background color</small>
                    </div>

                    <div class="form-group">
                        <label for="headerTextColor">Header Text Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="headerTextColor" class="setting-input" data-key="header_text_color" value="#FFFFFF">
                            <input type="text" id="headerTextColorHex" value="#FFFFFF">
                        </div>
                        <small>Navbar text and link color</small>
                    </div>

                    <div class="form-group">
                        <label for="headerSubtitleColor">Header Subtitle Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="headerSubtitleColor" class="setting-input" data-key="header_subtitle_color" value="#FFD700">
                            <input type="text" id="headerSubtitleColorHex" value="#FFD700">
                        </div>
                        <small>Color for the subtitle under organization name</small>
                    </div>

                    <div class="form-group">
                        <label for="headerTitleFont">Header Title Font</label>
                        <select id="headerTitleFont" class="setting-input" data-key="header_title_font">
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
                        <select id="headerSubtitleFont" class="setting-input" data-key="header_subtitle_font">
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
                        <input type="number" id="headerTitleFontSize" class="setting-input" data-key="header_title_font_size" value="1.3" min="0.5" max="3" step="0.05">
                        <small>Font size for organization name (0.5-3rem)</small>
                    </div>

                    <div class="form-group">
                        <label for="headerSubtitleFontSize">Header Subtitle Font Size (rem)</label>
                        <input type="number" id="headerSubtitleFontSize" class="setting-input" data-key="header_subtitle_font_size" value="0.85" min="0.5" max="2" step="0.05">
                        <small>Font size for subtitle text (0.5-2rem)</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="headerShowSubtitle" class="setting-input" data-key="header_show_subtitle" checked>
                            <strong>Show Header Subtitle</strong>
                        </label>
                        <small>Display subtitle below organization name</small>
                    </div>

                    <div class="form-group">
                        <label for="footerHeight">Footer Height (pixels)</label>
                        <input type="number" id="footerHeight" class="setting-input" data-key="footer_height" value="40" min="30" max="80">
                        <small>Footer height in pixels (30-80px)</small>
                    </div>

                    <div class="form-group">
                        <label for="footerTextSize">Footer Text Size (rem)</label>
                        <input type="number" id="footerTextSize" class="setting-input" data-key="footer_text_size" value="0.875" min="0.5" max="1.5" step="0.05">
                        <small>Font size for footer text (0.5-1.5rem)</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="footerShowVersion" class="setting-input" data-key="footer_show_version" checked>
                            <strong>Show Version Number</strong>
                        </label>
                        <small>Display version info in footer</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="footerShowUser" class="setting-input" data-key="footer_show_user" checked>
                            <strong>Show Current User</strong>
                        </label>
                        <small>Display logged-in username in footer</small>
                    </div>

                    <div class="form-group">
                        <label for="footerCustomText">Custom Footer Text</label>
                        <input type="text" id="footerCustomText" class="setting-input" data-key="footer_custom_text" placeholder="Optional">
                        <small>Additional text to display in footer</small>
                    </div>

                    <div class="form-group">
                        <label for="footerBgColor">Footer Background Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="footerBgColor" class="setting-input" data-key="footer_bg_color" value="#F3F4F6">
                            <input type="text" id="footerBgColorHex" value="#F3F4F6">
                        </div>
                        <small>Footer background color</small>
                    </div>

                    <div class="form-group">
                        <label for="footerTextColor">Footer Text Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="footerTextColor" class="setting-input" data-key="footer_text_color" value="#6B7280">
                            <input type="text" id="footerTextColorHex" value="#6B7280">
                        </div>
                        <small>Footer text color</small>
                    </div>
                </div>
            </div>

            <!-- Branding Subtab -->
            <div id="subtab-branding" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Upload logos and customize branding elements.
                </p>

                <div class="settings-grid">
                    <div class="form-group">
                        <label for="orgName">Organization Name</label>
                        <input type="text" id="orgName" class="setting-input" data-key="org_name" value="">
                        <small>Displayed in header and login page</small>
                    </div>

                    <div class="form-group">
                        <label for="orgShortName">Short Name</label>
                        <input type="text" id="orgShortName" class="setting-input" data-key="org_short_name" value="">
                        <small>Abbreviated organization name</small>
                    </div>

                    <div class="form-group">
                        <label>Logo Upload</label>
                        <div style="border: 2px dashed #dee2e6; border-radius: 8px; padding: 1rem; text-align: center;">
                            <input type="file" id="logoUpload" accept="image/*" style="display: none;">
                            <button class="btn btn-secondary" onclick="document.getElementById('logoUpload').click()">
                                <i class="fas fa-upload"></i> Upload Logo
                            </button>
                            <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6c757d;">PNG, JPG, or SVG (max 2MB)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar & Menu Subtab -->
            <div id="subtab-sidebar" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Configure sidebar and navigation menu behavior.
                </p>

                <div class="settings-grid">
                    <div class="form-group">
                        <label for="sidebarWidth">Sidebar Width (pixels)</label>
                        <input type="number" id="sidebarWidth" class="setting-input" data-key="sidebar_width" value="280" min="200" max="400">
                        <small>Sidebar width when expanded (200-400px)</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="sidebarCollapsible" class="setting-input" data-key="sidebar_collapsible" checked>
                            <strong>Collapsible Sidebar</strong>
                        </label>
                        <small>Allow users to collapse/expand sidebar</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="sidebarDefaultCollapsed" class="setting-input" data-key="sidebar_default_collapsed">
                            <strong>Start Collapsed</strong>
                        </label>
                        <small>Sidebar collapsed by default on page load</small>
                    </div>

                    <div class="form-group">
                        <label for="menuItemSpacing">Menu Item Spacing (rem)</label>
                        <input type="number" id="menuItemSpacing" class="setting-input" data-key="menu_item_spacing" value="0.5" min="0.25" max="2" step="0.05">
                        <small>Vertical spacing between menu items (0.25-2rem)</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="showMenuIcons" class="setting-input" data-key="show_menu_icons" checked>
                            <strong>Show Menu Icons</strong>
                        </label>
                        <small>Display icons next to menu items</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="highlightActiveSection" class="setting-input" data-key="highlight_active_section" checked>
                            <strong>Highlight Active Section</strong>
                        </label>
                        <small>Highlight currently active menu item</small>
                    </div>
                </div>
            </div>

            <!-- Colors Subtab -->
            <div id="subtab-colors" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Customize the color scheme for your site.
                </p>

                <div class="settings-grid">
                    <div class="form-group">
                        <label for="primaryColor">Primary Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="primaryColor" class="setting-input" data-key="primary_color" value="#0F4D8D">
                            <input type="text" id="primaryColorHex" value="#0F4D8D">
                        </div>
                        <small>Main brand color</small>
                    </div>

                    <div class="form-group">
                        <label for="secondaryColor">Secondary Color</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="secondaryColor" class="setting-input" data-key="secondary_color" value="#FFD700">
                            <input type="text" id="secondaryColorHex" value="#FFD700">
                        </div>
                        <small>Accent color</small>
                    </div>

                    <div class="form-group">
                        <label for="sidebarBg">Sidebar Background</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="sidebarBg" class="setting-input" data-key="sidebar_bg_color" value="#FFFFFF">
                            <input type="text" id="sidebarBgHex" value="#FFFFFF">
                        </div>
                        <small>Sidebar background color</small>
                    </div>

                    <div class="form-group">
                        <label for="sidebarText">Sidebar Text</label>
                        <div class="color-picker-wrapper">
                            <input type="color" id="sidebarText" class="setting-input" data-key="sidebar_text_color" value="#1F2937">
                            <input type="text" id="sidebarTextHex" value="#1F2937">
                        </div>
                        <small>Sidebar text color</small>
                    </div>
                </div>
            </div>

            <!-- Themes Subtab -->
            <div id="subtab-themes" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Choose from pre-built themes or customize your own.
                </p>

                <div class="settings-grid">
                    <div class="form-group">
                        <label for="activeTheme">Active Theme</label>
                        <select id="activeTheme" class="setting-input" data-key="active_theme">
                            <option value="default">Default Theme</option>
                            <option value="dark">Dark Mode</option>
                            <option value="light">Light Mode</option>
                            <option value="highcontrast">High Contrast</option>
                            <option value="custom">Custom Theme</option>
                        </select>
                        <small>Select a pre-built theme or use custom colors</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="allowUserThemes" class="setting-input" data-key="allow_user_themes">
                            <strong>Allow User Theme Selection</strong>
                        </label>
                        <small>Let users choose their own theme preference</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="respectSystemTheme" class="setting-input" data-key="respect_system_theme">
                            <strong>Respect System Theme</strong>
                        </label>
                        <small>Auto-switch based on OS dark/light mode preference</small>
                    </div>
                </div>
            </div>

            <!-- Management Subtab -->
            <div id="subtab-management" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Configure management console and administrative features.
                </p>

                <div class="settings-grid">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="enableManagementConsole" class="setting-input" data-key="enable_management_console" checked>
                            <strong>Enable Management Console</strong>
                        </label>
                        <small>Activate the management interface for advanced operations</small>
                    </div>

                    <div class="form-group">
                        <label for="managementDisplayName">Management Display Name</label>
                        <input type="text" id="managementDisplayName" class="setting-input" data-key="management_display_name" value="Management">
                        <small>Label shown in navigation for management console</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="requireMfaForManagement" class="setting-input" data-key="require_mfa_for_management">
                            <strong>Require MFA for Management</strong>
                        </label>
                        <small>Force multi-factor authentication for management access</small>
                    </div>

                    <div class="form-group">
                        <label for="managementSessionTimeout">Management Session (minutes)</label>
                        <input type="number" id="managementSessionTimeout" class="setting-input" data-key="management_session_timeout" value="30" min="5" max="120">
                        <small>Shorter timeout for management console (5-120 min)</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="logManagementActions" class="setting-input" data-key="log_management_actions" checked>
                            <strong>Log Management Actions</strong>
                        </label>
                        <small>Track all management console operations in audit log</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="showManagementBadge" class="setting-input" data-key="show_management_badge" checked>
                            <strong>Show Management Badge</strong>
                        </label>
                        <small>Display indicator when in management mode</small>
                    </div>
                </div>
            </div>

            <!-- Advanced Subtab -->
            <div id="subtab-advanced" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Advanced configuration options.
                </p>

                <div class="settings-grid">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="maintenanceMode" class="setting-input" data-key="maintenance_mode">
                            <strong>Maintenance Mode</strong>
                        </label>
                        <small>Block access to all users except super admins</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="debugMode" class="setting-input" data-key="debug_mode">
                            <strong>Debug Mode</strong>
                        </label>
                        <small>Show detailed error messages (development only)</small>
                    </div>

                    <div class="form-group">
                        <label for="sessionTimeout">Session Timeout (minutes)</label>
                        <input type="number" id="sessionTimeout" class="setting-input" data-key="session_timeout" value="60" min="15" max="480">
                        <small>Automatic logout after inactivity (15-480 min)</small>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="color-section" style="margin-top: 2rem; border: 2px solid #DC2626; border-radius: 8px;">
                    <div style="background: #FEE2E2; padding: 1rem; border-radius: 6px 6px 0 0;">
                        <h3 style="color: #DC2626; margin: 0; font-size: 1.1rem;">
                            <i class="fas fa-exclamation-triangle"></i> Danger Zone
                        </h3>
                    </div>
                    <div style="padding: 1rem;">
                        <p style="color: #991B1B; margin-bottom: 1rem;">
                            These actions cannot be undone. Proceed with extreme caution.
                        </p>
                        <div class="settings-grid">
                            <div class="form-group">
                                <button id="resetToDefaults" class="btn btn-danger">
                                    <i class="fas fa-undo"></i> Reset All Settings to Defaults
                                </button>
                                <small>WARNING: This will restore all settings to their original values</small>
                            </div>

                            <div class="form-group">
                                <button id="clearAllSessions" class="btn btn-danger">
                                    <i class="fas fa-sign-out-alt"></i> Clear All Active Sessions
                                </button>
                                <small>WARNING: Force logout all users (including yourself)</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
let originalSettings = {};
let currentSettings = {};

// Subtab switching
document.querySelectorAll('.subtab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const subtab = this.getAttribute('data-subtab');

        document.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');

        document.querySelectorAll('.user-subtab').forEach(s => s.classList.remove('active'));
        document.getElementById(`subtab-${subtab}`).classList.add('active');
    });
});

// Load settings
fetch('/admin/settings/get')
    .then(r => r.json())
    .then(settings => {
        originalSettings = { ...settings };
        currentSettings = { ...settings };
        populateSettings(settings);
    });

function populateSettings(settings) {
    document.querySelectorAll('.setting-input').forEach(input => {
        const key = input.getAttribute('data-key');
        if (key && settings[key] !== undefined) {
            if (input.type === 'checkbox') {
                input.checked = settings[key];
            } else {
                input.value = settings[key];
            }
        }
    });
}

// Track changes
document.querySelectorAll('.setting-input').forEach(input => {
    input.addEventListener('change', function() {
        const key = this.getAttribute('data-key');
        if (this.type === 'checkbox') {
            currentSettings[key] = this.checked;
        } else {
            currentSettings[key] = this.value;
        }
    });
});

// Color picker sync
['headerBgColor', 'headerTextColor', 'headerSubtitleColor', 'footerBgColor', 'footerTextColor',
 'primaryColor', 'secondaryColor', 'sidebarBg', 'sidebarText'].forEach(id => {
    const colorInput = document.getElementById(id);
    const hexInput = document.getElementById(id + 'Hex');

    if (colorInput && hexInput) {
        colorInput.addEventListener('input', () => hexInput.value = colorInput.value);
        hexInput.addEventListener('input', () => {
            if (/^#[0-9A-F]{6}$/i.test(hexInput.value)) {
                colorInput.value = hexInput.value;
            }
        });
    }
});

// Save settings
document.getElementById('saveSiteSettings').addEventListener('click', function() {
    fetch('/admin/settings', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(currentSettings)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            notyf.success(data.message);
            originalSettings = { ...currentSettings };
        } else {
            notyf.error(data.error || 'Save failed');
        }
    });
});

// Cancel changes
document.getElementById('cancelSiteSettings').addEventListener('click', function() {
    currentSettings = { ...originalSettings };
    populateSettings(originalSettings);
    notyf.success('Changes discarded');
});

// Reset to defaults
document.getElementById('resetToDefaults')?.addEventListener('click', function() {
    Swal.fire({
        title: 'Reset All Settings?',
        text: 'This will restore all default values',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, reset',
        confirmButtonColor: '#d32f2f'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/admin/settings/reset', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    notyf.success('Settings reset');
                    location.reload();
                } else {
                    notyf.error('Reset failed');
                }
            });
        }
    });
});
</script>
@endpush
@endsection
