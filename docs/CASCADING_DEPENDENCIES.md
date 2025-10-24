# Cascading Dependencies System

## Overview
The Hub supports cascading optional features where enabling one feature can reveal additional sub-features. This creates a clean, progressive disclosure UI that only shows relevant options when needed.

## Architecture Pattern

### Level 1: Top-Level Features
These are the main optional features that users can enable/disable:
- Google OAuth
- Microsoft OAuth  
- Email Notifications (future)
- API Access (future)
- Audit Logging (future)

### Level 2: Dependent Sub-Features
When a Level 1 feature is enabled, related sub-features become available:
- **Google OAuth** → Google Groups Auto-Role Assignment
- **Microsoft OAuth** → Azure AD Groups (coming soon)
- **Email Notifications** → Email Templates, Digest Settings
- **API Access** → Webhooks, Rate Limiting
- **Audit Logging** → Log Export, Retention Policies

### Level 3+: Nested Dependencies
Sub-features can have their own dependencies:
- **Google Groups** → Service Account Config → Custom Role Mappings
- **Webhooks** → Event Filters → Retry Policies

## Implementation Guide

### Step 1: HTML Structure

Wrap dependent content in a container with a unique ID:

```html
<!-- Parent Feature Checkbox -->
<div class="form-group">
    <label class="checkbox-label">
        <input type="checkbox" id="enableParentFeature" onchange="toggleDependentSection('enableParentFeature', 'parentFeatureFields', true)">
        <span>Enable Parent Feature</span>
    </label>
    <small>Description of parent feature</small>
</div>

<!-- Dependent Fields (initially hidden) -->
<div id="parentFeatureFields" style="display: none;">
    <div class="form-group">
        <label for="childSetting1">Child Setting 1</label>
        <input type="text" id="childSetting1">
    </div>
    
    <!-- Nested Dependency -->
    <div class="form-group">
        <label class="checkbox-label">
            <input type="checkbox" id="enableChildFeature" onchange="toggleDependentSection('enableChildFeature', 'childFeatureFields', true)">
            <span>Enable Child Feature</span>
        </label>
    </div>
    
    <div id="childFeatureFields" style="display: none;">
        <div class="form-group">
            <label for="grandchildSetting">Grandchild Setting</label>
            <input type="text" id="grandchildSetting">
        </div>
    </div>
</div>
```

### Step 2: JavaScript Registration

Add the dependency to `initializeDependencies()` in `admin.js`:

```javascript
function initializeDependencies() {
    // Existing dependencies...
    
    // Add your new dependency
    const enableParentFeature = document.getElementById('enableParentFeature');
    if (enableParentFeature) {
        enableParentFeature.addEventListener('change', function() {
            toggleDependentSection('enableParentFeature', 'parentFeatureFields', true);
        });
    }
}
```

### Step 3: Initialize on Page Load

The `populateAdvancedSettings()` function should trigger initial visibility:

```javascript
function populateAdvancedSettings(config) {
    // Set checkbox state
    document.getElementById('enableParentFeature').checked = config.parent_feature?.enabled || false;
    
    // Initialize visibility
    toggleDependentSection('enableParentFeature', 'parentFeatureFields', true);
    
    // Populate child fields
    document.getElementById('childSetting1').value = config.parent_feature?.child_setting || '';
}
```

## Function Reference

### `toggleDependentSection(checkboxId, dependentElementId, shouldDisable)`

**Parameters:**
- `checkboxId` (string): ID of the parent checkbox
- `dependentElementId` (string): ID of the container to show/hide
- `shouldDisable` (boolean): Whether to disable inputs when hidden
  - `true`: Disables all inputs and unchecks nested checkboxes (cascading effect)
  - `false`: Only hides the section, inputs remain enabled

**Example Usage:**
```javascript
// Simple show/hide
toggleDependentSection('enableFeature', 'featureFields', false);

// Show/hide with cascading disable
toggleDependentSection('enableFeature', 'featureFields', true);
```

### `toggleAuthSection(provider, isEnabled)`

**Legacy function for OAuth sections** (will migrate to `toggleDependentSection`):
```javascript
toggleAuthSection('google', true);  // Show Google OAuth section
toggleAuthSection('microsoft', false); // Hide Microsoft OAuth section
```

## Current Implementation

### Authentication Flow

```
┌─────────────────────────────────────┐
│ Authentication & Login Section      │
├─────────────────────────────────────┤
│ ☑ Local User Accounts               │
│ ☑ Google OAuth ─────────────┐       │
│ ☐ Microsoft OAuth           │       │
└─────────────────────────────┼───────┘
                              │
                              ▼
┌─────────────────────────────────────┐
│ Google OAuth & Groups Section       │
├─────────────────────────────────────┤
│ • Client ID                         │
│ • Client Secret                     │
│ • Redirect URI                      │
│                                     │
│ ☑ Enable Google Groups ─────┐      │
└─────────────────────────────┼───────┘
                              │
                              ▼
┌─────────────────────────────────────┐
│ Google Groups Fields                │
├─────────────────────────────────────┤
│ • Service Account Email             │
│ • Admin Email                       │
│ • Group-to-Role Associations        │
└─────────────────────────────────────┘
```

