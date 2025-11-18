# Package Theme Integration Guidelines

## Overview
All Hub packages must use **theme-aware CSS** to ensure consistent styling and customization through the Admin Dashboard Site Settings. This document shows how to integrate package styles with the Hub's theming system.

---

## ✅ Core Principles

### 1. **Use CSS Variables, Never Hardcode**
```css
/* ❌ BAD - Hardcoded values */
.my-component {
    color: #2c3e50;
    background: #667eea;
    font-size: 16px;
    padding: 20px;
}

/* ✅ GOOD - Theme variables */
.my-component {
    color: var(--text-primary, #2c3e50);
    background: var(--primary-color, #667eea);
    font-size: var(--font-size-base, 16px);
    padding: var(--spacing-lg, 20px);
}
```

### 2. **Create Package-Specific CSS File**
Place your styles in: `public/assets/css/{package-name}.css`

Example: Management System uses `public/assets/css/management.css`

### 3. **Use Namespace Prefixes**
Prefix all classes to avoid conflicts:
```css
/* Management System uses .mgmt-* prefix */
.mgmt-container { }
.mgmt-section-header { }
.mgmt-submit-btn { }

/* Your package should use .{pkg}-* prefix */
.maintenance-dashboard { }
.maintenance-work-order { }
.maintenance-btn-submit { }
```

### 4. **Add to Production Build**
Update `build-css.sh` to include your CSS:
```bash
echo -e "\n/* ========== YOUR PACKAGE NAME ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/your-package.css" >> "$OUTPUT_FILE"
```

Then run: `bash build-css.sh`

---

## 📋 Available CSS Variables

### Colors
```css
/* Primary Theme Colors */
var(--primary-color)          /* Main brand color (#667eea) */
var(--secondary-color)         /* Accent color */
var(--accent-color)           /* Highlight color */
var(--background-color)       /* Page background */

/* Text Colors */
var(--text-primary)           /* Main text (#2c3e50) */
var(--text-secondary)         /* Secondary text (#7f8c8d) */
var(--text-muted)             /* Muted/disabled text (#95a5a6) */

/* State Colors */
var(--success-color)          /* Success state (#27ae60) */
var(--warning-color)          /* Warning state (#f39c12) */
var(--danger-color)           /* Danger/error state (#e74c3c) */
var(--info-color)             /* Info state (#3498db) */

/* Component Colors */
var(--border-color)           /* Default border color */
var(--hover-bg)               /* Hover background */
var(--card-bg)                /* Card background */
```

### Typography
```css
var(--font-family)            /* Main font family */
var(--font-size-base)         /* Base font size (16px) */
var(--font-size-sm)           /* Small text (14px) */
var(--font-size-lg)           /* Large text (18px) */
var(--font-size-xl)           /* Extra large (24px) */
var(--font-size-xxl)          /* Heading size (32px) */

var(--font-weight-normal)     /* 400 */
var(--font-weight-medium)     /* 500 */
var(--font-weight-semibold)   /* 600 */
var(--font-weight-bold)       /* 700 */
```

### Spacing
```css
var(--spacing-xs)             /* 5px */
var(--spacing-sm)             /* 10px */
var(--spacing-md)             /* 15px */
var(--spacing-lg)             /* 20px */
var(--spacing-xl)             /* 40px */
var(--spacing-xxl)            /* 60px */
```

### Layout
```css
var(--container-max-width)    /* 1600px */
var(--border-radius)          /* 8px */
var(--border-radius-sm)       /* 4px */
var(--border-radius-lg)       /* 12px */
```

### Shadows
```css
var(--shadow-sm)              /* Small shadow */
var(--shadow-md)              /* Medium shadow */
var(--shadow-lg)              /* Large shadow */
```

---

## 🎨 Example: Complete Package CSS

Here's a full example following best practices:

