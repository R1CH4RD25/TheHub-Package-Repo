# Namespace Migration Summary

## Overview
Migrated from `WoodsonISD\Maintenance` to `Hub` namespace and removed all hardcoded Woodson ISD references to make the application generic and distributable.

## Changes Made

### 1. Namespace Updates
- **composer.json**: Changed PSR-4 autoload from `WoodsonISD\\Maintenance\\` → `Hub\\`
- **Package name**: `woodson-isd/vehicle-maintenance` → `thehub/core`
- **All src/ classes**: Updated namespace declarations (Auth, Database, User, etc.)
- **All use statements**: Updated across entire codebase (public/, cli/, docs/)

### 2. Organization References
- **Default organization name**: "Woodson ISD" → "Your Organization"
- **Updated files**:
  - `src/Layout.php` - Header and footer organization name
  - `public/admin/index.php` - Site Settings form defaults
  - `public/hub.php` - Page title
  - `public/api/site-settings.php` - Database defaults
  - `public/assets/js/site-settings.js` - JavaScript defaults

### 3. Domain Configuration
- **Hardcoded domains removed**: All `woodsonisd.net` references now configurable via `.env`
- **Updated files**:
  - `src/Auth.php`:
    - `getAuthUrl()` - Uses `ALLOWED_DOMAINS` for Google OAuth hint
    - `handleGoogleCallback()` - Domain verification via `REQUIRE_DOMAIN_MATCH`
  - `src/Invitation.php`:
    - `create()` - Domain validation from `ALLOWED_DOMAINS`
  - `src/Email.php`:
    - `setFrom()` - Uses `SMTP_FROM_NAME` instead of hardcoded name
    - `sendTestEmail()` - Generic test email content

### 4. Database Defaults
- **Default database name**: `woodson_maintenance` → `thehub`
- **Updated files**:
  - `public/api/system-config.php` - Default DB_NAME
  - `public/admin/index.php` - Form placeholder
  - `database/schema.sql` - Schema comment header

### 5. Email Templates
- **Invitation emails**: Now use `SiteSettings::get()` for organization and site names
- **Approval emails**: Dynamic branding from database settings
- **Updated files**:
  - `src/Invitation.php` - sendInvitationEmail() and sendApprovalEmail()

### 6. Placeholder Updates
All form placeholders changed to generic examples:
- `woodsonisd.net` → `example.com`
- `students@woodsonisd.net` → `students@example.com`
- `https://hub.woodsonisd.net` → `https://hub.example.com`
- `noreply@woodsonisd.net` → `noreply@example.com`

### 7. Session Name
- Changed from `WOODSON_HUB_SESSION` → `THEHUB_SESSION`

### 8. Documentation
- Updated `.github/copilot-instructions.md` - AI agent guide
- Updated all `*.md` files to use Hub namespace
- Removed Woodson-specific references from examples

## Environment Variables

The following `.env` variables now control organization-specific settings:

```bash
# Organization branding (stored in database via Site Settings)
# These are defaults only - actual values come from site_settings table
organization_name=Your Organization
site_name=The Hub

# Domain restrictions
ALLOWED_DOMAINS=example.com,yourdomain.org
REQUIRE_DOMAIN_MATCH=false  # Set true to enforce domain restrictions

# Email branding
SMTP_FROM_NAME=The Hub
SMTP_FROM_EMAIL=noreply@example.com

# Database
DB_NAME=thehub  # Default, can be customized per installation
```

## Migration Steps for Existing Installations

If you have an existing installation with the old namespace:

1. **Backup your database and .env file**
2. **Pull the updated code**
3. **Run composer update**:
   ```bash
   cd /var/www/woodson/thehub
   composer dump-autoload
   ```

4. **Clear PHP sessions** (users will need to re-login):
   ```bash
   rm sessions/sess_*
   ```

5. **Update your .env file**:
   - Add `SMTP_FROM_NAME=Your Organization`
   - Add `ALLOWED_DOMAINS=yourdomain.com`
   - Add `REQUIRE_DOMAIN_MATCH=false` (or true if you want to enforce)

6. **Test the application**:
   - Login should work
   - Admin panel should load
   - Site Settings should display correctly

## Role Names (Not Changed)

Role identifiers remain the same for backward compatibility:
- `maintenance_director` - Role still valid
- `maintenance_staff` - Role still valid

These are internal identifiers and don't need to change. The display labels can be customized through the admin interface if desired.

## Verification

Run these commands to verify the migration:

```bash
# Check namespace references
grep -r "WoodsonISD" /var/www/woodson/thehub/src --include="*.php"
# Should return nothing

# Check autoloader
php -r "require 'vendor/autoload.php'; echo class_exists('Hub\Auth') ? 'OK' : 'FAIL';"
# Should return: OK

# Check syntax
find src -name "*.php" -exec php -l {} \; | grep -v "No syntax"
# Should return nothing
```

## Benefits

1. **Generic Distribution**: Application can now be distributed on GitHub without organization-specific code
2. **Easy Customization**: All branding configurable through Admin Panel and .env
3. **Multi-Tenant Ready**: Different organizations can use the same codebase
4. **Professional**: No hardcoded school district references
5. **Flexible Domains**: Support for multiple email domains
6. **Better Maintainability**: Centralized configuration instead of scattered hardcoded values
