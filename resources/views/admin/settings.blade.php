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
        <!-- Settings Sub-tabs - CONDENSED TO 3 -->
        <div class="user-subtabs">
            <button class="subtab-btn active" data-subtab="appearance">Appearance</button>
            <button class="subtab-btn" data-subtab="behavior">Behavior & Access</button>
            <button class="subtab-btn" data-subtab="system">System</button>
        </div>

        <div class="site-settings-container">
            <!-- APPEARANCE TAB -->
            <div id="subtab-appearance" class="user-subtab active">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Customize the look and feel of your application. Click sections to expand.
                </p>

                <!-- Branding Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-trademark"></i> Branding
                            <span class="badge">3</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label for="orgName">Organization Name</label>
                                <input type="text" id="orgName" class="setting-input" data-key="organization_name">
                                <small>Displayed in header and login page</small>
                            </div>

                            <div class="form-group">
                                <label for="siteName">Site Name</label>
                                <input type="text" id="siteName" class="setting-input" data-key="site_name">
                                <small>Browser tab title</small>
                            </div>

                            <div class="form-group">
                                <label>Logo Upload</label>
                                <div style="border: 2px dashed #dee2e6; border-radius: 8px; padding: 1rem; text-align: center;">
                                    <input type="file" id="logoUpload" accept="image/*" style="display: none;">
                                    <button class="btn btn-secondary" id="logoUploadBtn">
                                        <i class="fas fa-upload"></i> Upload Logo
                                    </button>
                                    <p style="margin-top: 0.5rem; font-size: 0.875rem; color: #6c757d;">PNG, JPG, or SVG (max 2MB)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Header Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-window-maximize"></i> Header
                            <span class="badge">11</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body collapsed">
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
                                <label for="headerBgColor">Background Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="headerBgColor" class="setting-input" data-key="header_bg_color" value="#000000" style="padding:0!important" style="padding:0!important">
                                    <input type="text" id="headerBgColorHex" value="#000000">
                                </div>
                                <small>Navbar background color</small>
                            </div>

                            <div class="form-group">
                                <label for="headerTextColor">Text Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="headerTextColor" class="setting-input" data-key="header_text_color" value="#FFFFFF" style="padding:0!important" style="padding:0!important">
                                    <input type="text" id="headerTextColorHex" value="#FFFFFF">
                                </div>
                                <small>Navbar text and link color</small>
                            </div>

                            <div class="form-group">
                                <label for="headerSubtitleColor">Subtitle Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="headerSubtitleColor" class="setting-input" data-key="header_subtitle_color" value="#FFD700" style="padding:0!important" style="padding:0!important">
                                    <input type="text" id="headerSubtitleColorHex" value="#FFD700">
                                </div>
                                <small>Color for the subtitle under organization name</small>
                            </div>

                            <div class="form-group">
                                <label for="headerTitleFont">Title Font</label>
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
                                <label for="headerSubtitleFont">Subtitle Font</label>
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
                                <label for="headerTitleFontSize">Title Font Size (rem)</label>
                                <input type="number" id="headerTitleFontSize" class="setting-input" data-key="header_title_font_size" value="1.3" min="0.5" max="3" step="0.05">
                                <small>Font size for organization name (0.5-3rem)</small>
                            </div>

                            <div class="form-group">
                                <label for="headerSubtitleFontSize">Subtitle Font Size (rem)</label>
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
                        </div>
                    </div>
                </div>

                <!-- Sidebar Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-bars"></i> Sidebar & Navigation
                            <span class="badge">6</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body collapsed">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label for="sidebarWidth">Sidebar Width (pixels)</label>
                                <input type="number" id="sidebarWidth" class="setting-input" data-key="sidebar_width" value="280" min="200" max="400">
                                <small>Sidebar width when expanded (200-400px)</small>
                            </div>

                            <div class="form-group">
                                <label for="sidebarBg">Background Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="sidebarBg" class="setting-input" data-key="sidebar_bg" value="#FFFFFF" style="padding:0!important">
                                    <input type="text" id="sidebarBgHex" value="#FFFFFF">
                                </div>
                                <small>Sidebar background color</small>
                            </div>

                            <div class="form-group">
                                <label for="sidebarText">Text Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="sidebarText" class="setting-input" data-key="sidebar_text_color" value="#1F2937" style="padding:0!important">
                                    <input type="text" id="sidebarTextHex" value="#1F2937">
                                </div>
                                <small>Sidebar text color</small>
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
                        </div>
                    </div>
                </div>

                <!-- Footer Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-shoe-prints"></i> Footer
                            <span class="badge">7</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body collapsed">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label for="footerHeight">Footer Height (pixels)</label>
                                <input type="number" id="footerHeight" class="setting-input" data-key="footer_height" value="40" min="30" max="80">
                                <small>Footer height in pixels (30-80px)</small>
                            </div>

                            <div class="form-group">
                                <label for="footerTextSize">Text Size (rem)</label>
                                <input type="number" id="footerTextSize" class="setting-input" data-key="footer_text_size" value="0.875" min="0.5" max="1.5" step="0.05">
                                <small>Font size for footer text (0.5-1.5rem)</small>
                            </div>

                            <div class="form-group">
                                <label for="footerBgColor">Background Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="footerBgColor" class="setting-input" data-key="footer_bg_color" value="#F3F4F6" style="padding:0!important">
                                    <input type="text" id="footerBgColorHex" value="#F3F4F6">
                                </div>
                                <small>Footer background color</small>
                            </div>

                            <div class="form-group">
                                <label for="footerTextColor">Text Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="footerTextColor" class="setting-input" data-key="footer_text_color" value="#6B7280" style="padding:0!important">
                                    <input type="text" id="footerTextColorHex" value="#6B7280">
                                </div>
                                <small>Footer text color</small>
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
                </div>

                <!-- Colors & Theme Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-palette"></i> Colors & Theme
                            <span class="badge">6</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body collapsed">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label for="primaryColor">Primary Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="primaryColor" class="setting-input" data-key="primary_color" value="#0F4D8D" style="padding:0!important">
                                    <input type="text" id="primaryColorHex" value="#0F4D8D">
                                </div>
                                <small>Main brand color</small>
                            </div>

                            <div class="form-group">
                                <label for="accentColor">Accent Color</label>
                                <div class="color-picker-wrapper">
                                    <input type="color" id="accentColor" class="setting-input" data-key="accent_color" value="#FFD700" style="padding:0!important">
                                    <input type="text" id="accentColorHex" value="#FFD700">
                                </div>
                                <small>Accent color</small>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem;">Theme Presets</label>
                                <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0 0 1rem 0;">Select a pre-designed theme</p>

                                <div class="theme-gallery-container">
                                    <div class="theme-gallery">
                                    <!-- Woodson Personal Theme (local only, remove before distribution) -->
                                    <div class="theme-preview-card" data-theme="woodson">
                                        <div class="theme-preview-header">
                                            <div class="theme-preview-logo" style="background: rgba(250,204,21,0.18); color: #FACC15;">WD</div>
                                            <div class="theme-preview-title">Woodson</div>
                                        </div>
                                        <div class="theme-preview-body">
                                            <div class="theme-preview-sidebar">
                                                <div class="theme-preview-menu-item">● Dashboard</div>
                                                <div class="theme-preview-menu-item">○ Settings</div>
                                                <div class="theme-preview-menu-item">○ Reports</div>
                                            </div>
                                            <div class="theme-preview-content">
                                                <div class="theme-preview-card-mini"></div>
                                                <div class="theme-preview-card-mini"></div>
                                            </div>
                                        </div>
                                        <div class="theme-preview-footer">
                                            <div class="theme-color-dots">
                                                <span title="Gold"></span>
                                                <span title="Black"></span>
                                                <span title="White"></span>
                                            </div>
                                            <i class="fas fa-check-circle theme-selected-icon"></i>
                                        </div>
                                    </div>

                                    <!-- Notre Dame Theme -->
                                    <div class="theme-preview-card" data-theme="notre-dame">
                                        <div class="theme-preview-header">
                                            <div class="theme-preview-logo">ND</div>
                                            <div class="theme-preview-title">Notre Dame</div>
                                        </div>
                                        <div class="theme-preview-body">
                                            <div class="theme-preview-sidebar">
                                                <div class="theme-preview-menu-item">● Dashboard</div>
                                                <div class="theme-preview-menu-item">○ Settings</div>
                                                <div class="theme-preview-menu-item">○ Reports</div>
                                            </div>
                                            <div class="theme-preview-content">
                                                <div class="theme-preview-card-mini"></div>
                                                <div class="theme-preview-card-mini"></div>
                                            </div>
                                        </div>
                                        <div class="theme-preview-footer">
                                            <div class="theme-color-dots">
                                                <span title="Navy"></span>
                                                <span title="Gold"></span>
                                                <span title="White"></span>
                                            </div>
                                            <i class="fas fa-check-circle theme-selected-icon"></i>
                                        </div>
                                    </div>

                                    <!-- Midnight Theme -->
                                    <div class="theme-preview-card" data-theme="midnight">
                                        <div class="theme-preview-header">
                                            <div class="theme-preview-logo">MN</div>
                                            <div class="theme-preview-title">Midnight</div>
                                        </div>
                                        <div class="theme-preview-body">
                                            <div class="theme-preview-sidebar">
                                                <div class="theme-preview-menu-item">● Dashboard</div>
                                                <div class="theme-preview-menu-item">○ Settings</div>
                                                <div class="theme-preview-menu-item">○ Reports</div>
                                            </div>
                                            <div class="theme-preview-content">
                                                <div class="theme-preview-card-mini"></div>
                                                <div class="theme-preview-card-mini"></div>
                                            </div>
                                        </div>
                                        <div class="theme-preview-footer">
                                            <div class="theme-color-dots">
                                                <span title="Dark"></span>
                                                <span title="Gold"></span>
                                                <span title="Gray"></span>
                                            </div>
                                            <i class="fas fa-check-circle theme-selected-icon"></i>
                                        </div>
                                    </div>

                                    <!-- Ocean Theme -->
                                    <div class="theme-preview-card" data-theme="ocean">
                                        <div class="theme-preview-header">
                                            <div class="theme-preview-logo">OB</div>
                                            <div class="theme-preview-title">Ocean Blue</div>
                                        </div>
                                        <div class="theme-preview-body">
                                            <div class="theme-preview-sidebar">
                                                <div class="theme-preview-menu-item">● Dashboard</div>
                                                <div class="theme-preview-menu-item">○ Settings</div>
                                                <div class="theme-preview-menu-item">○ Reports</div>
                                            </div>
                                            <div class="theme-preview-content">
                                                <div class="theme-preview-card-mini"></div>
                                                <div class="theme-preview-card-mini"></div>
                                            </div>
                                        </div>
                                        <div class="theme-preview-footer">
                                            <div class="theme-color-dots">
                                                <span title="Blue"></span>
                                                <span title="Cyan"></span>
                                                <span title="Light Blue"></span>
                                            </div>
                                            <i class="fas fa-check-circle theme-selected-icon"></i>
                                        </div>
                                    </div>

                                    <!-- Forest Theme -->
                                    <div class="theme-preview-card" data-theme="forest">
                                        <div class="theme-preview-header">
                                            <div class="theme-preview-logo">FG</div>
                                            <div class="theme-preview-title">Forest Green</div>
                                        </div>
                                        <div class="theme-preview-body">
                                            <div class="theme-preview-sidebar">
                                                <div class="theme-preview-menu-item">● Dashboard</div>
                                                <div class="theme-preview-menu-item">○ Settings</div>
                                                <div class="theme-preview-menu-item">○ Reports</div>
                                            </div>
                                            <div class="theme-preview-content">
                                                <div class="theme-preview-card-mini"></div>
                                                <div class="theme-preview-card-mini"></div>
                                            </div>
                                        </div>
                                        <div class="theme-preview-footer">
                                            <div class="theme-color-dots">
                                                <span title="Green"></span>
                                                <span title="Olive"></span>
                                                <span title="Light Green"></span>
                                            </div>
                                            <i class="fas fa-check-circle theme-selected-icon"></i>
                                        </div>
                                    </div>

                                    <!-- Sunset Theme -->
                                    <div class="theme-preview-card" data-theme="sunset">
                                        <div class="theme-preview-header">
                                            <div class="theme-preview-logo">SS</div>
                                            <div class="theme-preview-title">Sunset</div>
                                        </div>
                                        <div class="theme-preview-body">
                                            <div class="theme-preview-sidebar">
                                                <div class="theme-preview-menu-item">● Dashboard</div>
                                                <div class="theme-preview-menu-item">○ Settings</div>
                                                <div class="theme-preview-menu-item">○ Reports</div>
                                            </div>
                                            <div class="theme-preview-content">
                                                <div class="theme-preview-card-mini"></div>
                                                <div class="theme-preview-card-mini"></div>
                                            </div>
                                        </div>
                                        <div class="theme-preview-footer">
                                            <div class="theme-color-dots">
                                                <span title="Red"></span>
                                                <span title="Orange"></span>
                                                <span title="Cream"></span>
                                            </div>
                                            <i class="fas fa-check-circle theme-selected-icon"></i>
                                        </div>
                                    </div>

                                    <!-- Custom Theme -->
                                    <div class="theme-preview-card" data-theme="custom">
                                        <div class="theme-preview-header">
                                            <div class="theme-preview-logo"><i class="fas fa-palette"></i></div>
                                            <div class="theme-preview-title">Custom</div>
                                        </div>
                                        <div class="theme-preview-body">
                                            <div class="theme-preview-sidebar">
                                                <div class="theme-preview-menu-item">● Dashboard</div>
                                                <div class="theme-preview-menu-item">○ Settings</div>
                                                <div class="theme-preview-menu-item">○ Reports</div>
                                            </div>
                                            <div class="theme-preview-content">
                                                <div class="theme-preview-card-mini"></div>
                                                <div class="theme-preview-card-mini"></div>
                                            </div>
                                        </div>
                                        <div class="theme-preview-footer">
                                            <div class="theme-color-dots">
                                                <span title="Purple"></span>
                                                <span title="Violet"></span>
                                                <span title="Gradient"></span>
                                            </div>
                                            <i class="fas fa-check-circle theme-selected-icon"></i>
                                        </div>
                                    </div>
                                </div>
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

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="showMenuIcons" class="setting-input" data-key="show_menu_icons" checked>
                                    <strong>Show Menu Icons</strong>
                                </label>
                                <small>Display icons next to menu items</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- Close .settings-section (Colors & Theme) -->
            </div> <!-- Close #subtab-appearance -->

            <!-- BEHAVIOR & ACCESS TAB -->
            <div id="subtab-behavior" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Configure navigation behavior and access controls.
                </p>

                <!-- Navigation Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-compass"></i> Navigation
                            <span class="badge">1</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="highlightActiveSection" class="setting-input" data-key="highlight_active_section" checked>
                                    <strong>Highlight Active Section</strong>
                                </label>
                                <small>Highlight currently active menu item</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Branding Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-id-badge"></i> Management Branding
                            <span class="badge">3</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label for="cc_display_name">Display Name</label>
                                <input type="text" id="cc_display_name" name="cc_display_name" class="setting-input" data-key="cc_display_name" placeholder="Management">
                                <small>Shown in navigation links, headers, and quick actions</small>
                            </div>

                            <div class="form-group">
                                <label for="cc_icon">Navigation Icon</label>
                                <div class="management-icon-input">
                                    <div class="management-icon-preview">
                                        <i id="managementIconPreview" class="bi-kanban"></i>
                                    </div>
                                    <input type="text" id="cc_icon" name="cc_icon" class="setting-input" data-key="cc_icon" placeholder="bi-kanban">
                                </div>
                                <small>Bootstrap icon class (for example <code>bi-kanban</code>, <code>bi-gear-fill</code>)</small>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label for="cc_description">Description</label>
                                <textarea id="cc_description" name="cc_description" class="setting-input" data-key="cc_description" rows="3" placeholder="Centralized management system for tracking and processing submissions"></textarea>
                                <small>Appears on the management landing experience and module selector</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Management Access Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-toolbox"></i> Management Access
                            <span class="badge">5</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="enableManagementConsole" class="setting-input" data-key="enable_management_console" checked>
                                    <strong>Enable Management Console</strong>
                                </label>
                                <small>Activate the management interface for advanced operations</small>
                            </div>

                            <div class="form-group">
                                <label for="managementSessionTimeout">Session Timeout (minutes)</label>
                                <input type="number" id="managementSessionTimeout" class="setting-input" data-key="management_session_timeout" value="30" min="5" max="120">
                                <small>Shorter timeout for management console (5-120 min)</small>
                            </div>

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="requireMfaForManagement" class="setting-input" data-key="require_mfa_for_management">
                                    <strong>Require MFA for Management</strong>
                                </label>
                                <small>Force multi-factor authentication for management access</small>
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
                </div>
            </div>

            <!-- SYSTEM TAB -->
            <div id="subtab-system" class="user-subtab">
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    Configure system-level settings and security options.
                </p>

                <!-- Sessions Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-clock"></i> Sessions
                            <span class="badge">1</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body">
                        <div class="settings-grid">
                            <div class="form-group">
                                <label for="sessionTimeout">Session Timeout (minutes)</label>
                                <input type="number" id="sessionTimeout" class="setting-input" data-key="session_timeout" value="60" min="15" max="480">
                                <small>Automatic logout after inactivity (15-480 min)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>
                            <i class="fas fa-shield-alt"></i> Security
                            <span class="badge">2</span>
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body collapsed">
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
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="settings-section danger-zone">
                    <div class="settings-section-header danger">
                        <h3>
                            <i class="fas fa-exclamation-triangle"></i> Danger Zone
                        </h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="settings-section-body collapsed">
                        <p style="color: #991B1B; margin-bottom: 1.5rem; font-weight: 500;">
                            <i class="fas fa-exclamation-circle"></i> These actions cannot be undone. Proceed with extreme caution.
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
            </div> <!-- Close #subtab-system -->
        </div> <!-- Close .site-settings-container -->
    </div> <!-- Close .tab-content-scroll -->
