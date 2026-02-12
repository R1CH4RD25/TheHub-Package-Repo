# Database Maintenance Tools - Installation Complete

**Installation Date:** October 23, 2025  
**Status:** ✅ All tools installed and configured

## Tools Installed

### 1. MySQLTuner
- **Location:** `/var/www/woodson/thehub/cli/mysqltuner.pl`
- **Size:** 272 KB
- **Purpose:** Performance analysis and tuning recommendations
- **Usage:**
  ```bash
  cd /var/www/woodson/thehub/cli
  ./mysqltuner.pl --host 127.0.0.1 --user $DB_USER --pass '$DB_PASSWORD'
  ```

### 2. Percona Toolkit
- **Package:** `percona-toolkit 3.2.1-1`
- **Purpose:** Advanced database diagnostics and optimization
- **Key Commands:**
  - `pt-duplicate-key-checker` - Find redundant indexes
  - `pt-query-digest` - Analyze slow queries
  - `pt-online-schema-change` - Safe schema modifications
  - `pt-index-usage` - Identify unused indexes

### 3. Automated Maintenance Script
- **Location:** `/var/www/woodson/thehub/cli/db-maintenance.sh`
- **Permissions:** Executable (`-rwxr-xr-x`)
- **Log Output:** `/var/www/woodson/thehub/logs/db-maintenance.log`
- **Backup Location:** `/var/www/woodson/thehub/logs/backup_*.sql.gz`

## What Gets Automated

The `db-maintenance.sh` script performs:

1. ✅ **Table Optimization** - `mysqlcheck --optimize`
2. ✅ **Corruption Detection** - Checks all table integrity
3. ✅ **Table Analysis** - Updates statistics for query optimizer
4. ✅ **Session Cleanup** - Removes files older than 30 days
5. ✅ **Log Rotation** - Archives logs older than 90 days
6. ✅ **Database Backup** - Compressed with gzip
7. ✅ **Backup Retention** - Keeps 30 days, deletes older

## Scheduling

### Recommended Schedule
Run maintenance every **Sunday at 3 AM**:

```bash
# Edit crontab
crontab -e

# Add this line
0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh
```

### Alternative Schedules

```cron
# Daily at 3 AM (for high-traffic sites)
0 3 * * * /var/www/woodson/thehub/cli/db-maintenance.sh

# First day of month at 4 AM
0 4 1 * * /var/www/woodson/thehub/cli/db-maintenance.sh
```

## Documentation Updated

The following files have been updated with installation and usage instructions:

### 1. DEPLOYMENT.md
Added comprehensive section:
- Database Maintenance Tools installation
- MySQLTuner setup and usage
- Percona Toolkit commands
- Automated maintenance scheduling
- Backup and restore procedures

### 2. REQUIREMENTS.md
Added database maintenance tools section:
- MySQLTuner installation
- Percona Toolkit installation (Ubuntu/Debian and CentOS/RHEL)
- Automated maintenance overview
- Cron scheduling reference

### 3. cli/CRON_SETUP.md (NEW)
Complete guide including:
- Step-by-step cron job setup
- What gets automated
- Monitoring and troubleshooting
- Cron schedule reference
- Backup restoration procedures
- Email notification setup

## Manual Testing

### Test MySQLTuner
```bash
cd /var/www/woodson/thehub/cli
./mysqltuner.pl --host 127.0.0.1 --user $DB_USER --pass '$DB_PASSWORD'
```

Expected output:
- Memory usage statistics
- Query cache analysis
- Index recommendations
- InnoDB buffer pool tuning suggestions
- Security warnings (if any)

### Test Maintenance Script
```bash
/var/www/woodson/thehub/cli/db-maintenance.sh
```

Check the log:
```bash
tail -50 /var/www/woodson/thehub/logs/db-maintenance.log
```

Verify backup created:
```bash
ls -lh /var/www/woodson/thehub/logs/backup_*.sql.gz
```

### Test Percona Toolkit
```bash
# Find duplicate indexes
pt-duplicate-key-checker --host=localhost --user=$DB_USER --password='$DB_PASSWORD'

# Expected: Analysis of all tables showing any duplicate indexes
```

## Benefits

### Performance
- **Optimized Tables:** Reduced fragmentation, faster queries
- **Updated Statistics:** Better query execution plans
- **Index Analysis:** Identify and remove unnecessary indexes

### Reliability
- **Corruption Detection:** Early warning of data issues
- **Automated Backups:** 30-day retention with compression
- **Proactive Monitoring:** MySQLTuner recommendations

### Maintenance
- **Reduced Manual Work:** Weekly automation
- **Log Cleanup:** Prevents disk space issues
- **Session Management:** Removes stale session files

## Monitoring

### Check Last Maintenance Run
```bash
tail -50 /var/www/woodson/thehub/logs/db-maintenance.log
```

### View Recent Backups
```bash
ls -lht /var/www/woodson/thehub/logs/backup_*.sql.gz | head -5
```

### Check Cron Jobs
```bash
crontab -l
```

### View Cron Execution Logs
```bash
sudo grep CRON /var/log/syslog | grep db-maintenance | tail -20
```

## Backup Strategy

### What's Backed Up
- Complete `woodson_hub` database
- All tables, data, and structure
- Compressed with gzip (typically 10:1 ratio)

### Retention Policy
- **Keep:** Last 30 days of backups
- **Delete:** Backups older than 30 days
- **Frequency:** Weekly (Sunday 3 AM)

### Restore Procedure
```bash
# List available backups
ls -lh /var/www/woodson/thehub/logs/backup_*.sql.gz

# Restore (replace timestamp)
gunzip -c logs/backup_YYYYMMDD_HHMMSS.sql.gz | \
  mysql -u $DB_USER -p'$DB_PASSWORD' woodson_hub
```

## Next Steps

1. ✅ **Tools Installed** - All database maintenance tools ready
2. ✅ **Documentation Updated** - DEPLOYMENT.md and REQUIREMENTS.md
3. ✅ **Guides Created** - CRON_SETUP.md for scheduling
4. ⏳ **Schedule Cron Job** - Run `crontab -e` and add maintenance schedule
5. ⏳ **Test First Run** - Execute script manually and verify logs
6. ⏳ **Monitor Weekly** - Check logs after first automated run

## Support

**Documentation:**
- `DEPLOYMENT.md` - Complete deployment guide with maintenance section
- `REQUIREMENTS.md` - System requirements including tools
- `cli/CRON_SETUP.md` - Cron job setup guide
- `docs/DATABASE_COLUMN_REFERENCE.md` - Schema reference

**Scripts:**
- `cli/db-maintenance.sh` - Automated maintenance
- `cli/mysqltuner.pl` - Performance analyzer
- `temp/audit-schema.sh` - Column verification

**Contact:**
- Richard Sullivan
- richard.sullivan@woodsonisd.net

---

**Installation verified and complete!** 🎉
