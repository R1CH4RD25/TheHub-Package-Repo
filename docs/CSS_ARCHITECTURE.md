# CSS Architecture Guide

## Overview
The Hub uses a **modular, context-specific CSS architecture** with production minification for optimal performance.

## Directory Structure

```
public/assets/css/
├── shared/                          # Shared across all contexts
│   ├── enterprise-design-system.css # Design tokens (colors, typography, spacing)
│   ├── enterprise-components.css    # Reusable UI components (tables, cards, buttons)
│   ├── enterprise-footer.css        # Enterprise footer component styles
│   ├── header.css                   # Hub navigation header
│   ├── footer.css                   # Hub footer
│   └── modals.css                   # Modal/dialog styles
│
├── admin/                           # Admin Dashboard specific
│   ├── admin.css                    # Admin console core styles
│   ├── admin-modern.css             # Modern admin enhancements
│   ├── admin-theme.css              # Admin theme system
│   └── admin-colors.css             # Color management UI
│
├── mgmt/                            # Management Console specific
│   ├── management.css               # Management console styles
│   └── dynamic-sections.css         # Dynamic form sections
│
├── hub/                             # PWA Frontend (students/staff)
│   ├── hub.css                      # Hub landing page
│   ├── hub-modern.css               # Modern hub with animations
│   ├── sections.css                 # Section selection page
│   ├── modules.css                  # Module selection page
│   └── modules-modern.css           # Modern module animations
│
├── admin-bundle.css                 # Admin bundle config (imports)
├── admin-bundle.min.css             # Minified admin bundle
├── mgmt-bundle.css                  # Management bundle config (imports)
├── mgmt-bundle.min.css              # Minified management bundle
├── hub-bundle.css                   # Hub bundle config (imports)
├── hub-bundle.min.css               # Minified hub bundle
├── css-version.json                 # Cache-busting version manifest
│
├── style.css                        # Base hub styles (legacy)
├── login.css                        # Login page
└── media.css                        # Mobile responsive styles
```

## Bundle System

### How It Works
Each context (Admin, Management, Hub) has its own **bundle configuration file** that imports only the CSS needed for that context.

### Bundle Configurations

#### 1. Admin Bundle (`admin-bundle.css`)
```css
/* Foundation */
@import url('shared/enterprise-design-system.css');

/* Components */
@import url('shared/enterprise-components.css');

/* Admin-specific */
@import url('admin/admin.css');
@import url('admin/admin-modern.css');
@import url('admin/admin-theme.css');
@import url('admin/admin-colors.css');

/* Shared utilities */
@import url('shared/modals.css');
```

**Used by**: `/admin/index.php`  
**Scope**: `.admin-root` class on body  
**Design**: Microsoft 365 / Google Admin Console inspired

#### 2. Management Bundle (`mgmt-bundle.css`)
```css
/* Foundation */
@import url('shared/enterprise-design-system.css');

/* Components */
@import url('shared/enterprise-components.css');
@import url('shared/enterprise-footer.css');

/* Management-specific */
@import url('mgmt/management.css');
@import url('mgmt/dynamic-sections.css');

/* Shared utilities */
@import url('shared/modals.css');
```

**Used by**: `/management/index.php`  
**Scope**: `.mgmt-root` class on body  
**Design**: Theme-aware workflow dashboard

#### 3. Hub Bundle (`hub-bundle.css`)
```css
/* Base styles */
@import url('style.css');

/* Layout components */
@import url('shared/header.css');
@import url('shared/footer.css');

/* Page-specific */
@import url('login.css');
@import url('hub/hub.css');
@import url('hub/sections.css');
@import url('hub/modules.css');

/* Modals */
@import url('shared/modals.css');

/* Responsive/Mobile */
@import url('media.css');
```

**Used by**: `/hub.php`, `/sections.php`, `/modules.php`  
**Scope**: `.hub-root` class on body  
**Design**: Mobile-first, friendly PWA interface

## Production Build System

### Build Script
```bash
./build-css-production.sh
```

### What It Does
1. **Reads** each bundle configuration file
2. **Minifies** using CSSO (CSS optimizer)
3. **Generates** `.min.css` files with 70-90% compression
4. **Creates** `css-version.json` with cache-busting version
5. **Outputs** compression stats and new version number