```css
/**
 * ============================================================================
 * YOUR PACKAGE NAME - THEME-AWARE STYLES
 * ============================================================================
 * Brief description of what this package does.
 * 
 * THEME INHERITANCE:
 * - Uses CSS custom properties from site_settings
 * - All colors/fonts/spacing inherit from admin theme
 * - No hardcoded values - fully customizable via Admin Dashboard
 * ============================================================================
 */

/* ============================================================================
   LAYOUT - Container structure
   ============================================================================ */
.pkg-container {
    flex: 1 0 auto;
    max-width: var(--container-max-width, 1400px);
    margin: 0 auto;
    padding: var(--spacing-lg, 20px);
    width: 100%;
}

/* ============================================================================
   HEADER - Page header styling
   ============================================================================ */
.pkg-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-xl, 30px);
    padding: var(--spacing-lg, 20px);
    background: var(--card-bg, white);
    border-radius: var(--border-radius, 8px);
    box-shadow: var(--shadow-sm);
}

.pkg-header h1 {
    margin: 0;
    font-size: var(--font-size-xxl, 28px);
    font-weight: var(--font-weight-semibold, 600);
    color: var(--text-primary, #2c3e50);
}

/* ============================================================================
   CARDS - Content cards
   ============================================================================ */
.pkg-card {
    background: var(--card-bg, white);
    border: 1px solid var(--border-color, #e9ecef);
    border-radius: var(--border-radius, 8px);
    padding: var(--spacing-lg, 20px);
    margin-bottom: var(--spacing-md, 15px);
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.pkg-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* ============================================================================
   BUTTONS - Action buttons
   ============================================================================ */
.pkg-btn {
    padding: var(--spacing-sm, 10px) var(--spacing-lg, 20px);
    background: var(--primary-color, #667eea);
    color: white;
    border: none;
    border-radius: var(--border-radius-sm, 4px);
    font-size: var(--font-size-base, 14px);
    font-weight: var(--font-weight-medium, 500);
    cursor: pointer;
    transition: all 0.2s ease;
}

.pkg-btn:hover {
    background: var(--primary-hover, #5568d3);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.pkg-btn-secondary {
    background: var(--secondary-color, #95a5a6);
}

.pkg-btn-danger {
    background: var(--danger-color, #e74c3c);
}

/* ============================================================================
   TABLES - Data tables
   ============================================================================ */
.pkg-table {
    width: 100%;
    border-collapse: collapse;
    background: var(--card-bg, white);
    border-radius: var(--border-radius, 8px);
    overflow: hidden;
}

.pkg-table thead {
    background: var(--primary-color, #667eea);
    color: white;
}

.pkg-table th {
    padding: var(--spacing-md, 15px);
    text-align: left;
    font-weight: var(--font-weight-semibold, 600);
    font-size: var(--font-size-sm, 14px);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pkg-table td {
    padding: var(--spacing-md, 12px);
    border-bottom: 1px solid var(--border-color, #f1f3f5);
}

.pkg-table tbody tr:hover {
    background: var(--hover-bg, #f8f9fa);
}

/* ============================================================================
   BADGES - Status indicators
   ============================================================================ */
.pkg-badge {
    display: inline-block;
    padding: var(--spacing-xs, 4px) var(--spacing-sm, 12px);
    border-radius: var(--border-radius-lg, 12px);
    font-size: var(--font-size-sm, 11px);
    font-weight: var(--font-weight-semibold, 600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pkg-badge-success {
    background: var(--success-color, #27ae60);
    color: white;
}

.pkg-badge-warning {
    background: var(--warning-color, #f39c12);
    color: white;
}

.pkg-badge-danger {
    background: var(--danger-color, #e74c3c);
    color: white;
}

/* ============================================================================
   RESPONSIVE - Mobile breakpoints
   ============================================================================ */
@media (max-width: 768px) {
    .pkg-container {
        padding: var(--spacing-md, 15px);
    }

    .pkg-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-md, 15px);
    }

    .pkg-table {
        font-size: var(--font-size-sm, 13px);
    }

    .pkg-table th,
    .pkg-table td {
        padding: var(--spacing-sm, 8px);
    }
}

@media (max-width: 480px) {
    .pkg-header h1 {
        font-size: var(--font-size-xl, 22px);
    }

    .pkg-btn {
        width: 100%;
        margin-bottom: var(--spacing-sm, 10px);
    }
}
```

---

## 🔌 Integration Steps

### Step 1: Create CSS File
Create `public/assets/css/your-package.css` with theme variables.

### Step 2: Update PHP Pages
```php
<?php
use Hub\SiteSettings;

// Get custom branding
$displayName = SiteSettings::get('yourpkg_display_name', 'Your Package');
$icon = SiteSettings::get('yourpkg_icon', 'bi-gear');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php Hub\Layout::renderHead($displayName, 'command'); ?>
</head>
<body>
    <?php Hub\Layout::renderHeader($user, $userRole, 'command'); ?>

    <div class="pkg-container">
        <div class="pkg-header">
            <h1><i class="<?= htmlspecialchars($icon) ?>"></i> <?= htmlspecialchars($displayName) ?></h1>
        </div>
        
        <!-- Your content here -->
    </div>

    <?php Hub\Layout::renderFooter($user, 'command'); ?>
</body>
</html>
```

