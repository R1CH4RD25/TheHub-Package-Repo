/**
 * Site Settings Management
 * JavaScript for handling site configuration
 */

// Debug mode - logs only show when enabled
const DEBUG_MODE = window.APP_DEBUG_MODE || false;
const debugLog = (...args) => {
    if (DEBUG_MODE) console.log(...args);
};

// Track if there are unsaved changes
let hasUnsavedChanges = false;

// Store original values to detect actual changes
let originalValues = {};

document.addEventListener('DOMContentLoaded', function () {
    // Only run on site settings tab
    if (!document.getElementById('tab-site-settings')) return;

    // Load settings when tab is opened
    loadSiteSettings();

    // File upload handlers
    const logoUpload = document.getElementById('logoUpload');
    const faviconUpload = document.getElementById('faviconUpload');
    const hubTileIconUpload = document.getElementById('hubTileIconUpload');

    if (logoUpload) {
        logoUpload.addEventListener('change', function () {
            uploadBrandingFile(this.files[0], 'logo');
        });
    }

    if (faviconUpload) {
        faviconUpload.addEventListener('change', function () {
            uploadBrandingFile(this.files[0], 'favicon');
        });
    }

    if (hubTileIconUpload) {
        hubTileIconUpload.addEventListener('change', function () {
            uploadBrandingFile(this.files[0], 'hub_tile_icon');
        });
    }

    // Color picker synchronization
    setupColorPickers();

    // Auto-adjust header height checkbox
    const headerMatchCheckbox = document.getElementById('headerMatchLogoHeight');
    if (headerMatchCheckbox) {
        headerMatchCheckbox.addEventListener('change', function () {
            if (this.checked) {
                updateHeaderHeightFromLogo();
            }
        });
    }

    // Logo height changes should update header if auto-adjust is on
    const logoHeightInput = document.getElementById('logoHeight');
    if (logoHeightInput) {
        logoHeightInput.addEventListener('input', function () {
            const autoAdjust = document.getElementById('headerMatchLogoHeight');
            if (autoAdjust && autoAdjust.checked) {
                updateHeaderHeightFromLogo();
            }
        });
    }

    // Save settings button
    const saveBtn = document.getElementById('saveSiteSettings');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveSiteSettings);
    }

    // Cancel button - reload settings
    const cancelBtn = document.getElementById('cancelSiteSettings');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', async function () {
            const confirmed = await showConfirmModal(
                'Discard Changes?',
                'All unsaved changes will be lost. Are you sure you want to discard your changes?'
            );

            if (confirmed) {
                hasUnsavedChanges = false;
                updateUnsavedIndicator();
                loadSiteSettings();
                showMessage('Changes discarded', 'info');
            }
        });
    }

    // Reset to defaults button
    const resetBtn = document.getElementById('resetToDefaults');
    if (resetBtn) {
        resetBtn.addEventListener('click', resetToDefaults);
    }

    // Setup live preview for all inputs
    setupLivePreview();
});

/**
 * Load current settings from API
 */
async function loadSiteSettings() {
    try {
        const response = await fetch('/api/site-settings.php');
        if (!response.ok) throw new Error('Failed to load settings');

        const settings = await response.json();

        // Populate form fields
        document.getElementById('siteName').value = settings.site_name || 'The Hub';
        document.getElementById('organizationName').value = settings.organization_name || 'Your Organization';
        document.getElementById('navbarSubtitle').value = settings.navbar_subtitle || 'The Hub';
        document.getElementById('welcomeMessage').value = settings.welcome_message || '';

        // Header settings
        document.getElementById('headerHeight').value = settings.header_height || 80;
        document.getElementById('headerMatchLogoHeight').checked = settings.header_match_logo_height === '1' || settings.header_match_logo_height === true;
        setColorPicker('headerBgColor', settings.header_bg_color || '#000000');
        setColorPicker('headerTextColor', settings.header_text_color || '#FFFFFF');
        setColorPicker('headerSubtitleColor', settings.header_subtitle_color || '#FFD700');
        document.getElementById('headerTitleFont').value = settings.header_title_font || 'Roboto';
        document.getElementById('headerSubtitleFont').value = settings.header_subtitle_font || 'Roboto';
        document.getElementById('headerTitleFontSize').value = settings.header_title_font_size || 1.3;
        document.getElementById('headerSubtitleFontSize').value = settings.header_subtitle_font_size || 0.85;
        document.getElementById('headerShowSubtitle').checked = settings.header_show_subtitle === '1' || settings.header_show_subtitle === true;

        // Footer settings
        document.getElementById('footerHeight').value = settings.footer_height || 40;
        document.getElementById('footerTextSize').value = settings.footer_text_size || 0.875;
        document.getElementById('footerShowVersion').checked = settings.footer_show_version === '1' || settings.footer_show_version === true;
        document.getElementById('footerShowUser').checked = settings.footer_show_user === '1' || settings.footer_show_user === true;
        document.getElementById('footerCustomText').value = settings.footer_custom_text || '';
        setColorPicker('footerBgColor', settings.footer_bg_color || '#F3F4F6');
        setColorPicker('footerTextColor', settings.footer_text_color || '#6B7280');

        // Landing page header
        document.getElementById('landingPageTitle').value = settings.landing_page_title || 'The Hub';
        document.getElementById('landingPageShowIcon').checked = settings.landing_page_show_icon || false;

        // Colors
        setColorPicker('primaryColor', settings.primary_color || '#C99700');
        setColorPicker('navbarColor', settings.navbar_color || '#000000');
        setColorPicker('backgroundColor', settings.background_color || '#FFFFFF');
        setColorPicker('accentColor', settings.accent_color || '#FFD700');

        // Hub colors
        setColorPicker('hubPageBg', settings.hub_page_bg || '#FFFFFF');
        setColorPicker('hubTileBg', settings.hub_tile_bg || '#FFFFFF');
        setColorPicker('hubTileText', settings.hub_tile_text || '#333333');
        setColorPicker('hubTitleColor', settings.hub_title_color || '#000000');
        setColorPicker('hubSubtitleColor', settings.hub_subtitle_color || '#666666');

        // Hub Tiles & Effects
        setColorPicker('hubCardHoverShadow', settings.hub_card_hover_shadow || '#C99700');
        setColorPicker('hubCardHoverBorder', settings.hub_card_hover_border || '#C99700');
        setColorPicker('hubCardHoverTitle', settings.hub_card_hover_title || '#C99700');
        setColorPicker('hubParticleGlow1', settings.hub_particle_glow_1 || '#C99700');
        setColorPicker('hubParticleGlow2', settings.hub_particle_glow_2 || '#FFD700');

        // Particle Effect Controls
        document.getElementById('hubParticleSize').value = settings.hub_particle_size || 600;
        document.getElementById('hubParticleBlur').value = settings.hub_particle_blur || 150;
        document.getElementById('hubParticleOpacity').value = settings.hub_particle_opacity || 0.15;
        document.getElementById('hubParticleSpeed').value = settings.hub_particle_speed || 20;
        // Handle particle enabled as boolean - check for '1', 1, true, or 'true'
        document.getElementById('hubParticleEnabled').checked = settings.hub_particle_enabled === '1' || settings.hub_particle_enabled === 1 || settings.hub_particle_enabled === true || settings.hub_particle_enabled === 'true';

        setColorPicker('hubCardGlowCenter', settings.hub_card_glow_center || '#C99700');
        setColorPicker('hubCardGlowEdge', settings.hub_card_glow_edge || '#000000');
        setColorPicker('hubIconColor', settings.hub_icon_color || '#C99700');
        setColorPicker('hubIconHoverColor', settings.hub_icon_hover_color || '#FFD700');
        setColorPicker('hubIconShadow', settings.hub_icon_shadow || '#C99700');
        setColorPicker('hubCardShadow', settings.hub_card_shadow || '#000000');
        setColorPicker('hubCardBorder', settings.hub_card_border || '#E5E7EB');
        setColorPicker('hubCardDescription', settings.hub_card_description || '#6B7280');
        setColorPicker('hubCardHoverDescription', settings.hub_card_hover_description || '#374151');
        setColorPicker('hubCardHoverDescriptionShadow', settings.hub_card_hover_description_shadow || '#000000');
        setColorPicker('hubNoSectionsBg', settings.hub_no_sections_bg || '#F9FAFB');
        setColorPicker('hubNoSectionsShadow', settings.hub_no_sections_shadow || '#000000');

        // Sidebar colors
        setColorPicker('sidebarBg', settings.sidebar_bg || '#FFFFFF');
        setColorPicker('sidebarTextColor', settings.sidebar_text_color || '#374151');
        setColorPicker('sidebarActiveHighlight', settings.sidebar_active_highlight || '#FEF3C7');
        setColorPicker('sidebarActiveTextColor', settings.sidebar_active_text_color || '#C99700');
        setColorPicker('sidebarHoverBg', settings.sidebar_hover_bg || '#F9FAFB');

        // Button colors
        setColorPicker('buttonPrimaryBg', settings.button_primary_bg || '#C99700');
        setColorPicker('buttonPrimaryText', settings.button_primary_text || '#FFFFFF');
        setColorPicker('buttonSecondaryBg', settings.button_secondary_bg || '#6B7280');
        setColorPicker('buttonSecondaryText', settings.button_secondary_text || '#FFFFFF');
        setColorPicker('buttonDangerBg', settings.button_danger_bg || '#DC2626');
        setColorPicker('buttonDangerText', settings.button_danger_text || '#FFFFFF');
        setColorPicker('buttonSuccessBg', settings.button_success_bg || '#059669');
        setColorPicker('buttonSuccessText', settings.button_success_text || '#FFFFFF');
        setColorPicker('unsavedChangesGlowColor', settings.unsaved_changes_glow_color || '#C99700');

        // Logo
        document.getElementById('logoHeight').value = settings.logo_height || 90;
        document.getElementById('logoHeightMobile').value = settings.logo_height_mobile || 50;
        document.getElementById('logoGlowEnabled').checked = settings.logo_glow_enabled || false;
        setColorPicker('logoGlowColor', settings.logo_glow_color || '#FFD700');

        // Active Menu Styling
        document.getElementById('activeMenuFontSize').value = settings.active_menu_font_size || 16;
        document.getElementById('activeMenuBold').checked = settings.active_menu_bold === '1' || settings.active_menu_bold === true;

        // Update preview images
        if (settings.logo_path) {
            document.getElementById('currentLogoPreview').src = settings.logo_path;
        }
        if (settings.favicon_path) {
            document.getElementById('currentFaviconPreview').src = settings.favicon_path;
        }
        if (settings.hub_tile_icon_path) {
            document.getElementById('currentHubTileIconPreview').src = settings.hub_tile_icon_path;
        }

        // Hub Tile Icon
        document.getElementById('hubTileIconCustomEnabled').checked = settings.hub_tile_icon_custom_enabled === '1';

        // Advanced
        document.getElementById('sessionTimeout').value = settings.session_timeout || 2;
        document.getElementById('maxUploadSize').value = settings.max_upload_size || 10;
        document.getElementById('maintenanceMode').checked = settings.maintenance_mode || false;

        // Apply auto-adjust if enabled
        if (settings.header_match_logo_height === '1' || settings.header_match_logo_height === true) {
            updateHeaderHeightFromLogo();
        }

        // Store original values for change detection
        storeOriginalValues();

        // Reset unsaved changes flag
        hasUnsavedChanges = false;
        updateUnsavedIndicator();

    } catch (error) {
        console.error('Error loading settings:', error);
        showMessage('Failed to load settings', 'error');
    }
}

