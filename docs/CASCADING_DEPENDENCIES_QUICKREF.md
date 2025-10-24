# Quick Reference: Adding Cascading Dependencies

## 3-Step Process

### 1️⃣ Add HTML with IDs

```html
<!-- Parent Checkbox -->
<input type="checkbox" id="enableFeature" 
       onchange="toggleDependentSection('enableFeature', 'featureFields', true)">

<!-- Dependent Container (hidden by default) -->
<div id="featureFields" style="display: none;">
    <!-- Your fields here -->
</div>
```

### 2️⃣ Register in JavaScript

In `/public/assets/js/admin.js` → `initializeDependencies()`:

```javascript
const enableFeature = document.getElementById('enableFeature');
if (enableFeature) {
    enableFeature.addEventListener('change', function() {
        toggleDependentSection('enableFeature', 'featureFields', true);
    });
}
```

### 3️⃣ Initialize on Load

In `populateAdvancedSettings()`:

```javascript
// Set checkbox state
document.getElementById('enableFeature').checked = config.feature?.enabled || false;

// Initialize visibility
toggleDependentSection('enableFeature', 'featureFields', true);

// Populate fields
document.getElementById('featureField1').value = config.feature?.field1 || '';
```

## Real Examples from The Hub

### ✅ Google OAuth → Google Groups
```
☑ Enable Google Login (in Auth section)
    ↓
📦 Google OAuth & Groups Section appears
    ↓
    ☑ Enable Google Groups Auto-Role Assignment
        ↓
    📦 Group configuration fields appear
```

### ✅ Microsoft OAuth → Microsoft Groups (coming soon)
```
☐ Enable Microsoft Login (in Auth section)
    ↓
📦 Microsoft OAuth & Groups Section (hidden)
```

## Common Patterns

### Simple Show/Hide
```javascript
onchange="toggleDependentSection('parentCheckbox', 'childContainer', false)"
```
- Shows/hides container
- Doesn't disable inputs
- Good for: Optional sections that don't have nested dependencies

### Cascading Disable
```javascript
onchange="toggleDependentSection('parentCheckbox', 'childContainer', true)"
```
- Shows/hides container
- Disables all inputs when hidden
- Unchecks nested checkboxes (triggers their dependencies)
- Good for: Multi-level features with deep nesting

## Styling Tips

### Add Visual Hierarchy
```html
<div id="childFields" style="display: none; padding-left: 2rem; border-left: 3px solid var(--primary-color);">
    <!-- Indented and color-coded -->
</div>
```

### Disabled State
```css
input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
```

## Testing Checklist

- [ ] Parent unchecked → Child hidden
- [ ] Parent checked → Child visible
- [ ] Child fields disabled when parent unchecked
- [ ] Nested checkboxes cascade properly
- [ ] Settings save when hidden
- [ ] Settings load and initialize visibility
- [ ] No JavaScript errors in console

## Need Help?

See full documentation: `/docs/CASCADING_DEPENDENCIES.md`