### Step 3: Update build-css.sh
```bash
# Add after admin-colors.css section
echo -e "\n/* ========== YOUR PACKAGE STYLES ========== */\n" >> "$OUTPUT_FILE"
cat "$CSS_DIR/your-package.css" >> "$OUTPUT_FILE"
```

### Step 4: Rebuild Production CSS
```bash
bash build-css.sh
```

### Step 5: Add Settings to Database
```sql
INSERT INTO site_settings (setting_key, setting_value, setting_type, description) VALUES 
('yourpkg_display_name', 'Your Package Name', 'text', 'Display name shown to users'),
('yourpkg_icon', 'bi-gear', 'text', 'Bootstrap icon class'),
('yourpkg_description', 'Package description', 'textarea', 'Help text for users');
```

### Step 6: Add Admin Settings UI
Add a new subtab in `public/admin/index.php`:
```html
<button class="subtab-btn" data-subtab="your-package">Your Package</button>

<!-- In settings container -->
<div id="subtab-your-package" class="user-subtab">
    <div class="form-group">
        <label>Display Name</label>
        <input type="text" id="yourpkg_display_name" 
               value="<?= e(SiteSettings::get('yourpkg_display_name')) ?>" 
               class="form-control">
    </div>
    
    <button onclick="saveYourPackageSettings()">Save Settings</button>
</div>
```

---

## 🚫 Common Mistakes to Avoid

### ❌ Hardcoding Colors
```css
/* WRONG */
.my-button {
    background: #667eea;
    color: #ffffff;
}
```
```css
/* CORRECT */
.my-button {
    background: var(--primary-color, #667eea);
    color: white;
}
```

### ❌ Fixed Pixel Sizes
```css
/* WRONG */
.my-header {
    padding: 20px;
    margin-bottom: 30px;
}
```
```css
/* CORRECT */
.my-header {
    padding: var(--spacing-lg, 20px);
    margin-bottom: var(--spacing-xl, 30px);
}
```

### ❌ Inline Styles in PHP
```php
<!-- WRONG -->
<div style="background: #667eea; padding: 20px;">
```
```php
<!-- CORRECT -->
<div class="pkg-card">
```

### ❌ Global Class Names
```css
/* WRONG - Too generic, will conflict */
.container { }
.header { }
.button { }
```
```css
/* CORRECT - Namespaced */
.pkg-container { }
.pkg-header { }
.pkg-button { }
```

---

## 📚 Real-World Example: Management System

The Management System (formerly Command Center) is a perfect reference implementation. Check these files:

- **CSS**: `public/assets/css/management.css` - 400+ lines, fully theme-aware
- **Index**: `public/command/index.php` - Section selector
- **Section**: `public/command/section.php` - DataTables list
- **Detail**: `public/command/submission.php` - Submission detail view

Key features:
- ✅ All colors use CSS variables
- ✅ Classes prefixed with `.mgmt-*`
- ✅ No inline `<style>` blocks
- ✅ Responsive breakpoints
- ✅ Admin settings integration
- ✅ Included in production.css

---

## 🎯 Testing Checklist

Before releasing your package, verify:

- [ ] No hardcoded colors in CSS (grep for `#[0-9a-f]{3,6}`)
- [ ] All classes use package prefix
- [ ] No inline styles in PHP files
- [ ] CSS added to build-css.sh
- [ ] Production CSS rebuilt successfully
- [ ] Settings added to database
- [ ] Admin UI tab created
- [ ] Theme changes propagate correctly
- [ ] Mobile responsive (test < 768px)
- [ ] Works with dark themes (if applicable)

---

## 🔧 Troubleshooting

### Styles Not Applying
1. Check production CSS was rebuilt: `bash build-css.sh`
2. Verify CSS file exists: `ls public/assets/css/your-package.css`
3. Check CSS loaded in browser DevTools Network tab
4. Clear browser cache (Ctrl+Shift+R)

### Theme Variables Not Working
1. Verify variable name matches: `var(--primary-color)` not `var(--primary)`
2. Check fallback value: `var(--primary-color, #667eea)`
3. Inspect element in DevTools to see computed values
4. Verify `/api/theme-css.php` loads correctly

### Colors Still Hardcoded
1. Search for hex colors: `grep -r "#[0-9a-f]" public/assets/css/your-package.css`
2. Replace with CSS variables
3. Rebuild production CSS

---

## 📞 Support

Questions about theme integration?
- Check existing packages: Management System, Bullying Report
- Review `docs/COMMAND_CENTER_ARCHITECTURE.md`
- Test in development mode first (individual CSS files)
- Use browser DevTools to inspect CSS variable values

---

**Last Updated**: November 18, 2025  
**Compatible With**: The Hub v1.3+
