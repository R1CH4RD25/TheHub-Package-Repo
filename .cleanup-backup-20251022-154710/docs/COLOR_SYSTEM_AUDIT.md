# Color System Audit & Migration Plan
## Date: 2025-10-22

## Current State Analysis

### Hardcoded Color Categories Found:

#### 1. TEXT COLORS
- `#111827` - Primary text (dark gray-black)
- `#374151` - Secondary text (medium gray)
- `#6b7280` - Tertiary/muted text (light gray)
- `#666` - Muted text
- `#212529` - Default text color
- `#d1d5db` - Very light text (disabled state)

#### 2. BORDER COLORS
- `#e5e7eb` - Primary borders (light gray)
- `#d1d5db` - Secondary borders (medium-light gray)
- `#ddd` - Generic borders
- `#dee2e6` - Light borders
- Gold borders: `rgba(201, 151, 0, 0.3)`, `rgba(201, 151, 0, 0.5)` - Theme-specific

#### 3. BACKGROUND COLORS
- `#f9fafb` - Ultra light background (hover states)
- `#f3f4f6` - Light background (panels)
- `#f8f9fa` - Light background variant
- `#f1f1f1` - Light gray background
- Modals: `rgba(0,0,0,0.5)` - Dark overlay
- `rgba(0,0,0,0.05)` - Light overlay

#### 4. STATUS COLORS
- Success: `#10b981`, `#28a745`, `#059669`, `#d4edda` (bg), `#155724` (text), `#c3e6cb` (border)
- Danger: `#ef4444`, `#dc2626`, `#c82333`, `#f8d7da` (bg), `#721c24` (text), `#f5c6cb` (border)
- Warning: `#f59e0b`, `#d97706`, `#ffc107`
- Info: Blue tones

#### 5. BUTTON SPECIFIC
- Warning hover: `#d97706`
- Success hover: `#059669`
- Danger hover: `#dc2626`, `#c82333`
- Gradients: `linear-gradient(135deg, #10b981 0%, #059669 100%)`
- Gradients: `linear-gradient(135deg, #ef4444 0%, #dc2626 100%)`
- Gradients: `linear-gradient(135deg, #f59e0b 0%, #d97706 100%)`

#### 6. SPECIAL ELEMENTS
- Scrollbar track: `#f1f1f1`
- Scrollbar thumb: `#888`
- Scrollbar hover: `#555`
- Code/pre background: `#333`
- Modal backdrop: `rgba(0,0,0,0.5)`

#### 7. FOCUS/ACTIVE STATES
- Gold focus ring: `rgba(201, 151, 0, 0.3)`
- Gold box-shadow: `rgba(201, 151, 0, 0.4)`, `rgba(201, 151, 0, 0.6)`
- Blue focus: `rgba(0, 102, 204, 0.1)`

#### 8. SHADOWS
- `rgba(0,0,0,0.1)` - Light shadows
- `rgba(0,0,0,0.2)` - Medium shadows
- `rgba(0,0,0,0.3)` - Heavy shadows
- `rgba(0,0,0,0.4)` - Very heavy shadows
- `rgba(0,0,0,0.5)` - Ultra heavy shadows

---

## Proposed Variable System (60 total settings)

### MAIN COLORS (already exist - 4)
- primary_color
- navbar_color
- background_color
- accent_color

### TEXT COLORS (6 new)
- text_primary
- text_secondary
- text_muted
- text_disabled
- text_inverse (for dark backgrounds)
- link_color

### BORDER COLORS (4 new)
- border_primary
- border_secondary
- border_light
- border_focus

### BACKGROUND COLORS (6 new)
- bg_primary (main page background - already as background_color)
- bg_secondary (panels, cards)
- bg_hover (hover states)
- bg_active (active/selected states)
- bg_modal_overlay
- bg_code (code blocks)

### STATUS COLORS - Success (3 new)
- success_bg
- success_text
- success_border

### STATUS COLORS - Danger (3 new)
- danger_bg
- danger_text
- danger_border

### STATUS COLORS - Warning (3 new)
- warning_bg
- warning_text
- warning_border

### STATUS COLORS - Info (3 new)
- info_bg
- info_text
- info_border

### SCROLLBAR COLORS (3 new)
- scrollbar_track
- scrollbar_thumb
- scrollbar_thumb_hover

### SHADOW COLORS (5 new - just alpha values, use black)
- shadow_light (0.1)
- shadow_medium (0.2)
- shadow_heavy (0.3)
- shadow_xheavy (0.4)
- shadow_ultra (0.5)

### DARK MODE (1 new boolean)
- dark_mode_enabled (0 or 1)

### ROLE BADGES (12 already added)
- role_staff_bg/text
- role_maintenance_bg/text
- role_maintenance_director_bg/text
- role_manager_bg/text
- role_admin_bg/text
- role_super_admin_bg/text

---

## Total: ~60 customizable color settings

## Implementation Strategy:

1. Add all settings to theme migration
2. Update SiteSettings::getCSSVariables() to output all vars
3. Systematically replace hardcoded colors in:
   - style.css
   - admin.css
   - admin-theme.css
4. Add dark mode logic (when enabled, auto-invert backgrounds/text)
5. Create organized UI with collapsible sections
6. Test across all themes

## Dark Mode Behavior:
When dark_mode_enabled = 1:
- Swap background_color with navbar_color
- Invert text colors
- Adjust borders to be lighter
- Keep primary/accent colors but adjust brightness
- Shadows become lighter (higher opacity)
