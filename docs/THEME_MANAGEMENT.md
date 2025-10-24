# Theme Management System

## Overview

The Woodson Hub now includes a complete theme management system that allows super administrators to save, load, and manage visual themes for the entire site. Themes package all appearance settings (colors, fonts, dimensions) into named configurations that can be quickly switched between.

## Key Features

- **Save Current Settings**: Capture current site appearance as a named theme
- **Load Themes**: Apply saved themes with one click (applies immediately)
- **System Themes**: Pre-installed themes (Woodson ISD Default, Dark Professional, High Contrast)
- **Custom Themes**: Create unlimited custom themes from any combination of settings
- **Export/Import**: Export themes as JSON files to share or backup
- **Update Themes**: Update saved themes with current settings
- **Visual Previews**: Color swatches show primary colors at a glance

## Database Schema

### `themes` Table

```sql
CREATE TABLE themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    settings JSON NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    is_system BOOLEAN DEFAULT FALSE COMMENT 'System themes cannot be deleted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)
```

### Stored Settings

Themes capture these settings from `site_settings` table:

**Colors:**
- `primary_color`, `navbar_color`, `background_color`, `accent_color`
- `header_bg_color`, `header_text_color`, `header_subtitle_color`
- `footer_bg_color`, `footer_text_color`
- `sidebar_bg`, `sidebar_text_color`, `sidebar_active_highlight`, `sidebar_active_text_color`, `sidebar_hover_bg`
- `button_primary_bg`, `button_primary_text`, `button_secondary_bg`, `button_secondary_text`
- `button_danger_bg`, `button_danger_text`, `button_success_bg`, `button_success_text`
- `logo_glow_color`, `unsaved_changes_glow_color`

**Fonts:**
- `header_title_font`, `header_subtitle_font`
- `header_title_font_size`, `header_subtitle_font_size`

**Dimensions:**
- `header_height`, `footer_height`, `footer_text_size`
- `logo_height`, `logo_height_mobile`

**Toggles:**
- `logo_glow_enabled`

## Architecture

### Backend Components

1. **`src/Theme.php`** - Core theme management class
   - `getAll()` - List all themes
   - `get($id)` - Get single theme by ID
   - `getBySlug($slug)` - Get theme by URL-friendly slug
   - `getActive()` - Get currently active theme
   - `saveFromCurrentSettings($name, $description, $createdBy)` - Save current settings as new theme
   - `updateFromCurrentSettings($id, $name, $description)` - Update existing theme with current settings
   - `activate($id)` - Activate theme (applies to site_settings and marks as active)
   - `delete($id)` - Delete non-system, non-active theme
   - `export($id)` - Export theme as JSON
   - `import($themeData, $createdBy)` - Import theme from JSON
   - `filterThemeSettings($allSettings)` - Extract only theme-related settings
   - `generatePreview($settings)` - Generate HTML color preview

2. **`public/api/themes.php`** - REST API endpoint
   - `GET /api/themes.php` - List all themes
   - `GET /api/themes.php?id=N` - Get single theme
   - `GET /api/themes.php?action=active` - Get active theme
   - `GET /api/themes.php?action=export&id=N` - Export theme
   - `POST /api/themes.php` - Save new theme or import
   - `PUT /api/themes.php` - Activate or update theme
   - `DELETE /api/themes.php` - Delete theme

3. **`database/migrations/add_themes_system.sql`** - Database migration
   - Creates `themes` table
   - Inserts 3 system themes (Woodson ISD Default, Dark Professional, High Contrast)
   - Adds `active_theme_id` to `site_settings`

### Frontend Components

1. **`public/admin/index.php`** - UI in "Site Settings → Advanced" subtab
   - Save current settings form (name + description)
   - Themes list with cards showing:
     - Theme name, description, status badges (ACTIVE, SYSTEM)
     - Color preview swatches (5 primary colors)
     - Created date and creator name
     - Action buttons (Load, Update, Export, Delete)

2. **`public/assets/js/admin.js`** - Theme management JavaScript
   - `loadThemes()` - Fetch and render theme cards
   - `activateTheme(id)` - Load theme and reload page
   - `updateTheme(id, name)` - Update theme with current settings
   - `deleteTheme(id, name)` - Delete theme after confirmation
   - `exportTheme(id)` - Download theme as JSON file
   - Auto-loads themes when Advanced subtab is clicked

3. **`public/assets/css/admin-theme.css`** - Theme card styling
   - Responsive grid (1-4 columns based on screen width)
   - Hover effects (lift and shadow)
   - Badge styles for status indicators

## Usage Guide

