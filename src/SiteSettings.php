<?php

namespace Hub;

/**
 * Site Settings Manager
 * Loads and manages dynamic site configuration
 */
class SiteSettings
{
    private static $settings = null;
    private static $db = null;

    /**
     * Load all settings from database
     */
    private static function loadSettings()
    {
        if (self::$settings === null) {
            self::$db = Database::getInstance();
            
            $rows = self::$db->fetchAll("
                SELECT setting_key, setting_value, setting_type 
                FROM site_settings
            ");
            
            self::$settings = [];
            foreach ($rows as $row) {
                $value = $row['setting_value'];
                
                // Type conversion
                if ($row['setting_type'] === 'boolean') {
                    $value = (bool)(int)$value;
                } elseif ($row['setting_type'] === 'number') {
                    // Preserve decimals - use floatval instead of int
                    $value = is_numeric($value) ? (float)$value : $value;
                }
                
                self::$settings[$row['setting_key']] = $value;
            }
        }
    }

    /**
     * Get a setting value
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public static function get($key, $default = null)
    {
        self::loadSettings();
        return self::$settings[$key] ?? $default;
    }

    /**
     * Get all settings as array
     * @return array All settings
     */
    public static function getAll()
    {
        self::loadSettings();
        return self::$settings;
    }

    /**
     * Generate CSS variables from settings
     * @return string CSS :root variables
     */
    public static function getCSSVariables()
    {
        self::loadSettings();
        
        $css = ":root {\n";
        $css .= "    --primary-color: " . self::get('primary_color', '#C99700') . ";\n";
        $css .= "    --navbar-color: " . self::get('navbar_color', '#000000') . ";\n";
        $css .= "    --background-color: " . self::get('background_color', '#FFFFFF') . ";\n";
        $css .= "    --accent-color: " . self::get('accent_color', '#FFD700') . ";\n";
        $css .= "    --logo-height: " . self::get('logo_height', '90') . "px;\n";
        $css .= "    --logo-height-mobile: " . self::get('logo_height_mobile', '50') . "px;\n";
        $css .= "    --logo-glow-color: " . self::get('logo_glow_color', '#FFD700') . ";\n";
        
        // Hub page colors
        $css .= "    --hub-page-bg: " . self::get('hub_page_bg', '#FFFFFF') . ";\n";
        $css .= "    --hub-tile-bg: " . self::get('hub_tile_bg', '#FFFFFF') . ";\n";
        $css .= "    --hub-tile-text: " . self::get('hub_tile_text', '#333333') . ";\n";
        $css .= "    --hub-title-color: " . self::get('hub_title_color', '#000000') . ";\n";
        $css .= "    --hub-subtitle-color: " . self::get('hub_subtitle_color', '#666666') . ";\n";
        
        // Header and footer
        $css .= "    --header-height: " . self::get('header_height', '80') . "px;\n";
        $css .= "    --footer-height: " . self::get('footer_height', '40') . "px;\n";
        $css .= "    --header-bg-color: " . self::get('header_bg_color', '#000000') . ";\n";
        $css .= "    --header-text-color: " . self::get('header_text_color', '#FFFFFF') . ";\n";
        $css .= "    --header-subtitle-color: " . self::get('header_subtitle_color', '#FFD700') . ";\n";
        $css .= "    --header-title-font: '" . self::get('header_title_font', 'Roboto') . "', sans-serif;\n";
        $css .= "    --header-subtitle-font: '" . self::get('header_subtitle_font', 'Roboto') . "', sans-serif;\n";
        $css .= "    --header-title-font-size: " . self::get('header_title_font_size', '1.3') . "rem;\n";
        $css .= "    --header-subtitle-font-size: " . self::get('header_subtitle_font_size', '0.85') . "rem;\n";
        $css .= "    --footer-bg-color: " . self::get('footer_bg_color', '#F3F4F6') . ";\n";
        $css .= "    --footer-text-color: " . self::get('footer_text_color', '#6B7280') . ";\n";
        $css .= "    --footer-text-size: " . self::get('footer_text_size', '0.875') . "rem;\n";
        
        // Sidebar colors
        $css .= "    --sidebar-bg: " . self::get('sidebar_bg', '#FFFFFF') . ";\n";
        $css .= "    --sidebar-text-color: " . self::get('sidebar_text_color', '#374151') . ";\n";
        $css .= "    --sidebar-active-highlight: " . self::get('sidebar_active_highlight', '#FEF3C7') . ";\n";
        $css .= "    --sidebar-active-text-color: " . self::get('sidebar_active_text_color', '#C99700') . ";\n";
        $css .= "    --sidebar-hover-bg: " . self::get('sidebar_hover_bg', '#F9FAFB') . ";\n";
        
        // Active menu item styling
        $css .= "    --active-menu-font-size: " . self::get('active_menu_font_size', '16') . "px;\n";
        $fontWeight = self::get('active_menu_bold', false) ? 'bold' : 'normal';
        $css .= "    --active-menu-font-weight: " . $fontWeight . ";\n";
        
        // Button colors
        $css .= "    --btn-primary-bg: " . self::get('button_primary_bg', '#C99700') . ";\n";
        $css .= "    --btn-primary-text: " . self::get('button_primary_text', '#FFFFFF') . ";\n";
        $css .= "    --btn-secondary-bg: " . self::get('button_secondary_bg', '#6B7280') . ";\n";
        $css .= "    --btn-secondary-text: " . self::get('button_secondary_text', '#FFFFFF') . ";\n";
        $css .= "    --btn-danger-bg: " . self::get('button_danger_bg', '#DC2626') . ";\n";
        $css .= "    --btn-danger-text: " . self::get('button_danger_text', '#FFFFFF') . ";\n";
        $css .= "    --btn-success-bg: " . self::get('button_success_bg', '#059669') . ";\n";
        $css .= "    --btn-success-text: " . self::get('button_success_text', '#FFFFFF') . ";\n";
        
        // Unsaved changes indicator
        $css .= "    --unsaved-changes-glow-color: " . self::get('unsaved_changes_glow_color', '#C99700') . ";\n";
        
        // Role badge colors
        $css .= "    --role-staff-bg: " . self::get('role_staff_bg', '#e0e7ff') . ";\n";
        $css .= "    --role-staff-text: " . self::get('role_staff_text', '#3730a3') . ";\n";
        $css .= "    --role-maintenance-bg: " . self::get('role_maintenance_bg', '#fef3c7') . ";\n";
        $css .= "    --role-maintenance-text: " . self::get('role_maintenance_text', '#92400e') . ";\n";
        $css .= "    --role-maintenance-director-bg: " . self::get('role_maintenance_director_bg', '#fed7aa') . ";\n";
        $css .= "    --role-maintenance-director-text: " . self::get('role_maintenance_director_text', '#9a3412') . ";\n";
        $css .= "    --role-manager-bg: " . self::get('role_manager_bg', '#ddd6fe') . ";\n";
        $css .= "    --role-manager-text: " . self::get('role_manager_text', '#5b21b6') . ";\n";
        $css .= "    --role-admin-bg: " . self::get('role_admin_bg', '#fce7f3') . ";\n";
        $css .= "    --role-admin-text: " . self::get('role_admin_text', '#9f1239') . ";\n";
        $css .= "    --role-super-admin-bg: " . self::get('role_super_admin_bg', '#fee2e2') . ";\n";
        $css .= "    --role-super-admin-text: " . self::get('role_super_admin_text', '#991b1b') . ";\n";
        
        // Additional school district role badge colors
        $css .= "    --role-teacher-bg: " . self::get('role_teacher_bg', '#eff6ff') . ";\n";
        $css .= "    --role-teacher-text: " . self::get('role_teacher_text', '#1e40af') . ";\n";
        $css .= "    --role-counselor-bg: " . self::get('role_counselor_bg', '#f5f3ff') . ";\n";
        $css .= "    --role-counselor-text: " . self::get('role_counselor_text', '#6b21a8') . ";\n";
        $css .= "    --role-principal-bg: " . self::get('role_principal_bg', '#dbeafe') . ";\n";
        $css .= "    --role-principal-text: " . self::get('role_principal_text', '#1e3a8a') . ";\n";
        $css .= "    --role-superintendent-bg: " . self::get('role_superintendent_bg', '#1e3a8a') . ";\n";
        $css .= "    --role-superintendent-text: " . self::get('role_superintendent_text', '#ffffff') . ";\n";
        $css .= "    --role-secretary-bg: " . self::get('role_secretary_bg', '#fff7ed') . ";\n";
        $css .= "    --role-secretary-text: " . self::get('role_secretary_text', '#9a3412') . ";\n";
        $css .= "    --role-librarian-bg: " . self::get('role_librarian_bg', '#f0fdf4') . ";\n";
        $css .= "    --role-librarian-text: " . self::get('role_librarian_text', '#166534') . ";\n";
        $css .= "    --role-it-support-bg: " . self::get('role_it_support_bg', '#f3f4f6') . ";\n";
        $css .= "    --role-it-support-text: " . self::get('role_it_support_text', '#374151') . ";\n";
        
        // Badge colors
        $css .= "    --badge-success-bg: " . self::get('badge_success_bg', '#059669') . ";\n";
        $css .= "    --badge-success-text: " . self::get('badge_success_text', '#FFFFFF') . ";\n";
        $css .= "    --badge-system-bg: " . self::get('badge_system_bg', '#6366F1') . ";\n";
        $css .= "    --badge-system-text: " . self::get('badge_system_text', '#FFFFFF') . ";\n";
        
        // Text colors
        $css .= "    --text-primary: " . self::get('text_primary', '#111827') . ";\n";
        $css .= "    --text-secondary: " . self::get('text_secondary', '#374151') . ";\n";
        $css .= "    --text-muted: " . self::get('text_muted', '#6b7280') . ";\n";
        $css .= "    --text-disabled: " . self::get('text_disabled', '#d1d5db') . ";\n";
        $css .= "    --text-inverse: " . self::get('text_inverse', '#FFFFFF') . ";\n";
        $css .= "    --link-color: " . self::get('link_color', '#3B82F6') . ";\n";
        
        // Border colors
        $css .= "    --border-primary: " . self::get('border_primary', '#e5e7eb') . ";\n";
        $css .= "    --border-secondary: " . self::get('border_secondary', '#d1d5db') . ";\n";
        $css .= "    --border-light: " . self::get('border_light', '#f3f4f6') . ";\n";
        $css .= "    --border-focus: " . self::get('border_focus', self::get('primary_color', '#C99700')) . ";\n";
        
        // Background colors
        $css .= "    --bg-secondary: " . self::get('bg_secondary', '#f9fafb') . ";\n";
        $css .= "    --bg-hover: " . self::get('bg_hover', '#f3f4f6') . ";\n";
        $css .= "    --bg-active: " . self::get('bg_active', '#e5e7eb') . ";\n";
        $css .= "    --bg-modal-overlay: " . self::get('bg_modal_overlay', 'rgba(0,0,0,0.5)') . ";\n";
        $css .= "    --bg-code: " . self::get('bg_code', '#1f2937') . ";\n";
        
        // Status colors - Success
        $css .= "    --success-bg: " . self::get('success_bg', '#d4edda') . ";\n";
        $css .= "    --success-text: " . self::get('success_text', '#155724') . ";\n";
        $css .= "    --success-border: " . self::get('success_border', '#c3e6cb') . ";\n";
        $css .= "    --success-button: " . self::get('success_button', '#10b981') . ";\n";
        $css .= "    --success-button-hover: " . self::get('success_button_hover', '#059669') . ";\n";
        
        // Status colors - Danger
        $css .= "    --danger-bg: " . self::get('danger_bg', '#f8d7da') . ";\n";
        $css .= "    --danger-text: " . self::get('danger_text', '#721c24') . ";\n";
        $css .= "    --danger-border: " . self::get('danger_border', '#f5c6cb') . ";\n";
        $css .= "    --danger-button: " . self::get('danger_button', '#ef4444') . ";\n";
        $css .= "    --danger-button-hover: " . self::get('danger_button_hover', '#dc2626') . ";\n";
        
        // Status colors - Warning
        $css .= "    --warning-bg: " . self::get('warning_bg', '#fff3cd') . ";\n";
        $css .= "    --warning-text: " . self::get('warning_text', '#856404') . ";\n";
        $css .= "    --warning-border: " . self::get('warning_border', '#ffeeba') . ";\n";
        $css .= "    --warning-button: " . self::get('warning_button', '#f59e0b') . ";\n";
        $css .= "    --warning-button-hover: " . self::get('warning_button_hover', '#d97706') . ";\n";
        
        // Status colors - Info
        $css .= "    --info-bg: " . self::get('info_bg', '#d1ecf1') . ";\n";
        $css .= "    --info-text: " . self::get('info_text', '#0c5460') . ";\n";
        $css .= "    --info-border: " . self::get('info_border', '#bee5eb') . ";\n";
        $css .= "    --info-button: " . self::get('info_button', '#3B82F6') . ";\n";
        $css .= "    --info-button-hover: " . self::get('info_button_hover', '#2563EB') . ";\n";
        
        // Scrollbar colors
        $css .= "    --scrollbar-track: " . self::get('scrollbar_track', '#f1f1f1') . ";\n";
        $css .= "    --scrollbar-thumb: " . self::get('scrollbar_thumb', '#888888') . ";\n";
        $css .= "    --scrollbar-thumb-hover: " . self::get('scrollbar_thumb_hover', '#555555') . ";\n";
        
        // Shadow opacity values
        $css .= "    --shadow-light: " . self::get('shadow_light', '0.1') . ";\n";
        $css .= "    --shadow-medium: " . self::get('shadow_medium', '0.2') . ";\n";
        $css .= "    --shadow-heavy: " . self::get('shadow_heavy', '0.3') . ";\n";
        $css .= "}\n";
        
        // Button base styles
        $css .= "\n.btn {\n";
        $css .= "    padding: 0.625rem 1.25rem;\n";
        $css .= "    border: none;\n";
        $css .= "    border-radius: 6px;\n";
        $css .= "    font-weight: 600;\n";
        $css .= "    font-size: 0.95rem;\n";
        $css .= "    cursor: pointer;\n";
        $css .= "    transition: all 0.2s ease;\n";
        $css .= "    display: inline-flex;\n";
        $css .= "    align-items: center;\n";
        $css .= "    gap: 0.5rem;\n";
        $css .= "}\n\n";
        
        $css .= ".btn-primary {\n";
        $css .= "    background: var(--btn-primary-bg);\n";
        $css .= "    color: var(--btn-primary-text);\n";
        $css .= "}\n";
        $css .= ".btn-primary:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }\n\n";
        
        $css .= ".btn-secondary {\n";
        $css .= "    background: var(--btn-secondary-bg);\n";
        $css .= "    color: var(--btn-secondary-text);\n";
        $css .= "}\n";
        $css .= ".btn-secondary:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }\n\n";
        
        $css .= ".btn-danger {\n";
        $css .= "    background: var(--btn-danger-bg);\n";
        $css .= "    color: var(--btn-danger-text);\n";
        $css .= "}\n";
        $css .= ".btn-danger:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }\n\n";
        
        $css .= ".btn-success {\n";
        $css .= "    background: var(--btn-success-bg);\n";
        $css .= "    color: var(--btn-success-text);\n";
        $css .= "}\n";
        $css .= ".btn-success:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }\n\n";
        
        // Add logo glow if enabled
        if (self::get('logo_glow_enabled', true)) {
            $css .= ".nav-brand img {\n";
            $css .= "    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.6))\n";
            $css .= "            drop-shadow(0 0 20px rgba(255, 255, 255, 0.4))\n";
            $css .= "            drop-shadow(0 0 30px rgba(255, 255, 255, 0.2));\n";
            $css .= "}\n\n";
            
            $css .= "@media (max-width: 768px) {\n";
            $css .= "    .nav-brand img {\n";
            $css .= "        filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.6))\n";
            $css .= "                drop-shadow(0 0 15px rgba(255, 255, 255, 0.4));\n";
            $css .= "    }\n";
            $css .= "}\n";
        }
        
        return $css;
    }

    /**
     * Get logo glow CSS if enabled
     * @return string CSS filter or empty string
     */
    public static function getLogoGlowCSS()
    {
        if (self::get('logo_glow_enabled', true)) {
            return "filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.6)) 
                    drop-shadow(0 0 20px rgba(255, 255, 255, 0.4))
                    drop-shadow(0 0 30px rgba(255, 255, 255, 0.2));";
        }
        return "";
    }

    /**
     * Get mobile logo glow CSS if enabled
     * @return string CSS filter or empty string
     */
    public static function getMobileLogoGlowCSS()
    {
        if (self::get('logo_glow_enabled', true)) {
            return "filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.6)) 
                    drop-shadow(0 0 15px rgba(255, 255, 255, 0.4));";
        }
        return "";
    }

    /**
     * Check if maintenance mode is enabled
     * @return bool
     */
    public static function isMaintenanceMode()
    {
        return self::get('maintenance_mode', false);
    }

    /**
     * Get Google Fonts link for header fonts
     * @return string Google Fonts HTML link tag
     */
    public static function getGoogleFontsLink()
    {
        $titleFont = self::get('header_title_font', 'Roboto');
        $subtitleFont = self::get('header_subtitle_font', 'Roboto');
        
        // Get unique fonts
        $fonts = array_unique([$titleFont, $subtitleFont]);
        
        // Build Google Fonts URL with proper encoding
        $fontParams = [];
        foreach ($fonts as $font) {
            // Replace all spaces with + for URL encoding
            $encodedFont = str_replace(' ', '+', $font);
            $fontParams[] = 'family=' . $encodedFont . ':wght@400;500;600;700';
        }
        
        if (empty($fontParams)) {
            return '';
        }
        
        return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n" .
               '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n" .
               '<link href="https://fonts.googleapis.com/css2?' . implode('&', $fontParams) . '&display=swap" rel="stylesheet">';
    }

    /**
     * Get session timeout in seconds
     * @return int
     */
    public static function getSessionTimeout()
    {
        $hours = self::get('session_timeout', 2);
        return $hours * 3600; // Convert to seconds
    }
}