</div> <!-- Close .admin-tab -->

@push('styles')
<style nonce="<?php echo CSP_NONCE; ?>">
/* Scoped settings accordion - avoids collision with bundle's .settings-section card */
.site-settings-container {
    min-height: 200px; /* Prevent container collapse */
}

.site-settings-container .settings-section {
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-primary);
    border-radius: 8px;
    overflow: visible !important; /* Ensure content not clipped */
    background: var(--bg-primary);
    padding: 0 !important; /* Override bundle's padding: 2rem */
}

.site-settings-container .settings-section-body {
    padding: 1.5rem;
    max-height: 2000px;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease;
}

.settings-section-header {
    padding: 1rem 1.5rem;
    background: var(--bg-secondary);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.2s;
    user-select: none;
}

.site-settings-container .settings-section-header:hover {
    background: var(--gray-200);
}

.settings-section-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.settings-section-header .badge {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.settings-section-header .toggle-icon {
    color: var(--text-muted);
    transition: transform 0.3s;
}

.site-settings-container .settings-section-header.active .toggle-icon {
    transform: rotate(180deg);
}

.site-settings-container .settings-section-body.collapsed {
    max-height: 0;
    padding: 0;
    overflow: hidden;
}

.management-icon-input {
    display: flex;
    gap: 0.75rem;
    align-items: center;
}

.management-icon-preview {
    width: 42px;
    height: 42px;
    border: 1px solid var(--border-primary);
    border-radius: 8px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    font-size: 1.15rem;
    flex-shrink: 0;
}

.management-icon-preview i {
    line-height: 1;
}

.danger-zone {
    border-color: #DC2626;
}

.danger-zone .settings-section-header.danger {
    background: #FEE2E2;
}

.danger-zone .settings-section-header.danger h3 {
    color: #DC2626;
}

.danger-zone .settings-section-header.danger:hover {
    background: #FEF2F2;
}

/* Theme gallery refresh */
.theme-gallery-container {
    margin: 1.5rem 0 0;
    padding: 1.75rem;
    border-radius: 18px;
    border: 1px solid var(--border-secondary);
    background: linear-gradient(180deg, rgba(255,255,255,0.96) 0%, rgba(245,247,250,0.96) 100%);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.1);
}