### For Super Administrators

#### Creating a Custom Theme

1. Navigate to **Admin → Site Settings → Advanced** tab
2. Use the other Site Settings tabs to configure colors, fonts, and dimensions exactly as desired
3. In Advanced tab, enter a name for your theme (e.g., "Spring 2024 Colors")
4. Optionally add a description
5. Click **💾 Save as New Theme**
6. Your theme appears in the Saved Themes list below

#### Loading a Theme

1. Find the desired theme in the Saved Themes list
2. Click **✓ Load Theme** button
3. Confirm the action (current settings will be replaced)
4. Page reloads with new theme applied automatically

#### Updating a Theme

1. Adjust current settings in Site Settings tabs
2. In Advanced tab, find the theme you want to update
3. Click **💾 Update** button
4. Confirm to overwrite the theme with current settings

#### Exporting a Theme

1. Click **⬇️ Export** on any theme card
2. JSON file downloads automatically (e.g., `my_custom_theme.json`)
3. Share with other Woodson Hub instances or keep as backup

#### Importing a Theme

> Note: Import functionality is via API, UI coming in future version

```bash
curl -X POST https://hub.woodsonisd.net/api/themes.php \
  -H "Cookie: PHPSESSID=your_session_id" \
  -F "action=import" \
  -F "theme_json=$(cat theme_file.json)" \
  -F "csrf_token=your_csrf_token"
```

#### Deleting a Theme

1. Click **🗑️ Delete** on a non-active, non-system theme
2. Confirm deletion (cannot be undone)
3. Theme removed from database

**Restrictions:**
- Cannot delete ACTIVE theme (deactivate first by loading another theme)
- Cannot delete SYSTEM themes (Woodson ISD Default, Dark Professional, High Contrast)

### For Developers

#### Testing Themes

```bash
cd /var/www/woodson/thehub
php cli/test-themes.php
```

This displays:
- All themes with IDs and status
- Active theme details
- Current site settings
- Preview generation test

#### Programmatic Theme Activation

```php
use WoodsonISD\Maintenance\Theme;

$theme = new Theme();

// Activate by ID
$theme->activate(2); // Activates Dark Professional theme

// Activate by slug
$darkTheme = $theme->getBySlug('dark-mode');
if ($darkTheme) {
    $theme->activate($darkTheme['id']);
}
```

#### Creating Themes Programmatically

```php
use WoodsonISD\Maintenance\Theme;

$theme = new Theme();

// Save current settings as theme
$newTheme = $theme->saveFromCurrentSettings(
    'Autumn Theme',
    'Warm colors for fall season',
    $userId
);

// Create from settings array
$customTheme = $theme->create(
    'Custom Theme',
    [
        'primary_color' => '#FF5722',
        'navbar_color' => '#212121',
        'background_color' => '#FAFAFA',
        // ... more settings
    ],
    'My custom description',
    $userId
);
```

## Integration Points

### Site Settings Tabs

All settings modified in these tabs are captured when saving themes:

1. **Colors** - Primary, navbar, background, accent, sidebar, buttons
2. **Header & Footer** - Colors, fonts, sizes, dimensions
3. **Branding** - Logo height, glow effects
4. **Advanced** - Theme management interface

### CSS Variables

Themes work through CSS custom properties generated by `SiteSettings::getCSSVariables()`:

```css
:root {
    --primary-color: #C99700;
    --navbar-color: #000000;
    --header-bg-color: #000000;
    /* ... 30+ variables ... */
}
```

These variables are loaded via `/api/theme-css.php` which is linked in all admin pages.

### Audit Logging

All theme operations are logged to `audit_log` table:

- `theme_created` - New theme saved
- `theme_updated` - Theme settings updated
- `theme_activated` - Theme loaded (shows before/after active theme names)
- `theme_deleted` - Theme removed
- `theme_imported` - Theme imported from JSON

View these in **Admin → Activity Logs** tab.

## System Themes