/**
 * Store current form values as original for change detection
 */
function storeOriginalValues() {
    originalValues = {
        // Text inputs
        siteName: document.getElementById('siteName').value,
        organizationName: document.getElementById('organizationName').value,
        navbarSubtitle: document.getElementById('navbarSubtitle').value,
        welcomeMessage: document.getElementById('welcomeMessage').value,
        footerCustomText: document.getElementById('footerCustomText').value,
        landingPageTitle: document.getElementById('landingPageTitle').value,

        // Number inputs
        headerHeight: document.getElementById('headerHeight').value,
        footerHeight: document.getElementById('footerHeight').value,
        footerTextSize: document.getElementById('footerTextSize').value,
        logoHeight: document.getElementById('logoHeight').value,
        logoHeightMobile: document.getElementById('logoHeightMobile').value,
        activeMenuFontSize: document.getElementById('activeMenuFontSize').value,
        sessionTimeout: document.getElementById('sessionTimeout').value,
        maxUploadSize: document.getElementById('maxUploadSize').value,
        headerTitleFont: document.getElementById('headerTitleFont').value,
        headerSubtitleFont: document.getElementById('headerSubtitleFont').value,
        headerTitleFontSize: document.getElementById('headerTitleFontSize').value,
        headerSubtitleFontSize: document.getElementById('headerSubtitleFontSize').value,

        // Checkboxes
        headerMatchLogoHeight: document.getElementById('headerMatchLogoHeight').checked,
        headerShowSubtitle: document.getElementById('headerShowSubtitle').checked,
        footerShowVersion: document.getElementById('footerShowVersion').checked,
        footerShowUser: document.getElementById('footerShowUser').checked,
        landingPageShowIcon: document.getElementById('landingPageShowIcon').checked,
        logoGlowEnabled: document.getElementById('logoGlowEnabled').checked,
        activeMenuBold: document.getElementById('activeMenuBold').checked,
        hubTileIconCustomEnabled: document.getElementById('hubTileIconCustomEnabled').checked,
        maintenanceMode: document.getElementById('maintenanceMode').checked,

        // Colors
        primaryColor: document.getElementById('primaryColor').value,
        navbarColor: document.getElementById('navbarColor').value,
        backgroundColor: document.getElementById('backgroundColor').value,
        accentColor: document.getElementById('accentColor').value,
        hubPageBg: document.getElementById('hubPageBg').value,
        hubTileBg: document.getElementById('hubTileBg').value,
        hubTileText: document.getElementById('hubTileText').value,
        hubTitleColor: document.getElementById('hubTitleColor').value,
        hubSubtitleColor: document.getElementById('hubSubtitleColor').value,
        hubCardHoverShadow: document.getElementById('hubCardHoverShadow').value,
        hubCardHoverBorder: document.getElementById('hubCardHoverBorder').value,
        hubCardHoverTitle: document.getElementById('hubCardHoverTitle').value,
        hubParticleGlow1: document.getElementById('hubParticleGlow1').value,
        hubParticleGlow2: document.getElementById('hubParticleGlow2').value,
        hubParticleSize: document.getElementById('hubParticleSize').value,
        hubParticleBlur: document.getElementById('hubParticleBlur').value,
        hubParticleOpacity: document.getElementById('hubParticleOpacity').value,
        hubParticleSpeed: document.getElementById('hubParticleSpeed').value,
        hubParticleEnabled: document.getElementById('hubParticleEnabled').checked,
        hubCardGlowCenter: document.getElementById('hubCardGlowCenter').value,
        hubCardGlowEdge: document.getElementById('hubCardGlowEdge').value,
        hubIconColor: document.getElementById('hubIconColor').value,
        hubIconHoverColor: document.getElementById('hubIconHoverColor').value,
        hubIconShadow: document.getElementById('hubIconShadow').value,
        hubCardShadow: document.getElementById('hubCardShadow').value,
        hubCardBorder: document.getElementById('hubCardBorder').value,
        hubCardDescription: document.getElementById('hubCardDescription').value,
        hubCardHoverDescription: document.getElementById('hubCardHoverDescription').value,
        hubCardHoverDescriptionShadow: document.getElementById('hubCardHoverDescriptionShadow').value,
        hubNoSectionsBg: document.getElementById('hubNoSectionsBg').value,
        hubNoSectionsShadow: document.getElementById('hubNoSectionsShadow').value,
        headerBgColor: document.getElementById('headerBgColor').value,
        headerTextColor: document.getElementById('headerTextColor').value,
        headerSubtitleColor: document.getElementById('headerSubtitleColor').value,
        footerBgColor: document.getElementById('footerBgColor').value,
        footerTextColor: document.getElementById('footerTextColor').value,
        sidebarBg: document.getElementById('sidebarBg').value,
        sidebarTextColor: document.getElementById('sidebarTextColor').value,
        sidebarActiveHighlight: document.getElementById('sidebarActiveHighlight').value,
        sidebarActiveTextColor: document.getElementById('sidebarActiveTextColor').value,
        sidebarHoverBg: document.getElementById('sidebarHoverBg').value,
        buttonPrimaryBg: document.getElementById('buttonPrimaryBg').value,
        buttonPrimaryText: document.getElementById('buttonPrimaryText').value,
        buttonSecondaryBg: document.getElementById('buttonSecondaryBg').value,
        buttonSecondaryText: document.getElementById('buttonSecondaryText').value,
        buttonDangerBg: document.getElementById('buttonDangerBg').value,
        buttonDangerText: document.getElementById('buttonDangerText').value,
        buttonSuccessBg: document.getElementById('buttonSuccessBg').value,
        buttonSuccessText: document.getElementById('buttonSuccessText').value,
        unsavedChangesGlowColor: document.getElementById('unsavedChangesGlowColor').value,
        logoGlowColor: document.getElementById('logoGlowColor').value
    };
    debugLog('Original values stored:', originalValues);
}

