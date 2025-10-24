<?php

namespace Hub;

use PDO;

/**
 * Theme Management
 * Handles saving, loading, and managing complete theme configurations
 */
class Theme
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all themes
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT t.*, u.name as creator_name
            FROM themes t
            LEFT JOIN users u ON t.created_by = u.id
            ORDER BY t.is_active DESC, t.is_system DESC, t.name ASC
        ");
        $themes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse JSON settings for each theme
        foreach ($themes as &$theme) {
            if (isset($theme['settings'])) {
                $theme['settings'] = json_decode($theme['settings'], true);
            }
        }
        
        return $themes;
    }

    /**
     * Get single theme by ID
     */
    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name as creator_name
            FROM themes t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($theme && isset($theme['settings'])) {
            $theme['settings'] = json_decode($theme['settings'], true);
        }
        
        return $theme ?: null;
    }

    /**
     * Get theme by slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name as creator_name
            FROM themes t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.slug = ?
        ");
        $stmt->execute([$slug]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($theme && isset($theme['settings'])) {
            $theme['settings'] = json_decode($theme['settings'], true);
        }
        
        return $theme ?: null;
    }

    /**
     * Get currently active theme
     */
    public function getActive(): ?array
    {
        $stmt = $this->db->query("
            SELECT t.*, u.name as creator_name
            FROM themes t
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.is_active = TRUE
            LIMIT 1
        ");
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($theme && isset($theme['settings'])) {
            $theme['settings'] = json_decode($theme['settings'], true);
        }
        
        return $theme ?: null;
    }

    /**
     * Save current site settings as a new theme
     */
    public function saveFromCurrentSettings(string $name, ?string $description, int $createdBy): array
    {
        // Create slug from name
        $slug = $this->createSlug($name);
        
        // Gather current site settings that are theme-related
        $settings = new SiteSettings();
        $currentSettings = $settings->getAll();
        
        // Filter to only theme-related settings
        $themeSettings = $this->filterThemeSettings($currentSettings);
        
        // Insert new theme
        $stmt = $this->db->prepare("
            INSERT INTO themes (name, slug, description, settings, is_active, is_system, created_by)
            VALUES (?, ?, ?, ?, FALSE, FALSE, ?)
        ");
        
        $stmt->execute([
            $name,
            $slug,
            $description,
            json_encode($themeSettings),
            $createdBy
        ]);
        
        $themeId = (int)$this->db->lastInsertId();
        
        return [
            'id' => $themeId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'settings' => $themeSettings
        ];
    }

    /**
     * Update an existing theme with current settings
     */
    public function updateFromCurrentSettings(int $id, ?string $name = null, ?string $description = null): bool
    {
        $theme = $this->get($id);
        if (!$theme) {
            throw new \Exception('Theme not found');
        }
        
        if ($theme['is_system']) {
            throw new \Exception('Cannot modify system themes');
        }
        
        // Gather current settings
        $settings = new SiteSettings();
        $currentSettings = $settings->getAll();
        $themeSettings = $this->filterThemeSettings($currentSettings);
        
        // Build update query
        $updates = ['settings = ?'];
        $params = [json_encode($themeSettings)];
        
        if ($name !== null) {
            $updates[] = 'name = ?';
            $params[] = $name;
            $updates[] = 'slug = ?';
            $params[] = $this->createSlug($name);
        }
        
        if ($description !== null) {
            $updates[] = 'description = ?';
            $params[] = $description;
        }
        
        $params[] = $id;
        
        $stmt = $this->db->prepare("
            UPDATE themes 
            SET " . implode(', ', $updates) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($params);
    }

    /**
     * Create a new theme from settings array
     */
    public function create(string $name, array $settings, ?string $description = null, ?int $createdBy = null): array
    {
        $slug = $this->createSlug($name);
        
        $stmt = $this->db->prepare("
            INSERT INTO themes (name, slug, description, settings, is_active, is_system, created_by)
            VALUES (?, ?, ?, ?, FALSE, FALSE, ?)
        ");
        
        $stmt->execute([
            $name,
            $slug,
            $description,
            json_encode($settings),
            $createdBy
        ]);
        
        $themeId = (int)$this->db->lastInsertId();
        
        return [
            'id' => $themeId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'settings' => $settings
        ];
    }

    /**
     * Activate a theme (applies its settings to site_settings)
     */
    public function activate(int $id): bool
    {
        $theme = $this->get($id);
        if (!$theme) {
            throw new \Exception('Theme not found');
        }
        
        $this->db->beginTransaction();
        
        try {
            // Deactivate all themes
            $this->db->exec("UPDATE themes SET is_active = FALSE");
            
            // Activate selected theme
            $stmt = $this->db->prepare("UPDATE themes SET is_active = TRUE WHERE id = ?");
            $stmt->execute([$id]);
            
            // Apply theme settings to site_settings table
            $this->applyThemeSettings($theme['settings']);
            
            // Update active_theme_id
            $stmt = $this->db->prepare("
                UPDATE site_settings 
                SET setting_value = ? 
                WHERE setting_key = 'active_theme_id'
            ");
            $stmt->execute([(string)$id]);
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a theme
     */
    public function delete(int $id): bool
    {
        $theme = $this->get($id);
        if (!$theme) {
            throw new \Exception('Theme not found');
        }
        
        if ($theme['is_system']) {
            throw new \Exception('Cannot delete system themes');
        }
        
        if ($theme['is_active']) {
            throw new \Exception('Cannot delete the active theme');
        }
        
        $stmt = $this->db->prepare("DELETE FROM themes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Export theme as JSON
     */
    public function export(int $id): array
    {
        $theme = $this->get($id);
        if (!$theme) {
            throw new \Exception('Theme not found');
        }
        
        return [
            'name' => $theme['name'],
            'description' => $theme['description'],
            'settings' => $theme['settings'],
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Import theme from JSON
     */
    public function import(array $themeData, int $createdBy): array
    {
        if (!isset($themeData['name']) || !isset($themeData['settings'])) {
            throw new \Exception('Invalid theme data');
        }
        
        // Check if name already exists and append number if needed
        $name = $themeData['name'];
        $originalName = $name;
        $counter = 1;
        
        while ($this->getBySlug($this->createSlug($name))) {
            $counter++;
            $name = "{$originalName} ({$counter})";
        }
        
        return $this->create(
            $name,
            $themeData['settings'],
            $themeData['description'] ?? null,
            $createdBy
        );
    }

    /**
     * Apply theme settings to site_settings table
     */
    private function applyThemeSettings(array $settings): void
    {
        $stmt = $this->db->prepare("
            UPDATE site_settings 
            SET setting_value = ? 
            WHERE setting_key = ?
        ");
        
        foreach ($settings as $key => $value) {
            $stmt->execute([$value, $key]);
        }
    }

    /**
     * Filter settings to only theme-related ones
     */
    private function filterThemeSettings(array $allSettings): array
    {
        // These are the settings that should be included in themes
        $themeKeys = [
            'primary_color',
            'navbar_color',
            'background_color',
            'accent_color',
            'header_bg_color',
            'header_text_color',
            'header_subtitle_color',
            'header_title_font',
            'header_subtitle_font',
            'header_title_font_size',
            'header_subtitle_font_size',
            'header_height',
            'footer_height',
            'footer_bg_color',
            'footer_text_color',
            'footer_text_size',
            'logo_height',
            'logo_height_mobile',
            'logo_glow_enabled',
            'logo_glow_color',
            'sidebar_bg',
            'sidebar_text_color',
            'sidebar_active_highlight',
            'sidebar_active_text_color',
            'sidebar_hover_bg',
            'button_primary_bg',
            'button_primary_text',
            'button_secondary_bg',
            'button_secondary_text',
            'button_danger_bg',
            'button_danger_text',
            'button_success_bg',
            'button_success_text',
            'unsaved_changes_glow_color',
            
            // Role badge colors
            'role_staff_bg',
            'role_staff_text',
            'role_maintenance_bg',
            'role_maintenance_text',
            'role_maintenance_director_bg',
            'role_maintenance_director_text',
            'role_manager_bg',
            'role_manager_text',
            'role_admin_bg',
            'role_admin_text',
            'role_super_admin_bg',
            'role_super_admin_text',
            
            // Additional school district role badge colors
            'role_teacher_bg',
            'role_teacher_text',
            'role_counselor_bg',
            'role_counselor_text',
            'role_principal_bg',
            'role_principal_text',
            'role_superintendent_bg',
            'role_superintendent_text',
            'role_secretary_bg',
            'role_secretary_text',
            'role_librarian_bg',
            'role_librarian_text',
            'role_it_support_bg',
            'role_it_support_text',
            
            // Badge colors
            'badge_success_bg',
            'badge_success_text',
            'badge_system_bg',
            'badge_system_text',
            
            // Text colors
            'text_primary',
            'text_secondary',
            'text_muted',
            'text_disabled',
            'text_inverse',
            'link_color',
            
            // Border colors
            'border_primary',
            'border_secondary',
            'border_focus',
            
            // Input colors
            'input_bg',
            'input_border',
            'input_focus_border',
            'input_disabled_bg',
            
            // Table colors
            'table_header_bg',
            'table_header_text',
            'table_row_hover',
            'table_border',
            
            // Card colors
            'card_bg',
            'card_border',
            'card_shadow',
            
            // Modal colors
            'modal_overlay',
            'modal_bg',
            
            // Tooltip colors
            'tooltip_bg',
            'tooltip_text',
            
            // Status colors
            'success_bg',
            'success_text',
            'success_border',
            'success_button',
            'success_button_hover',
            'danger_bg',
            'danger_text',
            'danger_border',
            'danger_button',
            'danger_button_hover',
            'warning_bg',
            'warning_text',
            'warning_border',
            'warning_button',
            'warning_button_hover',
            'info_bg',
            'info_text',
            'info_border',
            'info_button',
            'info_button_hover',
            
            // Scrollbar colors
            'scrollbar_track',
            'scrollbar_thumb',
            'scrollbar_thumb_hover',
            
            // Shadow opacity
            'shadow_light',
            'shadow_medium',
            'shadow_heavy'
        ];
        
        $filtered = [];
        foreach ($allSettings as $setting) {
            if (in_array($setting['setting_key'], $themeKeys)) {
                $filtered[$setting['setting_key']] = $setting['setting_value'];
            }
        }
        
        return $filtered;
    }

    /**
     * Create URL-safe slug from name
     */
    private function createSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Generate preview HTML for a theme
     */
    public function generatePreview(array $settings): string
    {
        $primary = $settings['primary_color'] ?? '#C99700';
        $navbar = $settings['navbar_color'] ?? '#000000';
        $accent = $settings['accent_color'] ?? '#FFD700';
        $headerBg = $settings['header_bg_color'] ?? '#000000';
        $buttonPrimary = $settings['button_primary_bg'] ?? '#C99700';
        
        return sprintf(
            '<div class="theme-preview" style="display:flex;gap:4px;margin-top:8px;">' .
            '<div style="width:20px;height:20px;background:%s;border-radius:3px;border:1px solid #ddd;"></div>' .
            '<div style="width:20px;height:20px;background:%s;border-radius:3px;border:1px solid #ddd;"></div>' .
            '<div style="width:20px;height:20px;background:%s;border-radius:3px;border:1px solid #ddd;"></div>' .
            '<div style="width:20px;height:20px;background:%s;border-radius:3px;border:1px solid #ddd;"></div>' .
            '<div style="width:20px;height:20px;background:%s;border-radius:3px;border:1px solid #ddd;"></div>' .
            '</div>',
            htmlspecialchars($primary),
            htmlspecialchars($navbar),
            htmlspecialchars($accent),
            htmlspecialchars($headerBg),
            htmlspecialchars($buttonPrimary)
        );
    }
}