.theme-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
}

.theme-preview-card {
    position: relative;
    border-radius: 14px;
    border: 1px solid rgba(148, 163, 184, 0.25);
    background: var(--bg-primary);
    overflow: hidden;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
    isolation: isolate;
}

.theme-preview-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, rgba(148,163,184,0.08) 100%);
    opacity: 0;
    transition: opacity 0.25s ease;
    pointer-events: none;
}

.theme-preview-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.16);
    border-color: rgba(37, 99, 235, 0.45);
}

.theme-preview-card:hover::before {
    opacity: 1;
}

.theme-preview-card.active {
    border-color: var(--primary-color);
    box-shadow: 0 18px 36px rgba(201, 151, 0, 0.28);
}

.theme-preview-card .theme-selected-icon {
    position: absolute;
    top: 14px;
    right: 14px;
    font-size: 1.35rem;
    color: var(--primary-color);
    background: rgba(255,255,255,0.9);
    border-radius: 999px;
    padding: 0.25rem;
    box-shadow: 0 6px 16px rgba(201, 151, 0, 0.22);
    opacity: 0;
    transform: scale(0.7);
    transition: opacity 0.2s ease, transform 0.2s ease;
    pointer-events: none;
}

.theme-preview-card.active .theme-selected-icon {
    opacity: 1;
    transform: scale(1);
}