/**
 * Check if current values differ from original
 */
function hasActualChanges() {
    const checks = [
        // Text inputs
        document.getElementById('siteName').value !== originalValues.siteName,
        document.getElementById('organizationName').value !== originalValues.organizationName,
        document.getElementById('navbarSubtitle').value !== originalValues.navbarSubtitle,
        document.getElementById('welcomeMessage').value !== originalValues.welcomeMessage,
        document.getElementById('footerCustomText').value !== originalValues.footerCustomText,
        document.getElementById('landingPageTitle').value !== originalValues.landingPageTitle,

        // Number inputs
        document.getElementById('headerHeight').value !== originalValues.headerHeight,
        document.getElementById('footerHeight').value !== originalValues.footerHeight,
        document.getElementById('footerTextSize').value !== originalValues.footerTextSize,
        document.getElementById('logoHeight').value !== originalValues.logoHeight,
        document.getElementById('logoHeightMobile').value !== originalValues.logoHeightMobile,
        document.getElementById('activeMenuFontSize').value !== originalValues.activeMenuFontSize,
        document.getElementById('sessionTimeout').value !== originalValues.sessionTimeout,
        document.getElementById('maxUploadSize').value !== originalValues.maxUploadSize,
        document.getElementById('headerTitleFont').value !== originalValues.headerTitleFont,
        document.getElementById('headerSubtitleFont').value !== originalValues.headerSubtitleFont,
        document.getElementById('headerTitleFontSize').value !== originalValues.headerTitleFontSize,
        document.getElementById('headerSubtitleFontSize').value !== originalValues.headerSubtitleFontSize,

        // Checkboxes
        document.getElementById('headerMatchLogoHeight').checked !== originalValues.headerMatchLogoHeight,
        document.getElementById('headerShowSubtitle').checked !== originalValues.headerShowSubtitle,
        document.getElementById('footerShowVersion').checked !== originalValues.footerShowVersion,
        document.getElementById('footerShowUser').checked !== originalValues.footerShowUser,
        document.getElementById('landingPageShowIcon').checked !== originalValues.landingPageShowIcon,
        document.getElementById('logoGlowEnabled').checked !== originalValues.logoGlowEnabled,
        document.getElementById('activeMenuBold').checked !== originalValues.activeMenuBold,
        document.getElementById('hubTileIconCustomEnabled').checked !== originalValues.hubTileIconCustomEnabled,
        document.getElementById('maintenanceMode').checked !== originalValues.maintenanceMode,

        // Colors
        document.getElementById('primaryColor').value !== originalValues.primaryColor,
        document.getElementById('navbarColor').value !== originalValues.navbarColor,
        document.getElementById('backgroundColor').value !== originalValues.backgroundColor,
        document.getElementById('accentColor').value !== originalValues.accentColor,
        document.getElementById('hubPageBg').value !== originalValues.hubPageBg,
        document.getElementById('hubTileBg').value !== originalValues.hubTileBg,
        document.getElementById('hubTileText').value !== originalValues.hubTileText,
        document.getElementById('hubTitleColor').value !== originalValues.hubTitleColor,
        document.getElementById('hubSubtitleColor').value !== originalValues.hubSubtitleColor,
        document.getElementById('headerBgColor').value !== originalValues.headerBgColor,
        document.getElementById('headerTextColor').value !== originalValues.headerTextColor,
        document.getElementById('headerSubtitleColor').value !== originalValues.headerSubtitleColor,
        document.getElementById('footerBgColor').value !== originalValues.footerBgColor,
        document.getElementById('footerTextColor').value !== originalValues.footerTextColor,
        document.getElementById('sidebarBg').value !== originalValues.sidebarBg,
        document.getElementById('sidebarTextColor').value !== originalValues.sidebarTextColor,
        document.getElementById('sidebarActiveHighlight').value !== originalValues.sidebarActiveHighlight,
        document.getElementById('sidebarActiveTextColor').value !== originalValues.sidebarActiveTextColor,
        document.getElementById('sidebarHoverBg').value !== originalValues.sidebarHoverBg,
        document.getElementById('buttonPrimaryBg').value !== originalValues.buttonPrimaryBg,
        document.getElementById('buttonPrimaryText').value !== originalValues.buttonPrimaryText,
        document.getElementById('buttonSecondaryBg').value !== originalValues.buttonSecondaryBg,
        document.getElementById('buttonSecondaryText').value !== originalValues.buttonSecondaryText,
        document.getElementById('buttonDangerBg').value !== originalValues.buttonDangerBg,
        document.getElementById('buttonDangerText').value !== originalValues.buttonDangerText,
        document.getElementById('buttonSuccessBg').value !== originalValues.buttonSuccessBg,
        document.getElementById('buttonSuccessText').value !== originalValues.buttonSuccessText,
        document.getElementById('unsavedChangesGlowColor').value !== originalValues.unsavedChangesGlowColor,
        document.getElementById('logoGlowColor').value !== originalValues.logoGlowColor
    ];

    return checks.some(changed => changed === true);
}

/**
 * Save all settings
 */
/**
 * Save site settings to database
 */
