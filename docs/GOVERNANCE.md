# The Hub - Development Governance & Standards

**Last Updated:** February 10, 2026  
**Purpose:** Define required artifacts, review gates, and development standards  
**Enforcement:** Manual (automation planned)

---

## 🎯 Overview

This document defines the "rules of the road" for The Hub development:
- What artifacts are required per change
- When documentation must be updated
- How to ensure traceability
- Definition of done criteria

**Philosophy:** Documentation is not overhead—it's how we make the system auditable, maintainable, and safe to extend.

---

## 📜 Required Artifacts Per Change

### Every Code Change

All changes must include:

1. ✅ **Git Commit**
   - Emoji prefix (see Commit Standards below)
   - Clear description of what changed
   - Link to issue/feature if applicable

2. ✅ **STATUS.md Update**
   - Move task from "Next" to "Now" when starting
   - Move task to "Done" when complete
   - Update "Change History" section with date and summary
   - Add to blockers if you discover issues

### Database Changes

If you touch `database/` or run migrations:

3. ✅ **Schema Update**
   - Update authoritative schema file:
     - `database/complete-schema.sql` for core tables
     - `database/modules-schema.sql` for module tables
     - `database/sections-schema.sql` for section tables
   - Create migration file in `database/migrations/`
   - Update `docs/DATABASE_COLUMN_REFERENCE.md` if needed

4. ✅ **Migration Documentation**
   - Add entry to migration log (or create one)
   - Document rollback procedure
   - Note data impact (safe to rerun? destructive?)

### Security/Auth/Permissions Changes

If you touch authentication, roles, or permissions:

5. ✅ **Security Documentation**
   - Update `docs/SECURITY.md` if auth flow changes
   - Update `docs/ROLE_PERMISSIONS.md` if roles/permissions change
   - Update `docs/AUDIT_LOGGING.md` if new auditable events added
   - Update `docs/INVITATION_SYSTEM.md` if invitation flow changes

6. ✅ **Security Testing**
   - Add/update security tests in `tests/Security/`
   - Verify CSRF protection on mutation endpoints
   - Verify permission checks on access-controlled features
   - Verify audit logging on data mutations

### User-Facing Changes

If users see or experience something different:

7. ✅ **CHANGELOG.md Entry**
   - Add to CHANGELOG.md with category:
     - Added
     - Changed
     - Fixed
     - Security
     - Performance
   - Use clear, user-friendly language
   - Include screenshots if UI change

8. ✅ **User Documentation**
   - Update relevant docs in `/docs/` if workflow changes
   - Update `docs/MANAGEMENT_QUICK_START.md` for Management features
   - Update `docs/COMMAND_CENTER_ARCHITECTURE.md` for admin features

### Package System Changes

If you touch packages, modules, or capabilities:

9. ✅ **Package Documentation**
   - Update `docs/PACKAGE_SPECIFICATION_V2.md` if spec changes
   - Update `docs/PACKAGE_CREATION_GUIDE.md` if workflow changes
   - Update `docs/MODULE_CATALOG_V2.md` if adding/removing packages
   - Update `docs/PACKAGE_PERMISSIONS_QUICKREF.md` if permissions change

### API Endpoint Changes

If you create or modify API endpoints:

10. ✅ **API Documentation**
    - Document endpoint in relevant technical doc
    - Specify request method, parameters, response format
    - Document error conditions
    - Include example request/response

### CSS/Design Changes

If you modify styles or design system:

11. ✅ **Design Documentation**
    - Update `docs/CSS_ARCHITECTURE.md` if architecture changes
    - Update `docs/THEME_MANAGEMENT.md` if theme system changes
    - Rebuild CSS bundles: `./build-css-production.sh`
    - Test in both admin and management contexts

---

## 🔍 Traceability Requirements

Every significant feature must have a **traceability stub** documenting:

### Traceability Template

Create a section in the relevant doc (or in `docs/features/<feature-name>.md`):

