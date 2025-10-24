# The Hub - System Requirements & Installation Prerequisites

## 🚀 Quick Install (Automated)

**For Ubuntu/Debian or CentOS/RHEL/Rocky/Alma:**

```bash
# Download and run automated installer
sudo bash install-packages.sh
```

This script will:
- ✅ Detect your operating system
- ✅ Install all required packages
- ✅ Enable Apache modules
- ✅ Start services
- ✅ Configure firewall

**Or install manually** using the package list in [`packages.txt`](packages.txt)

---

## Server Requirements

### Operating System
- **Recommended:** Ubuntu 20.04 LTS or 22.04 LTS
- **Also Compatible:** Debian 10+, CentOS 8+, Rocky Linux 8+, AlmaLinux 8+
- **Windows:** Windows Server 2016+ (with WSL2 recommended)

### Web Server
Choose **one** of the following:

#### Apache 2.4+ (Recommended)
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install apache2

# CentOS/RHEL/Rocky/Alma
sudo yum install httpd
sudo systemctl enable httpd
```

**Required Apache Modules:**
- `mod_rewrite` (URL rewriting)
- `mod_ssl` (HTTPS support)
- `mod_headers` (Security headers)
- `mod_dir` (Directory indexing)

```bash
# Enable modules (Ubuntu/Debian)
sudo a2enmod rewrite ssl headers dir
sudo systemctl restart apache2
```

#### Nginx 1.18+ (Alternative)
```bash
# Ubuntu/Debian
sudo apt install nginx

# CentOS/RHEL
sudo yum install nginx
sudo systemctl enable nginx
```

### Database

#### MariaDB 10.5+ (Recommended)
```bash
# Ubuntu/Debian
sudo apt install mariadb-server mariadb-client

# CentOS/RHEL
sudo yum install mariadb-server mariadb
sudo systemctl enable mariadb
sudo systemctl start mariadb

# Secure installation
sudo mysql_secure_installation
```

**OR**

#### MySQL 8.0+
```bash
# Ubuntu/Debian
sudo apt install mysql-server mysql-client

# CentOS/RHEL
sudo yum install mysql-server
sudo systemctl enable mysqld
sudo systemctl start mysqld
```

**Database Configuration Requirements:**
- **Character Set:** `utf8mb4`
- **Collation:** `utf8mb4_unicode_ci`
- **Minimum Storage:** 100 MB (grows with usage)
- **Recommended Storage:** 1 GB+ for production

#### Database Maintenance Tools (Recommended)

**MySQLTuner** - Performance analysis and tuning recommendations:
```bash
cd /var/www/woodson/thehub/cli
wget https://raw.githubusercontent.com/major/MySQLTuner-perl/master/mysqltuner.pl
chmod +x mysqltuner.pl
```

**Percona Toolkit** - Advanced database diagnostics:
```bash
# Ubuntu/Debian
sudo apt-get install percona-toolkit

# CentOS/RHEL
sudo yum install https://repo.percona.com/yum/percona-release-latest.noarch.rpm
sudo yum install percona-toolkit
```

**Automated Maintenance** - The Hub includes `cli/db-maintenance.sh` for:
- Weekly table optimization
- Corruption checks
- Automated backups with 30-day retention
- Session and log cleanup

Schedule via cron (see DEPLOYMENT.md for setup instructions):
```bash
# Run every Sunday at 3 AM
0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh
```

### PHP 8.0 or Higher

#### Install PHP
```bash
# Ubuntu/Debian (20.04+)
sudo apt install php8.1 php8.1-cli php8.1-fpm

# Ubuntu 18.04 (add PPA first)
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.1 php8.1-cli php8.1-fpm

# CentOS/RHEL 8+
sudo dnf install php php-cli php-fpm
```

#### Required PHP Extensions
```bash
# Ubuntu/Debian
sudo apt install \
    php8.1-mysql \
    php8.1-mbstring \
    php8.1-xml \
    php8.1-curl \
    php8.1-zip \
    php8.1-gd \
    php8.1-intl \
    php8.1-bcmath

