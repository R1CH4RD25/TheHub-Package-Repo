# Hub Color Customization Implementation

## Overview
Added three new color settings to allow granular customization of the Hub landing page appearance, separate from global theme colors.

## New Settings

### Main Theme Colors Section
1. **Hub Page Background** (`hub_page_bg`)
   - Controls the background color of the entire Hub landing page
   - Default: `#FFFFFF`
   - CSS Variable: `--hub-page-bg`
   - Applied to: `.hub-container`

2. **Hub Tile Background** (`hub_tile_bg`)
   - Controls the background color of section tiles/cards on the Hub
   - Default: `#FFFFFF`
   - CSS Variable: `--hub-tile-bg`
   - Applied to: `.section-card`

### Text Colors Section
3. **Hub Tile Text** (`hub_tile_text`)
   - Controls the text color on Hub section tiles
   - Default: `#333333`
   - CSS Variable: `--hub-tile-text`
   - Applied to: `.hub-header`, `h1`, `.section-title`, `.no-sections h3`

## Database Schema
```sql
-- All settings stored in site_settings table
INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES
('hub_page_bg', '#FFFFFF', 'color', 'Hub landing page background color'),
('hub_tile_bg', '#FFFFFF', 'color', 'Hub section tile background color'),
('hub_tile_text', '#333333', 'color', 'Hub section tile text color');
```

## Files Modified

### Backend
1. **Database** (`site_settings` table)
   - Added 3 new rows for hub color settings

2. **`/src/SiteSettings.php`** (line 73)
   - Added CSS variable output in `getCSSVariables()`:
     ```php
     --hub-page-bg: {$settings['hub_page_bg']};
     --hub-tile-bg: {$settings['hub_tile_bg']};
     --hub-tile-text: {$settings['hub_tile_text']};
     ```

### Frontend - Styles
3. **`/public/assets/css/hub.css`**
   - Updated all hub-specific styles to use new CSS variables
   - Replaced hardcoded colors with `var(--hub-page-bg)`, `var(--hub-tile-bg)`, `var(--hub-tile-text)`
   - Fallback values preserved for safety

4. **`/public/assets/css/production.css`**
   - Rebuilt to include updated hub.css (version 1761140274)
   - Contains 7 references to hub CSS variables

### Frontend - Admin UI
5. **`/public/admin/index.php`**
   - **Main Theme Colors section** (badge updated: 4 → 6)
     - Added Hub Page Background color picker (line ~850)
     - Added Hub Tile Background color picker (line ~856)
   
   - **Text Colors section** (badge updated: 6 → 7)
     - Added Hub Tile Text color picker (line ~944)

### Frontend - JavaScript
6. **`/public/assets/js/site-settings.js`**
   - Updated `setupColorPickers()` - added hubPageBg, hubTileBg, hubTileText (line 728)
   - Updated `loadSiteSettings()` - added loading of 3 hub colors (line ~148)
   - Updated `saveSiteSettings()` - added hub colors to save payload (line ~391)
   - Updated `applyCSSChanges()` - added live preview CSS variable updates (line ~479)
   - Updated `storeOriginalValues()` - added hub colors for change detection (line ~261)
   - Updated `hasActualChanges()` - added hub color change checks (line ~329)
   - Updated live preview color list - added 3 hub colors (line 1228)

## User Workflow

### Editing Colors
1. Navigate to **Admin Dashboard** → **Site Settings**
2. Scroll to **Color Scheme** tab
3. Expand **Main Theme Colors** section
   - Find "Hub Page Background" and "Hub Tile Background"
   - Use color picker or enter hex values
4. Expand **Text Colors** section
   - Find "Hub Tile Text Color"
   - Use color picker or enter hex value
5. Changes preview live in the admin panel
6. Click **Save Changes** to persist to database

### How It Works
1. User changes color → JavaScript updates CSS variables inline (live preview)
2. User saves → JavaScript sends all settings to `/api/site-settings.php`
3. API updates `site_settings` table in database
4. `SiteSettings::getCSSVariables()` outputs updated CSS variables
5. `theme-css.php` serves variables to browser
6. If `CSS_PRODUCTION_MODE` enabled, production.css is auto-rebuilt
7. Hub page refreshes with new colors

## CSS Variable Flow
```
Database (site_settings)
  ↓
SiteSettings::getCSSVariables()
  ↓
theme-css.php (served as text/css)
  ↓
Browser (:root CSS variables)
  ↓
hub.css (var(--hub-page-bg), etc.)
  ↓
Rendered Hub page
```

## Testing

### Verify Database
```bash
mysql -u WISDAdmin -p'$DB_PASSWORD' woodson_maintenance -e \
  "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'hub_%'"
```

Expected output:
```
+---------------+---------------+
| setting_key   | setting_value |
+---------------+---------------+
| hub_page_bg   | #FFFFFF       |
| hub_tile_bg   | #FFFFFF       |
| hub_tile_text | #333333       |
+---------------+---------------+
```

### Verify CSS Variables
```bash
cd /var/www/woodson/thehub/public
php -r "require_once '../src/bootstrap.php'; 
        use WoodsonISD\Maintenance\SiteSettings; 
        echo SiteSettings::getCSSVariables();" | grep hub
```

Expected output:
```css
--hub-page-bg: #FFFFFF;
--hub-tile-bg: #FFFFFF;
--hub-tile-text: #333333;
```

### Verify Production CSS
```bash
grep -c "var(--hub" /var/www/woodson/thehub/public/assets/css/production.css
```

Expected: `7` (7 references to hub CSS variables)

## Customization Examples

### Dark Hub Theme
```
Hub Page Background: #1F2937
Hub Tile Background: #374151
Hub Tile Text: #F9FAFB
```

### Gold & Black Theme
```
Hub Page Background: #FFD700
Hub Tile Background: #000000
Hub Tile Text: #FFD700
```

### Soft Blue Theme
```
Hub Page Background: #EFF6FF
Hub Tile Background: #DBEAFE
Hub Tile Text: #1E40AF
```

## Benefits
1. **Separation of Concerns**: Hub colors independent of global theme
2. **Live Preview**: Changes visible immediately without page reload
3. **Database Driven**: All colors persist across sessions
4. **User Friendly**: Simple color pickers in admin panel
5. **Consistent Architecture**: Follows existing site settings pattern
6. **Fallback Values**: CSS variables have safe defaults if database fails

## Future Enhancements
- Add preset color schemes for quick theme switching
- Add hub tile hover color customization
- Add hub tile border color/width settings
- Export/import color schemes as JSON
- Color contrast checker for accessibility