```markdown
## Feature: <Feature Name>

**Status:** <Development / Testing / Complete>  
**Owner:** <Name>  
**Date:** <YYYY-MM-DD>

### Outcome / Workflow
What user problem does this solve? What workflow does it enable?

### Screens Affected
- `/public/path/to/file.php` - What changed?
- `/public/another/file.php` - What changed?

### API Endpoints Affected
- `POST /api/endpoint.php` - What does it do?
- `GET /api/other.php` - What does it return?

### Database Tables Affected
- `table_name` - What columns? What changes?
- `other_table` - Schema changes? Data migrations?

### Permissions Required
- Global role: Admin / Super Admin / User
- Section access: Role matrix in section_role_access
- Module access: User-specific in user_module_access

### Audit Events Emitted
- Event type: `feature.action`
- Before/after: What gets logged?
- Retention: How long do we keep it?

### Tests / Verification Steps
- Unit tests: Location and coverage
- Integration tests: What scenarios?
- Security tests: Permission checks?
- Manual QA: What to test?

### Rollback Notes
- Safe to rollback? Data implications?
- Migration down script available?
- Cache clearing needed?
```

---

## ✅ Definition of Done Checklist

Use this checklist before closing a task:

### Code Quality
- [ ] Code follows PSR-12 standards
- [ ] No syntax errors or warnings
- [ ] Complex logic has comments
- [ ] No hardcoded credentials or secrets
- [ ] Error handling in place

### Testing
- [ ] Unit tests written and passing
- [ ] Integration tests if cross-component
- [ ] Security tests if auth/permissions touched
- [ ] Manual QA performed and documented
- [ ] Edge cases tested

### Security
- [ ] CSRF protection on mutation endpoints
- [ ] Permission checks on access-controlled features
- [ ] Audit logging on data mutations
- [ ] No sensitive data in logs
- [ ] Input validation/sanitization

### Documentation
- [ ] STATUS.md updated (task moved to Done)
- [ ] Relevant technical docs updated
- [ ] CHANGELOG.md entry if user-facing
- [ ] Traceability stub created if significant feature
- [ ] Code comments for complex logic

### Deployment
- [ ] Migrations tested and documented
- [ ] CSS rebuilt if styles changed
- [ ] Dependencies installed if needed
- [ ] Rollback plan documented
- [ ] Environment variables checked

### Review
- [ ] Code review completed (if applicable)
- [ ] Security review if auth/roles/permissions
- [ ] No unresolved feedback
- [ ] Tests passing in CI (when available)

---

## 🚀 Development Workflow

### Branch Strategy

**Current:** `laravel-migration` (active development)  
**Production:** `v1.1` (stable)  
**Feature Branches:** Optional for major features

### Commit Standards

Use emoji prefixes for commit clarity:

- 🐛 `:bug:` - Bug fix
- ✨ `:sparkles:` - New feature
- 🔒 `:lock:` - Security fix
- 📝 `:memo:` - Documentation
- ♻️ `:recycle:` - Refactoring
- ✅ `:white_check_mark:` - Tests
- 🎨 `:art:` - UI/design changes
- ⚡ `:zap:` - Performance improvement
- 🔧 `:wrench:` - Configuration
- 🗃️ `:card_file_box:` - Database changes

**Example:**
```
✨ Add permission visibility to package config

- Enhanced SectionRoleAccess with getUsersWithAccess()
- Updated section-config API to include permission data
- Added permission display in admin.js package config form
- Closes #42
```

### Pull Request Process (When Multi-Person Team)

1. Create feature branch from `laravel-migration`
2. Develop with commits following standards
3. Update STATUS.md, docs, tests
4. Create PR with description and checklist
5. Code review
6. Security review if needed
7. Merge after approval

### Direct Commit Process (Single Developer)

1. Update STATUS.md (move task to "Now")
2. Make changes
3. Update documentation
4. Write/update tests
5. Verify locally
6. Commit with emoji prefix
7. Update STATUS.md (move task to "Done")

---

## 🧪 Testing Standards

### Test Coverage Goals

- **Overall:** 60-65%
- **Auth Module:** 70% (critical security component)
- **Security-Critical:** 80%+ (auth flows, permission checks, CSRF)

### Test Categories

1. **Unit Tests** (`tests/Unit/`)
   - Test single class/method in isolation
   - Mock dependencies
   - Fast execution