# CentOS/RHEL
sudo dnf install \
    php-mysqlnd \
    php-mbstring \
    php-xml \
    php-curl \
    php-zip \
    php-gd \
    php-intl \
    php-bcmath
```

**PHP Extension Summary:**
- ✅ `pdo_mysql` - Database connectivity
- ✅ `mbstring` - Multibyte string handling
- ✅ `xml` - XML processing
- ✅ `curl` - HTTP requests (OAuth, APIs)
- ✅ `zip` - Excel export functionality
- ✅ `gd` OR `imagick` - Image processing
- ✅ `intl` - Internationalization
- ✅ `bcmath` - Precise decimal calculations
- ✅ `json` - JSON handling (usually built-in)
- ✅ `openssl` - Encryption (usually built-in)

#### Verify PHP Installation
```bash
php -v  # Should show 8.0 or higher
php -m  # List all installed modules
```

#### Recommended php.ini Settings
```ini
; File uploads
upload_max_filesize = 10M
post_max_size = 10M
max_file_uploads = 20

; Memory & execution
memory_limit = 256M
max_execution_time = 300
max_input_time = 300

; Error handling (production)
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
log_errors = On
error_log = /var/www/woodson/thehub/logs/php-errors.log

; Session
session.cookie_httponly = 1
session.cookie_secure = 1  ; Only if using HTTPS
session.cookie_samesite = "Strict"
session.gc_maxlifetime = 7200

; Timezone
date.timezone = America/Chicago  ; Set to your timezone
```

**Location of php.ini:**
```bash
php --ini  # Shows loaded config files
# Usually: /etc/php/8.1/apache2/php.ini or /etc/php/8.1/fpm/php.ini
```

### Composer (PHP Dependency Manager)

#### Install Composer
```bash
# Download and install globally
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify installation
composer --version  # Should show 2.0+
```

**OR** use system package:
```bash
# Ubuntu/Debian
sudo apt install composer

# May be older version - global install recommended
```

### SSL Certificate (HTTPS)

#### Let's Encrypt (Free, Recommended)
```bash
# Ubuntu/Debian
sudo apt install certbot python3-certbot-apache

# CentOS/RHEL
sudo yum install certbot python3-certbot-apache

# Get certificate
sudo certbot --apache -d hub.yourdomain.com

# Auto-renewal is configured automatically
# Test renewal:
sudo certbot renew --dry-run
```

#### Alternative SSL Options
- **Cloudflare** - Free SSL proxy
- **Self-signed** - Development/testing only
- **Commercial** - DigiCert, Sectigo, etc.

### Git (For installation & updates)
```bash
# Ubuntu/Debian
sudo apt install git

# CentOS/RHEL
sudo yum install git

# Verify
git --version  # Should show 2.0+
```

---

## Default Configuration Values

### Database Defaults
```bash
# Default database name
DB_NAME=thehub

# Default user (you create this)
DB_USER=thehub_user

# Default password (you MUST change this)
DB_PASSWORD=your_secure_password_here

# Default host
DB_HOST=localhost

# Default port (usually not needed)
DB_PORT=3306
```

### Admin Account
**There is NO default username/password!**

After installation, you **must** run the setup script:
```bash
php cli/setup.php
```

This will prompt you to create:
- ✅ Email address
- ✅ Full name
- ✅ Username (for local login)
- ✅ Secure password (min 8 chars, uppercase, lowercase, number)

The account will have `super_admin` role with full system access.

### Application Defaults
```bash
# Default URL (change to your domain)
APP_URL=https://hub.yourdomain.com

# Default environment
APP_ENV=production

# Default debugging (OFF in production)
DEBUG_MODE=false

# Default upload size (MB)
MAX_UPLOAD_SIZE=10

