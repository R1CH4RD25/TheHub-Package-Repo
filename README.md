# The Hub

A modular, secure web application platform for managing school district operations including vehicle maintenance, fuel tracking, bullying reports, and more.

## 📝 Recent Updates

**Latest Development Session**: February 12, 2026 - Package Rendering Engine & Mobile UX ✅
- ✅ Fixed Student Directory package rendering (12+ bugs across 11 files)
- ✅ Created `IconMapper.php` with 80+ Lucide → FontAwesome mappings
- ✅ Mobile responsive tables with `hide-mobile`/`hide-tablet` per column
- ✅ Desktop layout: 4-column dashboard grid, full-width tables, inline filters
- ✅ Collapsible hamburger nav menu on mobile (was broken — links never hid)
- ✅ Compact mobile filters (eliminated dead space between search and dropdowns)
- ✅ Default sort: grade (PK, KG, 1, 2, 3...) then name, with custom SQL CASE
- ✅ Password show/hide with touch event support for mobile
- ✅ Grade-aware badge colors, masked value toggle, column width percentages
- ✅ Stripped site-specific Google Group mappings from distributable `.hubpkg`
- ✅ Updated CONTRIBUTING.md with full package.json v3.0.0 schema reference
- ✅ Synced both repos: TheHub (laravel-migration) and TheHub-Package-Repo (main)
- 📋 Full handoff: [HANDOFF_2026-02-12_PACKAGE_MOBILE.md](HANDOFF_2026-02-12_PACKAGE_MOBILE.md)
- 🔜 **Next session**: Management Console redesign — surface packages properly (currently admin-style)

**Previous Development Session**: February 11, 2026 - Sprint 0: Platform Contract Complete ✅
- ✅ Built Layer 3 Package Architecture (9 core components, ~2,500+ lines of code)
- ✅ Package validation system with JSON schema enforcement (350+ line schema)
- ✅ Handler registry with interface whitelisting and security blocking
- ✅ PolicyEngine v0 with RBAC, role hierarchy, and wildcard permissions
- ✅ Enforcement pipelines: 8-step query router, 10-step mutation router
- ✅ Comprehensive test suite created (37/37 tests passing at 100%)
- ✅ Enhanced audit system with standardized taxonomy (`package.<id>.<type>.<name>`)
- ✅ P0 Security Hardening: UUID v4 correlation IDs, proxy-aware IP capture, sanitized error traces
- 📋 Created [AUDIT_SYSTEM_CHANGELOG.md](AUDIT_SYSTEM_CHANGELOG.md) - single source of truth for audit evolution
- 📚 Full handoff documentation: [HANDOFF_2026-02-11_SPRINT0_SECURITY.md](HANDOFF_2026-02-11_SPRINT0_SECURITY.md)
- 🔐 Security features: SQL injection prevention, XSS protection, CSRF validation, secret protection
- 📊 Next: Sprint 1 - UI Components (catch-all routing, component renderers, package landing page)

**Previous Development Session**: February 10, 2026 - Navigation Icon System & Header Layout
- Separated icon systems: simple icons for header navigation, emojis for hub content cards
- Fixed persistent CSS gap issue (Bootstrap CDN override)
- Added footer horizontal padding and optimized header alignment
- Comprehensive handoff documentation: [HANDOFF_2026-02-10_NAVIGATION_ICONS.md](HANDOFF_2026-02-10_NAVIGATION_ICONS.md)

**Previous Development Log**: [October 29, 2025](DEVELOPMENT_LOG_2025-10-29.md)
- Collapsible sidebar menu groups with accordion behavior
- User profile dropdown with contact preferences
- FontAwesome icon integration
- Section configuration tab fixes
- CSS and z-index cleanup

## ✨ Features

- **🔐 Flexible Authentication**
  - Google OAuth 2.0 (with optional Google Groups integration)
  - Microsoft OAuth / Azure AD
  - Local username/password authentication
  - Domain restrictions and auto-approval workflows

- **👥 Advanced Role Management**
  - Super Admin: Full system control with "View As" capability
  - Custom global roles with fine-grained permissions
  - Section-based access control
  - Module-level permissions
  - Role cascading and dependencies