.theme-preview-header {
    padding: 0.95rem 1.1rem;
    display: flex;
    gap: 0.85rem;
    align-items: center;
    font-weight: 600;
    font-size: 1rem;
    letter-spacing: 0.01em;
}

.theme-preview-logo {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.95rem;
    background: rgba(255,255,255,0.18);
}

.theme-preview-title {
    color: inherit;
}

.theme-preview-body {
    padding: 1rem 1.15rem;
    background: rgba(248, 250, 252, 0.92);
    display: flex;
    gap: 1rem;
}

.theme-preview-sidebar {
    width: 68px;
    padding: 0.55rem;
    border-radius: 9px;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06);
}

.theme-preview-menu-item {
    border-radius: 6px;
    font-size: 0.7rem;
    padding: 0.38rem 0.45rem;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    background: rgba(255,255,255,0.78);
    color: #1F2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.theme-preview-menu-item::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: currentColor;
    opacity: 0.55;
}

.theme-preview-content {
    flex: 1;
    display: grid;
    gap: 0.5rem;
}

.theme-preview-card-mini {
    height: 26px;
    border-radius: 9px;
    background: white;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
}

.theme-preview-footer {
    padding: 0.75rem 1.1rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: transparent;
    border-top: none;
}

.theme-color-dots {
    display: flex;
    gap: 0.45rem;
}