# Default session timeout (hours)
SESSION_TIMEOUT=2
```

---

## Minimum Hardware Requirements

### Development/Testing
- **CPU:** 1 core
- **RAM:** 512 MB
- **Storage:** 5 GB
- **Users:** 1-10

### Small Production (<50 users)
- **CPU:** 2 cores
- **RAM:** 2 GB
- **Storage:** 20 GB
- **Users:** 10-50

### Medium Production (50-200 users)
- **CPU:** 4 cores
- **RAM:** 4 GB
- **Storage:** 50 GB
- **Users:** 50-200

### Large Production (200+ users)
- **CPU:** 8+ cores
- **RAM:** 8+ GB
- **Storage:** 100+ GB
- **Users:** 200+

**Notes:**
- Storage grows with uploaded files and database records
- RAM usage increases with concurrent users
- Database can be on separate server for scaling

---

## Network Requirements

### Ports
- **80** (HTTP) - Redirects to HTTPS
- **443** (HTTPS) - Main application
- **3306** (MySQL/MariaDB) - If database is remote (close this if local)
- **22** (SSH) - Server administration

### Firewall Configuration
```bash
# Ubuntu/Debian (ufw)
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable

# CentOS/RHEL (firewalld)
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --reload
```

### DNS Requirements
Point your domain to the server:
```
Type: A Record
Host: hub (or @)
Value: YOUR_SERVER_IP
TTL: 3600
```

**Verify DNS:**
```bash
nslookup hub.yourdomain.com
# Should show your server IP
```

---

## Optional Requirements

### Google OAuth (Optional but Recommended)
- Google Cloud Project
- OAuth 2.0 Client ID & Secret
- See: `docs/GOOGLE_GROUPS_SETUP.md`

### Microsoft OAuth (Optional)
- Azure Active Directory
- App Registration
- See: `docs/MICROSOFT_OAUTH.md` (coming soon)

### Email (SMTP) - Optional
For sending invitation emails, notifications:
- SMTP server (Gmail, SendGrid, AWS SES, etc.)
- SMTP credentials

### Google Workspace Groups (Optional)
For auto-role assignment:
- Google Workspace account
- Service Account with Domain Delegation
- See: `docs/GOOGLE_GROUPS_SETUP.md`

---

## Verification Checklist

Before installing The Hub, verify:

**System:**
- [ ] Ubuntu 20.04+ / Debian 10+ / CentOS 8+ / Rocky 8+
- [ ] Root or sudo access

**Web Server:**
- [ ] Apache 2.4+ OR Nginx 1.18+
- [ ] `mod_rewrite`, `mod_ssl`, `mod_headers` enabled (Apache)

**Database:**
- [ ] MariaDB 10.5+ OR MySQL 8.0+
- [ ] Database service running: `sudo systemctl status mariadb`

**PHP:**
- [ ] PHP 8.0 or higher: `php -v`
- [ ] All required extensions installed: `php -m`
- [ ] php.ini configured (upload size, memory, etc.)

**Composer:**
- [ ] Composer installed: `composer --version`

**SSL:**
- [ ] Domain points to server: `nslookup hub.yourdomain.com`
- [ ] Certbot installed for Let's Encrypt

**Git:**
- [ ] Git installed: `git --version`

**Permissions:**
- [ ] Can create directories in `/var/www/`
- [ ] Can modify Apache/Nginx configs
- [ ] Can restart web server

---

## Quick Installation Test

Run this one-liner to check all requirements:

```bash
php -v && \
mysql --version && \
apache2 -v && \
composer --version && \
git --version && \
echo "✅ All prerequisites found!"
```

---

## Next Steps

Once all requirements are met:

1. **Read:** [`QUICKSTART.md`](QUICKSTART.md) for installation steps
2. **Or:** [`DEPLOYMENT.md`](DEPLOYMENT.md) for detailed deployment guide
3. **Configure:** Apache/Nginx virtual host
4. **Install:** Run `composer install`
5. **Setup:** Run `php cli/setup.php` to create first admin
6. **Launch:** Visit your Hub URL

---

## Getting Help

**Requirements Issues:**
- PHP version: Check with `php -v`
- Missing extensions: `php -m` to list installed
- Database: `sudo systemctl status mariadb`

**Installation Issues:**
- See `QUICKSTART.md` troubleshooting section
- Check logs: `tail -f logs/php-errors.log`

**Documentation:**
- `README.md` - Overview
- `QUICKSTART.md` - Fast setup
- `DEPLOYMENT.md` - Detailed deployment
- `docs/` - Feature-specific guides
