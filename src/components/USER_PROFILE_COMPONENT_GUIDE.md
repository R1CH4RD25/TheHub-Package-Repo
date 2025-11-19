# User Profile Dropdown Component - Usage Guide

## Overview
Standalone, reusable user profile dropdown component that can be included in any page (Hub, Management, Admin) without modifying existing code.

## Quick Start

### Basic Usage (Minimal Setup)
```php
<?php
// In your page (e.g., management/index.php)
require_once __DIR__ . '/../src/components/UserProfileDropdown.php';

// Somewhere in your navigation/header:
\Hub\Components\UserProfileDropdown::render($user, $userRole);
?>
```

That's it! The component includes its own JavaScript and handles all interactions.

## Examples

### Example 1: Basic Integration (Management Console)
```php
<?php
// management/index.php - Add to your navbar
require_once __DIR__ . '/../src/components/UserProfileDropdown.php';

// Your existing user data
$user = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['name'],
    'picture' => $_SESSION['picture'] ?? null,
    'email' => $_SESSION['email']
];
$userRole = $_SESSION['role'];
?>

<nav class="your-navbar">
    <div class="nav-left">
        <!-- Your existing nav items -->
    </div>
    <div class="nav-right">
        <?php \Hub\Components\UserProfileDropdown::render($user, $userRole); ?>
    </div>
</nav>
```

### Example 2: Custom Options
```php
<?php
// Customize which links appear
$options = [
    'show_preferences' => false,      // Hide preferences link
    'show_contact' => true,           // Show contact preferences
    'profile_url' => '/my-profile',   // Custom profile URL
    'logout_url' => '/auth/logout',   // Custom logout URL
];

\Hub\Components\UserProfileDropdown::render($user, $userRole, $options);
?>
```

### Example 3: Adding Custom Links
```php
<?php
$options = [
    'custom_links' => [
        [
            'url' => '/help',
            'icon' => 'fas fa-question-circle',
            'label' => 'Help & Support'
        ],
        [
            'url' => '/settings',
            'icon' => 'fas fa-sliders-h',
            'label' => 'Advanced Settings'
        ]
    ]
];

\Hub\Components\UserProfileDropdown::render($user, $userRole, $options);
?>
```

## Required Data Structure

### User Array (Minimum Required)
```php
$user = [
    'name' => 'John Doe',           // Required: Display name
    'picture' => 'https://...',     // Optional: Profile image URL
];
```

### User Role (String)
```php
$userRole = 'admin';  // Any role: admin, teacher, student, etc.
```

## Features

### ✅ What It Does
- **Avatar Display**: Shows user profile picture with fallback to default
- **User Info**: Name and role formatted nicely
- **Profile Links**: My Profile, Contact Preferences, Settings
- **Logout Button**: Styled logout with icon
- **Dropdown Toggle**: Click to open/close (includes keyboard support)
- **Auto-close**: Closes on outside click or Escape key
- **JavaScript Included**: No external script needed

### 🎨 CSS Classes (Already Styled)
The component uses these CSS classes (already in your shared CSS):
- `.nav-user-dropdown` - Container
- `.nav-user-trigger` - Button that shows avatar/name
- `.nav-user-avatar` - Profile image
- `.nav-user-info` - Name/role container
- `.nav-user-menu` - Dropdown menu
- `.user-menu-item` - Each menu link

### 🔒 Security
- All output is escaped with `htmlspecialchars()`
- Avatar URLs are validated (data URIs, HTTPS, local paths only)
- Default fallback avatar for invalid URLs

## Options Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `show_preferences` | bool | `true` | Show "Preferences" link |
| `show_contact` | bool | `true` | Show "Contact Preferences" link |
| `profile_url` | string | `/profile.php` | URL for profile page |
| `logout_url` | string | `/logout.php` | URL for logout |
| `custom_links` | array | `[]` | Additional menu items (see example 3) |

## Integration Checklist

- [ ] Include the component file: `require_once __DIR__ . '/../src/components/UserProfileDropdown.php';`
- [ ] Prepare user data array with `name` and `picture`
- [ ] Get user role from session/database
- [ ] Call `UserProfileDropdown::render($user, $userRole)` in your navbar
- [ ] Test dropdown opens/closes correctly
- [ ] Verify all links work (profile, logout, etc.)

## Troubleshooting

### Dropdown doesn't open
- Check browser console for JavaScript errors
- Ensure no other JavaScript is conflicting with click events
- Verify element IDs are unique (only one dropdown per page)

### Styles look wrong
- Ensure you're loading the shared CSS: `enterprise-design-system.css` or `header.css`
- Check for CSS conflicts with existing styles
- Browser cache: Hard refresh with Ctrl+Shift+R

### Avatar image not showing
- Verify `$user['picture']` is a valid URL or data URI
- Check network tab in browser DevTools
- Component falls back to `/assets/images/default-avatar.png` if invalid

## File Location
- **Component**: `/var/www/woodson/thehub/src/components/UserProfileDropdown.php`
- **This Guide**: `/var/www/woodson/thehub/src/components/USER_PROFILE_COMPONENT_GUIDE.md`

## Support
This component is maintained as part of The Hub v2.0 architecture. Updates to this file automatically propagate to all pages using it.
