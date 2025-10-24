# The Hub - Installation Summary & Defaults

## 📋 Quick Answer to Common Questions

### "What's the default admin username/password?"
**There isn't one!** You create it during setup:

```bash
php cli/setup.php
```

This interactive script will ask you to create:
- ✅ Email
- ✅ Full name  
- ✅ Username (you choose, e.g., `admin`)
- ✅ Password (min 8 chars, uppercase, lowercase, number)

### "What's the default database name?"
```bash
DB_NAME=thehub
```
But you can name it anything! Common alternatives:
- `thehub`
- `hub`
- `maintenance`
- `yourcompany_hub`

### "What are the default database credentials?"
**You create these!** Example:
```bash
DB_USER=thehub_user
DB_PASSWORD=YourSecurePasswordHere123!
```

**To create:**
```sql
CREATE DATABASE thehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'thehub_user'@'localhost' IDENTIFIED BY 'YourSecurePasswordHere123!';
GRANT ALL PRIVILEGES ON thehub.* TO 'thehub_user'@'localhost';
FLUSH PRIVILEGES;
```

### "What database engine do we use?"
**Recommended:** MariaDB 10.5+

**Also compatible:**
- MySQL 8.0+
- Percona Server 8.0+

**Why MariaDB?**
- ✅ Open source (GPLv2)
- ✅ Better performance than MySQL
- ✅ Drop-in MySQL replacement
- ✅ Active development
- ✅ Wide OS support

### "Do we have installation requirements documented?"
**Yes!** See [`REQUIREMENTS.md`](REQUIREMENTS.md)

**Includes:**
- System requirements (OS, hardware)
- Web server setup (Apache/Nginx)
- Database requirements
- PHP version & extensions
- SSL/TLS configuration
- Network & firewall
- Verification checklist

### "Is there an Apache config example?"
**Yes!** Two locations:

1. **Example template:**
   - File: `apache/hub.example.com.conf`
   - Full configuration with security headers
   - SSL/TLS best practices
   - Performance optimizations
   - Copy and customize for your domain

2. **Current production config:**
   - File: `apache/hub.woodsonisd.net.conf`
   - Working configuration for Woodson ISD
   - Simpler version

### "Do we document SSL setup requirements?"
**Yes!** Multiple places:

1. **REQUIREMENTS.md** - SSL prerequisites
2. **QUICKSTART.md** - Let's Encrypt setup
3. **DEPLOYMENT.md** - Detailed SSL configuration
4. **Apache config** - SSL headers and settings

**Recommended:** Let's Encrypt (free, auto-renewing)
```bash
sudo certbot --apache -d hub.yourdomain.com
```

---

## 📁 Complete File List

### Documentation Files
```
REQUIREMENTS.md          ← System requirements & prerequisites
QUICKSTART.md           ← Fast 10-step installation guide
DEPLOYMENT.md           ← Detailed deployment guide
README.md               ← Project overview
.env.example            ← Environment configuration template

docs/
├── GOOGLE_GROUPS_SETUP.md          ← Google Workspace integration
├── CASCADING_DEPENDENCIES*.md      ← Feature dependency system
└── [other feature docs]
```

### Configuration Examples
```
apache/
├── hub.example.com.conf        ← Template for new installations
└── hub.woodsonisd.net.conf     ← Production example (Woodson ISD)

.env.example                    ← All environment variables with examples
```

### Setup Scripts
```
cli/
├── setup.php                   ← Interactive first admin creation
├── migrate.php                 ← Core database schema
├── migrate-modules.php         ← Modules schema
└── migrate-sections.php        ← Sections schema
```

### Database
```
database/
├── schema.sql                  ← Core tables (users, vehicles, etc.)
├── modules-schema.sql          ← Modules system
├── sections-schema.sql         ← Sections/navigation
└── migrations/
    └── 001_add_local_auth_support.sql  ← Local login support
```

---

## 🎯 Default Values Reference

### Application Defaults (.env)
```bash
# Database
DB_HOST=localhost
DB_PORT=3306                    # Usually not needed
DB_NAME=thehub                  # Your choice
DB_USER=thehub_user             # Your choice
DB_PASSWORD=                    # You must set this!

# Application
APP_URL=https://hub.yourdomain.com  # Change to your domain
APP_ENV=production
DEBUG_MODE=false
MAX_UPLOAD_SIZE=10              # MB
MAINTENANCE_MODE=false

# Authentication
ALLOW_LOCAL_USERS=true
ENABLE_GOOGLE_LOGIN=false       # Set true if using Google OAuth
ENABLE_MICROSOFT_LOGIN=false    # Set true if using Microsoft OAuth
REQUIRE_DOMAIN_MATCH=false
ALLOWED_DOMAINS=
SESSION_TIMEOUT=2               # Hours

# Security
SESSION_SECRET=                 # Generate with: openssl rand -base64 32
SESSION_LIFETIME=7200           # Seconds (2 hours)

# Super Admin
SUPER_ADMIN_EMAIL=              # Your email (for OAuth auto-admin)
```

### No Default Credentials!
**Super Admin Account:**
- ❌ No default username
- ❌ No default password
- ✅ Created during `php cli/setup.php`
- ✅ You choose all credentials

**Database Account:**
- ❌ No default user
- ❌ No default password  
- ✅ Created during MySQL/MariaDB setup
- ✅ You choose all credentials

