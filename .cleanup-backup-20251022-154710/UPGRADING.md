# Upgrading The Hub

This guide covers upgrading existing installations to the latest version.

## Pre-Upgrade Checklist

Before starting, ensure you have:

- [ ] **Backup your database**
  ```bash
  mysqldump -u your_user -p your_database > backup_$(date +%Y%m%d).sql
  ```

- [ ] **Backup your .env file**
  ```bash
  cp .env .env.backup-$(date +%Y%m%d)
  ```

- [ ] **Note your current version/commit**
  ```bash
  git log -1 --oneline
  ```

- [ ] **Access to server as sudo/root** (for composer, file permissions)

## Standard Upgrade Process

### Step 1: Pull Latest Code

```bash
cd /var/www/woodson/thehub
git pull origin main
```

### Step 2: Update PHP Dependencies

```bash
composer install --no-dev --optimize-autoloader
composer dump-autoload
```

### Step 3: Migrate Environment File

The `.env` migration script will:
- Add new variables from `.env.example`
- Preserve all your existing settings
- Migrate deprecated variables to new names
- Create automatic backup

```bash
# Preview changes (dry run)
php cli/migrate-env.php --dry-run

# Apply migration
php cli/migrate-env.php
```

**Example output:**
```
🔍 Analyzing .env file...

📊 Found 5 missing variables:
   • ENABLE_MICROSOFT_LOGIN
   • MICROSOFT_CLIENT_ID
   • MICROSOFT_CLIENT_SECRET
   • MICROSOFT_TENANT_ID
   • MICROSOFT_REDIRECT_URI

📦 Found 2 variables to migrate:
   • SMTP_FROM_EMAIL

💾 Backed up current .env to: .env.backup-2025-10-22-143022

✅ Migration complete!
   Added 6 new variables
```

### Step 4: Run Database Migrations

```bash
# Core tables
php cli/migrate.php

# Module tables
php cli/migrate-modules.php

# Section access tables
php cli/migrate-sections.php
```

### Step 5: Clear Sessions

Force all users to re-login (required after namespace changes):

```bash
rm sessions/sess_*
```

### Step 6: Verify Installation

```bash
# Check PHP syntax
find src public/api -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# Verify autoloader
php -r "require 'vendor/autoload.php'; echo class_exists('Hub\Auth') ? 'OK' : 'FAIL';"

# Check .env variables
grep -E "MICROSOFT|GOOGLE|SMTP" .env | head -15
```

### Step 7: Test in Browser

1. **Login Test**: Go to your site and login with Google/Microsoft
2. **Admin Panel**: Verify dashboard loads
3. **Advanced Settings**: Check Site Settings → Advanced tab loads correctly
4. **Module Access**: Test that modules/sections load properly

## Version-Specific Upgrades

### Upgrading to Hub Namespace (October 2025)

This is a major change from `WoodsonISD\Maintenance` to `Hub` namespace.

**Critical Changes:**
- Session name changed: `WOODSON_HUB_SESSION` → `THEHUB_SESSION`
- All class namespaces updated
- Organization-specific references removed

**Additional Steps:**
```bash
# Clear PHP OPcache if enabled
sudo systemctl reload apache2

# Or restart PHP-FPM if using
sudo systemctl restart php8.1-fpm
```

See `NAMESPACE_MIGRATION.md` for complete details.

### Adding Microsoft OAuth Support

New `.env` variables:
```bash
ENABLE_MICROSOFT_LOGIN=false
MICROSOFT_CLIENT_ID=your-app-id-here
MICROSOFT_CLIENT_SECRET=your-client-secret-here
MICROSOFT_TENANT_ID=common
MICROSOFT_REDIRECT_URI=https://hub.yourdomain.com/microsoft_login.php
```

Configure via: **Admin Panel → Site Settings → Advanced → Microsoft OAuth**

See `MICROSOFT_OAUTH.md` for Azure AD setup instructions.

## Troubleshooting

### Error: "Class 'Hub\Auth' not found"

**Cause**: Autoloader not regenerated after namespace change

**Fix**:
```bash
composer dump-autoload
```

### Error: Session data lost / Users logged out

**Cause**: Session name changed from `WOODSON_HUB_SESSION` to `THEHUB_SESSION`