- **📦 Package System (Layer 3)**
  - JSON-driven `.hubpkg` packages — no custom templates needed
  - Component renderers: Dashboard, Table, Filters, Detail, Form
  - Responsive column visibility (`hide-mobile`, `hide-tablet`) per package
  - Icon mapping (Lucide → FontAwesome) with 80+ built-in mappings
  - Masked fields (passwords/SSNs) with show/hide toggle
  - Grade-aware badge coloring, sortable columns, pagination
  - Bulk actions, row actions, mutation confirmation modals
  - RBAC policy engine with role hierarchy enforcement
  - See **[CONTRIBUTING.md](CONTRIBUTING.md)** for the full schema reference

- **🎨 Complete Theme System**
  - 45+ customizable color settings
  - Dark mode support
  - Role badge customization
  - Branding and logo upload
  - Real-time CSS generation

- **📊 Admin Dashboard**
  - User management with advanced filtering
  - Invitation system
  - Activity audit logs
  - Site settings management
  - Data export (Excel/CSV)

- **📱 Mobile Responsive**
  - Works on all devices
  - Touch-friendly interface
  - Optimized for non-tech users

## 🚀 Technology Stack

- **Backend**: PHP 8.0+ (PSR-4 autoloading)
- **Database**: MariaDB 10.5+ / MySQL 8.0+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Authentication**: OAuth 2.0 (Google, Microsoft)
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Dependencies**: Composer (PHPMailer, PhpSpreadsheet, Google API Client)

## 📋 Quick Start

See **[QUICKSTART.md](QUICKSTART.md)** for detailed installation instructions.