## Best Practices

### 1. **Clear Hierarchy**
Make it obvious which features depend on others:
- Use indentation or borders
- Add "requires [parent feature]" text
- Disable dependent checkboxes when parent is off

### 2. **Graceful Degradation**
When a parent feature is disabled:
- Uncheck all child features
- Disable (not hide) their inputs
- Show a tooltip explaining why it's disabled

### 3. **State Persistence**
Save the state of all checkboxes, even when hidden:
```javascript
// DON'T skip hidden fields
if (element.style.display !== 'none') {
    config.setting = getSetting();
}

// DO save all settings
config.setting = getSetting(); // Saves even when hidden
```

### 4. **Visual Feedback**
Add styling to show relationships:
```css
.dependent-section {
    margin-left: 2rem;
    padding-left: 1rem;
    border-left: 3px solid var(--primary-color);
}
```

## Future Enhancements

### Planned Dependencies

1. **Email System**
   - Enable Email → SMTP Config → Templates → Per-Module Settings

2. **Notifications**
   - Enable Notifications → Email Digest → Slack → SMS

3. **API Access**
   - Enable API → API Keys → Webhooks → Event Filters

4. **Backup & Export**
   - Enable Backups → Schedule → Retention → Cloud Storage

5. **Modules**
   - Enable Module → Module Settings → Module Permissions → Module Integrations

### Advanced Pattern: Conditional Dependencies

Some features might depend on multiple parents:

```javascript
// Show feature C only if BOTH A and B are enabled
function updateFeatureCVisibility() {
    const featureA = document.getElementById('enableFeatureA').checked;
    const featureB = document.getElementById('enableFeatureB').checked;
    const shouldShow = featureA && featureB;
    
    const featureC = document.getElementById('featureCFields');
    if (featureC) {
        featureC.style.display = shouldShow ? 'block' : 'none';
    }
}
```

## Testing Checklist

When implementing new dependencies:

- [ ] Parent feature off → Dependent section hidden
- [ ] Parent feature on → Dependent section visible
- [ ] Nested dependencies cascade properly (3+ levels)
- [ ] Settings save correctly when hidden
- [ ] Settings load correctly on page refresh
- [ ] Disabled inputs don't submit values
- [ ] Visual styling shows hierarchy clearly
- [ ] Works on mobile/tablet (responsive)
- [ ] No console errors
- [ ] Accessibility (screen readers can navigate)

## Example: Adding Email Digest Feature

```html
<!-- In Email Configuration section -->
<div class="form-group">
    <label class="checkbox-label">
        <input type="checkbox" id="enableEmailDigest" 
               onchange="toggleDependentSection('enableEmailDigest', 'emailDigestFields', true)">
        <span>Enable Daily Email Digest</span>
    </label>
    <small>Send summary emails to users daily</small>
</div>

<div id="emailDigestFields" style="display: none; margin-top: 1rem; padding-left: 2rem; border-left: 3px solid var(--primary-color);">
    <div class="settings-grid">
        <div class="form-group">
            <label for="digestTime">Send Time</label>
            <input type="time" id="digestTime" value="08:00">
            <small>Time to send daily digest (server time)</small>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" id="digestIncludeFuel">
                <span>Include Fuel Reports</span>
            </label>
        </div>
        
        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" id="digestIncludeVehicles">
                <span>Include Vehicle Status</span>
            </label>
        </div>
    </div>
</div>
```

```javascript
// In initializeDependencies()
const enableEmailDigest = document.getElementById('enableEmailDigest');
if (enableEmailDigest) {
    enableEmailDigest.addEventListener('change', function() {
        toggleDependentSection('enableEmailDigest', 'emailDigestFields', true);
    });
}

// In populateAdvancedSettings()
document.getElementById('enableEmailDigest').checked = config.email?.digest_enabled || false;
toggleDependentSection('enableEmailDigest', 'emailDigestFields', true);
document.getElementById('digestTime').value = config.email?.digest_time || '08:00';

// In gatherAdvancedSettings()
email: {
    digest_enabled: document.getElementById('enableEmailDigest').checked,
    digest_time: document.getElementById('digestTime').value,
    digest_include_fuel: document.getElementById('digestIncludeFuel').checked,
    digest_include_vehicles: document.getElementById('digestIncludeVehicles').checked
}
```

## Support

For questions or issues with cascading dependencies, check:
1. Browser console for JavaScript errors
2. Section IDs match between HTML and JavaScript
3. `initializeDependencies()` is called on page load
4. Parent checkbox has correct `onchange` handler
