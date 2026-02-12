#!/bin/bash
# Database Maintenance Script
# Place in /var/www/woodson/thehub/cli/db-maintenance.sh
# Run weekly via cron: 0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh

LOG_FILE="/var/www/woodson/thehub/logs/db-maintenance.log"
DB_USER="$DB_USER"
DB_PASS="$DB_PASSWORD"
DB_NAME="woodson_hub"

echo "================================" >> $LOG_FILE
echo "Database Maintenance - $(date)" >> $LOG_FILE
echo "================================" >> $LOG_FILE

# 1. Optimize tables
echo "Optimizing tables..." >> $LOG_FILE
mysqlcheck -u $DB_USER -p$DB_PASS --optimize $DB_NAME >> $LOG_FILE 2>&1

# 2. Check for corruption
echo "Checking for corruption..." >> $LOG_FILE
mysqlcheck -u $DB_USER -p$DB_PASS --check $DB_NAME >> $LOG_FILE 2>&1

# 3. Analyze tables
echo "Analyzing tables..." >> $LOG_FILE
mysqlcheck -u $DB_USER -p$DB_PASS --analyze $DB_NAME >> $LOG_FILE 2>&1

# 4. Clean old sessions (older than 30 days)
echo "Cleaning old sessions..." >> $LOG_FILE
find /var/www/woodson/thehub/sessions -type f -mtime +30 -delete >> $LOG_FILE 2>&1

# 5. Clean old logs (keep 90 days)
echo "Rotating logs..." >> $LOG_FILE
find /var/www/woodson/thehub/logs -name "*.log" -type f -mtime +90 -delete >> $LOG_FILE 2>&1

# 6. Backup database
echo "Creating backup..." >> $LOG_FILE
BACKUP_FILE="/var/www/woodson/thehub/backups/db-$(date +%Y%m%d).sql"
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > ${BACKUP_FILE}.gz 2>> $LOG_FILE

# Keep only last 30 days of backups
find /var/www/woodson/thehub/backups -name "db-*.sql.gz" -type f -mtime +30 -delete

echo "Maintenance complete - $(date)" >> $LOG_FILE
echo "" >> $LOG_FILE
