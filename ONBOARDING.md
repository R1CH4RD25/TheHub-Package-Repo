# 🚀 New Engineer Onboarding - Quick Start Guide

**Welcome to The Hub!** This guide will get you up to speed quickly.

---

## 📚 Essential Reading (Priority Order)

### 1. Start Here
- **[README.md](README.md)** - Project overview, features, installation
- **[STATUS.md](STATUS.md)** - Current system status, active work, priorities
- **[.github/copilot-instructions.md](.github/copilot-instructions.md)** - AI agent guide, project orientation

### 2. Recent Work (Read These!)
- **[AUDIT_SYSTEM_CHANGELOG.md](AUDIT_SYSTEM_CHANGELOG.md)** ⭐ **NEW!** - Single source of truth for audit system evolution, Sprint 0 enhancements
- **[HANDOFF_2026-02-10_NAVIGATION_ICONS.md](HANDOFF_2026-02-10_NAVIGATION_ICONS.md)** - Latest session work (navigation icon system, header layout, CSS fixes)
- **[CSS_ARCHITECTURE.md](CSS_ARCHITECTURE.md)** - CSS file structure, build system, gotchas

### 3. Architecture & Design
- **[ENTERPRISE_ADMIN_DESIGN_SYSTEM.md](ENTERPRISE_ADMIN_DESIGN_SYSTEM.md)** - Design system guidelines
- **[MODULAR_ARCHITECTURE.md](docs/MODULAR_ARCHITECTURE.md)** - Module system architecture
- **[PACKAGE_SYSTEM_ARCHITECTURE.md](PACKAGE_SYSTEM_ARCHITECTURE.md)** - Package management system

### 4. Security & Authentication
- **[AUTHENTICATION_SETTINGS_INTEGRATION.md](AUTHENTICATION_SETTINGS_INTEGRATION.md)** - Auth system integration
- **[OAUTH_PHASE_3_COMPLETE.md](OAUTH_PHASE_3_COMPLETE.md)** - Google + Microsoft OAuth
- **[docs/INVITATION_SYSTEM.md](docs/INVITATION_SYSTEM.md)** - Invitation workflow

### 5. Development Workflow
- **[INSTALLATION.md](INSTALLATION.md)** - Setup and installation guide
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Deployment procedures

---

## 🏗️ Project Structure

```
/var/www/woodson/thehub/
├── public/               # Web root (index.php, assets)
│   ├── assets/
│   │   ├── css/         # CSS source files + bundles
│   │   └── js/          # JavaScript files
│   ├── admin/           # Admin dashboard pages
│   ├── api/             # API endpoints
│   └── management/      # Management console
├── src/                 # PSR-4 Hub\* classes
│   ├── Auth.php         # Authentication system
│   ├── Database.php     # Database singleton
│   ├── Layout.php       # Header/footer rendering
│   └── ...
├── database/            # Schema SQL files
├── tests/               # PHPUnit tests
└── docs/                # Comprehensive documentation
```

---

## 🚦 Development Workflow

### 1. Environment Setup
```bash
# Clone repo
git clone https://github.com/R1CH4RD25/TheHub.git
cd TheHub

# Install dependencies
composer install

# Configure environment
cp .env.example .env
vim .env  # Set database credentials, OAuth keys

# Run migrations
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php
```

### 2. Making Changes

#### CSS Changes
```bash
# Edit source files in public/assets/css/shared/
vim public/assets/css/shared/header.css

# Rebuild bundles (REQUIRED!)
bash build-css.sh

# Verify bundles updated
ls -lh public/assets/css/*-bundle.css
```

#### PHP Changes
```bash
# Edit source files in src/ or public/
vim src/Layout.php

# No build step needed for PHP
```

#### Database Changes
```bash
# Update schema file
vim database/schema.sql

# Run migration
php cli/migrate.php
```

### 3. Testing
```bash
# Run all tests
vendor/bin/phpunit

# Run specific test
vendor/bin/phpunit tests/AuthTest.php

# Check coverage
vendor/bin/phpunit --coverage-html coverage/
```

### 4. Commit & Push
```bash
git add -A
git commit -m "✨ Description of changes"
git push origin laravel-migration
```

---

## 🎯 Current Priorities (Feb 2026)

