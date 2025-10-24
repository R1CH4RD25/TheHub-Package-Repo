# Migration Checklist: maintenance → thehub

## Overview
Renaming the application from "maintenance.woodsonisd.net" to "hub.woodsonisd.net"

## Automated Steps (via script)
Run: `sudo ./migrate-to-thehub.sh`

- [x] Update .env file with new domain and paths
- [x] Disable old Apache sites
- [x] Rename directory: /var/www/woodson/maintenance → /var/www/woodson/thehub
- [x] Install new Apache configuration
- [x] Enable new site
- [x] Reload Apache

## Manual Steps Required

### 1. DNS Configuration
- [ ] Update DNS A record: `hub.woodsonisd.net` → Server IP
- [ ] Wait for DNS propagation (check with: `nslookup hub.woodsonisd.net`)
- [ ] Verify: `ping hub.woodsonisd.net`

### 2. SSL Certificate (After DNS)
```bash
sudo certbot --apache -d hub.woodsonisd.net
```
- [ ] Run Certbot
- [ ] Verify SSL: https://hub.woodsonisd.net
- [ ] Check auto-renewal: `sudo certbot renew --dry-run`

### 3. Google OAuth Console
URL: https://console.cloud.google.com/apis/credentials

- [ ] Add new Authorized redirect URI: `https://hub.woodsonisd.net/google_login.php`
- [ ] Test login with Google OAuth
- [ ] Optional: Remove old URI after testing: `https://maintenance.woodsonisd.net/google_login.php`

### 4. Google Workspace (Optional)
If using email notifications:
- [ ] Create/configure `hub@woodsonisd.net` email alias or account
- [ ] Update SMTP settings in Google Admin Console if needed

### 5. Testing
- [ ] Test login: https://hub.woodsonisd.net
- [ ] Verify user sees correct hub (Staff/Student)
- [ ] Test section access
- [ ] Test form submissions
- [ ] Check admin dashboard
- [ ] Verify role management works

### 6. Bookmarks & Documentation
- [ ] Update internal documentation
- [ ] Notify staff of new URL
- [ ] Update any external links/bookmarks

## Rollback Plan
If something goes wrong:

```bash
# Disable new site
sudo a2dissite hub.woodsonisd.net.conf

# Rename back
sudo mv /var/www/woodson/thehub /var/www/woodson/maintenance

# Re-enable old site
sudo a2ensite maintenance.woodsonisd.net.conf
sudo a2ensite maintenance.woodsonisd.net-le-ssl.conf

# Restore .env (if you made a backup)
sudo cp /var/www/woodson/maintenance/.env.backup /var/www/woodson/maintenance/.env

# Reload Apache
sudo systemctl reload apache2
```

## Verification Commands

```bash
# Check Apache configuration
apache2ctl -S | grep hub

# Check if site is responding
curl -I https://hub.woodsonisd.net

# Check logs
tail -f /var/log/apache2/hub.woodsonisd.net-error.log
tail -f /var/log/apache2/hub.woodsonisd.net-access.log

# Check .env settings
grep "APP_URL\|GOOGLE_REDIRECT" /var/www/woodson/thehub/.env
```

## Post-Migration Notes

### Log Files
- Old logs: `/var/log/apache2/maintenance.woodsonisd.net-*.log`
- New logs: `/var/log/apache2/hub.woodsonisd.net-*.log`

### SSL Certificates
- Old: `/etc/letsencrypt/live/maintenance.woodsonisd.net/`
- New: `/etc/letsencrypt/live/hub.woodsonisd.net/`

### Database
No changes needed - database name remains `woodson_maintenance`

### Sessions
Sessions stored in `/var/www/woodson/thehub/sessions/` (no change needed, just new path)
