# Database Maintenance Cron Job Setup

## Quick Setup

### 1. Open Crontab Editor
```bash
crontab -e
```

### 2. Add Maintenance Job

Add this line to run database maintenance every Sunday at 3 AM:

```cron
0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh
```

### 3. Save and Exit
- **nano:** Press `Ctrl+X`, then `Y`, then `Enter`
- **vim:** Press `Esc`, type `:wq`, press `Enter`

### 4. Verify Job is Scheduled
```bash
crontab -l
```

You should see:
```
0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh
```

## What Gets Automated

The weekly maintenance script performs:

1. **Table Optimization** - Defragments and optimizes all tables
2. **Corruption Checks** - Verifies table integrity
3. **Table Analysis** - Updates statistics for query optimizer
4. **Session Cleanup** - Removes sessions older than 30 days
5. **Log Rotation** - Archives logs older than 90 days
6. **Database Backup** - Creates compressed backup (30-day retention)

## Monitoring

### View Last Run
```bash
tail -50 /var/www/woodson/thehub/logs/db-maintenance.log
```

### Check Backup Files
```bash
ls -lh /var/www/woodson/thehub/logs/backup_*.sql.gz
```

### Manual Execution
Test the script manually before scheduling:
```bash
/var/www/woodson/thehub/cli/db-maintenance.sh
```

## Cron Schedule Reference

```
# ┌───────────── minute (0-59)
# │ ┌───────────── hour (0-23)
# │ │ ┌───────────── day of month (1-31)
# │ │ │ ┌───────────── month (1-12)
# │ │ │ │ ┌───────────── day of week (0-7, Sunday=0 or 7)
# │ │ │ │ │
# * * * * * command
```

### Common Schedules

```cron
# Every day at 3 AM
0 3 * * * /var/www/woodson/thehub/cli/db-maintenance.sh

# Every Sunday at 3 AM (recommended)
0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh

# Every Monday at 2 AM
0 2 * * 1 /var/www/woodson/thehub/cli/db-maintenance.sh

# First day of month at 4 AM
0 4 1 * * /var/www/woodson/thehub/cli/db-maintenance.sh
```

## Troubleshooting

### Job Not Running?

1. **Check cron service:**
   ```bash
   sudo systemctl status cron
   ```

2. **Check cron logs:**
   ```bash
   sudo grep CRON /var/log/syslog | tail -20
   ```

3. **Verify script permissions:**
   ```bash
   ls -l /var/www/woodson/thehub/cli/db-maintenance.sh
   ```
   Should show: `-rwxr-xr-x` (executable)

4. **Test script manually:**
   ```bash
   /var/www/woodson/thehub/cli/db-maintenance.sh
   ```
   Check for any errors

### Email Notifications

To receive email notifications when maintenance runs:

```cron
MAILTO=richard.sullivan@woodsonisd.net
0 3 * * 0 /var/www/woodson/thehub/cli/db-maintenance.sh
```

Make sure mail is configured on your server:
```bash
sudo apt-get install mailutils
```

## Backup Retention

- **Backups kept:** 30 days
- **Location:** `/var/www/woodson/thehub/logs/backup_*.sql.gz`
- **Format:** `backup_YYYYMMDD_HHMMSS.sql.gz`
- **Compression:** gzip (typical 10:1 ratio)

### Restore from Backup

```bash
# List available backups
ls -lh /var/www/woodson/thehub/logs/backup_*.sql.gz

# Restore (replace YYYYMMDD_HHMMSS with actual timestamp)
gunzip -c /var/www/woodson/thehub/logs/backup_YYYYMMDD_HHMMSS.sql.gz | \
  mysql -u $DB_USER -p'$DB_PASSWORD' woodson_hub
```

## Additional Tools

### MySQLTuner

Run monthly for performance recommendations:

```bash
cd /var/www/woodson/thehub/cli
./mysqltuner.pl --host 127.0.0.1 --user $DB_USER --pass '$DB_PASSWORD'
```

### Percona Toolkit

Check for duplicate indexes:
```bash
pt-duplicate-key-checker --host=localhost --user=$DB_USER --password='$DB_PASSWORD'
```

## Support

For issues or questions:
- Check logs: `tail -f /var/www/woodson/thehub/logs/db-maintenance.log`
- Review DEPLOYMENT.md for detailed setup
- Contact: richard.sullivan@woodsonisd.net