### ✅ Recently Completed
- **Sprint 0: Platform Contract** (Feb 11, 2026) - Layer 3 Package Architecture complete
  - 9 core components built: PackageValidator, HandlerRegistry, PolicyEngine, enforcement pipelines
  - 37/37 tests passing (100% coverage for Sprint 0)
  - Audit system enhanced with standardized taxonomy
  - AUDIT_SYSTEM_CHANGELOG.md created as single source of truth

### High Priority
1. **Sprint 1: UI Components** ⭐ NEXT UP - Package rendering system
   - Catch-all routing: `/p/{packageId}/{pageId}`
   - Component renderers (table, form, detail views)
   - Package landing page
   - Basic layout system
2. **Test Suite Improvements** - Working on test coverage and stability
3. **Auth System Testing** - Improving authentication test coverage
4. **Documentation Updates** - Keep docs in sync with code changes

### Medium Priority
1. **Sprint 2: Advanced Security** - PolicyEngine v1 enhancements
   - Scope filters (row-level security)
   - Field masking (data classification)
   - Rate limiting
2. Package system enhancements
3. Security hardening (CSP, OAuth)
4. Mobile responsiveness optimization

### Low Priority
1. **Sprint 3 & 4** - Forms, exports, Student Directory pilot package
2. Icon picker UI for admin settings
3. Frontend build system modernization
4. Component library documentation

---

## ⚠️ Common Gotchas

### 1. Duplicate CSS Files
**Problem:** `/public/assets/css/header.css` AND `/shared/header.css` both exist

**Solution:** Edit BOTH files identically until consolidation complete

**Details:** [CSS_ARCHITECTURE.md](CSS_ARCHITECTURE.md)

### 2. CSS Changes Not Appearing
**Problem:** Edited CSS but browser shows old styles

**Solution:**
1. Rebuild bundles: `bash build-css.sh`
2. Hard refresh: `Ctrl+Shift+R`
3. Verify editing source file, not bundle

### 3. Bootstrap Override
**Problem:** Bootstrap CSS from CDN overrides custom styles

**Solution:** Use `!important` flag strategically
```css
.nav-links { gap: 0 !important; }
```

### 4. Icon System Architecture
**Two Icon Systems:**
- **Header Navigation:** Simple icons (`bi-*` Bootstrap Icons, `fas fa-*` FontAwesome)
- **Hub Content Cards:** Colorful emojis (🚙, 🚗, ⛽, 🔧)

**Solution:** Use correct class syntax
- ✅ `fas fa-shield-alt` (FontAwesome 6)
- ✅ `bi-kanban` (Bootstrap Icons)
- ❌ `fa-shield-alt` (missing prefix)

**Details:** See [HANDOFF_2026-02-10_NAVIGATION_ICONS.md](HANDOFF_2026-02-10_NAVIGATION_ICONS.md)

### 5. Development Environment
**Current Setup:** Using production database `woodson_hub` for development/testing

**Note:** System is not live yet, so all work uses the main database

**Future:** Separate test database will be configured when moving to production

---

## 🔧 Essential Commands

### Development Server
```bash
cd public && php -S localhost:8000
```

### CSS Build
```bash
bash build-css.sh
```

### Run Migrations
```bash
php cli/migrate.php           # Main schema
php cli/migrate-modules.php   # Module schema
php cli/migrate-sections.php  # Section schema
```

### Run Tests
```bash
vendor/bin/phpunit                    # All tests
vendor/bin/phpunit --filter AuthTest  # Specific test
vendor/bin/phpunit --testdox          # Readable output
```

### Git Snapshots
```bash
# View snapshots
git log --oneline | grep snapshot

# Restore snapshot
git checkout snapshot-20260210-221930
```

### Database Access
```bash
# MySQL CLI
mysql -u WISDAdmin -p woodson_hub

# Or via PHP helper
php -a
require 'src/bootstrap.php';
$db = Hub\Database::getInstance();

# Quick table inspection
mysql -u WISDAdmin -p woodson_hub -e "SHOW TABLES;"
```

---

## 📞 Key Contacts & Resources

### Documentation Hub
- **Main Docs:** `/var/www/woodson/thehub/docs/`
- **Architecture Diagrams:** See ENTERPRISE_ADMIN_DESIGN_SYSTEM.md
- **API Reference:** See individual API endpoint files in `public/api/`

### Git
- **Branch:** laravel-migration (active development)
- **Default Branch:** v1.1 (production)
- **Remote:** https://github.com/R1CH4RD25/TheHub.git

### Database
- **Database:** woodson_hub (development/production)
- **Server:** localhost
- **Note:** Using single database for all work until system goes live