### Output
```
admin-bundle.min.css    99 bytes   (-91.5%)
mgmt-bundle.min.css     146 bytes  (-79.3%)
hub-bundle.min.css      231 bytes  (-68.5%)
```

### Cache-Busting
```json
{
  "version": "1763587429",
  "timestamp": "2025-11-19T21:30:29+00:00",
  "bundles": {
    "admin": "admin-bundle.min.css",
    "mgmt": "mgmt-bundle.min.css",
    "hub": "hub-bundle.min.css"
  }
}
```

## Loading CSS in Pages

### Development Mode (CSS_PRODUCTION_MODE = false)
Loads individual CSS files for easier debugging:
```php
// src/bootstrap.php
define('CSS_PRODUCTION_MODE', false);

// Individual files loaded via @import at runtime
```

### Production Mode (CSS_PRODUCTION_MODE = true)
Loads minified bundles with cache-busting:
```php
// public/admin/index.php
<link rel="stylesheet" 
      href="/assets/css/admin-bundle.min.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-bundle.min.css') ?>">
```

### Automatic Mode Switching
```php
// src/bootstrap.php
$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
$debugMode = ($_ENV['DEBUG_MODE'] ?? 'false') === 'true';

define('CSS_PRODUCTION_MODE', $isProduction && !$debugMode);
```

## Shared vs. Context-Specific CSS

### Shared CSS (`shared/`)
**When to use**: Styles needed by multiple contexts (Admin + Management + Hub)

**Examples**:
- `enterprise-design-system.css` - CSS variables, design tokens
- `enterprise-components.css` - Tables, cards, buttons, forms
- `modals.css` - Modal dialogs used everywhere
- `header.css` / `footer.css` - Hub navigation components

### Context-Specific CSS (`admin/`, `mgmt/`, `hub/`)
**When to use**: Styles only needed in one specific context

**Examples**:
- `admin/admin-colors.css` - Admin-only color management UI
- `mgmt/dynamic-sections.css` - Management-only form sections
- `hub/hub-modern.css` - Hub-only animations and effects

## CSS Scoping

### Why Scoping?
Prevents style conflicts when multiple contexts exist (e.g., admin testing hub features)

### How It Works
```html
<!-- Admin Dashboard -->
<body class="admin-root">
  <!-- All admin styles scoped to .admin-root -->
</body>

<!-- Management Console -->
<body class="mgmt-root">
  <!-- All mgmt styles scoped to .mgmt-root -->
</body>

<!-- Hub Frontend -->
<body class="hub-root">
  <!-- All hub styles scoped to .hub-root -->
</body>
```

### Example
```css
/* admin-colors.css - All selectors scoped */
.admin-root .color-section { ... }
.admin-root .color-grid { ... }
.admin-root .color-input-group { ... }
```

## Design System Foundation

### Enterprise Design System (`shared/enterprise-design-system.css`)
Contains CSS custom properties (variables) used across all contexts:

```css
:root {
  /* Colors */
  --primary-color: #C99700;
  --secondary-color: #000000;
  --gray-50: #f9fafb;
  --gray-100: #f3f4f6;
  --gray-300: #d1d5db;
  --gray-500: #6b7280;
  
  /* Typography */
  --font-sans: system-ui, -apple-system, sans-serif;
  --font-mono: 'Courier New', monospace;
  --font-size-sm: 0.875rem;
  --font-size-base: 1rem;
  --font-size-lg: 1.125rem;
  
  /* Spacing */
  --space-1: 0.25rem;
  --space-2: 0.5rem;
  --space-3: 0.75rem;
  --space-4: 1rem;
  --space-6: 1.5rem;
  --space-8: 2rem;
  
  /* Shadows */
  --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
  --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
}
```

## Component Reusability

### Enterprise Components (`shared/enterprise-components.css`)
Reusable UI components with consistent styling:

- **Tables**: `.enterprise-table`, `.data-table`
- **Cards**: `.card`, `.metric-card`
- **Buttons**: `.btn`, `.btn-primary`, `.btn-secondary`
- **Forms**: `.form-group`, `.settings-grid`
- **Navigation**: `.nav-user-dropdown`, `.user-menu-item`
- **Layout**: `.tab-content-scroll`, `.user-subtab`

### Usage Example
```html
<!-- Works in Admin, Management, or Hub -->
<div class="card">
  <div class="card-header">
    <h3>Title</h3>
  </div>
  <div class="card-body">
    <button class="btn btn-primary">Action</button>
  </div>
</div>
```

## Adding New CSS

### For Shared Components
1. Add CSS to appropriate `shared/` file
2. Rebuild bundles: `./build-css-production.sh`
3. Commit both source and minified files

### For Context-Specific Styles
1. Add CSS to appropriate context folder (`admin/`, `mgmt/`, `hub/`)
2. Ensure bundle config imports the file
3. Rebuild bundles: `./build-css-production.sh`
4. Commit all changes

### Creating a New Bundle
1. Create bundle config: `new-context-bundle.css`
2. Add @import statements for needed CSS
3. Update `build-css-production.sh` to build new bundle
4. Add to PHP pages with cache-busting

## Best Practices

### ✅ DO
- Use CSS variables from `enterprise-design-system.css`
- Scope context-specific styles to `.admin-root`, `.mgmt-root`, `.hub-root`
- Rebuild production bundles after CSS changes
- Use semantic class names (`.user-profile`, not `.up`)
- Keep shared components in `shared/` folder
- Test in all contexts where CSS is used

### ❌ DON'T
- Use inline styles (except for dynamic values)
- Mix context-specific styles in shared files
- Forget to rebuild after CSS changes
- Use `!important` unless absolutely necessary
- Create duplicate styles across contexts
- Hard-code colors (use CSS variables)

## Troubleshooting

### CSS Changes Not Showing
1. Rebuild bundles: `./build-css-production.sh`
2. Hard refresh browser: `Ctrl+Shift+R` (clears cache)
3. Check `css-version.json` updated
4. Verify `filemtime()` in PHP includes correct path

### Styles Conflicting
1. Check if styles are properly scoped (`.admin-root`, etc.)
2. Verify bundle imports correct files
3. Check CSS specificity (more specific selector wins)
4. Use browser DevTools to see which styles are applied

### Bundle Not Loading
1. Check @import paths in bundle config (must be relative)
2. Verify all imported files exist
3. Check browser network tab for 404 errors
4. Ensure bundle path in PHP is correct

### Build Script Fails
1. Verify CSSO is installed: `which csso`
2. Check file permissions: `chmod +x build-css-production.sh`
3. Verify CSS syntax in source files (invalid CSS breaks build)
4. Check disk space for writing minified files

## Performance

### Compression Results
- **Admin Bundle**: 91.5% smaller (99 bytes)
- **Management Bundle**: 79.3% smaller (146 bytes)
- **Hub Bundle**: 68.5% smaller (231 bytes)

### Why So Small?
Bundle files only contain `@import` statements. Actual CSS is loaded at runtime by the browser, which:
- ✅ Allows browser caching of individual files
- ✅ Enables partial updates (change one file, others stay cached)
- ✅ Provides flexibility between dev and production modes

### Future Enhancement Opportunity
For true inlined bundles, switch from CSSO to PostCSS with `postcss-import`:
```bash
postcss admin-bundle.css --use postcss-import --use cssnano -o admin-bundle.min.css
```
This would inline all @imports into a single file for maximum performance.

## Version History

- **v1.0.0** (Nov 19, 2025) - Initial modular architecture
  - Created bundle system with context-specific CSS
  - Implemented production build with CSSO minification
  - Organized CSS into shared/admin/mgmt/hub structure
  - Added cache-busting with `css-version.json`
  - Created enterprise component library

## Related Documentation

- [User Profile Component Guide](../src/components/USER_PROFILE_COMPONENT_GUIDE.md)
- [Enterprise Design System](../public/assets/css/shared/enterprise-design-system.css)
- [Admin Dashboard](../public/admin/index.php)
- [Management Console](../public/management/index.php)
- [Hub Frontend](../public/hub.php)