async function saveSiteSettings() {
    const saveBtn = document.getElementById('saveSiteSettings');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    try {
        const settings = {
            site_name: document.getElementById('siteName').value,
            organization_name: document.getElementById('organizationName').value,
            navbar_subtitle: document.getElementById('navbarSubtitle').value,
            welcome_message: document.getElementById('welcomeMessage').value,

            header_height: parseInt(document.getElementById('headerHeight').value),
            header_match_logo_height: document.getElementById('headerMatchLogoHeight').checked,
            header_show_subtitle: document.getElementById('headerShowSubtitle').checked,
            header_bg_color: document.getElementById('headerBgColor').value,
            header_text_color: document.getElementById('headerTextColor').value,
            header_subtitle_color: document.getElementById('headerSubtitleColor').value,
            header_title_font: document.getElementById('headerTitleFont').value,
            header_subtitle_font: document.getElementById('headerSubtitleFont').value,
            header_title_font_size: parseFloat(document.getElementById('headerTitleFontSize').value),
            header_subtitle_font_size: parseFloat(document.getElementById('headerSubtitleFontSize').value),
            footer_height: parseInt(document.getElementById('footerHeight').value),
            footer_text_size: parseFloat(document.getElementById('footerTextSize').value),
            footer_show_version: document.getElementById('footerShowVersion').checked,
            footer_show_user: document.getElementById('footerShowUser').checked,
            footer_custom_text: document.getElementById('footerCustomText').value,
            footer_bg_color: document.getElementById('footerBgColor').value,
            footer_text_color: document.getElementById('footerTextColor').value,

            landing_page_title: document.getElementById('landingPageTitle').value,
            landing_page_show_icon: document.getElementById('landingPageShowIcon').checked,

            primary_color: document.getElementById('primaryColor').value,
            navbar_color: document.getElementById('navbarColor').value,
            background_color: document.getElementById('backgroundColor').value,
            accent_color: document.getElementById('accentColor').value,

            hub_page_bg: document.getElementById('hubPageBg').value,
            hub_tile_bg: document.getElementById('hubTileBg').value,
            hub_tile_text: document.getElementById('hubTileText').value,
            hub_title_color: document.getElementById('hubTitleColor').value,
            hub_subtitle_color: document.getElementById('hubSubtitleColor').value,

            // Hub Tiles & Effects
            hub_card_hover_shadow: document.getElementById('hubCardHoverShadow').value,
            hub_card_hover_border: document.getElementById('hubCardHoverBorder').value,
            hub_card_hover_title: document.getElementById('hubCardHoverTitle').value,
            hub_particle_glow_1: document.getElementById('hubParticleGlow1').value,
            hub_particle_glow_2: document.getElementById('hubParticleGlow2').value,
            hub_particle_size: parseInt(document.getElementById('hubParticleSize').value),
            hub_particle_blur: parseInt(document.getElementById('hubParticleBlur').value),
            hub_particle_opacity: parseFloat(document.getElementById('hubParticleOpacity').value),
            hub_particle_speed: parseInt(document.getElementById('hubParticleSpeed').value),
            hub_particle_enabled: document.getElementById('hubParticleEnabled').checked,
            hub_card_glow_center: document.getElementById('hubCardGlowCenter').value,
            hub_card_glow_edge: document.getElementById('hubCardGlowEdge').value,
            hub_icon_color: document.getElementById('hubIconColor').value,
            hub_icon_hover_color: document.getElementById('hubIconHoverColor').value,
            hub_icon_shadow: document.getElementById('hubIconShadow').value,
            hub_card_shadow: document.getElementById('hubCardShadow').value,
            hub_card_border: document.getElementById('hubCardBorder').value,
            hub_card_description: document.getElementById('hubCardDescription').value,
            hub_card_hover_description: document.getElementById('hubCardHoverDescription').value,
            hub_card_hover_description_shadow: document.getElementById('hubCardHoverDescriptionShadow').value,
            hub_no_sections_bg: document.getElementById('hubNoSectionsBg').value,
            hub_no_sections_shadow: document.getElementById('hubNoSectionsShadow').value,

            sidebar_bg: document.getElementById('sidebarBg').value,
            sidebar_text_color: document.getElementById('sidebarTextColor').value,
            sidebar_active_highlight: document.getElementById('sidebarActiveHighlight').value,
            sidebar_active_text_color: document.getElementById('sidebarActiveTextColor').value,
            sidebar_hover_bg: document.getElementById('sidebarHoverBg').value,

            button_primary_bg: document.getElementById('buttonPrimaryBg').value,
            button_primary_text: document.getElementById('buttonPrimaryText').value,
            button_secondary_bg: document.getElementById('buttonSecondaryBg').value,
            button_secondary_text: document.getElementById('buttonSecondaryText').value,
            button_danger_bg: document.getElementById('buttonDangerBg').value,
            button_danger_text: document.getElementById('buttonDangerText').value,
            button_success_bg: document.getElementById('buttonSuccessBg').value,
            button_success_text: document.getElementById('buttonSuccessText').value,
            unsaved_changes_glow_color: document.getElementById('unsavedChangesGlowColor').value,

            logo_height: parseInt(document.getElementById('logoHeight').value),
            logo_height_mobile: parseInt(document.getElementById('logoHeightMobile').value),
            logo_glow_enabled: document.getElementById('logoGlowEnabled').checked,
            logo_glow_color: document.getElementById('logoGlowColor').value,

            active_menu_font_size: parseInt(document.getElementById('activeMenuFontSize').value),
            active_menu_bold: document.getElementById('activeMenuBold').checked,

            hub_tile_icon_custom_enabled: document.getElementById('hubTileIconCustomEnabled').checked,

            session_timeout: parseInt(document.getElementById('sessionTimeout').value),
            max_upload_size: parseInt(document.getElementById('maxUploadSize').value),
            maintenance_mode: document.getElementById('maintenanceMode').checked
        };

        const response = await fetch('/api/site-settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(settings)
        });

        if (!response.ok) throw new Error('Failed to save settings');

        hasUnsavedChanges = false;
        updateUnsavedIndicator();

        // Check if settings that require a page reload were changed
        const requiresReload = (
            originalValues.footerShowVersion !== settings.footer_show_version ||
            originalValues.footerShowUser !== settings.footer_show_user ||
            originalValues.footerCustomText !== settings.footer_custom_text ||
            originalValues.headerShowSubtitle !== settings.header_show_subtitle ||
            originalValues.navbarSubtitle !== settings.navbar_subtitle ||
            originalValues.organizationName !== settings.organization_name
        );

        if (requiresReload) {
            showMessage('Settings saved! Refreshing to apply changes...', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showMessage('Settings saved successfully!', 'success');
            // Reload settings to sync checkbox states and other values from database
            await loadSiteSettings();
        }

        // Apply changes dynamically without reload
        applyCSSChanges();

    } catch (error) {
        console.error('Error saving settings:', error);
        showMessage('Failed to save settings', 'error');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Changes';
    }
}

/**
 * Apply CSS changes dynamically without page reload
 */