### Prerequisites
- PHP 8.0+ with extensions (mysql, mbstring, xml, curl, zip, gd, intl, bcmath)
- MariaDB 10.5+ or MySQL 8.0+
- Apache 2.4+ or Nginx 1.18+
- Composer
- SSL certificate (Let's Encrypt recommended)

### Quick Install (Ubuntu/Debian)
```bash
# Clone the repository
git clone https://github.com/yourusername/thehub.git
cd thehub

# Install system dependencies (automated)
sudo bash install-packages.sh

# Check dependencies and auto-fix any issues
php cli/check-dependencies.php

# Install PHP dependencies
composer install

# Configure environment
cp .env.example .env
nano .env  # Edit with your values

# Create database
sudo mysql -u root -p
> CREATE DATABASE thehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> CREATE USER 'thehub_user'@'localhost' IDENTIFIED BY 'your_password';
> GRANT ALL PRIVILEGES ON thehub.* TO 'thehub_user'@'localhost';
> FLUSH PRIVILEGES;
> EXIT;

# Run migrations
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php

# Create first admin user
php cli/setup.php

# Configure Apache (or Nginx)
sudo cp apache/hub.example.com.conf /etc/apache2/sites-available/
# Edit server name, paths, then enable
sudo a2ensite hub.example.com
sudo systemctl reload apache2

# Set up SSL
sudo certbot --apache -d hub.example.com

# Done! Visit https://hub.example.com
```

## 📚 Documentation

### Getting Started
- **[QUICKSTART.md](QUICKSTART.md)** - Step-by-step installation guide
- **[REQUIREMENTS.md](REQUIREMENTS.md)** - Complete system requirements
- **[INSTALLATION_DEFAULTS.md](INSTALLATION_DEFAULTS.md)** - Default values and quick reference
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Production deployment guide

### Authentication & Access
- **[MICROSOFT_OAUTH.md](MICROSOFT_OAUTH.md)** - Microsoft/Azure AD setup
- **[docs/GOOGLE_GROUPS_SETUP.md](docs/GOOGLE_GROUPS_SETUP.md)** - Google Groups integration
- **[docs/INVITATION_SYSTEM.md](docs/INVITATION_SYSTEM.md)** - User invitation workflow
- **[docs/ROLES_DOCUMENTATION.md](docs/ROLES_DOCUMENTATION.md)** - Role system overview
- **[docs/ROLE_PERMISSIONS.md](docs/ROLE_PERMISSIONS.md)** - Permission reference
- **[docs/SECTION_ACCESS.md](docs/SECTION_ACCESS.md)** - Section-based access control
- **[docs/ADDING_NEW_ROLES.md](docs/ADDING_NEW_ROLES.md)** - How to add custom roles

### Features & Customization
- **[docs/MODULAR_ARCHITECTURE.md](docs/MODULAR_ARCHITECTURE.md)** - Module system design
- **[docs/THEME_MANAGEMENT.md](docs/THEME_MANAGEMENT.md)** - Theme customization guide
- **[docs/COLOR_SCHEME_QUICKSTART.md](docs/COLOR_SCHEME_QUICKSTART.md)** - Color customization
- **[docs/CSS_BUILD_QUICKSTART.md](docs/CSS_BUILD_QUICKSTART.md)** - CSS build system
- **[docs/CASCADING_DEPENDENCIES.md](docs/CASCADING_DEPENDENCIES.md)** - Role dependencies feature
- **[docs/CASCADING_DEPENDENCIES_QUICKREF.md](docs/CASCADING_DEPENDENCIES_QUICKREF.md)** - Quick reference

### Administration
- **[docs/AUDIT_LOGGING.md](docs/AUDIT_LOGGING.md)** - Activity log system
- **[docs/ADVANCED_USER_FILTERING.md](docs/ADVANCED_USER_FILTERING.md)** - User management filters

### Developer Resources
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Package JSON schema, icons, responsive rules, component reference
- **[.github/copilot-instructions.md](.github/copilot-instructions.md)** - AI development guidelines
- **[database/schema.sql](database/schema.sql)** - Core database schema
- **[database/modules-schema.sql](database/modules-schema.sql)** - Module schema
- **[database/sections-schema.sql](database/sections-schema.sql)** - Sections schema

### Package System
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Complete guide to building `.hubpkg` packages
- **[PACKAGE_ARCHITECTURE_SPEC.md](PACKAGE_ARCHITECTURE_SPEC.md)** - Deep architecture spec
- **[src/Package/IconMapper.php](src/Package/IconMapper.php)** - Lucide → FontAwesome icon mapping reference

## 🎯 Usage

### For End Users

1. Navigate to your Hub URL (e.g., `https://hub.example.com`)
2. Click "Sign in with Google" or "Sign in with Microsoft" (or use local credentials)
3. First-time users: Accept invitation or request access
4. Access available modules based on your role

### For Administrators

- **Access admin dashboard**: Click your profile → Admin Panel
- **Manage users**: Users tab → Invite, approve, or remove users
- **Configure sections**: Sections tab → Control visibility by role
- **Customize theme**: Site Settings → Branding & Colors
- **View audit logs**: Activity Logs tab → Track all system changes
- **Export data**: Admin Dashboard → Filter → Export to Excel/CSV

### For Developers

```bash
# Run dependency check
php cli/check-dependencies.php

# Run migrations
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php

# Create additional admin users
php cli/setup.php

# Local development server
cd public && php -S localhost:8000
```

## 🏗️ Project Structure

```
thehub/
├── .env.example          # Environment configuration template
├── .github/
│   └── copilot-instructions.md
├── apache/               # Apache virtual host configs
├── cli/                  # Command-line scripts
│   ├── check-dependencies.php
│   ├── migrate.php
│   ├── migrate-modules.php
│   ├── migrate-sections.php
│   └── setup.php
├── composer.json         # PHP dependencies
├── config/               # OAuth service account JSON files
├── database/
│   ├── schema.sql
│   ├── modules-schema.sql
│   ├── sections-schema.sql
│   └── migrations/       # Database migrations
├── docs/                 # Documentation
├── logs/                 # Application logs
├── packages/             # .hubpkg package sources
│   └── [category]/
│       └── [package-id]/
│           ├── package.json          # Package manifest (schema, pages, components)
│           ├── *Handler.php          # Data query/mutation handlers
│           └── README.md
├── public/               # Web root
│   ├── index.php
│   ├── login.php
│   ├── hub.php           # Hub landing (section cards)
│   ├── p/index.php       # Package page renderer (routes /p/*)
│   ├── admin/
│   ├── api/
│   ├── assets/
│   │   ├── css/
│   │   │   ├── package-components.css  # Table, form, filter, dashboard styles
│   │   │   └── hub/                    # Hub landing page styles
│   │   └── js/
│   │       ├── package-table.js        # Table sort, filter, pagination, masked toggle
│   │       └── package-dashboard.js    # Dashboard chart rendering
│   ├── modules/
│   └── ...
├── sessions/             # PHP sessions
├── src/                  # PHP classes (PSR-4: Hub\*)
│   ├── Auth.php
│   ├── Database.php
│   ├── Helpers.php
│   ├── User.php
│   ├── Module.php
│   ├── Theme.php
│   └── Package/          # Package rendering engine
│       ├── PageRouter.php            # Routes /p/* requests to components
│       ├── ComponentRegistry.php     # Maps component types to renderers
│       ├── IconMapper.php            # Lucide → FontAwesome mapping (80+ icons)
│       ├── PolicyEngine.php          # RBAC enforcement
│       ├── Contracts/
│       │   └── ComponentRendererInterface.php
│       ├── Renderers/
│       │   ├── DashboardRenderer.php # KPI cards grid
│       │   ├── TableRenderer.php     # Data tables with responsive columns
│       │   ├── FilterRenderer.php    # Search & filter bars
│       │   ├── DetailRenderer.php    # Record detail views
│       │   └── FormRenderer.php      # Create/edit forms
│       └── [PackageName]/
│           └── *Handler.php          # Per-package data handlers
├── temp/                 # Temporary files
└── uploads/              # User uploads
```

## 🔒 Security

- **Authentication**: OAuth 2.0 (Google, Microsoft) + optional local auth
- **Authorization**: Role-based access control (RBAC) with section permissions
- **Domain Restrictions**: Optional email domain whitelist
- **HTTPS Required**: Enforced via Apache/Nginx config
- **SQL Injection Prevention**: PDO prepared statements throughout
- **CSRF Protection**: Tokens on all state-changing operations
- **Password Security**: Bcrypt hashing for local accounts
- **Activity Logging**: All admin actions logged with IP addresses
- **Session Security**: Secure, HTTPOnly cookies with configurable timeout

## 🛠️ Maintenance

### Regular Tasks
- **Backup database** regularly (recommended: daily)
- **Monitor logs** at `logs/php-errors.log`
- **Review audit logs** in Admin Panel
- **Update dependencies**: `composer update`
- **Clear old sessions**: `rm sessions/sess_*` (optional)

### Troubleshooting
- **Can't login?** Check `.env` OAuth credentials and redirect URIs
- **Database errors?** Verify credentials in `.env` and run migrations
- **Permission denied?** Check file permissions on `logs/`, `sessions/`, `temp/`, `uploads/`
- **Theme not loading?** Clear browser cache and check `public/assets/css/generated/`

See **[REQUIREMENTS.md](REQUIREMENTS.md)** for detailed troubleshooting guide.

## 🗺️ Roadmap

### v1.0 (Current) - Core Platform ✅
- Modular architecture with packages
- Authentication (Google, Microsoft, Local)
- Role-based access control
- Theme system with 45+ settings
- Admin dashboard with user management
- Audit logging
- Section-based access

### v2.0 (Planned) - Add-ons & Component System
**Goal**: Make package building easier for end users with pre-built, reusable components

#### Add-ons System
- **Component Marketplace**: Browse and install pre-built add-ons
- **Core Add-ons** (hardcoded, maintained by core team):
  - **To-Do Lists**: Task management with priorities and due dates
  - **Checklists**: Simple checkbox lists with persistence
  - **Form Builder**: Drag-drop form creation with validation
  - **Data Tables**: Sortable, filterable, exportable tables
  - **File Uploaders**: Drag-drop file handling with previews
  - **Calendar/Scheduler**: Event management widgets
  - **Charts & Graphs**: Data visualization components
  - **Comment Systems**: Threaded discussions
  - **Rich Text Editor**: WYSIWYG content editing
  - **Image Galleries**: Photo management with lightbox
  - **Search Filters**: Advanced filtering UI components
  - **Notifications**: Toast/alert system
  - **Progress Trackers**: Multi-step workflows
  - **User Selectors**: Advanced user/group pickers

#### Technical Architecture
- Add-ons stored in `src/Addons/` or `public/addons/`
- Simple API for package developers to integrate add-ons
- Minimal configuration required
- Compose complex packages from simple building blocks
- Version control and dependency management

#### Benefits
- Faster package development
- Consistent UX across packages
- Reduced code duplication
- Lower barrier to entry for new developers
- Easier maintenance and updates

### Future Considerations
- Community add-on marketplace
- Third-party add-on support
- Add-on SDK and documentation
- Visual package builder UI

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is proprietary software developed for school district use.

## 👨‍💻 Support

For questions, issues, or feature requests:
- Open an issue on GitHub
- Contact: your-email@example.com

## 🎓 Credits

Developed for educational institutions to streamline operations and improve efficiency.

---

**Version**: 2.0
**Last Updated**: February 2026
