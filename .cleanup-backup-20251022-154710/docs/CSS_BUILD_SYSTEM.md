# CSS Build System Configuration

## Overview
The Hub now uses a build system that combines all CSS files into optimized production bundles.

## Modes

### Development Mode (Default)
- Individual CSS files loaded separately
- Easy debugging and development
- Automatic cache-busting with timestamps
- No build step required

### Production Mode
- Single combined CSS file per page type
- Faster page loads (fewer HTTP requests)
- Version-based cache busting
- Requires running build script

## Enabling Production Mode

### Option 1: Environment Variable
Add to your `.env` file:
```
CSS_PRODUCTION_MODE=true
```

### Option 2: Bootstrap Configuration
Add to `src/bootstrap.php`:
```php
define('CSS_PRODUCTION_MODE', true);
```

## Build Process

### Manual Build
Run the build script after CSS changes:
```bash
./build-css.sh
```

### Automatic Build on Settings Change
The build script is automatically triggered when:
- Site settings are updated via the admin panel
- Theme colors are changed
- Header/footer settings are modified

### What Gets Built
1. **hub-production.css** - Combines:
   - style.css (base styles)
   - header.css (navbar/header)
   - footer.css (footer)
   - hub.css (hub-specific)
   - media.css (responsive)

2. **dashboard-production.css** - Combines:
   - style.css (base styles)
   - header.css (navbar/header)
   - footer.css (footer)
   - admin.css (dashboard layout)
   - admin-theme.css (dashboard theming)
   - admin-colors.css (dashboard colors)
   - media.css (responsive)

## File Structure
```
public/assets/css/
├── style.css          # Base styles (shared)
├── header.css         # Header/navbar (shared)
├── footer.css         # Footer (shared)
├── hub.css            # Hub-specific styles
├── admin.css          # Dashboard layout
├── admin-theme.css    # Dashboard theming
├── admin-colors.css   # Dashboard color system
├── media.css          # Responsive/media queries (shared)
└── dist/              # Production builds
    ├── hub-production.css
    ├── dashboard-production.css
    ├── hub-production.min.css (if csso installed)
    ├── dashboard-production.min.css (if csso installed)
    └── version.txt
```

## Optional: CSS Minification

For additional file size reduction, install csso-cli:
```bash
npm install -g csso-cli
```

The build script will automatically create minified versions if csso is available.

## Cache Busting

### Development Mode
Uses timestamp: `?v=1234567890`

### Production Mode
Uses build version from `dist/version.txt`: `?v=1729612345`

## Workflow

### Development
1. Edit CSS source files in `public/assets/css/`
2. Changes reflect immediately (development mode)
3. Test in browser with hard refresh

### Deploying to Production
1. Enable production mode in `.env`
2. Run `./build-css.sh`
3. Commit both source files and built files
4. Deploy to server

### After Site Settings Changes
1. Settings are saved to database
2. Build script runs automatically
3. New version number generated
4. Users get updated CSS on next page load

## Performance Benefits

### Development Mode
- 8 HTTP requests for Hub
- 11 HTTP requests for Dashboard

### Production Mode
- 2 HTTP requests for Hub (theme + production bundle)
- 2 HTTP requests for Dashboard (theme + production bundle)

Typical savings: **75% fewer HTTP requests**, **30-50% smaller total CSS size** (with minification)
