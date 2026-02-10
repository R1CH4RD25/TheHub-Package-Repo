# CSS Architecture Quick Reference

**Last Updated:** February 10, 2026  
**Purpose:** Document CSS file structure and build system to prevent duplicate file issues

---

## 📁 Directory Structure

### Source Files (Canonical)
```
public/assets/css/
├── shared/               # ✅ PRIMARY SOURCE FILES
│   ├── header.css       # Header/navbar styles
│   ├── footer.css       # Footer styles
│   ├── sidebar.css      # Sidebar navigation
│   ├── hub-components.css  # Hub page components
│   ├── navigation-emoji.css  # Icon/emoji styles
│   ├── variables.css    # CSS custom properties
│   └── ...
├── admin/               # Admin-specific styles
├── management/          # Management-specific styles
├── legacy/              # Deprecated styles (DO NOT USE)
└── header.css           # ⚠️ DUPLICATE - consolidate with shared/header.css
```

### Generated Bundles (Do Not Edit)
```
public/assets/css/
├── admin-bundle.css     # Admin dashboard bundle (172KB)
├── hub-bundle.css       # Hub page bundle (128KB)
├── mgmt-bundle.css      # Management console bundle (172KB)
└── css-version.json     # Cache-busting timestamp
```

---

## 🔧 Build System

### Build Script
**File:** `build-css.sh`

**Command:**
```bash
bash build-css.sh
```

**What It Does:**
1. Concatenates source CSS files based on bundle arrays
2. Generates three bundles: admin, hub, mgmt
3. Updates css-version.json timestamp
4. Outputs bundle sizes

### Bundle Definitions

#### Admin Bundle
```bash
ADMIN_FILES=(
    "shared/variables.css"
    "shared/header.css"
    "shared/footer.css"
    "shared/sidebar.css"
    "shared/navigation-emoji.css"
    "admin/dashboard.css"
    # ... more files
)
```

#### Hub Bundle
```bash
HUB_FILES=(
    "shared/variables.css"
    "shared/header.css"
    "shared/footer.css"
    "shared/hub-components.css"
    "shared/navigation-emoji.css"
    # ... more files
)
```

#### Management Bundle
```bash
MGMT_FILES=(
    "shared/variables.css"
    "shared/header.css"
    "shared/footer.css"
    "shared/sidebar.css"
    "shared/navigation-emoji.css"
    "management/console.css"
    # ... more files
)
```

---

## ⚠️ Known Issues

### 1. Duplicate header.css Files
**Problem:** Two header.css files exist at different paths
- `/public/assets/css/shared/header.css` (primary)
- `/public/assets/css/header.css` (duplicate)

**Impact:** Both files included in bundles, changes must be made to BOTH

**Current Workaround:** Update both files identically

**Long-term Fix:** 
1. Remove `/public/assets/css/header.css`
2. Update build script to only include `shared/header.css`
3. Verify all pages still load correctly

### 2. Bootstrap CDN Override
**Problem:** Bootstrap CSS loaded from CDN overrides custom styles

**Solution:** Use `!important` flag when necessary
```css
.nav-links {
    gap: 0 !important;  /* Overrides Bootstrap's gap: 15px */
}
```

**Why:** Cannot control CDN load order or modify Bootstrap source

### 3. No Source Maps
**Problem:** Difficult to trace which source file CSS rules come from

**Recommendation:** Add CSS source map generation to build script

---

## 🔄 Standard Workflow

### Making CSS Changes

#### 1. Edit Source Files
```bash
# Edit canonical source files in shared/ directory
vim public/assets/css/shared/header.css

# If duplicate exists, edit both!
vim public/assets/css/header.css
```

#### 2. Rebuild Bundles
```bash
cd /var/www/woodson/thehub
bash build-css.sh

# Verify output
ls -lh public/assets/css/*-bundle.css
```

