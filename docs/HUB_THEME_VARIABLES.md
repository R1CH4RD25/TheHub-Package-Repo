# Hub Theme Variables Reference

All theme colors and styling for the Hub landing page are controlled via CSS variables. No hardcoded values!

## 📍 Location
These variables can be set in your theme CSS or in the Site Settings → Theme Management interface.

## 🎨 Available Theme Variables

### Page Background
```css
--hub-page-bg                    /* Main background color (default: #FFFFFF) */
--hub-particle-glow-1            /* Animated particle glow 1 (default: rgba(201, 151, 0, 0.05)) */
--hub-particle-glow-2            /* Animated particle glow 2 (default: rgba(201, 151, 0, 0.05)) */
```

### Header Section
```css
--hub-title-color                /* Main title color (default: #000) */
--hub-subtitle-color             /* Subtitle text color (default: #666) */
```

### Section Cards (Tiles)
```css
--hub-tile-bg                    /* Card background (default: rgba(255, 255, 255, 0.95)) */
--hub-tile-text                  /* Card text color (default: #333) */
--hub-card-shadow                /* Card shadow (default: rgba(0,0,0,0.08)) */
--hub-card-border                /* Card border (default: rgba(0,0,0,0.05)) */
```

### Card Hover Effects
```css
--hub-card-hover-shadow          /* Hover shadow (default: rgba(201, 151, 0, 0.2)) */
--hub-card-hover-border          /* Hover border (default: rgba(201, 151, 0, 0.1)) */
--hub-card-hover-title           /* Title color on hover (default: var(--primary-color)) */
--hub-card-glow-center           /* Inner glow center (default: rgba(201, 151, 0, 0.03)) */
--hub-card-glow-edge             /* Inner glow edge (default: rgba(255, 215, 0, 0.03)) */
```

### Icon & Description
```css
--hub-icon-shadow                /* Icon drop shadow (default: rgba(0,0,0,0.1)) */
--hub-card-description           /* Description text color (default: var(--text-muted)) */
--hub-card-hover-description     /* Description on hover (default: rgba(255, 255, 255, 0.95)) */
--hub-card-hover-description-shadow /* Description text shadow on hover (default: rgba(0, 0, 0, 0.3)) */
```

### Empty State
```css
--hub-no-sections-bg             /* Empty state background (default: white) */
--hub-no-sections-shadow         /* Empty state shadow (default: rgba(0,0,0,0.08)) */
```

## 🎯 Example Usage

### In Custom Theme CSS
```css
:root {
    --hub-page-bg: #f8f9fa;
    --hub-tile-bg: rgba(255, 255, 255, 0.98);
    --hub-card-hover-shadow: rgba(0, 123, 255, 0.3);
    --hub-card-hover-title: #0066cc;
}
```

### Dark Mode Example
```css
[data-theme="dark"] {
    --hub-page-bg: #1a1a1a;
    --hub-tile-bg: rgba(40, 40, 40, 0.95);
    --hub-tile-text: #ffffff;
    --hub-title-color: #ffffff;
    --hub-subtitle-color: #aaaaaa;
    --hub-card-description: #999999;
    --hub-card-shadow: rgba(0,0,0,0.5);
}
```

### School Colors Example
```css
:root {
    /* Blue & Gold Theme */
    --hub-card-hover-shadow: rgba(0, 51, 153, 0.25);
    --hub-card-hover-border: rgba(255, 215, 0, 0.3);
    --hub-card-hover-title: #003399;
    --hub-particle-glow-1: rgba(0, 51, 153, 0.05);
    --hub-particle-glow-2: rgba(255, 215, 0, 0.05);
}
```

## 🔧 Applying Changes

### Method 1: Via Theme Manager (Recommended)
1. Go to **Admin → Site Settings → Theme Management**
2. Create or edit a theme
3. Add your CSS variables in the Custom CSS section
4. Save and apply the theme

### Method 2: Direct CSS File
1. Edit `/public/assets/css/custom-theme.css`
2. Add your variable overrides
3. Theme changes apply immediately

## 📚 Related Documentation
- [THEME_MANAGEMENT.md](./THEME_MANAGEMENT.md) - Full theme system documentation
- [COLOR_SCHEME_QUICKSTART.md](./COLOR_SCHEME_QUICKSTART.md) - Quick color customization guide
- [CSS_BUILD_QUICKSTART.md](./CSS_BUILD_QUICKSTART.md) - CSS compilation process