### Woodson ISD Default (ID: 1)
**Status:** Active by default, System protected  
**Colors:** Gold (#C99700) and Black (#000000)  
**Description:** Classic Woodson ISD branding with gold accents and black headers

### Dark Professional (ID: 2)
**Status:** System protected  
**Colors:** Blue (#60A5FA) on Dark Gray (#1F2937)  
**Description:** Dark theme for reduced eye strain in low-light environments

### High Contrast (ID: 3)
**Status:** System protected  
**Colors:** Pure Blue (#0000FF), Black, White, Yellow  
**Description:** Maximum contrast for accessibility compliance (WCAG AAA)

System themes:
- Cannot be deleted
- Can be activated/loaded
- Can be exported
- Serve as starting templates for custom themes

## Migration

To apply the themes system to an existing installation:

```bash
cd /var/www/woodson/thehub
mysql -u WISDAdmin -p woodson_maintenance < database/migrations/add_themes_system.sql
```

This creates the `themes` table and populates with 3 system themes. No data loss occurs—existing `site_settings` remain unchanged.

## Future Enhancements

Potential additions (not yet implemented):

1. **Theme Import UI** - File upload interface in Advanced tab
2. **Theme Preview Modal** - See theme colors before activating
3. **Theme Scheduling** - Auto-switch themes by date (e.g., seasonal themes)
4. **Theme Inheritance** - Create themes based on other themes with overrides
5. **Partial Themes** - Themes that only modify colors or fonts, not everything
6. **Theme Marketplace** - Share themes with other schools
7. **Theme History** - Track when themes were activated (audit log already captures this)
8. **Multi-Site Themes** - Apply same theme across multiple Hub instances

## Troubleshooting

### Theme Not Applying After Activation

**Symptoms:** Clicked "Load Theme" but colors didn't change  
**Causes:**
1. Browser cache - Hard refresh (Ctrl+Shift+R)
2. CDN cache if using one
3. PHP opcode cache - Restart PHP-FPM

**Solution:**
```bash
# Clear PHP opcode cache
sudo systemctl restart php8.0-fpm

# Or restart Apache if using mod_php
sudo systemctl restart apache2
```

### Cannot Delete Theme

**Symptoms:** "Cannot delete the active theme" error  
**Solution:** Activate a different theme first, then delete

**Symptoms:** "Cannot delete system themes" error  
**Solution:** System themes are protected. Export and modify if customization needed.

### Theme Colors Not Matching Preview

**Symptoms:** Color swatches in theme card don't match actual theme  
**Cause:** Theme was manually edited in database without updating JSON  
**Solution:** Load theme, adjust in Site Settings UI, click Update Theme button

### Session/CSRF Errors on Theme Operations

**Symptoms:** "Invalid CSRF token" when saving/loading themes  
**Cause:** Session expired or CSRF token mismatch  
**Solution:**
1. Refresh admin page to get new CSRF token
2. Check `sessions/` directory permissions (should be writable)
3. Verify `session.save_path` in `php.ini`

## Security Considerations

- **Role Restriction:** Only super_admin role can access theme management
- **CSRF Protection:** All POST/PUT/DELETE operations require valid CSRF token
- **Input Validation:** Theme names sanitized, descriptions escaped
- **JSON Validation:** Imported themes validated before insertion
- **SQL Injection Prevention:** All queries use prepared statements
- **Audit Trail:** All operations logged with user ID and timestamps

## Performance Impact

Theme system has minimal performance impact:

- **Theme Load:** Single SELECT query on `themes` table (indexed)
- **Theme Activation:** Transaction with ~35 UPDATE queries to `site_settings` (fast on indexed table)
- **CSS Generation:** Cached via `SiteSettings::getCSSVariables()`, regenerated only when settings change
- **Admin UI:** Themes loaded via AJAX only when Advanced subtab opened

No impact on non-admin pages—they continue using `site_settings` table as before.

## Related Documentation

- [THEME_SYSTEM_REFACTOR.md](THEME_SYSTEM_REFACTOR.md) - CSS refactoring that enabled themes
- [AUDIT_LOGGING.md](AUDIT_LOGGING.md) - How theme operations are logged
- [ROLES_DOCUMENTATION.md](ROLES_DOCUMENTATION.md) - Role permissions for theme access

## Files Modified/Created

**Created:**
- `database/migrations/add_themes_system.sql`
- `src/Theme.php`
- `public/api/themes.php`
- `cli/test-themes.php`
- `docs/THEME_MANAGEMENT.md` (this file)

**Modified:**
- `public/admin/index.php` - Added theme management UI to Advanced subtab
- `public/assets/js/admin.js` - Added theme management JavaScript functions
- `public/assets/css/admin-theme.css` - Added theme card styling

**Dependencies:**
- Requires: `src/Database.php`, `src/SiteSettings.php`, `src/Auth.php`, `src/AuditLogger.php`
- Database: `themes` table, `site_settings` table
- Session: CSRF token via `src/bootstrap.php`

## Changelog

**Version 1.0** (Current)
- Initial theme management system
- Save/load/update/delete themes
- 3 system themes (Woodson ISD Default, Dark Professional, High Contrast)
- Export theme as JSON
- Color preview swatches
- Full audit logging integration
- API and UI complete
