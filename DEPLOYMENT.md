# Deployment Guide - Woodson ISD Vehicle Maintenance

This guide will walk you through deploying the Woodson ISD Vehicle Maintenance application on your Apache server.

## Prerequisites Checklist

- [ ] PHP 8.0 or higher installed
- [ ] Apache 2.4+ with mod_rewrite enabled
- [ ] MariaDB/MySQL installed
- [ ] Composer installed
- [ ] DNS record for maintenance.woodsonisd.net pointing to your server
- [ ] Google Cloud Console access for OAuth setup

## Step-by-Step Deployment

### 1. Verify PHP Version

```bash
php -v
```

Should show PHP 8.0 or higher. If not, install/upgrade PHP:

```bash
sudo apt update
sudo apt install php8.1 php8.1-cli php8.1-mysql php8.1-xml php8.1-mbstring php8.1-curl
```

### 2. Install Composer Dependencies

```bash
cd /var/www/woodson/maintenance
composer install
```

If Composer is not installed:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. Configure Environment

```bash
cp .env.example .env
nano .env
```

Update these values:
- `DB_USER` and `DB_PASS` with your database credentials
- `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` (see step 5)
- `SESSION_SECRET` with a random string: `openssl rand -base64 32`

### 4. Create Database

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE woodson_maintenance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'maintenance_user'@'localhost' IDENTIFIED BY 'YOUR_SECURE_PASSWORD';
GRANT ALL PRIVILEGES ON woodson_maintenance.* TO 'maintenance_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Update `.env` with the password you set above.

### 5. Run Database Migrations

```bash
php cli/migrate.php
```

### 6. Set Up Google OAuth

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project or select existing: "Woodson ISD Maintenance"
3. Navigate to "APIs & Services" > "Credentials"
4. Click "+ CREATE CREDENTIALS" > "OAuth 2.0 Client ID"
5. Configure consent screen if prompted:
   - User Type: Internal
   - App name: Woodson ISD Vehicle Maintenance
   - Support email: richard.sullivan@woodsonisd.net
6. Create OAuth 2.0 Client:
   - Application type: Web application
   - Name: Woodson Maintenance
   - Authorized redirect URIs: `https://maintenance.woodsonisd.net/auth/callback`
7. Copy the Client ID and Client Secret to `.env`

### 7. Create Required Directories

```bash
mkdir -p logs sessions temp uploads
touch logs/.gitkeep sessions/.gitkeep temp/.gitkeep uploads/.gitkeep
```

### 8. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/woodson/maintenance
sudo chmod -R 755 /var/www/woodson/maintenance
sudo chmod -R 775 /var/www/woodson/maintenance/logs
sudo chmod -R 775 /var/www/woodson/maintenance/sessions
sudo chmod -R 775 /var/www/woodson/maintenance/temp
sudo chmod -R 775 /var/www/woodson/maintenance/uploads
```

### 9. Configure Apache

```bash
# Enable required modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers

# Copy virtual host configuration
sudo cp apache/maintenance.woodsonisd.net.conf /etc/apache2/sites-available/

# Enable the site
sudo a2ensite maintenance.woodsonisd.net

# Test configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

### 10. Set Up SSL with Certbot

```bash
# Install Certbot if not already installed
sudo apt install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d maintenance.woodsonisd.net
```

Follow the prompts. Certbot will automatically:
- Obtain the certificate
- Update your Apache configuration
- Set up auto-renewal

### 11. Verify Installation

1. Visit `https://maintenance.woodsonisd.net`
2. You should see the landing page
3. Click "Sign in with Google"
4. Log in with richard.sullivan@woodsonisd.net
5. You should be automatically assigned Super Admin role
6. Access the admin dashboard

### 12. Test Functionality

As Super Admin:
- [ ] Add a test vehicle
- [ ] Create a test fuel entry
- [ ] View entries in admin dashboard
- [ ] Export data to CSV/Excel
- [ ] Add another user and assign roles

## Troubleshooting

### "500 Internal Server Error"

Check Apache error log:
```bash
sudo tail -f /var/log/apache2/maintenance.woodsonisd.net-error.log
```

Common issues:
- PHP version too old
- Missing PHP extensions
- Permission issues
- Database connection failed

### Google OAuth Not Working

- Verify redirect URI exactly matches in Google Console
- Check that domain is accessible externally
- Ensure HTTPS is working

### Database Connection Failed

