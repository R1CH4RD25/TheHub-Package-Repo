# Advanced Settings - System Configuration

## Overview
The Advanced Settings section provides Super Admins with comprehensive control over system-level configuration, including authentication, database, Google OAuth, and application settings.

**⚠️ Warning**: Changes to these settings can break the application if configured incorrectly. Always back up your `.env` file before making changes.

## Access
- **Location**: Admin Panel → Site Settings → Advanced
- **Required Role**: Super Admin only
- **Security**: All changes are logged in the audit trail

## Configuration Sections

### 1. Authentication & Login

#### Google OAuth Only
- When **enabled**: Only Google authentication is allowed
- When **disabled**: Both Google and local user accounts are permitted
- **Recommendation**: Keep disabled during initial setup

#### Allow Local User Accounts
- Enable this to create admin accounts before Google OAuth is configured
- **Critical for first-time installation**: You need at least one local admin before enabling Google-only mode

#### Require Domain Match
- Restrict Google logins to specific email domains
- Configure allowed domains in the "Allowed Email Domains" field

#### Allowed Email Domains
- Comma-separated list (e.g., `woodsonisd.net, example.com`)
- Only applies when "Require Domain Match" is enabled

#### Session Timeout
- How long users stay logged in (in hours)
- Range: 1-168 hours (1 week maximum)
- Default: 2 hours

---

### 2. Google OAuth Configuration

Configure Google Cloud Console OAuth 2.0 credentials for Google login functionality.