**Why no defaults?**
- 🔒 Security best practice
- 🔒 Prevents forgotten default passwords
- 🔒 Forces strong credential creation
- 🔒 Each installation is unique

---

## 🚀 Installation Steps (Overview)

### 1. Prerequisites
```bash
# Install required software
- Apache 2.4+ OR Nginx 1.18+
- MariaDB 10.5+ OR MySQL 8.0+
- PHP 8.0+
- Composer
- Git

# See REQUIREMENTS.md for details
```

### 2. Clone Repository
```bash
git clone https://github.com/yourusername/thehub.git
cd thehub
composer install
```

### 3. Configure Environment
```bash
cp .env.example .env
nano .env  # Edit database credentials, APP_URL, etc.
```

### 4. Create Database
```bash
sudo mysql -u root -p

CREATE DATABASE thehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'thehub_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';
GRANT ALL PRIVILEGES ON thehub.* TO 'thehub_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 5. Run Migrations
```bash
php cli/migrate.php
php cli/migrate-modules.php
php cli/migrate-sections.php
```

### 6. Create Super Admin
```bash
php cli/setup.php

# Follow prompts:
Email address: admin@yourdomain.com
Full name: John Admin
Username: admin
Password: [secure password]
```

### 7. Configure Web Server
```bash
# Copy example config
sudo cp apache/hub.example.com.conf /etc/apache2/sites-available/hub.yourdomain.com.conf

# Edit for your domain
sudo nano /etc/apache2/sites-available/hub.yourdomain.com.conf

# Enable site
sudo a2ensite hub.yourdomain.com
sudo systemctl reload apache2
```

### 8. Get SSL Certificate
```bash
sudo certbot --apache -d hub.yourdomain.com
```

### 9. Set Permissions
```bash
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 logs sessions temp uploads
```

### 10. Login!
```
https://hub.yourdomain.com

Username: admin (or whatever you set)
Password: [your password]
```

---

## 🔍 Verification Commands

### Check PHP Version
```bash
php -v
# Should show 8.0 or higher
```

### Check PHP Extensions
```bash
php -m | grep -E 'pdo_mysql|mbstring|xml|curl|zip|gd|intl|bcmath'
# Should show all extensions
```

### Check Database
```bash
sudo systemctl status mariadb
# Should show "active (running)"
```

### Check Web Server
```bash
sudo systemctl status apache2  # or nginx
# Should show "active (running)"
```

### Test Database Connection
```bash
php -r "new PDO('mysql:host=localhost;dbname=thehub', 'thehub_user', 'YourPassword');"
# No output = success
```

### Check SSL
```bash
curl -I https://hub.yourdomain.com
# Should show "HTTP/2 200" or "HTTP/1.1 200"
```

---

## 📞 Getting Help

**Installation Issues:**
1. Check `REQUIREMENTS.md` - Verify all prerequisites
2. Check `QUICKSTART.md` - Follow step-by-step
3. Check logs: `tail -f logs/php-errors.log`
4. Check web server logs: `sudo tail -f /var/log/apache2/error.log`

**Common Problems:**

**"Can't connect to database"**
```bash
# Verify credentials
grep DB_ .env

# Test connection
mysql -u thehub_user -p thehub
```

**"500 Internal Server Error"**
```bash
# Check PHP errors
tail -f logs/php-errors.log

# Check permissions
ls -la logs/ sessions/ temp/ uploads/
# Should be owned by www-data
```

**"OAuth not working"**
- Verify redirect URI matches exactly
- Check HTTPS is working
- Ensure OAuth app is published (not in testing mode)

**"Setup script fails"**
```bash
# Check migrations ran
mysql -u thehub_user -p thehub -e "SHOW TABLES;"
# Should show users, vehicles, etc.

# Check PHP has database access
php -m | grep pdo_mysql
```

---

## 📚 Next Steps After Installation

1. **Configure Site Settings**
   - Admin Panel → Site Settings → Branding
   - Set organization name, logo, colors

2. **Enable OAuth (Optional)**
   - Admin Panel → Site Settings → Advanced
   - Configure Google or Microsoft OAuth

3. **Add Vehicles**
   - Admin Dashboard → Vehicles → Add Vehicle

4. **Invite Users**
   - Admin Dashboard → Users → Invite User

5. **Configure Modules**
   - Admin Dashboard → Modules
   - Enable needed features

6. **Set Up Backups**
   - Database: `mysqldump thehub > backup.sql`
   - Files: `tar -czf backup.tar.gz /var/www/thehub`

---

## ✅ Complete Installation Checklist

- [ ] Read `REQUIREMENTS.md`
- [ ] Install Apache/Nginx
- [ ] Install MariaDB/MySQL
- [ ] Install PHP 8.0+
- [ ] Install Composer
- [ ] Clone repository
- [ ] Run `composer install`
- [ ] Copy `.env.example` to `.env`
- [ ] Edit `.env` with your settings
- [ ] Create database & user
- [ ] Run migrations (3 scripts)
- [ ] Run `php cli/setup.php`
- [ ] Configure web server
- [ ] Get SSL certificate
- [ ] Set file permissions
- [ ] Test login
- [ ] Configure site settings
- [ ] Add first vehicle (test)
- [ ] Submit test fuel entry
- [ ] Invite another user (test)
- [ ] Configure backups
- [ ] Document your credentials (securely!)

---

**🎉 That's everything you need to know about installation & defaults!**
