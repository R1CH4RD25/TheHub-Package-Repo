# The Hub - Quick Start Guide

Get the application up and running in minutes!

## Prerequisites

### Option 1: Automated Installation (Recommended)

```bash
# Clone repository
git clone https://github.com/yourusername/thehub.git
cd thehub

# Run automated package installer
sudo bash install-packages.sh
```

This installs everything you need automatically!

### Option 2: Manual Installation

✅ You need:
- Apache or Nginx web server
- MariaDB or MySQL database
- PHP 8.0+ (verify: `php -v`)
- Composer (PHP dependency manager)
- SSL certificate (Let's Encrypt recommended)

## Quick Setup (5-10 minutes)

### 1. Clone Repository

```bash
# Clone from GitHub
git clone https://github.com/yourusername/thehub.git
cd thehub

# Or download and extract ZIP
wget https://github.com/yourusername/thehub/archive/main.zip
unzip main.zip
cd thehub-main
```

### 2. Install Dependencies

```bash
# Install Composer if needed
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install PHP packages
composer install
```

### 3. Configure Environment

```bash
# Copy and edit configuration
cp .env.example .env
nano .env
```

**Required Changes:**
- `DB_NAME` - Your database name (e.g., `thehub`)
- `DB_USER` - Database username
- `DB_PASSWORD` - Set a secure password
- `APP_URL` - Your site URL (e.g., `https://hub.yourdomain.com`)
- `SUPER_ADMIN_EMAIL` - Your email (you'll be the first admin)
- `SESSION_SECRET` - Generate with: `openssl rand -base64 32`

**Choose OAuth Provider (Google or Microsoft):**

For **Google OAuth**:
- `GOOGLE_CLIENT_ID` - Get from Google Cloud Console
- `GOOGLE_CLIENT_SECRET` - Get from Google Cloud Console  
- `GOOGLE_REDIRECT_URI` - `https://hub.yourdomain.com/google_login.php`

For **Microsoft OAuth** (optional):
- `ENABLE_MICROSOFT_LOGIN=true`
- `MICROSOFT_CLIENT_ID` - Get from Azure Portal
- `MICROSOFT_CLIENT_SECRET` - Get from Azure Portal
- `MICROSOFT_TENANT_ID` - Your tenant ID or `common`
- `MICROSOFT_REDIRECT_URI` - `https://hub.yourdomain.com/microsoft_login.php`

### 4. Create Database

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE thehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'thehub_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON thehub.* TO 'thehub_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Update `.env` with this password!**

### 5. Set Up OAuth Provider

#### Option A: Google OAuth

1. Go to: https://console.cloud.google.com
2. Create new project
3. APIs & Services → Credentials → Create OAuth 2.0 Client ID
4. Configure:
   - Type: Web application
   - Redirect URI: `https://hub.yourdomain.com/google_login.php`
5. Copy Client ID and Secret to `.env`

See `GOOGLE_GROUPS_SETUP.md` for advanced auto-role features.

#### Option B: Microsoft OAuth

1. Go to: https://portal.azure.com
2. Azure Active Directory → App registrations → New registration
3. Configure:
   - Redirect URI: `https://hub.yourdomain.com/microsoft_login.php`
   - Supported account types: Choose based on needs
4. Create client secret
5. Copy Application ID, Client Secret, Tenant ID to `.env`

See `MICROSOFT_OAUTH.md` for detailed setup instructions.

### 6. Run Migrations

```bash
# Core database schema
php cli/migrate.php

# Modules schema
php cli/migrate-modules.php

# Sections schema
php cli/migrate-sections.php
```

You should see: ✓ Database schema created successfully!

### 7. Create First Super Admin

```bash
# Run interactive setup script
php cli/setup.php
```

This will prompt you to create the first super admin account:
- **Email:** Your email address
- **Name:** Your full name
- **Username:** Username for local login (e.g., `admin`)
- **Password:** Secure password (min 8 chars, uppercase, lowercase, number)

**Example:**
```
Email address: admin@yourdomain.com
Full name: John Admin
Username (for local login): admin
Password: [hidden]
Confirm password: [hidden]

✓ Super admin account created successfully!
```

**Important:** 
- Save these credentials securely!
- You'll use username + password to login initially
- Once OAuth is configured, you can login with Google/Microsoft too

### 8. Set Permissions

```bash
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 logs sessions temp uploads
```

### 9. Configure Web Server

#### Apache

```bash
# Enable required modules
sudo a2enmod rewrite ssl headers

# Copy example configuration
sudo cp apache/hub.example.com.conf /etc/apache2/sites-available/hub.yourdomain.com.conf

# Edit configuration (update domain and paths)
sudo nano /etc/apache2/sites-available/hub.yourdomain.com.conf
```

**Or create manually:**
```apache
<VirtualHost *:80>
    ServerName hub.yourdomain.com
    DocumentRoot /path/to/thehub/public
    
    <Directory /path/to/thehub/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/hub-error.log
    CustomLog ${APACHE_LOG_DIR}/hub-access.log combined
</VirtualHost>
```

```bash
# Enable site
sudo a2ensite hub.yourdomain.com
sudo apache2ctl configtest
sudo systemctl restart apache2
```

#### Nginx

```nginx
server {
    listen 80;
    server_name hub.yourdomain.com;
    root /path/to/thehub/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 9. Get SSL Certificate

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache

# Get certificate (Apache)
sudo certbot --apache -d hub.yourdomain.com

# Or for Nginx
sudo certbot --nginx -d hub.yourdomain.com
```

### 10. First Login

1. Visit: `https://hub.yourdomain.com`
2. **Login with local account:**
   - Username: `admin` (or whatever you set in setup)
   - Password: [your password]
3. You're automatically Super Admin!

**OR** after configuring OAuth:
- Click "Sign in with Google" (or Microsoft)
- Login with the email you set as `SUPER_ADMIN_EMAIL`

## Initial Configuration

### As Super Admin, configure the site:

1. **Site Settings → Branding**
   - Set organization name
   - Upload logo
   - Customize color scheme
   
2. **Site Settings → Advanced**
   - Verify OAuth settings
   - Configure SMTP email (optional)
   - Set domain restrictions (optional)
   - Enable Google Groups (optional)

3. **Admin Dashboard → Vehicles**
   - Add your vehicles

4. **Admin Dashboard → Users**
   - Invite additional users
   - Set roles

## What's Next?

### Common Admin Tasks:

**Add Vehicles**
- Admin Dashboard → Vehicles
- Click "+ Add Vehicle"
- Enter vehicle details

**Invite Users**
- Admin Dashboard → Users
- Click "Invite User"
- Enter email and select role
- They receive invitation link

**Configure Modules**
- Admin Dashboard → Modules
- Enable/disable features
- Set access permissions

**Export Data**
- Go to any module (e.g., Fuel Entries)
- Set filters
- Click "Export XLS" or "Export CSV"

## Troubleshooting

### Can't connect to database?
```bash
# Test connection
php -r "new PDO('mysql:host=localhost;dbname=thehub', 'thehub_user', 'YOUR_PASSWORD');"
```

### OAuth not working?
- Verify redirect URI matches exactly (check for http vs https)
- Ensure HTTPS is working: `curl -I https://hub.yourdomain.com`
- Check `.env` credentials are correct
- Verify OAuth app is not in testing mode (publish it)

### 500 Internal Server Error?
```bash
# Check application log
tail -f logs/php-errors.log

# Check web server log
sudo tail -f /var/log/apache2/error.log
# or
sudo tail -f /var/log/nginx/error.log
```

### Permissions issues?
```bash
# Fix all permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 logs sessions temp uploads
```

### Session issues / constantly logged out?
```bash
# Clear sessions
rm sessions/sess_*

# Verify SESSION_SECRET is set in .env
grep SESSION_SECRET .env
```

## Testing Checklist

- [ ] Can access landing page
- [ ] Can login with OAuth
- [ ] Super admin sees admin dashboard
- [ ] Can configure site settings
- [ ] Can add a vehicle
- [ ] Can submit fuel entry
- [ ] Can view entries in admin
- [ ] Can export to Excel/CSV
- [ ] Can invite users
- [ ] Email invitations work (if SMTP configured)

## Security Checklist

- [ ] HTTPS enabled (SSL certificate installed)
- [ ] `.env` file permissions set to 600 or 640
- [ ] Database user has minimal required permissions
- [ ] `SESSION_SECRET` is randomly generated
- [ ] `SUPER_ADMIN_EMAIL` is set correctly
- [ ] File upload directory has proper restrictions
- [ ] `APP_ENV=production` in .env
- [ ] Error display is off in production
- [ ] Regular database backups configured

## Daily Use for End Users

**Simple 4-step process:**

1. Go to your Hub URL
2. Sign in (first time only)
3. Select module (e.g., Fuel Entry)
4. Fill form and submit

That's it! 🎉

## Upgrading Existing Installation

If you already have The Hub installed and are upgrading:

```bash
# See UPGRADING.md for detailed instructions
php cli/migrate-env.php  # Adds new .env variables
composer install
composer dump-autoload
php cli/migrate.php
```

## Getting Help

**Documentation:**
- `README.md` - Overview and features
- `DEPLOYMENT.md` - Detailed deployment guide
- `UPGRADING.md` - Upgrade instructions
- `MICROSOFT_OAUTH.md` - Microsoft login setup
- `GOOGLE_GROUPS_SETUP.md` - Auto-role assignment

**Logs:**
- Application: `logs/php-errors.log`
- Web server: Check Apache/Nginx error logs

**Support:**
- GitHub Issues: https://github.com/yourusername/thehub/issues
- Documentation: See `docs/` directory

---

**Tips:**
- Bookmark the site on mobile devices for easy access
- Export data regularly for backup
- Monitor `logs/php-errors.log` for issues
- SSL certificates auto-renew with Certbot
- Test new features in staging environment first