function applyCSSChanges() {
    // Update CSS variables in :root
    const root = document.documentElement;

    root.style.setProperty('--primary-color', document.getElementById('primaryColor').value);
    root.style.setProperty('--navbar-color', document.getElementById('navbarColor').value);
    root.style.setProperty('--background-color', document.getElementById('backgroundColor').value);
    root.style.setProperty('--accent-color', document.getElementById('accentColor').value);

    // Update hub colors
    root.style.setProperty('--hub-page-bg', document.getElementById('hubPageBg').value);
    root.style.setProperty('--hub-tile-bg', document.getElementById('hubTileBg').value);
    root.style.setProperty('--hub-tile-text', document.getElementById('hubTileText').value);
    root.style.setProperty('--hub-title-color', document.getElementById('hubTitleColor').value);
    root.style.setProperty('--hub-subtitle-color', document.getElementById('hubSubtitleColor').value);

    // Update hub particle effect variables
    root.style.setProperty('--hub-particle-glow-1', document.getElementById('hubParticleGlow1').value);
    root.style.setProperty('--hub-particle-glow-2', document.getElementById('hubParticleGlow2').value);
    root.style.setProperty('--hub-particle-size', document.getElementById('hubParticleSize').value + 'px');
    root.style.setProperty('--hub-particle-blur', document.getElementById('hubParticleBlur').value + 'px');
    root.style.setProperty('--hub-particle-opacity', document.getElementById('hubParticleOpacity').value);
    root.style.setProperty('--hub-particle-speed', document.getElementById('hubParticleSpeed').value + 's');
    root.style.setProperty('--hub-particle-display', document.getElementById('hubParticleEnabled').checked ? 'block' : 'none');

    // Update hub card effects
    root.style.setProperty('--hub-card-hover-shadow', document.getElementById('hubCardHoverShadow').value);
    root.style.setProperty('--hub-card-hover-border', document.getElementById('hubCardHoverBorder').value);
    root.style.setProperty('--hub-card-hover-title', document.getElementById('hubCardHoverTitle').value);
    root.style.setProperty('--hub-card-glow-center', document.getElementById('hubCardGlowCenter').value);
    root.style.setProperty('--hub-card-glow-edge', document.getElementById('hubCardGlowEdge').value);
    root.style.setProperty('--hub-icon-color', document.getElementById('hubIconColor').value);
    root.style.setProperty('--hub-icon-hover-color', document.getElementById('hubIconHoverColor').value);
    root.style.setProperty('--hub-icon-shadow', document.getElementById('hubIconShadow').value);
    root.style.setProperty('--hub-card-shadow', document.getElementById('hubCardShadow').value);
    root.style.setProperty('--hub-card-border', document.getElementById('hubCardBorder').value);
    root.style.setProperty('--hub-card-description', document.getElementById('hubCardDescription').value);
    root.style.setProperty('--hub-card-hover-description', document.getElementById('hubCardHoverDescription').value);

    // Update header/footer colors
    root.style.setProperty('--header-bg-color', document.getElementById('headerBgColor').value);
    root.style.setProperty('--header-text-color', document.getElementById('headerTextColor').value);
    root.style.setProperty('--footer-bg-color', document.getElementById('footerBgColor').value);
    root.style.setProperty('--footer-text-color', document.getElementById('footerTextColor').value);

    // Update button colors
    root.style.setProperty('--button-primary-bg', document.getElementById('buttonPrimaryBg').value);
    root.style.setProperty('--button-primary-text', document.getElementById('buttonPrimaryText').value);
    root.style.setProperty('--button-secondary-bg', document.getElementById('buttonSecondaryBg').value);
    root.style.setProperty('--button-secondary-text', document.getElementById('buttonSecondaryText').value);
    root.style.setProperty('--button-danger-bg', document.getElementById('buttonDangerBg').value);
    root.style.setProperty('--button-danger-text', document.getElementById('buttonDangerText').value);
    root.style.setProperty('--button-success-bg', document.getElementById('buttonSuccessBg').value);
    root.style.setProperty('--button-success-text', document.getElementById('buttonSuccessText').value);

    // Update unsaved changes glow color
    root.style.setProperty('--unsaved-changes-glow-color', document.getElementById('unsavedChangesGlowColor').value);

    // Update sidebar colors
    root.style.setProperty('--sidebar-bg', document.getElementById('sidebarBg').value);
    root.style.setProperty('--sidebar-text-color', document.getElementById('sidebarTextColor').value);
    root.style.setProperty('--sidebar-active-highlight', document.getElementById('sidebarActiveHighlight').value);
    root.style.setProperty('--sidebar-active-text-color', document.getElementById('sidebarActiveTextColor').value);
    root.style.setProperty('--sidebar-hover-bg', document.getElementById('sidebarHoverBg').value);

    // Update active menu styling
    const activeMenuFontSize = document.getElementById('activeMenuFontSize');
    const activeMenuBold = document.getElementById('activeMenuBold');
    if (activeMenuFontSize) {
        root.style.setProperty('--active-menu-font-size', activeMenuFontSize.value + 'px');
    }
    if (activeMenuBold) {
        root.style.setProperty('--active-menu-font-weight', activeMenuBold.checked ? 'bold' : 'normal');
    }

    // Update sidebar directly
    const sidebar = document.querySelector('.admin-sidebar');
    if (sidebar) {
        sidebar.style.background = document.getElementById('sidebarBg').value;
    }

    // Update navbar dynamically
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        navbar.style.background = document.getElementById('headerBgColor').value;
        navbar.style.color = document.getElementById('headerTextColor').value;
    }

    const navSubtitle = document.querySelector('.nav-brand-subtitle');
    if (navSubtitle) {
        // Only update color, not content (content is set by PHP - "Admin Dashboard" vs navbar_subtitle)
        navSubtitle.style.color = document.getElementById('headerSubtitleColor').value;
    }

    const navTitle = document.querySelector('.nav-brand-title');
    if (navTitle) {
        // Only update color, not content (content is set by PHP - organization_name)
        navTitle.style.color = document.getElementById('headerTextColor').value;
    }

    // Update Google Fonts dynamically
    const headerTitleFont = document.getElementById('headerTitleFont').value;
    const headerSubtitleFont = document.getElementById('headerSubtitleFont').value;

    // Remove old Google Fonts link if it exists
    const oldFontLink = document.getElementById('google-fonts-dynamic');
    if (oldFontLink) {
        oldFontLink.remove();
    }

    // Create new Google Fonts link
    const fonts = new Set([headerTitleFont, headerSubtitleFont]);
    const fontFamilies = Array.from(fonts).map(font => {
        // Replace all spaces with + for URL encoding
        const encodedFont = font.replace(/\s+/g, '+');
        return `family=${encodedFont}:wght@400;500;600;700`;
    }).join('&');

    const fontLink = document.createElement('link');
    fontLink.id = 'google-fonts-dynamic';
    fontLink.rel = 'stylesheet';
    fontLink.href = `https://fonts.googleapis.com/css2?${fontFamilies}&display=swap`;
    document.head.appendChild(fontLink);

    // Update CSS variables for fonts
    root.style.setProperty('--header-title-font', `"${headerTitleFont}", sans-serif`);
    root.style.setProperty('--header-subtitle-font', `"${headerSubtitleFont}", sans-serif`);
    root.style.setProperty('--header-subtitle-color', document.getElementById('headerSubtitleColor').value);
    root.style.setProperty('--header-title-font-size', document.getElementById('headerTitleFontSize').value + 'rem');
    root.style.setProperty('--header-subtitle-font-size', document.getElementById('headerSubtitleFontSize').value + 'rem');
    root.style.setProperty('--footer-text-size', document.getElementById('footerTextSize').value + 'rem');

    // Apply fonts directly to elements for immediate preview
    if (navTitle) {
        navTitle.style.fontFamily = `"${headerTitleFont}", sans-serif`;
        navTitle.style.fontSize = document.getElementById('headerTitleFontSize').value + 'rem';
    }
    if (navSubtitle) {
        navSubtitle.style.fontFamily = `"${headerSubtitleFont}", sans-serif`;
        navSubtitle.style.fontSize = document.getElementById('headerSubtitleFontSize').value + 'rem';
    }

    // Update navbar links color
    const navLinks = document.querySelectorAll('.navbar a, .navbar .user-menu button');
    navLinks.forEach(link => {
        link.style.color = document.getElementById('headerTextColor').value;
    });

    // Update logo glow effect
    const navBrandImg = document.querySelector('.nav-brand img');
    if (navBrandImg) {
        const glowEnabled = document.getElementById('logoGlowEnabled').checked;
        const glowColor = document.getElementById('logoGlowColor').value;
        if (glowEnabled) {
            // Convert hex to rgba
            const r = parseInt(glowColor.substr(1, 2), 16);
            const g = parseInt(glowColor.substr(3, 2), 16);
            const b = parseInt(glowColor.substr(5, 2), 16);
            navBrandImg.style.filter = `drop-shadow(0 0 8px rgba(${r}, ${g}, ${b}, 0.6))`;
        } else {
            navBrandImg.style.filter = 'none';
        }
    }

    // Update footer
    const footer = document.querySelector('.admin-footer');
    if (footer) {
        footer.style.background = document.getElementById('footerBgColor').value;
        footer.style.color = document.getElementById('footerTextColor').value;
        footer.style.fontSize = document.getElementById('footerTextSize').value + 'rem';
    }

    // Update page background
    document.body.style.backgroundColor = document.getElementById('backgroundColor').value;

    // Update page title
    document.title = document.getElementById('siteName').value + ' - Admin Dashboard';

    // Update all existing buttons to match new colors
    updateButtonColors();
}

/**
 * Update button colors across the page
 */
function updateButtonColors() {
    const primaryBg = document.getElementById('buttonPrimaryBg').value;
    const primaryText = document.getElementById('buttonPrimaryText').value;
    const secondaryBg = document.getElementById('buttonSecondaryBg').value;
    const secondaryText = document.getElementById('buttonSecondaryText').value;
    const dangerBg = document.getElementById('buttonDangerBg').value;
    const dangerText = document.getElementById('buttonDangerText').value;
    const successBg = document.getElementById('buttonSuccessBg').value;
    const successText = document.getElementById('buttonSuccessText').value;

    // Update primary buttons
    document.querySelectorAll('.btn-primary').forEach(btn => {
        btn.style.backgroundColor = primaryBg;
        btn.style.color = primaryText;
    });

    // Update secondary buttons
    document.querySelectorAll('.btn-secondary').forEach(btn => {
        btn.style.backgroundColor = secondaryBg;
        btn.style.color = secondaryText;
    });

    // Update danger buttons
    document.querySelectorAll('.btn-danger').forEach(btn => {
        btn.style.backgroundColor = dangerBg;
        btn.style.color = dangerText;
    });

    // Update success buttons
    document.querySelectorAll('.btn-success').forEach(btn => {
        btn.style.backgroundColor = successBg;
        btn.style.color = successText;
    });
}

/**
 * Upload logo or favicon file
 */