.theme-color-dots span {
    width: 14px;
    height: 14px;
    border-radius: 999px;
    box-shadow: 0 6px 10px rgba(15, 23, 42, 0.12);
    border: 2px solid rgba(255, 255, 255, 0.65);
}

@media (max-width: 640px) {
    .theme-gallery-container {
        padding: 1.25rem;
    }

    .theme-preview-card {
        border-radius: 12px;
    }

    .theme-preview-header {
        padding: 0.85rem 1rem;
    }

    .theme-preview-body {
        flex-direction: column;
        padding: 0.85rem 1rem;
    }

    .theme-preview-sidebar {
        width: 100%;
        flex-direction: row;
        justify-content: space-between;
        gap: 0.5rem;
    }
}
</style>
@endpush

@push('scripts')
<script nonce="<?php echo CSP_NONCE; ?>">
const csrfToken = '{{ csrf_token() }}';
let originalSettings = {};
let currentSettings = {};

function updateManagementIconPreview(iconClass) {
    const preview = document.getElementById('managementIconPreview');
    if (preview) {
        const value = (iconClass || '').trim();
        preview.className = value !== '' ? value : 'bi-kanban';
    }
}

// Section toggle - bind event listeners instead of inline onclick
document.addEventListener('DOMContentLoaded', () => {
    console.log('═══════════════════════════════════════════════════');
    console.log('🚀 SETTINGS PAGE DEBUG - DOM STRUCTURE CHECK');
    console.log('═══════════════════════════════════════════════════');
    
    // Check container structure
    const container = document.querySelector('.site-settings-container');
    console.log('📦 .site-settings-container found:', !!container);
    if (container) {
        console.log('   Children:', container.children.length);
        Array.from(container.children).forEach((child, i) => {
            console.log(`   Child ${i}:`, child.tagName, child.id, child.className);
        });
    }
    
    // Check all user-subtab elements
    const allSubtabs = document.querySelectorAll('.user-subtab');
    console.log('\n🔍 Found .user-subtab elements:', allSubtabs.length);
    allSubtabs.forEach((tab, i) => {
        const sectionsInside = tab.querySelectorAll('.settings-section');
        console.log(`   Tab ${i}: #${tab.id}`, {
            classes: tab.className,
            hasActive: tab.classList.contains('active'),
            sectionsInside: sectionsInside.length,
            parent: tab.parentElement?.className
        });
    });
    
    // Check subtab buttons
    const buttons = document.querySelectorAll('.subtab-btn');
    console.log('\n🔘 Found .subtab-btn elements:', buttons.length);
    buttons.forEach((btn, i) => {
        console.log(`   Button ${i}:`, btn.getAttribute('data-subtab'), {
            hasActive: btn.classList.contains('active'),
            text: btn.textContent.trim()
        });
    });
    
    console.log('═══════════════════════════════════════════════════\n');
    
    // CSS Version Debug
    const links = document.querySelectorAll('link[rel="stylesheet"]');
    console.log('🎨 DEBUG: CSS files loaded:');
    links.forEach(link => {
        const url = new URL(link.href);
        console.log(`  ${url.pathname}${url.search}`);
    });

    // Accordion toggles
    const allSectionHeaders = document.querySelectorAll('.settings-section-header');
    console.log('🔧 Setting up accordion toggles for', allSectionHeaders.length, 'headers');
    
    document.querySelectorAll('.settings-section-header').forEach((header, index) => {
        header.addEventListener('click', () => {
            const wasActive = header.classList.contains('active');
            const body = header.nextElementSibling;
            const wasCollapsed = body?.classList.contains('collapsed');
            const headerText = header.textContent.trim().replace(/\s+/g, ' ').substring(0, 30);
            
            console.log(`🔧 ACCORDION CLICK ${index}: "${headerText}"`);
            console.log(`   Before: header.active=${wasActive}, body.collapsed=${wasCollapsed}`);
            
            header.classList.toggle('active');
            body?.classList.toggle('collapsed');
            
            const nowActive = header.classList.contains('active');
            const nowCollapsed = body?.classList.contains('collapsed');
            console.log(`   After: header.active=${nowActive}, body.collapsed=${nowCollapsed}`);
            
            if (body) {
                const bodyStyleImmediate = window.getComputedStyle(body);
                console.log(`   Body IMMEDIATE: maxHeight=${bodyStyleImmediate.maxHeight}, height=${bodyStyleImmediate.height}`);
                
                // Check again after transition completes (350ms)
                setTimeout(() => {
                    const bodyStyleFinal = window.getComputedStyle(body);
                    console.log(`   Body AFTER TRANSITION: maxHeight=${bodyStyleFinal.maxHeight}, height=${bodyStyleFinal.height}`);
                }, 350);
            }
        });
    });

    // Logo upload button
    const logoBtn = document.getElementById('logoUploadBtn');
    const logoInput = document.getElementById('logoUpload');
    if (logoBtn && logoInput) {
        logoBtn.addEventListener('click', () => logoInput.click());
    }

    // Subtab switching with DEBUG
    const subtabButtons = document.querySelectorAll('.subtab-btn');
    console.log('🔍 DEBUG: Found subtab buttons:', subtabButtons.length);

    subtabButtons.forEach((btn, index) => {
        console.log(`🔍 DEBUG: Button ${index}:`, btn.getAttribute('data-subtab'), btn.classList.contains('active'));

        btn.addEventListener('click', function() {
            const subtab = this.getAttribute('data-subtab');
            console.log('🔵 DEBUG: Clicked tab:', subtab);

            // Remove active from all buttons
            subtabButtons.forEach(b => {
                b.classList.remove('active');
                console.log(`  ❌ Removed active from button:`, b.getAttribute('data-subtab'));
            });

            // Add active to clicked button
            this.classList.add('active');
            console.log(`  ✅ Added active to button:`, subtab);

            // Remove active from all tab content
            const allTabs = document.querySelectorAll('.user-subtab');
            console.log('🔍 DEBUG: Found tab contents:', allTabs.length);
            allTabs.forEach(s => {
                s.classList.remove('active');
                console.log(`  ❌ Removed active from content:`, s.id);
            });

            // Add active to target tab content
            const targetTab = document.getElementById(`subtab-${subtab}`);
            console.log('🔍 DEBUG: Target tab element:', targetTab);
            if (targetTab) {
                targetTab.classList.add('active');
                console.log(`  ✅ Added active to content:`, targetTab.id);

                // Check computed styles
                const computedStyle = window.getComputedStyle(targetTab);
                console.log('📊 DEBUG: Computed display:', computedStyle.display);
                console.log('📊 DEBUG: Computed visibility:', computedStyle.visibility);
                console.log('📊 DEBUG: Computed opacity:', computedStyle.opacity);
                console.log('📊 DEBUG: Computed height:', computedStyle.height);

                // Check if content actually exists inside
                const contentSections = targetTab.querySelectorAll('.settings-section');
                console.log('📦 DEBUG: Sections inside tab:', contentSections.length);
                contentSections.forEach((section, i) => {
                    const header = section.querySelector('.settings-section-header');
                    const body = section.querySelector('.settings-section-body');
                    const headerText = header ? header.textContent.trim().replace(/\s+/g, ' ').substring(0, 40) : 'none';
                    console.log(`  📦 Section ${i}: ${headerText}`, {
                        bodyExists: !!body,
                        bodyCollapsed: body?.classList.contains('collapsed'),
                        bodyDisplay: body ? window.getComputedStyle(body).display : 'none',
                        bodyHeight: body ? window.getComputedStyle(body).height : 'none',
                        bodyMaxHeight: body ? window.getComputedStyle(body).maxHeight : 'none'
                    });
                });

                // Check tab position and visibility
                const rect = targetTab.getBoundingClientRect();
                console.log('📐 DEBUG: Tab position:', {
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height,
                    bottom: rect.bottom,
                    isInViewport: rect.top >= 0 && rect.left >= 0 && rect.bottom <= window.innerHeight && rect.right <= window.innerWidth
                });

                // Check ALL computed styles on tab
                const tabStyle = window.getComputedStyle(targetTab);
                console.log('🎨 DEBUG: Tab computed styles:', {
                    display: tabStyle.display,
                    position: tabStyle.position,
                    width: tabStyle.width,
                    height: tabStyle.height,
                    minHeight: tabStyle.minHeight,
                    maxHeight: tabStyle.maxHeight,
                    flex: tabStyle.flex,
                    flexBasis: tabStyle.flexBasis,
                    flexGrow: tabStyle.flexGrow,
                    flexShrink: tabStyle.flexShrink
                });

                // Check first child (p tag) dimensions
                const firstChild = targetTab.firstElementChild;
                if (firstChild) {
                    const childRect = firstChild.getBoundingClientRect();
                    const childStyle = window.getComputedStyle(firstChild);
                    console.log('📏 DEBUG: First child element:', {
                        tagName: firstChild.tagName,
                        rect: { width: childRect.width, height: childRect.height },
                        display: childStyle.display,
                        width: childStyle.width,
                        height: childStyle.height
                    });
                }

                // Check first .settings-section
                const firstSection = targetTab.querySelector('.settings-section');
                if (firstSection) {
                    const sectionRect = firstSection.getBoundingClientRect();
                    const sectionStyle = window.getComputedStyle(firstSection);
                    console.log('📦 DEBUG: First .settings-section:', {
                        rect: { width: sectionRect.width, height: sectionRect.height },
                        display: sectionStyle.display,
                        width: sectionStyle.width,
                        height: sectionStyle.height,
                        overflow: sectionStyle.overflow
                    });
                }

                // Check parent container
                const container = targetTab.closest('.site-settings-container');
                if (container) {
                    const containerStyle = window.getComputedStyle(container);
                    const containerRect = container.getBoundingClientRect();
                    console.log('📦 DEBUG: Container .site-settings-container:', {
                        display: containerStyle.display,
                        height: containerStyle.height,
                        maxHeight: containerStyle.maxHeight,
                        overflow: containerStyle.overflow,
                        position: containerStyle.position,
                        rect: { width: containerRect.width, height: containerRect.height }
                    });
                }
            } else {
                console.error('❌ ERROR: Target tab not found:', `subtab-${subtab}`);
            }
        });
    });

    // Ensure default active subtab content is visible
    const initialActiveBtn = document.querySelector('.subtab-btn.active') || subtabButtons[0];
    console.log('🔍 DEBUG: Initial active button:', initialActiveBtn?.getAttribute('data-subtab'));
    if (initialActiveBtn) {
        const subtab = initialActiveBtn.getAttribute('data-subtab');
        const initialTab = document.getElementById(`subtab-${subtab}`);
        console.log('🔍 DEBUG: Initial tab element:', initialTab);
        if (initialTab) {
            initialTab.classList.add('active');
            console.log('✅ DEBUG: Set initial tab active:', subtab);
            const computedStyle = window.getComputedStyle(initialTab);
            console.log('📊 DEBUG: Initial display:', computedStyle.display);
        }
    }
});