#### 3. Verify Changes
```bash
# Check bundle contains your changes
grep -n "your-css-rule" public/assets/css/hub-bundle.css
```

#### 4. Commit and Deploy
```bash
git add -A
git commit -m "🎨 Description of CSS changes"
git push origin laravel-migration
```

#### 5. Browser Testing
- Hard refresh: `Ctrl+Shift+R` (Win/Linux) or `Cmd+Shift+R` (Mac)
- Clear cache if needed
- Test multiple browsers

---

## 📋 CSS Editing Checklist

When making CSS changes:

- [ ] Edited canonical source file in `shared/` directory
- [ ] Edited duplicate file if it exists (e.g., header.css)
- [ ] Ran `bash build-css.sh` to rebuild bundles
- [ ] Verified bundle file sizes updated (check timestamps)
- [ ] Grep'd bundle to confirm changes included
- [ ] Committed both source files AND bundles
- [ ] Hard refreshed browser cache
- [ ] Tested across multiple pages
- [ ] Verified mobile responsive (if applicable)
- [ ] Updated documentation if architecture changed

---

## 🎯 CSS Organization Principles

### 1. Shared vs. Specific
**Shared:** Styles used across multiple page types → `shared/`
**Specific:** Styles only for one area → `admin/`, `management/`, etc.

### 2. File Naming
- `{component}.css` - Component-specific styles (header, footer, sidebar)
- `{page}.css` - Page-specific styles (dashboard, hub)
- `variables.css` - CSS custom properties only
- `{feature}.css` - Feature-specific styles (navigation-emoji, modals)

### 3. Load Order Matters
Files concatenated in array order:
1. `variables.css` (first - defines CSS custom properties)
2. Component files (header, footer, etc.)
3. Page-specific files
4. Override files (last)

### 4. Specificity Strategy
- Avoid `!important` unless overriding external CSS (Bootstrap)
- Use CSS custom properties for themeable values
- BEM naming for component isolation
- Utility classes for common patterns

---

## 🔍 Troubleshooting

### "Changes Not Appearing"
1. Did you rebuild? `bash build-css.sh`
2. Did you edit ALL duplicate files?
3. Hard refresh browser (Ctrl+Shift+R)
4. Check bundle timestamp updated
5. Verify editing source file, not bundle

### "Duplicate Styles"
1. Check for duplicate files at different paths
2. Grep all CSS files for the rule
3. Consolidate into single source file
4. Update build script

### "Bootstrap Override"
1. Use `!important` flag
2. Increase specificity (nested selectors)
3. Consider moving rule later in bundle
4. Check browser console for computed styles

### "Bundle Too Large"
1. Analyze with `ls -lh public/assets/css/*-bundle.css`
2. Check for duplicate inclusions in build script
3. Remove unused CSS rules
4. Consider code splitting

---

## 📚 Related Files

- `build-css.sh` - Bundle generation script
- `css-version.json` - Cache busting timestamp
- `HANDOFF_2026-02-10_NAVIGATION_ICONS.md` - Recent CSS work documentation
- `CSS_AUDIT_COMPLETE.md` - Comprehensive CSS audit results
- `FRONTEND_MODERNIZATION.md` - Frontend strategy and architecture

---

## 🚀 Future Improvements

### Short Term
1. **Consolidate duplicate header.css** - Remove root-level duplicate
2. **Add source maps** - Track which source file CSS comes from
3. **Automate rebuild** - Git pre-commit hook for CSS changes

### Medium Term
1. **CSS linting** - Add stylelint for consistency
2. **Minification** - Compress bundles for production
3. **Autoprefixing** - Add vendor prefixes automatically

### Long Term
1. **Modern build tool** - Migrate to Vite or Laravel Mix
2. **CSS modules** - Scope styles to components
3. **Hot reload** - Live CSS updates during development
4. **Tree shaking** - Remove unused CSS automatically

---

**Remember:** Always edit source files in `shared/`, rebuild bundles, and test thoroughly!