async function uploadBrandingFile(file, type) {
    if (!file) return;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('type', type);

    try {
        const response = await fetch('/api/upload-branding.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) throw new Error('Upload failed');

        const data = await response.json();

        if (type === 'logo') {
            document.getElementById('currentLogoPreview').src = data.path + '?t=' + Date.now();
        } else if (type === 'favicon') {
            document.getElementById('currentFaviconPreview').src = data.path + '?t=' + Date.now();
        } else if (type === 'hub_tile_icon') {
            document.getElementById('currentHubTileIconPreview').src = data.path + '?t=' + Date.now();
            // Auto-enable custom icon checkbox
            document.getElementById('hubTileIconCustomEnabled').checked = true;
        }

        showMessage(data.message, 'success');

    } catch (error) {
        console.error('Upload error:', error);
        showMessage('Failed to upload file', 'error');
    }
}

/**
 * Reset all settings to defaults
 */
async function resetToDefaults() {
    const confirmed = await showConfirmModal(
        'Reset All Settings?',
        'Are you sure you want to reset all settings to their default values? This cannot be undone and the page will reload.'
    );

    if (!confirmed) {
        return;
    }

    try {
        const response = await fetch('/api/site-settings.php', {
            method: 'PUT'
        });

        if (!response.ok) throw new Error('Failed to reset settings');

        hasUnsavedChanges = false;
        updateUnsavedIndicator();
        showMessage('Settings reset to defaults! Reloading in 2 seconds...', 'success');
        setTimeout(() => window.location.reload(), 2000);

    } catch (error) {
        console.error('Error resetting settings:', error);
        showMessage('Failed to reset settings', 'error');
    }
}

/**
 * Synchronize color pickers with hex inputs
 */
function setupColorPickers() {
    const colorInputs = [
        'primaryColor', 'navbarColor', 'backgroundColor', 'accentColor',
        'hubPageBg', 'hubTileBg', 'hubTileText', 'hubTitleColor', 'hubSubtitleColor',
        'hubCardHoverShadow', 'hubCardHoverBorder', 'hubCardHoverTitle',
        'hubParticleGlow1', 'hubParticleGlow2',
        'hubCardGlowCenter', 'hubCardGlowEdge', 'hubIconColor', 'hubIconHoverColor', 'hubIconShadow',
        'hubCardShadow', 'hubCardBorder', 'hubCardDescription', 'hubCardHoverDescription',
        'hubCardHoverDescriptionShadow', 'hubNoSectionsBg', 'hubNoSectionsShadow',
        'textPrimary', 'textSecondary', 'textMuted', 'textDisabled', 'textInverse', 'linkColor',
        'sidebarBg', 'sidebarTextColor', 'sidebarActiveHighlight', 'sidebarActiveTextColor', 'sidebarHoverBg',
        'buttonPrimaryBg', 'buttonPrimaryText', 'buttonSecondaryBg', 'buttonSecondaryText',
        'buttonDangerBg', 'buttonDangerText', 'buttonSuccessBg', 'buttonSuccessText',
        'unsavedChangesGlowColor', 'logoGlowColor',
        'headerBgColor', 'headerTextColor', 'headerSubtitleColor',
        'footerBgColor', 'footerTextColor',
        'roleStaffBg'
    ];

    colorInputs.forEach(id => {
        const colorPicker = document.getElementById(id);
        const hexInput = document.getElementById(id + 'Hex');

        if (colorPicker && hexInput) {
            colorPicker.addEventListener('input', function () {
                hexInput.value = this.value;
                applyCSSChanges();
            });

            hexInput.addEventListener('input', function () {
                if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                    colorPicker.value = this.value;
                    applyCSSChanges();
                }
            });
        }
    });
}

/**
 * Set color picker and hex input values
 */
function setColorPicker(id, value) {
    const colorPicker = document.getElementById(id);
    const hexInput = document.getElementById(id + 'Hex');

    if (colorPicker) colorPicker.value = value;
    if (hexInput) hexInput.value = value;
}

/**
 * Show message to user using toast notification
 */
function showMessage(message, type = 'info') {
    showToast(message, type);
}

/**
 * Show toast notification (matches admin.js pattern)
 */
