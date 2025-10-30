# TheHub - Complete Installation Guide

**Self-Hosted Platform for Any Organization**  
Schools, businesses, non-profits, or personal projects - TheHub adapts to your needs.

---

## 📋 Table of Contents

1. [System Requirements](#system-requirements)
2. [Quick Installation](#quick-installation-15-minutes)
3. [Detailed Setup](#detailed-setup)
4. [Optional Components](#optional-components)
5. [Verification](#verification)
6. [Troubleshooting](#troubleshooting)
7. [Post-Installation](#post-installation)

---

## System Requirements

### Minimum Specifications

- **OS:** Linux (Ubuntu 20.04+, Debian 10+, CentOS 8+) or Windows with XAMPP
- **RAM:** 2GB minimum, 4GB recommended
- **Storage:** 10GB minimum (more for file uploads)
- **PHP:** 8.0 or higher
- **Database:** MySQL 8.0+ or MariaDB 10.5+
- **Web Server:** Apache 2.4+ or Nginx 1.18+

### Required Software

| Component | Minimum Version | Recommended |
|-----------|----------------|-------------|
| PHP | 8.0 | 8.3 |
| MySQL/MariaDB | 8.0 / 10.5 | 8.3 / 11.0 |
| Apache/Nginx | 2.4 / 1.18 | Latest |
| Composer | 2.0 | Latest |

### PHP Extensions Required

```bash
# Check installed extensions
php -m

# Required extensions:
- pdo
- pdo_mysql
- mbstring
- curl
- json
- openssl
- zip
- xml
- session
```

---

## Quick Installation (15 minutes)

### Option 1: Automated Script (Recommended)

```bash
# 1. Clone repository
git clone https://github.com/yourusername/thehub.git
cd thehub

# 2. Run automated installer
sudo bash install-packages.sh

# 3. Install PHP dependencies
composer install

# 4. Configure environment
cp .env.example .env
nano .env  # Edit database credentials

# 5. Run migrations
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php

# 6. Access your site
# Visit: https://your-domain.com
```

### Option 2: Docker (Coming Soon)

```bash
docker-compose up -d
```

---

## Detailed Setup

### Step 1: Install System Dependencies

#### Ubuntu/Debian

```bash
# Update package list
sudo apt update && sudo apt upgrade -y

# Install Apache, PHP, MySQL
sudo apt install -y apache2 \
    php8.3 php8.3-cli php8.3-common php8.3-mysql \
    php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl \
    php8.3-xml php8.3-bcmath \
    mariadb-server mariadb-client \
    git curl wget unzip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Enable Apache modules
sudo a2enmod rewrite ssl headers
sudo systemctl restart apache2
```

#### CentOS/RHEL

```bash
# Install EPEL and Remi repositories
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Enable PHP 8.3
sudo yum module reset php
sudo yum module enable php:remi-8.3 -y

# Install packages
sudo yum install -y httpd \
    php php-cli php-mysqlnd php-zip php-gd \
    php-mbstring php-curl php-xml php-bcmath \
    mariadb-server mariadb \
    git curl wget unzip

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Start services
sudo systemctl start httpd mariadb
sudo systemctl enable httpd mariadb
```

### Step 2: Configure Database

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database
sudo mysql -u root -p
```

```sql
-- Create database
CREATE DATABASE thehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'thehub_user'@'localhost' IDENTIFIED BY 'your_secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON thehub.* TO 'thehub_user'@'localhost';
FLUSH PRIVILEGES;

-- Verify
SHOW DATABASES;
EXIT;
```

### Step 3: Clone and Configure Application

```bash
# Clone repository
cd /var/www
sudo git clone https://github.com/yourusername/thehub.git
cd thehub

# Set permissions
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 uploads/ sessions/ logs/ temp/

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Copy environment file
cp .env.example .env
```

### Step 4: Configure Environment Variables

Edit `.env` with your settings:

```bash
nano .env
```

**Required Configuration:**

```bash
# Database
DB_HOST=localhost
DB_NAME=thehub
DB_USER=thehub_user
DB_PASSWORD=your_secure_password

# Application
APP_URL=https://hub.yourdomain.com
APP_ENV=production
DEBUG_MODE=false

# Session
SESSION_SECRET=generate_with_openssl_rand_base64_32
SESSION_TIMEOUT=2

# Authentication - Choose at least one
ALLOW_LOCAL_USERS=true

# Google OAuth (Optional)
ENABLE_GOOGLE_LOGIN=true
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-your-secret
GOOGLE_REDIRECT_URI=https://hub.yourdomain.com/google_login.php

# Microsoft OAuth (Optional)
ENABLE_MICROSOFT_LOGIN=false
MICROSOFT_CLIENT_ID=your-app-id
MICROSOFT_CLIENT_SECRET=your-secret
MICROSOFT_TENANT_ID=common
MICROSOFT_REDIRECT_URI=https://hub.yourdomain.com/microsoft_login.php

# Email (for invitations)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=noreply@yourdomain.com
SMTP_PASSWORD=your_smtp_password
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=Your Organization
SMTP_ENCRYPTION=tls
```

**Generate Session Secret:**

```bash
openssl rand -base64 32
```

### Step 5: Run Database Migrations

```bash
# Core schema
php cli/migrate.php

# Module system
php cli/migrate-modules.php

# Dynamic sections
php cli/migrate-sections.php

# Verify tables
mysql -u thehub_user -p thehub -e "SHOW TABLES;"
```

### Step 6: Configure Web Server

#### Apache Configuration

Create `/etc/apache2/sites-available/thehub.conf`:

```apache
<VirtualHost *:80>
    ServerName hub.yourdomain.com
    ServerAdmin admin@yourdomain.com
    DocumentRoot /var/www/thehub/public

    <Directory /var/www/thehub/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/thehub_error.log
    CustomLog ${APACHE_LOG_DIR}/thehub_access.log combined

    # Redirect HTTP to HTTPS
    RewriteEngine on
    RewriteCond %{SERVER_NAME} =hub.yourdomain.com
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>
```

Enable site and restart:

```bash
sudo a2ensite thehub
sudo systemctl restart apache2
```

#### Nginx Configuration (Alternative)

Create `/etc/nginx/sites-available/thehub`:

```nginx
server {
    listen 80;
    server_name hub.yourdomain.com;
    root /var/www/thehub/public;
    index index.php;

    access_log /var/log/nginx/thehub_access.log;
    error_log /var/log/nginx/thehub_error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable and restart:

```bash
sudo ln -s /etc/nginx/sites-available/thehub /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 7: Configure SSL (Let's Encrypt)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache  # For Apache
# OR
sudo apt install certbot python3-certbot-nginx   # For Nginx

# Obtain certificate
sudo certbot --apache -d hub.yourdomain.com  # Apache
# OR
sudo certbot --nginx -d hub.yourdomain.com   # Nginx

# Auto-renewal (already configured by certbot)
sudo certbot renew --dry-run
```

---

## Optional Components

### Redis Caching (Recommended for Performance)

**Why?** 30x faster page loads, scales to 1000+ concurrent users

```bash
# Install Redis
sudo apt install redis-server

# Start and enable
sudo systemctl start redis-server
sudo systemctl enable redis-server

# Test
redis-cli ping  # Should return: PONG

# Configure in .env
nano .env
```

Add to `.env`:

```bash
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DATABASE=0
CACHE_PREFIX=thehub
```

**Automatic Fallback:** If Redis isn't available, TheHub automatically uses file-based caching.

### Python CLI Tools (Optional)

For code analysis and maintenance tasks:

```bash
# Verify Python 3 is installed
python3 --version  # Should be 3.6+

# No additional packages needed - uses standard library only
# Test tool
python3 cli/cleanup-analyzer.py --help
```

### Email Configuration

For user invitations and notifications:

**Gmail Setup:**
1. Create an app-specific password: https://myaccount.google.com/apppasswords
2. Add to `.env`:

```bash
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-specific-password
SMTP_ENCRYPTION=tls
```

**Other Providers:**
- **SendGrid:** smtp.sendgrid.net:587
- **Mailgun:** smtp.mailgun.org:587
- **Amazon SES:** email-smtp.us-east-1.amazonaws.com:587

---

## Verification

### Check Installation

```bash
# 1. PHP version and extensions
php -v
php -m | grep -E 'pdo|mysql|mbstring|curl|zip'

# 2. Composer is working
composer --version

# 3. Database connection
mysql -u thehub_user -p thehub -e "SELECT COUNT(*) FROM users;"

# 4. Web server is running
sudo systemctl status apache2   # or nginx

# 5. Redis is running (if installed)
redis-cli ping

# 6. File permissions
ls -la /var/www/thehub/uploads
ls -la /var/www/thehub/sessions
```

### Test Application

1. **Visit your site:** `https://hub.yourdomain.com`
2. **Create first user:** Should see registration/login page
3. **Login:** Try your credentials or OAuth
4. **Check admin panel:** Navigate to Admin → Site Settings

### Run Tests

```bash
cd /var/www/thehub

# Run test suite
vendor/bin/phpunit --testdox

# Should see 25 tests
# Expected: ~17 passing, ~8 DB-related errors (normal in dev)
```

---

## Troubleshooting

### Common Issues

#### "500 Internal Server Error"

**Check PHP error logs:**

```bash
tail -f /var/log/apache2/error.log
# or
tail -f /var/log/nginx/error.log
# or
tail -f /var/www/thehub/logs/php-errors.log
```

**Common causes:**
- Missing PHP extensions
- Wrong file permissions
- Syntax error in .env

#### "Database connection failed"

```bash
# Test database connection
mysql -u thehub_user -p thehub

# Verify credentials in .env
cat .env | grep DB_

# Check MySQL is running
sudo systemctl status mariadb
```

#### "Session errors" or "CSRF token mismatch"

```bash
# Clear sessions
rm -rf /var/www/thehub/sessions/*

# Check permissions
sudo chmod -R 775 /var/www/thehub/sessions
sudo chown -R www-data:www-data /var/www/thehub/sessions
```

#### OAuth login not working

**Google OAuth:**
- Verify redirect URI in Google Cloud Console exactly matches `.env`
- Check domain is authorized in OAuth consent screen
- Ensure API is enabled

**Microsoft OAuth:**
- Verify redirect URI in Azure AD app registration
- Check API permissions granted
- Use `common` tenant for multi-tenant

#### Composer install fails

```bash
# Clear cache and try again
composer clear-cache
composer install --no-cache

# If out of memory
php -d memory_limit=-1 /usr/local/bin/composer install
```

#### File upload errors

```bash
# Check PHP limits
php -i | grep -E 'upload_max_filesize|post_max_size'

# Edit php.ini
sudo nano /etc/php/8.3/apache2/php.ini

# Increase limits
upload_max_filesize = 50M
post_max_size = 50M

# Restart Apache
sudo systemctl restart apache2
```

### Getting Help

- **Documentation:** Check `/docs` folder
- **Logs:** Monitor `logs/php-errors.log`
- **GitHub Issues:** Report bugs with logs
- **Community:** [Coming soon]

---

## Post-Installation

### First Steps

1. **Create Super Admin:**
   - Register first account
   - Will automatically become super admin

2. **Configure Site Settings:**
   - Admin → Site Settings
   - Set organization name
   - Configure authentication options
   - Upload logo

3. **Invite Users:**
   - Admin → Users → Invite User
   - Send invitation emails
   - Users will receive signup link

4. **Install Packages:**
   - Admin → Packages
   - Browse available packages
   - Install modules you need

5. **Create Sections:**
   - Sections → Create New
   - Add custom data collections
   - Set role-based access

### Security Hardening

```bash
# 1. Restrict file permissions
sudo find /var/www/thehub -type d -exec chmod 755 {} \;
sudo find /var/www/thehub -type f -exec chmod 644 {} \;
sudo chmod -R 775 uploads/ sessions/ logs/ temp/

# 2. Disable directory listing (Apache)
echo "Options -Indexes" | sudo tee /var/www/thehub/public/.htaccess

# 3. Configure firewall
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp  # SSH
sudo ufw enable

# 4. Set up automated backups (recommended)
sudo crontab -e
# Add daily backup at 2 AM:
0 2 * * * bash /var/www/thehub/cli/db-maintenance.sh backup
```

### Performance Optimization

```bash
# 1. Enable PHP OPcache
sudo nano /etc/php/8.3/apache2/php.ini

# Add or uncomment:
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2

# 2. Restart Apache
sudo systemctl restart apache2

# 3. Install Redis (if not done already)
# See "Optional Components" section above

# 4. Configure database maintenance
sudo crontab -e
# Add weekly optimization at 3 AM Sunday:
0 3 * * 0 bash /var/www/thehub/cli/db-maintenance.sh optimize
```

### Monitoring

```bash
# Check application health
tail -f /var/www/thehub/logs/php-errors.log

# Monitor Redis (if installed)
redis-cli monitor

# Check database performance
mysql -u root -p -e "SHOW PROCESSLIST;"

# Monitor Apache/Nginx
sudo systemctl status apache2
# or
sudo systemctl status nginx

# Check disk usage
df -h
du -sh /var/www/thehub/uploads/*
```

---

## Upgrade Instructions

### Updating TheHub

```bash
cd /var/www/thehub

# 1. Backup first!
bash cli/db-maintenance.sh backup

# 2. Pull latest code
git pull origin main

# 3. Update dependencies
composer install --no-dev --optimize-autoloader

# 4. Run migrations
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php

# 5. Clear cache
rm -rf temp/cache/*

# 6. Restart web server
sudo systemctl restart apache2
```

---

## Uninstallation

```bash
# 1. Backup data first
bash cli/db-maintenance.sh backup

# 2. Drop database
mysql -u root -p -e "DROP DATABASE thehub; DROP USER 'thehub_user'@'localhost';"

# 3. Remove files
sudo rm -rf /var/www/thehub

# 4. Remove Apache config
sudo a2dissite thehub
sudo rm /etc/apache2/sites-available/thehub.conf
sudo systemctl reload apache2

# 5. Remove SSL certificate (optional)
sudo certbot delete --cert-name hub.yourdomain.com
```

---

## Use Cases

TheHub is **generic and flexible** - not just for schools:

### Education
- Student management, grades, attendance
- Course scheduling and assignments
- Parent-teacher communication

### Business
- HR management and employee evaluations
- Project tracking and workflows
- Client relationship management (CRM)
- Inventory and asset tracking

### Non-Profit
- Volunteer coordination
- Donor management
- Event planning and registration
- Grant tracking

### Personal/Hobby
- Collection management (books, movies, etc.)
- Home inventory and maintenance
- Personal knowledge base
- Family organization

### Government
- Permit tracking and approvals
- Citizen service requests
- Asset and facility management
- Document workflows

**Customize with packages** - Install only what you need!

---

## Next Steps

1. ✅ **Installation Complete**
2. 📚 Read [Module Architecture](docs/MODULAR_ARCHITECTURE.md)
3. 📦 Learn about [Package System](docs/PACKAGE_REPOSITORY_SYSTEM.md)
4. 🔐 Configure [Role Permissions](docs/ROLE_PERMISSIONS.md)
5. 🎨 Customize [Theme](docs/THEME_MANAGEMENT.md)
6. ⚡ Set up [Caching](docs/CACHING_SYSTEM.md)

---

**Version:** 1.0  
**Last Updated:** October 30, 2025  
**License:** MIT (or your chosen license)