```bash
# Test database connection
php -r "new PDO('mysql:host=localhost;dbname=woodson_maintenance', 'maintenance_user', 'password');"
```

### Session Issues

```bash
# Verify sessions directory is writable
ls -la sessions/
sudo chmod 775 sessions/
sudo chown www-data:www-data sessions/
```

## Maintenance Tasks

### Database Maintenance Tools

The Hub includes automated database maintenance tools for optimal performance and reliability.

#### Install Required Tools

```bash
# MySQLTuner - Performance analysis and recommendations
cd /var/www/woodson/thehub/cli
wget https://raw.githubusercontent.com/major/MySQLTuner-perl/master/mysqltuner.pl
chmod +x mysqltuner.pl

# Percona Toolkit - Advanced database diagnostics
sudo apt-get update
sudo apt-get install -y percona-toolkit
```

#### Run MySQLTuner

Analyze database performance and get tuning recommendations:

```bash
cd /var/www/woodson/thehub/cli
./mysqltuner.pl --host 127.0.0.1 --user WISDAdmin --pass '$DB_PASSWORD'
```

Review the output for:
- Memory usage optimization
- Query cache settings
- Table index recommendations
- Connection pool sizing
- InnoDB buffer pool tuning

#### Set Up Automated Maintenance

The Hub includes `cli/db-maintenance.sh` for weekly automated maintenance:

**What it does:**
- Optimizes all tables
- Checks for corruption
- Analyzes table statistics
- Cleans old session files (30+ days)
- Rotates logs (90+ days)
- Creates compressed database backups
- Maintains 30-day backup retention

**Schedule with cron:**

```bash
# Edit crontab
crontab -e

# Add this line to run every Sunday at 3 AM
0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh

# Verify cron job
crontab -l
```

**Manual execution:**

```bash
# Run maintenance script manually
/var/www/woodson/thehub/cli/db-maintenance.sh

# View maintenance logs
tail -f /var/www/woodson/thehub/logs/db-maintenance.log
```

**Backup location:**

```bash
# Backups are stored in logs/ directory
ls -lh /var/www/woodson/thehub/logs/backup_*.sql.gz

# Restore from backup
gunzip -c logs/backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u WISDAdmin -p'$DB_PASSWORD' woodson_hub
```

#### Percona Toolkit Commands

Advanced diagnostics when needed:

```bash
# Find duplicate indexes
pt-duplicate-key-checker --host=localhost --user=WISDAdmin --password='$DB_PASSWORD'

# Analyze slow queries
pt-query-digest /var/log/mysql/mysql-slow.log

# Check table fragmentation
pt-online-schema-change --host=localhost --user=WISDAdmin --password='$DB_PASSWORD' \
  --alter "ENGINE=InnoDB" D=woodson_hub,t=your_table --execute

# Find unused indexes
pt-index-usage /var/log/mysql/mysql-slow.log
```

### View Logs

```bash
# Application logs
tail -f logs/php-errors.log

# Database maintenance logs
tail -f logs/db-maintenance.log

# Apache logs
sudo tail -f /var/log/apache2/maintenance.woodsonisd.net-error.log
sudo tail -f /var/log/apache2/maintenance.woodsonisd.net-access.log
```

### Manual Database Backup

```bash
# Create backup
mysqldump -u WISDAdmin -p'$DB_PASSWORD' woodson_hub > backup_$(date +%Y%m%d_%H%M%S).sql

# Create compressed backup
mysqldump -u WISDAdmin -p'$DB_PASSWORD' woodson_hub | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz

# Restore from backup
mysql -u WISDAdmin -p'$DB_PASSWORD' woodson_hub < backup_YYYYMMDD_HHMMSS.sql

# Restore from compressed backup
gunzip -c backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u WISDAdmin -p'$DB_PASSWORD' woodson_hub
```

### Update Application

```bash
cd /var/www/woodson/maintenance
git pull  # If using git
composer install
php cli/migrate.php  # Run any new migrations
sudo systemctl reload apache2
```

## Security Recommendations

1. **Regular Updates**: Keep PHP, Apache, and dependencies updated
2. **Database Backups**: Set up automated daily backups
3. **Monitor Logs**: Review logs regularly for suspicious activity
4. **SSL Certificate**: Certbot auto-renews, but monitor expiration
5. **User Access**: Regularly review user permissions and remove inactive accounts

## Support

For issues or questions:
- Contact: richard.sullivan@woodsonisd.net
- Check logs first for error details
- Review README.md for usage instructions