#### Setup Steps:
1. Visit [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Create or select a project
3. Create OAuth 2.0 Client ID credentials
4. Add authorized redirect URIs (must match your GOOGLE_REDIRECT_URI exactly)
5. Copy Client ID and Client Secret to these fields

#### Fields:
- **Google Client ID**: Format `xxxxx.apps.googleusercontent.com`
- **Google Client Secret**: Format `GOCSPX-xxxxx`
- **Google Redirect URI**: Full URL to your OAuth callback (e.g., `https://hub.yourdomain.com/auth/google_callback.php`)

---

### 3. Google Workspace Groups Integration

Automatically approve users based on Google Workspace group membership. **Requires a service account with Directory API access.**

#### Setup Requirements:
1. Create a service account in Google Cloud Console
2. Enable Google Workspace Domain-Wide Delegation
3. Grant Directory API scopes: `https://www.googleapis.com/auth/admin.directory.group.member.readonly`
4. Download JSON key file and place in `config/` directory
5. Configure admin email for impersonation

#### Fields:
- **Enable Google Groups Auto-Approval**: Toggle feature on/off
- **Service Account Email**: Email of the service account (e.g., `service-account@project-id.iam.gserviceaccount.com`)
- **Google Workspace Admin Email**: Admin account the service account will impersonate
- **Service Account Key File Path**: Relative path from project root (e.g., `config/service-account-key.json`)
- **Auto-Approval Groups**: One group email per line (e.g., `staff@yourdomain.com`)

**See**: `docs/GOOGLE_GROUPS_SETUP.md` for detailed setup instructions

---

### 4. Database Configuration

MySQL/MariaDB connection settings. **Changes require application refresh to take effect.**

#### Fields:
- **Database Host**: MySQL server hostname or IP (usually `localhost`)
- **Database Name**: Name of the database
- **Database Username**: Database user with full permissions
- **Database Password**: Password for database user (stored in .env file)

#### Test Connection:
Click "Test Connection" to verify database credentials before saving.

**Result indicators**:
- ✅ Connected successfully: Shows MySQL version
- ❌ Connection failed: Shows error message

---

### 5. Application Settings

Core application configuration.

#### Fields:
- **Application URL**: Full URL where The Hub is accessed (used for OAuth callbacks, email links, etc.)
- **Environment**: `production`, `development`, or `staging`
  - Production: Optimized performance, minimal error display
  - Development: Detailed errors, debugging enabled
  - Staging: Testing environment before production
- **Debug Mode**: Show detailed error messages (**⚠️ disable in production!**)
- **Max Upload Size**: Maximum file size for logos and branding (in MB, range: 1-100)
- **Maintenance Mode**: Temporarily disable access for non-admin users

---

### 6. Email Configuration (Future)

SMTP settings for sending system emails (invitation notifications, password resets, etc.).

**Status**: UI ready, email functionality not yet implemented

#### Fields:
- **SMTP Host**: SMTP server hostname (e.g., `smtp.gmail.com`)
- **SMTP Port**: Usually 587 (TLS) or 465 (SSL)
- **SMTP Username**: Email account for sending
- **SMTP Password**: Password or app-specific password
- **From Email Address**: Email shown as sender
- **From Name**: Name shown as sender (e.g., "The Hub")

---

### 7. Danger Zone

**⚠️ These actions cannot be undone. Use with extreme caution.**

#### Reset All Color Settings to Defaults
- Restores all color/branding settings to original values
- Does NOT affect themes (only active color settings)
- Includes: Primary colors, hub colors, role badges, status badges

#### Clear All Active Sessions
- Force logout **all users**, including yourself
- Use when: Users stuck in bad session state, security concern
- **You will be logged out** and redirected to login page

#### Regenerate .env File
- Re-creates `.env` file from current database settings and form values
- Backs up current `.env` as `.env.backup.YYYY-MM-DD_HH-MM-SS`
- Use when: .env file corrupted or missing

---

## Security Features

### Automatic Backups
Every time you save configuration, the system automatically:
1. Backs up current `.env` to `.env.backup.YYYY-MM-DD_HH-MM-SS`
2. Logs the change in Activity Logs with field names that were updated

### Password Handling
- Passwords never sent to frontend (shown as `••••••••`)
- If password field is empty when saving, original password is retained
- Update password by typing new value; leave empty to keep current

### Audit Logging
All configuration changes are logged with:
- User who made the change
- Timestamp
- Fields that were updated
- Before/after values (where applicable)

---

## First-Time Installation Workflow

### Step 1: Initial Setup
1. Copy `.env.example` to `.env`
2. Configure database settings:
   ```
   DB_HOST=localhost
   DB_NAME=your_database_name
   DB_USER=your_database_user
   DB_PASSWORD=your_database_password
   ```
3. Keep these settings:
   ```
   ALLOW_LOCAL_USERS=true
   GOOGLE_ONLY_LOGIN=false
   ```

### Step 2: Database Migration
Run all migration scripts:
```bash
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php
```

### Step 3: Create Super Admin
1. Access the application at your configured URL
2. Register a new account (first user becomes super admin)
3. Verify login works

### Step 4: Configure Google OAuth (Optional)
1. Follow Google Cloud Console setup in section 2 above
2. Enter credentials in Admin Panel → Site Settings → Advanced → Google OAuth Configuration
3. Save and test Google login
4. Once verified, optionally enable "Google OAuth Only"

### Step 5: Configure Additional Settings
- Set application URL
- Configure session timeout
- Set allowed domains (if using domain restriction)
- Configure Google Groups integration (if applicable)

---

## Troubleshooting

### "Database connection failed"
- Verify database credentials
- Check MySQL service is running: `sudo systemctl status mysql`
- Ensure database exists: `mysql -u root -p -e "SHOW DATABASES;"`
- Verify user permissions: User needs `SELECT`, `INSERT`, `UPDATE`, `DELETE` on database

### "Google OAuth not working"
- Verify redirect URI matches exactly in Google Cloud Console
- Check authorized domains in Google Cloud Console
- Ensure OAuth consent screen is configured
- Verify Client ID and Secret are correct

### "Locked out after changing settings"
If you accidentally lock yourself out:
1. SSH into server
2. Edit `.env` file directly: `nano /var/www/woodson/thehub/.env`
3. Set `ALLOW_LOCAL_USERS=true`
4. Log in with local account
5. Fix configuration through admin panel

### "Configuration not taking effect"
Some settings require application refresh:
- Clear browser cache
- Restart PHP-FPM: `sudo systemctl restart php-fpm` (or `php8.2-fpm`)
- Check `.env` file was actually updated
- Verify file permissions: `.env` should be readable by web server

---

## Best Practices

### Security
- ✅ Always back up `.env` before making changes
- ✅ Keep `DEBUG_MODE=false` in production
- ✅ Use strong, unique passwords for database
- ✅ Restrict database user permissions (only grant what's needed)
- ✅ Enable Google OAuth only after verifying it works
- ✅ Keep at least one super admin with local credentials as backup

### Maintenance
- ✅ Document your configuration in a secure location
- ✅ Test changes in development environment first
- ✅ Review Activity Logs regularly for unauthorized changes
- ✅ Rotate database passwords periodically
- ✅ Keep backup of working `.env` file off-server

### Performance
- ✅ Use `production` environment in production
- ✅ Set reasonable session timeout (2-8 hours)
- ✅ Limit max upload size to actual needs
- ✅ Monitor session directory size (clear old sessions periodically)

---

## Related Documentation

- [Google Groups Setup](GOOGLE_GROUPS_SETUP.md) - Detailed Google Workspace integration
- [Roles Documentation](ROLES_DOCUMENTATION.md) - User roles and permissions
- [Deployment Guide](DEPLOYMENT.md) - Production deployment instructions
- [Quick Start](QUICKSTART.md) - Getting started guide

---

## API Endpoints

For developers integrating with Advanced Settings:

### Load Configuration
```
GET /api/system-config.php?action=load
```
Returns current configuration (passwords masked)

### Save Configuration
```
POST /api/system-config.php?action=save
Content-Type: application/json

{
  "auth": { ... },
  "google_oauth": { ... },
  "database": { ... },
  ...
}
```

### Test Database Connection
```
POST /api/system-config.php?action=test-db
Content-Type: application/json

{
  "host": "localhost",
  "name": "db_name",
  "user": "db_user",
  "password": "db_pass"
}
```

### Clear All Sessions
```
POST /api/system-config.php?action=clear-sessions
```

### Regenerate .env File
```
POST /api/system-config.php?action=regenerate-env
```

**Authentication**: All endpoints require Super Admin role. Returns 403 Forbidden for non-super admins.