// Load settings
fetch('/admin/settings/get')
    .then(r => r.json())
    .then(settings => {
        originalSettings = { ...settings };
        currentSettings = { ...settings };
        populateSettings(settings);
        highlightActiveTheme(settings);
    });

function populateSettings(settings) {
    document.querySelectorAll('.setting-input').forEach(input => {
        const key = input.getAttribute('data-key');
        if (key && settings[key] !== undefined) {
            if (input.type === 'checkbox') {
                const value = settings[key];
                input.checked = value === true || value === '1' || value === 1 || value === 'true';
            } else {
                input.value = settings[key];
            }

            if (input.type === 'color') {
                const hexInput = document.getElementById(`${input.id}Hex`);
                if (hexInput) {
                    hexInput.value = input.value;
                }
            }

            if (key === 'cc_icon') {
                if (!input.value) {
                    input.value = 'bi-kanban';
                    currentSettings[key] = 'bi-kanban';
                }
                updateManagementIconPreview(input.value);
            } else if (key === 'cc_display_name' && !input.value) {
                input.value = 'Management';
                currentSettings[key] = 'Management';
            } else if (key === 'cc_description' && !input.value) {
                const fallback = 'Centralized management system for tracking and processing submissions';
                input.value = fallback;
                currentSettings[key] = fallback;
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

        if (key === 'cc_icon') {
            updateManagementIconPreview(this.value);
        }

        highlightActiveTheme(currentSettings);
    });
});

// Color picker sync
['headerBgColor', 'headerTextColor', 'headerSubtitleColor', 'footerBgColor', 'footerTextColor',
 'primaryColor', 'accentColor', 'sidebarBg', 'sidebarText'].forEach(id => {
    const colorInput = document.getElementById(id);
    const hexInput = document.getElementById(id + 'Hex');

    if (colorInput && hexInput) {
        colorInput.addEventListener('input', () => hexInput.value = colorInput.value);
        hexInput.addEventListener('input', () => {
            if (/^#[0-9A-F]{6}$/i.test(hexInput.value)) {
                colorInput.value = hexInput.value;
                colorInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }
});

const commandCenterIconInput = document.getElementById('cc_icon');
if (commandCenterIconInput) {
    commandCenterIconInput.addEventListener('input', function() {
        currentSettings['cc_icon'] = this.value;
        updateManagementIconPreview(this.value);
    });
}

// Theme presets
const themePresets = {
    // NOTE: Woodson preset is personal/local only. Remove before packaging for distribution.
    'woodson': {
        primary_color: '#C99700',
        accent_color: '#111827',
        navbar_color: '#000000',
        background_color: '#FFFFFF',
        header_bg_color: '#000000',
        header_text_color: '#FFFFFF',
        header_subtitle_color: '#C99700',
        sidebar_bg: '#FFFFFF',
        sidebar_text_color: '#1F2937',
        footer_bg_color: '#111827',
        footer_text_color: '#E5E7EB'
    },
    'notre-dame': {
        primary_color: '#C99700',
        accent_color: '#0C2340',
        navbar_color: '#0C2340',
        background_color: '#FFFFFF',
        header_bg_color: '#0C2340',
        header_text_color: '#FFFFFF',
        header_subtitle_color: '#C99700',
        sidebar_bg: '#FFFFFF',
        sidebar_text_color: '#1F2937',
        footer_bg_color: '#F3F4F6',
        footer_text_color: '#6B7280'
    },
    'midnight': {
        primary_color: '#FFD700',
        accent_color: '#1A1A1A',
        navbar_color: '#1A1A1A',
        background_color: '#1A1A1A',
        header_bg_color: '#1A1A1A',
        header_text_color: '#FFFFFF',
        header_subtitle_color: '#FFD700',
        sidebar_bg: '#323130',
        sidebar_text_color: '#FFFFFF',
        footer_bg_color: '#1A1A1A',
        footer_text_color: '#C8C6C4'
    },
    'ocean': {
        primary_color: '#0078D4',
        accent_color: '#00BCF2',
        navbar_color: '#0078D4',
        background_color: '#E3F2FD',
        header_bg_color: '#0078D4',
        header_text_color: '#FFFFFF',
        header_subtitle_color: '#00BCF2',
        sidebar_bg: '#E3F2FD',
        sidebar_text_color: '#005A9E',
        footer_bg_color: '#0078D4',
        footer_text_color: '#FFFFFF'
    },
    'forest': {
        primary_color: '#107C10',
        accent_color: '#498205',
        navbar_color: '#107C10',
        background_color: '#E8F5E9',
        header_bg_color: '#107C10',
        header_text_color: '#FFFFFF',
        header_subtitle_color: '#90EE90',
        sidebar_bg: '#E8F5E9',
        sidebar_text_color: '#1B5E20',
        footer_bg_color: '#F1F8F4',
        footer_text_color: '#2E7D32'
    },
    'sunset': {
        primary_color: '#D13438',
        accent_color: '#F7630C',
        navbar_color: '#D13438',
        background_color: '#FFF4E5',
        header_bg_color: '#D13438',
        header_text_color: '#FFFFFF',
        header_subtitle_color: '#FFD700',
        sidebar_bg: '#FFF4E5',
        sidebar_text_color: '#C62828',
        footer_bg_color: '#FFEBEE',
        footer_text_color: '#B71C1C'
    }
};

function highlightActiveTheme(settings, forceTheme = null) {
    document.querySelectorAll('.theme-preview-card').forEach(card => card.classList.remove('active'));

    if (forceTheme === 'custom') {
        document.querySelector('.theme-preview-card[data-theme="custom"]')?.classList.add('active');
        return;
    }

    let matchedTheme = null;

    Object.entries(themePresets).forEach(([themeName, preset]) => {
        const matches = Object.entries(preset).every(([key, presetValue]) => {
            if (!(key in settings)) {
                return false;
            }

            const settingValue = settings[key];
            if (typeof presetValue === 'string' && presetValue.startsWith('#')) {
                return String(settingValue).toUpperCase() === presetValue.toUpperCase();
            }

            return String(settingValue) === String(presetValue);
        });

        if (matches) {
            matchedTheme = themeName;
        }
    });

    if (matchedTheme) {
        document.querySelector(`.theme-preview-card[data-theme="${matchedTheme}"]`)?.classList.add('active');
    } else {
        document.querySelector('.theme-preview-card[data-theme="custom"]')?.classList.add('active');
    }
}

document.querySelectorAll('.theme-preview-card').forEach(card => {
    card.addEventListener('click', function() {
        const themeName = this.getAttribute('data-theme');

        if (themeName === 'custom') {
            highlightActiveTheme(currentSettings, 'custom');
            notyf.success('Custom theme selected - use color pickers to customize');
            return;
        }

        const preset = themePresets[themeName];
        if (preset) {
            Object.keys(preset).forEach(key => {
                currentSettings[key] = preset[key];
                const input = document.querySelector(`[data-key="${key}"]`);
                if (input) {
                    if (input.type === 'color') {
                        input.value = preset[key];
                        const hexInput = document.getElementById(`${input.id}Hex`);
                        if (hexInput) {
                            hexInput.value = preset[key];
                        }
                    } else if (input.type === 'checkbox') {
                        input.checked = preset[key] === true || preset[key] === '1' || preset[key] === 1 || preset[key] === 'true';
                    } else {
                        input.value = preset[key];
                    }

                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            highlightActiveTheme(currentSettings);
            const title = this.querySelector('.theme-preview-title')?.textContent ?? 'Theme';
            notyf.success(`${title} theme applied`);
        }
    });
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
    highlightActiveTheme(currentSettings);
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