function showToast(message, type = 'success') {
    // Remove existing toast if any
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    // Force positioning with inline styles to override any CSS conflicts
    toast.style.cssText = `
        position: fixed !important;
        bottom: -100px !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        background: var(--bg-code) !important;
        color: white !important;
        padding: 18px 24px !important;
        border-radius: 8px !important;
        box-shadow: 0 6px 20px rgba(0,0,0,0.4) !important;
        z-index: 100000 !important;
        transition: bottom 0.3s ease !important;
        min-width: 320px !important;
        max-width: 500px !important;
        display: flex !important;
        align-items: center !important;
        gap: 14px !important;
        font-size: 15px !important;
        font-weight: 500 !important;
        border: 2px solid rgba(255,255,255,0.2) !important;
    `;

    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;

    document.body.appendChild(toast);

    // Trigger animation with forced positioning
    setTimeout(() => {
        toast.classList.add('show');
        toast.style.bottom = '2rem !important'; // Force bottom positioning
    }, 10);

    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Live preview functions removed - no longer needed since previews were removed from UI

/**
 * Auto-calculate header height based on logo height + padding
 */
function updateHeaderHeightFromLogo() {
    const logoHeight = document.getElementById('logoHeight');
    const headerHeightInput = document.getElementById('headerHeight');

    if (logoHeight && headerHeightInput) {
        // Logo height + padding (top and bottom 1rem each = 32px total)
        const calculatedHeight = parseInt(logoHeight.value) + 32;
        headerHeightInput.value = Math.max(60, Math.min(150, calculatedHeight));

        // Update CSS variable
        document.documentElement.style.setProperty('--header-height', headerHeightInput.value + 'px');
    }
}

/**
 * Setup live preview for all settings inputs
 * Changes are applied immediately to CSS variables
 */
function setupLivePreview() {
    // Mark as changed when any input changes
    function markChanged() {
        // Check if there are actual changes from original
        const actualChanges = hasActualChanges();
        hasUnsavedChanges = actualChanges;
        updateUnsavedIndicator();
        debugLog('Change detected. Has actual changes:', actualChanges);
    }

    // Header height
    const headerHeight = document.getElementById('headerHeight');
    if (headerHeight) {
        headerHeight.addEventListener('input', function () {
            document.documentElement.style.setProperty('--header-height', this.value + 'px');
            markChanged();
        });
    }

    // Header Title Font Size
    const headerTitleFontSize = document.getElementById('headerTitleFontSize');
    if (headerTitleFontSize) {
        headerTitleFontSize.addEventListener('input', function () {
            applyCSSChanges();
            markChanged();
        });
    }

    // Header Subtitle Font Size
    const headerSubtitleFontSize = document.getElementById('headerSubtitleFontSize');
    if (headerSubtitleFontSize) {
        headerSubtitleFontSize.addEventListener('input', function () {
            applyCSSChanges();
            markChanged();
        });
    }

    // Footer height
    const footerHeight = document.getElementById('footerHeight');
    if (footerHeight) {
        footerHeight.addEventListener('input', function () {
            document.documentElement.style.setProperty('--footer-height', this.value + 'px');
            markChanged();
        });
    }

    // Footer Text Size
    const footerTextSize = document.getElementById('footerTextSize');
    if (footerTextSize) {
        footerTextSize.addEventListener('input', function () {
            applyCSSChanges();
            markChanged();
        });
    }

    // Header Show Subtitle - live preview
    const headerShowSubtitle = document.getElementById('headerShowSubtitle');
    if (headerShowSubtitle) {
        debugLog('Header show subtitle listener attached');
        headerShowSubtitle.addEventListener('change', function () {
            debugLog('Header show subtitle changed:', this.checked);
            const navSubtitle = document.querySelector('.nav-brand-subtitle');
            if (navSubtitle) {
                navSubtitle.style.display = this.checked ? 'block' : 'none';
            }
            markChanged();
        });
    } else {
        console.warn('Header show subtitle element not found');
    }

    // Footer show version - live preview
    const footerShowVersion = document.getElementById('footerShowVersion');
    const footerVersionDisplay = document.getElementById('footerVersionDisplay');
    const footerUserSeparator = document.getElementById('footerUserSeparator');

    if (footerShowVersion && footerVersionDisplay) {
        debugLog('Footer show version listener attached');
        footerShowVersion.addEventListener('change', function () {
            debugLog('Footer show version changed:', this.checked);
            footerVersionDisplay.style.display = this.checked ? 'inline' : 'none';
            // Also hide/show separator if user display is visible
            if (footerUserSeparator) {
                const footerShowUser = document.getElementById('footerShowUser');
                footerUserSeparator.style.display = (this.checked && footerShowUser && footerShowUser.checked) ? 'inline' : 'none';
            }
            markChanged();
        });
    } else {
        console.warn('Footer show version elements not found:', { footerShowVersion, footerVersionDisplay });
    }

    // Footer show user - live preview
    const footerShowUser = document.getElementById('footerShowUser');
    const footerUserDisplay = document.getElementById('footerUserDisplay');

    if (footerShowUser && footerUserDisplay) {
        debugLog('Footer show user listener attached');
        footerShowUser.addEventListener('change', function () {
            debugLog('Footer show user changed:', this.checked);
            footerUserDisplay.style.display = this.checked ? 'inline' : 'none';
            // Also hide/show separator if version display is visible
            if (footerUserSeparator) {
                const footerShowVersion = document.getElementById('footerShowVersion');
                footerUserSeparator.style.display = (this.checked && footerShowVersion && footerShowVersion.checked) ? 'inline' : 'none';
            }
            markChanged();
        });
    } else {
        console.warn('Footer show user elements not found:', { footerShowUser, footerUserDisplay });
    }

    // Footer custom text - live preview
    const footerCustomText = document.getElementById('footerCustomText');
    const footerCustomTextDisplay = document.getElementById('footerCustomTextDisplay');
    const footerCustomSeparator = document.getElementById('footerCustomSeparator');

    if (footerCustomText) {
        debugLog('Footer custom text listener attached');
        footerCustomText.addEventListener('input', function () {
            debugLog('Footer custom text changed:', this.value);
            if (footerCustomTextDisplay) {
                if (this.value.trim()) {
                    footerCustomTextDisplay.textContent = this.value;
                    footerCustomTextDisplay.style.display = 'inline';
                    if (footerCustomSeparator) {
                        footerCustomSeparator.style.display = 'inline';
                    }
                } else {
                    footerCustomTextDisplay.style.display = 'none';
                    if (footerCustomSeparator) {
                        footerCustomSeparator.style.display = 'none';
                    }
                }
            }
            markChanged();
        });
    } else {
        console.warn('Footer custom text element not found');
    }

    // Branding & Images - Organization Name
    const organizationName = document.getElementById('organizationName');
    if (organizationName) {
        debugLog('Organization name listener attached');
        organizationName.addEventListener('input', function () {
            debugLog('Organization name changed:', this.value);
            const navTitle = document.querySelector('.nav-brand-title');
            if (navTitle) {
                navTitle.textContent = this.value;
            }
            const footerOrgName = document.getElementById('footerOrganizationName');
            if (footerOrgName) {
                footerOrgName.textContent = this.value;
            }
            markChanged();
        });
    } else {
        console.warn('Organization name element not found');
    }

    // Header Title Font
    const headerTitleFont = document.getElementById('headerTitleFont');
    if (headerTitleFont) {
        debugLog('Header title font listener attached');
        headerTitleFont.addEventListener('change', function () {
            debugLog('Header title font changed:', this.value);
            applyCSSChanges();
            markChanged();
        });
    } else {
        console.warn('Header title font element not found');
    }

    // Header Subtitle Font
    const headerSubtitleFont = document.getElementById('headerSubtitleFont');
    if (headerSubtitleFont) {
        debugLog('Header subtitle font listener attached');
        headerSubtitleFont.addEventListener('change', function () {
            debugLog('Header subtitle font changed:', this.value);
            applyCSSChanges();
            markChanged();
        });
    } else {
        console.warn('Header subtitle font element not found');
    }

    // Logo Height
    const logoHeight = document.getElementById('logoHeight');
    if (logoHeight) {
        debugLog('Logo height listener attached');
        logoHeight.addEventListener('input', function () {
            debugLog('Logo height changed:', this.value);
            document.documentElement.style.setProperty('--logo-height', this.value + 'px');
            markChanged();
        });
    } else {
        console.warn('Logo height element not found');
    }

    // Logo Height Mobile
    const logoHeightMobile = document.getElementById('logoHeightMobile');
    if (logoHeightMobile) {
        debugLog('Logo height mobile listener attached');
        logoHeightMobile.addEventListener('input', function () {
            debugLog('Logo height mobile changed:', this.value);
            markChanged();
        });
    } else {
        console.warn('Logo height mobile element not found');
    }

    // Logo Glow Effect
    const logoGlowEnabled = document.getElementById('logoGlowEnabled');
    if (logoGlowEnabled) {
        debugLog('Logo glow enabled listener attached');
        logoGlowEnabled.addEventListener('change', function () {
            debugLog('Logo glow enabled changed:', this.checked);
            const navBrandImg = document.querySelector('.nav-brand img');
            const glowColor = document.getElementById('logoGlowColor').value;
            if (navBrandImg) {
                if (this.checked) {
                    // Convert hex to rgba for the glow
                    const r = parseInt(glowColor.substr(1, 2), 16);
                    const g = parseInt(glowColor.substr(3, 2), 16);
                    const b = parseInt(glowColor.substr(5, 2), 16);
                    navBrandImg.style.filter = `drop-shadow(0 0 8px rgba(${r}, ${g}, ${b}, 0.6))`;
                } else {
                    navBrandImg.style.filter = 'none';
                }
            }
            markChanged();
        });
    } else {
        console.warn('Logo glow enabled element not found');
    }

    // Site Name
    const siteName = document.getElementById('siteName');
    if (siteName) {
        debugLog('Site name listener attached');
        siteName.addEventListener('input', function () {
            debugLog('Site name changed:', this.value);
            markChanged();
        });
    } else {
        console.warn('Site name element not found');
    }

    // Navbar Subtitle
    const navbarSubtitle = document.getElementById('navbarSubtitle');
    if (navbarSubtitle) {
        debugLog('Navbar subtitle listener attached');
        navbarSubtitle.addEventListener('input', function () {
            debugLog('Navbar subtitle changed:', this.value);
            markChanged();
        });
    } else {
        console.warn('Navbar subtitle element not found');
    }

    // Welcome Message
    const welcomeMessage = document.getElementById('welcomeMessage');
    if (welcomeMessage) {
        debugLog('Welcome message listener attached');
        welcomeMessage.addEventListener('input', function () {
            debugLog('Welcome message changed:', this.value);
            markChanged();
        });
    } else {
        console.warn('Welcome message element not found');
    }

    // Landing Page Title
    const landingPageTitle = document.getElementById('landingPageTitle');
    if (landingPageTitle) {
        debugLog('Landing page title listener attached');
        landingPageTitle.addEventListener('input', function () {
            debugLog('Landing page title changed:', this.value);
            markChanged();
        });
    } else {
        console.warn('Landing page title element not found');
    }

    // Landing Page Show Icon
    const landingPageShowIcon = document.getElementById('landingPageShowIcon');
    if (landingPageShowIcon) {
        debugLog('Landing page show icon listener attached');
        landingPageShowIcon.addEventListener('change', function () {
            debugLog('Landing page show icon changed:', this.checked);
            markChanged();
        });
    } else {
        console.warn('Landing page show icon element not found');
    }

    // Hub Tile Icon Custom Enabled
    const hubTileIconCustomEnabled = document.getElementById('hubTileIconCustomEnabled');
    if (hubTileIconCustomEnabled) {
        debugLog('Hub tile icon custom enabled listener attached');
        hubTileIconCustomEnabled.addEventListener('change', function () {
            debugLog('Hub tile icon custom enabled changed:', this.checked);
            markChanged();
        });
    } else {
        console.warn('Hub tile icon custom enabled element not found');
    }

    // Active Menu Font Size
    const activeMenuFontSize = document.getElementById('activeMenuFontSize');
    if (activeMenuFontSize) {
        debugLog('Active menu font size listener attached');
        activeMenuFontSize.addEventListener('input', function () {
            debugLog('Active menu font size changed:', this.value);
            document.documentElement.style.setProperty('--active-menu-font-size', this.value + 'px');
            markChanged();
        });
    } else {
        console.warn('Active menu font size element not found');
    }

    // Active Menu Bold
    const activeMenuBold = document.getElementById('activeMenuBold');
    if (activeMenuBold) {
        debugLog('Active menu bold listener attached');
        activeMenuBold.addEventListener('change', function () {
            debugLog('Active menu bold changed:', this.checked);
            const weight = this.checked ? '700' : '400';
            document.documentElement.style.setProperty('--active-menu-font-weight', weight);
            markChanged();
        });
    } else {
        console.warn('Active menu bold element not found');
    }

    // Advanced - Session Timeout
    const sessionTimeout = document.getElementById('sessionTimeout');
    if (sessionTimeout) {
        debugLog('Session timeout listener attached');
        sessionTimeout.addEventListener('input', function () {
            debugLog('Session timeout changed:', this.value);
            markChanged();
        });
    } else {
        console.warn('Session timeout element not found');
    }

    // Advanced - Max Upload Size
    const maxUploadSize = document.getElementById('maxUploadSize');
    if (maxUploadSize) {
        debugLog('Max upload size listener attached');
        maxUploadSize.addEventListener('input', function () {
            debugLog('Max upload size changed:', this.value);
            markChanged();
        });
    } else {
        console.warn('Max upload size element not found');
    }

    // Advanced - Maintenance Mode
    const maintenanceMode = document.getElementById('maintenanceMode');
    if (maintenanceMode) {
        debugLog('Maintenance mode listener attached');
        maintenanceMode.addEventListener('change', function () {
            debugLog('Maintenance mode changed:', this.checked);
            markChanged();
        });
    } else {
        console.warn('Maintenance mode element not found');
    }

    // All color inputs - setup live preview
    const colorInputs = [
        'primaryColor', 'navbarColor', 'backgroundColor', 'accentColor',
        'hubPageBg', 'hubTileBg', 'hubTileText', 'hubTitleColor', 'hubSubtitleColor',
        'headerBgColor', 'headerTextColor', 'headerSubtitleColor', 'footerBgColor', 'footerTextColor',
        'sidebarBg', 'sidebarTextColor', 'sidebarActiveHighlight', 'sidebarActiveTextColor', 'sidebarHoverBg',
        'buttonPrimaryBg', 'buttonPrimaryText', 'buttonSecondaryBg', 'buttonSecondaryText',
        'buttonDangerBg', 'buttonDangerText', 'buttonSuccessBg', 'buttonSuccessText',
        'unsavedChangesGlowColor', 'logoGlowColor'
    ];

    colorInputs.forEach(inputId => {
        const colorInput = document.getElementById(inputId);
        const hexInput = document.getElementById(inputId + 'Hex');

        // Color picker changes -> update hex input
        if (colorInput) {
            colorInput.addEventListener('input', function () {
                if (hexInput) {
                    hexInput.value = this.value;
                }
                applyCSSChanges();
                markChanged();
            });
        }

        // Hex text input changes -> update color picker
        if (hexInput) {
            hexInput.addEventListener('input', function () {
                const value = this.value.trim();
                // Validate hex color format
                if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                    if (colorInput) {
                        colorInput.value = value;
                    }
                    applyCSSChanges();
                    markChanged();
                }
            });

            // Also handle when user pastes or blurs
            hexInput.addEventListener('blur', function () {
                let value = this.value.trim();
                // Add # if missing
                if (value && !value.startsWith('#')) {
                    value = '#' + value;
                }
                // Validate and update
                if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                    this.value = value.toUpperCase();
                    if (colorInput) {
                        colorInput.value = value;
                    }
                    applyCSSChanges();
                    markChanged();
                } else if (colorInput) {
                    // Reset to current color picker value if invalid
                    this.value = colorInput.value;
                }
            });
        }
    });

    // Text inputs and checkboxes
    const allInputs = document.querySelectorAll('#tab-site-settings input, #tab-site-settings textarea, #tab-site-settings select');
    allInputs.forEach(input => {
        if (!input.id || colorInputs.includes(input.id)) return; // Skip already handled

        const eventType = input.type === 'checkbox' ? 'change' : 'input';
        input.addEventListener(eventType, markChanged);
    });
}