### Environment
- **Laravel:** 11.47.0
- **PHP:** 8.3.6
- **MySQL:** 8.x
- **Composer:** 2.x

---

## 🐛 Troubleshooting

### "Page Not Loading"
1. Check `.env` database credentials
2. Verify migrations ran: `php cli/migrate.php`
3. Check Apache/PHP logs: `tail -f logs/php-errors.log`
4. Ensure session directory writable: `chmod 755 sessions/`

### "OAuth Not Working"
1. Verify `.env` OAuth credentials set
2. Check callback URLs match Google/Microsoft console
3. Ensure HTTPS in production (OAuth requires it)
4. Review [OAUTH_PHASE_3_COMPLETE.md](OAUTH_PHASE_3_COMPLETE.md)

### "Tests Failing"
1. Ensure database is properly configured in `.env`
2. Run migrations: `php cli/migrate.php`
3. Review [STATUS.md](STATUS.md) for known test issues
4. Check logs: `tail -f logs/php-errors.log`

### "CSS Not Updating"
1. Rebuild: `bash build-css.sh`
2. Edit source files, not bundles
3. Hard refresh browser: `Ctrl+Shift+R`
4. See [CSS_ARCHITECTURE.md](CSS_ARCHITECTURE.md)

---

## 🎓 Learning Path

### Week 1: Orientation
- [ ] Read README.md and STATUS.md
- [ ] Read latest handoff: HANDOFF_2026-02-10_NAVIGATION_ICONS.md
- [ ] Set up local environment
- [ ] Run test suite, review failures
- [ ] Browse codebase, understand structure

### Week 2: Small Contributions
- [ ] Fix a small CSS issue
- [ ] Write a test case
- [ ] Review CSS_ARCHITECTURE.md
- [ ] Update documentation
- [ ] Fix a failing test

### Week 3: Feature Work
- [ ] Pick an issue from STATUS.md backlog
- [ ] Implement with tests
- [ ] Review enterprise design system
- [ ] Submit PR with documentation

### Week 4: Architecture Deep Dive
- [ ] Read MODULAR_ARCHITECTURE.md
- [ ] Read PACKAGE_SYSTEM_ARCHITECTURE.md
- [ ] Understand auth system flow
- [ ] Review security documentation

---

## 📋 Onboarding Checklist

### Access & Setup
- [ ] Git repo cloned
- [ ] Composer dependencies installed
- [ ] .env configured
- [ ] Database migrations run
- [ ] Development server running
- [ ] Test suite runs (even if some fail)

### Essential Reading
- [ ] README.md
- [ ] STATUS.md
- [ ] AUDIT_SYSTEM_CHANGELOG.md ⭐ NEW: Single source of truth for audit system
- [ ] HANDOFF_2026-02-10_NAVIGATION_ICONS.md
- [ ] CSS_ARCHITECTURE.md
- [ ] .github/copilot-instructions.md

### First Tasks
- [ ] Build CSS bundles successfully
- [ ] Run one test successfully
- [ ] Make a small CSS change
- [ ] Commit and push a documentation update
- [ ] Review current test failures

### Architecture Understanding
- [ ] Understand Layout.php header/footer rendering
- [ ] Understand Auth.php authentication flow
- [ ] Understand CSS bundle build process
- [ ] Understand module system architecture
- [ ] Understand section-based access control

---

## 🚀 Quick Wins for New Engineers

These are good starter tasks to get familiar with the codebase:

1. **Update Documentation** (Documentation)
   - Fix outdated information
   - Add missing code examples
   - Clarify confusing sections
   - Keep STATUS.md current

2. **Write Tests** (Test Coverage)
   - Pick a class needing tests
   - Write unit tests
   - Improve overall coverage
   - Document test patterns

3. **CSS Improvements** (User Interface)
   - Fix responsive issues
   - Improve mobile experience
   - Optimize bundle sizes
   - Document CSS architecture

4. **Package System Features** (New Features)
   - Enhance package discovery
   - Improve configuration UI
   - Add installation helpers
   - Update package docs

5. **Security Enhancements** (Security)
   - Review OAuth flows
   - Improve CSP policies
   - Audit session handling
   - Document security patterns

---

**Welcome aboard! 🎉**

Questions? Check STATUS.md for current priorities or review the comprehensive handoff documentation.

Remember: When in doubt, read the code, check the docs, and ask questions!
