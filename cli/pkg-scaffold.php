#!/usr/bin/env php
<?php
/**
 * Package Scaffolder - Generate new package templates
 * 
 * Usage:
 *   php cli/pkg-scaffold.php --name=bullying-report --namespace=br
 *   php cli/pkg-scaffold.php --name=my-package --namespace=mp --category=education --author="Your Name"
 * 
 * Options:
 *   --name         Package name (kebab-case, required)
 *   --namespace    2-5 char prefix for DB tables (required)
 *   --category     Package category (optional, default: other)
 *   --author       Author name (optional, default: Woodson ISD)
 *   --email        Author email (optional)
 *   --template     Template type: simple|standard|workflow (default: standard)
 * 
 * @author The Hub Team
 * @version 2.0.0
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

class PackageScaffolder {
    private $name;
    private $namespace;
    private $category;
    private $author;
    private $email;
    private $template;
    private $packageDir;
    
    const CATEGORIES = [
        'education', 'maintenance', 'administration', 'facilities', 
        'transportation', 'hr', 'finance', 'other'
    ];
    
    const TEMPLATES = ['simple', 'standard', 'workflow'];
    
    public function __construct($options) {
        $this->name = $options['name'] ?? null;
        $this->namespace = $options['namespace'] ?? null;
        $this->category = $options['category'] ?? 'other';
        $this->author = $options['author'] ?? 'Woodson ISD';
        $this->email = $options['email'] ?? 'tech@woodsonisd.net';
        $this->template = $options['template'] ?? 'standard';
        
        $this->validate();
    }
    
    private function validate() {
        // Validate name
        if (!$this->name) {
            $this->error('--name is required');
        }
        
        if (!preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $this->name)) {
            $this->error("Package name must be kebab-case: {$this->name}");
        }
        
        if (strlen($this->name) < 3 || strlen($this->name) > 50) {
            $this->error("Package name must be 3-50 characters: {$this->name}");
        }
        
        // Validate namespace
        if (!$this->namespace) {
            $this->error('--namespace is required');
        }
        
        if (!preg_match('/^[a-z]{2,5}$/', $this->namespace)) {
            $this->error("Namespace must be 2-5 lowercase letters: {$this->namespace}");
        }
        
        // Validate category
        if (!in_array($this->category, self::CATEGORIES)) {
            echo "\033[33mWarning: Unknown category '{$this->category}'. Using 'other'.\033[0m\n";
            echo "Valid categories: " . implode(', ', self::CATEGORIES) . "\n";
            $this->category = 'other';
        }
        
        // Validate template
        if (!in_array($this->template, self::TEMPLATES)) {
            echo "\033[33mWarning: Unknown template '{$this->template}'. Using 'standard'.\033[0m\n";
            $this->template = 'standard';
        }
        
        // Set package directory
        $this->packageDir = __DIR__ . "/../packages/local/{$this->name}";
        
        // Check if already exists
        if (is_dir($this->packageDir)) {
            $this->error("Package directory already exists: {$this->packageDir}");
        }
    }
    
    public function scaffold() {
        echo "\033[1m\033[34mPackage Scaffolder v2.0\033[0m\n";
        echo str_repeat('=', 80) . "\n\n";
        
        echo "Creating package: \033[1m{$this->name}\033[0m\n";
        echo "Namespace: \033[1m{$this->namespace}\033[0m\n";
        echo "Category: {$this->category}\n";
        echo "Template: {$this->template}\n";
        echo "Directory: {$this->packageDir}\n\n";
        
        // Create directories
        $this->createDirectories();
        
        // Generate files
        $this->generateManifest();
        $this->generateReadme();
        $this->generateChangelog();
        $this->generateLicense();
        $this->createScreenshotsDir();
        
        // Additional files based on template
        if ($this->template === 'standard' || $this->template === 'workflow') {
            $this->createMigrationsDir();
            $this->createModulesDir();
        }
        
        echo "\n\033[32m✓ Package scaffolded successfully!\033[0m\n\n";
        echo "Next steps:\n";
        echo "  1. cd {$this->packageDir}\n";
        echo "  2. Edit manifest.json to define your fields and modules\n";
        echo "  3. Update README.md with package details\n";
        echo "  4. Add screenshots to screenshots/ directory\n";
        echo "  5. Validate with: php ../../cli/pkg-lint.php manifest.json\n";
        echo "  6. Build with: php ../../cli/pkg-build.php .\n\n";
    }
    
    private function createDirectories() {
        $dirs = [
            '',
            '/screenshots',
        ];
        
        if ($this->template === 'standard' || $this->template === 'workflow') {
            $dirs[] = '/migrations';
            $dirs[] = '/migrations/rollback';
            $dirs[] = '/modules';
            $dirs[] = '/seeds';
        }
        
        foreach ($dirs as $dir) {
            $path = $this->packageDir . $dir;
            if (!mkdir($path, 0775, true)) {
                $this->error("Failed to create directory: $path");
            }
            echo "\033[32m✓\033[0m Created: " . str_replace($this->packageDir, '.', $path) . "\n";
        }
    }
    
    private function generateManifest() {
        $displayName = ucwords(str_replace('-', ' ', $this->name));
        $packageId = "com.woodson.{$this->name}";
        $tableName = "{$this->namespace}_record";
        
        $manifest = [
            'schemaVersion' => 1,
            'package' => [
                'id' => $packageId,
                'name' => $this->name,
                'namespace' => $this->namespace,
                'display_name' => $displayName,
                'description' => "Description for {$displayName}",
                'version' => '1.0.0',
                'author' => $this->author,
                'author_email' => $this->email,
                'license' => 'MIT',
                'category' => $this->category,
                'tags' => [],
                'icon' => 'bi-circle',
                'base_url' => "/pkg/{$this->namespace}/",
                'repository' => '',
                'homepage' => '',
                'support' => ''
            ],
            'compatibility' => [
                'hub_version' => '>=1.0.0 <2.0.0',
                'php_version' => '>=8.0',
                'mysql_version' => '>=5.7',
                'tested_up_to' => '1.5.0'
            ],
            'capabilities' => [
                'forms',
                'tables'
            ],
            'db' => [
                'entities' => [
                    [
                        'name' => $tableName,
                        'displayName' => $displayName . ' Record',
                        'fields' => [
                            'id CHAR(26) PRIMARY KEY',
                            'tenant_id CHAR(26) NOT NULL',
                            'title VARCHAR(255) NOT NULL',
                            'description TEXT',
                            'status ENUM(\'draft\', \'submitted\', \'approved\', \'rejected\') DEFAULT \'draft\'',
                            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
                            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                            'created_by CHAR(26)',
                            'updated_by CHAR(26)',
                            'is_deleted BOOLEAN DEFAULT FALSE'
                        ],
                        'indexes' => [
                            'INDEX idx_tenant (tenant_id)',
                            'INDEX idx_status (status, created_at)',
                            'INDEX idx_created (created_at DESC)'
                        ]
                    ]
                ],
                'migrations' => []
            ],
            'modules' => [],
            'fields' => [
                [
                    'name' => 'title',
                    'type' => 'text',
                    'label' => 'Title',
                    'required' => true,
                    'order' => 1,
                    'searchable' => true,
                    'visible_in_list' => true,
                    'validation' => [
                        'minLength' => 3,
                        'maxLength' => 255
                    ]
                ],
                [
                    'name' => 'description',
                    'type' => 'textarea',
                    'label' => 'Description',
                    'required' => false,
                    'order' => 2,
                    'searchable' => true,
                    'visible_in_list' => false,
                    'validation' => [
                        'maxLength' => 5000
                    ]
                ],
                [
                    'name' => 'status',
                    'type' => 'select',
                    'label' => 'Status',
                    'required' => true,
                    'order' => 3,
                    'searchable' => false,
                    'visible_in_list' => true,
                    'default_value' => 'draft',
                    'options' => [
                        ['value' => 'draft', 'label' => 'Draft', 'color' => 'secondary'],
                        ['value' => 'submitted', 'label' => 'Submitted', 'color' => 'info'],
                        ['value' => 'approved', 'label' => 'Approved', 'color' => 'success'],
                        ['value' => 'rejected', 'label' => 'Rejected', 'color' => 'danger']
                    ]
                ]
            ],
            'permissions' => [
                'roles' => [
                    [
                        'key' => "{$this->namespace}_view",
                        'displayName' => 'View Records',
                        'description' => "Can view {$displayName} records"
                    ],
                    [
                        'key' => "{$this->namespace}_manage",
                        'displayName' => 'Manage Records',
                        'description' => "Can create and edit {$displayName} records"
                    ],
                    [
                        'key' => "{$this->namespace}_admin",
                        'displayName' => 'Administrator',
                        'description' => 'Full control over all features'
                    ]
                ],
                'roleMatrix' => [
                    "{$this->namespace}_view" => [
                        'module:table-view:read'
                    ],
                    "{$this->namespace}_manage" => [
                        'module:table-view:read',
                        'module:form:create',
                        'module:form:update'
                    ],
                    "{$this->namespace}_admin" => [
                        'module:*:*'
                    ]
                ],
                'defaultAccess' => [
                    'staff' => ["{$this->namespace}_view"],
                    'admin' => ["{$this->namespace}_manage"],
                    'super_admin' => ["{$this->namespace}_admin"]
                ]
            ],
            'menu_items' => [
                [
                    'label' => 'View Records',
                    'route' => "/pkg/{$this->namespace}/records",
                    'icon' => 'bi-table',
                    'order' => 1,
                    'minimum_role' => "{$this->namespace}_view"
                ]
            ]
        ];
        
        // Add modules based on template
        if ($this->template === 'standard') {
            $manifest['modules'] = [
                [
                    'type' => 'Form',
                    'slug' => 'entry-form',
                    'displayName' => 'Submit Entry',
                    'entity' => $tableName,
                    'route' => "/pkg/{$this->namespace}/submit",
                    'icon' => 'bi-file-earmark-plus',
                    'allowAnonymous' => false,
                    'fields' => [
                        [
                            'key' => 'title',
                            'fieldType' => 'text',
                            'label' => 'Title',
                            'required' => true,
                            'validation' => [
                                'minLength' => 3,
                                'maxLength' => 255
                            ]
                        ],
                        [
                            'key' => 'description',
                            'fieldType' => 'textarea',
                            'label' => 'Description',
                            'required' => false,
                            'rows' => 6
                        ]
                    ],
                    'validation' => [
                        'rateLimit' => [
                            'perUser' => 10,
                            'perMinute' => 5
                        ]
                    ]
                ],
                [
                    'type' => 'TableView',
                    'slug' => 'records-table',
                    'displayName' => 'View Records',
                    'entity' => $tableName,
                    'route' => "/pkg/{$this->namespace}/records",
                    'icon' => 'bi-table',
                    'columns' => [
                        ['key' => 'title', 'label' => 'Title', 'sortable' => true],
                        ['key' => 'status', 'label' => 'Status', 'filterable' => true, 'badge' => true],
                        ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'format' => 'datetime']
                    ],
                    'filters' => [
                        ['key' => 'status', 'type' => 'select', 'label' => 'Status']
                    ],
                    'actions' => [
                        ['key' => 'view', 'label' => 'View', 'icon' => 'bi-eye'],
                        ['key' => 'edit', 'label' => 'Edit', 'icon' => 'bi-pencil', 'permission' => "{$this->namespace}_manage"]
                    ],
                    'defaultSort' => ['field' => 'created_at', 'direction' => 'DESC'],
                    'pagination' => [
                        'enabled' => true,
                        'perPage' => 25
                    ]
                ]
            ];
        }
        
        // Save manifest
        $path = $this->packageDir . '/manifest.json';
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "\033[32m✓\033[0m Created: manifest.json\n";
    }
    
    private function generateReadme() {
        $displayName = ucwords(str_replace('-', ' ', $this->name));
        $version = '1.0.0';
        
        $readme = <<<MD
# {$displayName}

Brief description of what this package does (2-3 sentences).

## Features

- Feature 1
- Feature 2
- Feature 3

## Requirements

- **Hub Version**: >= 1.0.0
- **PHP**: >= 8.0
- **MySQL**: >= 5.7

## Installation

1. Download `{$this->name}_{$version}.hubpkg`
2. Navigate to **Admin → Package Manager**
3. Click **"Upload Package"**
4. Select the `.hubpkg` file
5. Review the compatibility report
6. Click **"Install"**

## Configuration

### Initial Setup

1. Go to **Sections → Section Access**
2. Grant appropriate roles:
   - `{$this->namespace}_view` → Staff, Teachers
   - `{$this->namespace}_manage` → Department Heads
   - `{$this->namespace}_admin` → Administrators

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | Text | Yes | Short title or summary |
| `description` | Textarea | No | Detailed description |
| `status` | Select | Yes | Current status (draft, submitted, etc.) |

## Usage

### For End Users

1. Navigate to the package from the main menu
2. Click "Submit Entry" to create a new record
3. Fill in the required fields
4. Click "Submit"

### For Administrators

1. Navigate to "View Records" to see all submissions
2. Use filters to find specific records
3. Click on a record to view details
4. Use actions to edit or update status

## Permissions

| Role | Access Level | Description |
|------|--------------|-------------|
| `{$this->namespace}_view` | Read-only | Can view records |
| `{$this->namespace}_manage` | Create & Edit | Can submit and edit records |
| `{$this->namespace}_admin` | Full Control | All permissions including delete |

## Screenshots

*Add screenshots here after taking them*

![Main View](screenshots/main-view.png)
![Admin Panel](screenshots/admin-view.png)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Support

- **Issues**: [GitHub Issues](https://github.com/your-org/your-repo/issues)
- **Email**: {$this->email}
- **Documentation**: [Package Specification](../../docs/PACKAGE_SPECIFICATION_V2.md)

## License

MIT License - See [LICENSE](LICENSE) for details.

## Credits

Developed by {$this->author}
MD;
        
        file_put_contents($this->packageDir . '/README.md', $readme);
        echo "\033[32m✓\033[0m Created: README.md\n";
    }
    
    private function generateChangelog() {
        $changelog = <<<MD
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- List planned features here

## [1.0.0] - {$this->getDate()}

### Added
- Initial release
- Basic data entry form
- Record viewing table
- Permission system
- Field validation

### Security
- Input validation on all fields
- Rate limiting on form submissions
- Role-based access control

---

## Version History

- [1.0.0] - Initial release
MD;
        
        file_put_contents($this->packageDir . '/CHANGELOG.md', $changelog);
        echo "\033[32m✓\033[0m Created: CHANGELOG.md\n";
    }
    
    private function generateLicense() {
        $year = date('Y');
        
        $license = <<<LICENSE
MIT License

Copyright (c) {$year} {$this->author}

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
LICENSE;
        
        file_put_contents($this->packageDir . '/LICENSE', $license);
        echo "\033[32m✓\033[0m Created: LICENSE\n";
    }
    
    private function createScreenshotsDir() {
        $readme = <<<TXT
# Screenshots

Add at least 2 screenshots here:

1. **main-view.png** - Main user interface
2. **admin-view.png** - Administrative view

Requirements:
- Format: PNG or JPEG
- Resolution: 1280×720 or higher
- Max size: 500KB each
- Show actual data (not empty forms)

You can use browser dev tools or screenshot tools to capture these.
TXT;
        
        file_put_contents($this->packageDir . '/screenshots/README.txt', $readme);
    }
    
    private function createMigrationsDir() {
        $readme = <<<TXT
# Migrations

Place database migration files here for version upgrades.

Naming convention: NNNN_description.sql

Example:
- 0001_init.sql (created automatically from manifest)
- 0002_add_priority_field.sql
- 0003_create_comments_table.sql

Also create rollback scripts in rollback/ directory:
- 0002_rollback.sql
- 0003_rollback.sql

These are executed when upgrading or downgrading package versions.
TXT;
        
        file_put_contents($this->packageDir . '/migrations/README.txt', $readme);
    }
    
    private function createModulesDir() {
        $readme = <<<TXT
# Modules

Place additional module definition files here (optional).

Modules can also be defined inline in manifest.json.
Separate files are useful for complex module configurations.

Example:
- report-form.module.json
- analytics-dashboard.module.json
- workflow.module.json

Each file should contain a single module definition object.
TXT;
        
        file_put_contents($this->packageDir . '/modules/README.txt', $readme);
    }
    
    private function getDate() {
        return date('Y-m-d');
    }
    
    private function error($message) {
        echo "\033[31mError: $message\033[0m\n";
        exit(1);
    }
}

// Parse arguments
$options = getopt('', ['name:', 'namespace:', 'category:', 'author:', 'email:', 'template:']);

if (empty($options)) {
    echo "Package Scaffolder - Generate new package templates\n\n";
    echo "Usage:\n";
    echo "  php cli/pkg-scaffold.php --name=my-package --namespace=mp\n\n";
    echo "Required Options:\n";
    echo "  --name         Package name (kebab-case)\n";
    echo "  --namespace    2-5 char prefix for DB tables\n\n";
    echo "Optional:\n";
    echo "  --category     Category (education, maintenance, etc.)\n";
    echo "  --author       Author name (default: Woodson ISD)\n";
    echo "  --email        Author email\n";
    echo "  --template     Template: simple|standard|workflow (default: standard)\n\n";
    echo "Example:\n";
    echo "  php cli/pkg-scaffold.php --name=bullying-report --namespace=br --category=education\n\n";
    exit(1);
}

// Run scaffolder
$scaffolder = new PackageScaffolder($options);
$scaffolder->scaffold();
