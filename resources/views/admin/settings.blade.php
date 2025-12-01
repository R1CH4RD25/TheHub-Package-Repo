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
            <button class="subtab-btn" data-subtab="colors">Color Scheme</button>
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
['headerBgColor', 'headerTextColor', 'primaryColor', 'secondaryColor', 'sidebarBg', 'sidebarText'].forEach(id => {
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
