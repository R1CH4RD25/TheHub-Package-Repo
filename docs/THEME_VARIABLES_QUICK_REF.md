# Theme Variables Quick Reference

**Last Updated**: November 18, 2025 | **The Hub v1.3+**

---

## 🎨 Color Variables

| Variable | Default | Usage |
|----------|---------|-------|
| `--primary-color` | `#667eea` | Main brand color, primary buttons |
| `--secondary-color` | `#764ba2` | Secondary UI elements |
| `--accent-color` | `#f093fb` | Highlights, accents |
| `--background-color` | `#f8f9fa` | Page background |
| `--text-primary` | `#2c3e50` | Main text color |
| `--text-secondary` | `#7f8c8d` | Secondary text |
| `--text-muted` | `#95a5a6` | Disabled/muted text |
| `--success-color` | `#27ae60` | Success states |
| `--warning-color` | `#f39c12` | Warning states |
| `--danger-color` | `#e74c3c` | Error states |
| `--info-color` | `#3498db` | Info states |
| `--border-color` | `#dee2e6` | Default borders |
| `--hover-bg` | `#f8f9fa` | Hover backgrounds |
| `--card-bg` | `white` | Card backgrounds |

---

## 📝 Typography Variables

| Variable | Default | Usage |
|----------|---------|-------|
| `--font-family` | System fonts | Main font |
| `--font-size-base` | `16px` | Base text |
| `--font-size-sm` | `14px` | Small text |
| `--font-size-lg` | `18px` | Large text |
| `--font-size-xl` | `24px` | Extra large |
| `--font-size-xxl` | `32px` | Headings |
| `--font-weight-normal` | `400` | Normal weight |
| `--font-weight-medium` | `500` | Medium weight |
| `--font-weight-semibold` | `600` | Semi-bold |
| `--font-weight-bold` | `700` | Bold |

---

## 📏 Spacing Variables

| Variable | Value | Usage |
|----------|-------|-------|
| `--spacing-xs` | `5px` | Minimal spacing |
| `--spacing-sm` | `10px` | Small spacing |
| `--spacing-md` | `15px` | Medium spacing |
| `--spacing-lg` | `20px` | Large spacing |
| `--spacing-xl` | `40px` | Extra large |
| `--spacing-xxl` | `60px` | Section gaps |

---

## 🖼️ Layout Variables

| Variable | Default | Usage |
|----------|---------|-------|
| `--container-max-width` | `1600px` | Max content width |
| `--border-radius` | `8px` | Default radius |
| `--border-radius-sm` | `4px` | Small radius |
| `--border-radius-lg` | `12px` | Large radius |

---

## ✨ Shadow Variables

| Variable | Value | Usage |
|----------|-------|-------|
| `--shadow-sm` | `0 1px 3px rgba(0,0,0,0.12)` | Subtle elevation |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.1)` | Medium elevation |
| `--shadow-lg` | `0 10px 25px rgba(0,0,0,0.15)` | Strong elevation |

---

## 🔧 Usage Examples

### Button with Theme Colors
```css
.pkg-btn {
    background: var(--primary-color);
    color: white;
    padding: var(--spacing-sm) var(--spacing-lg);
    border-radius: var(--border-radius-sm);
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-medium);
    box-shadow: var(--shadow-sm);
}
```

### Card Component
```css
.pkg-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: var(--spacing-lg);
    box-shadow: var(--shadow-sm);
}
```

### Status Badge
```css
.pkg-badge-success {
    background: var(--success-color);
    color: white;
    padding: var(--spacing-xs) var(--spacing-sm);
    border-radius: var(--border-radius-lg);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
}
```

### Text Hierarchy
```css
.pkg-heading {
    font-size: var(--font-size-xxl);
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    margin-bottom: var(--spacing-lg);
}

.pkg-subtext {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
}
```

---

## ⚡ Pro Tips

1. **Always provide fallbacks**: `var(--primary-color, #667eea)`
2. **Use semantic variables**: Prefer `--text-primary` over custom colors
3. **Respect spacing scale**: Use `--spacing-*` instead of arbitrary px values
4. **Test theme changes**: Change Admin colors to verify your styles update
5. **Mobile responsive**: Use variables for breakpoints too

---

## 🔗 See Also

- **Full Guide**: `docs/PACKAGE_THEME_GUIDELINES.md`
- **Example CSS**: `public/assets/css/management.css`
- **Build System**: `build-css.sh`
- **Theme API**: `public/api/theme-css.php`