2. **Integration Tests** (`tests/Integration/`)
   - Test cross-component interactions
   - Use test database (`woodson_hub_test`)
   - Verify workflows end-to-end

3. **Security Tests** (`tests/Security/`)
   - Auth flows (login, logout, session)
   - Permission checks (roles, sections, modules)
   - CSRF protection
   - Input validation

### Test Database

- **Name:** `woodson_hub_test`
- **Location:** Configured in `.env.testing`
- **Setup:** Auto-created by PHPUnit bootstrap
- **Isolation:** Tests must clean up after themselves

### Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Specific test
./vendor/bin/phpunit tests/Unit/AuthTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage

# Filter by name
./vendor/bin/phpunit --filter testLogin
```

---

## 🔐 Security Standards

### Authentication Requirements

- All pages must load `src/bootstrap.php`
- Protected pages must call `Auth::requireLogin()`
- Admin pages must call `Auth::requireRole('admin')`
- Super admin features must call `Auth::requireRole('super_admin')`

### CSRF Protection

- All POST/PUT/DELETE endpoints must verify CSRF token
- Use `verifyCsrfToken()` helper
- Include `csrf_token()` in forms
- JavaScript must fetch token from meta tag or `/api/csrf-token.php`

### Permission Checks

- Section access: `SectionRoleAccess::hasAccess($userId, $sectionSlug)`
- Module access: `Module::hasAccess($userId, $moduleSlug, $minimumRole)`
- View-as override: `Auth::getEffectiveRole()` respects super admin "view as"

### Audit Logging

- All mutations must call `AuditLogger::log($event, $userId, $before, $after)`
- Event naming: `entity.action` (e.g., `user.create`, `section.update`)
- Include before/after state as arrays
- Never log passwords or sensitive tokens

### Data Protection

- Never hard-delete unless design requires it
- Use `is_active = 0` for soft deletes
- Sanitize all user input
- Escape all output
- Use prepared statements (PDO)

---

## 📊 Code Quality Standards

### PHP Standards

- Follow PSR-12 (coding style)
- Follow PSR-4 (autoloading) for `src/` classes
- Type hints on all method parameters
- Return type declarations
- DocBlocks on public methods

### JavaScript Standards

- Vanilla JS (no frameworks in Hub core)
- Use `addEventListener` (not inline handlers)
- Fetch API for AJAX (not jQuery)
- Handle errors with try/catch
- Show user feedback with `showMessage(text, type)`

### CSS Standards

- Use CSS variables for theming
- Follow BEM methodology for custom classes
- Leverage enterprise design system tokens
- Mobile-first responsive design
- Build with scripts: `./build-css.sh` or `./build-css-production.sh`

### SQL Standards

- Use prepared statements (always)
- Index foreign keys
- Soft delete with `is_active`
- Use migrations for schema changes
- Document complex queries

---

## 🧭 Navigation & UI Standards

### Navigation Architecture Rules

**Maximum Navigation Levels:** 2 (sidebar → page)

**Allowed:**
- Sidebar groups (expandable containers)
- Direct page links in sidebar
- Entity-specific tabs (e.g., user detail page with Profile/Roles/Activity tabs)

**Forbidden:**
- On-page tabs that duplicate sidebar submenu items
- More than 2 levels of navigation (sidebar → submenu → on-page tabs is BANNED)
- Query parameter-based navigation (`?tab=pending` is legacy and deprecated)

**Exception for Entity Tabs:**
- Tabs are allowed ONLY for single-entity views (e.g., `/admin/users/:id/roles`)
- These tabs MUST be deep-linkable routes, not client-side JavaScript navigation
- These tabs are about viewing different aspects of ONE entity, not navigating between entity lists

### Routing Requirements

**All routes must:**
- Use named routes via `Route::name('section.subsection.action')`
- Use route groups with middleware for permission enforcement
- Provide 301 permanent redirects for legacy URLs

**Example:**
```php
// ✅ CORRECT: Named routes in grouped hierarchy
Route::prefix('admin')->middleware(['auth:admin,super_admin'])->group(function () {
    Route::prefix('users')->name('admin.users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/pending', [UserController::class, 'pending'])->name('pending');
    });
});