/**
 * Show confirmation modal (prettier than browser confirm)
 * @param {string} title - Modal title
 * @param {string} message - Modal message
 * @returns {Promise<boolean>} - True if confirmed, false if cancelled
 */
function showConfirmModal(title, message) {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const confirmBtn = document.getElementById('modalConfirm');
        const cancelBtn = document.getElementById('modalCancel');

        modalTitle.textContent = title;
        modalMessage.textContent = message;

        modal.classList.add('show');

        function cleanup() {
            modal.classList.remove('show');
            confirmBtn.removeEventListener('click', handleConfirm);
            cancelBtn.removeEventListener('click', handleCancel);
            modal.removeEventListener('click', handleOverlayClick);
        }

        function handleConfirm() {
            cleanup();
            resolve(true);
        }

        function handleCancel() {
            cleanup();
            resolve(false);
        }

        function handleOverlayClick(e) {
            if (e.target === modal) {
                handleCancel();
            }
        }

        confirmBtn.addEventListener('click', handleConfirm);
        cancelBtn.addEventListener('click', handleCancel);
        modal.addEventListener('click', handleOverlayClick);
    });
}

/**
 * Update visual indicator for unsaved changes
 */
function updateUnsavedIndicator() {
    const saveBtn = document.getElementById('saveSiteSettings');
    if (saveBtn) {
        if (hasUnsavedChanges) {
            saveBtn.classList.add('has-changes');
        } else {
            saveBtn.classList.remove('has-changes');
        }
    }
}

/**
 * Save Command Center settings
 */
async function saveCommandCenterSettings() {
    const saveBtn = event.target;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

    try {
        const settings = {
            cc_display_name: document.getElementById('cc_display_name').value,
            cc_icon: document.getElementById('cc_icon').value,
            cc_description: document.getElementById('cc_description').value
        };

        const response = await fetch('/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(settings)
        });

        const data = await response.json();

        if (data.success) {
            showMessage('Command Center settings saved successfully!', 'success');
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Saved!';
            setTimeout(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Save Command Center Settings';
            }, 2000);
        } else {
            throw new Error(data.message || 'Failed to save settings');
        }
    } catch (error) {
        console.error('Error saving Command Center settings:', error);
        showMessage('Error: ' + error.message, 'error');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="bi bi-x-circle"></i> Save Failed - Try Again';
    }
}

/**
 * Update icon preview in real-time
 */
function updateIconPreview(iconClass) {
    const preview = document.getElementById('icon-preview');
    if (preview) {
        // Clear existing classes and add new one
        preview.className = '';
        preview.classList.add(iconClass.trim());
    }
}

/**
 * Quick select an icon from the preset buttons
 */
function selectIcon(iconClass) {
    const iconInput = document.getElementById('cc_icon');
    if (iconInput) {
        iconInput.value = iconClass;
        updateIconPreview(iconClass);

        // Add visual feedback
        const button = event.target.closest('button');
        if (button) {
            button.style.transform = 'scale(0.95)';
            setTimeout(() => {
                button.style.transform = 'scale(1)';
            }, 150);
        }

        // Close dropdown after selection
        setTimeout(() => {
            toggleIconDropdown(true);
        }, 300);
    }
}

/**
 * Toggle icon selector dropdown
 */
function toggleIconDropdown(forceClose = false) {
    const dropdown = document.getElementById('icon-selector-dropdown');
    const arrow = document.getElementById('icon-dropdown-arrow');
    const text = document.getElementById('icon-dropdown-text');

    if (!dropdown) return;

    const isVisible = dropdown.style.display !== 'none';

    if (forceClose || isVisible) {
        // Close
        dropdown.style.display = 'none';
        if (arrow) arrow.style.transform = 'rotate(0deg)';
        if (text) text.textContent = 'Browse Icons';
    } else {
        // Open
        dropdown.style.display = 'block';
        if (arrow) arrow.style.transform = 'rotate(180deg)';
        if (text) text.textContent = 'Hide Icons';
    }
}