**Expected**: This is normal after namespace migration. Users simply need to re-login.

### Error: Advanced Settings tab returns 500

**Cause**: Missing namespace use statements in `system-config.php`

**Fix**: Ensure these lines exist at top of `/public/api/system-config.php`:
```php
use Hub\Auth;
use Hub\User;
use Hub\AuditLogger;
```

### Error: .env migration adds duplicate variables

**Cause**: Variables already existed with different casing or spacing

**Fix**: 
1. Check `.env.backup-*` files
2. Manually edit `.env` to remove duplicates
3. Keep the most recent/correct values

### Database migration fails

**Cause**: Schema already up to date or MySQL permissions

**Fix**:
```bash
# Check current schema
mysql -u user -p database_name -e "SHOW TABLES;"

# Re-run with verbose output
php cli/migrate.php 2>&1 | tee migration.log
```

## Rollback Procedure

If something goes wrong:

### 1. Restore .env
```bash
cp .env.backup-YYYYMMDD .env
```

### 2. Restore Database
```bash
mysql -u user -p database_name < backup_YYYYMMDD.sql
```

### 3. Restore Code
```bash
git reset --hard COMMIT_HASH
composer dump-autoload
```

### 4. Clear Cache
```bash
rm sessions/sess_*
sudo systemctl reload apache2
```

## Post-Upgrade Tasks

After upgrading successfully:

### 1. Review New Settings

**Admin Panel → Site Settings → Advanced**

Check and configure:
- [ ] Microsoft OAuth settings (if using Azure AD)
- [ ] Google Groups role associations
- [ ] Domain restrictions (`ALLOWED_DOMAINS`, `REQUIRE_DOMAIN_MATCH`)
- [ ] SMTP settings migrated correctly
- [ ] Database connection working

### 2. Test Key Functionality

- [ ] Login with Google OAuth works
- [ ] Login with Microsoft OAuth works (if enabled)
- [ ] Users can create/edit records
- [ ] Admin can access all sections
- [ ] Modules load correctly
- [ ] Export functionality works
- [ ] Email invitations send properly

### 3. Notify Users

Template email:
```
Subject: System Upgrade - Re-login Required

Hello,

The Hub has been upgraded to the latest version with new features:

- Microsoft login support (optional)
- Enhanced configuration UI
- Improved security and performance

ACTION REQUIRED:
Please log out and log back in at your next visit.

New Features:
- [List any user-facing features]

Questions? Contact: [your-email]
```

### 4. Monitor Logs

```bash
# Application errors
tail -f logs/php-errors.log

# Apache errors
tail -f /var/log/apache2/hub.yourdomain.com-error.log

# Check for issues
grep -i "error\|fatal\|warning" logs/php-errors.log | tail -20
```

## Getting Help

If you encounter issues:

1. **Check Documentation**
   - `README.md` - General setup
   - `NAMESPACE_MIGRATION.md` - Namespace changes
   - `MICROSOFT_OAUTH.md` - Microsoft login
   - `ADVANCED_SETTINGS.md` - Configuration UI

2. **Check Logs**
   - `logs/php-errors.log`
   - Browser console (F12)
   - Apache error logs

3. **Verify Environment**
   ```bash
   php -v  # Should be 8.0+
   composer --version
   mysql --version
   ```

4. **Common Issues**: See Troubleshooting section above

## Staying Up to Date

To receive notifications about updates:

1. **Watch the repository** (if using GitHub)
2. **Subscribe to releases**
3. **Check for updates monthly**
   ```bash
   git fetch origin
   git log HEAD..origin/main --oneline
   ```

## Automated Updates (Advanced)

For automated deployments, create a script:

```bash
#!/bin/bash
# update-hub.sh

set -e  # Exit on error

cd /var/www/woodson/thehub

# Backup
mysqldump -u user -p'password' database > backup_$(date +%Y%m%d).sql
cp .env .env.backup-$(date +%Y%m%d)

# Update
git pull origin main
composer install --no-dev --optimize-autoloader

# Migrate
php cli/migrate-env.php
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php

# Clear cache
rm sessions/sess_* 2>/dev/null || true
sudo systemctl reload apache2

echo "✅ Update complete!"
```

**Note**: Always test updates in staging environment first!