// ❌ WRONG: Flat routes with query params
Route::get('/users', [UserController::class, 'index']);
Route::get('/users?tab=pending', ...); // Query params are not routes!
```

### Sidebar Implementation

**Sidebar navigation must:**
- Use `route()` helper for all URLs: `route('admin.users.pending')`
- Use `request()->routeIs('admin.users.*')` for active state detection
- Never hardcode URLs like `/admin/users?tab=pending`
- Never use string matching (`strpos($url, '/admin/users')`) for active state

**Example:**
```php
$navItems = [
    [
        'type' => 'expandable',
        'id' => 'users',
        'label' => 'Users',
        'active' => request()->routeIs('admin.users.*'),
        'submenu' => [
            [
                'label' => 'Pending Approvals',
                'url' => route('admin.users.pending'),
                'active' => request()->routeIs('admin.users.pending')
            ]
        ]
    ]
];
```

### View Requirements

**No duplicate navigation:**
- If sidebar has submenu items, DO NOT replicate them as on-page tabs
- Users should navigate via sidebar, not click tabs within a page to change content sections
- Views should show ONE thing per route, controlled by `$activeTab` variable from controller

**Client-side tab switching is BANNED for navigation:**
- Remove `data-tab` and `data-subtab` attributes used for navigation
- Remove JavaScript event listeners for tab button clicks
- Tab visibility controlled by server-side routing, not client-side JavaScript

**Example:**
```blade
{{-- ✅ CORRECT: No tab buttons, content determined by route --}}
@php
    $activeTab = $activeTab ?? 'index';
@endphp

<div id="content-active-users" class="content-section {{ $activeTab === 'active' ? 'active' : '' }}" style="{{ $activeTab === 'active' ? '' : 'display:none;' }}">
    <!-- Active users content -->
</div>

{{-- ❌ WRONG: Tab buttons duplicate sidebar navigation --}}
<div class="tabs">
    <button data-tab="active-users">Active Users</button>
    <button data-tab="pending-users">Pending Approvals</button>
