# Package Configuration System

## Overview
Each package can include a `config.php` file to define package-specific settings, resources, and behaviors. This keeps packages self-contained and avoids polluting global site settings with package-specific data.

## Benefits
- **Portability**: Packages include all their configuration
- **Isolation**: No global database pollution
- **Customization**: Easy to modify per installation
- **Version Control**: Settings tracked with package code
- **Self-Documentation**: Config file shows all available options

## File Structure
```
packages/
└── your-package/
    ├── manifest.json        # Package metadata
    ├── config.php          # Package configuration (NEW)
    ├── index.php           # Main view
    ├── list.php           # List view (optional)
    └── README.md          # Documentation
```

## Example: Bullying Report Config

```php
<?php
/**
 * Bullying Report Package Configuration
 */

return [
    'display_name' => 'Bullying & Harassment Report',
    'version' => '1.0.0',
    'author' => 'TheHub',
    
    // Emergency contact information
    'emergency_contacts' => [
        'emergency_911' => [
            'label' => 'Emergency',
            'number' => '911',
            'icon' => 'bi-telephone-fill',
            'icon_color' => 'text-danger',
            'description' => 'Life-threatening emergencies only'
        ],
        'school_safety' => [
            'label' => 'School Safety Office',
            'number' => '(903) 763-5511',
            'icon' => 'bi-building',
            'icon_color' => 'text-primary',
            'description' => 'Report non-emergency safety concerns'
        ]
    ],
    
    // Form behavior
    'allow_anonymous' => true,
    'require_confirmation' => false,
    'auto_notify_staff' => true,
    
    // Notification settings
    'notify_roles' => ['principal', 'counselor'],
    'notify_email' => 'safety@example.com',
    
    // Display options
    'show_help_resources' => true,
    'show_confidentiality_notice' => true,
    
    // Data retention
    'retention_days' => 1825, // 5 years
];
```

## Usage in Templates

### Loading Configuration
```php
<?php
// In packages/your-package/index.php

// Load package configuration
$packageConfig = require __DIR__ . '/config.php';

// Access settings
$emergencyContacts = $packageConfig['emergency_contacts'] ?? [];
$allowAnonymous = $packageConfig['allow_anonymous'] ?? false;
```

### Using in HTML
```php
<?php foreach ($emergencyContacts as $key => $contact): ?>
    <div class="contact-info">
        <i class="<?php echo e($contact['icon']); ?> <?php echo e($contact['icon_color']); ?>"></i>
        <strong><?php echo e($contact['label']); ?>:</strong> 
        <?php echo e($contact['number']); ?>
        <?php if (isset($contact['description'])): ?>
            <small><?php echo e($contact['description']); ?></small>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
```

## Common Configuration Options

### Contact Information
```php
'contacts' => [
    'primary' => [
        'label' => 'Main Office',
        'phone' => '(555) 123-4567',
        'email' => 'office@example.com',
        'icon' => 'bi-building',
        'icon_color' => 'text-primary'
    ]
]
```

### Form Behavior
```php
'form_options' => [
    'allow_anonymous' => true,
    'require_confirmation' => false,
    'show_progress' => true,
    'enable_drafts' => false,
    'max_attachments' => 5,
    'allowed_file_types' => ['pdf', 'jpg', 'png', 'doc', 'docx']
]
```

### Notifications
```php
'notifications' => [
    'enabled' => true,
    'notify_on_submit' => true,
    'notify_roles' => ['admin', 'staff'],
    'notify_emails' => ['alerts@example.com'],
    'email_template' => 'default',
    'include_submission_data' => false
]
```

### Display Options
```php
'display' => [
    'show_help_text' => true,
    'show_examples' => false,
    'theme' => 'default',
    'icon' => 'bi-clipboard-check',
    'color_scheme' => 'primary'
]
```

### Data Management
```php
'data' => [
    'retention_days' => 365,
    'archive_after_days' => 180,
    'allow_editing' => true,
    'allow_deletion' => false,
    'require_approval' => true
]
```

### List View Options
```php
'list_view' => [
    'default_sort' => 'submitted_at',
    'default_order' => 'DESC',
    'items_per_page' => 25,
    'show_filters' => true,
    'exportable' => true,
    'export_formats' => ['csv', 'xlsx', 'pdf']
]
```

## Environment-Specific Overrides

For settings that change per environment (dev/staging/production), you can check environment variables:

```php
<?php
return [
    'notify_email' => $_ENV['PACKAGE_NOTIFY_EMAIL'] ?? 'default@example.com',
    'debug_mode' => ($_ENV['APP_ENV'] === 'development'),
    'api_endpoint' => $_ENV['PACKAGE_API_ENDPOINT'] ?? 'https://api.production.com',
];
```

## Validation

Add validation logic at the top of your config:

```php
<?php
// Validate required environment variables
$requiredEnvVars = ['PACKAGE_API_KEY', 'PACKAGE_WEBHOOK_URL'];
foreach ($requiredEnvVars as $var) {
    if (!isset($_ENV[$var])) {
        throw new \Exception("Missing required environment variable: $var");
    }
}

return [
    'api_key' => $_ENV['PACKAGE_API_KEY'],
    'webhook_url' => $_ENV['PACKAGE_WEBHOOK_URL'],
    // ... rest of config
];
```

## Migration from Site Settings

If you previously stored package data in `site_settings` table:

### Before (Global Database)
```php
<?php
$db = Database::getInstance();
$settings = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'package_%'");
```

### After (Package Config)
```php
<?php
$config = require __DIR__ . '/config.php';
$phoneNumber = $config['contacts']['primary']['phone'];
```

### Cleanup
Remove package-specific settings from database:
```sql
DELETE FROM site_settings WHERE setting_key IN ('package_setting_1', 'package_setting_2');
```

## Best Practices

1. **Default Values**: Always provide sensible defaults
2. **Comments**: Document each configuration option
3. **Structure**: Group related settings logically
4. **Types**: Use appropriate data types (booleans, integers, arrays)
5. **Validation**: Validate critical settings at load time
6. **Security**: Never commit secrets (use environment variables)
7. **Documentation**: Include example values and descriptions

## Security Considerations

- ✅ Store in `config.php` (version controlled)
- ✅ Use environment variables for secrets
- ❌ Don't hardcode API keys or passwords
- ❌ Don't store user data in config
- ✅ Validate all config values before use
- ✅ Use `e()` helper when outputting config values in HTML

## Example: Full Package Implementation

```php
<?php
// packages/my-package/config.php
return [
    'display_name' => 'My Package',
    'version' => '1.0.0',
    'contacts' => ['email' => 'support@example.com'],
    'form_options' => ['allow_anonymous' => true]
];

// packages/my-package/index.php
<?php
use Hub\DynamicFormRenderer;
use Hub\Auth;

$config = require __DIR__ . '/config.php';
$currentUser = Auth::getCurrentUser();

?>
<div class="dynamic-section">
    <h2><?php echo e($config['display_name']); ?></h2>
    
    <?php if ($config['form_options']['allow_anonymous']): ?>
        <p>You may submit anonymously</p>
    <?php endif; ?>
    
    <?php
    $renderer = new DynamicFormRenderer($section);
    echo $renderer->render();
    ?>
    
    <div class="contact-info">
        Support: <?php echo e($config['contacts']['email']); ?>
    </div>
</div>
```

## Related Documentation
- [Package Development Guide](PACKAGE_DEVELOPMENT.md)
- [Dynamic Form System](DYNAMIC_FORMS.md)
- [Package Theme Guidelines](PACKAGE_THEME_GUIDELINES.md)