</div>
```

### Deep Linking & Bookmarking

**All navigation targets must:**
- Be directly accessible via URL (deep linking)
- Work when bookmarked and loaded later
- Show correct active state when loaded directly

**Test:** Can you paste `/admin/users/invitations` into browser and get the correct page with correct sidebar highlight? If no, fix it.

---

## 📦 Package Development Standards

### Package Structure

See `docs/PACKAGE_SPECIFICATION_V2.md` for complete spec.

Required files in packages:
- `package.json` - Metadata and configuration
- `index.php` - Entry point
- `icon.svg` - Module icon
- `README.md` - User documentation
- `schema.sql` - Database schema (if needed)

### Package Permissions

Packages must document:
- Minimum role required (admin/user)
- Section access if Management integration
- Capabilities provided
- Permission checks in code

### Package Testing

Packages should include:
- `tests/` directory (if complex)
- Manual testing guide in README
- Screenshots of key features

---

## 🛠️ CLI Tools & Scripts

### Migration Tools

- `php cli/migrate.php` - Core schema migrations
- `php cli/migrate-modules.php` - Module schema migrations
- `php cli/migrate-sections.php` - Section schema migrations

### Package Tools

- `php cli/pkg-scaffold.php` - Create new package skeleton
- `php cli/pkg-build.php` - Package existing code
- `php cli/pkg-lint.php` - Validate package structure
- `php cli/pkg-migrate.php` - Apply package migrations

### Maintenance Tools

- `php cli/db-maintenance.sh` - Database backups/optimization
- `php cli/revalidate-packages.php` - Re-scan package repository
- `php cli/cleanup-orphaned-sections.php` - Clean up unused sections

### CSS Build Tools

- `./build-css.sh` - Development build (unminified)
- `./build-css-production.sh` - Production build (minified)
- `./build-css-bundles.sh` - All bundles

---

## 📋 Documentation Standards

### Structure

All documentation should include:
- **Title** - Clear, descriptive
- **Last Updated** - Date of last change
- **Purpose** - What problem does this solve?
- **Audience** - Who is this for?
- **Content** - Organized with headers

### Formatting

- Use Markdown
- Code blocks with syntax highlighting
- Screenshots for UI features
- Links to related docs
- Table of contents for long docs

### Maintenance

- Update "Last Updated" date when changed
- Remove outdated information
- Keep examples current
- Link to STATUS.md for current state

### Doc Types

1. **Technical Docs** (`/docs/`) - Architecture, APIs, schemas
2. **User Docs** (`/docs/`) - Guides, quickstarts, tutorials
3. **Process Docs** (root) - Governance, workflows, standards
4. **Audit Docs** (`AUDIT_DOCS.md`) - Comprehensive index for auditors

---

## 🔄 Change Management

### Versioning

- **Major:** Breaking changes, major features (1.0 → 2.0)
- **Minor:** New features, non-breaking (1.1 → 1.2)
- **Patch:** Bug fixes, minor improvements (1.1.0 → 1.1.1)

### Release Process

1. Update version in relevant files
2. Update CHANGELOG.md with release notes
3. Create git tag: `git tag v1.2.0`
4. Run all migrations
5. Rebuild CSS
6. Deploy to production
7. Monitor logs for errors
8. Update STATUS.md

### Deprecation

When deprecating features:
1. Document in CHANGELOG.md
2. Add deprecation notice in code
3. Provide migration path
4. Keep deprecated code for 1 major version
5. Remove in next major version

---

## 🚨 Incident Response

### Severity Levels

- **Critical:** System down, data loss, security breach
- **High:** Major feature broken, performance degraded
- **Medium:** Minor feature broken, cosmetic issues
- **Low:** Enhancement requests, nice-to-haves

### Response Process

1. **Identify** - What's broken? What's the impact?
2. **Assess** - Severity? How many users affected?
3. **Contain** - Can we mitigate immediately?
4. **Fix** - Develop solution, test thoroughly
5. **Deploy** - Apply fix, verify resolution
6. **Document** - Update STATUS.md, post-mortem if needed

### Rollback Procedure

1. Revert latest commits
2. Restore database from backup if needed
3. Clear sessions if auth changed
4. Rebuild CSS if styles changed
5. Verify system stability
6. Update STATUS.md with incident notes

---

## 📞 Roles & Responsibilities

### Product Owner (Sully)
- Define workflows and acceptance criteria
- Prioritize features and fixes
- Approve user-facing changes
- Provide domain expertise

### Technical Lead (Claude AI)
- Implement features and fixes
- Write tests and documentation
- Maintain code quality
- Ensure security best practices

### Auditor (External Consultant)
- Review compliance posture
- Verify evidence completeness
- Assess governance effectiveness
- Recommend improvements

---

## 🎓 Quick Reference

### Before Starting Work
1. Pull latest from `laravel-migration`
2. Update STATUS.md (move task to "Now")
3. Check for blockers

### During Development
1. Follow standards (PSR-12, security, testing)
2. Update docs as you go
3. Commit frequently with emoji prefixes

### Before Completing
1. Run full test suite
2. Update STATUS.md (move to "Done")
3. Update CHANGELOG.md if user-facing
4. Verify all required artifacts

### Checklist Template
```
- [ ] Code complete and tested
- [ ] STATUS.md updated
- [ ] Docs updated (see Required Artifacts)
- [ ] CHANGELOG.md entry (if user-facing)
- [ ] Tests passing
- [ ] Security verified
- [ ] Ready for deployment
```

---

## 🔮 Future Automation Plans

### PR Checklist Automation
- Auto-check if STATUS.md updated
- Auto-check if CHANGELOG.md needed
- Auto-check if migrations need schema updates
- Auto-check test coverage thresholds

### Documentation Validation
- Validate all linked docs exist
- Check for stale "Last Updated" dates
- Verify AUDIT_DOCS.md index is current

### CI/CD Pipeline
- Run tests on every commit
- Block merge if tests fail
- Auto-deploy to staging
- Manual promotion to production

---

*This document defines the **development governance** for The Hub. For current system state, see [STATUS.md](STATUS.md). For complete documentation, see [AUDIT_DOCS.md](AUDIT_DOCS.md).*
